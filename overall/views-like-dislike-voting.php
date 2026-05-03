<?php
defined('ABSPATH') || exit;

// ======================================
// ADD POST VIEWS TRACKING
// ======================================

// Increment View Count on single Posts, Pages, CPT
function er_track_post_views($post_id) {
    if (!is_singular()) return;
    if (empty($post_id)) $post_id = get_the_ID();
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    // Increment the Counter Row
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table}
         SET count = count + 1
         WHERE post_id = %d AND type = 'view' AND row_type = 'total'",
        $post_id
    ));
    // Insert Event Row
    $wpdb->insert($table, [
        'post_id'    => $post_id,
        'type'       => 'view',
        'row_type'   => 'event',
        'count'      => null,
        'created_at' => current_time('mysql'),
    ]);
}

// Ensure View Meta exists for new Posts
function er_init_views_meta($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE post_id = %d AND type = 'view' AND row_type = 'total'",
        $post_id
    ));
    if (!$exists) {
        $wpdb->insert($table, [
            'post_id'    => $post_id,
            'type'       => 'view',
            'row_type'   => 'total',
            'count'      => rand(5000, 10000),
            'created_at' => null,
        ]);
    }
}
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish') {
        er_init_views_meta($post->ID);
    }
}, 10, 3);
add_action('wp_head', function() {
    if (is_singular()) er_track_post_views(get_the_ID());
});

// Shortcode with Prefix / Suffix Options for Views
function er_post_views_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => get_the_ID(),
        'before' => '👁️ ',
        'after' => ' Views',
    ], $atts, 'post_views');
    $post_id = $atts['id'];
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $views = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT count FROM {$table}
         WHERE post_id = %d AND type = 'view' AND row_type = 'total'",
        $post_id
    ));
	// Inline CSS Styles
    $style = '<style>
        .post-views-wrapper {
            display: inline-block;
            margin-right: 25px;
			padding-top: 10px;
			padding-bottom: 25px;
            vertical-align: middle;
			font-weight: bold;
        }
    </style>';
	// Output Number of Views
    $output = esc_html($atts['before']) . number_format($views) . esc_html($atts['after']);
    return $style . '<span class="post-views-wrapper">' . $output . '</span>';
}
add_shortcode('post_views', 'er_post_views_shortcode');

// Shortcode to display Views on Frontend
function er_today_total_views_shortcode() {
    global $wpdb;
    $cache_key = 'er_views_stats_' . date('Y-m-d-H');
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
	$table = $wpdb->prefix . 'er_post_stats';
    $views_today = $wpdb->get_var("
        SELECT COUNT(*) FROM {$table}
        WHERE type = 'view' AND row_type = 'event' AND DATE(created_at) = CURDATE()
    ");
    $views_total = $wpdb->get_var("
        SELECT SUM(count) FROM {$table}
        WHERE type = 'view' AND row_type = 'total'
    ");
    $format_count = fn($num) => number_format($num);
    $output = '<p>👁️ Views: <strong style="color: var(--color-2);">' . $format_count($views_today) . '</strong> today / <strong>' . $format_count($views_total) . '</strong> total</p>';
    set_transient($cache_key, $output, HOUR_IN_SECONDS);
    return $output;
}
add_shortcode('today_total_views', 'er_today_total_views_shortcode');

// Introduce Increments for Views
function increment_views() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    // Increment Counter Rows for all published Posts
    $wpdb->query("
        UPDATE {$table} ps
        INNER JOIN {$wpdb->posts} p ON p.ID = ps.post_id
        SET ps.count = ps.count + FLOOR(20 + RAND() * 21)
        WHERE ps.type = 'view' AND ps.row_type = 'total'
        AND p.post_status = 'publish'
    ");
}

// Schedule automatic Increments for Views
add_action('increment_views_event', 'increment_views');
if (!wp_next_scheduled('increment_views_event')) {
    wp_schedule_event(time(), 'weekly', 'increment_views_event');
}

// ======================================
// ADD LIKE / DISLIKE BUTTONS
// ======================================

// Update Likes
function update_likes() {
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'update_post_reaction')) {
        wp_die('Invalid request');
    }
    $post_id = intval($_GET['post_id']);
    if (!$post_id || !get_post_status($post_id)) {
        wp_die('Invalid Post');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    // Increment Counter Row
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table}
         SET count = count + 1
         WHERE post_id = %d AND type = 'like' AND row_type = 'total'",
        $post_id
    ));
    // Get Value from table
    $new_likes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT count FROM {$table}
         WHERE post_id = %d AND type = 'like' AND row_type = 'total'",
        $post_id
    ));
    // Insert Event Row
    $wpdb->insert($table, [
        'post_id'    => $post_id,
        'type'       => 'like',
        'row_type'   => 'event',
        'count'      => null,
        'created_at' => current_time('mysql'),
    ]);
    echo $new_likes;
    wp_die();
}

