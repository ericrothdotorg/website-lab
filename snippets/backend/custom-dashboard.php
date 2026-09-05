<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// Day boundary in WP local time. wp_er_post_stats.created_at is written with
// current_time('mysql'), but MySQL runs on UTC — CURDATE() would be off by the
// timezone offset.
if (!function_exists('er_today_start')) {
	function er_today_start() {
		return current_time('Y-m-d') . ' 00:00:00';
	}
}

// ============================================================
// THEME-COUPLING MARKERS (search these before/after a theme switch):
//   THEME RELATED = hard coupling; breaks/orphans on switch — must fix.
//   THEME REVIEW  = soft coupling; reads a theme-defined value, won't
//                   break but the value shifts — verify.
// ============================================================

// ======================================
// SHARED HELPERS
// ======================================

// Renders a row of link buttons. Used by every widget that shows a button row,
// so spacing and markup stay identical everywhere.
function custom_render_button_row(array $links, $style = '') {
	echo '<div class="cd-widget cd-flex"' . ($style ? ' style="' . esc_attr($style) . '"' : '') . '>';
	foreach ($links as $label => $url) {
		echo '<a href="' . esc_url($url) . '" target="_blank" class="button">' . esc_html($label) . '</a>';
	}
	echo '</div>';
}

// ======================================
// 📇 AT A GLANCE
// ======================================

// Add CPTs
add_filter('dashboard_glance_items', 'custom_filter_dashboard_glance_items');
function custom_filter_dashboard_glance_items($items) {
	$post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
	foreach ($post_types as $pt) {
		$count = wp_count_posts($pt->name)->publish;
		if ($count) {
			$items[] = sprintf(
				'<a href="edit.php?post_type=%1$s" target="_blank" class="cd-link">%2$s %3$s</a>',
				esc_attr($pt->name),
				number_format_i18n($count),
				esc_html($pt->labels->name)
			);
		}
	}
	return $items;
}

// ======================================
// 🎨 THEME SNAPSHOT - THEME RELATED
// ======================================

function custom_render_theme_snapshot_widget() {
	$theme     = wp_get_theme();
	$theme_dir = get_theme_root() . '/' . $theme->get_stylesheet();
	$updated   = wp_date('F j, Y', filemtime($theme_dir . '/style.css'));

	printf('<p>Theme is <strong>%s</strong> (v%s). Last updated: <strong>%s</strong></p>',
		esc_html($theme->get('Name')),
		esc_html($theme->get('Version')),
		esc_html($updated)
	);
	// THEME RELATED — hardcoded parent-theme changelog URL (olliewp.com).
	// Points to the current theme's docs; update or remove on a theme switch.
	custom_render_button_row([
		'Site Editor'    => admin_url('site-editor.php'),
		'View Changelog' => 'https://olliewp.com/docs/ollie-block-theme/ollie-changelog/',
	]);
}

// ======================================
// 📌 EDITING RULES — FILE-BASED REMINDER
// ======================================

function custom_render_editing_rules_widget() {
	echo '<div style="line-height: 1.6;">';
	echo '<p style="margin: 0 0 10px;"><strong class="cd-alert">This Site is file-based.</strong></p>';
	echo '<ul style="margin: 0 0 10px; padding-left: 18px; list-style: disc;">';
	echo '<li>Templates &amp; Parts → Edit the Theme Files (<code>Templates/</code>, <code>Parts/</code>) directly.</li>';
	echo '<li>Styles → Edit <code>theme.json</code>, <code>style.css</code>, <code>snippet.css</code></li>';
	echo '<li><strong class="cd-alert">Never</strong> edit in Appearance → Editor cuz saving there creates a DB Copy that silently overrides the File.</li>';
	echo '</ul>';
	echo '<p style="margin: 0;"><span class="cd-success cd-bold">OK to edit in the Editor</span>: The <strong>Synced Patterns</strong> (→ <strong>Design Blocks</strong>) and the <strong>Nav Menus</strong> cuz these have no File Form.</p>';
	echo '</div>';
}

// ======================================
// 🌀 HOSTING & CODE REPO
// ======================================

