<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ============================================================================
// DATABASE MAINTENANCE (meta, transients, logs, OPTIMIZE TABLE)
// ============================================================================
// Weekly via wp-cron (er_weekly_cleanup) and on demand via the "InnoDB
// Cleanup" button in the Custom Dashboard, which calls
// custom_run_innodb_cleanup() from here.
//
// Scope "Run snippet everywhere" is mandatory. wp-cron.php runs with
// is_admin() === false, so a callback registered from an admin-only snippet
// is invisible to the scheduler: the event fires, finds no callback,
// reschedules itself and deletes nothing. Do not move this into the Custom
// Dashboard - it would be a duplicate declaration and fatal in wp-admin.
//
// Moved here from the Stats Engine unchanged, except: only expired transients
// are deleted, and the rules for tables that clean themselves are gone.
// ============================================================================

add_action('init', function () {
	if (!wp_next_scheduled('er_weekly_cleanup')) {
		wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', 'er_weekly_cleanup');
	}
});

add_action('er_weekly_cleanup', function () {
	$result = custom_run_innodb_cleanup();
	update_option('custom_last_cleanup', time());
	update_option('custom_last_cleanup_result', $result['message']);
	update_option('custom_last_cleanup_success', $result['success']);
});

if (!function_exists('custom_run_innodb_cleanup')) {
function custom_run_innodb_cleanup() {
	global $wpdb;
	$deleted_total = 0;
	$errors = [];

	$safe_delete = function($query, $operation_name) use ($wpdb, &$errors) {
		$result = $wpdb->query($query);
		if ($result === false) {
			$errors[] = $operation_name . ' failed: ' . $wpdb->last_error;
			return 0;
		}
		return (int) $result;
	};

	$deleted_total += custom_cleanup_orphaned_data($wpdb, $safe_delete);
	$deleted_total += custom_cleanup_postmeta($wpdb, $safe_delete);
	$deleted_total += custom_cleanup_usermeta($wpdb, $safe_delete);
	$deleted_total += custom_cleanup_transients($wpdb, $safe_delete);
	$deleted_total += custom_cleanup_old_data($wpdb, $safe_delete);
	$optimized_count = custom_optimize_tables($wpdb, $errors);

	if (!empty($errors)) {
		return [
			'success' => false,
			'message' => "⚠️ Partial cleanup: {$deleted_total} rows deleted → {$optimized_count} tables optimized. Errors: " . implode(' | ', $errors),
		];
	}
	return [
		'success' => true,
		'message' => "✅ Total rows deleted: {$deleted_total} → {$optimized_count} tables optimized.",
	];
}

function custom_cleanup_orphaned_data($wpdb, $safe_delete) {
	$deleted = 0;
	$deleted += $safe_delete("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL", 'Orphaned postmeta cleanup');
	$deleted += $safe_delete("DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL", 'Orphaned term relationships cleanup');
	$deleted += $safe_delete("DELETE um FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE u.ID IS NULL", 'Orphaned usermeta cleanup');
	$deleted += $safe_delete("DELETE tm FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL", 'Orphaned termmeta cleanup');
	return $deleted;
}

function custom_cleanup_postmeta($wpdb, $safe_delete) {
	$deleted = 0;
	$safe_keys = ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_last_viewed_timestamp', 'litespeed-optimize-set', 'litespeed-optimize-size'];
	foreach ($safe_keys as $key) {
		$deleted += $safe_delete($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $key), "Postmeta cleanup ({$key})");
	}
	$deleted += $safe_delete("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_target' AND (meta_value IS NULL OR meta_value = '')", 'Empty menu item targets cleanup');
	$deleted += $safe_delete("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_oembed_%' OR meta_key LIKE '_oembed_time_%'", 'oEmbed cache cleanup');
	return $deleted;
}

function custom_cleanup_usermeta($wpdb, $safe_delete) {
	$deleted = 0;
	$safe_keys = ['_session_tokens', '_last_activity', '_woocommerce_persistent_cart'];
	foreach ($safe_keys as $key) {
		$deleted += $safe_delete($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $key), "Usermeta cleanup ({$key})");
	}
	return $deleted;
}

// Expired transients only (value and timeout row together), the same SQL as
// WordPress core's delete_expired_transients(). Transients that are still
// valid stay: wiping them all would also drop running 24-hour locks, rate
// limits and caches that are then rebuilt on the next visit.
function custom_cleanup_transients($wpdb, $safe_delete) {
	$deleted = 0;
	$deleted += $safe_delete("DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
		WHERE a.option_name LIKE '\\_transient\\_%' AND a.option_name NOT LIKE '\\_transient\\_timeout\\_%'
		  AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
		  AND b.option_value < UNIX_TIMESTAMP()", 'Expired transient cleanup');
	$deleted += $safe_delete("DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
		WHERE a.option_name LIKE '\\_site\\_transient\\_%' AND a.option_name NOT LIKE '\\_site\\_transient\\_timeout\\_%'
		  AND b.option_name = CONCAT('_site_transient_timeout_', SUBSTRING(a.option_name, 17))
		  AND b.option_value < UNIX_TIMESTAMP()", 'Expired site transient cleanup');
	return $deleted;
}

// Retention rules for data no snippet cleans itself. They are NOT background
// jobs: nothing expires on its own, the limits only apply the moment this runs.
// Not here on purpose: wp_er_post_stats (Stats Engine) and wp_er_map_views /
// wp_er_live_visitors (Visitor Tracking) are cleaned daily by their owners.
// The date columns compared with NOW() are all stored in UTC.
function custom_cleanup_old_data($wpdb, $safe_delete) {
	$deleted = 0;
	if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}actionscheduler_actions'") === "{$wpdb->prefix}actionscheduler_actions") {
		$deleted += $safe_delete("DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE status = 'complete' AND scheduled_date_gmt < NOW() - INTERVAL 30 DAY", 'ActionScheduler cleanup');
	}
	$deleted += $safe_delete("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_content = ''", 'Auto-draft cleanup');
	$deleted += $safe_delete("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_modified_gmt < NOW() - INTERVAL 1 DAY", 'Trash posts cleanup');
	$deleted += $safe_delete("DELETE FROM {$wpdb->prefix}er_subscribers WHERE status = 'pending' AND created_at < NOW() - INTERVAL 7 DAY", 'Stale pending subscriber cleanup');
	return $deleted;
}

function custom_optimize_tables($wpdb, &$errors) {
	$tables = ['postmeta', 'usermeta', 'termmeta', 'er_post_stats', 'er_map_views'];
	$optimized_count = 0;
	foreach ($tables as $table) {
		$wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}{$table}");
		if ($wpdb->last_error === '') {
			$optimized_count++;
		} else {
			$errors[] = "Failed to optimize {$table}: " . $wpdb->last_error;
		}
	}
	return $optimized_count;
}
}
