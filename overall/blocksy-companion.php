<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ======================================
// PERFORMANCE & ASSET LOADING
// ======================================

// Enable Blocksy Flexy Animation Styles
add_action('wp_enqueue_scripts', fn() => wp_enqueue_style('ct-flexy-styles'));

// Switch from "lazy" to "eager" for featured Images (ct-media-container)
add_filter('blocksy:frontend:dynamic-data:post-featured-image:html', function($html) {
	if (!is_singular()) return $html;
	// Remove loading "lazy"
	$html = preg_replace('/\s*loading=["\']lazy["\']/', '', $html);
	// Add fetchpriority "high" if not present
	if (strpos($html, 'fetchpriority') === false) {
		$html = str_replace('<img', '<img fetchpriority="high"', $html);
	}
	// Add loading "eager"
	if (strpos($html, 'loading=') === false) {
		$html = str_replace('<img', '<img loading="eager"', $html);
	}
	return $html;
}, 10);

// ======================================
// SHORTCODES
// ======================================

// Alias for [reusable] → get_reusable_block() defined in Site Essentials & Cookies PHP
if ( function_exists( 'get_reusable_block' ) ) {
	add_shortcode( 'blocksy_content_block', 'get_reusable_block' );
}

// ======================================
// HELPERS
// ======================================

// Taxonomy Map - Single Source of Truth for post_type → taxonomy Relationships
function er_taxonomy_map() {
	return array(
		'my-interests' => 'topics',
		'my-quotes'    => 'groups',
		'my-traits'    => 'types',
	);
}

// ======================================
// CRITICAL CSS + HEAD SCRIPTS
// ======================================

add_action('wp_head', function() {
	?>
	<style>

		/* Header Container: Image */
		:root {--hero-curve: 15vw;}
		.hero-section .ct-media-container {border-bottom-right-radius: var(--hero-curve);}

		/* Header Container: Layer */
		.page-title,
		.page-description,
		.ct-breadcrumbs {background: rgba(0, 0, 0, 0.65);}
		.entry-header .page-title {border-radius: 10px 10px 0 0;}
		.entry-header .ct-breadcrumbs {border-radius: 0 0 10px 10px;}

		/* Header Container: Content */
		.page-title {padding: 10px 20px; color: var(--color-8);}
		.page-description {padding: 0 20px 20px; color: var(--color-5);}
		.ct-breadcrumbs {padding: 0 20px 20px; color: var(--color-5);}

		/* Header - Submenu Offset (CSS Variable Overrides) */
		[class*=animated-submenu] > .sub-menu {
			--dropdown-top-offset: 0px;
			--sticky-state-dropdown-top-offset: 0px;
		}

		/* Header - Fixed Bar (Layout Overrides) */
		#header [data-device="desktop"] [data-row="middle"],
		#header [data-device="mobile"]  [data-row="middle"] {
			position: fixed !important;
			top: 0 !important; left: 0 !important; right: 0 !important;
			width: 100% !important;
			z-index: 999 !important;
			display: flex !important;
			align-items: center !important;
			background: rgba(0, 0, 0, 0.65) !important;
			backdrop-filter: blur(10px) !important;
			-webkit-backdrop-filter: blur(10px) !important;
			transition: height 0.3s ease !important;
		}
		#header [data-device="desktop"] [data-row="middle"] {height: 120px !important;}
		#header [data-device="mobile"]  [data-row="middle"] {height: 90px !important;}
		#header.er-scrolled [data-device="desktop"] [data-row="middle"],
		#header.er-scrolled [data-device="mobile"]  [data-row="middle"] {height: 90px !important;}
		.hero-section {padding-top: 120px !important;}
		@media (max-width: 767px) {.hero-section {padding-top: 90px !important;}}

	</style>

	<?php
	// Footer hide Rule - Only needed on non-frontpage
	if (!is_front_page()) :
	?>
	<style>.er-custom-footer ~ #footer.ct-footer { display: none !important; }</style>
	<?php endif; ?>

	<?php
	// Term Image Meta - Inject paired with registered er_term_image_id
	// NOTE: This intentionally uses two Strategies and reflects two genuinely different Page Contexts with different DOM States
	$er_term_image_map = [
		'category' => 'category',
		'topics'   => 'topics',
		'groups'   => 'groups',
		'types'    => 'types',
	];

	$er_term = null;

	// Strategy 1: GET param present (filter-tab URL on a static page)
	foreach ($er_term_image_map as $er_param => $er_taxonomy) {
		if (!empty($_GET[$er_param])) {
			$er_t = get_term_by('slug', sanitize_key($_GET[$er_param]), $er_taxonomy);
			if ($er_t instanceof WP_Term) { $er_term = $er_t; break; }
		}
	}

	// Strategy 2: Native Taxonomy Archive (no GET param, Blocksy renders no <figure>)
	if (!$er_term && (is_tax() || is_category() || is_tag())) {
		$er_queried = get_queried_object();
		if ($er_queried instanceof WP_Term) {
			$er_term = get_term_by('slug', $er_queried->slug, $er_queried->taxonomy);
		}
	}

	if ($er_term instanceof WP_Term) {
		$er_img_id  = (int) get_term_meta($er_term->term_id, 'er_term_image_id', true);
		$er_img_url = $er_img_id ? wp_get_attachment_image_url($er_img_id, 'full') : '';
		if ($er_img_url) :
			if (is_tax() || is_category() || is_tag()) :
			// Defer term image injection script to footer to avoid WAF/REST API 405 issues
			add_action( 'wp_footer', function() use ( $er_img_url ) {
				?>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var hero = document.querySelector('.hero-section');
			if (!hero || hero.querySelector('figure')) return;
			var img = document.createElement('img');
			img.src           = '<?php echo esc_url($er_img_url); ?>';
			img.alt           = '';
			img.loading       = 'eager';
			img.fetchPriority = 'high';
			img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';

			var container = document.createElement('div');
			container.className = 'ct-media-container';
			container.appendChild(img);

			var figure = document.createElement('figure');
			figure.setAttribute('aria-hidden', 'true');
			figure.appendChild(container);

			hero.insertBefore(figure, hero.firstChild);
		});
	</script>
			<?php
			}, 5 );
			?>
			<?php else : ?>
	<style>
		/* Term Image Meta — Hide baked-in IMG, show Term IMG as Header Container IMG */
		.hero-section figure .ct-media-container img {opacity: 0 !important;}
		.hero-section figure {background-image: url('<?php echo esc_url($er_img_url); ?>') !important; background-size: cover !important; background-position: center !important; border-bottom-right-radius: var(--hero-curve); overflow: hidden;}
	</style>
			<?php endif;
		endif;
	}
	?>

