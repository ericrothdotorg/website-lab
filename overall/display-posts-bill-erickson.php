<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// =====================================
// SELECT DROPDOWN FUNCTIONALITY
// =====================================

/* Open Select Wrapper with accessibility Features */
function be_dps_select_open( $output, $atts ) {
    if ( empty( $atts['wrapper'] ) || 'select' !== $atts['wrapper'] ) {
        return $output;
    }
    $unique_id = uniqid( 'dps_', true );
    $desc_id   = "{$unique_id}_desc";
    $live_id   = "{$unique_id}_live";
    return sprintf(
        '<p id="%1$s" class="screen-reader-text">Selecting an option will take you to that post.</p>
        <p id="%2$s" aria-live="polite" class="screen-reader-text"></p>
        <select class="display-posts-listing" data-live-id="%2$s" aria-label="Select a post" aria-describedby="%1$s">
        <option value="" selected disabled>Make Your Choice</option>',
        esc_attr( $desc_id ),
        esc_attr( $live_id )
    );
}
add_filter( 'display_posts_shortcode_wrapper_open', 'be_dps_select_open', 10, 2 );

/* Preload Taxonomy Term Caches for Select Wrapper Queries */
add_action( 'the_posts', function( $posts, $query ) {
    if ( is_admin() || $query->is_main_query() || 'select' !== $query->get( 'wrapper' ) || empty( $posts ) ) {
        return $posts;
    }
    $post_ids = wp_list_pluck( $posts, 'ID' );
    update_post_term_cache( $post_ids, [ 'category', 'topics', 'groups' ] );
    update_post_cache( $posts );
    return $posts;
}, 10, 2 );

// =====================================
// MENU-ORDER HELPER FOR PAGES
// =====================================

function dps_get_page_menu_groups( $menu_location = 'menu_1' ) {
	// Block-theme nav: parse the wp_navigation post and map each page ID to its
	// top-level section (About Me / Personal / Professional / This Site). Labels
	// may contain HTML, so strip to text. $menu_location kept for compat, unused
	static $cache = null;
	if ( $cache !== null ) {
		return $cache;
	}

	$nav_post_id = 158986; // wp_navigation post backing the primary nav block
	$nav         = get_post( $nav_post_id );
	if ( ! $nav || empty( $nav->post_content ) ) {
		return $cache = [];
	}

	$blocks = parse_blocks( $nav->post_content );
	$result = [];

	// Recursive walker: carries the active top-level label down through nesting.
	$walk = function ( $blocks, $top_label, $top_order ) use ( &$walk, &$result ) {
		foreach ( $blocks as $block ) {
			$name  = $block['blockName'] ?? '';
			$attrs = $block['attrs'] ?? [];

			if ( $name === 'core/navigation-submenu' ) {
				$is_top = ! empty( $attrs['isTopLevelItem'] );

				if ( $is_top || $top_label === null ) {
					// Establish (or re-establish) the top-level section here.
					$label = trim( wp_strip_all_tags( $attrs['label'] ?? '' ) );
					$order = isset( $attrs['id'] ) ? (int) $attrs['id'] : PHP_INT_MAX;
					$top_label = $label;
					$top_order = $order;
				}

				// A submenu that is itself a page belongs to the current top section.
				if ( ( $attrs['type'] ?? '' ) === 'page' && isset( $attrs['id'] ) ) {
					$result[ (int) $attrs['id'] ] = [ 'label' => $top_label, 'order' => $top_order ];
				}

				if ( ! empty( $block['innerBlocks'] ) ) {
					$walk( $block['innerBlocks'], $top_label, $top_order );
				}

			} elseif ( $name === 'core/navigation-link' ) {
				if ( ( $attrs['type'] ?? '' ) === 'page' && isset( $attrs['id'] ) && $top_label !== null ) {
					$result[ (int) $attrs['id'] ] = [ 'label' => $top_label, 'order' => $top_order ];
				}
			}
		}
	};

	$walk( $blocks, null, PHP_INT_MAX );

	return $cache = $result;
}

// =====================================
// GERMAN TITLE DETECTOR
// =====================================

