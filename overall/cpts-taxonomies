<?php

// ✅ Register Custom TAXONOMY for Pages
function page_register_taxonomy() {
	$labels = array(
		'name'                       => esc_html_x( 'Things', 'textdomain' ),
		'singular_name'              => esc_html_x( 'Thing', 'textdomain' ),
		'menu_name'                  => esc_html__( 'Things', 'textdomain' ),
		'all_items'                  => esc_html__( 'All Things', 'textdomain' ),
		'parent_item'                => esc_html__( 'Parent Thing', 'textdomain' ),
		'parent_item_colon'          => esc_html__( 'Parent Thing:', 'textdomain' ),
		'new_item_name'              => esc_html__( 'New Thing', 'textdomain' ),
		'add_new_item'               => esc_html__( 'Add Thing', 'textdomain' ),
		'edit_item'                  => esc_html__( 'Edit Thing', 'textdomain' ),
		'update_item'                => esc_html__( 'Update Thing', 'textdomain' ),
		'view_item'                  => esc_html__( 'View Thing', 'textdomain' ),
		'separate_items_with_commas' => esc_html__( 'Separate things with commas', 'textdomain' ),
		'add_or_remove_items'        => esc_html__( 'Add or remove things', 'textdomain' ),
		'choose_from_most_used'      => esc_html__( 'Choose from the most used', 'textdomain' ),
		'popular_items'              => esc_html__( 'Popular things', 'textdomain' ),
		'search_items'               => esc_html__( 'Search Things', 'textdomain' ),
		'not_found'                  => esc_html__( 'Not Found', 'textdomain' ),
		'no_terms'                   => esc_html__( 'No Things', 'textdomain' ),
		'items_list'                 => esc_html__( 'Things list', 'textdomain' ),
		'items_list_navigation'      => esc_html__( 'Things list navigation', 'textdomain' ),
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'query_var'        			 => true,
		'rewrite'                    => $rewrite,
		'show_in_rest'               => true,
	);
	$rewrite = array(
		'slug'                       => 'things',
		'with_front'                 => true,
		'hierarchical'               => true,
	);
	register_taxonomy( 'things', array( 'page' ), $args );
}
add_action( 'init', 'page_register_taxonomy', 0 );

// ✅ Register Custom TAXONOMY for My Interests
function mi_register_taxonomy() {
    $rewrite = array(
        'slug'         => 'topics',
        'with_front'   => true,
        'hierarchical' => true,
    );
    $labels = array(
        'name'                       => esc_html_x( 'Topics', 'taxonomy general name', 'textdomain' ),
        'singular_name'              => esc_html_x( 'Topic', 'taxonomy singular name', 'textdomain' ),
        'menu_name'                  => esc_html__( 'Topics', 'textdomain' ),
        'all_items'                  => esc_html__( 'All Topics', 'textdomain' ),
        'parent_item'                => esc_html__( 'Parent Topic', 'textdomain' ),
        'parent_item_colon'          => esc_html__( 'Parent Topic:', 'textdomain' ),
        'new_item_name'              => esc_html__( 'New Topic Name', 'textdomain' ),
        'add_new_item'               => esc_html__( 'Add New Topic', 'textdomain' ),
        'edit_item'                  => esc_html__( 'Edit Topic', 'textdomain' ),
        'update_item'                => esc_html__( 'Update Topic', 'textdomain' ),
        'view_item'                  => esc_html__( 'View Topic', 'textdomain' ),
        'search_items'               => esc_html__( 'Search Topics', 'textdomain' ),
        'not_found'                  => esc_html__( 'No topics found', 'textdomain' ),
    );
    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => true,
        'show_in_rest'      => true,
        'query_var'         => true,
        'rewrite'           => $rewrite,
    );
    register_taxonomy( 'topics', array( 'my-interests' ), $args );
}
add_action( 'init', 'mi_register_taxonomy', 0 );

// ✅ Register Custom POST TYPE for My Interests
function mi_register_post_type() {
	$args = [
		'label'  => esc_html__( 'My Interests', 'textdomain' ),
		'labels' => [
			'name'               => esc_html__( 'My Interests', 'textdomain' ),
			'menu_name'          => esc_html__( 'My Interests', 'textdomain' ),
			'singular_name'      => esc_html__( 'Interest', 'textdomain' ),
			'add_new'            => esc_html__( 'Add New Interest', 'textdomain' ),
			'add_new_item'       => esc_html__( 'Add New Interest', 'textdomain' ),
			'new_item'           => esc_html__( 'New Interest', 'textdomain' ),
			'edit_item'          => esc_html__( 'Edit Interest', 'textdomain' ),
			'view_item'          => esc_html__( 'View Interest', 'textdomain' ),
			'update_item'        => esc_html__( 'View Interest', 'textdomain' ),
			'all_items'          => esc_html__( 'All Interests', 'textdomain' ),
			'search_items'       => esc_html__( 'Search Interests', 'textdomain' ),
			'parent_item_colon'  => esc_html__( 'Parent Interest', 'textdomain' ),
			'not_found'          => esc_html__( 'No Interests found', 'textdomain' ),
			'not_found_in_trash' => esc_html__( 'No Interests found in Trash', 'textdomain' ),
		],
		'public'              => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'show_in_rest'        => true,
		'capability_type'     => 'post',
		'hierarchical'        => false,
		'has_archive'         => true,
		'query_var'           => true,
		'can_export'          => true,
		'show_in_menu'        => true,
		'map_meta_cap'        => true,
		'menu_position'       => 20,
		'menu_icon'           => 'dashicons-portfolio',
		'supports' => [
			'title',
			'editor',
			'author',
			'excerpt',
			'thumbnail',
			'custom-fields',
			'revisions',
			'post-formats',
		],
		'taxonomies' => [
			'topics',
		],
		'rewrite'        => array(
			'slug'       => 'my-interests',
			'with_front' => true,
			'feeds'      => true,
			'pages'      => true,
		),
	];
	register_post_type( 'my-interests', $args );
}
add_action( 'init', 'mi_register_post_type' );

