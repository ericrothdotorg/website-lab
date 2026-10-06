<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ============================================================================
// STATS ENGINE - single source for all counters
// ----------------------------------------------------------------------------
// Owns wp_er_post_stats: creates it, is the only snippet that writes to it,
// and cleans it. Everything else reads through the functions below.
// Scope "Run snippet everywhere" (votes arrive via admin-ajax, the jobs run
// via wp-cron). Must load before the voting snippet and the dashboard -> low
// priority.
//
// PROVIDES
//   er_track_post_views( $post_id )  counts a real page view (Visitor Tracking)
//   er_stats_counts( $post_id )      view / like / dislike totals of one post (Voting)
//   er_stats_snapshot()              site-wide figures (Voting, Custom Dashboard)
//   er_today_start()                 start of today in site time
//   AJAX action er_vote              like / dislike buttons (Voting)
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

function er_stats_is_tracked($post_id) {
    return $post_id
        && get_post_status($post_id) === 'publish'
        && in_array(get_post_type($post_id), er_stats_tracked_types(), true);
}

// Day boundary in site time. created_at is written with current_time('mysql'),
// but MySQL runs on UTC, so CURDATE() would be off by the time zone offset.
if (!function_exists('er_today_start')) {
    function er_today_start() {
        return current_time('Y-m-d') . ' 00:00:00';
    }
}

// ----------------------------------------------------------------------------
// TABLE
// ----------------------------------------------------------------------------
// One table for everything: one 'total' row per post and type holds the
// number shown; 'event' rows log today's real views and votes.
// CREATE TABLE IF NOT EXISTS never touches an existing table. Runs once per
// schema version on init, and daily from the cleanup as a safety net.

function er_stats_install_table() {
    global $wpdb;
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}er_post_stats (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `post_id` bigint(20) unsigned NOT NULL,
        `type` enum('view','like','dislike') NOT NULL,
        `row_type` enum('total','event') NOT NULL,
        `count` bigint(20) unsigned DEFAULT 0,
        `created_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_counter` (`post_id`,`type`,`row_type`),
        KEY `idx_events` (`type`,`row_type`,`created_at`)
    ) " . $wpdb->get_charset_collate());
}
add_action('init', function() {
    if (get_option('er_stats_schema') !== '1') {
        er_stats_install_table();
        update_option('er_stats_schema', '1');
    }
});

// ----------------------------------------------------------------------------
// RECORDING
// ----------------------------------------------------------------------------
// Real views and votes: +1 on the total row, plus one event row for today's
// figures. Only for published content of the tracked types.

function er_stats_record($post_id, $type) {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $sql   = $wpdb->prepare(
        "UPDATE {$table} SET count = count + 1
         WHERE post_id = %d AND type = %s AND row_type = 'total'",
        $post_id, $type
    );
    // No counter row yet (content published before the counters existed):
    // create it with its initial values first, so the count is not lost.
    if (!$wpdb->query($sql)) {
        er_stats_seed_post($post_id);
        $wpdb->query($sql);
    }
    $wpdb->insert($table, [
        'post_id'    => $post_id,
        'type'       => $type,
        'row_type'   => 'event',
        'count'      => null,
        'created_at' => current_time('mysql'),
    ]);
}

// Called by Visitor Tracking once per visitor, post and 24 hours.
function er_track_post_views($post_id) {
    $post_id = (int) $post_id;
    if (er_stats_is_tracked($post_id)) {
        er_stats_record($post_id, 'view');
    }
}

// Totals of one post in a single query: ['view' => n, 'like' => n, 'dislike' => n].
// Kept per request, so several shortcodes on one page cost one query.
function er_stats_counts($post_id, $fresh = false) {
    static $cache = [];
    $post_id = (int) $post_id;
    if ($fresh || !isset($cache[$post_id])) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT type, SUM(count) AS n FROM {$wpdb->prefix}er_post_stats
             WHERE post_id = %d AND row_type = 'total' GROUP BY type",
            $post_id
        ));
        $out = ['view' => 0, 'like' => 0, 'dislike' => 0];
        foreach ((array) $rows as $r) {
            $out[$r->type] = (int) $r->n;
        }
        $cache[$post_id] = $out;
    }
    return $cache[$post_id];
}

// ----------------------------------------------------------------------------
// LIKES AND DISLIKES (AJAX action er_vote)
// ----------------------------------------------------------------------------
// Pages are full-page cached for days, so a nonce baked into them would expire
// long before the page does. Like the tracking beacon, this endpoint is guarded
// without one: POST only, published tracked content only, one vote per IP and
// post within 5 minutes (the same lock the buttons apply in the browser), and
// at most 20 votes per IP and minute. Answers with the new total.

