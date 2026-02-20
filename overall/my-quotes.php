<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* =======================================
   REGISTER TAXONOMY: QUOTE CATEGORY
   ======================================= */

function q_register_taxonomy() {

    register_taxonomy( 'quote_category', array( 'quotes' ), array(
        'labels' => array(
            'name'                       => esc_html_x( 'Quote Categories', 'taxonomy general name', 'textdomain' ),
            'singular_name'              => esc_html_x( 'Quote Category', 'taxonomy singular name', 'textdomain' ),
            'menu_name'                  => esc_html__( 'Quote Categories', 'textdomain' ),
            'all_items'                  => esc_html__( 'All Categories', 'textdomain' ),
            'parent_item'                => esc_html__( 'Parent Category', 'textdomain' ),
            'parent_item_colon'          => esc_html__( 'Parent Category:', 'textdomain' ),
            'new_item_name'              => esc_html__( 'New Category Name', 'textdomain' ),
            'add_new_item'               => esc_html__( 'Add New Category', 'textdomain' ),
            'edit_item'                  => esc_html__( 'Edit Category', 'textdomain' ),
            'update_item'                => esc_html__( 'Update Category', 'textdomain' ),
            'view_item'                  => esc_html__( 'View Category', 'textdomain' ),
            'separate_items_with_commas' => esc_html__( 'Separate Categories with Commas', 'textdomain' ),
            'add_or_remove_items'        => esc_html__( 'Add or remove Categories', 'textdomain' ),
            'choose_from_most_used'      => esc_html__( 'Choose from the most used', 'textdomain' ),
            'popular_items'              => esc_html__( 'Popular Categories', 'textdomain' ),
            'search_items'               => esc_html__( 'Search Categories', 'textdomain' ),
            'not_found'                  => esc_html__( 'No Categories found', 'textdomain' ),
            'no_terms'                   => esc_html__( 'No Categories', 'textdomain' ),
            'items_list'                 => esc_html__( 'Categories List', 'textdomain' ),
            'items_list_navigation'      => esc_html__( 'Categories List Navigation', 'textdomain' ),
        ),
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'query_var'         => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => true,
        'rewrite'           => array(
            'slug'         => 'quote-category',
            'with_front'   => true,
            'hierarchical' => true,
        ),
    ) );
}
add_action( 'init', 'q_register_taxonomy', 0 );

/* =======================================
   REGISTER CPT: QUOTES
   ======================================= */

function q_register_post_type() {

    register_post_type( 'quotes', array(
        'labels' => array(
			'name'					=> esc_html__( 'Quotes', 'textdomain' ),
            'menu_name'				=> esc_html__( 'My Quotes', 'textdomain' ),
            'singular_name'			=> esc_html__( 'Quote', 'textdomain' ),
            'add_new'				=> esc_html__( 'Add Quote', 'textdomain' ),
            'add_new_item'			=> esc_html__( 'Add New Quote', 'textdomain' ),
            'new_item'				=> esc_html__( 'New Quote', 'textdomain' ),
            'edit_item'				=> esc_html__( 'Edit Quote', 'textdomain' ),
            'view_item'				=> esc_html__( 'View Quote', 'textdomain' ),
            'update_item'			=> esc_html__( 'Update Quote', 'textdomain' ),
            'all_items'				=> esc_html__( 'All My Quotes', 'textdomain' ),
            'search_items'			=> esc_html__( 'Search Quotes', 'textdomain' ),
            'not_found'				=> esc_html__( 'No Quotes found', 'textdomain' ),
            'not_found_in_trash'	=> esc_html__( 'No Quotes found in Trash', 'textdomain' ),
        ),
        'public'				=> true,
        'show_ui'				=> true,
        'show_in_rest'			=> true,
        'query_var'				=> true,
        'publicly_queryable'	=> true,
        'exclude_from_search'	=> true,
        'has_archive'			=> true,
        'capability_type'		=> 'post',
		'hierarchical'			=> false,
		'can_export'			=> true,
        'show_in_menu'			=> true,
        'map_meta_cap'			=> true,
        'menu_icon'				=> 'dashicons-format-quote',
        'supports'	=> array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
        'taxonomies'	=> array( 'quote_category' ),
        'rewrite'	=> array(
            'slug'			=> 'my-quotes',
            'with_front'	=> true,
            'feeds'			=> false,
            'pages'			=> true,
        ),
    ) );
}
add_action( 'init', 'q_register_post_type' );

