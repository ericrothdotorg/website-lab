<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ============================================================
// CHILD THEME COMPANION PHP — emits all custom block markup, shortcodes,
// critical head CSS, and front-end JS for the child theme.
// (Its CSS counterpart is the "Child Theme Companion CSS" snippet + theme style.css.)
// 
// CONTENTS
// 
//   1. SHARED HELPERS          taxonomy map, breadcrumbs, reading time
//                              (defined first; called by hero, cards, footer)
//   2. PERFORMANCE             featured-image eager + fetchpriority filter
//   3. CRITICAL CSS + HEAD     above-the-fold CSS, dark-mode FOUC guard,
//                              dynamic per-term hero background injection
//   4. HERO                    [er_hero] context-aware hero block
//   5. CPT ARCHIVE + FILTER    [er_cpt_archive] grid, filter pills, render-card,
//                              infinite-scroll AJAX handler
//   6. RELATED POSTS           [er_related_posts] slider
//   7. POST FOOTER             [er_post_footer] share row + prev/next + helpers
//   8. HEADER / NAV            header-scroll/submenu JS, mobile-overlay inject
//   9. FOOTER SCRIPTS (JS)     dark-mode toggle, card reveal, infinite scroll,
//                              details-open persistence
//   10. WOOCOMMERCE            Hide unused WooCommerce template and parts
//   11. TEMPLATE ROUTING       my-quotes + my-traits -> single-no-sidebar.html
//   
// CONVENTIONS
// 
//   - Shared helpers are defined in §1 so every later shortcode can call them
//     in any context (front end, admin-ajax, editor).
//   - Hook registrations (add_action/add_shortcode) fire at runtime, so their
//     order in this file does not affect execution; sections are grouped by topic.
//   - CSS that isn't above-the-fold lives in style.css / the CSS snippet, NOT here.
//
//   THEME-COUPLING MARKERS (search these before/after a theme switch):
//     THEME RELATED = hard coupling; breaks/orphans on switch — must fix.
//     THEME REVIEW  = soft coupling; reads a theme-defined value, won't
//                     break but the value shifts — verify.
// ============================================================

// ======================================
// 1. SHARED HELPERS
// ======================================

// Defined before everything that calls them so they're available in all
// execution contexts: front end, admin-ajax (infinite scroll), and editor.

// ---- 1a. Taxonomy map — single source of truth for post_type → taxonomy ----
function er_taxonomy_map() {
	return array(
		'post'         => 'category',
		'page'         => 'things',
		'my-interests' => 'topics',
		'my-quotes'    => 'groups',
		'my-traits'    => 'types',
	);
}

// ---- Things hub: canonical "My Pages" landing for the `page` taxonomy ----
// Stored as an option so it survives slug / parent changes and edits in one place.
// er_things_hub_id() is the single accessor; both er_things_hub_url() and the breadcrumb trail read the option only through it.
function er_things_hub_id() {
	return (int) get_option( 'er_things_hub_page' );
}

function er_things_hub_url() {
	$id = er_things_hub_id();
	return $id ? get_permalink( $id ) : home_url( '/' );
}

// Per-row taxonomy for mixed-type (search) loops: map lookup, else 'category'.
function er_taxonomy_for_post( $post_id ) {
	$map  = er_taxonomy_map();
	$type = get_post_type( $post_id );
	return isset( $map[ $type ] ) ? $map[ $type ] : 'category';
}

