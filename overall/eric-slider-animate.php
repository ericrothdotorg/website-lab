<?php
defined('ABSPATH') || exit;

// ======================================
// ERIC SLIDER & ANIMATE: BASICS
// ======================================

add_action('wp_enqueue_scripts', function () {

    // Animate.css
    wp_enqueue_style('animate-css', home_url('/my-assets/animate.min.css'), [], '4.1.1');

    // Eric Slider
    wp_enqueue_style('eric-slider-css', home_url('/my-assets/eric-slider/eric-slider-v1.14.0.css'), [], '1.14.0');
    wp_enqueue_script('eric-slider-js', home_url('/my-assets/eric-slider/eric-slider-v2.25.0.js'), [], '2.25.0', true);

}, 20);

// Ignore Slider Images in LiteSpeed Cache – Let Eric Slider handle them
add_filter('litespeed_optimize_html_excluded_selectors', function($excludes) {
    $excludes[] = '.eric-slider img';
    return $excludes;
});

// ======================================
// ERIC SLIDER: Criticals in Head
// ======================================

add_action('wp_head', function () {
    ?>
    <style>
        .slideshow-single-item,
        .slideshow-single-item-no-dots,
        .slideshow-multiple-items,
        .slideshow-multiple-items-3,
        .slideshow-multiple-items-4,
        .slideshow-multiple-items-vertical,
        .slideshow-multiple-items-center-mode,
        .slideshow-quotes {visibility: hidden;}
        .eric-slider-initialized {visibility: visible;}
    </style>
    <?php
}, 6);

// ======================================
// ERIC SLIDER & ANIMATE: Styles & Script
// ======================================

