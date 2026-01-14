<?php
defined('ABSPATH') || exit;

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

// Stay logged in longer
function er_stay_logged_in($expiration, $user_id) {
    $user = get_userdata($user_id);
    if (in_array('administrator', (array) $user->roles)) {
        return 365 * DAY_IN_SECONDS; // 1 year
    }
    return $expiration; // Default for others
}
add_filter('auth_cookie_expiration', 'er_stay_logged_in', 99, 2);

// Force LiteSpeed Purge on Content Save
add_action('save_post', function($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    if (function_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
        LiteSpeed_Cache_API::purge_all();
    }
}, 999);

// Prevent cached previews (with timestamp)
add_filter('preview_post_link', function($url){
    return add_query_arg('ts', time(), $url);
});

// Prevent cached frontend for logged-in editors
add_action('template_redirect', function () {
    if (!is_user_logged_in()) return;
    if (!current_user_can('edit_posts')) return;
    nocache_headers();
});

// Unregister Tags
function er_unregister_tags() {
    if (!is_admin()) return;
    unregister_taxonomy_for_object_type('post_tag', 'post');
}
add_action('init', 'er_unregister_tags');

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

// Remove Site Health Access
remove_filter('user_has_cap', 'wp_maybe_grant_site_health_caps', 1, 4);