// ---- 1b. Breadcrumbs — [er_breadcrumbs]; called by er_hero_shortcode() ----
// Defined here (not in Site Essentials) so it's available on front end, admin, and AJAX.
function er_breadcrumbs() {
	// Never show on front page
	if ( is_front_page() ) {
		return '';
	}

	$separator = '<svg class="er-breadcrumb-separator" fill="currentColor" width="8" height="8" viewBox="0 0 8 8" aria-hidden="true" focusable="false"><path d="M2,6.9L4.8,4L2,1.1L2.6,0l4,4l-4,4L2,6.9z"></path></svg>';

	$home_icon = '<svg class="er-breadcrumb-home-icon" width="15" height="15" viewBox="0 0 15 15" fill="currentColor" aria-hidden="true" focusable="false"><path d="M7.5 1 0 7.8h2.1v6.1h4.1V9.8h2.7v4.1H13V7.8h2.1L7.5 1Z"></path></svg>';

	$crumbs = [];

	// Home
	$crumbs[] = '<a href="' . esc_url( home_url( '/' ) ) . '">' . $home_icon . '<span class="screen-reader-text">' . esc_html__( 'Home' ) . '</span></a>';

	if ( is_singular() ) {
		$post_type = get_post_type();

		if ( $post_type === 'post' ) {
			// Standard post: Home → Posts page (e.g. "My Blog") → Primary Category → Post title
			// The Posts-page crumb mirrors the is_home() branch: resolve the assigned
			// Posts page, walk its ancestors first, then the page itself — here LINKED,
			// because it is an ancestor level, not the current item.
			$posts_page_id = (int) get_option( 'page_for_posts' );
			if ( $posts_page_id ) {
				$posts_page_ancestors = array_reverse( get_post_ancestors( $posts_page_id ) );
				foreach ( $posts_page_ancestors as $ancestor_id ) {
					$crumbs[] = '<a href="' . esc_url( get_permalink( $ancestor_id ) ) . '">' . esc_html( get_the_title( $ancestor_id ) ) . '</a>';
				}
				$crumbs[] = '<a href="' . esc_url( get_permalink( $posts_page_id ) ) . '">' . esc_html( get_the_title( $posts_page_id ) ) . '</a>';
			}

			$categories = get_the_category();
			if ( ! empty( $categories ) ) {
				$primary_cat = $categories[0];
				$crumbs[] = '<a href="' . esc_url( get_category_link( $primary_cat->term_id ) ) . '">' . esc_html( $primary_cat->name ) . '</a>';
			}

		} elseif ( $post_type !== 'page' ) {
			// CPT single: Home → Listing-page ancestor(s) → Listing page → Primary term → Post title
			// Mirrors the 'post' branch. The listing pages are real Pages whose slug equals the
			// CPT archive slug (e.g. /my-interests/), so resolve the Page first to get its
			// ancestors (e.g. "Personal"); fall back to the bare post type archive link.
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && $post_type_obj->has_archive ) {
				$archive_slug = is_string( $post_type_obj->has_archive ) ? $post_type_obj->has_archive : $post_type;

				// Look the listing Page up by SLUG, not path: get_page_by_path() requires the
				// full hierarchical path (e.g. 'personal/my-interests'), so a child page would
				// be missed. A slug query finds it regardless of its parent.
				$listing_pages = get_posts( array(
					'name'        => $archive_slug,
					'post_type'   => 'page',
					'post_status' => 'publish',
					'numberposts' => 1,
				) );
				$listing_page  = ! empty( $listing_pages ) ? $listing_pages[0] : null;

				if ( $listing_page instanceof WP_Post ) {
					$listing_ancestors = array_reverse( get_post_ancestors( $listing_page->ID ) );
					foreach ( $listing_ancestors as $ancestor_id ) {
						$crumbs[] = '<a href="' . esc_url( get_permalink( $ancestor_id ) ) . '">' . esc_html( get_the_title( $ancestor_id ) ) . '</a>';
					}
					$crumbs[] = '<a href="' . esc_url( get_permalink( $listing_page->ID ) ) . '">' . esc_html( get_the_title( $listing_page->ID ) ) . '</a>';
				} else {
					$archive_link = get_post_type_archive_link( $post_type );
					if ( $archive_link ) {
						$crumbs[] = '<a href="' . esc_url( $archive_link ) . '">' . esc_html( $post_type_obj->labels->name ) . '</a>';
					}
				}
			}

			// Primary term — the CPT equivalent of the post branch's primary category,
			// resolved through er_taxonomy_map() (the single source of truth).
			$taxonomy_map = er_taxonomy_map();
			if ( isset( $taxonomy_map[ $post_type ] ) ) {
				$post_terms = get_the_terms( get_the_ID(), $taxonomy_map[ $post_type ] );
				if ( ! empty( $post_terms ) && ! is_wp_error( $post_terms ) ) {
					$primary_term = $post_terms[0];
					$term_link    = get_term_link( $primary_term );
					if ( ! is_wp_error( $term_link ) ) {
						$crumbs[] = '<a href="' . esc_url( $term_link ) . '">' . esc_html( $primary_term->name ) . '</a>';
					}
				}
			}

		} else {
			// Static page: Home → Parent page(s) → Page title
			$ancestors = array_reverse( get_post_ancestors( get_the_ID() ) );
			foreach ( $ancestors as $ancestor_id ) {
				$crumbs[] = '<a href="' . esc_url( get_permalink( $ancestor_id ) ) . '">' . esc_html( get_the_title( $ancestor_id ) ) . '</a>';
			}

			// Filtered listing Page (e.g. /this-site/my-pages/?things=slug or
			// /personal/my-interests/?topics=slug): any Page hosting [er_cpt_archive]
			// with a taxonomy filter param in the URL. Iterate the taxonomies in
			// er_taxonomy_map() (single source of truth); for whichever filter param is
			// present and resolves to a real term, mirror the term-archive trail —
			// demote the page title to a LINK and make the filtered term the current
			// item, so the breadcrumb agrees with the hero, pills and grid (all of which
			// read this param). No valid param → this block is inert and the shared
			// current-item span below runs as before.
			$taxonomy_map = er_taxonomy_map();
			foreach ( $taxonomy_map as $map_taxonomy ) {
				if ( ! empty( $_GET[ $map_taxonomy ] ) ) {
					$filter_term = get_term_by( 'slug', sanitize_key( wp_unslash( $_GET[ $map_taxonomy ] ) ), $map_taxonomy );
					if ( $filter_term instanceof WP_Term ) {
						$crumbs[]            = '<a href="' . esc_url( get_permalink( get_the_ID() ) ) . '">' . esc_html( get_the_title() ) . '</a>';
						$crumbs[]            = '<span aria-current="page">' . esc_html( $filter_term->name ) . '</span>';
						$er_crumb_self_built = true;
						break;
					}
				}
			}
		}

		// Current item — no link
		// Skipped only when a branch above already appended its own current item (filtered listing page). All other singular contexts are unaffected.
		if ( empty( $er_crumb_self_built ) ) {
			$crumbs[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
		}

	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();

		// Standard category/tag: anchor under the assigned Posts page (e.g. Personal → My Blog),
		// mirroring the 'post' single branch so a category/tag trail matches its posts' trail.
		if ( is_category() || is_tag() ) {
			$posts_page_id = (int) get_option( 'page_for_posts' );
			if ( $posts_page_id ) {
				$posts_page_ancestors = array_reverse( get_post_ancestors( $posts_page_id ) );
				foreach ( $posts_page_ancestors as $ancestor_id ) {
					$crumbs[] = '<a href="' . esc_url( get_permalink( $ancestor_id ) ) . '">' . esc_html( get_the_title( $ancestor_id ) ) . '</a>';
				}
				$crumbs[] = '<a href="' . esc_url( get_permalink( $posts_page_id ) ) . '">' . esc_html( get_the_title( $posts_page_id ) ) . '</a>';
			}
		}

		if ( is_tax() ) {
			// Taxonomy archive: Home → Listing-page ancestor(s) → Listing page → Term name.
			// Resolve the listing PAGE (real Page whose slug equals the CPT archive slug, e.g.
			// /my-interests/) and walk its ancestors (e.g. "Personal"), exactly like the CPT
			// single branch — so a topics/groups/types trail matches its CPT's trail. Falls back
			// to the bare post type archive link if no listing Page exists.
			$taxonomy   = $term->taxonomy;
			$tax_obj    = get_taxonomy( $taxonomy );
			$post_types = $tax_obj ? (array) $tax_obj->object_type : [];

			// Use first associated CPT that has an archive
			foreach ( $post_types as $pt ) {
				$pt_obj = get_post_type_object( $pt );
				if ( $pt_obj && $pt_obj->has_archive ) {
					$archive_slug = is_string( $pt_obj->has_archive ) ? $pt_obj->has_archive : $pt;

					$listing_pages = get_posts( array(
						'name'        => $archive_slug,
						'post_type'   => 'page',
						'post_status' => 'publish',
						'numberposts' => 1,
					) );
					$listing_page  = ! empty( $listing_pages ) ? $listing_pages[0] : null;

					if ( $listing_page instanceof WP_Post ) {
						$listing_ancestors = array_reverse( get_post_ancestors( $listing_page->ID ) );
						foreach ( $listing_ancestors as $ancestor_id ) {
							$crumbs[] = '<a href="' . esc_url( get_permalink( $ancestor_id ) ) . '">' . esc_html( get_the_title( $ancestor_id ) ) . '</a>';
						}
						$crumbs[] = '<a href="' . esc_url( get_permalink( $listing_page->ID ) ) . '">' . esc_html( get_the_title( $listing_page->ID ) ) . '</a>';
					} else {
						$archive_link = get_post_type_archive_link( $pt );
						if ( $archive_link ) {
							$crumbs[] = '<a href="' . esc_url( $archive_link ) . '">' . esc_html( $pt_obj->labels->name ) . '</a>';
						}
					}
					break;
				}
			}
		}

		// Parent term (if any)
		if ( ! empty( $term->parent ) ) {
			$parent_term = get_term( $term->parent, $term->taxonomy );
			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				$crumbs[] = '<a href="' . esc_url( get_term_link( $parent_term ) ) . '">' . esc_html( $parent_term->name ) . '</a>';
			}
		}

		// Current term — no link.
		// For `things` (taxonomy on the page post type, no CPT archive), route the trail through the My Pages hub, then end on the term name
		if ( $term->taxonomy === 'things' ) {
			$hub_id = er_things_hub_id();
			if ( $hub_id ) {
				$hub_ancestors = array_reverse( get_post_ancestors( $hub_id ) );
				foreach ( $hub_ancestors as $ancestor_id ) {
					$crumbs[] = '<a href="' . esc_url( get_permalink( $ancestor_id ) ) . '">' . esc_html( get_the_title( $ancestor_id ) ) . '</a>';
				}
				$crumbs[] = '<a href="' . esc_url( get_permalink( $hub_id ) ) . '">' . esc_html( get_the_title( $hub_id ) ) . '</a>';
			}
		}
		$crumbs[] = '<span aria-current="page">' . esc_html( $term->name ) . '</span>';

	} elseif ( is_home() ) {
		// Blog posts index — mirror the static-page crumb trail for the assigned Posts page
		$posts_page_id = (int) get_option( 'page_for_posts' );
		if ( $posts_page_id ) {
			$ancestors = array_reverse( get_post_ancestors( $posts_page_id ) );
			foreach ( $ancestors as $ancestor_id ) {
				$crumbs[] = '<a href="' . esc_url( get_permalink( $ancestor_id ) ) . '">' . esc_html( get_the_title( $ancestor_id ) ) . '</a>';
			}

			// Filtered blog listing (e.g. /personal/my-blog/?category=slug): same pattern
			// as the CPT listing branch above. The blog's taxonomy is 'category'
			// (er_taxonomy_map()['post']). When ?category= resolves to a real term, demote
			// the Posts-page title to a LINK and make the filtered term the current item,
			// matching the native /category/<slug>/ trail. No/invalid param → unchanged.
			$blog_taxonomy = er_taxonomy_map()['post']; // 'category'
			if ( ! empty( $_GET[ $blog_taxonomy ] ) ) {
				$filter_term = get_term_by( 'slug', sanitize_key( wp_unslash( $_GET[ $blog_taxonomy ] ) ), $blog_taxonomy );
			}
			if ( ! empty( $filter_term ) && $filter_term instanceof WP_Term ) {
				$crumbs[] = '<a href="' . esc_url( get_permalink( $posts_page_id ) ) . '">' . esc_html( get_the_title( $posts_page_id ) ) . '</a>';
				$crumbs[] = '<span aria-current="page">' . esc_html( $filter_term->name ) . '</span>';
			} else {
				$crumbs[] = '<span aria-current="page">' . esc_html( get_the_title( $posts_page_id ) ) . '</span>';
			}
		} else {
			$crumbs[] = '<span aria-current="page">' . esc_html__( 'Blog' ) . '</span>';
		}
	
	} elseif ( is_post_type_archive() ) {
		// CPT archive root: Home → CPT name
		$post_type_obj = get_queried_object();
		$crumbs[]      = '<span aria-current="page">' . esc_html( $post_type_obj->labels->name ) . '</span>';

	} elseif ( is_search() ) {
		$crumbs[] = '<span aria-current="page">' . esc_html__( 'Search Results' ) . '</span>';

	} elseif ( is_404() ) {
		$crumbs[] = '<span aria-current="page">' . esc_html__( '404 Not Found' ) . '</span>';
	}

	// Build output
	$output  = '<nav class="er-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb' ) . '">';
	$output .= '<ol>';

	foreach ( $crumbs as $index => $crumb ) {
		$is_last = ( $index === count( $crumbs ) - 1 );
		$output .= '<li>';
		$output .= $crumb;
		if ( ! $is_last ) {
			$output .= $separator;
		}
		$output .= '</li>';
	}

	$output .= '</ol>';
	$output .= '</nav>';

	return $output;
}
add_shortcode( 'er_breadcrumbs', 'er_breadcrumbs' );