function dps_is_german_title( string $title ): bool {
    $german_titles = [
        'Berufswelt',
        'Mein Hintergrund',
        'Meine Eigenschaften',
        'Meine Kompetenzen',
        'Mein Kompass',
        'Meine Publikationen',
        'Meine Verfügbarkeit',
        'Über Mich',
        'Kontakt',
    ];
    return in_array( trim( $title ), $german_titles, true );
}

// =====================================
// GROUPED COLLECTOR CLASS
// =====================================

class DPS_Grouped_Collector {
    public static $instances = [];
    /* Taxonomy Mapping for Post Types */
    public static function get_group_config( $post_type ) {
        $configs = [
            'page'          => [ 'menu_location' => 'menu_1' ],
            'my-traits'     => [ 'term_id' => -200, 'label' => 'My Traits' ],
            'post'          => [ 'taxonomy' => 'category' ],
            'my-interests'  => [ 'taxonomy' => 'topics' ],
            'my-quotes'     => [ 'taxonomy' => 'groups' ],
        ];
        return $configs[ $post_type ] ?? [];
    }
    /* Add Post to grouped Collection */
    public static function add_post( $atts, $post ) {
        $instance_id = md5( json_encode( $atts ) );
        if ( ! isset( self::$instances[ $instance_id ] ) ) {
            self::$instances[ $instance_id ] = [];
        }
        $config = self::get_group_config( $post->post_type );
        // Handle menu-based Grouping (pages)
        if ( isset( $config['menu_location'] ) ) {
            $page_groups = dps_get_page_menu_groups( $config['menu_location'] );
            if ( isset( $page_groups[ $post->ID ] ) ) {
                $group_info = $page_groups[ $post->ID ];
                $term_id    = 'menu_' . sanitize_title( $group_info['label'] );
                $label      = $group_info['label'];
                $order      = $group_info['order'];
			} else {
				// Page exists but isn't in the Menu — Split into In German and Others
				$title = get_the_title( $post );
				if ( dps_is_german_title( $title ) ) {
					$term_id = 'menu_unlisted_de';
					$label   = 'In German';
					$order   = 9998;
				} else {
					$term_id = 'menu_unlisted';
					$label   = 'Others';
					$order   = 9999;
				}
			}
        }
        // Handle manual Grouping (traits)
        elseif ( isset( $config['term_id'] ) ) {
            $term_id = $config['term_id'];
            $label   = $config['label'];
            $order   = 0;
        }
        // Handle taxonomy-based Grouping (categories, topics, groups)
        elseif ( isset( $config['taxonomy'] ) ) {
            $terms = get_the_terms( $post->ID, $config['taxonomy'] );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $term    = $terms[0];
                $term_id = $term->term_id;
                $label   = $term->name;
                $order   = 0;
            } else {
                $term_id = -1;
                $label   = 'Uncategorized';
                $order   = 9998;
            }
        }
        // Default Fallback
        else {
            $term_id = -999;
            $label   = 'Uncategorized';
            $order   = 9999;
        }
        // Initialize Group if needed
        if ( ! isset( self::$instances[ $instance_id ][ $term_id ] ) ) {
            self::$instances[ $instance_id ][ $term_id ] = [
                'label' => $label,
                'order' => $order,
                'posts' => [],
            ];
        }
        // Add Post Data
        self::$instances[ $instance_id ][ $term_id ]['posts'][] = [
            'title' => get_the_title( $post ),
            'link'  => get_permalink( $post ),
        ];
    }
    /* Render grouped Select Options */
    public static function render_grouped( $atts ) {
        $instance_id = md5( json_encode( $atts ) );
        $grouped     = self::$instances[ $instance_id ] ?? [];
        // Sort by Menu Order (for pages), fall back to alphabetical for Taxonomy Groups
        uasort( $grouped, function( $a, $b ) {
            $ao = $a['order'] ?? PHP_INT_MAX;
            $bo = $b['order'] ?? PHP_INT_MAX;
            if ( $ao !== $bo ) {
                return $ao <=> $bo;
            }
            return strcasecmp( $a['label'] ?? '', $b['label'] ?? '' );
        } );
        $output = '';
        foreach ( $grouped as $group ) {
            $label     = $group['label'] ?? '';
            $has_label = is_string( $label ) && trim( $label ) !== '';
            if ( $has_label ) {
                $output .= sprintf( '<optgroup label="%s">', esc_attr( $label ) );
            }
            foreach ( $group['posts'] as $post ) {
                $output .= sprintf(
                    '<option value="%s">%s</option>',
                    esc_url( $post['link'] ),
                    esc_html( $post['title'] )
                );
            }
            if ( $has_label ) {
                $output .= '</optgroup>';
            }
        }
        // Cleanup Memory
        unset( self::$instances[ $instance_id ] );
        return $output;
    }
}

