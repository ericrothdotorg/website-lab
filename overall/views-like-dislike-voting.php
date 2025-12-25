<?php
// ======================================
// ADD POST VIEWS TRACKING
// ======================================

// Increment View Count on single Posts, Pages, CPT
function er_track_post_views($post_id) {
    if (!is_singular()) return;
    if (empty($post_id)) $post_id = get_the_ID();
    $views = (int) get_post_meta($post_id, '_er_post_views', true);
    update_post_meta($post_id, '_er_post_views', $views + 1);
    add_post_meta($post_id, 'view_timestamp', current_time('mysql'));
}
add_action('wp_head', function() {
    if (is_singular()) er_track_post_views(get_the_ID());
});

// Shortcode with Prefix / Suffix Options
function er_post_views_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => get_the_ID(),
        'before' => '👁️ ',
        'after' => ' Views',
    ], $atts, 'post_views');
    $post_id = $atts['id'];
    
    // Assign initial random Values if not set
    $views = get_post_meta($post_id, '_er_post_views', true);
    if (!$views || $views < 5000) {
        $views = rand(5000, 10000);
        update_post_meta($post_id, '_er_post_views', $views);
    }
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

// Introduce Increments for Views
function increment_views() {
    $args = array('post_type' => get_post_types(['public' => true]), 'posts_per_page' => -1);
    $posts = get_posts($args);
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $views = get_post_meta($post_id, '_er_post_views', true) ?: 5000;
        $increment = rand(100, 200);
        $views += $increment;
        update_post_meta($post_id, '_er_post_views', $views);
        // Add timestamps for each incremented view
        for ($i = 0; $i < $increment; $i++) {
            add_post_meta($post_id, 'view_timestamp', current_time('mysql'));
        }
    }
}

// Schedule automatic Increments
if (!wp_next_scheduled('increment_views_event')) {
    wp_schedule_event(time(), 'biweekly', 'increment_views_event');
}
add_action('increment_views_event', 'increment_views');

// Add custom bi-weekly Schedule
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['biweekly'])) {
        $schedules['biweekly'] = array(
            'interval' => 1209600, // 14 days in seconds
            'display' => __('Every Two Weeks')
        );
    }
    return $schedules;
});

// Shortcode to display output on Frontend
function er_today_total_views_shortcode() {
    global $wpdb;
    $views_today = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->prefix}postmeta
        WHERE meta_key = 'view_timestamp' AND DATE(meta_value) = CURDATE()
    ");
    $views_total = $wpdb->get_var("
        SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->prefix}postmeta
        WHERE meta_key = '_er_post_views'
    ");
    $format_count = fn($num) => number_format($num);
    return '<p>👁️ Views: <strong style="color: red;">' . intval($views_today) . '</strong> today / <strong>' . $format_count($views_total) . '</strong> total</p>';
}
add_shortcode('today_total_views', 'er_today_total_views_shortcode');

// ======================================
// ADD LIKE / DISLIKE BUTTONS
// ======================================

// Shortcode to manually insert Like / Dislike Buttons
function custom_like_dislike_shortcode() {
    if (!is_singular()) {
        return '';
    }
    $post_id = get_the_ID();
    // Assign initial random Values if not set
    $likes = get_post_meta($post_id, 'likes', true);
    if (!$likes || $likes < 500) {
        $likes = rand(500, 1000);
        update_post_meta($post_id, 'likes', $likes);
    }
    $dislikes = get_post_meta($post_id, 'dislikes', true);
    if (!$dislikes || $dislikes < 5) {
        $dislikes = rand(5, 10);
        update_post_meta($post_id, 'dislikes', $dislikes);
    }
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
            color: #1e73be;
            cursor: pointer;
            display: inline-block;
        }
        .like-dislike-buttons-wrapper button:hover {
            color: #c53030;
        }
        .like-dislike-buttons-wrapper button:focus-visible {
            outline: 2px dashed #1e73be;
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

// Update Likes
function update_likes() {
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'update_post_reaction')) {
        wp_die('Invalid request');
    }
    $post_id = intval($_GET['post_id']);
    if (!$post_id || !get_post_status($post_id)) {
        wp_die('Invalid Post');
    }
    $likes = get_post_meta($post_id, 'likes', true) ?: 0;
    update_post_meta($post_id, 'likes', $likes + 1);
    add_post_meta($post_id, 'like_timestamp', current_time('mysql'));
    echo $likes + 1;
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
    $dislikes = get_post_meta($post_id, 'dislikes', true) ?: 0;
    update_post_meta($post_id, 'dislikes', $dislikes + 1);
    add_post_meta($post_id, 'dislike_timestamp', current_time('mysql'));
    echo $dislikes + 1;
    wp_die();
}
add_action('wp_ajax_update_dislikes', 'update_dislikes');
add_action('wp_ajax_nopriv_update_dislikes', 'update_dislikes');

// Introduce Increments for Likes
function increment_likes() {
    $args = array('post_type' => get_post_types(['public' => true]), 'posts_per_page' => -1);
    $posts = get_posts($args);
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $likes = get_post_meta($post_id, 'likes', true) ?: 500;
        $likes += rand(10, 20);
        update_post_meta($post_id, 'likes', $likes);
    }
}

// Introduce Increments for Dislikes
function increment_dislikes() {
    $args = array('post_type' => get_post_types(['public' => true]), 'posts_per_page' => -1);
    $posts = get_posts($args);
    foreach ($posts as $post) {
        $post_id = $post->ID;
        $dislikes = get_post_meta($post_id, 'dislikes', true) ?: 5;
        $dislikes += rand(1, 2);
        update_post_meta($post_id, 'dislikes', $dislikes);
    }
}

// Schedule automatic Increments
if (!wp_next_scheduled('increment_likes_event')) {
    wp_schedule_event(time(), 'weekly', 'increment_likes_event');
}
add_action('increment_likes_event', 'increment_likes');

if (!wp_next_scheduled('increment_dislikes_event')) {
    wp_schedule_event(time(), 'monthly', 'increment_dislikes_event');
}
add_action('increment_dislikes_event', 'increment_dislikes');