<?php

}, 5); // Load critical Styles in Head early

// ======================================
// FOOTER PATTERN & READ TIME
// ======================================

// Footer - Matching Blocksy's native DOM Layout
add_action('blocksy:footer:before', function() {
	if (is_front_page()) {
		return;
	}
	echo '<footer id="footer" class="ct-footer er-custom-footer" data-id="type-1">';
	echo '<div data-row="middle">';
	echo '<div class="ct-container">';
	echo '<div data-column="text">';
	echo do_shortcode('[reusable id="125664"]'); // Synced Pattern: Footer
	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</footer>';
});

// Read Time - Read Time Feature and Output
function er_reading_time( $post_id = null, $echo = false ) {
	$post_id    = $post_id ?: get_the_ID();
	$content    = get_post_field( 'post_content', $post_id );
	$word_count = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) );
	$wpm        = defined( 'READING_SPEED_WPM' ) ? READING_SPEED_WPM : 200; // As defined in Site Essentials & Cookies PHP
	$minutes    = max( 1, (int) ceil( $word_count / $wpm ) );
	$output = '<li class="er-reading-time">'
			.   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" '
			.       'width="1em" height="1em" '
			.       'fill="none" stroke="currentColor" '
			.       'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
			.       'aria-hidden="true">'
			.       '<circle cx="12" cy="12" r="10" fill="none"/>'
			.       '<polyline points="12 6 12 12 16 14" fill="none"/>'
			.   '</svg>'
			// NOTE: ' min read' may stay hardcoded as there is no Translation needed
			.   ' ' . $minutes . ' min read'
			. '</li>';
	if ( $echo ) {
		echo $output;
	} else {
		return $output;
	}
}
if ( apply_filters( 'er_reading_time_on_cards', true ) ) {
	add_action( 'blocksy:post-meta:render-meta', function( $single_meta_id ) {
		if ( 'categories' !== $single_meta_id ) {
			return;
		}
		er_reading_time( null, true );
	} );
}

// ======================================
// ARCHIVE FILTER
// ======================================

// NOTE: Filter Tab HTML is intentionally duplicated here and in er_cpt_archive. Both render identical Output but have different Control Flow and Context - Combining them adds Complexity without Benefit

// PART 1 — Render Filter Tabs above the Post Loop