/* Close Select Wrapper with grouped Output */
function be_dps_select_close_grouped( $output, $atts ) {
    if ( ! empty( $atts['wrapper'] ) && 'select' === $atts['wrapper'] ) {
        return DPS_Grouped_Collector::render_grouped( $atts ) . '</select>';
    }
    return $output;
}
add_filter( 'display_posts_shortcode_wrapper_close', 'be_dps_select_close_grouped', 10, 2 );

/* Collect Post Data during Output */
function be_dps_option_output_grouped( $output, $atts ) {
    if ( empty( $atts['wrapper'] ) || 'select' !== $atts['wrapper'] ) {
        return $output;
    }
    $post = get_post();
    if ( $post ) {
        DPS_Grouped_Collector::add_post( $atts, $post );
    }
    return '';
}
add_filter( 'display_posts_shortcode_output', 'be_dps_option_output_grouped', 10, 2 );

// =====================================
// DPS FOR TAXONOMIES
// =====================================

function display_taxonomies_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'taxonomy'      => 'category',
        'show_count'    => 'false',
        'image_size'    => '',
        'orderby'       => 'name',
        'order'         => 'ASC',
        'hide_empty'    => 'true',
        'wrapper'       => 'ul',
        'wrapper_class' => '',
        'wrapper_id'    => '',
        'include_id'       => '',
    ], $atts, 'display-taxonomies' );
    // Validate Taxonomy
    if ( ! taxonomy_exists( $atts['taxonomy'] ) ) {
        return '';
    }
    // Sanitize Wrapper Tag
    $tag = in_array( $atts['wrapper'], [ 'ul', 'ol', 'div' ], true ) ? $atts['wrapper'] : 'ul';
    $terms = get_terms( [
        'taxonomy'   => $atts['taxonomy'],
        'orderby'    => $atts['orderby'],
        'order'      => strtoupper( $atts['order'] ),
        'hide_empty' => 'true' === $atts['hide_empty'],
        'include'    => ! empty( $atts['include_id'] )
                        ? array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $atts['include_id'] ) ) ) )
                        : [],
    ] );
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return '';
    }
	// Wrapper Attributes
    $classes = trim( 'display-taxonomies ' . $atts['wrapper_class'] );
    $id_attr = $atts['wrapper_id'] ? sprintf( ' id="%s"', esc_attr( $atts['wrapper_id'] ) ) : '';
    $items   = '';
    global $wpdb;
    foreach ( $terms as $term ) {
        $link = get_term_link( $term );
        if ( is_wp_error( $link ) ) continue;
        $image = '';
        if ( ! empty( $atts['image_size'] ) ) {
            $img_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = 'er_term_image_id'",
                $term->term_id
            ) );
            if ( $img_id ) {
                $img_html = wp_get_attachment_image( $img_id, $atts['image_size'] );
                if ( $img_html ) {
                    $image = sprintf( '<a href="%s" class="image">%s</a>', esc_url( $link ), $img_html );
                }
            }
        }
        $count = 'true' === $atts['show_count']
            ? sprintf( ' <span class="term-count">(%d)</span>', (int) $term->count )
            : '';
        $items .= sprintf(
            '<div class="listing-item">%1$s<div class="title"><a href="%2$s">%3$s</a>%4$s</div></div>',
            $image,
            esc_url( $link ),
            esc_html( $term->name ),
            $count
        );
    }
    return sprintf(
        '<%1$s class="%2$s"%3$s>%4$s</%1$s>',
        $tag,
        esc_attr( $classes ),
        $id_attr,
        $items
    );
}
add_shortcode( 'display-taxonomies', 'display_taxonomies_shortcode' );

// =====================================
// INLINE STYLES (in Head)
// =====================================