function custom_render_hosting_repo_widget() {
	custom_render_button_row([
		'🔐 Login'   => 'https://auth.hostinger.com/login',
		'📬 Webmail' => 'https://mail.hostinger.com/',
		'🧠 AI'      => admin_url('admin.php?page=hostinger-ai-assistant'),
	]);
	custom_render_button_row([
		'💾 GitHub'        => 'https://github.com/ericrothdotorg',
		'🎨 Design Blocks' => admin_url('themes.php?page=design-block-tracker'),
		'✂️ Snippets'      => admin_url('admin.php?page=snippets'),
	], 'margin-top: 6px;');
}

// ======================================
// 🔗 QUICK LINKS
// ======================================

function custom_render_quick_links_widget() {
	$groups = [
		'🤖 AI Chatbots' => [
			'✨ Copilot'  => 'https://m365.cloud.microsoft/chat',
			'✨ Frontier' => 'https://stride.microsoft.com/',
			'✨ Claude'   => 'https://claude.ai/',
			'✨ DS'       => 'https://chat.deepseek.com/',
		],
		'🎁 Sponsor Channels' => [
			'💰 GitHub'  => 'https://github.com/ericrothdotorg',
			'💰 Patreon' => 'https://www.patreon.com/cw/ericrothdotorg',
			'💰 PayPal'  => 'https://www.paypal.com/paypalme/ericrothdotorg',
			'💰 BMC'     => 'https://buymeacoffee.com/ericrothdotorg',
		],
	];
	$first = true;
	foreach ($groups as $heading => $links) {
		echo '<p class="cd-muted cd-bold" style="margin: ' . ($first ? '0' : '15px') . ' 0 8px;">' . esc_html($heading) . '</p>';
		custom_render_button_row($links);
		$first = false;
	}
}

// ======================================
// 🗓️ RECENT SITE ACTIVITY
// ======================================