// ---- 1c. Reading time — used by er_render_card() for archive cards ----
// READING_SPEED_WPM is defined in Site Essentials; falls back to 200 via defined() guard.
// (On single posts, reading time shows via [post_stats] in the sidebar — no injection here.)

function er_reading_time( $post_id = null, $echo = false ) {
	$post_id    = $post_id ?: get_the_ID();
	$content    = get_post_field( 'post_content', $post_id );
	$word_count = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) );
	$wpm        = defined( 'READING_SPEED_WPM' ) ? READING_SPEED_WPM : 200;
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
			.   ' ' . $minutes . ' min read'
			. '</li>';
	if ( $echo ) {
		echo $output;
	} else {
		return $output;
	}
}

// ======================================
// 2. PERFORMANCE & ASSET LOADING
// ======================================

// Switch singular featured images from lazy -> eager + fetchpriority="high".

// THEME REVIEW — hooks core/post-featured-image block output. Core block (theme-agnostic
// across block themes); survives a block->block theme switch. Verify only if the new theme
// renders featured images via a non-core mechanism.
add_filter( 'render_block', function( $block_content, $block ) {
	if ( $block['blockName'] !== 'core/post-featured-image' ) {
		return $block_content;
	}
	if ( ! is_singular() ) {
		return $block_content;
	}
	// Remove loading="lazy"
	$block_content = preg_replace( '/\s*loading=["\']lazy["\']/', '', $block_content );
	// Add fetchpriority="high" if not present
	if ( strpos( $block_content, 'fetchpriority' ) === false ) {
		$block_content = str_replace( '<img', '<img fetchpriority="high"', $block_content );
	}
	// Add loading="eager" if not present
	if ( strpos( $block_content, 'loading=' ) === false ) {
		$block_content = str_replace( '<img', '<img loading="eager"', $block_content );
	}
	return $block_content;
}, 10, 2 );

// ======================================
// 3. CRITICAL CSS + HEAD SCRIPTS
// ======================================

// Above-the-fold CSS (hero structure + fixed header) that must paint before
// style.css loads, the dark-mode FOUC guard, and the per-term hero background.

add_action( 'wp_head', function() {
	?>
	<style>

		/* Critical above-the-fold only — everything else is in style.css */

		/* Hero structural */
		:root {--hero-curve: 15vw;}
		.er-hero-section {position: relative; min-height: 320px; display: flex; align-items: flex-end;}
		.er-hero-section.has-background-dim {min-height: 480px;}
		.er-hero-bg {position: absolute; inset: 0; background-size: cover !important; background-position: center center !important; border-bottom-right-radius: var(--hero-curve); overflow: hidden;}
		.er-hero-inner {position: relative; z-index: 1; width: 100%; padding: 120px 0 40px;}
		@media (max-width: 767px) {
			.er-hero-section {min-height: 240px;}
			.er-hero-section.has-background-dim {min-height: 300px;}
			.er-hero-inner {padding: 90px 0 30px;}
		}

		/* Header fixed positioning — must be critical to prevent layout shift */
		.er-header {
			position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important;
			width: 100% !important; z-index: 999 !important; display: flex !important;
			align-items: center !important; background: rgba(0, 0, 0, 0.65) !important;
			backdrop-filter: blur(10px) !important; -webkit-backdrop-filter: blur(10px) !important;
			transition: height 0.3s ease !important; height: 120px !important;
		}
		@media (max-width: 1050px) {.er-header {height: 90px !important;}}
		.er-header.er-scrolled {height: 90px !important;}

	</style>

	<!-- Dark mode flash prevention — must run before body renders -->
	<script>
(function() {
	try {
		const storedPreference = localStorage.getItem('changeMode');
		const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
		if (storedPreference === 'true' || (storedPreference === null && prefersDark)) {
			document.documentElement.style.background = '#070c12';
			document.documentElement.classList.add('dark-mode-preload');
		}
	} catch(e) {
		console.warn('Dark mode initialization failed:', e);
	}
})();
	</script>

	<?php
	// Term Image Meta - Inject paired with registered er_term_image_id
	// NOTE: This intentionally uses two Strategies and reflects two genuinely different Page Contexts with different DOM States
	// Valid taxonomy filter params = the taxonomies in er_taxonomy_map() (single
	// source of truth). The filter-tab URL uses the taxonomy slug as the GET param
	// (see [er_cpt_archive]: add_query_arg( $taxonomy, ... )), so the taxonomy IS
	// the param name. Deriving from the map means a new CPT added there is picked
	// up here automatically — no separate list to keep in sync.
	$er_term = null;

	// Strategy 1: GET param present (filter-tab URL on a static page)
	foreach (er_taxonomy_map() as $er_taxonomy) {
		if (!empty($_GET[$er_taxonomy])) {
			$er_t = get_term_by('slug', sanitize_key($_GET[$er_taxonomy]), $er_taxonomy);
			if ($er_t instanceof WP_Term) { $er_term = $er_t; break; }
		}
	}

	// Strategy 2: Native Taxonomy Archive (no GET param, FSE renders no background span)
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
			// Defer term image injection to footer — inject as background span into cover structure
			add_action( 'wp_footer', function() use ( $er_img_url ) {
				?>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var hero = document.querySelector('.er-hero-section');
			if (!hero || hero.querySelector('.er-hero-bg')) return;
			var span = document.createElement('span');
			span.setAttribute('aria-hidden', 'true');
			span.className = 'has-background-dim er-hero-bg';
			span.style.backgroundImage = 'url(<?php echo esc_url($er_img_url); ?>)';
			span.style.backgroundPosition = 'center center';
			hero.insertBefore(span, hero.firstChild);
			hero.classList.add('has-background-dim');
		});
	</script>
			<?php
			}, 5 );
			?>
			<?php else : ?>
	<style>
		/* Term Image Meta — override background on the cover span */
		.er-hero-section .er-hero-bg {background-image: url('<?php echo esc_url($er_img_url); ?>') !important;}
	</style>
			<?php endif;
		endif;
	}
	?>

