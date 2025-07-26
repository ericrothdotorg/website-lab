<?php 

/* ADD LIKE / DISLIKE BUTTONS TO POSTS & CPTs */

// ✅ Shortcode to manually insert like / dislike buttons anywhere
function custom_like_dislike_shortcode() {
    if (!is_singular()) {
        return '';
    }
    $post_id = get_the_ID();
    // Assign initial random values if not set
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
    // Inline CSS styles
    $style = '<style>
        .like-dislike-container {
            margin-top: -25px;
        }
        .like-dislike-container button {
            background: none;
            border: none;
            font-size: 16px;
            font-weight: bold;
            color: #1e73be;
            cursor: pointer;
        }
        .like-dislike-container button:hover {
            color: #c53030;
        }
    </style>';
    // Output buttons and prevent voting again before xxx time passed using localStorage
    $buttons = '<div class="like-dislike-container" style="padding-bottom: 5px;">
        <button id="like-btn-' . $post_id . '" onclick="updateLikes(' . $post_id . ')" aria-label="Like this post">
            👍 Like (<span id="like-count-' . $post_id . '" aria-live="polite">' . $likes . '</span>)
        </button>
        <button id="dislike-btn-' . $post_id . '" onclick="updateDislikes(' . $post_id . ')" aria-label="Dislike this post">
            👎 Dislike (<span id="dislike-count-' . $post_id . '" aria-live="polite">' . $dislikes . '</span>)
        </button>
    </div>
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
            if (lastVoteTime && Date.now() < expiryTime) {
                document.getElementById("like-btn-" + postId).innerHTML = "👍 Already Voted";
                document.getElementById("like-btn-" + postId).disabled = true;
                document.getElementById("like-btn-" + postId).style.opacity = "0.75";
                return;
            }
            fetch("/wp-admin/admin-ajax.php?action=update_likes&post_id=" + postId + "&nonce=" + reactionNonce)
            .then(response => response.text())
            .then(newLikes => {
                document.getElementById("like-count-" + postId).innerText = newLikes;
                localStorage.setItem(voteKey, Date.now());
                localStorage.setItem(expiryKey, Date.now() + 300000); // Expire vote after 5 min
            });
        }
        function updateDislikes(postId) {
            checkVoteExpiration(postId);
            const voteKey = "voted_" + postId;
            const expiryKey = "voteExpiry_" + postId;
            const lastVoteTime = localStorage.getItem(voteKey);
            const expiryTime = localStorage.getItem(expiryKey);
            if (lastVoteTime && Date.now() < expiryTime) {
                document.getElementById("dislike-btn-" + postId).innerHTML = "👎 Already Voted";
                document.getElementById("dislike-btn-" + postId).disabled = true;
                document.getElementById("dislike-btn-" + postId).style.opacity = "0.75";
                return;
            }
            fetch("/wp-admin/admin-ajax.php?action=update_dislikes&post_id=" + postId + "&nonce=" + reactionNonce)
            .then(response => response.text())
            .then(newDislikes => {
                document.getElementById("dislike-count-" + postId).innerText = newDislikes; // ✅ Update dislike count immediately
                localStorage.setItem(voteKey, Date.now());
                localStorage.setItem(expiryKey, Date.now() + 300000); // Expire vote after 5 min
            });
        }
    </script>';
    return $style . $buttons;
}
add_shortcode('like_dislike_buttons', 'custom_like_dislike_shortcode');
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

// ✅ Introduce increments for likes
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

// ✅ Introduce increments for dislikes
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

// ✅ Schedule automatic increments
if (!wp_next_scheduled('increment_likes_event')) {
    wp_schedule_event(time(), 'weekly', 'increment_likes_event');
}
add_action('increment_likes_event', 'increment_likes');
if (!wp_next_scheduled('increment_dislikes_event')) {
    wp_schedule_event(time(), 'monthly', 'increment_dislikes_event');
}
add_action('increment_dislikes_event', 'increment_dislikes');