add_action( 'blocksy:loop:before', function() {
	$page_map = array(
		13405  => array( 'post_type' => 'post',         'taxonomy' => 'category' ),
		13749  => array( 'post_type' => 'my-interests', 'taxonomy' => 'topics'   ),
		103040 => array( 'post_type' => 'my-quotes',    'taxonomy' => 'groups'   ),
		83147  => array( 'post_type' => 'my-traits',    'taxonomy' => 'types'    ),
	);
	$post_type = null;
	$taxonomy  = null;
	if ( isset( $page_map[ get_the_ID() ] ) ) {
		$post_type = $page_map[ get_the_ID() ]['post_type'];
		$taxonomy  = $page_map[ get_the_ID() ]['taxonomy'];
	} elseif ( is_home() || is_front_page() || is_category() ) {
		$post_type = 'post';
		$taxonomy  = 'category';
	} elseif ( is_tax( 'topics' ) || is_post_type_archive( 'my-interests' ) ) {
		$post_type = 'my-interests';
		$taxonomy  = 'topics';
	} elseif ( is_tax( 'groups' ) || is_post_type_archive( 'my-quotes' ) ) {
		$post_type = 'my-quotes';
		$taxonomy  = 'groups';
	} elseif ( is_tax( 'types' ) || is_post_type_archive( 'my-traits' ) ) {
		$post_type = 'my-traits';
		$taxonomy  = 'types';
	} else {
		return;
	}
	$active_slug = isset( $_GET[ $taxonomy ] ) ? sanitize_key( $_GET[ $taxonomy ] ) : '';
	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => true,
	) );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return;
	}
	$base_url = esc_url_raw( strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) );
	$total    = wp_count_posts( $post_type )->publish;
	echo '<div class="er-archive-filter">';
	$all_active = $active_slug === '' ? ' er-active' : '';
	printf(
		'<a href="%s" class="er-filter-tab%s">ALL <span>(%d)</span></a>',
		esc_url( $base_url ),
		esc_attr( $all_active ),
		intval( $total )
	);
	foreach ( $terms as $term ) {
		$active = ( $active_slug === $term->slug ) ? ' er-active' : '';
		$url    = add_query_arg( $taxonomy, $term->slug, $base_url );
		printf(
			'<a href="%s" class="er-filter-tab%s">%s <span>(%d)</span></a>',
			esc_url( $url ),
			esc_attr( $active ),
			esc_html( strtoupper( $term->name ) ),
			intval( $term->count )
		);
	}
	echo '</div>';
} );

// PART 2 — Filter Queries (hooks into WP and Blocksy Query Args)

// Native WP Posts Page (My Blog)
add_action( 'pre_get_posts', function( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_home() ) {
		return;
	}
	if ( empty( $_GET['category'] ) ) {
		return;
	}
	$query->set( 'category_name', sanitize_key( $_GET['category'] ) );
} );

// CPT Pages via er_cpt_archive Shortcode
add_filter( 'blocksy:general:shortcodes:blocksy-posts:args', function( $query_args, $shortcode_args ) {
	$taxonomy_map = er_taxonomy_map();
	$post_type    = isset( $shortcode_args['post_type'] ) ? $shortcode_args['post_type'] : '';

	if ( ! isset( $taxonomy_map[ $post_type ] ) ) {
		return $query_args;
	}
	$taxonomy = $taxonomy_map[ $post_type ];
	if ( empty( $_GET[ $taxonomy ] ) ) {
		return $query_args;
	}
	$slug = sanitize_key( $_GET[ $taxonomy ] );
	$term = get_term_by( 'slug', $slug, $taxonomy );

	if ( ! $term || is_wp_error( $term ) ) {
		return $query_args;
	}
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => $taxonomy,
			'field'    => 'term_id',
			'terms'    => $term->term_id,
		),
	);
	return $query_args;
}, 10, 2 );

// ======================================
// OWN CPT ARCHIVE (with Filter)
// ======================================

// Shared Card Renderer - Used by er_cpt_archive Shortcode and er_load_more_handler
function er_render_card( $post_id, $taxonomy ) {
	$url        = get_permalink( $post_id );
	$title      = get_the_title( $post_id );
	$thumb      = get_the_post_thumbnail( $post_id, 'medium_large', array(
		'loading'  => 'lazy',
		'decoding' => 'async',
		'style'    => 'aspect-ratio: 5/3;',
	) );
	$post_terms = get_the_terms( $post_id, $taxonomy );
	$term_obj   = ( $post_terms && ! is_wp_error( $post_terms ) ) ? $post_terms[0] : null;

	echo '<article class="' . esc_attr( implode( ' ', get_post_class( 'entry-card card-content', $post_id ) ) ) . '">';
	echo '<h2 class="entry-title"><a href="' . esc_url( $url ) . '" rel="bookmark">' . esc_html( $title ) . '</a></h2>';

	if ( $thumb ) {
		echo '<a class="ct-media-container" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $title ) . '">'
		   . $thumb
		   . '</a>';
	}

	echo '<ul class="entry-meta" data-type="icons:slash" data-id="eQBHSW">';
	er_reading_time( $post_id, true );

	if ( $term_obj ) {
		echo '<li class="meta-categories" data-type="simple">'
		   . '<svg width="13" height="13" viewBox="0 0 15 15"><path d="M14.4,1.2H0.6C0.3,1.2,0,1.5,0,1.9V5c0,0.3,0.3,0.6,0.6,0.6h0.6v7.5c0,0.3,0.3,0.6,0.6,0.6h11.2c0.3,0,0.6-0.3,0.6-0.6V5.6h0.6C14.7,5.6,15,5.3,15,5V1.9C15,1.5,14.7,1.2,14.4,1.2z M12.5,12.5h-10V5.6h10V12.5z M13.8,4.4H1.2V2.5h12.5V4.4z M5.6,7.5c0-0.3,0.3-0.6,0.6-0.6h2.5c0.3,0,0.6,0.3,0.6,0.6S9.1,8.1,8.8,8.1H6.2C5.9,8.1,5.6,7.8,5.6,7.5z"></path></svg>'
		   . '<a href="' . esc_url( get_term_link( $term_obj ) ) . '" rel="tag" class="ct-term-' . esc_attr( $term_obj->term_id ) . '">'
		   . esc_html( $term_obj->name )
		   . '</a>'
		   . '</li>';
	}

	echo '</ul>';
	echo '</article>';
}