<?php

}, 5 ); // Load critical Styles in Head early

// ======================================
// 4. HERO
// ======================================

// [er_hero] — self-contained hero (div.er-hero-section), styled by er-hero-*
// in the §3 critical CSS. Context-aware: taxonomy / singular / page / home / archive.

function er_hero_shortcode() {
	$output = '';

	// --------------------------------------------------------
	// Shared helper: build background span from attachment ID
	// --------------------------------------------------------
	
	// NOTE on the "has-background-dim" class below:
	// This is WordPress's core cover-block class name, reused here ONLY as a
	// flag meaning "this hero has a background image". It does NOT add any
	// overlay/tint over the image — there is no layer over the image at all.
	// The only dimming in the hero is the rgba(0,0,0,0.65) box behind the
	// title/breadcrumbs, defined on .er-hero-inner in the child theme style.css
	
	$build_bg_span = function( $img_id ) {
		if ( ! $img_id ) return '';
		$img_url = wp_get_attachment_image_url( $img_id, 'full' );
		if ( ! $img_url ) return '';
		return '<span'
			. ' aria-hidden="true"'
			. ' class="has-background-dim er-hero-bg"'
			. ' style="background-image:url(' . esc_url( $img_url ) . ');background-position:center center;">'
			. '</span>';
	};

	// Shared helper: build background span from post thumbnail
	$build_bg_from_post = function( $post_id ) use ( $build_bg_span ) {
		if ( ! has_post_thumbnail( $post_id ) ) return '';
		return $build_bg_span( get_post_thumbnail_id( $post_id ) );
	};

	// --------------------------------------------------------
	// CONTEXT: Taxonomy Archive (is_tax, is_category, is_tag)
	// --------------------------------------------------------
	if ( is_tax() || is_category() || is_tag() ) {
		$term        = get_queried_object();
		$title       = $term->name;
		$description = term_description();
		$term_img_id = (int) get_term_meta( $term->term_id, 'er_term_image_id', true );
		$bg_span     = $build_bg_span( $term_img_id );
		$has_image   = $bg_span ? ' has-background-dim' : ' er-hero--no-image';

		$output .= '<div class="er-hero-section' . $has_image . '" data-type="taxonomy">';
		$output .= $bg_span;
		$output .= '<div class="er-hero-inner">';
		$output .= '<header class="er-entry-header">';
		$output .= '<h1 class="page-title">' . esc_html( $title ) . '</h1>';
		if ( $description ) {
			$output .= '<div class="page-description er-hidden-sm">' . wp_kses_post( $description ) . '</div>';
		}
		$output .= er_breadcrumbs();
		$output .= '</header>';
		$output .= '</div>';
		$output .= '</div>';

	// --------------------------------------------------------
	// CONTEXT: Singular Post or CPT (not Page)
	// --------------------------------------------------------
	} elseif ( is_singular() && ! is_page() ) {
		$post_id  = get_the_ID();
		$title    = get_the_title();
		$bg_span  = $build_bg_from_post( $post_id );
		$has_image = $bg_span ? ' has-background-dim' : ' er-hero--no-image';

		$output .= '<div class="er-hero-section' . $has_image . '" data-type="singular">';
		$output .= $bg_span;
		$output .= '<div class="er-hero-inner">';
		$output .= '<header class="er-entry-header">';
		$output .= '<h1 class="page-title">' . esc_html( $title ) . '</h1>';
		if ( has_excerpt( $post_id ) ) {
			$output .= '<div class="page-description er-hidden-sm">' . wp_kses_post( get_the_excerpt( $post_id ) ) . '</div>';
		}
		$output .= er_breadcrumbs();
		$output .= '</header>';
		$output .= '</div>';
		$output .= '</div>';

	// --------------------------------------------------------
	// CONTEXT: Static Page
	// --------------------------------------------------------
	} elseif ( is_page() ) {
		$post_id   = get_the_ID();
		$title     = get_the_title();
		$bg_span   = $build_bg_from_post( $post_id );
		$has_image = $bg_span ? ' has-background-dim' : ' er-hero--no-image';

		$output .= '<div class="er-hero-section' . $has_image . '" data-type="page">';
		$output .= $bg_span;
		$output .= '<div class="er-hero-inner">';
		$output .= '<header class="er-entry-header">';
		$output .= '<h1 class="page-title">' . esc_html( $title ) . '</h1>';
		if ( has_excerpt( $post_id ) ) {
			$output .= '<div class="page-description er-hidden-sm">' . wp_kses_post( get_the_excerpt( $post_id ) ) . '</div>';
		}
		$output .= er_breadcrumbs();
		$output .= '</header>';
		$output .= '</div>';
		$output .= '</div>';

	// --------------------------------------------------------
	// CONTEXT: Blog Posts Index (is_home — assigned Posts page)
	// --------------------------------------------------------
	} elseif ( is_home() ) {
		// Blog posts index (the Page assigned under Settings → Reading → Posts page)
		$post_id   = (int) get_option( 'page_for_posts' );
		$title     = $post_id ? get_the_title( $post_id ) : get_bloginfo( 'name' );
		$bg_span   = $build_bg_from_post( $post_id );
		$has_image = $bg_span ? ' has-background-dim' : ' er-hero--no-image';

		$output .= '<div class="er-hero-section' . $has_image . '" data-type="home">';
		$output .= $bg_span;
		$output .= '<div class="er-hero-inner">';
		$output .= '<header class="er-entry-header">';
		$output .= '<h1 class="page-title">' . esc_html( $title ) . '</h1>';
		if ( $post_id && has_excerpt( $post_id ) ) {
			$output .= '<div class="page-description er-hidden-sm">' . wp_kses_post( get_the_excerpt( $post_id ) ) . '</div>';
		}
		$output .= er_breadcrumbs();
		$output .= '</header>';
		$output .= '</div>';
		$output .= '</div>';
	}
	return $output;
}
add_shortcode( 'er_hero', 'er_hero_shortcode' );

// ======================================
// 5. CPT ARCHIVE + FILTER
// ======================================

// [er_cpt_archive] renders the filtered grid with infinite scroll.
// er_render_card() is shared with the AJAX load-more handler.

// ---- 5a. Shared card renderer (used by the shortcode AND the AJAX handler) ----
function er_render_card( $post_id, $taxonomy ) {
	$url        = get_permalink( $post_id );
	$title      = get_the_title( $post_id );
	$thumb      = get_the_post_thumbnail( $post_id, 'medium_large', array(
		'loading'  => 'lazy',
		'decoding' => 'async',
		'style'    => 'aspect-ratio: 16/9;',
	) );
	$post_terms = get_the_terms( $post_id, $taxonomy );
	$term_obj   = ( $post_terms && ! is_wp_error( $post_terms ) ) ? $post_terms[0] : null;

	echo '<article class="' . esc_attr( implode( ' ', get_post_class( 'entry-card card-content', $post_id ) ) ) . '">';
	echo '<h2 class="entry-title"><a href="' . esc_url( $url ) . '" rel="bookmark">' . esc_html( $title ) . '</a></h2>';

	if ( $thumb ) {
		echo '<a class="er-media-container" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $title ) . '">'
		   . $thumb
		   . '</a>';
	}

	echo '<ul class="entry-meta" data-type="icons:slash" data-id="eQBHSW">';
	er_reading_time( $post_id, true );

	if ( $term_obj ) {
		echo '<li class="meta-categories" data-type="simple">'
		   . '<svg width="13" height="13" viewBox="0 0 15 15"><path d="M14.4,1.2H0.6C0.3,1.2,0,1.5,0,1.9V5c0,0.3,0.3,0.6,0.6,0.6h0.6v7.5c0,0.3,0.3,0.6,0.6,0.6h11.2c0.3,0,0.6-0.3,0.6-0.6V5.6h0.6C14.7,5.6,15,5.3,15,5V1.9C15,1.5,14.7,1.2,14.4,1.2z M12.5,12.5h-10V5.6h10V12.5z M13.8,4.4H1.2V2.5h12.5V4.4z M5.6,7.5c0-0.3,0.3-0.6,0.6-0.6h2.5c0.3,0,0.6,0.3,0.6,0.6S9.1,8.1,8.8,8.1H6.2C5.9,8.1,5.6,7.8,5.6,7.5z"></path></svg>'
		   . '<a href="' . esc_url( get_term_link( $term_obj ) ) . '" rel="tag" class="er-term-' . esc_attr( $term_obj->term_id ) . '">'
		   . esc_html( $term_obj->name )
		   . '</a>'
		   . '</li>';
	}

	echo '</ul>';
	echo '</article>';
}

