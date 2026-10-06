<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ======================================
// VIEWS / LIKE / DISLIKE - display only
// ======================================
// Shortcodes and buttons. Every number comes from the Stats Engine snippet,
// which owns wp_er_post_stats and records views, likes and dislikes. This
// snippet runs no SQL and no cron. Without the Stats Engine the shortcodes
// show 0 and the buttons do nothing; nothing breaks.

// Totals of one post, or zeros if the Stats Engine is not active.
function er_voting_counts($post_id) {
    return function_exists('er_stats_counts')
        ? er_stats_counts($post_id)
        : ['view' => 0, 'like' => 0, 'dislike' => 0];
}

// Inline CSS Styles - printed once per page, no matter how many shortcodes.
function er_voting_styles() {
    static $done = false;
    if ($done) return '';
    $done = true;
    return '<style>
        .post-views-wrapper {
            display: inline-block;
            margin-right: 25px;
			padding-top: 0px;
			padding-bottom: 10px;
            vertical-align: middle;
			font-weight: var(--er-fw-bold);
        }
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
}

// ======================================
// POST VIEWS
// ======================================

// Shortcode with Prefix / Suffix Options for Views
function er_post_views_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => get_the_ID(),
        'before' => '👁️ ',
        'after' => ' Views',
    ], $atts, 'post_views');
    $views = er_voting_counts($atts['id'])['view'];
	// Output Number of Views
    $output = esc_html($atts['before']) . number_format($views) . esc_html($atts['after']);
    return er_voting_styles() . '<span class="post-views-wrapper">' . $output . '</span>';
}
add_shortcode('post_views', 'er_post_views_shortcode');

// ======================================
// LIKE / DISLIKE BUTTONS
// ======================================

// Shortcode with the two Buttons for the current Post
function custom_like_dislike_shortcode() {
    if (!is_singular()) {
        return '';
    }
    $post_id = get_the_ID();
    $counts  = er_voting_counts($post_id);
    // Script once per page, in the footer
    if (!has_action('wp_footer', 'er_voting_script')) {
        add_action('wp_footer', 'er_voting_script');
    }
    return er_voting_styles() . '<span class="like-dislike-buttons-wrapper">
        <button id="like-btn-' . $post_id . '" onclick="erVote(' . $post_id . ', \'like\')" aria-label="Like this post">
            👍 <span class="visually-hidden">Like</span> Like (<span id="like-count-' . $post_id . '" aria-live="polite">' . $counts['like'] . '</span>)
        </button>
        <button id="dislike-btn-' . $post_id . '" onclick="erVote(' . $post_id . ', \'dislike\')" aria-label="Dislike this post">
            👎 <span class="visually-hidden">Dislike</span> Dislike (<span id="dislike-count-' . $post_id . '" aria-live="polite">' . $counts['dislike'] . '</span>)
        </button>
        <span id="vote-feedback-' . $post_id . '" class="visually-hidden" aria-live="assertive"></span>
    </span>';
}
add_shortcode('like_dislike_buttons', 'custom_like_dislike_shortcode');

// Prevent Voting again before 5 Minutes passed using localStorage (one vote
// per post, like or dislike). The Stats Engine applies the same lock per IP.
// No nonce: cached pages outlive it (see Stats Engine, er_vote).
function er_voting_script() {
    ?>
    <script>
    (function () {
        var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        var icons = { like: "👍", dislike: "👎" };
        function checkVoteExpiration(postId) {
            const expiryKey = "voteExpiry_" + postId;
            const expiryTime = localStorage.getItem(expiryKey);
            if (expiryTime && Date.now() > expiryTime) {
                localStorage.removeItem("voted_" + postId);
                localStorage.removeItem(expiryKey);
            }
        }
        window.erVote = function (postId, type) {
            checkVoteExpiration(postId);
            const voteKey = "voted_" + postId;
            const expiryKey = "voteExpiry_" + postId;
            const lastVoteTime = localStorage.getItem(voteKey);
            const expiryTime = localStorage.getItem(expiryKey);
            const btn = document.getElementById(type + "-btn-" + postId);
            if (lastVoteTime && Date.now() < expiryTime) {
                btn.innerHTML = icons[type] + " Already Voted";
                btn.disabled = true;
                btn.setAttribute("aria-disabled", "true");
                btn.setAttribute("tabindex", "0");
                btn.setAttribute("title", "You have already voted. Try again later.");
                return;
            }
            fetch(ajaxUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ action: "er_vote", post_id: postId, type: type }).toString(),
                credentials: "same-origin"
            })
            .then(response => response.ok ? response.json() : null)
            .then(j => {
                if (!j || !j.success) return;
                document.getElementById(type + "-count-" + postId).innerText = j.data.count;
                document.getElementById("vote-feedback-" + postId).innerText = "Your " + type + " has been recorded.";
                localStorage.setItem(voteKey, Date.now());
                localStorage.setItem(expiryKey, Date.now() + 300000); // 5 minutes
            })
            .catch(() => {});
        };
    })();
    </script>
    <?php
}

// ======================================
// SITE-WIDE FIGURES
// ======================================

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