// CPT Archive Shortcode - Renders filtered Grid with infinite Scroll
add_shortcode( 'er_cpt_archive', function( $atts ) {

	// Default Values as Fallback if no Attributes defined in Shortcode
	$atts = shortcode_atts( array(
		'post_type' => 'my-interests',
		'limit'     => -1,
		'orderby'   => 'rand',
		'order'     => 'DESC',
		'filtering' => 'yes',
	), $atts, 'er_cpt_archive' );

	$taxonomy_map = er_taxonomy_map();
	$post_type    = sanitize_key( $atts['post_type'] );

	if ( ! isset( $taxonomy_map[ $post_type ] ) ) {
		return '<p>Invalid post_type.</p>';
	}

	$taxonomy    = $taxonomy_map[ $post_type ];
	$active_slug = isset( $_GET[ $taxonomy ] ) ? sanitize_key( $_GET[ $taxonomy ] ) : '';
	$base_url    = esc_url_raw( strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) );

	// Filter Tabs
	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => true,
	) );

	ob_start();

	if ( ! empty( $terms ) && ! is_wp_error( $terms ) && $atts['filtering'] !== 'no' ) {
		$total      = wp_count_posts( $post_type )->publish;
		$all_active = $active_slug === '' ? ' er-active' : '';
		echo '<div class="er-archive-filter">';
		printf(
			'<a href="%s" class="er-filter-tab%s">ALL <span>(%d)</span></a>',
			esc_url( $base_url ),
			esc_attr( $all_active ),
			intval( $total )
		);
		foreach ( $terms as $term ) {
			$active = ( $active_slug === $term->slug ) ? ' er-active' : '';
			$url    = add_query_arg( $taxonomy, $term->slug, $base_url );
			printf(
				'<a href="%s" class="er-filter-tab%s">%s <span>(%d)</span></a>',
				esc_url( $url ),
				esc_attr( $active ),
				esc_html( strtoupper( $term->name ) ),
				intval( $term->count )
			);
		}
		echo '</div>';
	}

	// Query Args
	$query_args = array(
		'post_type'      => $post_type,
		'posts_per_page' => intval( $atts['limit'] ),
		'post_status'    => 'publish',
		'orderby'        => in_array( sanitize_key( $atts['orderby'] ), [ 'date', 'title', 'rand', 'menu_order' ], true )
						   ? sanitize_key( $atts['orderby'] )
						   : 'rand',
		'order'          => in_array( strtoupper( $atts['order'] ), [ 'ASC', 'DESC' ] ) ? strtoupper( $atts['order'] ) : 'DESC',
	);

	if ( $active_slug !== '' ) {
		$term = get_term_by( 'slug', $active_slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			);
		}
	}

	// Post Grid
	$loop = new WP_Query( $query_args );

	if ( ! $loop->have_posts() ) {
		echo '<p>No entries found.</p>';
		return ob_get_clean();
	}

	$initial_count = $loop->post_count;

	echo '<div class="er-cpt-grid" data-prefix="blog" data-archive="default"'
		. ' data-post-type="' . esc_attr( $post_type ) . '"'
		. ' data-taxonomy="' . esc_attr( $taxonomy ) . '"'
		. ' data-limit="' . esc_attr( $atts['limit'] ) . '"'
		. ' data-orderby="' . esc_attr( $atts['orderby'] ) . '"'
		. ' data-order="' . esc_attr( $atts['order'] ) . '"'
		. ' data-offset="' . esc_attr( $initial_count ) . '"'
		. '>';

	while ( $loop->have_posts() ) {
		$loop->the_post();
		er_render_card( get_the_ID(), $taxonomy );
	}

	echo '</div>';

	// Only render Sentinel when there are more Posts to fetch
	if ( intval( $atts['limit'] ) !== -1 && $loop->found_posts > $initial_count ) {
		echo '<div class="er-load-more-sentinel"></div>';
	}

	wp_reset_postdata();

	return ob_get_clean();
} );

// Infinite Scroll AJAX Handler - Handles load-more Requests from er_cpt_archive
add_action( 'wp_ajax_er_load_more', 'er_load_more_handler' );
add_action( 'wp_ajax_nopriv_er_load_more', 'er_load_more_handler' );