// ---- 5b. [er_cpt_archive] — filtered grid + filter pills + infinite-scroll sentinel ----
add_shortcode( 'er_cpt_archive', function( $atts ) {

	$taxonomy_map = er_taxonomy_map();

	// Context detection
	// Term archive (/topics/x/, /category/y/ ...): post_type, taxonomy and term are all
	// derived from the URL; the grid is locked to that single term and shows no filter tabs.
	// Listing page (My Blog and CPTs): attribute-driven with ?taxonomy tabs.
	$queried         = get_queried_object();
	$is_term_archive = ( $queried instanceof WP_Term ) && in_array( $queried->taxonomy, $taxonomy_map, true );
	$has_explicit_pt = is_array( $atts ) && ! empty( $atts['post_type'] );

	if ( $is_term_archive ) {
		$taxonomy  = $queried->taxonomy;
		$post_type = array_search( $taxonomy, $taxonomy_map, true );
		$atts      = shortcode_atts( array(
			'limit'   => 12,
			'orderby' => 'date',
			'order'   => 'DESC',
		), $atts, 'er_cpt_archive' );
		$show_tabs   = true; // Term archives show the full taxonomy pill row (current term marked active)
		$active_slug = $queried->slug; // grid is locked to this term (query + infinite scroll)

	} elseif ( $has_explicit_pt || ! is_archive() ) {
		// Default Values as Fallback if no Attributes defined in Shortcode
		$atts = shortcode_atts( array(
			'post_type' => 'my-interests',
			'limit'     => -1,
			'orderby'   => 'rand',
			'order'     => 'DESC',
			'filtering' => 'yes',
		), $atts, 'er_cpt_archive' );

		$post_type = sanitize_key( $atts['post_type'] );

		if ( ! isset( $taxonomy_map[ $post_type ] ) ) {
			return '<p>Invalid post_type.</p>';
		}

		$taxonomy    = $taxonomy_map[ $post_type ];
		$show_tabs   = ( $atts['filtering'] !== 'no' );
		$active_slug = isset( $_GET[ $taxonomy ] ) ? sanitize_key( $_GET[ $taxonomy ] ) : '';

	} else {
		// Archive context we deliberately do not render here (tags, dates, authors, unmapped taxonomies)
		return '';
	}

	$base_url = esc_url_raw( strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) );

	// Filter Tabs
	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => true,
	) );

	ob_start();

	if ( ! empty( $terms ) && ! is_wp_error( $terms ) && $show_tabs ) {
		$total      = wp_count_posts( $post_type )->publish;
		$all_active = $active_slug === '' ? ' er-active' : '';

		// Pill URLs differ by mode:
		// - Listing page: ?taxonomy=slug query args on the current page (original behaviour).
		// - Term archive: clean term-archive permalinks; the ALL pill points back to the
		//   listing page (the assigned Posts page for 'post', the post type archive for CPTs).
		if ( $is_term_archive ) {
			if ( $post_type === 'post' ) {
				$posts_page_id = (int) get_option( 'page_for_posts' );
				$all_url       = $posts_page_id ? get_permalink( $posts_page_id ) : home_url( '/' );
			} elseif ( $post_type === 'page' ) {
				$all_url = er_things_hub_url();
			} else {
				$archive_link = get_post_type_archive_link( $post_type );
				$all_url      = $archive_link ? $archive_link : home_url( '/' );
			}
		} else {
			$all_url = $base_url;
		}

		echo '<div class="er-archive-filter">';
		printf(
			'<a href="%s" class="er-filter-tab%s">All <span>(%d)</span></a>',
			esc_url( $all_url ),
			esc_attr( $all_active ),
			intval( $total )
		);
		foreach ( $terms as $term ) {
			$active = ( $active_slug === $term->slug ) ? ' er-active' : '';
			if ( $is_term_archive ) {
				$term_link = get_term_link( $term );
				$url       = is_wp_error( $term_link ) ? $base_url : $term_link;
			} else {
				$url = add_query_arg( $taxonomy, $term->slug, $base_url );
			}
			printf(
				'<a href="%s" class="er-filter-tab%s">%s <span>(%d)</span></a>',
				esc_url( $url ),
				esc_attr( $active ),
				esc_html( $term->name ), /* natural case, matching the tag-cloud pills */
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
	
	if ( $post_type === 'page' ) {
		$query_args['post__not_in'] = array( 14581, 159702 ); // exclude DUMMY and My Pages — must match 5c
	}
	
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
		. ' data-active-slug="' . esc_attr( $active_slug ) . '"'
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

// ---- 5c. Infinite-scroll AJAX handler (load-more) ----
add_action( 'wp_ajax_er_load_more', 'er_load_more_handler' );
add_action( 'wp_ajax_nopriv_er_load_more', 'er_load_more_handler' );

function er_load_more_handler() {
	check_ajax_referer( 'er_load_more_nonce', 'nonce' );

	// Search mode: mixed post types, no taxonomy/term filter.
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	if ( $search !== '' ) {
		$limit  = intval( $_POST['limit'] );
		$offset = intval( $_POST['offset'] );

		$loop = new WP_Query( array(
			's'              => $search,
			'post_type'      => get_post_types( array( 'exclude_from_search' => false ), 'names' ),
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'offset'         => $offset,
		) );

		if ( ! $loop->have_posts() ) {
			wp_send_json_success( array( 'html' => '', 'has_more' => false ) );
			wp_die();
		}

		ob_start();
		while ( $loop->have_posts() ) {
			$loop->the_post();
			er_render_card( get_the_ID(), er_taxonomy_for_post( get_the_ID() ) );
		}
		wp_reset_postdata();

		wp_send_json_success( array(
			'html'     => ob_get_clean(),
			'has_more' => $loop->found_posts > ( $offset + $limit ),
		) );
		wp_die();
	}

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

	if ( $post_type === 'page' ) {
		$query_args['post__not_in'] = array( 14581, 159702 ); // exclude DUMMY and My Pages — must match 5b
	}

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
// 6. RELATED POSTS
// ======================================

// [er_related_posts] — auto-detects post_type, matches by taxonomy (CPTs) or
// category (posts), excludes current post, renders 12 via Eric Slider

add_shortcode( 'er_related_posts', function() {
	global $post;
	if ( ! $post ) return '';

	$post_type    = get_post_type( $post->ID );
	$taxonomy_map = er_taxonomy_map();

	// Resolve the relating taxonomy up front so it is ALWAYS defined:
	// a CPT's custom taxonomy, or 'category' for a standard post.
	// er_render_card() requires this as its second argument.
	$taxonomy = isset( $taxonomy_map[ $post_type ] ) ? $taxonomy_map[ $post_type ] : 'category';

	$query_args = array(
		'post_type'      => $post_type,
		'posts_per_page' => 12,
		'post_status'    => 'publish',
		'orderby'        => 'rand',
		'post__not_in'   => array( $post->ID ),
	);

	if ( isset( $taxonomy_map[ $post_type ] ) ) {
		// CPT — match by its custom taxonomy
		$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$query_args['tax_query'] = array( array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $terms,
			) );
		}
	} else {
		// Standard post — match by category
		$categories = wp_get_post_categories( $post->ID );
		if ( ! empty( $categories ) ) {
			$query_args['category__in'] = $categories;
		}
	}

	$related = new WP_Query( $query_args );
	if ( ! $related->have_posts() ) return '';

	ob_start();
	echo '<div class="er-related-posts-container">';
	echo '<h3 class="er-related-title">Related</h3>';
	echo '<div class="er-related-posts slideshow-multiple-items-4">';

	while ( $related->have_posts() ) {
		$related->the_post();
		er_render_card( get_the_ID(), $taxonomy );
	}
	wp_reset_postdata();

	echo '</div>';
	echo '</div>';

	return ob_get_clean();
} );

// ======================================
// 7. POST FOOTER  (blog + CPT)
// ======================================

// [er_post_footer] — share row + prev/next navigation, single posts only.
// CSS lives in style.css; share opener + blocker-evasion explained inline.

// ---- 7a. [er_post_footer] — share row + prev/next ----
add_shortcode( 'er_post_footer', function () {

	global $post;
	if ( ! $post || ! is_singular() ) {
		return '';
	}

	// Share Targets: Label => [ href, icon-key ]
	$url   = get_permalink( $post );
	$title = get_the_title( $post );
	$u     = rawurlencode( $url );
	$t     = rawurlencode( $title );

	$networks = array(
		'Bluesky'  => array( 'https://bsky.app/intent/compose?text=' . $t . '%20' . $u, 'bluesky' ),
		'Facebook' => array( 'https://www.facebook.com/sharer/sharer.php?u=' . $u,       'facebook' ),
		'LINE'     => array( 'https://social-plugins.line.me/lineit/share?url=' . $u,    'line' ),
		'LinkedIn' => array( 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u, 'linkedin' ),
		'Telegram' => array( 'https://t.me/share/url?url=' . $u . '&text=' . $t,         'telegram' ),
		'Threads'  => array( 'https://www.threads.net/intent/post?text=' . $t . '%20' . $u, 'threads' ),
		'WhatsApp' => array( 'https://api.whatsapp.com/send?text=' . $t . '%20' . $u,    'whatsapp' ),
		'Email'    => array( 'mailto:?subject=' . $t . '&body=' . $u,                    'email' ),
	);

	$icons = er_post_footer_icons();

	// Prev / Next Resolution
	$post_type    = get_post_type( $post );
	$taxonomy_map = function_exists( 'er_taxonomy_map' ) ? er_taxonomy_map() : array();
	$taxonomy     = isset( $taxonomy_map[ $post_type ] ) ? $taxonomy_map[ $post_type ] : 'category';

	$prev = get_previous_post( true, '', $taxonomy ); // true = within same term
	$next = get_next_post( true, '', $taxonomy );

	ob_start();
	echo er_post_footer_js(); // Prints share-opener JS once per Page (CSS is in style.css)
	?>
	<div class="er-post-footer">

		<div class="er-pf-net">
			<span class="er-pf-net-label">Share This</span>
			<div class="er-pf-net-links">
				<?php
				foreach ( $networks as $label => $data ) :
					list( $href, $key ) = $data;
					if ( empty( $icons[ $key ] ) ) {
						continue;
					}
					?>
					<a class="er-pf-net-link"
					   href="#"
					   data-share="<?php echo esc_url( $href ); ?>"
					   role="button"
					   aria-label="Share on <?php echo esc_attr( $label ); ?>"
					   title="<?php echo esc_attr( $label ); ?>"><?php
						echo $icons[ $key ]; // inline SVG, trusted literal
					?></a>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( $prev || $next ) : ?>
		<nav class="er-postnav" aria-label="Post navigation">

			<div class="er-postnav-side er-postnav-prev">
				<?php if ( $prev ) :
					$prev_url = get_permalink( $prev ); ?>
					<div class="er-postnav-card">
						<?php echo er_post_footer_thumb( $prev, $prev_url ); ?>
						<span class="er-postnav-text">
							<span class="er-postnav-tag">Previous Post</span>
							<a class="er-postnav-title" href="<?php echo esc_url( $prev_url ); ?>"><?php echo esc_html( get_the_title( $prev ) ); ?></a>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<div class="er-postnav-side er-postnav-next">
				<?php if ( $next ) :
					$next_url = get_permalink( $next ); ?>
					<div class="er-postnav-card">
						<span class="er-postnav-text">
							<span class="er-postnav-tag">Next Post</span>
							<a class="er-postnav-title" href="<?php echo esc_url( $next_url ); ?>"><?php echo esc_html( get_the_title( $next ) ); ?></a>
						</span>
						<?php echo er_post_footer_thumb( $next, $next_url ); ?>
					</div>
				<?php endif; ?>
			</div>

		</nav>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
} );

// ---- 7b. Helper: round neighbour thumbnail (empty if no featured image) ----
function er_post_footer_thumb( $p, $url ) {
	if ( ! has_post_thumbnail( $p ) ) {
		return '';
	}
	return '<a class="er-postnav-thumb" href="' . esc_url( $url ) . '">'
		. get_the_post_thumbnail( $p, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) )
		. '</a>';
}

// ---- 7c. Helper: inline-SVG icon library (Simple Icons CC0 paths) ----
function er_post_footer_icons() {

	$wrap = function ( $path ) {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
	};

	return array(
		'bluesky'  => $wrap( 'M5.202 2.857C7.954 4.922 10.913 9.11 12 11.358c1.087-2.247 4.046-6.436 6.798-8.501C20.783 1.366 24 .213 24 3.883c0 .732-.42 6.156-.667 7.037-.856 3.061-3.978 3.842-6.755 3.37 4.854.826 6.089 3.562 3.422 6.299-5.065 5.196-7.28-1.304-7.847-2.97-.104-.305-.152-.448-.153-.327 0-.121-.05.022-.153.327-.568 1.666-2.782 8.166-7.847 2.97-2.667-2.737-1.432-5.473 3.422-6.3-2.777.473-5.899-.308-6.755-3.369C.42 10.04 0 4.615 0 3.883c0-3.67 3.217-2.517 5.202-1.026' ),
		'facebook' => $wrap( 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z' ),
		'line'     => $wrap( 'M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314' ),
		'linkedin' => $wrap( 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z' ),
		'telegram' => $wrap( 'M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z' ),
		'threads'  => $wrap( 'M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.783 3.631 2.698 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.79-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.964-.065-1.19.408-2.285 1.33-3.082.88-.76 2.119-1.207 3.583-1.291a13.853 13.853 0 0 1 3.02.142c-.126-.742-.375-1.332-.75-1.757-.513-.586-1.308-.883-2.359-.89h-.029c-.844 0-1.992.232-2.721 1.32L7.734 7.847c.98-1.454 2.568-2.256 4.478-2.256h.044c3.194.02 5.097 1.975 5.287 5.388.108.046.216.094.321.142 1.49.7 2.58 1.761 3.154 3.07.797 1.82.871 4.79-1.548 7.158-1.85 1.81-4.094 2.628-7.277 2.65Zm1.003-11.69c-.242 0-.487.007-.739.021-1.836.103-2.98.946-2.916 2.143.067 1.256 1.452 1.839 2.784 1.767 1.224-.065 2.818-.543 3.086-3.71a10.5 10.5 0 0 0-2.215-.221z' ),
		'whatsapp' => $wrap( 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z' ),
		'email'    => $wrap( 'M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z' ),
	);
}

// ---- 7d. Helper: share-link opener JS (printed once via static guard) ----
function er_post_footer_js() {

	static $printed = false;
	if ( $printed ) {
		return '';
	}
	$printed = true;

	ob_start();
	?>
	<script>
	( function () {
		if ( window.erPostFooterShareBound ) { return; }
		window.erPostFooterShareBound = true;
		document.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( '.er-pf-net-link' );
			if ( ! link ) { return; }
			e.preventDefault();
			var url = link.getAttribute( 'data-share' );
			if ( ! url ) { return; }
			if ( url.indexOf( 'mailto:' ) === 0 ) {
				window.location.href = url;
			} else {
				window.open( url, '_blank', 'noopener' );
			}
		} );
	} )();
	</script>
	<?php
	return ob_get_clean();
}

// ======================================
// 8. HEADER / NAV
// ======================================

// THEME RELATED — the JS and render_block injection in this section depend on the
// core/navigation block DOM (.wp-block-navigation__container, __submenu-container,
// __responsive-container-content). These are CORE block classes, not theme-specific,
// so a block->block theme switch likely survives; a switch to a theme that builds its
// nav differently (or a classic theme) breaks submenu positioning + overlay injection.
// On switch: re-verify the nav selectors below against the new theme's menu markup.

// Header-scroll/submenu-positioning JS and the mobile-overlay Socials/Support
// injection. Bar styling itself is in §3 critical CSS; submenu CSS is in style.css.

// ---- 8a. Header scroll + top-level submenu positioning (own wp_footer closure) ----
// Adds .er-scrolled past 50px and opens top-level submenus flush with the header's
// bottom edge in both heights. Split into its own closure so all header JS lives here.
add_action( 'wp_footer', function() {
	?>
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			var shrunkHeight = 90;
			var normalHeight = 120;
			var transitionMs = 300;
			var wasShrunk    = false;
			var header       = document.querySelector(".er-header");
			if (!header) return;

			// Top-level submenus: The top <ul> has class __container; nested submenus have class __submenu-container, so this matches first-level only
			function topLevelSubmenus() {
				return document.querySelectorAll(".er-header .wp-block-navigation__container > .wp-block-navigation-item > .wp-block-navigation__submenu-container");
			}
			function currentHeight() {
				return (header.classList.contains("er-scrolled") || window.innerWidth <= 1050) ? shrunkHeight : normalHeight;
			}
			function positionSubmenus(headerHeight) {
				topLevelSubmenus().forEach(function(menu) {
					if (window.innerWidth <= 1050) { menu.style.top = ""; return; }
					var liTop = menu.parentElement.getBoundingClientRect().top;
					menu.style.top = (headerHeight - liTop) + "px";
				});
			}

			topLevelSubmenus().forEach(function(menu) {
				var li = menu.parentElement;
				li.addEventListener("mouseenter", function() { positionSubmenus(currentHeight()); });
				li.addEventListener("focusin",    function() { positionSubmenus(currentHeight()); });
			});

			positionSubmenus(currentHeight());
			window.addEventListener("resize", function() { positionSubmenus(currentHeight()); }, { passive: true });

			window.addEventListener("scroll", function() {
				var shrunk = window.scrollY > 50;
				if (shrunk === wasShrunk) return;
				wasShrunk = shrunk;
				header.classList.toggle("er-scrolled", shrunk);
				setTimeout(function() { positionSubmenus(shrunk ? shrunkHeight : normalHeight); }, transitionMs);
			}, { passive: true });
		});
	</script>
	<?php
}, 15 );