// ✅ Register Custom TAXONOMY for My Traits
function mt_register_taxonomy() {
    $rewrite = array(
        'slug'         => 'types',
        'with_front'   => true,
        'hierarchical' => true,
    );
    $labels = array(
        'name'                       => esc_html_x( 'Types', 'taxonomy general name', 'textdomain' ),
        'singular_name'              => esc_html_x( 'Type', 'taxonomy singular name', 'textdomain' ),
        'menu_name'                  => esc_html__( 'Types', 'textdomain' ),
        'all_items'                  => esc_html__( 'All Types', 'textdomain' ),
        'parent_item'                => esc_html__( 'Parent Type', 'textdomain' ),
        'parent_item_colon'          => esc_html__( 'Parent Type:', 'textdomain' ),
        'new_item_name'              => esc_html__( 'New Type Name', 'textdomain' ),
        'add_new_item'               => esc_html__( 'Add New Type', 'textdomain' ),
        'edit_item'                  => esc_html__( 'Edit Type', 'textdomain' ),
        'update_item'                => esc_html__( 'Update Type', 'textdomain' ),
        'view_item'                  => esc_html__( 'View Type', 'textdomain' ),
        'separate_items_with_commas' => esc_html__( 'Separate types with commas', 'textdomain' ),
        'add_or_remove_items'        => esc_html__( 'Add or remove types', 'textdomain' ),
        'choose_from_most_used'      => esc_html__( 'Choose from the most used', 'textdomain' ),
        'popular_items'              => esc_html__( 'Popular Types', 'textdomain' ),
        'search_items'               => esc_html__( 'Search Types', 'textdomain' ),
        'not_found'                  => esc_html__( 'No Types found', 'textdomain' ),
        'no_terms'                   => esc_html__( 'No Types', 'textdomain' ),
        'items_list'                 => esc_html__( 'Types list', 'textdomain' ),
        'items_list_navigation'      => esc_html__( 'Types list navigation', 'textdomain' ),
    );
    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => true,
        'show_in_rest'      => true,
        'query_var'         => true,
        'rewrite'           => $rewrite,
    );
    register_taxonomy( 'types', array( 'my-traits' ), $args );
}
add_action( 'init', 'mt_register_taxonomy', 0 );

// ✅ Register Custom POST TYPE for My Traits
function mt_register_post_type() {
	$args = [
		'label'  => esc_html__( 'My Traits', 'textdomain' ),
		'labels' => [
			'name'               => esc_html__( 'My Traits', 'textdomain' ),
			'menu_name'          => esc_html__( 'My Traits', 'textdomain' ),
			'singular_name'      => esc_html__( 'Trait', 'textdomain' ),
			'add_new'            => esc_html__( 'Add Trait', 'textdomain' ),
			'add_new_item'       => esc_html__( 'Add new Trait', 'textdomain' ),
			'new_item'           => esc_html__( 'New Trait', 'textdomain' ),
			'edit_item'          => esc_html__( 'Edit Trait', 'textdomain' ),
			'view_item'          => esc_html__( 'View Trait', 'textdomain' ),
			'update_item'        => esc_html__( 'View Trait', 'textdomain' ),
			'all_items'          => esc_html__( 'All My Traits', 'textdomain' ),
			'search_items'       => esc_html__( 'Search My Traits', 'textdomain' ),
			'parent_item_colon'  => esc_html__( 'Parent Trait', 'textdomain' ),
			'not_found'          => esc_html__( 'No Traits found', 'textdomain' ),
			'not_found_in_trash' => esc_html__( 'No Traits found in Trash', 'textdomain' ),
		],
		'public'              => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'show_in_rest'        => true,
		'capability_type'     => 'page',
		'hierarchical'        => true,
		'has_archive'         => true,
		'query_var'           => true,
		'can_export'          => true,
		'show_in_menu'        => true,
		'map_meta_cap'        => true,
		'menu_position'       => 20,
		'menu_icon'           => 'dashicons-star-filled',
		'supports' => [
			'title',
			'editor',
			'author',
			'excerpt',
			'thumbnail',
			'custom-fields',
			'revisions',
			'page-attributes',
		],
		'taxonomies' => [
			'types',
		],
		'rewrite'        => array(
			'slug'       => 'my-traits',
			'with_front' => true,
			'feeds'      => true,
			'pages'      => true,
		),
	];
	register_post_type( 'my-traits', $args );
}
add_action( 'init', 'mt_register_post_type' );