function custom_render_activity_widget() {
	global $wpdb;
	$table = $wpdb->prefix . 'er_post_stats';
	$today = er_today_start();

	$cached = get_transient('custom_activity_stats');
	if ($cached === false) {
		// Views, likes and dislikes come from the "Stats Engine" snippet — the same
		// source the frontend shortcodes read. If the engine is ever switched off,
		// this shows zeros instead of crashing.
		$stats = function_exists('er_stats_snapshot') ? er_stats_snapshot() : [
			'views_today'    => 0, 'views_total'    => 0,
			'likes_today'    => 0, 'likes_total'    => 0,
			'dislikes_today' => 0, 'dislikes_total' => 0,
		];
		$cached = array_merge($stats, [
			'contact_today'     => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_contact_messages WHERE DATE(submitted_at) = CURDATE()"),
			'contact_total'     => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_contact_messages"),
			'subscribers_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_subscribers WHERE status = 'active' AND DATE(created_at) = CURDATE()"),
			'subscribers_total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_subscribers WHERE status = 'active'"),
		]);
		set_transient('custom_activity_stats', $cached, 5 * MINUTE_IN_SECONDS);
	}

	// Posts reacted to today. Read live on every load, so a new vote shows up
	// immediately instead of waiting out the five-minute transient above.
	$posts_reacted = function($type) use ($wpdb, $table, $today) {
		return $wpdb->get_results($wpdb->prepare("
			SELECT DISTINCT p.ID, p.post_title, p.post_type
			FROM {$table} ps
			JOIN {$wpdb->posts} p ON p.ID = ps.post_id
			WHERE ps.type = %s AND ps.row_type = 'event'
			  AND ps.created_at >= %s
			  AND p.post_status = 'publish'
			ORDER BY p.post_title ASC
		", $type, $today));
	};
	$liked_today    = $posts_reacted('like');
	$disliked_today = $posts_reacted('dislike');

	$format = fn($num) => number_format_i18n((int) $num, 0);
	// Rows open themselves when there is something real to see, so a real vote
	// never hides behind a click.
	$fold = fn($posts) => empty($posts) ? 'none' : 'block';
	// Fingerprint of the current list. Changes when a different post is reacted to.
	$signature = fn($posts) => implode('.', array_map(fn($p) => (int) $p->ID, $posts));

	$render_today_list = function($posts) {
		if (empty($posts)) {
			echo '<li style="font-style: italic;">Nothing to review.</li>';
			return;
		}
		foreach ($posts as $p) {
			$type_label = ucfirst(str_replace('-', ' ', $p->post_type));
			echo '<li style="margin-bottom: 5px;">';
			echo '<a href="' . esc_url(get_edit_post_link($p->ID)) . '" target="_blank">' . esc_html($p->post_title) . '</a>';
			echo ' <span class="cd-muted" style="font-size: 11px;">(' . esc_html($type_label) . ')</span>';
			echo ' <a href="' . esc_url(get_permalink($p->ID)) . '" target="_blank" class="cd-muted" style="font-size: 11px;">↗</a>';
			echo '</li>';
		}
	};

	$reaction_row = function($icon, $label, $slug, $today_val, $total_val, $posts)
	                use ($format, $fold, $signature, $render_today_list) {
		echo '<li>' . $icon . ' ' . $label . ': ';
		echo '<span class="cd-toggle cd-summary" data-target="' . esc_attr($slug) . '-today">' . $format($today_val) . ' today</span>';
		echo ' / <strong>' . $format($total_val) . '</strong> total';
		echo '<ul id="' . esc_attr($slug) . '-today" data-signature="' . esc_attr($signature($posts)) . '" style="display:' . $fold($posts) . '; margin: 8px 0 4px 16px; font-size: 13px; line-height: 1.8;">';
		$render_today_list($posts);
		echo '</ul></li>';
	};

	echo '<ul style="line-height: 1.5;">';
	echo '<li>📬 Contact Messages: <strong class="cd-alert">' . $format($cached['contact_today']) . '</strong> today / <strong>' . $format($cached['contact_total']) . '</strong> total</li>';
	echo '<li>📩 Subscribers: <strong class="cd-alert">' . $format($cached['subscribers_today']) . '</strong> new today / <strong>' . $format($cached['subscribers_total']) . '</strong> total</li>';
	echo '<li>👁️ Views: <strong class="cd-alert">' . $format($cached['views_today']) . '</strong> today / <strong>' . $format($cached['views_total']) . '</strong> total</li>';
	$reaction_row('👍', 'Likes',    'likes',    $cached['likes_today'],    $cached['likes_total'],    $liked_today);
	$reaction_row('👎', 'Dislikes', 'dislikes', $cached['dislikes_today'], $cached['dislikes_total'], $disliked_today);
	echo '</ul>';
}

// ======================================
// 📊 ANALYTICS TOOLKIT
// ======================================

function custom_render_analytics_toolkit() {
	custom_handle_youtube_check_submission();
	custom_render_external_tools_buttons();
	custom_render_site_metrics();
	custom_render_tools_and_actions();
}

function custom_handle_youtube_check_submission() {
	if (!isset($_POST['check_broken_yt'])) return;
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized access');
	}
	check_admin_referer('check_broken_yt_action', 'check_broken_yt_nonce');
	update_option('custom_broken_yt_results', custom_check_broken_yt_links());
	update_option('custom_last_yt_check', time());
}

function custom_render_external_tools_buttons() {
	$site = urlencode(home_url('/'));
	custom_render_button_row([
		'🧩 Google Rich' => 'https://search.google.com/test/rich-results?url=' . $site,
		'🧩 schema.org'  => 'https://validator.schema.org/?url=' . $site,
	]);
	custom_render_button_row([
		'🚀 PageSpeed'     => 'https://pagespeed.web.dev/report?url=' . $site . '&hl=en',
		'🚀 WebPageTest'   => 'https://www.webpagetest.org/?url=' . $site,
		'♿ Accessibility' => 'https://wave.webaim.org/report#/' . $site,
	], 'margin-top: 10px;');
}

function custom_render_site_metrics() {
	global $wpdb;
	$total_media    = array_sum((array) wp_count_attachments());
	$db_table_count = count($wpdb->get_col('SHOW TABLES'));

	// First public IPv4 we can find across the usual proxy headers.
	$visitor_ip = '';
	foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $header) {
		if (empty($_SERVER[$header])) continue;
		foreach (explode(',', $_SERVER[$header]) as $candidate) {
			$candidate = trim($candidate);
			if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$visitor_ip = $candidate;
				break 2;
			}
		}
	}

	// Shows the IPv4 when there is one, otherwise falls back to a city lookup.
	if ($visitor_ip !== '') {
		$cd_display = '🧊 Your IP: <strong>' . esc_html($visitor_ip) . '</strong>';
	} else {
		$raw = $_SERVER['REMOTE_ADDR'] ?? '';
		$loc = 'Unknown';
		if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
			$resp = wp_remote_get("http://ip-api.com/json/{$raw}?fields=status,city,country", ['timeout' => 5]);
			if (!is_wp_error($resp)) {
				$data = json_decode(wp_remote_retrieve_body($resp), true);
				if (isset($data['status']) && $data['status'] === 'success') {
					$loc = trim(($data['city'] ?? '') . ', ' . ($data['country'] ?? ''), ', ');
				}
			}
		}
		$cd_display = '📍 Place: <strong>' . esc_html($loc) . '</strong>';
	}

	if (!function_exists('get_plugins')) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugin_count = count(get_plugins());

	echo '<div class="cd-widget cd-flex" style="margin-top: 15px;">';
	echo '<div style="width: calc(50% - 5px);">';
	echo '<p style="margin: 0 0 5px;">🖼️ Media Files: <strong>' . number_format_i18n($total_media) . '</strong></p>';
	echo '<p style="margin: 0;">🧵 InnoDB Tables: <strong>' . number_format_i18n($db_table_count) . '</strong></p>';
	echo '</div>';
	echo '<div style="width: calc(50% - 5px);">';
	echo '<p style="margin: 0 0 5px;">' . $cd_display . '</p>';
	echo '<p style="margin: 0;">🔌 Active Plugins Installed: <strong>' . number_format_i18n($plugin_count) . '</strong></p>';
	echo '</div>';
	echo '</div>';
}

