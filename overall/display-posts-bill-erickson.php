<?php
// =====================================
// SELECT DROPDOWN FUNCTIONALITY
// =====================================

/* Open select wrapper with accessibility features */
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

/* Preload taxonomy term caches for select wrapper queries */
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
// GROUPED COLLECTOR CLASS
// =====================================

class DPS_Grouped_Collector {
    public static $instances = [];
	
    /* Taxonomy mapping for post types */
    public static function get_group_config( $post_type ) {
        $configs = [
            'page'          => [ 'term_id' => -100, 'label' => 'My Pages' ],
            'my-traits'     => [ 'term_id' => -200, 'label' => 'My Traits' ],
            'post'          => [ 'taxonomy' => 'category' ],
            'my-interests'  => [ 'taxonomy' => 'topics' ],
			'my-quotes'  	=> [ 'taxonomy' => 'groups' ],
        ];
        return $configs[ $post_type ] ?? [];
    }
	
    /* Add post to grouped collection */
    public static function add_post( $atts, $post ) {
        $instance_id = md5( json_encode( $atts ) );
        if ( ! isset( self::$instances[ $instance_id ] ) ) {
            self::$instances[ $instance_id ] = [];
        }
        $config = self::get_group_config( $post->post_type );
        // Handle manual grouping (pages, traits)
        if ( isset( $config['term_id'] ) ) {
            $term_id = $config['term_id'];
            $label   = $config['label'];
        }
        // Handle taxonomy-based grouping
        elseif ( isset( $config['taxonomy'] ) ) {
            $terms = get_the_terms( $post->ID, $config['taxonomy'] );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $term    = $terms[0];
                $term_id = $term->term_id;
                $label   = $term->name;
            } else {
                $term_id = -1;
                $label   = 'Uncategorized';
            }
        }
        // Default fallback
        else {
            $term_id = -999;
            $label   = 'Uncategorized';
        }
        // Initialize group if needed
        if ( ! isset( self::$instances[ $instance_id ][ $term_id ] ) ) {
            self::$instances[ $instance_id ][ $term_id ] = [
                'label' => $label,
                'posts' => [],
            ];
        }
        // Add post data
        self::$instances[ $instance_id ][ $term_id ]['posts'][] = [
            'title' => get_the_title( $post ),
            'link'  => get_permalink( $post ),
        ];
    }
	
    /* Render grouped select options */
    public static function render_grouped( $atts ) {
        $instance_id = md5( json_encode( $atts ) );
        $grouped     = self::$instances[ $instance_id ] ?? [];
        // Sort alphabetically by label
        uasort( $grouped, fn( $a, $b ) => strcasecmp( $a['label'] ?? '', $b['label'] ?? '' ) );
        $output = '';
        foreach ( $grouped as $group ) {
            $label      = $group['label'] ?? '';
            $has_label  = is_string( $label ) && trim( $label ) !== '';
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
        // Cleanup memory
        unset( self::$instances[ $instance_id ] );
        return $output;
    }
}

/* Close select wrapper with grouped output */
function be_dps_select_close_grouped( $output, $atts ) {
    if ( ! empty( $atts['wrapper'] ) && 'select' === $atts['wrapper'] ) {
        return DPS_Grouped_Collector::render_grouped( $atts ) . '</select>';
    }
    return $output;
}
add_filter( 'display_posts_shortcode_wrapper_close', 'be_dps_select_close_grouped', 10, 2 );

/* Collect post data during output */
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
// TAXONOMY TERM COUNT DISPLAY
// =====================================

/* Append Term Counts to configured Taxonomy Links */
function posts_count_per_category( $output, $atts ) {
    if ( empty( $atts['show_category_count'] ) || 'true' !== $atts['show_category_count'] ) {
        return $output;
    }
    global $post;
    $post_type = isset( $atts['post_type'] ) ? $atts['post_type'] : '';
    $config   = DPS_Grouped_Collector::get_group_config( $post_type );
    $taxonomy = isset( $config['taxonomy'] ) ? $config['taxonomy'] : 'category';
    $terms    = get_the_terms( $post->ID, $taxonomy );
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return $output;
    }
    foreach ( $terms as $term ) {
        $term_name  = esc_html( $term->name );
        $term_count = (int) $term->count;
        $term_link  = esc_url( get_term_link( $term ) );
        $pattern     = '/<a href="' . preg_quote( $term_link, '/' ) . '">' . preg_quote( $term_name, '/' ) . '<\/a>/';
        $replacement = sprintf( '<a href="%s">%s</a> (%d)', $term_link, $term_name, $term_count );
        $output      = preg_replace( $pattern, $replacement, $output );
    }
    return $output;
}
add_filter( 'display_posts_shortcode_output', 'posts_count_per_category', 10, 2 );

// =====================================
// INLINE STYLES (in Head)
// =====================================