/* =======================================
   META BOX: LINKED CONTENT
   ======================================= */

function q_add_metabox() {
    add_meta_box(
        'q_related_content',
        esc_html__( 'Linked Content', 'textdomain' ),
        'q_metabox_callback',
        'quotes',
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
add_action( 'save_post_quotes', 'q_save_related_content' );

/* =======================================
   HELPERS
   ======================================= */

// Returns true only on Singular Pages / Posts that actually contain a Quotes Shortcode
// Guards both q_output_styles() and q_output_scripts() — Update here if add more Shortcodes
function q_should_load_assets() {
    $post = get_post();
    if ( ! $post ) return false;
    if ( ! is_singular() ) return false;
    return has_shortcode( $post->post_content, 'quotes_slider' )
        || has_shortcode( $post->post_content, 'quote_text' );
}

// Renders a Quote's Block Content as safe HTML. Handles Gutenberg Blocks via do_blocks()
function q_render_content( $post_obj ) {
    if ( empty( $post_obj->post_content ) ) return '';
    return wp_kses_post( do_blocks( $post_obj->post_content ) );
}

// Builds a Display Posts Card for a given post ID. Auto-detects Post Type
function q_dps_card( $post_id ) {
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
// Optionally filtered by Category Slug
function q_get_quote_for( $content_id, $category = '' ) {
    $content_id = (int) $content_id;
    if ( ! $content_id ) return null;
    if ( get_post_type( $content_id ) === 'quotes' ) {
        $post = get_post( $content_id );
        return ( $post && $post->post_status === 'publish' ) ? $post : null;
    }
    $args = array(
        'post_type'      => 'quotes',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_key'       => 'related_content',
        'meta_value'     => $content_id,
    );
    if ( $category ) {
        $args['tax_query'] = array( array(
            'taxonomy' => 'quote_category',
            'field'    => 'slug',
            'terms'    => sanitize_text_field( $category ),
        ) );
    }
    $results = get_posts( $args );
    return ! empty( $results ) ? $results[0] : null;
}

// Returns all published Quotes, newest first. Optionally filtered by Category Slug
// Used by the [quotes_slider] Shortcode. No pagination — fine unless Quote Count grows large
function q_get_all_quotes( $category = '' ) {
    $args = array(
        'post_type'      => 'quotes',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    if ( $category ) {
        $args['tax_query'] = array( array(
            'taxonomy' => 'quote_category',
            'field'    => 'slug',
            'terms'    => sanitize_text_field( $category ),
        ) );
    }
    return get_posts( $args );
}

/* =======================================
   FRONTEND STYLES
   ======================================= */

function q_output_styles() {
	if ( ! q_should_load_assets() ) return;
    ?>
    <style>
		.slideshow-quotes {visibility: hidden;}
		.slideshow-quotes.slick-initialized {visibility: visible;}
		.my-quote-slide-inner {display: flex; flex-direction: row; align-items: stretch; gap: 1.5em;}
		.my-quote-slide-dps {flex: 0 0 33.33%; max-width: 33.33%;}
		.my-quote-slide-dps .display-posts-listing {margin: 0;}
		.my-quote-slide-dps .display-posts-listing img {display: block; width: 100%; height: auto;}
		.my-quote-slide-content {flex: 0 0 calc(66.66% - 1.5em); max-width: calc(66.66% - 1.5em); padding: 1.5em; box-sizing: border-box; background: #F2F5F7; border-radius: 25px; display: flex; flex-direction: column; justify-content: center;}
		.my-quote-slide-content .wp-block-quote {margin: 0 auto !important;}
		.my-quote-slide-content .wp-block-quote p,
		.my-quote-slide-content .wp-block-quote ul,
		.my-quote-slide-content .wp-block-quote li {font-size: clamp(1rem, 1.25vw + 0.5rem, 1.25rem);}
		.my-quote-slide-content .wp-block-quote ul,
		.my-quote-slide-content .wp-block-quote li {margin-left: -15px;}
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

/* =======================================
   SHORTCODE: [quote_text]
   ======================================= */

function q_shortcode_quote_text( $atts ) {
    $atts = shortcode_atts( array(
        'id'       => 0,
        'category' => '',
    ), $atts, 'quote_text' );
    $lookup_id = (int) $atts['id'] ?: get_the_ID();
    $quote     = q_get_quote_for( $lookup_id, $atts['category'] );
    if ( ! $quote ) return '';
    $text = q_render_content( $quote );
    return $text ?: '';
}
add_shortcode( 'quote_text', 'q_shortcode_quote_text' );

/* =======================================
   SHORTCODE: [quotes_slider]
   ======================================= */

function q_shortcode_quotes_slider( $atts ) {
    $atts   = shortcode_atts( array( 'category' => '' ), $atts, 'quotes_slider' );
    $quotes = q_get_all_quotes( $atts['category'] );
    if ( empty( $quotes ) ) return '';
    ob_start(); ?>
    <div class="slideshow-quotes">
        <?php foreach ( $quotes as $quote_post ) :
            $text       = q_render_content( $quote_post );
            $related_id = (int) get_post_meta( $quote_post->ID, 'related_content', true );
            $dps_card   = $related_id ? q_dps_card( $related_id ) : '';
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

/* =======================================
   DASHBOARD ADMIN COLUMNS
   ======================================= */

function q_admin_columns( $columns ) {
    return array(
        'cb'         => $columns['cb'],
        'q_id'       => 'ID',
        'q_thumb'    => 'Image',
        'title'      => $columns['title'],
        'q_related'  => 'Linked Content',
        'q_category' => 'Category',
        'q_preview'  => 'Preview',
        'date'       => $columns['date'],
    );
}
add_filter( 'manage_quotes_posts_columns', 'q_admin_columns' );

// This Thumbnail Column is only applied on the Quotes List Screen
function q_admin_column_styles() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'quotes' ) return;
    echo '<style>
		.column-cb			{ width: 3%; }
		.column-q_id		{ width: 5%; }
		.column-q_thumb		{ width: 8%; }
		.column-q_thumb img	{ width: 65px; height: auto; border-radius: 4px; }
		.column-title		{ width: 12%; }
		.column-q_related	{ width: 10%; }
		.column-q_category	{ width: 10%; }
		.column-q_preview	{ width: 25%; }
		.column-date		{ width: 10%; }
    </style>';
}
add_action( 'admin_head', 'q_admin_column_styles' );

function q_admin_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'q_id':
            echo (int) $post_id;
            break;
        case 'q_thumb':
            $thumb_id = get_post_thumbnail_id( $post_id );
            echo $thumb_id ? wp_get_attachment_image( $thumb_id, array( 65, 65 ) ) : '&mdash;';
            break;
        case 'q_related':
            $related = get_post_meta( $post_id, 'related_content', true );
            echo $related
                ? sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $related ) ), esc_html( get_the_title( $related ) ) )
                : '&mdash;';
            break;
        case 'q_category':
            $terms = get_the_terms( $post_id, 'quote_category' );
            echo ( $terms && ! is_wp_error( $terms ) )
                ? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) )
                : '&mdash;';
            break;
        case 'q_preview':
            $content = strip_tags( get_post_field( 'post_content', $post_id ) );
            echo esc_html( mb_strlen( $content ) > 80 ? mb_substr( $content, 0, 80 ) : $content );
			if ( mb_strlen( $content ) > 80 ) echo '&hellip;';
            break;
    }
}
add_action( 'manage_quotes_posts_custom_column', 'q_admin_column_content', 10, 2 );