// ---- 8b. Mobile-overlay Socials/Support pair (injected into the nav overlay) ----
// Injected inside the nav's responsive-container-content, after the menu <ul> closes.
// Markup comes from [er_socials_support context="overlay"] — single source (see 8c).
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( ( $block['blockName'] ?? '' ) !== 'core/navigation' ) {
		return $block_content;
	}
	$pair = do_shortcode( '[er_socials_support context="overlay"]' );
	// The responsive-container-content holds the menu <ul>. Inject right after
	// that <ul> closes, before the container's own closing </div>.
	// Anchor: the content container has id="...-content". We insert before the
	// FIRST </div> that follows it.
	$marker = '__responsive-container-content"';
	$start  = strpos( $block_content, $marker );
	if ( $start === false ) {
		return $block_content;
	}
	// Find the closing </ul> of the menu after that container opens.
	$ul_close = strrpos( $block_content, '</ul>' );
	if ( $ul_close === false ) {
		return $block_content;
	}
	$insert_at = $ul_close + strlen( '</ul>' );
	return substr_replace( $block_content, $pair, $insert_at, 0 );
}, 10, 2 );

// ---- 8c. Socials + Support icons — [er_socials_support]; single source for header, footer, mobile overlay ----
// URLs + icon files defined once here; two skins differ only in wrapper/classes/icon size:
//   context="bar"     (default) → desktop header + footer  (.er-socials-support / .er-header-icon, 22px)
//   context="overlay"           → mobile hamburger overlay (.er-nav-overlay-extra, 20px)
add_shortcode( 'er_socials_support', function ( $atts ) {
	$atts = shortcode_atts( array( 'context' => 'bar' ), $atts, 'er_socials_support' );

	// Single source of truth: links + icons.
	$socials_url  = '/about-me/contact/';
	$socials_icon = '/wp-content/uploads/2025/12/users-solid-white.svg';
	$support_url  = '/this-site/';
	$support_icon = '/wp-content/uploads/2025/12/hand-holding-dollar-solid-full-white.svg';

	if ( $atts['context'] === 'overlay' ) {
		// Mobile overlay skin: .er-nav-overlay-extra, plain anchors, 20px icons.
		return '<div class="er-nav-overlay-extra">'
		     . '<a href="' . esc_url( $socials_url ) . '"><img src="' . esc_url( $socials_icon ) . '" alt="" width="20" height="20"><span>Socials</span></a>'
		     . '<a href="' . esc_url( $support_url ) . '"><img src="' . esc_url( $support_icon ) . '" alt="" width="20" height="20"><span>Support</span></a>'
		     . '</div>';
	}

	// Default bar skin: .er-socials-support, .er-header-icon anchors, 22px icons.
	return '<span class="er-socials-support">'
	     . '<a class="er-header-icon" href="' . esc_url( $socials_url ) . '"><img src="' . esc_url( $socials_icon ) . '" alt="" width="22" height="22"><span class="er-header-label">Socials</span></a>'
	     . '<a class="er-header-icon" href="' . esc_url( $support_url ) . '"><img src="' . esc_url( $support_icon ) . '" alt="" width="22" height="22"><span class="er-header-label">Support</span></a>'
	     . '</span>';
} );