function custom_render_tools_and_actions() {
	echo '<div style="margin-top: 15px;">';
	custom_render_button_row([
		'🛠️ W3.org Dev Tools' => 'https://www.w3.org/developers/tools/',
		'📈 MS Clarity'        => 'https://clarity.microsoft.com/projects/view/eic7b2e9o1/dashboard',
	], 'margin-bottom: 10px;');

	echo '<div class="cd-widget cd-flex" style="align-items: center; gap: 15px;">';
	echo '<form method="post" class="cd-form">';
	wp_nonce_field('check_broken_yt_action', 'check_broken_yt_nonce');
	echo '<button type="submit" name="check_broken_yt" class="button">🔍 Broken YT Links</button>';
	echo '</form>';

	$results      = get_option('custom_broken_yt_results', []);
	$last_check   = get_option('custom_last_yt_check', 0);
	$broken_count = !empty($results['broken_count']) ? (int) $results['broken_count'] : 0;

	echo '<div style="display: flex; flex-direction: column; line-height: 1.4;">';
	echo '<span>🔴 Broken YT Links: <strong class="cd-alert">' . $broken_count . '</strong></span>';
	echo '<em class="cd-date">Last checked: ';
	echo $last_check
		? esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_check))
		: 'Never';
	echo '</em>';
	echo '</div>';
	echo '</div>';

	if (!empty($results['broken_posts'])) {
		echo '<details style="margin-top: 10px;">';
		echo '<summary class="cd-summary" style="margin-bottom: 15px;">Broken Links Locations</summary>';
		echo '<ul>';
		foreach ($results['broken_posts'] as $post_id => $video_ids) {
			echo '<li><a href="' . esc_url(get_edit_post_link($post_id)) . '" target="_blank">' . esc_html(get_the_title($post_id)) . '</a>: ';
			echo esc_html(implode(', ', $video_ids)) . '</li>';
		}
		echo '</ul>';
		echo '</details>';
	}
	echo '</div>';
}

// Video IDs that always fail the oEmbed check but are fine in the page.
function custom_yt_excluded_ids() {
	return ['-cW'];
}