// Ensure Likes Meta exists for new Posts
function er_init_likes_meta($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE post_id = %d AND type = 'like' AND row_type = 'total'",
        $post_id
    ));
    if (!$exists) {
        $wpdb->insert($table, [
            'post_id'    => $post_id,
            'type'       => 'like',
            'row_type'   => 'total',
            'count'      => rand(500, 1000),
            'created_at' => null,
        ]);
    }
}
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish') {
        er_init_likes_meta($post->ID);
    }
}, 10, 3);
add_action('wp_ajax_update_likes', 'update_likes');
add_action('wp_ajax_nopriv_update_likes', 'update_likes');

// Update Dislikes
function update_dislikes() {
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'update_post_reaction')) {
        wp_die('Invalid request');
    }
    $post_id = intval($_GET['post_id']);
    if (!$post_id || !get_post_status($post_id)) {
        wp_die('Invalid Post');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    // Increment Counter Row
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table}
         SET count = count + 1
         WHERE post_id = %d AND type = 'dislike' AND row_type = 'total'",
        $post_id
    ));
    // Get Value from Table
    $new_dislikes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT count FROM {$table}
         WHERE post_id = %d AND type = 'dislike' AND row_type = 'total'",
        $post_id
    ));
    // Insert Event Row
    $wpdb->insert($table, [
        'post_id'    => $post_id,
        'type'       => 'dislike',
        'row_type'   => 'event',
        'count'      => null,
        'created_at' => current_time('mysql'),
    ]);
    echo $new_dislikes;
    wp_die();
}

// Ensure Dislikes Meta exists for new Posts
function er_init_dislikes_meta($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE post_id = %d AND type = 'dislike' AND row_type = 'total'",
        $post_id
    ));
    if (!$exists) {
        $wpdb->insert($table, [
            'post_id'    => $post_id,
            'type'       => 'dislike',
            'row_type'   => 'total',
            'count'      => rand(5, 10),
            'created_at' => null,
        ]);
    }
}
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status === 'publish' && $old_status !== 'publish') {
        er_init_dislikes_meta($post->ID);
    }
}, 10, 3);
add_action('wp_ajax_update_dislikes', 'update_dislikes');
add_action('wp_ajax_nopriv_update_dislikes', 'update_dislikes');

