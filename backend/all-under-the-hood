<?php

// ✅ Remove query string from static resources
function remove_cssjs_ver($src) {
    if (strpos($src, '?ver=')) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('style_loader_src', 'remove_cssjs_ver', 10, 2);
add_filter('script_loader_src', 'remove_cssjs_ver', 10, 2);

// ✅ Remove tags support from posts
function er_unregister_tags() {
    unregister_taxonomy_for_object_type('post_tag', 'post');
}
add_action('init', 'er_unregister_tags');

// ✅ Enable the Dashicons script
function load_dashicons_front_end() {
    wp_enqueue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'load_dashicons_front_end');

// ✅ Disable Pingbacks & Trackbacks
add_filter('xmlrpc_enabled', '__return_false');
add_filter('pings_open', '__return_false');
add_filter('pre_ping', '__return_empty_array');

// ✅ Disable Emojis in Wordpress
function disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', 'disable_emojis_tinymce');
    add_filter('wp_resource_hints', 'disable_emojis_remove_dns_prefetch', 10, 2);
}
add_action('init', 'disable_emojis');
function disable_emojis_tinymce($plugins) {
    if (is_array($plugins)) {
        return array_diff($plugins, array('wpemoji'));
    } else {
        return array();
    }
}
function disable_emojis_remove_dns_prefetch($urls, $relation_type) {
    if ('dns-prefetch' == $relation_type) {
        $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
        $urls = array_diff($urls, array($emoji_svg_url));
    }
    return $urls;
}

// ✅ View Site from Non-Logged-In User's Perspective
add_filter('edit_post_link', '__return_false');

// ✅ Stay Logged in to WP: https://digwp.com/2023/01/stay-logged-in-to-wordpress/
function er_stay_logged_in($expires) {
    return 25 * YEAR_IN_SECONDS; // default 48 hours
}
add_filter('auth_cookie_expiration', 'er_stay_logged_in', 10);

// ✅ Limit revisions (set number or false for none)
define( 'WP_POST_REVISIONS', 1 );

// ✅ Change order of posts in WP dashboard admin
function custom_post_order($query) {
    $post_types = array_merge(
        get_post_types(array('_builtin' => true), 'names'),
        array('my-interests') // Add your custom post type here
    );
    $post_type = $query->get('post_type');
    if (in_array($post_type, $post_types)) {
        if ($query->get('orderby') == '') {
            $query->set('orderby', 'title');
        }
        if ($query->get('order') == '') {
            $query->set('order', 'ASC');
        }
    }
    return $query;
}
if (is_admin()) {
    add_action('pre_get_posts', 'custom_post_order');
}

// ✅ How to manage widgets (restore classic editor)
add_filter('use_widgets_block_editor', '__return_false', 10);

// ✅ Style the block editor
function er_gutenberg_admin_styles() {
    echo '<style type="text/css">';
    echo ".wp-block {max-width: 1024px !important;}";
    echo ".blocks-widgets-container .editor-styles-wrapper {max-width: 1024px;}";
    echo ".block-editor-block-list__block.wp-block:hover {background-color: #e6f2ff;}";
    echo ".wp-block-group.block-editor-block-list__block.wp-block:hover {background-color: #e6f2ff;}";
    echo ".components-button:not(.is-primary):hover {background-color: #f2f2f2;}";
    echo ".components-button:not(.is-primary):focus {background-color: #f2f2f2;}";
    echo ".components-button:not(.is-primary):active {background-color: #f2f2f2;}";
    echo "</style>";
}
add_action("admin_head", "er_gutenberg_admin_styles");

// ✅ Adjust menu order in admin menu
add_filter('custom_menu_order', '__return_true');
add_filter('menu_order', function ($menu_order) {
  return [
    'hostinger', // Host "Hostinger"
    'index.php', // WP "Dashboard"
    'ct-dashboard', // Theme "Blocksy"
    'edit.php?post_type=page', // WP "Pages"
    'edit.php', // WP "Posts"
    'edit.php?post_type=my-interests', // Custom "My Interests"
    'edit.php?post_type=my-traits', // Custom "My Traits"
    'upload.php', // WP "Media"
    'themes.php', // WP "Appearance"
    'plugins.php', // WP "Plugins"
    'users.php', // WP "Users"
    'tools.php', // WP "Tools"
    'options-general.php', // WP "Settings"
    'subscriber-list', // Custom "Subscribers"
    'contact-form', // Custom "Contact Form"
    'forum-structure', // Custom "Forum"
    'asgarosforum-structure', // Plugin "Asgaros Forum"
    'theseoframework-settings', // Plugin "SEO Framework"
    'wpcodebox2', // Plugin "WPCodeBox 2"
    'litespeed', // Plugin "LiteSpeed Cache"
  ];
});

// ✅ Remove Comments from admin menu
add_action('admin_menu', function () {
  remove_menu_page('edit-comments.php');
});

// ✅ Remove Site Health Check from admin menu and dashboard
remove_filter( 'user_has_cap', 'wp_maybe_grant_site_health_caps', 1, 4 );