// ======================================
// 9. FOOTER SCRIPTS  (JS)
// ======================================

// Deferred front-end JS: dark-mode toggle, card-reveal animation, infinite
// scroll, and <details>-open persistence across filter clicks.
// All non-critical CSS now lives in style.css / the CSS snippet — none here.

add_action( 'wp_footer', function() {
	?>
	<script>

		// ---- 9a. Dark-mode toggle: apply stored preference, sync the switch UI ----
		document.addEventListener('DOMContentLoaded', function() {
			var body             = document.body;
			var changeModeSwitch = document.getElementById('change-mode-switch');
			var changeModeButton = document.getElementById('change-mode-button');
			var visualToggle     = document.querySelector('#dark-mode-toggle-btn .toggle-visual');
			var statusEl         = document.getElementById('dark-mode-status');

			// Apply from localStorage
			try {
				if (localStorage.getItem('changeMode') === 'true') {
					body.classList.add('dark-mode');
				}
			} catch(e) { console.warn('Could not load dark mode preference'); }

			// Remove preload class (added in wp_head to prevent flash)
			document.documentElement.classList.remove('dark-mode-preload');

			// Sync toggle with current state
			var isDark = body.classList.contains('dark-mode');
			if (changeModeSwitch) { changeModeSwitch.checked = isDark; changeModeSwitch.setAttribute('aria-checked', isDark); }

			// Toggle function
			var changeMode = function() {
				var isDark = body.classList.toggle('dark-mode');
				if (changeModeSwitch) { changeModeSwitch.checked = isDark; changeModeSwitch.setAttribute('aria-checked', isDark); }
				if (changeModeButton) { changeModeButton.setAttribute('aria-checked', isDark); }
				if (statusEl) { statusEl.textContent = isDark ? 'Dark mode enabled' : 'Light mode enabled'; }
				try { localStorage.setItem('changeMode', isDark ? 'true' : 'false'); } catch(e) { console.warn('LocalStorage unavailable'); }
			};

			// Accessibility setup
			var addAccessibility = function(el) {
				if (!el) return;
				el.setAttribute('role', 'switch');
				el.setAttribute('aria-checked', body.classList.contains('dark-mode'));
				el.setAttribute('tabindex', '0');
				el.addEventListener('keydown', function(e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); changeMode(); } });
			};
			addAccessibility(changeModeSwitch);
			addAccessibility(changeModeButton);
			if (visualToggle) { visualToggle.setAttribute('aria-hidden', 'true'); }

			// Event listeners
			if (changeModeSwitch) { changeModeSwitch.addEventListener('change', changeMode); }
			if (changeModeButton) { changeModeButton.addEventListener('click', changeMode); }
			if (visualToggle) {
				visualToggle.addEventListener('click', function() {
					if (changeModeSwitch) { changeModeSwitch.checked = !changeModeSwitch.checked; }
					changeMode();
				});
			}
		});

		// ---- 9b. Infinite scroll: ajax config + card-reveal helpers ----
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

		// ---- 9c. On load: reveal initial cards + slider entrance animation ----
		document.addEventListener( 'DOMContentLoaded', function() {

			// Card Reveal Animation: Mark + animate initial server-rendered Cards
			const initialCards = Array.from( document.querySelectorAll( '.er-cpt-grid article' ) );
			if ( initialCards.length ) {
				initialCards.forEach( function( card ) { card.setAttribute( 'data-revealed', '1' ); } );
				revealCards( initialCards );
			}

			// Eric Slider: Entrance Animation for related posts and other sliders
			const sliderElements = document.querySelectorAll( '.slideshow-multiple-items-4' );
			if ( sliderElements.length ) {
				const observer = new IntersectionObserver( function( entries ) {
					entries.forEach( function( entry ) {
						if ( entry.isIntersecting ) {
							entry.target.classList.add( 'daneden-slideInUp' );
							observer.unobserve( entry.target );
						}
					} );
				}, { threshold: 0.1 } );
				sliderElements.forEach( function( el ) { observer.observe( el ); } );
			}

		} );

		// ---- 9d. Infinite scroll: observer that fetches & appends more cards ----
		// Infinite Scroll
		const grids = document.querySelectorAll( '.er-cpt-grid' );

		grids.forEach( function( grid ) {
			// Each grid emits its sentinel as the immediately-following sibling.
			const sentinel = grid.nextElementSibling;
			if ( ! sentinel || ! sentinel.classList.contains( 'er-load-more-sentinel' ) ) return;

			// Per-grid state — kept in this closure so multiple grids on one
			// page don't share loading/offset/hasMore.
			let loading  = false;
			let hasMore  = true;
			let offset   = parseInt( grid.dataset.offset );

			const postType  = grid.dataset.postType;
			const taxonomy  = grid.dataset.taxonomy;
			const limit     = parseInt( grid.dataset.limit );
			const orderby   = grid.dataset.orderby;
			const order     = grid.dataset.order;

			// Read active Filter from the grid (server-set: ?taxonomy on listing pages, the term on archives)
			const activeSlug = grid.dataset.activeSlug || '';

			// Fetch next batch and append — callable from the observer and the <details> open handler.
			function loadMore() {
				if ( loading || ! hasMore ) return;
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

				const searchTerm = grid.dataset.search || '';
				if ( searchTerm ) { formData.append( 'search', searchTerm ); }

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
						maybeLoad();
					} )
					.catch( function() { loading = false; } );
			}

			// Load if this grid's sentinel is within 200px of the viewport now.
			// A hidden sentinel (collapsed <details>) has a zero-size rect, so it's skipped.
			function maybeLoad() {
				if ( loading || ! hasMore ) return;
				const rect = sentinel.getBoundingClientRect();
				const inView = rect.top < ( window.innerHeight + 200 ) && rect.bottom > 0;
				const hasLayout = rect.width > 0 || rect.height > 0;
				if ( inView && hasLayout ) { loadMore(); }
			}

			const observer = new IntersectionObserver( function( entries ) {
				entries.forEach( function( entry ) {
					if ( entry.isIntersecting ) { loadMore(); }
				} );
			}, { rootMargin: '200px' } );

			observer.observe( sentinel );

			// If this grid sits inside a <details>, its sentinel has no layout
			// while collapsed, so the observer never fires. Re-check on open.
			const detailsParent = grid.closest( 'details' );
			if ( detailsParent ) {
				detailsParent.addEventListener( 'toggle', function() {
					if ( detailsParent.open ) { maybeLoad(); }
				} );
			}
		} );

		// ---- 9e. <details> persistence across filter-tab navigation ----
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
}, 15 ); // Load deferred Footer JS after theme defaults

