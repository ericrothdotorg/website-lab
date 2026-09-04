<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ============================================================================
// STATS ENGINE - single source for all counters
// Must load before the voting snippet and the dashboard -> low priority
// ============================================================================

// ----------------------------------------------------------------------------
// BASE CONFIGURATION
// ----------------------------------------------------------------------------
// Baseline daily rates. Change here and nowhere else.

function er_stats_base_rates() {
    return ['view' => 1000, 'like' => 156, 'dislike' => 1.6];
}

// Only these content types get counters at all.
// Keeps oembed_cache and similar internal types out of the totals.
function er_stats_tracked_types() {
    return ['post', 'page', 'my-interests', 'my-quotes', 'my-traits'];
}

// ----------------------------------------------------------------------------
// DAILY VALUE
// ----------------------------------------------------------------------------
// Deterministic per date: The same date always yields the same number, no
// matter how often the function is called. No randomness at runtime, otherwise
// the output would change on every page load.

function er_stats_synth_daily($type, $date) {
    $rates = er_stats_base_rates();
    if (!isset($rates[$type])) return 0;

    // Hash of the date, normalised to a value between 0 and 1.
    $u = hexdec(substr(hash('sha256', 'er-stats|' . $type . '|' . $date), 0, 8)) / 0xffffffff;

    // Weekday pattern. The average across the week stays at 1.0.
    $dow = (int) date('N', strtotime($date));
    $wd  = ($dow >= 6) ? 0.75 : 1.10;

    // Spread of plus / minus 25 percent so the curve is not perfectly uniform.
    $noise = 0.75 + $u * 0.5;

    return max(0, (int) round($rates[$type] * $wd * $noise));
}

// How far the day has advanced, as a smooth curve rather than linear:
// Slow in the morning, fastest around midday, flattening again in the evening.
function er_stats_day_progress() {
    $start   = strtotime(current_time('Y-m-d') . ' 00:00:00');
    $elapsed = (current_time('timestamp') - $start) / DAY_IN_SECONDS;
    $elapsed = min(1, max(0, $elapsed));
    return 0.5 - 0.5 * cos(M_PI * $elapsed);
}

// ----------------------------------------------------------------------------
// LOG
// ----------------------------------------------------------------------------
// The daily job records here what it actually applied. The display reads the
// same entry, so the daily figure and the running total cannot drift apart.

function er_stats_log_get($date) {
    $log = get_option('er_stats_synth_log', []);
    return isset($log[$date]) ? $log[$date] : null;
}

function er_stats_log_put($date, $values) {
    $log = get_option('er_stats_synth_log', []);
    $log[$date] = $values;
    if (count($log) > 90) {                       // keep only the last 90 days
        ksort($log);
        $log = array_slice($log, -90, null, true);
    }
    update_option('er_stats_synth_log', $log, false);
}

// Today's value. Computed directly if the job has not run yet.
function er_stats_today_target($type) {
    $today = current_time('Y-m-d');
    $entry = er_stats_log_get($today);
    if (is_array($entry) && isset($entry[$type])) {
        return (int) $entry[$type];
    }
    return er_stats_synth_daily($type, $today);
}

// ----------------------------------------------------------------------------
// DAILY JOB
// ----------------------------------------------------------------------------
// Distributes the daily amount across the content and writes it to the log.
// Randomness is fine here: the job runs once and the result is then fixed in the database.

function er_stats_apply_daily() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $types = er_stats_tracked_types();
    $in    = "'" . implode("','", array_map('esc_sql', $types)) . "'";
    $date  = current_time('Y-m-d');

    if (er_stats_log_get($date) !== null) return;   // already ran today

    $ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_status = 'publish' AND post_type IN ({$in})"
    );
    if (empty($ids)) return;

    $applied = [];
    foreach (['view', 'like', 'dislike'] as $type) {
        $total = er_stats_synth_daily($type, $date);
        $applied[$type] = $total;
        if ($total <= 0) continue;

        // Spread the amount across the content at random.
        $per = array_fill_keys($ids, 0);
        for ($i = 0; $i < $total; $i++) {
            $per[$ids[array_rand($ids)]]++;
        }

        // Group by amount so this becomes a few large queries instead of hundreds of small ones.
        $groups = [];
        foreach ($per as $pid => $n) {
            if ($n > 0) $groups[$n][] = (int) $pid;
        }
        foreach ($groups as $n => $pids) {
            $list = implode(',', array_map('intval', $pids));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET count = count + %d
                 WHERE type = %s AND row_type = 'total' AND post_id IN ({$list})",
                $n, $type
            ));
        }
    }

    er_stats_log_put($date, $applied);
    delete_transient('er_stats_snapshot');
    delete_transient('custom_activity_stats');
}
add_action('er_stats_daily_increment', 'er_stats_apply_daily');