// Shortcode with Prefix / Suffix Options for Like / Dislike
function custom_like_dislike_shortcode() {
    if (!is_singular()) {
        return '';
    }
    $post_id = get_the_ID();
    // Assign initial random Values if not set
	global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $likes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT count FROM {$table}
         WHERE post_id = %d AND type = 'like' AND row_type = 'total'",
        $post_id
    ));
    $dislikes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT count FROM {$table}
         WHERE post_id = %d AND type = 'dislike' AND row_type = 'total'",
        $post_id
    ));
    // Inline CSS Styles
    $style = '<style>
        .like-dislike-buttons-wrapper {
            display: inline-block;
			padding-top: 10px;
			padding-bottom: 25px;
            vertical-align: middle;
        }
        .like-dislike-buttons-wrapper button {
            background: none;
            border: none;
            font-weight: bold;
            color: var(--color-1);
            cursor: pointer;
            display: inline-block;
        }
        .like-dislike-buttons-wrapper button:hover {
            color: var(--color-2);
        }
        .like-dislike-buttons-wrapper button:focus-visible {
            outline: 2px dashed var(--color-1);
            outline-offset: 3px;
        }
        .visually-hidden {
            position: absolute !important;
            width: 1px;
            height: 1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
            border: 0;
        }
    </style>';

    // Output Buttons and prevent Voting again before xxx Time passed using localStorage
    $buttons = '<span class="like-dislike-buttons-wrapper">
        <button id="like-btn-' . $post_id . '" onclick="updateLikes(' . $post_id . ')" aria-label="Like this post">
            👍 <span class="visually-hidden">Like</span> Like (<span id="like-count-' . $post_id . '" aria-live="polite">' . $likes . '</span>)
        </button>
        <button id="dislike-btn-' . $post_id . '" onclick="updateDislikes(' . $post_id . ')" aria-label="Dislike this post">
            👎 <span class="visually-hidden">Dislike</span> Dislike (<span id="dislike-count-' . $post_id . '" aria-live="polite">' . $dislikes . '</span>)
        </button>
        <span id="vote-feedback" class="visually-hidden" aria-live="assertive"></span>
    </span>

    <script>
        var reactionNonce = "' . wp_create_nonce("update_post_reaction") . '";
        function checkVoteExpiration(postId) {
            const expiryKey = "voteExpiry_" + postId;
            const expiryTime = localStorage.getItem(expiryKey);
            if (expiryTime && Date.now() > expiryTime) {
                localStorage.removeItem("voted_" + postId);
                localStorage.removeItem(expiryKey);
            }
        }
        function updateLikes(postId) {
            checkVoteExpiration(postId);
            const voteKey = "voted_" + postId;
            const expiryKey = "voteExpiry_" + postId;
            const lastVoteTime = localStorage.getItem(voteKey);
            const expiryTime = localStorage.getItem(expiryKey);
            const btn = document.getElementById("like-btn-" + postId);
            if (lastVoteTime && Date.now() < expiryTime) {
                btn.innerHTML = "👍 Already Voted";
                btn.disabled = true;
                btn.setAttribute("aria-disabled", "true");
                btn.setAttribute("tabindex", "0");
                btn.setAttribute("title", "You have already voted. Try again later.");
                return;
            }
            fetch("/wp-admin/admin-ajax.php?action=update_likes&post_id=" + postId + "&nonce=" + reactionNonce)
            .then(response => response.text())
            .then(newLikes => {
                document.getElementById("like-count-" + postId).innerText = newLikes;
                document.getElementById("vote-feedback").innerText = "Your like has been recorded.";
                localStorage.setItem(voteKey, Date.now());
                localStorage.setItem(expiryKey, Date.now() + 300000); // 5 minutes
            });
        }
        function updateDislikes(postId) {
            checkVoteExpiration(postId);
            const voteKey = "voted_" + postId;
            const expiryKey = "voteExpiry_" + postId;
            const lastVoteTime = localStorage.getItem(voteKey);
            const expiryTime = localStorage.getItem(expiryKey);
            const btn = document.getElementById("dislike-btn-" + postId);
            if (lastVoteTime && Date.now() < expiryTime) {
                btn.innerHTML = "👎 Already Voted";
                btn.disabled = true;
                btn.setAttribute("aria-disabled", "true");
                btn.setAttribute("tabindex", "0");
                btn.setAttribute("title", "You have already voted. Try again later.");
                return;
            }
            fetch("/wp-admin/admin-ajax.php?action=update_dislikes&post_id=" + postId + "&nonce=" + reactionNonce)
            .then(response => response.text())
            .then(newDislikes => {
                document.getElementById("dislike-count-" + postId).innerText = newDislikes;
                document.getElementById("vote-feedback").innerText = "Your dislike has been recorded.";
                localStorage.setItem(voteKey, Date.now());
                localStorage.setItem(expiryKey, Date.now() + 300000); // 5 minutes
            });
        }
    </script>';

    return $style . $buttons;
}
add_shortcode('like_dislike_buttons', 'custom_like_dislike_shortcode');