add_action( 'wp_head', function () {
    ?>
	<!-- NOTE: These styles are mirrored in the EDITOR LIVE PREVIEWS snippet for editor display. Update both when changing. -->
    <style>
		/* Accessibility */
		.screen-reader-text {position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden;}
		
		/* Base Styles */
		.display-posts-listing {cursor: pointer;}
		.display-posts-listing .listing-item {clear: both; overflow: hidden; background: var(--color-8); border: 1px solid var(--color-5); border-radius: 25px;}
.display-posts-listing:not(.grid) .listing-item {display: flex; flex-direction: column;}
		.display-posts-listing .listing-item:hover {background: var(--color-7);}
		.display-posts-listing img {aspect-ratio: 16/9; transition: transform 0.3s ease; will-change: transform;}
.display-posts-listing:not(.grid) img {display: block; width: 100%; object-fit: cover;}
		.display-posts-listing img:hover {transform: scale(1.05);}
		.display-posts-listing .title {display: flex; align-items: center; justify-content: center; margin: 0; padding: 16px 1rem; text-align: center; font-size: var(--er-fs-md); width: 100%; box-sizing: border-box;}
		.listing-item .excerpt-dash {display: none;}
		.display-posts-listing .excerpt {clear: right; display: block; text-align: center; margin: 0 16px 20px;}
		.display-posts-listing .category-display, .display-posts-listing.grid .category-display {display: block; font-size: var(--er-fs-sm); text-align: center; margin: -8px 0 16px; opacity: 0.75;}
		
		/* Grid Layout (2 columns) */
		.display-posts-listing.grid {display: grid; grid-template-columns: repeat(2, 1fr); grid-gap: 1.75rem 1.5rem;}
		.display-posts-listing.grid img {display: block; max-width: 100%; height: auto;}
		.display-posts-listing.grid .title {min-height: 0; padding: 12px 1rem; font-size: var(--er-fs-md);}
		@media (max-width: 600px) {.display-posts-listing.grid .excerpt {padding: 0 8px; font-size: var(--er-fs-xs);}}
		@media (min-width: 600px) {.display-posts-listing.grid .excerpt {padding: 0 16px;}}
		
		/* Grid Layout (4 columns) */
		@media (min-width: 600px) and (max-width: 992px) {.display-posts-listing.grid#four-columns {grid-template-columns: repeat(2, 1fr);}}
		@media (min-width: 992px) {.display-posts-listing.grid#four-columns {grid-template-columns: repeat(4, 1fr);}}
		@media (min-width: 600px) {.display-posts-listing.grid#four-columns .title {font-size: var(--er-fs-md);}}
		
		/* Grid Layout (6 columns) */
		.display-posts-listing.grid#six-columns .title {min-height: 0; padding: 8px 0.5rem; font-size: var(--er-fs-xs);}
		@media (min-width: 600px) and (max-width: 992px) {.display-posts-listing.grid#six-columns {grid-template-columns: repeat(3, 1fr);}}
		@media (min-width: 992px) {.display-posts-listing.grid#six-columns {grid-template-columns: repeat(6, 1fr);}}
		
		/* Layout Variations */
		.display-posts-listing#small-version, .display-posts-listing#notorious-big {overflow: hidden;}
		.display-posts-listing.grid#small-version .listing-item {margin-bottom: 0;}
		.display-posts-listing.grid#small-version .title {min-height: 0; padding: 10px 0.5rem; font-size: var(--er-fs-xs);}
		.display-posts-listing#notorious-big .title, .display-posts-listing.grid#notorious-big .title {min-height: 0; padding: 7.5px 1rem 2.5px; font-size: var(--er-fs-lg);}
		.display-posts-listing.grid#notorious-big .excerpt {font-size: var(--er-fs-body);}
		@media (max-width: 768px) {.display-posts-listing.grid#notorious-big {grid-template-columns: 1fr;}}
		
		/* FAQs Layout */
		.display-posts-faqs .listing-item {clear: both; overflow: hidden; margin-bottom: 20px;}
		.display-posts-faqs .image {float: left; margin: 0 16px 0 0;}
		.display-posts-faqs .title {display: block; min-height: 0; padding: 0; text-align: justify; font-size: var(--er-fs-body); margin-top: -4px;}
		.display-posts-faqs .excerpt {display: block; text-align: justify;}
		
		/* Trending Layout */
		.display-posts-trending {display: flex; flex-wrap: wrap; gap: 20px;}
		.display-posts-listing.display-posts-trending .listing-item {display: flex; flex-direction: row; align-items: center; justify-content: flex-start; flex: 1 1 calc(16.66% - 20px); box-sizing: border-box; margin-bottom: 20px; padding: 0; overflow: visible; background: none; border: none;}
		.display-posts-listing.display-posts-trending .listing-item:hover {background: none; border: none;}
		.display-posts-listing.display-posts-trending .image {flex: 0 0 80px; width: 80px; height: 80px; margin: 0 15px 0 0; overflow: hidden; border-radius: 50%; display: flex; justify-content: center; align-items: center;}
		.display-posts-listing.display-posts-trending .image img {width: 100%; height: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 50%;}
		.display-posts-listing.display-posts-trending .title {display: flex; flex-direction: column; justify-content: center; align-items: flex-start; min-height: 0; padding: 0; text-align: left; font-size: var(--er-fs-sm); margin: 0; flex: 1; overflow: visible; overflow-wrap: anywhere;}
		@media (min-width: 768px) and (max-width: 1200px) {.display-posts-listing.display-posts-trending .listing-item {flex: 1 1 calc(33.33% - 20px);}}
		@media (max-width: 768px) {.display-posts-listing.display-posts-trending .listing-item {flex: 1 1 calc(50% - 20px);}}

		/* Select Dropdown */
		.display-posts-listing[data-live-id] {flex-grow: 1; flex-basis: 0; min-width: 0;}
		.wp-block-group:has(> .display-posts-listing[data-live-id]) {gap: 0;}
		
		/* Sidebar Widgets */
		.display-posts-listing#latest > *:not(:first-child):not(:last-child) {margin: 25px 0;}
		
		/* Traits Conclusion */
		.display-posts-listing.grid.traits-conclusion {grid-gap: 0.25rem;}
		.display-posts-listing.grid.traits-conclusion .title {font-size: var(--er-fs-sm) !important;}
		
		/* DPS for Taxonomies */
		.display-taxonomies .title {display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 0.4em;}
.display-taxonomies .title a {text-decoration: none;}
.display-taxonomies .term-count {margin-left: 0; font-weight: var(--er-fw-normal); font-style: italic; color: var(--color-3);}
body.dark-mode .display-taxonomies .term-count {color: var(--color-5);}
		.display-taxonomies .listing-item a.image {display: block;}
		.display-taxonomies .listing-item a.image img {width: 100%; height: auto;}
		
		/* Dark Mode */
		body.dark-mode .display-posts-listing .listing-item {background: var(--color-10); border: 1px solid var(--color-4);}
    	body.dark-mode .display-posts-listing .listing-item:hover {background: var(--color-6);}
		body.dark-mode .display-posts-listing.grid#small-version .listing-item {background: var(--color-10); border: 1px solid var(--color-10);}
    	body.dark-mode .display-posts-listing.grid#small-version .listing-item:hover {background: var(--color-6);}
		body.dark-mode .display-posts-trending .listing-item {background: none; border: none;}
    </style>
    <?php
} );

// =====================================
// INLINE JAVASCRIPT (in Footer)
// =====================================

add_action( 'wp_footer', function () {
    ?>
    <script>
		(function() {
			'use strict';
			// Setup ARIA live Announcements for Select Navigation
			function setupSelectAnnouncements() {
				document.querySelectorAll('.display-posts-listing[data-live-id]').forEach(select => {
					const live = document.getElementById(select.dataset.liveId);
					select.addEventListener('change', function() {
						if (this.value) {
							// Announce to Screen Readers
							if (live) {
								live.textContent = `Navigating to ${this.options[this.selectedIndex].text}`;
							}
							// Navigate to the selected URL
							window.location.href = this.value;
						}
					});
				});
			}
			// Initialize on DOM ready
			function init() {
				const runInit = () => {
					setupSelectAnnouncements();
				};
				if ('requestIdleCallback' in window) {
					requestIdleCallback(runInit);
				} else {
					setTimeout(runInit, 100);
				}
			}
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
		})();
    </script>
    <?php
} );
