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

// Styles for the post tiles - printed once per page. The tile look itself
// comes from er_stat_tile_styles() (SITE-WIDE FIGURES); this adds the button
// states for the vote tiles.
function er_voting_styles() {
    static $done = false;
    if ($done) return '';
    $done = true;
    return er_stat_tile_styles() . '<style>
        button.er-stat-line {display: flex; flex-direction: column; justify-content: flex-start; font: inherit; line-height: 1.3; text-align: left; cursor: pointer; border-color: var(--color-1); transition: background 0.2s ease;}
        button.er-stat-line:hover {background: var(--color-7);}
        button.er-stat-line:disabled {cursor: default;}
        button.er-stat-line:focus-visible {outline: 2px dashed var(--color-1); outline-offset: 3px;}
        body.dark-mode button.er-stat-line {border-color: var(--color-1);}
        body.dark-mode button.er-stat-line:hover {background: var(--color-6);}
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

// Tile with the total views of a post. 'before' + 'after' form the label.
function er_post_views_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => get_the_ID(),
        'before' => '👁️ ',
        'after' => ' Views',
    ], $atts, 'post_views');
    $views = er_voting_counts($atts['id'])['view'];
    return er_voting_styles()
         . '<span class="er-stat-line">'
         . '<span class="er-stat-label">' . esc_html(trim($atts['before']) . ' ' . trim($atts['after'])) . '</span>'
         . '<strong class="er-stat-today">' . number_format_i18n($views) . '</strong>'
         . '<span class="er-stat-unit">total</span>'
         . '<span class="er-stat-foot">Thanks for reading</span>'
         . '</span>';
}
add_shortcode('post_views', 'er_post_views_shortcode');

// ======================================
// LIKE / DISLIKE BUTTONS
// ======================================