// Shortcode to display Likes on Frontend
function er_today_total_likes_shortcode() {
    global $wpdb;
    $cache_key = 'er_likes_stats_' . date('Y-m-d-H');
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
	$table = $wpdb->prefix . 'er_post_stats';
    $likes_today = $wpdb->get_var("
        SELECT COUNT(*) FROM {$table}
        WHERE type = 'like' AND row_type = 'event' AND DATE(created_at) = CURDATE()
    ");
    $likes_total = $wpdb->get_var("
        SELECT SUM(count) FROM {$table}
        WHERE type = 'like' AND row_type = 'total'
    ");
    $format_count = fn($num) => number_format($num);
    $output = '<p>👍 Likes: <strong style="color: var(--color-2);">' . $format_count($likes_today) . '</strong> today / <strong>' . $format_count($likes_total) . '</strong> total</p>';
    set_transient($cache_key, $output, HOUR_IN_SECONDS); // ⏱️ Cached 1hr — lags behind Dashboard (which is 5min)
    return $output;
}
add_shortcode('today_total_likes', 'er_today_total_likes_shortcode');

// Shortcode to display Dislikes on Frontend
function er_today_total_dislikes_shortcode() {
    global $wpdb;
    $cache_key = 'er_dislikes_stats_' . date('Y-m-d-H');
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }
	$table = $wpdb->prefix . 'er_post_stats';
    $dislikes_today = $wpdb->get_var("
        SELECT COUNT(*) FROM {$table}
        WHERE type = 'dislike' AND row_type = 'event' AND DATE(created_at) = CURDATE()
    ");
    $dislikes_total = $wpdb->get_var("
        SELECT SUM(count) FROM {$table}
        WHERE type = 'dislike' AND row_type = 'total'
    ");
    $format_count = fn($num) => number_format($num);
    $output = '<p>👎 Dislikes: <strong style="color: var(--color-2);">' . $format_count($dislikes_today) . '</strong> today / <strong>' . $format_count($dislikes_total) . '</strong> total</p>';
    set_transient($cache_key, $output, HOUR_IN_SECONDS); // ⏱️ Cached 1hr — lags behind Dashboard (which is 5min)
    return $output;
}
add_shortcode('today_total_dislikes', 'er_today_total_dislikes_shortcode');

// Introduce Increments for Likes
function increment_likes() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $wpdb->query("
        UPDATE {$table} ps
        INNER JOIN {$wpdb->posts} p ON p.ID = ps.post_id
        SET ps.count = ps.count + FLOOR(10 + RAND() * 11)
        WHERE ps.type = 'like' AND ps.row_type = 'total'
        AND p.post_status = 'publish'
    ");
}

// Schedule automatic Increments for Likes
add_action('increment_likes_event', 'increment_likes');
if (!wp_next_scheduled('increment_likes_event')) {
    wp_schedule_event(time(), 'weekly', 'increment_likes_event');
}

// Introduce Increments for Dislikes
function increment_dislikes() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $wpdb->query("
        UPDATE {$table} ps
        INNER JOIN {$wpdb->posts} p ON p.ID = ps.post_id
        SET ps.count = ps.count + FLOOR(1 + RAND() * 2)
        WHERE ps.type = 'dislike' AND ps.row_type = 'total'
        AND p.post_status = 'publish'
    ");
}

// Schedule automatic Increments for Dislikes
add_action('increment_dislikes_event', 'increment_dislikes');
if (!wp_next_scheduled('increment_dislikes_event')) {
    wp_schedule_event(time(), 'monthly', 'increment_dislikes_event');
}
