<?php

/* Convert DPS output into a grouped Dropdown Menu */

function be_dps_select_open( $output, $atts ) {
    if ( ! empty( $atts['wrapper'] ) && 'select' === $atts['wrapper'] ) {
        $unique_id = uniqid( 'dps_', true );
        $desc_id   = $unique_id . '_desc';
        $live_id   = $unique_id . '_live';
        // Accessible description and live region
        $output  = '<p id="' . esc_attr( $desc_id ) . '" class="screen-reader-text">Selecting an option will take you to that post.</p>';
        $output .= '<p id="' . esc_attr( $live_id ) . '" aria-live="polite" class="screen-reader-text"></p>';
        $output .= '<select class="display-posts-listing" data-live-id="' . esc_attr( $live_id ) . '" aria-label="Select a post" aria-describedby="' . esc_attr( $desc_id ) . '" onchange="if (this.value) window.location.href=this.value">';
        $output .= '<option value="" selected disabled>Make Your Choice</option>';
    }
    return $output;
}
add_filter( 'display_posts_shortcode_wrapper_open', 'be_dps_select_open', 10, 2 );

/* Preload Taxonomy Term Caches for Select Wrapper Queries */

add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() && ! $query->is_main_query() && $query->get( 'wrapper' ) === 'select' ) {
        if ( ! empty( $query->posts ) ) {
            update_post_term_cache( $query->posts, [ 'category', 'topics' ] );
        }
    }
}, 5 );

/* Class: Collects and Groups Posts by Taxonomy or Post Type */

class DPS_Grouped_Collector {
    public static $instances = [];
    public static function add_post( $atts, $post ) {
        $instance_id = md5( serialize( $atts ) );
        if ( ! isset( self::$instances[ $instance_id ] ) ) {
            self::$instances[ $instance_id ] = [];
        }
        $post_type = get_post_type( $post );
        $term_id   = null;
        $label     = null;
        $taxonomy  = null;
        // Assign taxonomy or manual grouping labels
        if ( $post_type === 'page' ) {
            $term_id = -100;
            $label   = 'My Pages';
        } elseif ( $post_type === 'my-traits' ) {
            $term_id = -200;
            $label   = 'My Traits';
        } elseif ( $post_type === 'post' ) {
            $taxonomy = 'category';
        } elseif ( $post_type === 'my-interests' ) {
            $taxonomy = 'topics';
        }
        // Determine taxonomy-based grouping
        if ( $taxonomy ) {
            $terms = get_the_terms( $post->ID, $taxonomy );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $term    = $terms[0];
                $term_id = $term->term_id;
                $label   = $term->name;
            } else {
                $term_id = -1;
                $label   = 'Uncategorized';
            }
        }
        // Default fallback if nothing assigned
        if ( $term_id === null ) {
            $term_id = -999;
            $label   = 'Uncategorized';
        }
        // Initialize grouping bucket if not already created
        if ( ! isset( self::$instances[ $instance_id ][ $term_id ] ) ) {
            self::$instances[ $instance_id ][ $term_id ] = [
                'label' => $label,
                'posts' => [],
            ];
        }
        // Append current post data
        self::$instances[ $instance_id ][ $term_id ]['posts'][] = [
            'title' => get_the_title( $post ),
            'link'  => get_permalink( $post ),
        ];
    }
    public static function render_grouped( $atts ) {
        $instance_id = md5( serialize( $atts ) );
        $grouped     = self::$instances[ $instance_id ] ?? [];
        // Sort alphabetically by label
        uasort( $grouped, function( $a, $b ) {
            return strcasecmp( $a['label'] ?? '', $b['label'] ?? '' );
        });
        $output = '';
        foreach ( $grouped as $group ) {
            $label = $group['label'] ?? '';
            $is_grouped = is_string( $label ) && trim( $label ) !== '';
            if ( $is_grouped ) {
                $output .= '<optgroup label="' . esc_attr( $label ) . '">';
            }
            foreach ( $group['posts'] as $post ) {
                $output .= '<option value="' . esc_url( $post['link'] ) . '">' . esc_html( $post['title'] ) . '</option>';
            }
            if ( $is_grouped ) {
                $output .= '</optgroup>';
            }
        }
        // Cleanup memory after rendering
        unset( self::$instances[ $instance_id ] );
        return $output;
    }
}

/* Replace Closing Wrapper with Grouped <select> Output */

