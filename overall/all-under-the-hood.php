<?php
defined('ABSPATH') || exit;

// ======================================
// WORDPRESS CORE OPTIMIZATIONS
// ======================================

// Disable WordPress emoji Scripts and Styles
add_action('init', function() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', fn($plugins) => is_array($plugins) ? array_diff($plugins, ['wpemoji']) : []);
    add_filter('wp_resource_hints', function($urls, $relation_type) {
        if ($relation_type === 'dns-prefetch') {
            $emoji_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
            $urls = array_diff($urls, [$emoji_url]);
        }
        return $urls;
    }, 10, 2);
});

// Remove "Edit" Link for non-logged-in Users
add_filter('edit_post_link', '__return_false');

// Disable Pingbacks and Trackbacks
add_filter('xmlrpc_enabled', '__return_false');
add_filter('pings_open', '__return_false');
add_filter('pre_ping', '__return_empty_array');

// Limit Post Revisions
if (!defined('WP_POST_REVISIONS')) {
    define('WP_POST_REVISIONS', 1);
}

// ======================================
// AUTHENTICATION & SECURITY
// ======================================

// Stay logged in longer
function er_stay_logged_in($expiration, $user_id) {
    $user = get_userdata($user_id);
    if (in_array('administrator', (array) $user->roles)) {
        return 365 * DAY_IN_SECONDS; // 1 year
    }
    return $expiration; // Default for others
}
add_filter('auth_cookie_expiration', 'er_stay_logged_in', 99, 2);

// Remove Site Health Access
remove_filter('user_has_cap', 'wp_maybe_grant_site_health_caps', 1, 4);

// ======================================
// CACHE MANAGEMENT
// ======================================

// Force LiteSpeed Purge on Content Save
add_action('save_post', function($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    if (function_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
        LiteSpeed_Cache_API::purge_all();
    }
}, 999);

// ======================================
// ADMIN INTERFACE CUSTOMIZATION
// ======================================

// Change Post Order
function er_custom_post_order($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    $post_types = array_merge(
        get_post_types(['_builtin' => true], 'names'),
        ['my-interests']
    );
    $post_type = $query->get('post_type');
    if (in_array($post_type, $post_types)) {
        if ($query->get('orderby') === '') {
            $query->set('orderby', 'title');
        }
        if ($query->get('order') === '') {
            $query->set('order', 'ASC');
        }
    }
}
add_action('pre_get_posts', 'er_custom_post_order');

// Style Gutenberg UI
function er_gutenberg_admin_styles() {
    echo '<style type="text/css">
        .wp-block { max-width: 1024px !important; }
        .blocks-widgets-container .editor-styles-wrapper { max-width: 1024px; }
        .block-editor-block-list__block.wp-block:hover { background-color: #e6f2ff; }
        .wp-block-group.block-editor-block-list__block.wp-block:hover { background-color: #e6f2ff; }
        .components-button:not(.is-primary):hover,
        .components-button:not(.is-primary):focus,
        .components-button:not(.is-primary):active {
            background-color: #f2f2f2;
        }
    </style>';
}
add_action("admin_head", "er_gutenberg_admin_styles");

// Keep old Widgets Editor
add_filter('use_widgets_block_editor', '__return_false');

// Custom Admin-Menu Order
add_filter('custom_menu_order', '__return_true');
add_filter('menu_order', function ($menu_order) {
    return [
        'hostinger',
        'index.php',
        'ct-dashboard',
        'edit.php?post_type=page',
        'edit.php',
        'edit.php?post_type=my-interests',
        'edit.php?post_type=my-traits',
        'upload.php',
        'themes.php',
        'plugins.php',
        'users.php',
        'tools.php',
        'options-general.php',
        'contact-form',
        'theseoframework-settings',
		'snippets',
        'litespeed',
    ];
});

// Remove Comments from Menu
function er_remove_comments_menu() {
    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'er_remove_comments_menu');
