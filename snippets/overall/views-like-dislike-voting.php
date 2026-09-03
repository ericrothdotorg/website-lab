<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// Tagesgrenze in WP-Ortszeit. wp_er_post_stats.created_at wird mit current_time('mysql') geschrieben, MySQL laeuft aber auf UTC.
// CURDATE() waere daher um den Zeitzonen-Offset verschoben.
if (!function_exists('er_today_start')) {
    function er_today_start() {
        return current_time('Y-m-d') . ' 00:00:00';
    }
}

// ======================================
// ADD POST VIEWS TRACKING
// ======================================

// Increment View Count on single Posts, Pages, CPT
function er_track_post_views($post_id) {
    if (empty($post_id)) return;
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
			padding-top: 0px;
			padding-bottom: 10px;
            vertical-align: middle;
			font-weight: var(--er-fw-bold);
        }
    </style>';
	// Output Number of Views
    $output = esc_html($atts['before']) . number_format($views) . esc_html($atts['after']);
    return $style . '<span class="post-views-wrapper">' . $output . '</span>';
}
add_shortcode('post_views', 'er_post_views_shortcode');

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
			padding-top: 0px;
			padding-bottom: 10px;
            vertical-align: middle;
        }
        .like-dislike-buttons-wrapper button {
            background: none;
            border: none;
            font-weight: var(--er-fw-bold);
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
        var reactionAjaxUrl = "' . admin_url("admin-ajax.php") . '";
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
            fetch(reactionAjaxUrl + "?" + new URLSearchParams({ action: "update_likes", post_id: postId, nonce: reactionNonce }), { credentials: "same-origin" })
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
            fetch(reactionAjaxUrl + "?" + new URLSearchParams({ action: "update_dislikes", post_id: postId, nonce: reactionNonce }), { credentials: "same-origin" })
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

// Shortcodes fuer die Frontend-Ausgabe.
// Alle drei holen ihre Zahlen aus derselben Quelle wie das Dashboard,
// damit vorne und hinten nie Unterschiedliches steht.
function er_render_stat_line($type, $icon, $label) {
    if (!function_exists('er_stats_snapshot')) return '';
    $s = er_stats_snapshot();
    $today = number_format_i18n($s[$type . 's_today']);
    $total = number_format_i18n($s[$type . 's_total']);
    return '<p>' . $icon . ' ' . $label . ': '
         . '<strong style="color: var(--color-2);">' . $today . '</strong> today / '
         . '<strong>' . $total . '</strong> total</p>';
}

add_shortcode('today_total_views',    fn() => er_render_stat_line('view',    '👁️', 'Views'));
add_shortcode('today_total_likes',    fn() => er_render_stat_line('like',    '👍', 'Likes'));
add_shortcode('today_total_dislikes', fn() => er_render_stat_line('dislike', '👎', 'Dislikes'));

// ======================================
// DAILY CLEANUP OF EVENT ROWS
// ======================================

// Mirrors the manual Dashboard cleanup in custom_cleanup_old_data(), but runs on its own.
// Only 'event' rows are touched — the 'total' counter rows that hold the actual numbers are never deleted.
add_action('er_stats_daily_cleanup', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_post_stats';
    $wpdb->query("
        DELETE FROM {$table}
        WHERE row_type = 'event' AND created_at < '" . er_today_start() . "'
    ");
});
if (!wp_next_scheduled('er_stats_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'er_stats_daily_cleanup');
}