function custom_check_broken_yt_links() {
	$broken_links   = 0;
	$broken_posts   = [];
	$checked_videos = [];
	$excluded       = custom_yt_excluded_ids();

	$query = new WP_Query([
		'post_type'      => ['post', 'page', 'my-interests', 'my-traits'],
		'posts_per_page' => -1,
	]);

	foreach ($query->posts as $post) {
		preg_match_all('/https:\/\/(?:www\.)?youtube\.com\/(?:watch\?v=|embed\/)([a-zA-Z0-9_-]+)/', $post->post_content, $matches);
		if (empty($matches[1])) continue;
		
		// A block embed writes the same URL twice (block settings + wrapper),
		// so each video is only counted once per post.
		foreach (array_unique($matches[1]) as $video_id) {
			if (in_array($video_id, $excluded, true)) continue;

			// Each video is only fetched once, however many posts embed it.
			if (!isset($checked_videos[$video_id])) {
				$url = 'https://www.youtube.com/oembed?url=' . urlencode("https://www.youtube.com/watch?v={$video_id}") . '&format=json';
				$response = wp_remote_get($url, [
					'timeout'    => 10,
					'user-agent' => 'Mozilla/5.0 (compatible; LinkChecker/1.0)',
				]);
				$code = wp_remote_retrieve_response_code($response);
				$checked_videos[$video_id] = !(is_wp_error($response) || !in_array($code, [200, 401, 403], true));
			}

			if ($checked_videos[$video_id] === false) {
				$broken_links++;
				if (!in_array($video_id, $broken_posts[$post->ID] ?? [], true)) {
					$broken_posts[$post->ID][] = $video_id;
				}
			}
		}
	}
	wp_reset_postdata();

	return ['broken_count' => $broken_links, 'broken_posts' => $broken_posts];
}

// ======================================
// 🧹 OPTIMIZE & CLEAN-UP
// ======================================

function custom_render_innodb_cleanup() {
	custom_handle_cleanup_submission();
	custom_render_action_buttons();
	custom_render_database_stats();
	custom_render_cleanup_history();
}

function custom_handle_cleanup_submission() {
	if (!isset($_POST['er_run_full_cleanup'])) return;
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized access');
	}
	check_admin_referer('custom_cleanup_action', 'custom_cleanup_nonce');
	$result = custom_run_innodb_cleanup();
	update_option('custom_last_cleanup', time());
	update_option('custom_last_cleanup_result', $result['message']);
	update_option('custom_last_cleanup_success', $result['success']);
}

function custom_render_action_buttons() {
	echo '<div class="cd-widget cd-flex" style="align-items: center;">';
	echo '<form method="post" class="cd-form">';
	wp_nonce_field('custom_cleanup_action', 'custom_cleanup_nonce');
	echo '<button type="submit" name="er_run_full_cleanup" class="button">🧵 InnoDB Cleanup</button>';
	echo '</form>';
	echo '<a href="' . esc_url(admin_url('admin.php?page=litespeed-db_optm')) . '" class="button" target="_blank">🛢️ LiteSpeed DB</a>';
	$purge = admin_url('index.php?LSCWP_CTRL=purge&LSCWP_NONCE=' . wp_create_nonce('purge') . '&litespeed_type=purge_all');
	echo '<a href="' . esc_url($purge) . '" class="button">⚡ Purge All</a>';
	echo '</div>';
}

