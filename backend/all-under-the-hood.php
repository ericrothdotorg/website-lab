<?php

if (is_admin()) {

    // Remove tags support from posts
    function er_unregister_tags() {
        unregister_taxonomy_for_object_type('post_tag', 'post');
    }
    add_action('init', 'er_unregister_tags');

    // Disable pingbacks and trackbacks
    add_filter('xmlrpc_enabled', '__return_false');
    add_filter('pings_open', '__return_false');
    add_filter('pre_ping', '__return_empty_array');

    // Remove "Edit" link for viewing as non-logged-in user
    add_filter('edit_post_link', '__return_false');

    // Stay logged in to WordPress longer
    function er_stay_logged_in($expires) {
        return 25 * YEAR_IN_SECONDS;
    }
    add_filter('auth_cookie_expiration', 'er_stay_logged_in', 10);

    // Limit post revisions
    if (!defined('WP_POST_REVISIONS')) {
        define('WP_POST_REVISIONS', 1);
    }

    // Sort post types alphabetically in admin
    function custom_post_order($query) {
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
    add_action('pre_get_posts', 'custom_post_order');

    // Use classic widget editor
    add_filter('use_widgets_block_editor', '__return_false');

    // Custom admin editor styles
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

    // Adjust admin menu order
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
            'subscriber-list',
            'contact-form',
            'asgarosforum-structure',
            'theseoframework-settings',
            'wpcodebox2',
            'litespeed',
        ];
    });

    // Remove comments menu item
    add_action('admin_menu', function () {
        remove_menu_page('edit-comments.php');
    });

    // Remove Site Health access
    remove_filter('user_has_cap', 'wp_maybe_grant_site_health_caps', 1, 4);
}