add_action( 'wp_head', function () {
    ?>
    <style>
    /* Accessibility */
    .screen-reader-text {position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden;}

    /* Base Styles */
    .display-posts-listing {cursor: pointer;}
    .display-posts-listing .listing-item {clear: both; overflow: hidden; background: #fafbfc; border: 1px solid #e1e8ed; border-radius: 25px;}
    .display-posts-listing .listing-item:hover {background: #f2f5f7;}
    .display-posts-listing img {aspect-ratio: 16/9; transition: transform 0.3s ease; will-change: transform;}
    .display-posts-listing img:hover {transform: scale(1.05);}
    .display-posts-listing .title {display: block; margin: 16px 0; text-align: center; font-size: 1.125rem; width: 100%;}
    .listing-item .excerpt-dash {display: none;}
    .display-posts-listing .excerpt {clear: right; display: block; text-align: center; margin: 0 16px 20px;}
    .display-posts-listing .category-display, .display-posts-listing.grid .category-display {display: block; font-size: 0.85rem; text-align: center; margin: -8px 0 16px; opacity: 0.75;}

    /* Grid Layout (2 columns) */
    .display-posts-listing.grid {display: grid; grid-template-columns: repeat(2, 1fr); grid-gap: 1.75rem 1.5rem;}
    .display-posts-listing.grid img {display: block; max-width: 100%; height: auto;}
    .display-posts-listing.grid .title {margin: 12px 0; font-size: 1.125rem;}
    @media (max-width: 600px) {.display-posts-listing.grid .excerpt {padding: 0 8px; font-size: 0.75rem;}}
    @media (min-width: 600px) {.display-posts-listing.grid .excerpt {padding: 0 16px;}}

    /* Grid Layout (3 columns) */
    @media (min-width: 768px) {.display-posts-listing.grid#three-columns {grid-template-columns: repeat(3, 1fr);}}
	
	/* Grid Layout (4 columns) */
    @media (min-width: 600px) and (max-width: 992px) {.display-posts-listing.grid#four-columns {grid-template-columns: repeat(2, 1fr);}}
    @media (min-width: 992px) {.display-posts-listing.grid#four-columns {grid-template-columns: repeat(4, 1fr);}}
    @media (min-width: 600px) {.display-posts-listing.grid#four-columns .title {font-size: 1.125rem;}}
	
	/* Grid Layout (6 columns) */
    .display-posts-listing.grid#six-columns .title {margin: 8px 0; font-size: 0.75rem;}
    @media (min-width: 600px) and (max-width: 992px) {.display-posts-listing.grid#six-columns {grid-template-columns: repeat(3, 1fr);}}
    @media (min-width: 992px) {.display-posts-listing.grid#six-columns {grid-template-columns: repeat(6, 1fr);}}

    /* Layout Variations */
    .display-posts-listing#small-version, .display-posts-listing#notorious-big {overflow: hidden;}
    .display-posts-listing.grid#small-version .listing-item {margin-bottom: 0;}
    .display-posts-listing.grid#small-version .title {margin: 10px 0; font-size: 0.75rem;}
    .display-posts-listing#notorious-big .title, .display-posts-listing.grid#notorious-big .title {font-size: 1.5rem; margin: 7.5px 0 2.5px;}
    .display-posts-listing.grid#notorious-big .excerpt {font-size: 1rem;}
    @media (max-width: 768px) {.display-posts-listing.grid#notorious-big {grid-template-columns: 1fr;}}

    /* FAQs Layout */
    .display-posts-faqs .listing-item {clear: both; overflow: hidden; margin-bottom: 20px;}
    .display-posts-faqs .image {float: left; margin: 0 16px 0 0;}
    .display-posts-faqs .title {display: block; text-align: justify; font-size: 1rem; margin-top: -4px;}
    .display-posts-faqs .excerpt {display: block; text-align: justify;}

    /* Trending Layout */
    .display-posts-trending {display: flex; flex-wrap: wrap; gap: 20px;}
    .display-posts-trending .listing-item {display: flex; align-items: center; justify-content: space-between; flex: 1 1 calc(16.66% - 20px); box-sizing: border-box; margin-bottom: 20px; background: none; border: none;}
    .display-posts-trending .listing-item:hover {background: none; border: none;}
    .display-posts-trending .image {width: 80px; height: 80px; margin: 0 15px 0 0; overflow: hidden; border-radius: 50%; display: flex; justify-content: center; align-items: center;}
    .display-posts-trending .image img {width: 100%; height: 100%; object-fit: cover; border-radius: 50%;}
    .display-posts-trending .title {text-align: left; font-size: 1rem; margin: 0; flex: 1; overflow-wrap: anywhere;}
    @media (min-width: 768px) and (max-width: 1200px) {.display-posts-trending .listing-item {flex: 1 1 calc(33.33% - 20px);}}
    @media (max-width: 768px) {.display-posts-trending .listing-item {flex: 1 1 calc(50% - 20px);}}

    /* Sidebar Widgets */
    .display-posts-widgets .listing-item .category-display a {font-weight: normal;}
    .ct-sidebar .display-posts-widgets {list-style-type: disc !important; padding-left: 20px;}
    .display-posts-listing#latest > *:not(:first-child):not(:last-child) {margin: 25px 0;}

    /* Traits Conclusion */
    .display-posts-listing.grid.traits-conclusion {grid-gap: 0.25rem;}
    .display-posts-listing.grid.traits-conclusion .title {font-size: 0.85rem !important;}
	
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
        // Adjust sidebar font size to prevent overflow
        function adjustFontSize() {
            const sidebar = document.querySelector('.ct-sidebar');
            if (!sidebar) return;
            sidebar.style.whiteSpace = 'nowrap';
            sidebar.style.overflow = 'hidden';
            const maxWidth = sidebar.offsetWidth;
            let fontSize = parseFloat(window.getComputedStyle(sidebar).fontSize);
            const minSize = 5;
            const step = 0.1;
            while (sidebar.scrollWidth > maxWidth && fontSize > minSize) {
                fontSize -= step;
                sidebar.style.fontSize = `${fontSize}px`;
            }
        }
		// Setup ARIA live announcements for select navigation
		function setupSelectAnnouncements() {
    		document.querySelectorAll('.display-posts-listing[data-live-id]').forEach(select => {
        		const live = document.getElementById(select.dataset.liveId);
        		select.addEventListener('change', function() {
            		if (this.value) {
                		// Announce to screen readers
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
				adjustFontSize();
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
        // Debounced resize handler
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(adjustFontSize, 150);
        });
    })();
    </script>
    <?php
} );
