<?php
// ✅ Load jQuery from CDN if not already enqueued
if (!is_admin()) {
    add_action('wp_enqueue_scripts', function () {
        add_action('wp_head', function () {
            echo '<link rel="preconnect" href="https://code.jquery.com" crossorigin>';
        }, 0);
        if (!wp_script_is('jquery', 'enqueued')) {
            wp_deregister_script('jquery'); // Deregister built-in from WP
            wp_register_script(
                'jquery',
                'https://code.jquery.com/jquery-3.7.1.min.js',
                [],
                null,
                true
            );
            wp_enqueue_script('jquery');
        }
    }, 11);
}

// ✅ Enqueue Slick Slider assets after jQuery
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('slick-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.min.css');
    wp_enqueue_style('slick-theme-css', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick-theme.min.css');
    wp_enqueue_script('slick-js', 'https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.min.js', ['jquery'], null, true);
}, 20);

// ✅ Initialize Slick sliders and add custom styles in the footer
add_action('wp_footer', function () {
    ?>
    <script>
    setTimeout(() => {
        jQuery(document).ready(function($) {
            $('.slideshow-single-item').slick({
                lazyLoad: 'ondemand',
                autoplay: true,
                autoplaySpeed: 2000,
                infinite: true,
                arrows: false,
                dots: true,
                fade: false,
                adaptiveHeight: true,
                slidesToShow: 1,
                slidesToScroll: 1,
                swipeToSlide: true,
            });
            $('.slideshow-single-item-no-dots').slick({
                lazyLoad: 'ondemand',
                autoplay: true,
                autoplaySpeed: 2000,
                infinite: true,
                arrows: false,
                dots: false,
                fade: false,
                adaptiveHeight: true,
                slidesToShow: 1,
                slidesToScroll: 1,
                swipeToSlide: true,
            });
            $('.slideshow-multiple-items').slick({
                lazyLoad: 'ondemand',
                autoplay: true,
                autoplaySpeed: 2000,
                infinite: true,
                arrows: true,
                dots: false,
                fade: false,
                adaptiveHeight: false,
                slidesToShow: 4,
                slidesToScroll: 1,
                swipeToSlide: true,
                mobileFirst: true,
                responsive: [
                    { breakpoint: 1200, settings: { slidesToShow: 4 } },
                    { breakpoint: 992, settings: { slidesToShow: 3 } },
                    { breakpoint: 600, settings: { slidesToShow: 2 } }
                ]
            });
            $('.slideshow-multiple-items-vertical').slick({
                lazyLoad: 'ondemand',
                autoplay: true,
                autoplaySpeed: 2000,
                infinite: true,
                arrows: false,
                dots: false,
                fade: false,
                adaptiveHeight: false,
                vertical: true,
                verticalSwiping: true,
                slidesToShow: 3,
                slidesToScroll: 1,
                swipeToSlide: true,
            });
            $('.slideshow-multiple-items-center-mode').slick({
                lazyLoad: 'ondemand',
                autoplay: true,
                autoplaySpeed: 2000,
                infinite: true,
                arrows: false,
                dots: true,
                fade: false,
                adaptiveHeight: false,
                slidesToShow: 1,
                slidesToScroll: 1,
                swipeToSlide: true,
                centerMode: true,
                centerPadding: '175px',
                mobileFirst: true,
                responsive: [
                    { breakpoint: 1200, settings: { centerPadding: '125px' } },
                    { breakpoint: 992, settings: { centerPadding: '75px' } },
                    { breakpoint: 600, settings: { centerPadding: '0px' } }
                ]
            });
        });
    }, 200);
    </script>
    <style>
        .slick-slider img {margin: 0 auto;}
        .slick-next:before, .slick-prev:before {font-size: 35px; color: #afc2cf;}
        .slick-next, .slick-prev {position: absolute; top: -35px;}
        .slick-next {right: 20px; left: auto;}
        .slick-prev {right: 70px; left: auto;}
        .slick-dots li button:before {font-size: 15px; margin-top: 15px; opacity: 0.5; color: #afc2cf;}
        .slick-dots li.slick-active button:before {opacity: 1; color: #afc2cf;}
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
        #frontpage.slideshow-multiple-items-vertical .slick-slide {margin: 0px;}
        .slideshow-multiple-items-center-mode img {padding: 0 0.75% 0 0.75%;}
        .layer-container {position: relative; margin: 0 auto;}
        .layer-content-quotes-featured-image {
            position: absolute;
            margin: 0 25px;
            padding: 25px 25px 25px 0;
            background: rgba(0, 0, 0, 0.65);
            bottom: 25px;
        }
        @media (max-width: 600px) {
            .layer-content-quotes-featured-image {line-height: 1.05; bottom: 0px;}
            .layer-content-quotes-featured-image li {font-size: 75%;}
        }
        @media (min-width: 600px) and (max-width: 992px) {
            .layer-content-quotes-featured-image {line-height: 1.1; bottom: 0px;}
            .layer-content-quotes-featured-image li {font-size: 85%;}
        }
        .layer-content-quotes-featured-text {text-align: justify; color: #339966; font-style: italic;}
        .layer-content-procurement-consulting {margin-right: 7.5px;}
        .layer-content-industries-served {font-size: 150%; color: #ffffff; padding: 0 0.75%;}
    </style>
    <?php
}, 100);
?>
