<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ======================================
// WORDPRESS CORE OPTIMIZATIONS
// ======================================

add_action( 'enqueue_block_assets', function() {
    if ( is_admin() ) {
        wp_add_inline_style(
            'wp-block-library',
            '.editor-styles-wrapper .block-editor-block-list__layout.is-root-container > :where(:not(.alignleft):not(.alignright):not(.alignfull)),
             .editor-styles-wrapper .edit-post-visual-editor__post-title-wrapper > :where(:not(.alignleft):not(.alignright):not(.alignfull)) {
                max-width: 90% !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }'
        );
    }
} );

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

// Prevent cached Previews (with timestamp)
add_filter('preview_post_link', function($url){
    return add_query_arg('ts', time(), $url);
});

// Limit Post Revisions
if (!defined('WP_POST_REVISIONS')) {
    define('WP_POST_REVISIONS', 1);
}

// Force LiteSpeed Purge on Content Save
add_action('save_post', function($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    if (function_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
        LiteSpeed_Cache_API::purge_all();
    }
}, 999);

// Force Empty Entire Cache on Core, Theme & Plugin Updates
add_action( 'upgrader_process_complete', function( $upgrader, $options ) {
    if ( in_array( $options['type'], array( 'theme', 'plugin', 'core' ) ) ) {
        do_action( 'litespeed_purge_all_object' );
    }
}, 10, 2 );

// ======================================
// EDITOR (THIS ONE HERE) INTERFACE
// ======================================

add_action('admin_head', function () {
    echo '<style>
        .CodeMirror .cm-matchhighlight,
        .CodeMirror .CodeMirror-selectedtext {
            background-color: #ffffaa !important; /* Fallback */
            background-color: rgba(255, 255, 0, .4) !important; /* Same as .cm-searching */
            color: inherit !important; /* Keep original Font Color */
        }
    </style>';
});

// ======================================
// ADMIN INTERFACE CUSTOMIZATION
// ======================================

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
		'edit.php?post_type=my-quotes',
        'upload.php',
        'themes.php',
        'plugins.php',
        'users.php',
        'tools.php',
        'options-general.php',
        'contact-form',
		'er-subscribers',
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
