<?php
// Enqueue Slick Slider Assets from local Files
add_action('wp_enqueue_scripts', function () {
    $theme_uri = get_stylesheet_directory_uri();
	wp_enqueue_style('slick-css', home_url('/my-assets/slick-slider/slick.css'), [], '1.9.0');
	wp_enqueue_style('slick-theme-css', home_url('/my-assets/slick-slider/slick-theme.css'), [], '1.9.0');
	wp_enqueue_script('slick-js', home_url('/my-assets/slick-slider/slick.js'), ['jquery'], '1.9.0', true);
}, 20);

// Add Defer Attribute to Slick.js to prevent Render Blocking
add_filter('script_loader_tag', function($tag, $handle) {
    if ('slick-js' === $handle) {
        return str_replace(' src', ' defer src', $tag);
    }
    return $tag;
}, 10, 2);

// Ignore Slider Images in LiteSpeed Cache – Let Slick's LazyLoad: 'ondemand' handle them
add_filter('litespeed_optimize_html_excluded_selectors', function($excludes) {
    $excludes[] = '.slick-slider img';
    return $excludes;
});

// Initialize Slick Sliders and add custom Styles in the Footer
add_action('wp_footer', function () {
    ?>
    <style>
        /* Navigation */
        .slick-slider img {margin: 0 auto;}
        .slick-next:before, .slick-prev:before {font-size: 35px; color: #afc2cf;}
        .slick-next, .slick-prev {position: absolute; top: -35px;}
        .slick-next {right: 20px; left: auto;}
        .slick-prev {right: 70px; left: auto;}
        .slick-dots li button:before {font-size: 15px; margin-top: 15px; opacity: 0.5; color: #afc2cf;}
        .slick-dots li.slick-active button:before {opacity: 1; color: #afc2cf;}
		.slick-autoplay-toggle-button {display: none;}

        /* Style Height Transition*/
        .slideshow-single-item {transition: height 0.4s ease;}

        /* Prevent Flash before Slick initializes */
        .slideshow-single-item,
        .slideshow-single-item-no-dots,
        .slideshow-multiple-items,
        .slideshow-multiple-items-vertical,
        .slideshow-multiple-items-center-mode {visibility: hidden;}
        .slick-initialized {visibility: visible;}

        /* Optimize Slider Animations */
        .slick-track {will-change: transform;}

        /* Style Slideshows with multiple Items*/
        .slideshow-multiple-items .slick-list {margin: 0 -12.5px;}
        .slideshow-multiple-items .slick-slide {margin: 0 12.5px;}
        @media (max-width: 600px) {
            .slideshow-multiple-items .slick-list {margin: 0 -7.5px;}
            .slideshow-multiple-items .slick-slide {margin: 0 7.5px;}
        }
        @media (min-width: 600px) and (max-width: 992px) {
            .slideshow-multiple-items .slick-list {margin: 0 -10px;}
            .slideshow-multiple-items .slick-slide {margin: 0 10px;}
        }
        .slideshow-multiple-items-vertical .slick-list {margin: -10px 0;}
        .slideshow-multiple-items-vertical .slick-slide {margin: 10px 0;}
        .slideshow-multiple-items-center-mode img {padding: 0 0.75% 0 0.75%;}

        /* Style Slideshows with WP Columns */
        .slideshow-single-item .wp-block-columns {align-items: center;}
		.dummy-columns {display: none !important;}

        /* Style Slideshows with Layers */
        .layer-container {position: relative; margin: 0 auto;}
		.layer-content-procurement-consulting h5 {text-align: justify;}
		.layer-content-procurement-consulting p {text-align: justify; padding-top: 5px;}
		.layer-content-procurement-consulting .emphasized-design-red {padding-top: 15px;}
        .layer-content-industries-served {color: #FFFFFF; padding: 0 0.75%; font-size: clamp(1.125rem, 3vw, 1.5rem);}
		.layer-content-industries-served > div {height: 150px; display: flex; justify-content: center; align-items: center; padding: 0 25px;}
		.layer-content-industries-served p {margin: 0; text-align: center;}
		.layer-content-ninja-services {color: #FFFFFF; padding: 0 0.75%; font-size: clamp(1rem, 5vw, 3rem);}
		.layer-content-ninja-services > div {height: 150px; display: flex; justify-content: center; align-items: center; padding: 0 25px;}
		.layer-content-ninja-services p {margin: 0; text-align: center;}

		/* Prevent hidden Slides from being focusable */
		/* Extra Layer of Protection (CSS-based) - Main Fix is in Site_Essentials via MutationObserver */
		.slick-slide[aria-hidden="true"] {pointer-events: none;}
		.slick-slide[aria-hidden="true"] a {tabindex: -1;}
    </style>

    <script>
    (function() {
        function initSlick() {
            if (typeof jQuery === 'undefined' || typeof jQuery.fn.slick === 'undefined') {
                return;
            }
            var $ = jQuery;
			
            // Common Config to reduce Repetition
            var baseConfig = {
                lazyLoad: 'ondemand',
                autoplay: true,
                autoplaySpeed: 2000,
                infinite: true,
                swipeToSlide: true,
				accessibility: true,
				focusOnSelect: false
            };
			
			// Config for Single Items
            $('.slideshow-single-item').slick($.extend({}, baseConfig, {
                arrows: false,
                dots: true,
                fade: true,
                adaptiveHeight: true,
                slidesToShow: 1,
                slidesToScroll: 1
            }));
			
			// Config for Single Items without Dots
            $('.slideshow-single-item-no-dots').slick($.extend({}, baseConfig, {
                arrows: false,
                dots: false,
                fade: false,
                adaptiveHeight: true,
                slidesToShow: 1,
                slidesToScroll: 1
            }));
			
			// Config for Multiple Items (horizontal)
            $('.slideshow-multiple-items').slick($.extend({}, baseConfig, {
                arrows: true,
                dots: false,
                fade: false,
                adaptiveHeight: false,
                slidesToShow: 2,
                slidesToScroll: 1,
                mobileFirst: true,
                responsive: [
                    { breakpoint: 350, settings: { slidesToShow: 2 } },
                    { breakpoint: 768, settings: { slidesToShow: 3 } },
                    { breakpoint: 992, settings: { slidesToShow: 4 } }
                ]
            }));
			
			// Config for Multiple Items (vertical)
            $('.slideshow-multiple-items-vertical').slick($.extend({}, baseConfig, {
                arrows: false,
                dots: false,
                fade: false,
                adaptiveHeight: false,
                vertical: true,
                verticalSwiping: true,
                slidesToShow: 3,
                slidesToScroll: 1
            }));
			
			// Config for Multiple Items (center mode)
            $('.slideshow-multiple-items-center-mode').slick($.extend({}, baseConfig, {
                arrows: false,
                dots: true,
                fade: false,
                adaptiveHeight: false,
                slidesToShow: 1,
                slidesToScroll: 1,
                centerMode: true,
                centerPadding: '0px',
                mobileFirst: true,
                responsive: [
                    { breakpoint: 350, settings: { centerPadding: '0px' } },
                    { breakpoint: 768, settings: { centerPadding: '75px' } },
                    { breakpoint: 992, settings: { centerPadding: '175px' } }
                ]
            }));
			
        }
        // Use requestIdleCallback for non-critical Initialization
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                if ('requestIdleCallback' in window) {
                    requestIdleCallback(initSlick, { timeout: 500 });
                } else {
                    setTimeout(initSlick, 200);
                }
            });
        } else {
            if ('requestIdleCallback' in window) {
                requestIdleCallback(initSlick, { timeout: 500 });
            } else {
                setTimeout(initSlick, 200);
            }
        }
    })();
    </script>
    <?php
}, 100);