function er_load_more_handler() {
	check_ajax_referer( 'er_load_more_nonce', 'nonce' );

	$post_type       = sanitize_key( $_POST['post_type'] );
	$limit           = intval( $_POST['limit'] );
	$allowed_orderby = [ 'date', 'title', 'rand', 'menu_order' ];
	$orderby         = in_array( sanitize_key( $_POST['orderby'] ), $allowed_orderby, true )
					   ? sanitize_key( $_POST['orderby'] )
					   : 'date';
	$order           = in_array( strtoupper( $_POST['order'] ), [ 'ASC', 'DESC' ] ) ? strtoupper( $_POST['order'] ) : 'DESC';
	$offset          = intval( $_POST['offset'] );
	$active_slug     = sanitize_key( $_POST['active_slug'] );

	$taxonomy_map = er_taxonomy_map();

	if ( ! isset( $taxonomy_map[ $post_type ] ) ) {
		wp_send_json_error( 'Invalid post_type.' );
		wp_die();
	}

	$taxonomy = $taxonomy_map[ $post_type ]; // derived from validated post_type — $_POST['taxonomy'] ignored

	$query_args = array(
		'post_type'      => $post_type,
		'posts_per_page' => $limit,
		'post_status'    => 'publish',
		'orderby'        => $orderby,
		'order'          => $order,
		'offset'         => $offset,
	);

	if ( $active_slug !== '' ) {
		$term = get_term_by( 'slug', $active_slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			);
		}
	}

	$loop = new WP_Query( $query_args );

	if ( ! $loop->have_posts() ) {
		wp_send_json_success( [ 'html' => '', 'has_more' => false ] );
		wp_die();
	}

	ob_start();

	while ( $loop->have_posts() ) {
		$loop->the_post();
		er_render_card( get_the_ID(), $taxonomy );
	}

	wp_reset_postdata();

	$html     = ob_get_clean();
	$has_more = $loop->found_posts > ( $offset + $limit );

	wp_send_json_success( [ 'html' => $html, 'has_more' => $has_more ] );
}

// ======================================
// RELATED POSTS QUERY
// ======================================

// Fix related posts to show from same category or taxonomy as current post
add_filter( 'blocksy:related-posts:query', function( $query ) {
	global $post;

	$post_type    = get_post_type( $post->ID );
	$taxonomy_map = er_taxonomy_map();

	if ( isset( $taxonomy_map[ $post_type ] ) ) {
		// CPT post — match by custom taxonomy
		$taxonomy = $taxonomy_map[ $post_type ];
		$terms    = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			return new WP_Query( array(
				'post_type'      => $post_type,
				'posts_per_page' => 12,
				'post_status'    => 'publish',
				'orderby'        => 'rand',
				'post__not_in'   => array( $post->ID ),
				'tax_query'      => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				),
			) );
		}
	} else {
		// Standard post — match by category
		$categories = wp_get_post_categories( $post->ID );
		if ( ! empty( $categories ) ) {
			return new WP_Query( array(
				'post_type'      => 'post',
				'posts_per_page' => 12,
				'post_status'    => 'publish',
				'orderby'        => 'rand',
				'post__not_in'   => array( $post->ID ),
				'category__in'   => $categories,
			) );
		}
	}

	return $query;
} );

// ======================================
// RELATED POSTS SLIDER
// ======================================

// Buffers Blocksy's related-posts HTML and rewrites it for Flexy
// NOTE: Relies on Blocksy outputting the Items Container + plain <article> Tags. Any Markup Change breaks it. Only Query Filters exist, so Buffering stays. If it breaks, check the Container + Article Markup. Eric Slider as Replacement was considered but Blocksy has no Render Hook (only Query Filters)

add_action( 'blocksy:single:related_posts:before', function() {
	ob_start();
} );

add_action( 'blocksy:single:related_posts:after', function() {
	$html   = ob_get_clean();
	$arrows = '<span class="flexy-arrow-prev">'
		. '<svg width="16" height="10" fill="currentColor" viewBox="0 0 16 10"><path d="M15.3 4.3h-13l2.8-3c.3-.3.3-.7 0-1-.3-.3-.6-.3-.9 0l-4 4.2-.2.2v.6c0 .1.1.2.2.2l4 4.2c.3.4.6.4.9 0 .3-.3.3-.7 0-1l-2.8-3h13c.2 0 .4-.1.5-.2s.2-.3.2-.5-.1-.4-.2-.5c-.1-.1-.3-.2-.5-.2z"></path></svg>'
		. '</span>'
		. '<span class="flexy-arrow-next">'
		. '<svg width="16" height="10" fill="currentColor" viewBox="0 0 16 10"><path d="M.2 4.5c-.1.1-.2.3-.2.5s.1.4.2.5c.1.1.3.2.5.2h13l-2.8 3c-.3.3-.3.7 0 1 .3.3.6.3.9 0l4-4.2.2-.2V5v-.3c0-.1-.1-.2-.2-.2l-4-4.2c-.3-.4-.6-.4-.9 0-.3.3-.3.7 0 1l2.8 3H.7c-.2 0-.4.1-.5.2z"></path></svg>'
		. '</span>';

	$html = str_replace(
		'<div class="ct-related-posts-items" data-layout="grid">',
		'<div class="flexy-container" data-flexy="no" data-autoplay="2">'
		// NOTE: data-flexy="no" is correct - Blocksy's Scanner State for "pending init". Flexy clears it to "" on Mount. Confirmed via flexy.scss Source
		. '<div class="flexy">'
		. '<div class="flexy-view" data-flexy-view="boxed">'
		. '<div class="ct-related-posts-items flexy-items">',
		$html
	);

	$html = str_replace( '<article>', '<article class="flexy-item">', $html );

	$last_article_end = strrpos( $html, '</article>' );
	if ( $last_article_end !== false ) {
		$after_articles = substr( $html, $last_article_end + strlen( '</article>' ) );
		$after_articles = preg_replace( '/<\/div>/', '', $after_articles, 1 );
		$closing = '</div>'   // close flexy-items
				 . '</div>'   // close flexy-view
				 . $arrows    // arrows inside .flexy, after .flexy-view
				 . '</div>'   // close flexy
				 . '</div>';  // close flexy-container
		$html = substr( $html, 0, $last_article_end + strlen( '</article>' ) )
			  . $closing
			  . $after_articles;
	}

	echo $html;
} );