// Two tiles that are the vote buttons of the current post: border in the link
// colour and "Click to like / dislike" below the line, so they read as
// buttons without hover (touch). Direct children of the surrounding <p>, so
// together with [post_views] in one Shortcode block they form one row.
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
    $tile = function($type, $icon, $label) use ($post_id, $counts) {
        return '<button type="button" class="er-stat-line" id="' . $type . '-btn-' . $post_id . '" onclick="erVote(' . $post_id . ', \'' . $type . '\')" aria-label="' . $label . ' this post">'
             . '<span class="er-stat-label">' . $icon . ' ' . $label . '</span>'
             . '<strong class="er-stat-today" id="' . $type . '-count-' . $post_id . '" aria-live="polite">' . number_format_i18n($counts[$type]) . '</strong>'
             . '<span class="er-stat-unit">' . strtolower($label) . 's</span>'
             . '<span class="er-stat-foot">Click to ' . strtolower($label) . '</span>'
             . '</button>';
    };
    return er_voting_styles()
         . $tile('like', '👍', 'Like')
         . $tile('dislike', '👎', 'Dislike')
         . '<span id="vote-feedback-' . $post_id . '" class="visually-hidden" aria-live="assertive"></span>';
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
            const foot = btn ? btn.querySelector(".er-stat-foot") : null;
            if (lastVoteTime && Date.now() < expiryTime) {
                if (foot) foot.textContent = "Already voted";
                btn.disabled = true;
                btn.setAttribute("aria-disabled", "true");
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
                document.getElementById(type + "-count-" + postId).innerText = Number(j.data.count).toLocaleString(document.documentElement.lang || undefined);
                if (foot) foot.textContent = "Thanks for voting";
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

// Front-end tiles. Same figures as the dashboard (er_stats_snapshot);
// er_stats_live_script() refreshes them after load, since pages are cached.
// Several shortcodes in one Shortcode block sit side by side (:has()), all
// the same width. They stack only when the space they sit in is narrower
// than 580px (container query: measures that space, not the screen).
function er_stat_tile_styles() {
    static $done = false;
    if ($done) return '';
    $done = true;
    return '<style>
        p:has(> .er-stat-line), body:has(> .er-stat-line) {container-type: inline-size; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 12px;}
        .er-stat-line + br {display: none;}
        .er-stat-line {display: block; flex: 1 1 0; min-width: 0; box-sizing: border-box; padding: 14px 16px; background: var(--color-8); border: 1px solid var(--color-5); border-radius: 12px; color: var(--color-3); line-height: 1.3;}
        @container (max-width: 579px) {.er-stat-line {flex-basis: 100%;}}
        .er-stat-label {display: block; margin-bottom: 6px; font-size: var(--er-fs-sm);}
        .er-stat-today {display: block; font-size: var(--er-fs-xl); font-weight: var(--er-fw-bold); color: #990033; line-height: 1.1;}
        .er-stat-unit {display: block; margin: 2px 0 10px; font-size: var(--er-fs-xs); color: var(--color-9);}
        .er-stat-foot {display: block; padding-top: 8px; border-top: 1px solid var(--color-5); font-size: var(--er-fs-xs); color: var(--color-4);}
        .er-stat-total {font-weight: var(--er-fw-bold);}
        body.dark-mode .er-stat-line {background: var(--color-10); border-color: var(--color-4); color: var(--color-5);}
        body.dark-mode .er-stat-today {color: #990033;}
        body.dark-mode .er-stat-foot {border-color: var(--color-4); color: var(--color-5);}
    </style>';
}

function er_render_stat_line($type, $icon, $label) {
    if (!function_exists('er_stats_snapshot')) return '';
    $s = er_stats_snapshot();
    $today = number_format_i18n($s[$type . 's_today']);
    $total = number_format_i18n($s[$type . 's_total']);
    // Script once per page, in the footer, only where a shortcode is used
    if (!has_action('wp_footer', 'er_stats_live_script')) {
        add_action('wp_footer', 'er_stats_live_script');
    }
    return er_stat_tile_styles()
         . '<span class="er-stat-line" data-er-stat="' . esc_attr($type) . '">'
         . '<span class="er-stat-label"><span class="er-stat-icon">' . $icon . '</span> ' . esc_html($label) . '</span>'
         . '<strong class="er-stat-today">' . $today . '</strong>'
         . '<span class="er-stat-unit">today</span>'
         . '<span class="er-stat-foot"><strong class="er-stat-total">' . $total . '</strong> total</span>'
         . '</span>';
}

add_shortcode('today_total_views',    fn() => er_render_stat_line('view',    '👁️', 'Views'));
add_shortcode('today_total_likes',    fn() => er_render_stat_line('like',    '👍', 'Likes'));
add_shortcode('today_total_dislikes', fn() => er_render_stat_line('dislike', '👎', 'Dislikes'));

// AJAX action er_stats_live: current figures, formatted like the shortcodes.
// Read-only, answered from the 5-minute snapshot cache. POST so neither
// LiteSpeed nor the CDN caches the answer; no nonce (cached pages outlive it).
function er_stats_live() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_send_json_error(null, 405);
    }
    if (!function_exists('er_stats_snapshot')) {
        wp_send_json_error(null, 503);
    }
    $s   = er_stats_snapshot();
    $out = [];
    foreach (['view', 'like', 'dislike'] as $type) {
        $out[$type] = [
            'today' => number_format_i18n($s[$type . 's_today']),
            'total' => number_format_i18n($s[$type . 's_total']),
        ];
    }
    nocache_headers();
    wp_send_json_success($out);
}
add_action('wp_ajax_er_stats_live', 'er_stats_live');
add_action('wp_ajax_nopriv_er_stats_live', 'er_stats_live');

// After load, when the browser is idle: fetch the current figures once and
// swap them into every stat line on the page. On any failure the cached
// numbers simply stay. Bots (navigator.webdriver) keep the cached numbers.
function er_stats_live_script() {
    ?>
    <script>
    (function () {
        var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        function run() {
            if (navigator.webdriver) return;
            var lines = document.querySelectorAll('.er-stat-line[data-er-stat]');
            if (!lines.length) return;
            fetch(ajaxUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=er_stats_live",
                credentials: "same-origin"
            })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (!j || !j.success) return;
                lines.forEach(function (p) {
                    var d = j.data[p.getAttribute('data-er-stat')];
                    if (!d) return;
                    var t = p.querySelector('.er-stat-today');
                    var a = p.querySelector('.er-stat-total');
                    if (t) t.textContent = d.today;
                    if (a) a.textContent = d.total;
                });
            })
            .catch(function () {});
        }
        function idle() {
            if ('requestIdleCallback' in window) requestIdleCallback(run, { timeout: 3000 });
            else setTimeout(run, 1);
        }
        if (document.readyState === 'complete') idle();
        else window.addEventListener('load', idle);
    })();
    </script>
    <?php
}
