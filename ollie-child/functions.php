<?php
/**
 * Ollie Child — theme functions.
 *
 * Purpose: load the child theme's own style.css on the front end.
 *
 * Why this is needed: the Ollie parent enqueues its stylesheet with
 * get_template_directory_uri(), which always points at the PARENT directory.
 * It therefore never loads the child's style.css. Without the enqueue below,
 * any CSS we put in the child style.css would be ignored. (theme.json styles
 * are unaffected — those load through the FSE engine, not through style.css.)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // No direct access.
}
/**
 * Enqueue the child stylesheet.
 *
 * - Handle 'ollie-child' is our own.
 * - The dependency array( 'ollie' ) is the PARENT's style handle
 *   (Ollie registers it as sanitize_title( 'Ollie' ) = 'ollie'). Declaring it
 *   makes the child stylesheet load AFTER the parent, so child rules win.
 * - filemtime() as the version string busts the browser cache automatically
 *   whenever style.css changes, so edits show up without cache-fighting.
 */
function ollie_child_enqueue_styles() {
    $child_style_path = get_stylesheet_directory() . '/style.css';
    wp_enqueue_style(
        'ollie-child',
        get_stylesheet_directory_uri() . '/style.css',
        array( 'ollie' ),
        file_exists( $child_style_path ) ? filemtime( $child_style_path ) : null
    );
}
add_action( 'wp_enqueue_scripts', 'ollie_child_enqueue_styles' );

/**
 * Restore the Excerpt box for Pages.
 */
function ollie_child_enable_page_excerpts() {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'ollie_child_enable_page_excerpts' );