function be_dps_select_close_grouped( $output, $atts ) {
    if ( ! empty( $atts['wrapper'] ) && 'select' === $atts['wrapper'] ) {
        $output = DPS_Grouped_Collector::render_grouped( $atts ) . '</select>';
    }
    return $output;
}
add_filter( 'display_posts_shortcode_wrapper_close', 'be_dps_select_close_grouped', 10, 2 );

/* Collect Post Data for Grouped <select> Output */

function be_dps_option_output_grouped( $output, $atts ) {
    if ( empty( $atts['wrapper'] ) || 'select' !== $atts['wrapper'] ) {
        return $output;
    }
    $post = get_post();
    if ( ! $post ) {
        return '';
    }
    DPS_Grouped_Collector::add_post( $atts, $post );
    return '';
}
add_filter( 'display_posts_shortcode_output', 'be_dps_option_output_grouped', 10, 2 );

/* Add Post Counts per Category or Topic (for sidebar display) */

function posts_count_per_category( $output, $atts ) {
    if ( isset( $atts['show_category_count'] ) && $atts['show_category_count'] === 'true' ) {
        global $post;
        $taxonomy = ( isset( $atts['post_type'] ) && $atts['post_type'] === 'my-interests' ) ? 'topics' : 'category';
        $terms    = get_the_terms( $post->ID, $taxonomy );
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $term_name        = esc_html( $term->name );
                $term_post_count  = (int) $term->count;
                $term_link        = esc_url( get_term_link( $term ) );
                $pattern      = '/<a href="' . preg_quote( $term_link, '/' ) . '">' . preg_quote( $term_name, '/' ) . '<\/a>/';
                $replacement  = '<a href="' . $term_link . '">' . $term_name . '</a> (' . $term_post_count . ')';
                $output = preg_replace( $pattern, $replacement, $output );
            }
        }
    }
    return $output;
}
add_filter( 'display_posts_shortcode_output', 'posts_count_per_category', 10, 2 );

/* Inline CSS in wp_head for Early Load */