add_action('wp_footer', function () {
    ?>
    <style>
        /* Navigation */
        @media (min-width: 782px) {.eric-slider-controls {position: absolute; top: -50px; right: 20px;}}
        @media (max-width: 781px) {
            .wp-block-column .eric-slider-controls {position: static; top: auto; right: auto;}
            .eric-slider-controls {position: absolute; top: -50px; right: 20px;}
        }
        .eric-slider-dots li button:before {font-size: 48px; opacity: 0.5; color: var(--color-9);}
        .eric-slider-dots li.eric-slider-active button:before {opacity: 1; color: var(--color-9);}

        /* Style Height Transition */
        .slideshow-single-item {transition: height 0.4s ease;}

        /* Style Slideshows with single Item */
        .slideshow-single-item img {border-radius: 25px;}
        .slideshow-single-item .listing-item img {border-radius: 25px 25px 0 0;}

        /* Style Slideshows with multiple Items */
        .slideshow-multiple-items-3 .eric-slider-list,
        .slideshow-multiple-items-4 .eric-slider-list {margin: 0 -12.5px;}
        .slideshow-multiple-items-3 .eric-slider-slide,
        .slideshow-multiple-items-4 .eric-slider-slide {margin: 0 12.5px;}
        @media (max-width: 600px) {
            .slideshow-multiple-items-3 .eric-slider-list,
            .slideshow-multiple-items-4 .eric-slider-list {margin: 0 -7.5px;}
            .slideshow-multiple-items-3 .eric-slider-slide,
            .slideshow-multiple-items-4 .eric-slider-slide {margin: 0 7.5px;}
        }
        @media (min-width: 600px) and (max-width: 992px) {
            .slideshow-multiple-items-3 .eric-slider-list,
            .slideshow-multiple-items-4 .eric-slider-list {margin: 0 -10px;}
            .slideshow-multiple-items-3 .eric-slider-slide,
            .slideshow-multiple-items-4 .eric-slider-slide {margin: 0 10px;}
        }
        .slideshow-multiple-items-vertical .eric-slider-list {margin: -10px 0;}
        .slideshow-multiple-items-vertical .eric-slider-slide {margin: 10px 0;}
        .slideshow-multiple-items-center-mode img {padding: 0 0.75%;}

        /* Style Slideshows with WP Columns */
        .slideshow-single-item .wp-block-columns {align-items: center;}

        /* Style Slideshows with Layers */
        .layer-container {position: relative; margin: 0 auto;}
        .layer-content-procurement-consulting {margin-right: 10px;}
        .layer-content-procurement-consulting h5 {text-align: justify;}
        .layer-content-procurement-consulting p {text-align: justify; padding-top: 5px;}
        .layer-content-procurement-consulting .emphasized-design-red {padding-top: 15px;}
        .layer-content-industries-served {color: var(--color-8); padding: 0 0.75%; font-size: clamp(1.125rem, 3vw, 1.5rem);}
        .layer-content-industries-served > div {height: 150px; display: flex; justify-content: center; align-items: center; padding: 0 25px;}
        .layer-content-industries-served p {margin: 0; text-align: center;}
        .layer-content-ninja-services {color: var(--color-8); padding: 0 0.75%; font-size: clamp(1rem, 5vw, 3rem);}
        .layer-content-ninja-services > div {height: 150px; display: flex; justify-content: center; align-items: center; padding: 0 25px;}
        .layer-content-ninja-services p {margin: 0; text-align: center;}
    </style>

    <!-- ERIC SLIDER & ANIMATE: SCRIPT -->

    <script>
        (function() {
            // Animate.css Initialization
            function initAnimateCSS() {
                var animationClasses = [
                    '.daneden-fadeIn',
                    '.daneden-fadeInUp',
                    '.daneden-fadeInDown',
                    '.daneden-zoomIn',
                    '.daneden-zoomOut',
                    '.daneden-slideInLeft',
                    '.daneden-slideInRight',
                    '.daneden-slideInUp',
                    '.daneden-slideInDown'
                ];

                // Use IntersectionObserver for better Performance
                if ('IntersectionObserver' in window) {
                    var observer = new IntersectionObserver(function(entries, observer) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                var elem = entry.target;
                                animationClasses.forEach(function(className) {
                                    if (elem.classList.contains(className.replace('.', ''))) {
                                        var animation = className.replace('.daneden-', '');
                                        elem.classList.add('animate__animated', 'animate__' + animation);
                                    }
                                });
                                observer.unobserve(elem);
                            }
                        });
                    }, {
                        rootMargin: '0px 0px -10% 0px'
                    });

                    animationClasses.forEach(function(className) {
                        document.querySelectorAll(className).forEach(function(elem) {
                            observer.observe(elem);
                        });
                    });
                } else {
                    // Fallback for older Browsers
                    function isScrolledIntoView(elem) {
                        var rect = elem.getBoundingClientRect();
                        var viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);
                        return !(rect.bottom < 0 || rect.top - viewHeight >= 0);
                    }
                    function addAnimationClass(className) {
                        document.querySelectorAll(className).forEach(function(elem) {
                            if (!elem.classList.contains('animate__animated') && isScrolledIntoView(elem)) {
                                var animation = className.replace('.daneden-', '');
                                elem.classList.add('animate__animated', 'animate__' + animation);
                            }
                        });
                    }
                    function onScroll() {
                        animationClasses.forEach(addAnimationClass);
                    }
                    function throttle(fn, limit) {
                        let waiting = false;
                        return function() {
                            if (!waiting) {
                                fn();
                                waiting = true;
                                setTimeout(() => waiting = false, limit);
                            }
                        };
                    }
                    window.addEventListener('scroll', throttle(onScroll, 150));
                    onScroll();
                }
            }

            // Eric Slider Initialization
            function initEricSlider() {
                if (typeof EricSlider === 'undefined') {
                    setTimeout(initEricSlider, 100);
                    return;
                }

                // Single Item with Fade
                document.querySelectorAll('.slideshow-single-item').forEach(function(el) {
                    new EricSlider(el, {
                        label: 'Images Slideshow',
                        autoplay: true,
                        autoplaySpeed: 2000,
                        fade: true,
                        adaptiveHeight: true,
                        dots: true,
                        infinite: true,
                        slidesToShow: 1,
                        slidesToScroll: 1
                    });
                });

                // Single Item No Dots
                document.querySelectorAll('.slideshow-single-item-no-dots').forEach(function(el) {
                    new EricSlider(el, {
                        label: 'Images Slideshow',
                        autoplay: true,
                        autoplaySpeed: 2000,
                        fade: false,
                        adaptiveHeight: true,
                        dots: false,
                        infinite: true,
                        slidesToShow: 1,
                        slidesToScroll: 1
                    });
                });

                // Multiple Items 3
                document.querySelectorAll('.slideshow-multiple-items-3').forEach(function(el) {
                    new EricSlider(el, {
                        label: 'Posts Slideshow',
                        controls: true,
                        autoplay: true,
                        autoplaySpeed: 2000,
                        fade: false,
                        adaptiveHeight: false,
                        dots: true,
                        infinite: true,
                        slidesToShow: 3,
                        slidesToScroll: 1,
                        responsive: [
                            { breakpoint: 992, settings: { slidesToShow: 2 } },
                            { breakpoint: 768, settings: { slidesToShow: 1 } }
                        ]
                    });
                });

                // Multiple Items 4
                document.querySelectorAll('.slideshow-multiple-items-4').forEach(function(el) {
                    new EricSlider(el, {
                        label: 'Posts Slideshow',
                        controls: true,
                        autoplay: true,
                        autoplaySpeed: 2000,
                        fade: false,
                        adaptiveHeight: false,
                        dots: true,
                        infinite: true,
                        slidesToShow: 4,
                        slidesToScroll: 1,
                        responsive: [
                            { breakpoint: 992, settings: { slidesToShow: 3 } },
                            { breakpoint: 768, settings: { slidesToShow: 2 } },
                            { breakpoint: 350, settings: { slidesToShow: 1 } }
                        ]
                    });
                });

                // Multiple Items Vertical
                document.querySelectorAll('.slideshow-multiple-items-vertical').forEach(function(el) {
                    new EricSlider(el, {
                        label: 'Posts Slideshow',
                        autoplay: true,
                        autoplaySpeed: 2000,
                        fade: false,
                        adaptiveHeight: false,
                        dots: false,
                        infinite: true,
                        vertical: true,
                        slidesToShow: 3,
                        slidesToScroll: 1
                    });
                });

                // Center Mode
                document.querySelectorAll('.slideshow-multiple-items-center-mode').forEach(function(el) {
                    new EricSlider(el, {
                        label: 'Services Slideshow',
                        autoplay: true,
                        autoplaySpeed: 2000,
                        fade: false,
                        adaptiveHeight: false,
                        dots: true,
                        infinite: true,
                        centerMode: true,
                        centerPadding: '175px',
                        slidesToShow: 1,
                        slidesToScroll: 1,
                        responsive: [
                            { breakpoint: 992, settings: { centerPadding: '75px' } },
                            { breakpoint: 768, settings: { centerPadding: '0px' } }
                        ]
                    });
                });

                // My Quotes
                document.querySelectorAll('.slideshow-quotes').forEach(function(el) {
                    new EricSlider(el, {
                        label: 'Quotes Slideshow',
                        autoplay: true,
                        autoplaySpeed: 2000,
                        fade: true,
                        adaptiveHeight: true,
                        dots: true,
                        infinite: true,
                        pauseOnHover: true,
                        pauseOnFocus: true,
                        slidesToShow: 1,
                        slidesToScroll: 1
                    });
                });

            }

            // Eric Slider: Initialize immediately on DOMContentLoaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initEricSlider);
            } else {
                initEricSlider();
            }

            // Animate.css: Defer via requestIdleCallback as it is non-critical
            function scheduleAnimateCSS() {
                if ('requestIdleCallback' in window) {
                    requestIdleCallback(initAnimateCSS, { timeout: 500 });
                } else {
                    setTimeout(initAnimateCSS, 200);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', scheduleAnimateCSS);
            } else {
                scheduleAnimateCSS();
            }
        })();
    </script>
    <?php
}, 100);