// Schedule the job and clear out the old weekly ones.
// Unscheduling the legacy weekly events on every load is cheap and idempotent,
// so an older database restore gets cleaned up too.
add_action('init', function() {
    foreach (['increment_views_event', 'increment_likes_event', 'increment_dislikes_event'] as $old) {
        $ts = wp_next_scheduled($old);
        if ($ts) wp_unschedule_event($ts, $old);
    }
    if (!wp_next_scheduled('er_stats_daily_increment')) {
        wp_schedule_event(strtotime('tomorrow 00:05'), 'daily', 'er_stats_daily_increment');
    }
    if (!wp_next_scheduled('er_stats_daily_cleanup')) {
        wp_schedule_event(strtotime('tomorrow 00:15'), 'daily', 'er_stats_daily_cleanup');
    }
});

// ----------------------------------------------------------------------------
// FIGURES FOR DISPLAY AND DASHBOARD
// ----------------------------------------------------------------------------
// Single entry point for shortcodes and the dashboard. One function, one
// cache, no second copy anywhere.

function er_stats_snapshot() {
    $cached = get_transient('er_stats_snapshot');
    if ($cached !== false) return $cached;

    global $wpdb;
    $table    = $wpdb->prefix . 'er_post_stats';
    $today    = current_time('Y-m-d') . ' 00:00:00';
    $progress = er_stats_day_progress();
    $out      = [];

    foreach (['view', 'like', 'dislike'] as $type) {
        // Recorded events from today.
        $real = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE type = %s AND row_type = 'event' AND created_at >= %s", $type, $today
        ));
        // The share of the daily amount accrued so far.
        $synth = (int) round(er_stats_today_target($type) * $progress);

        $out[$type . 's_today'] = $synth + $real;
        // Recorded events kept separately for the dashboard control line.
        $out['real_' . $type . 's_today'] = $real;
        $out[$type . 's_total'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(count),0) FROM {$table} WHERE type = %s AND row_type = 'total'", $type
        ));
    }

    set_transient('er_stats_snapshot', $out, 5 * MINUTE_IN_SECONDS);
    return $out;
}

// ----------------------------------------------------------------------------
// INITIAL VALUES FOR NEW CONTENT
// ----------------------------------------------------------------------------
// Views are drawn, likes and dislikes derived from them to keep ratios consistent.

function er_stats_seed_post($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if (!in_array(get_post_type($post_id), er_stats_tracked_types(), true)) return;

    global $wpdb;
    $table  = $wpdb->prefix . 'er_post_stats';
    $rates  = er_stats_base_rates();
    $views  = rand(5000, 10000);

    // Same ratios as the rest of the site, with a little spread.
    $r_like    = $rates['like']    / $rates['view'];
    $r_dislike = $rates['dislike'] / $rates['view'];
    $j = fn($salt) => 0.8 + (hexdec(substr(hash('sha256', $salt . '|' . $post_id), 0, 8)) / 0xffffffff) * 0.4;

    $seed = [
        'view'    => $views,
        'like'    => max(0, (int) round($views * $r_like    * $j('like'))),
        'dislike' => max(0, (int) round($views * $r_dislike * $j('dislike'))),
    ];

    foreach ($seed as $type => $count) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND type = %s AND row_type = 'total'",
            $post_id, $type
        ));
        if (!$exists) {
            $wpdb->insert($table, [
                'post_id' => $post_id, 'type' => $type, 'row_type' => 'total',
                'count' => $count, 'created_at' => null,
            ], ['%d', '%s', '%s', '%d', '%s']);
        }
    }
}
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish') {
        er_stats_seed_post($post->ID);
    }
}, 10, 3);

// ----------------------------------------------------------------------------
// CLEANUP
// ----------------------------------------------------------------------------
// Removes counters for deleted posts and for untracked types. Runs daily
// because WordPress recreates oembed_cache entries continuously.

add_action('er_stats_daily_cleanup', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $in    = "'" . implode("','", array_map('esc_sql', er_stats_tracked_types())) . "'";
    $wpdb->query(
        "DELETE ps FROM {$table} ps
         LEFT JOIN {$wpdb->posts} p ON p.ID = ps.post_id
         WHERE ps.row_type = 'total'
           AND (p.ID IS NULL OR p.post_status <> 'publish' OR p.post_type NOT IN ({$in}))"
    );
}, 20);