// =============================================
// 10. HIDE UNUSED WOOCOMMERCE TEMPLATES + PARTS
// =============================================

// THEME RELATED — genuinely theme-specific: the $woo_parts / $woo_templates slug lists
// below are the WooCommerce templates+parts the PARENT THEME ships. A different theme
// ships a different set, so on a theme switch this list must be re-derived from the new
// theme's bundled Woo templates (or removed if the new theme ships none).
//
// The parent theme ships WooCommerce templates AND template parts. When WooCommerce is
// inactive, the parent unregisters its Woo patterns and filters some Woo templates,
// but leaves Woo template *parts* and several Woo *templates* cluttering the
// Site Editor. This hides the full Woo set from the editor lists when Woo is
// not active. Gated on the same class_exists check the parent theme uses, so everything
// returns automatically if WooCommerce is ever installed.

add_filter( 'get_block_templates', function ( $query_result, $query, $template_type ) {
    // Only act when WooCommerce is not active.
    if ( class_exists( 'WooCommerce' ) ) {
        return $query_result;
    }

    $woo_parts = array(
        'product-card',
        'simple-product-add-to-cart-with-options',
        'variable-product-add-to-cart-with-options',
    );

    $woo_templates = array(
        'archive-product',
        'single-product',
        'page-cart',
        'page-checkout',
        'order-confirmation',
        'product-search-results',
    );

    $hide = ( 'wp_template_part' === $template_type ) ? $woo_parts : $woo_templates;

    return array_filter(
        $query_result,
        function ( $template ) use ( $hide ) {
            return ! in_array( $template->slug, $hide, true );
        }
    );
}, 10, 3 );

// ======================================
// 11. TEMPLATE ROUTING
// ======================================
// THEME RELATED — routes my-quotes + my-traits singles to this child theme's
// templates/single-no-sidebar.html via one shared file instead of duplicate
// per-CPT templates. Coupled to the child theme's template folder + these CPT
// slugs; on theme switch it orphans (file gone: theme.json) but degrades safely.

function ollie_route_no_sidebar( $templates ) {
	if ( is_singular( array( 'my-quotes', 'my-traits' ) ) ) {
		array_unshift( $templates, 'single-no-sidebar' );
	}
	return $templates;
}
add_filter( 'single_template_hierarchy', 'ollie_route_no_sidebar' );
add_filter( 'single_template_hierarchy', 'ollie_route_no_sidebar' );
