<?php
defined('ABSPATH') || exit;

// =======================================
// META BOX: LINKED CONTENT
// =======================================

function q_add_metabox() {
    add_meta_box(
        'q_related_content',
        esc_html__( 'Linked Content', 'textdomain' ),
        'q_metabox_callback',
        'my-quotes',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'q_add_metabox' );

function q_metabox_callback( $post ) {
    wp_nonce_field( 'q_save_related_content', 'q_related_content_nonce' );
    $selected      = get_post_meta( $post->ID, 'related_content', true );
    $allowed_types = array( 'page', 'post', 'my-traits', 'my-interests' );
    foreach ( $allowed_types as $type ) {
        $posts = get_posts( array(
            'post_type'      => $type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        if ( empty( $posts ) ) continue;
        $pt_obj = get_post_type_object( $type );
        echo '<label style="display: block; margin-bottom: 4px; font-weight: 600;">' . esc_html( $pt_obj->labels->singular_name ) . '</label>';
        echo '<select name="q_related_content_field[]" style="width: 90%; margin-bottom: 12px;">';
        echo '<option value="">&mdash; None &mdash;</option>';
        foreach ( $posts as $p ) {
            echo '<option value="' . esc_attr( $p->ID ) . '" ' . selected( $selected, $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
        }
        echo '</select>';
    }
}

function q_save_related_content( $post_id ) {
    if ( ! isset( $_POST['q_related_content_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['q_related_content_nonce'], 'q_save_related_content' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    $value = '';
    if ( ! empty( $_POST['q_related_content_field'] ) && is_array( $_POST['q_related_content_field'] ) ) {
        foreach ( $_POST['q_related_content_field'] as $v ) {
            $v = sanitize_text_field( $v );
            if ( $v ) { $value = $v; break; }
        }
    }
    if ( $value ) {
        update_post_meta( $post_id, 'related_content', $value );
    } else {
        delete_post_meta( $post_id, 'related_content' );
    }
}
add_action( 'save_post', function( $post_id ) {
    if ( get_post_type( $post_id ) === 'my-quotes' ) q_save_related_content( $post_id );
});

// =======================================
// HELPERS
// =======================================

// Returns true only on Singular Pages / Posts that actually contain a Quotes Shortcode
// Guards both q_output_styles() and q_output_scripts() — Update here if add more Shortcodes
function q_should_load_assets() {
    $post = get_post();
    if ( ! $post ) return false;
    if ( ! is_singular() ) return false;
    // Always load on Single Quote Pages (Slider is injected by quote_text Shortcode)
    if ( is_singular( 'my-quotes' ) ) return true;
    return has_shortcode( $post->post_content, 'quotes_slider' )
        || has_shortcode( $post->post_content, 'quote_text' );
}

// Content to load on Single Quote Pages (Slider is injected by quote_text Shortcode)
function q_append_slider_on_single_quote( $content ) {
    if ( ! is_singular( 'my-quotes' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }
    // Prepend: Linked Content Title + Permalink before the Quote Text
    $related_id = (int) get_post_meta( get_the_ID(), 'related_content', true );
    $prefix     = '';
    if ( $related_id ) {
        $related_url   = get_permalink( $related_id );
        $related_title = get_the_title( $related_id );
        if ( $related_url && $related_title ) {
            $prefix = '<h3><a href="' . esc_url( $related_url ) . '">' . esc_html( $related_title ) . '</a></h3>';
        }
    }
	// Append: Load the Rest of the Content
    $personal     = do_shortcode( '[quotes_slider category="personal"]' );
    $professional = do_shortcode( '[quotes_slider category="professional"]' );
    $output = '';
    if ( $personal ) {
        $output .= '<h3><a href="' . esc_url( home_url( '/personal/' ) ) . '">Personal</a></h3>';
        $output .= $personal;
    }
    if ( $professional ) {
        $output .= '<h3><a href="' . esc_url( home_url( '/professional/' ) ) . '">Professional</a></h3>';
        $output .= $professional;
    }
    return $prefix . $content . $output;
}
add_filter( 'the_content', 'q_append_slider_on_single_quote' );

// Renders a Quote's Block Content as safe HTML. Handles Gutenberg Blocks via do_blocks()
function q_render_content( $post_obj ) {
    if ( empty( $post_obj->post_content ) ) return '';
    return wp_kses_post( do_blocks( $post_obj->post_content ) );
}

// Returns all published Quotes, newest first. Optionally filtered by Category Slug
// Used by the [quotes_slider] Shortcode. No pagination — fine unless Quote Count grows large
function q_get_quotes_for_slider( $category = '' ) {
    $args = array(
        'post_type'      => 'my-quotes',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    if ( $category ) {
        // Split comma-separated slugs into an array
        $terms = array_filter( array_map( 'sanitize_title', explode( ',', $category ) ) );
        $args['tax_query'] = array( array(
            'taxonomy' => 'groups',
            'field'    => 'slug',
            'terms'    => $terms,
            'operator' => 'IN',
        ) );
    }
    return get_posts( $args );
}

// Builds a DPS for a given post ID. Auto-detects Post Type
function q_dps_slider_card( $post_id ) {
    $post_type = get_post_type( (int) $post_id );
    if ( ! $post_type ) return '';
    return do_shortcode( sprintf(
        '[display-posts post_type="%s" id="%d" image_size="large" wrapper="div" wrapper_class="display-posts-listing"]',
        esc_attr( $post_type ),
        (int) $post_id
    ) );
}

// Finds the most recent published Quote linked to a given Content ID
// If the ID itself is a Quote, returns it directly (Edge Case: Shortcode on the Quote Post itself)
function q_get_quote_for( $content_id ) {
    $content_id = (int) $content_id;
    if ( ! $content_id ) return null;
    if ( get_post_type( $content_id ) === 'my-quotes' ) {
        $post = get_post( $content_id );
        return ( $post && $post->post_status === 'publish' ) ? $post : null;
    }
    $args = array(
        'post_type'      => 'my-quotes',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_key'       => 'related_content',
        'meta_value'     => $content_id,
    );
    $results = get_posts( $args );
    return ! empty( $results ) ? $results[0] : null;
}

// =======================================
// ADMIN COLUMN: LINKED CONTENT
// =======================================

// Column definition and rendering kept here as both are tightly coupled to the related_content meta
add_filter( 'manage_my-quotes_posts_columns', function( $columns ) {
    $new = array();
    foreach ( $columns as $key => $value ) {
        $new[ $key ] = $value;
        if ( $key === 'title' ) {
            $new['q_related'] = __( 'Linked Content' );
        }
    }
    return $new;
}, 20 );

function q_admin_column_linked_content( $column, $post_id ) {
    if ( $column !== 'q_related' ) return;
    $related = get_post_meta( $post_id, 'related_content', true );
    echo $related
        ? sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $related ) ), esc_html( get_the_title( $related ) ) )
        : '&mdash;';
}
add_action( 'manage_my-quotes_posts_custom_column', 'q_admin_column_linked_content', 10, 2 );

// =======================================
// FRONTEND STYLES
// =======================================

function q_output_styles() {
	if ( ! q_should_load_assets() ) return;
    ?>
	<style>
		/* Slick slider: Hidden until initialized to prevent Flash */
		.slideshow-quotes {visibility: hidden;}
		.slideshow-quotes.slick-initialized {visibility: visible;}
		/* Slide Layout: Image left (33%), Content right (66%) */
		.my-quote-slide-inner {display: flex; flex-direction: row; align-items: stretch; gap: 1.5em;}
		.my-quote-slide-dps {flex: 0 0 33.33%; max-width: 33.33%;}
		.my-quote-slide-dps .display-posts-listing {margin: 0;}
		.my-quote-slide-dps .display-posts-listing img {display: block; width: 100%; height: auto;}
		/* Content Card Styling */
		.my-quote-slide-content {flex: 0 0 calc(66.66% - 1.5em); max-width: calc(66.66% - 1.5em); padding: 1.5em; box-sizing: border-box; background: #F2F5F7; border-radius: 25px; display: flex; flex-direction: column; justify-content: center;}
		/* [quotes_slider]: Typography and List Offset inside Card */
		.my-quote-slide-content .wp-block-quote {margin: 0 auto !important;}
		.my-quote-slide-content .wp-block-quote p,
		.my-quote-slide-content .wp-block-quote ul,
		.my-quote-slide-content .wp-block-quote li {font-size: clamp(1rem, 1.25vw + 0.5rem, 1.25rem);}
		.my-quote-slide-content .wp-block-quote ul,
		.my-quote-slide-content .wp-block-quote li {margin-left: -20px;}
		/* [quote_text]: List Offset to match that of [quotes_slider] */
		.my-quote-text-content .wp-block-quote p,
		.my-quote-text-content .wp-block-quote ul,
		.my-quote-text-content .wp-block-quote li {font-size: 1rem;}
		.my-quote-text-content .wp-block-quote ul,
		.my-quote-text-content .wp-block-quote li {margin-left: -20px;}
		/* Mobile: Stack Image above Content */
		@media (max-width: 768px) {
			.my-quote-slide-inner {flex-direction: column; gap: 1.5em;}
			.my-quote-slide-dps,
			.my-quote-slide-content {flex: 0 0 100%; max-width: 100%;}
			.my-quote-slide-content {padding: 1.5em; border-radius: 25px;}
		}
	</style>
    <?php
}
add_action( 'wp_head', 'q_output_styles' );

function q_output_scripts() {
	if ( ! q_should_load_assets() ) return;
    ?>
    <script>
    (function() {
        function initQuoteSlick() {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.slick === 'undefined') return;
            jQuery('.slideshow-quotes:not(.slick-initialized)').slick({
                lazyLoad: 'ondemand',
                autoplay: true,
                autoplaySpeed: 2000,
                infinite: true,
                swipeToSlide: true,
                accessibility: true,
                focusOnSelect: false,
				pauseOnHover: true,
				pauseOnFocus: true,
                arrows: false,
                dots: true,
                fade: true,
                adaptiveHeight: true,
                slidesToShow: 1,
                slidesToScroll: 1
            });
        }
        if ('requestIdleCallback' in window) {
            requestIdleCallback(initQuoteSlick, { timeout: 500 });
        } else {
            setTimeout(initQuoteSlick, 200);
        }
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'q_output_scripts', 100 );

// =======================================
// SHORTCODE: [quote_text]
// =======================================

function q_shortcode_quote_text( $atts ) {
    $atts = shortcode_atts( array(
        'id' => 0,
    ), $atts, 'quote_text' );
    $raw_ids = array_filter( array_map( 'intval', explode( ',', $atts['id'] ) ) );
    if ( empty( $raw_ids ) ) {
        $raw_ids = array( get_the_ID() );
    }
    $quote = null;
    foreach ( $raw_ids as $lookup_id ) {
        $quote = q_get_quote_for( $lookup_id );
        if ( $quote ) break;
    }
    if ( ! $quote ) return '';
    $text = q_render_content( $quote );
    return '<div class="my-quote-text-content">' . $text . '</div>';
}
add_shortcode( 'quote_text', 'q_shortcode_quote_text' );

// =======================================
// SHORTCODE: [quotes_slider]
// =======================================

function q_shortcode_quotes_slider( $atts ) {
    $atts   = shortcode_atts( array( 'category' => '' ), $atts, 'quotes_slider' );
    $quotes = q_get_quotes_for_slider( $atts['category'] );
    if ( empty( $quotes ) ) return '';
    ob_start(); ?>
    <div class="slideshow-quotes">
        <?php foreach ( $quotes as $quote_post ) :
            $text       = q_render_content( $quote_post );
            $related_id = (int) get_post_meta( $quote_post->ID, 'related_content', true );
            $dps_card   = $related_id ? q_dps_slider_card( $related_id ) : '';
            if ( ! $text && ! $dps_card ) continue;
        ?>
            <div class="my-quote-slide">
                <div class="my-quote-slide-inner">
                    <?php if ( $dps_card ) : ?>
                        <div class="my-quote-slide-dps"><?php echo $dps_card; ?></div>
                    <?php endif; ?>
                    <?php if ( $text ) : ?>
                        <div class="my-quote-slide-content"><?php echo $text; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}
add_shortcode( 'quotes_slider', 'q_shortcode_quotes_slider' );