add_action( 'wp_head', function () { ?>
    <style>
    /* Accessibility Utilities */
    .screen-reader-text {position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden;}
    /* General Styling */
    .display-posts-listing {cursor: pointer;}
    .display-posts-listing .listing-item {clear: both; overflow: hidden; background: #fafbfc; border: 1px solid #e1e8ed; border-radius: 25px;}
    .display-posts-listing .listing-item:hover {background: #f2f5f7;}
    .display-posts-listing img {aspect-ratio: 16/9;}
    .display-posts-listing .title {display: block; margin-top: 16px; margin-bottom: 16px; text-align: center; font-size: 1.125rem; width: 100%;}
    .listing-item .excerpt-dash {display: none;}
    .display-posts-listing .excerpt {clear: right; display: block; text-align: center; margin: 0 16px 20px 16px;}
    /* Category Styling */
    .display-posts-listing .category-display, .display-posts-listing.grid .category-display {display: block; font-size: 0.85rem; text-align: center; margin-top: -8px; margin-bottom: 16px; opacity: 0.75;}
    /* Grid Layouts */
    .display-posts-listing.grid {display: grid; grid-template-columns: repeat(2, 1fr); grid-gap: 1.75rem 1.5rem;}
    .display-posts-listing.grid img {display: block; max-width: 100%; height: auto;}
    .display-posts-listing.grid .title {margin-top: 12px; margin-bottom: 12px; font-size: 1.125rem;}
    @media (max-width: 600px) {.display-posts-listing.grid .excerpt {padding-left: 8px; padding-right: 8px; font-size: 0.75rem;}}
    @media (min-width: 600px) {.display-posts-listing.grid .excerpt {padding-left: 16px; padding-right: 16px;}}
    /* More Columns */
    @media (min-width: 768px) {.display-posts-listing.grid#three-columns {grid-template-columns: repeat(3, 1fr);}}
    @media (min-width: 600px) and (max-width: 992px) {.display-posts-listing.grid#four-columns {grid-template-columns: repeat(2, 1fr);}}
    @media (min-width: 992px) {.display-posts-listing.grid#four-columns {grid-template-columns: repeat(4, 1fr);}}
    @media (min-width: 600px) {.display-posts-listing.grid#four-columns .title {font-size: 1.125rem;}}
    .display-posts-listing.grid#six-columns .title {margin-top: 8px; margin-bottom: 8px; font-size: 0.75rem;}
    @media (min-width: 600px) and (max-width: 992px) {.display-posts-listing.grid#six-columns {grid-template-columns: repeat(3, 1fr);}}
    @media (min-width: 992px) {.display-posts-listing.grid#six-columns {grid-template-columns: repeat(6, 1fr);}}
    /* Specific Layout Adjustments */
    .display-posts-listing.grid#small-version .listing-item {margin-bottom: 0;}
    .display-posts-listing.grid#small-version .title {margin-top: 10px; margin-bottom: 10px; font-size: 0.75rem;}
    .display-posts-listing#notorious-big .title, .display-posts-listing.grid#notorious-big .title {font-size: 1.5rem; margin-top: 7.5px; margin-bottom: 2.5px;}
    .display-posts-listing.grid#notorious-big .excerpt {font-size: 1rem;}
    @media (max-width: 768px) {.display-posts-listing.grid#notorious-big {grid-template-columns: repeat(1, 1fr);}}
    /* FAQs Layout */
    .display-posts-faqs .listing-item {clear: both; overflow: hidden; margin-bottom: 20px;}
    .display-posts-faqs .image {float: left; margin: 0 16px 0 0;}
    .display-posts-faqs .title {display: block; text-align: justify; font-size: 1rem; margin-top: -4px;}
    @media (max-width: 600px) {.display-posts-faqs .title {font-size: 1rem;}}
    .display-posts-faqs .excerpt {display: block; text-align: justify;}
    /* Trending Section */
    .display-posts-trending {display: flex; flex-wrap: wrap; gap: 20px;}
    .display-posts-trending .listing-item {display: flex; align-items: center; justify-content: space-between; flex: 1 1 calc(16.66% - 20px); box-sizing: border-box; margin-bottom: 20px; background: none; border: none;}
    .display-posts-trending .listing-item:hover {background: none; border: none;}
    .display-posts-trending .image {width: 80px; height: 80px; margin: 0 15px 0 0; overflow: hidden; border-radius: 50%; display: flex; justify-content: center; align-items: center;}
    .display-posts-trending .image img {width: 100%; height: 100%; object-fit: cover; border-radius: 50%;}
    .display-posts-trending .title {text-align: left; font-size: 1rem; margin: 0; flex: 1; overflow-wrap: anywhere;}
    @media (min-width: 768px) and (max-width: 1200px) {.display-posts-trending .listing-item {flex: 1 1 calc(33.33% - 20px);}}
    @media (max-width: 768px) {.display-posts-trending .listing-item {flex: 1 1 calc(50% - 20px);}}
    /* Widgets in Sidebar */
    .display-posts-widgets .listing-item .category-display a {font-weight: normal;}
    .ct-sidebar .display-posts-widgets {list-style-type: disc !important; padding-left: 20px;}
    /* Traits Conclusion Grid */
    .display-posts-listing.grid.traits-conclusion {display: grid; grid-gap: 0.25rem;}
    .display-posts-listing.grid.traits-conclusion .title {font-size: 0.85rem !important;}
    </style>
<?php });

/* Inline JS in Footer for Dynamic Behavior */

add_action( 'wp_footer', function () { ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Adjust font size in sidebar to prevent overflow
    function adjustFontSize() {
        const sidebar = document.querySelector('.ct-sidebar');
        if (!sidebar) return;
        sidebar.style.whiteSpace = 'nowrap';
        sidebar.style.overflow = 'hidden';
        const maxWidth = sidebar.offsetWidth;
        let currentFontSize = parseFloat(window.getComputedStyle(sidebar).fontSize);
        const minFontSize = 5;
        const step = 0.1;
        while (sidebar.scrollWidth > maxWidth && currentFontSize > minFontSize) {
            currentFontSize -= step;
            sidebar.style.fontSize = `${currentFontSize}px`;
        }
    }
    setTimeout(adjustFontSize, 100);
    window.addEventListener('resize', adjustFontSize);
});
document.addEventListener('DOMContentLoaded', function() {
    // ARIA live announcement when navigating via select menu
    document.querySelectorAll('.display-posts-listing').forEach(function(select) {
        const liveId = select.dataset.liveId;
        const live = document.getElementById(liveId);
        if (live) {
            select.addEventListener('change', function() {
                const selectedText = this.options[this.selectedIndex].text;
                live.textContent = `Navigating to ${selectedText}`;
            });
        }
    });
});
</script>
<?php });