function custom_render_database_stats() {
	global $wpdb;
	$rows = [
		['Content Meta Rows', (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}"), 'meta'],
		['Term Meta Rows',    (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->termmeta}"), 'meta'],
		['User Meta Rows',    (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta}"), 'meta'],
		['Post Stats Rows',   (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_post_stats"), 'meta'],
		['Map View Rows',     (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_map_views"), 'map_views'],
	];
	$total = 0;

	echo '<div style="margin-top: 15px;">';
	foreach ($rows as [$label, $count, $profile]) {
		$total += $count;
		$status = custom_get_health_status($count, $profile);
		echo '<p style="margin: 5px 0;">' . esc_html($label) . ': <strong>' . number_format_i18n($count) . '</strong> ';
		echo '<span class="' . esc_attr($status[0]) . '">— ' . esc_html($status[1]) . '</span></p>';
	}
	echo '<p style="margin: 5px 0;">TOTAL Meta Rows: <strong>' . number_format_i18n($total) . '</strong></p>';
	echo '</div>';
}

// HEALTH STATUS — Every green / orange / red label on the "Optimize & Clean-Up" stat rows is decided here and nowhere else. To change a cutoff, change it here.
// How it works: Each row passes a "profile" telling this function which size limits apply. Up to and including the orange number = green ("Healthy"). Above orange = orange ("Moderate bloat"). Above red = red ("Consider a cleanup"). Rows with no profile use 'meta' by default.
//   'meta'      → postmeta, usermeta, termmeta, er_post_stats. Big tables, high limits: orange 10k, red 50k.
//   'map_views' → er_map_views. Normal size is ~11,500 rows (about 128 visits a day kept for 90 days), so its limits sit higher than that on purpose: orange 15k, red 30k.

function custom_get_health_status($count, $profile = 'meta') {
	[$orange, $red] = ($profile === 'map_views') ? [15000, 30000] : [10000, 50000];
	if ($count > $red)    return ['cd-alert',   'Consider running a cleanup.'];
	if ($count > $orange) return ['cd-warning', 'Moderate bloat detected.'];
	return                       ['cd-success', 'Healthy state.'];
}

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

function custom_cleanup_transients($wpdb, $safe_delete) {
	$deleted = 0;
	$deleted += $safe_delete("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'", 'Transient cleanup');
	$deleted += $safe_delete("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()", 'Expired transient timeout cleanup');
	return $deleted;
}

function custom_cleanup_old_data($wpdb, $safe_delete) {
	$deleted = 0;
	if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}actionscheduler_actions'") === "{$wpdb->prefix}actionscheduler_actions") {
		$deleted += $safe_delete("DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE status = 'complete' AND scheduled_date_gmt < NOW() - INTERVAL 30 DAY", 'ActionScheduler cleanup');
	}
	$deleted += $safe_delete("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_content = ''", 'Auto-draft cleanup');
	$deleted += $safe_delete($wpdb->prepare("DELETE FROM {$wpdb->prefix}er_post_stats WHERE row_type = 'event' AND created_at < %s", er_today_start()), 'Old vote/view event log cleanup');
	$deleted += $safe_delete("DELETE FROM {$wpdb->prefix}er_map_views WHERE viewed_at < NOW() - INTERVAL 90 DAY", 'Old map view log cleanup (90-day retention)');
	$deleted += $safe_delete("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_modified < NOW() - INTERVAL 1 DAY", 'Trash posts cleanup');
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

function custom_render_cleanup_history() {
	$last_cleanup = get_option('custom_last_cleanup');
	if (!$last_cleanup) return;

	$last_result  = get_option('custom_last_cleanup_result');
	$last_success = get_option('custom_last_cleanup_success', true);
	if ($last_result) {
		$result_class = $last_success ? 'cd-success' : 'cd-alert';
		echo '<p style="margin: 10px 0;" class="' . esc_attr($result_class) . '"><strong>' . esc_html($last_result) . '</strong></p>';
	}
	echo '<p style="margin: 5px 0;"><em>Last cleanup: ' .
		esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_cleanup)) . '</em></p>';
}

// ======================================
// 📰 RSS FEEDS
// ======================================

function custom_render_blog_rss_widget() {
	custom_render_rss_widget(home_url('/feed/'));
}

function custom_render_interests_rss_widget() {
	custom_render_rss_widget(home_url('/my-interests/feed/'));
}

// Shared renderer. Prints every item; the JavaScript pages through them.
function custom_render_rss_widget($feed_url) {
	echo '<div class="rss-widget">';
	echo '<div class="rss-nav" style="display: flex; align-items: center; margin: 0 0 10px;"><span class="rss-counter"></span></div>';
	echo '<div class="rss-items" style="display: none;">';

	$items = custom_get_rss_items($feed_url, 15);
	if (is_wp_error($items)) {
		echo '<p>🚫 Error fetching feed: ' . esc_html($items->get_error_message()) . '</p>';
	} else {
		foreach ($items as $item) {
			$desc = wp_strip_all_tags($item->get_description());
			// Feeds end on a stray trailing word from the "read more" link — drop it.
			$desc      = preg_replace('/\s+\b\w{1,10}\b$/u', '', $desc);
			$excerpt   = $desc ? wp_trim_words($desc, 30) : 'No description available';
			$timestamp = $item->get_date('U');
			$date      = $timestamp ? wp_date('F j, Y', $timestamp) : 'Unknown date';

			$edit_link = '';
			if (preg_match('/p=(\d+)/', $item->get_id(), $matches)) {
				$edit_link = get_edit_post_link((int) $matches[1]);
			}

			echo '<div class="rss-item" style="display: none; margin-bottom: 15px;">';
			echo '<div><a href="' . esc_url($item->get_link()) . '" target="_blank" class="cd-link">' . esc_html($item->get_title()) . '</a> – ';
			echo '<span class="cd-muted cd-date">🗓️ Published: <strong>' . esc_html($date) . '</strong></span>';
			if ($edit_link) {
				echo ' – <a href="' . esc_url($edit_link) . '" target="_blank" class="cd-link">Edit</a>';
			}
			echo '</div>';
			echo '<p style="margin: 5px 0;">' . esc_html($excerpt) . '</p>';
			echo '</div>';
		}
	}

	echo '</div>';
	echo '</div>';
}

function custom_get_rss_items($feed_url, $max_items) {
	if (!function_exists('fetch_feed')) {
		require_once ABSPATH . WPINC . '/feed.php';
	}
	$rss = fetch_feed($feed_url);
	if (is_wp_error($rss)) return $rss;
	$rss->set_cache_duration(1800);
	return $rss->get_items(0, $max_items);
}

// ======================================
// INLINE CSS & JAVASCRIPT
// ======================================

add_action('admin_footer', 'custom_dashboard_inline_assets');
function custom_dashboard_inline_assets() {
	$screen = get_current_screen();
	if (!$screen || $screen->id !== 'dashboard') return;

	echo '<style>
		/* === Root Colour Variables === */
		#wpwrap {
			--cd-blue:   #1e73be;
			--cd-red:    #c53030;
			--cd-muted:  #808080;
			--cd-green:  green;
			--cd-orange: orange;
		}
		/* One text size for every widget body, so the boxes match each other. */
		#dashboard-widgets .inside { font-size: 14px; }
		#dashboard-widgets .inside strong { font-weight: bold; }
		#dashboard-widgets a { color: var(--cd-blue); text-decoration: none; }
		#dashboard-widgets a:hover { color: var(--cd-red); }
		/* === Buttons === */
		.cd-widget {
			--cd-btn-bg-top:    #fafbfc;
			--cd-btn-bg-bottom: #e1e8ed;
			--cd-btn-border:    #8da6b9;
			--cd-btn-text:      #3a4f66;
			--cd-btn-hover:     #fafbfc;
			--cd-btn-shadow:    rgba(0,0,0,.08);
		}
		.cd-widget .button,
		.cd-widget button.button {
			background: linear-gradient(to bottom, var(--cd-btn-bg-top), var(--cd-btn-bg-bottom));
			border: 1px solid var(--cd-btn-border);
			border-radius: 4px;
			color: var(--cd-btn-text);
			min-height: 30px;
			padding: 0 12px;
			font-weight: normal;
			line-height: 24px;
			box-shadow: inset 0 1px 0 rgba(255,255,255,.7), 0 1px 2px var(--cd-btn-shadow);
			transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 5px;
			cursor: pointer;
		}
		.cd-widget .button:hover,
		.cd-widget button.button:hover {
			color: var(--cd-btn-text);
			background: var(--cd-btn-hover);
			border-color: var(--cd-btn-border);
			box-shadow: inset 0 1px 0 rgba(255,255,255,.8), 0 1px 3px rgba(0,0,0,.12);
		}
		/* === Utility Classes === */
		.cd-link          { color: var(--cd-blue); text-decoration: none; font-weight: bold; }
		.cd-link:hover    { color: var(--cd-red); }
		.cd-summary       { color: var(--cd-blue); font-weight: bold; cursor: pointer; }
		.cd-summary:hover { color: var(--cd-red); }
		.cd-alert         { color: var(--cd-red); }
		.cd-success       { color: var(--cd-green); }
		.cd-warning       { color: var(--cd-orange); }
		.cd-muted         { color: var(--cd-muted); }
		.cd-bold          { font-weight: bold; }
		.cd-date          { font-size: 12px; }
		.cd-flex          { display: flex; gap: 6px; flex-wrap: wrap; }
		.cd-form          { margin: 0; }
		/* === Widget Header === */
		#dashboard-widgets .postbox-header .hndle {
			display: flex;
			justify-content: flex-start;
			align-items: center;
			gap: 5px;
		}
	</style>';

	echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {

	// RSS Feed Widgets: paged navigation, 3 items at a time, wrapping at both ends.
	document.querySelectorAll('.rss-widget').forEach(widget => {
		const nav     = widget.querySelector('.rss-nav');
		const list    = widget.querySelector('.rss-items');
		const counter = widget.querySelector('.rss-counter');
		if (!nav || !list) return;

		const items = list.querySelectorAll('.rss-item');
		if (items.length === 0) {
			if (counter) counter.textContent = 'No items found';
			return;
		}

		// Build the back / forward Buttons and put them in the nav Bar.
		// Both stay visible on every Page, so neither can shift under the cursor.
		const group = document.createElement('div');
		group.style.marginLeft = 'auto';
		group.style.display = 'flex';
		group.style.gap = '6px';
		const makeBtn = (glyph, title) => {
			const btn = document.createElement('button');
			btn.innerHTML = glyph;
			btn.title = title;
			btn.style.padding = '6px';
			btn.style.cursor = 'pointer';
			group.appendChild(btn);
			return btn;
		};
		const prevBtn = makeBtn('⬅️', 'Previous');
		const nextBtn = makeBtn('➡️', 'Next');
		nav.appendChild(group);

		const batchSize = 3;
		const lastStart = Math.floor((items.length - 1) / batchSize) * batchSize;
		let currentStart = 0;

		function renderBatch(start) {
			currentStart = start;
			items.forEach((item, i) => {
				item.style.display = (i >= start && i < start + batchSize) ? 'block' : 'none';
			});
			if (counter) {
				const endItem = Math.min(start + batchSize, items.length);
				counter.textContent = 'Showing ' + (start + 1) + '-' + endItem + ' of ' + items.length;
			}
		}

		prevBtn.addEventListener('click', () => {
			const prevStart = currentStart - batchSize;
			renderBatch(prevStart < 0 ? lastStart : prevStart);
		});
		nextBtn.addEventListener('click', () => {
			const nextStart = currentStart + batchSize;
			renderBatch(nextStart >= items.length ? 0 : nextStart);
		});

		renderBatch(0);
		list.style.display = 'block';
	});

});

// Recent Site Activity: fold / unfold the Likes and Dislikes Lists.
// Runs outside the page-ready wrapper so a folded Row does not flash open first.
// The Widget markup sits above this Script, so the Elements already exist.
// A Row with real Votes opens by itself, but stays folded once it gots folded —
// until a different Post shows up in the List, which changes the signature.
document.querySelectorAll('.cd-toggle').forEach(trigger => {
	const target = document.getElementById(trigger.dataset.target);
	if (!target) return;
	const key = 'cdFolded_' + trigger.dataset.target;
	const signature = target.dataset.signature || '';
	if (signature === '') {
		localStorage.removeItem(key);
	} else if (localStorage.getItem(key) === signature) {
		target.style.display = 'none';
	}
	trigger.addEventListener('click', () => {
		const opening = target.style.display === 'none';
		target.style.display = opening ? 'block' : 'none';
		if (opening) {
			localStorage.removeItem(key);
		} else {
			localStorage.setItem(key, signature);
		}
	});
});
</script>
HTML;
}

// ======================================
// HOOK REGISTRATION
// ======================================

add_action('wp_dashboard_setup', function () {
	if (!current_user_can('manage_options')) return;

	// 📇 At a Glance — Rename Title
	global $wp_meta_boxes;
	if (isset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now'])) {
		$wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']['title'] = '📇 At a Glance';
	}

	// Register all Widgets
	wp_add_dashboard_widget('custom_theme_snapshot',       '🎨 Theme Snapshot',        'custom_render_theme_snapshot_widget'); // THEME RELATED
	wp_add_dashboard_widget('custom_editing_rules',        '📌 Editing Rules',         'custom_render_editing_rules_widget');
	wp_add_dashboard_widget('hosting_code_repo',           '🌀 Hosting & Code Repos',  'custom_render_hosting_repo_widget');
	wp_add_dashboard_widget('quick_links',                 '🔗 Quick Links',           'custom_render_quick_links_widget');
	wp_add_dashboard_widget('custom_activity_alerts',      '🗓️ Recent Site Activity',  'custom_render_activity_widget');
	wp_add_dashboard_widget('custom_analytics_toolkit',    '📊 Analytics Toolkit',     'custom_render_analytics_toolkit');
	wp_add_dashboard_widget('custom_optimize_and_cleanup', '🧹 Optimize & Clean-Up',   'custom_render_innodb_cleanup');
	wp_add_dashboard_widget('custom_blog_rss_widget',      '📰 RSS Feed: My Blog',     'custom_render_blog_rss_widget');
	wp_add_dashboard_widget('custom_interests_rss_widget', '📰 RSS Feed: My Interests','custom_render_interests_rss_widget');
});