function er_stats_vote() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_send_json_error(null, 405);
    }
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $type    = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
    if (!in_array($type, ['like', 'dislike'], true) || !er_stats_is_tracked($post_id)) {
        wp_send_json_error(null, 400);
    }
    $ip   = md5($_SERVER['REMOTE_ADDR'] ?? '');
    $rl   = 'er_vote_rl_' . $ip;
    $hits = (int) get_transient($rl);
    if ($hits >= 20) {
        wp_send_json_error(null, 429);
    }
    set_transient($rl, $hits + 1, MINUTE_IN_SECONDS);
    $lock = 'er_vote_' . md5($ip . '|' . $post_id);
    if (get_transient($lock) === false) {
        er_stats_record($post_id, $type);
        set_transient($lock, 1, 5 * MINUTE_IN_SECONDS);
    }
    wp_send_json_success(['count' => er_stats_counts($post_id, true)[$type]]);
}
add_action('wp_ajax_er_vote', 'er_stats_vote');
add_action('wp_ajax_nopriv_er_vote', 'er_stats_vote');

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

// Next occurrence of a time of day in site time (not UTC).
function er_stats_next_local($time) {
    return (new DateTime('tomorrow ' . $time, wp_timezone()))->getTimestamp();
}

// Schedule the jobs and clear out the old weekly ones.
// Unscheduling the legacy weekly events on every load is cheap and idempotent,
// so an older database restore gets cleaned up too.
add_action('init', function() {
    foreach (['increment_views_event', 'increment_likes_event', 'increment_dislikes_event'] as $old) {
        $ts = wp_next_scheduled($old);
        if ($ts) wp_unschedule_event($ts, $old);
    }
    // One-time reset: both jobs used to be scheduled in UTC, and the voting
    // snippet scheduled the cleanup at a random time of day. If today's
    // amount has not been applied yet, it is applied now instead of skipped.
    if (get_option('er_stats_cron') !== '2') {
        wp_clear_scheduled_hook('er_stats_daily_increment');
        wp_clear_scheduled_hook('er_stats_daily_cleanup');
        update_option('er_stats_cron', '2');
        er_stats_apply_daily();
    }
    if (!wp_next_scheduled('er_stats_daily_increment')) {
        wp_schedule_event(er_stats_next_local('00:05'), 'daily', 'er_stats_daily_increment');
    }
    if (!wp_next_scheduled('er_stats_daily_cleanup')) {
        wp_schedule_event(er_stats_next_local('00:15'), 'daily', 'er_stats_daily_cleanup');
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
    $progress = er_stats_day_progress();
    $out      = [];

    // Two queries for all three types: today's recorded events, and the totals.
    $real = $wpdb->get_results($wpdb->prepare(
        "SELECT type, COUNT(*) AS n FROM {$table}
         WHERE type IN ('view','like','dislike') AND row_type = 'event' AND created_at >= %s
         GROUP BY type", er_today_start()
    ), OBJECT_K);
    $total = $wpdb->get_results(
        "SELECT type, COALESCE(SUM(count),0) AS n FROM {$table}
         WHERE row_type = 'total' GROUP BY type", OBJECT_K
    );

    foreach (['view', 'like', 'dislike'] as $type) {
        $r = isset($real[$type]) ? (int) $real[$type]->n : 0;
        // The share of the daily amount accrued so far.
        $synth = (int) round(er_stats_today_target($type) * $progress);

        $out[$type . 's_today'] = $synth + $r;
        // Recorded events kept separately for the dashboard control line.
        $out['real_' . $type . 's_today'] = $r;
        $out[$type . 's_total'] = isset($total[$type]) ? (int) $total[$type]->n : 0;
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
// STATS TABLE CLEANUP (daily, 00:15 site time)
// ----------------------------------------------------------------------------
// Removes counters for deleted posts and for untracked types (WordPress
// recreates oembed_cache entries continuously), and event rows from before
// today: only today's are ever read. Total rows are never touched otherwise.

add_action('er_stats_daily_cleanup', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $in    = "'" . implode("','", array_map('esc_sql', er_stats_tracked_types())) . "'";
    er_stats_install_table();
    $wpdb->query(
        "DELETE ps FROM {$table} ps
         LEFT JOIN {$wpdb->posts} p ON p.ID = ps.post_id
         WHERE ps.row_type = 'total'
           AND (p.ID IS NULL OR p.post_status <> 'publish' OR p.post_type NOT IN ({$in}))"
    );
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE row_type = 'event' AND created_at < %s",
        er_today_start()
    ));
});