// ======================================
// NON-CRITICAL CSS + FOOTER SCRIPTS
// ======================================

// NOTE: Styles are intentionally output via wp_footer rather than wp_add_inline_style() - ct-flexy-styles is not enqueued on all Pages, making wp_add_inline_style() unreliable as an Attachment Handle

add_action('wp_footer', function() {
	?>

	<style>

		/* Header */
		body.dark-mode[data-header*="type-1"] .ct-header [data-row*="middle"] {background: rgba(0, 0, 0, 0.65) !important;}
		body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-title {--linkInitialColor: var(--color-8); --linkHoverColor: var(--color-5);}
		body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-description {color: var(--color-5);}
		body.dark-mode[data-header*="type-1"] .ct-header [data-id="menu"] > ul > li > a {--linkInitialColor: var(--color-8); --linkHoverColor: var(--color-5);}
		body.dark-mode[data-header*="type-1"] [data-id="trigger"] {--icon-color: var(--color-8); --icon-hover-color: var(--color-5);}

		/* Sidebar */
		body.dark-mode aside[data-type='type-4']:after {background: var(--color-6);}
		body.dark-mode .ct-sidebar .ct-widget {color: var(--color-5);}
		@media (max-width: 1400px) {
			[data-sidebar] {display: block !important;}
			[data-sidebar] > aside {display: none !important;}
			[data-sidebar] > * {width: 100% !important;}
		}

		/* Footer */
		.ct-breadcrumbs-shortcode {background: none; margin-left: -20px; color: var(--color-5);}
		.ct-breadcrumbs-shortcode a {color: var(--color-1);}
		@media (max-width: 992px) {.ct-breadcrumbs-shortcode {margin-bottom: 15px;}}

		/* Query Templates */
		.single-query .ct-query-template-grid,
		.single-query .ct-query-template.is-layout-slider {
			border: 1px solid var(--color-5);
			border-radius: 25px;
		}
		.single-query .ct-query-template-grid:hover,
		.single-query .ct-query-template.is-layout-slider:hover {background: var(--color-7);}
		.single-query .ct-dynamic-data {padding-bottom: 16px;}
		body.dark-mode .single-query .ct-query-template-grid,
		body.dark-mode .single-query .ct-query-template.is-layout-slider {background: var(--color-10); border: 1px solid var(--color-10);}
		body.dark-mode .single-query .ct-query-template-grid:hover,
		body.dark-mode .single-query .ct-query-template.is-layout-slider:hover {background: var(--color-6);}
		@media (min-width: 1024px) {.grid-four-columns .ct-query-template-grid {grid-template-columns: repeat(4, 1fr);}}

		/* Archive Filter */
		.er-archive-filter {display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-bottom: 4rem;}
		.er-filter-tab {text-decoration: none; font-size: 0.85em; padding: 3px 10px; color: var(--theme-palette-color-1);}
		.er-filter-tab.er-active, .er-filter-tab:hover {color: var(--theme-palette-color-2);}

		/* Taxonomy Blocks */
		.wp-block-term.is-layout-flow .ct-dynamic-data {padding-bottom: 16px;}
		.wp-block-term.is-layout-flow {
			background: var(--color-8);
			border: 1px solid var(--color-5);
			border-radius: 25px;
		}
		.wp-block-term.is-layout-flow:hover {background: var(--color-7);}
		body.dark-mode .wp-block-term.is-layout-flow {background: var(--color-10); border: 1px solid var(--color-10);}
		body.dark-mode .wp-block-term.is-layout-flow:hover {background: var(--color-6);}

		/* Media Styling */
		.ct-media-container img:not(.hero-section img),
		.ct-media-container picture:not(.hero-section picture) {border-radius: 25px;}
		.ct-dynamic-media-inner[data-hover="zoom-in"] {border-radius: 25px 25px 0 0 !important;}

		/* Own CPT Archive Grid Layouts */
		.er-cpt-grid {display: grid; grid-template-columns: repeat(4, 1fr); column-gap: 30px; row-gap: 35px;}
		@media (min-width: 600px) and (max-width: 1024px) {.er-cpt-grid {grid-template-columns: repeat(3, 1fr);}}
		@media (max-width: 600px) {.er-cpt-grid {grid-template-columns: repeat(2, 1fr);}}
		.er-cpt-grid .ct-media-container {display: flex; margin-block-end: 30px;}
		.er-cpt-grid .entry-card {padding-bottom: 30px; text-align: start;}

		/* Related Content - Container & Slider */
		.ct-related-posts-container {border-top: 1px solid var(--color-5) !important; max-width: 1290px; margin: 0 auto;}
		body.dark-mode .ct-related-posts-container {border-top: 1px solid var(--color-3) !important;}
		body.dark-mode article > .ct-related-posts {border-top: 1px solid var(--color-3);}
		.ct-related-posts.is-layout-slider {--grid-columns-gap: 30px; --grid-columns-width: 25%;}
		@media (max-width: 992px) {.ct-related-posts.is-layout-slider {--grid-columns-width: 33.333%;}}
		@media (max-width: 768px) {.ct-related-posts.is-layout-slider {--grid-columns-width: 50%;}}

		/* Navigation Arrows */
		.flexy-arrow-next, .flexy-arrow-prev {
			width: 30px;
			height: 30px;
			transform: none;
			opacity: 0.75;
			background: var(--color-9);
			color: var(--color-8);
			position: absolute;
			top: -55px;
		}
		.flexy-arrow-next:hover, .flexy-arrow-prev:hover {opacity: 1; background: var(--color-9);}
		.flexy-arrow-next:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
		.flexy-arrow-next:focus:not(:focus-visible) {outline: none;}
		.flexy-arrow-prev:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
		.flexy-arrow-prev:focus:not(:focus-visible) {outline: none;}
		.flexy-arrow-next {right: 10px; left: auto;}
		.flexy-arrow-prev {right: 60px; left: auto;}

	</style>

	<script>

		// Header: class-toggle Approach - all visual Layout in CSS above, JS only for Scroll State + Submenu Repositioning
		document.addEventListener("DOMContentLoaded", function() {
			var shrunkHeight = 90;  // Header Height when scrolled (all Devices)
			var transitionMs = 300; // Must match CSS transition Duration
			var wasShrunk    = false;
			var header       = document.getElementById("header");
			if (!header) return;
			window.addEventListener("scroll", function() {
				var shrunk = window.scrollY > 50;
				if (shrunk === wasShrunk) return;
				wasShrunk = shrunk;
				header.classList.toggle("er-scrolled", shrunk);
				// Reposition Submenus after Header Height Transition completes
				setTimeout(function() {
					document.querySelectorAll("li.animated-submenu-block > .sub-menu").forEach(function(el) {
						if (shrunk) {
							var liTop = el.parentElement.getBoundingClientRect().top;
							el.style.top = (shrunkHeight - liTop) + "px";
						} else {
							el.style.top = "100%";
						}
					});
				}, transitionMs);
			}, { passive: true });
		});

		// Infinite Scroll - erLoadMore Object injected here for use by Observer below
		const erLoadMore = {
			ajaxUrl: '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>',
			nonce:   '<?php echo wp_create_nonce( "er_load_more_nonce" ); ?>'
		};

		// Card Reveal Animation - Helpers defined outside DOMContentLoaded so revealCards() is accessible from the Infinite Scroll Handler below
		const getColumns = function() {
			if ( window.innerWidth < 600 )  return 2;
			if ( window.innerWidth < 1024 ) return 3;
			return 4;
		};

		const revealCards = function( cards ) {
			cards.forEach( function( card ) {
				card.style.opacity   = '0';
				card.style.transform = 'translateY(150px)';
			} );
			const revealObserver = new IntersectionObserver( function( entries ) {
				entries.forEach( function( entry ) {
					if ( ! entry.isIntersecting ) return;
					const allCards = Array.from( document.querySelectorAll( '.er-cpt-grid article' ) );
					const index    = allCards.indexOf( entry.target );
					const delay    = ( index % getColumns() ) * 250;
					entry.target.style.transition = 'opacity 1.5s cubic-bezier(0.2, 1, 0.2, 1) ' + delay + 'ms, transform 1.5s cubic-bezier(0.2, 1, 0.2, 1) ' + delay + 'ms';
					entry.target.style.opacity    = '1';
					entry.target.style.transform  = 'translateY(0)';
					revealObserver.unobserve( entry.target );
				} );
			}, { threshold: 0.1 } );
			cards.forEach( function( card ) { revealObserver.observe( card ); } );
		};

		document.addEventListener( 'DOMContentLoaded', function() {

			// Related Posts: Trigger Flexy init + Entrance Animation
			var related = document.querySelector( '.ct-related-posts' );
			if ( related && related.querySelector( '.flexy-container' ) ) {
				related.classList.add( 'is-layout-slider' );
			}
			const flexyElements = document.querySelectorAll( '.flexy-container' );
			if ( flexyElements.length ) {
				const observer = new IntersectionObserver( function( entries ) {
					entries.forEach( function( entry ) {
						if ( entry.isIntersecting ) {
							entry.target.classList.add( 'daneden-slideInUp' );
							observer.unobserve( entry.target );
						}
					} );
				}, { threshold: 0.1 } );
				flexyElements.forEach( function( el ) { observer.observe( el ); } );
			}

			// Card Reveal Animation: Mark + animate initial server-rendered Cards
			const initialCards = Array.from( document.querySelectorAll( '.er-cpt-grid article' ) );
			if ( initialCards.length ) {
				initialCards.forEach( function( card ) { card.setAttribute( 'data-revealed', '1' ); } );
				revealCards( initialCards );
			}

		} );

		// Infinite Scroll
		const grid     = document.querySelector( '.er-cpt-grid' );
		const sentinel = document.querySelector( '.er-load-more-sentinel' );

		if ( grid && sentinel ) {
			let loading  = false;
			let hasMore  = true;
			let offset   = parseInt( grid.dataset.offset );

			const postType  = grid.dataset.postType;
			const taxonomy  = grid.dataset.taxonomy;
			const limit     = parseInt( grid.dataset.limit );
			const orderby   = grid.dataset.orderby;
			const order     = grid.dataset.order;

			// Read active Filter from URL Query String
			const urlParams  = new URLSearchParams( window.location.search );
			const activeSlug = urlParams.get( taxonomy ) || '';

			const observer = new IntersectionObserver( function( entries ) {
				entries.forEach( function( entry ) {
					if ( ! entry.isIntersecting || loading || ! hasMore ) return;
					loading = true;

					const formData = new FormData();
					formData.append( 'action',      'er_load_more' );
					formData.append( 'nonce',       erLoadMore.nonce );
					formData.append( 'post_type',   postType );
					formData.append( 'taxonomy',    taxonomy );
					formData.append( 'limit',       limit );
					formData.append( 'orderby',     orderby );
					formData.append( 'order',       order );
					formData.append( 'offset',      offset );
					formData.append( 'active_slug', activeSlug );

					fetch( erLoadMore.ajaxUrl, { method: 'POST', body: formData } )
						.then( function( res ) { return res.json(); } )
						.then( function( data ) {
							if ( data.success && data.data.html ) {
								grid.insertAdjacentHTML( 'beforeend', data.data.html );
								const newCards = Array.from( grid.querySelectorAll( 'article:not([data-revealed])' ) );
								newCards.forEach( function( card ) { card.setAttribute( 'data-revealed', '1' ); } );
								revealCards( newCards );
								offset  += limit;
								hasMore  = data.data.has_more;
							} else {
								hasMore = false;
							}
							loading = false;
						} )
						.catch( function() { loading = false; } );
				} );
			}, { rootMargin: '200px' } );

			observer.observe( sentinel );
		}

		// Details-open Persistence - Keeps Ancestor <details> open when er-filter-tab Links are clicked
		document.addEventListener('DOMContentLoaded', function() {

			// On Load: Restore any <details> that were open before the Filter Navigation
			var allDetails    = Array.from(document.querySelectorAll('details'));
			var openIndexes   = JSON.parse(sessionStorage.getItem('er_open_details') || '[]');
			var firstRestored = null;
			openIndexes.forEach(function(index) {
				if (allDetails[index]) {
					allDetails[index].open = true;
					if (!firstRestored) { firstRestored = allDetails[index]; }
				}
			});
			sessionStorage.removeItem('er_open_details');
			if (firstRestored) {
				var top = firstRestored.getBoundingClientRect().top + window.scrollY - 120;
				window.scrollTo({ top: top, behavior: 'smooth' });
			}

			// On filter-tab mousedown: Save Index of each Ancestor <details> in the page-wide Details List
			document.querySelectorAll('.er-archive-filter .er-filter-tab').forEach(function(tab) {
				tab.addEventListener('mousedown', function() {
					var allDetails  = Array.from(document.querySelectorAll('details'));
					var openIndexes = [];
					var node        = tab.parentElement;
					while (node && node !== document.body) {
						if (node.tagName === 'DETAILS') {
							var index = allDetails.indexOf(node);
							if (index !== -1) { openIndexes.push(index); }
						}
						node = node.parentElement;
					}
					if (openIndexes.length) {
						sessionStorage.setItem('er_open_details', JSON.stringify(openIndexes));
					}
				});
			});

		});

	</script>

	<?php
}, 15); // Load deferred Footer Styles after Theme defaults
