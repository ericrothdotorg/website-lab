<?php
add_action('wp_head', function () {
    ?>
    <!-- Preconnect and DNS Prefetch -->
    <link rel="preconnect" href="https://www.clarity.ms" crossorigin>
    <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://secure.gravatar.com">
    <?php
});

add_action('wp_footer', function () {
    ?>
    <!-- Microsoft Clarity Tracking -->
    <script type="text/javascript">
        (function(c, l, a, r, i, t, y) {
            c[a] = c[a] || function() {
                (c[a].q = c[a].q || []).push(arguments);
            };
            t = l.createElement(r);
            t.async = 1;
            t.src = "https://www.clarity.ms/tag/" + i;
            y = l.getElementsByTagName(r)[0];
            y.parentNode.insertBefore(t, y);
        })(window, document, "clarity", "script", "eic7b2e9o1");
    </script>
    <!-- Google Tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-X88D2RT23H"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', 'G-X88D2RT23H');
    </script>
    <!-- Scroll Indicator (Optimized) -->
    <style>
        .scroll-indicator-container {width: 100%;}
        .scroll-indicator-bar {will-change: width; width: 0%; position: fixed; overflow: hidden; bottom: 0; height: 5px; background: #c53030; z-index: 99999;}
    </style>
    <script>
        function scrollIndicator() {
            requestAnimationFrame(() => {
                var winScroll = document.body.scrollTop || document.documentElement.scrollTop;
                var height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                var scrolled = (winScroll / height) * 100;
                document.getElementById("my_scroll_indicator").style.width = scrolled + "%";
            });
        }
        window.addEventListener("scroll", scrollIndicator);
    </script>
    <div class="scroll-indicator-container">
        <div class="scroll-indicator-bar" id="my_scroll_indicator"></div>
    </div>
    <!-- Get Current Year -->
    <script>
        const yearEl = document.getElementById("current-year");
        if (yearEl) yearEl.innerHTML = new Date().getFullYear();
    </script>
    <!-- Details Accordion (Optimized) -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const details = document.querySelectorAll("details.details-accordion");
        details.forEach((detail) => {
            detail.addEventListener("toggle", () => {
                if (detail.open) {
                    details.forEach((other) => {
                        if (other !== detail) other.removeAttribute("open");
                    });
                }
            });
            const summary = detail.querySelector("summary");
            if (summary) {
                summary.setAttribute("tabindex", "0");
                summary.addEventListener("keydown", (e) => {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        detail.open = !detail.open;
                    }
                });
            }
        });
    });
    </script>
    <!-- Add Dan Eden's Animation to Blocksy's Flexy Container -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll('.flexy-container').forEach(el => {
        el.classList.add('daneden-slideInUp');
        });
    });
    </script>
    <!-- Instant Page: Smart Preloading for Navigation -->
    <script src="https://instant.page/5.2.0" type="module" integrity="sha384-jnZyxPjiipYXnSU0ygqeac2q7CVYMbh84q0uHVRRxEtvFPiQYbXWUorga2aqZJ0z"></script>
    <?php
}, 10, 1);

/* ✅ ABOUT HEADER IMAGES & LCP Issues */

add_action('template_redirect', function () {
    ob_start(function ($html) {
        $count = 0;
        $html = preg_replace_callback('/<img[^>]+>/i', function ($imgMatch) use (&$count) {
            $img = $imgMatch[0];
            if ($count === 0 && preg_match('/width=["\'](\d{4,})["\']/', $img, $widthMatch)) {
                $count++;
                $img = preg_replace('/\sloading=["\']lazy["\']/', '', $img);
                if (strpos($img, 'fetchpriority') === false) {
                    $img = str_replace('<img', '<img fetchpriority="high"', $img);
                }
                if (strpos($img, 'sizes=') !== false) {
                    $img = preg_replace('/sizes=["\'][^"\']*["\']/', 'sizes="(max-width: 600px) 100vw, (max-width: 1024px) 80vw, 1000px"', $img);
                } else {
                    $img = str_replace('<img', '<img sizes="(max-width: 600px) 100vw, (max-width: 1024px) 80vw, 1000px"', $img);
                }
            }
            return $img;
        }, $html);
        if (preg_match('/<img[^>]+width=["\'](\d{4,})["\'][^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $image_url = $match[2];
            $preload = '<link rel="preload" as="image" href="' . esc_url($image_url) . '" fetchpriority="high">';
            $html = preg_replace('/<head>/', '<head>' . $preload, $html, 1);
        }
        return $html;
    });
});

/* ✅ AUTOMATICALLY ADD ALT TEXT TO IMGs */

add_action('init', function() {
    add_filter('wp_get_attachment_image', function( $html, $attachment_id ) {
        $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        $image_title = get_the_title( $attachment_id );
        $image_src = wp_get_attachment_url( $attachment_id );
        $is_decorative = preg_match('/(divider|icon|bg|decor|spacer)/i', $image_src);
        if ( $is_decorative ) {
            $alt_text = '';
        } else {
            if ( empty( $alt_text ) ) {
                $alt_text = $image_title;
            }
            if ( strpos( $html, '<a' ) !== false && strpos( $html, $alt_text ) !== false ) {
                $alt_text = '';
            }
            $alt_text = esc_attr( wp_strip_all_tags( $alt_text ) );
            $alt_text = mb_substr( $alt_text, 0, 100 );
        }
        return preg_replace('/alt=["\'](.*?)["\']/', 'alt="' . $alt_text . '"', $html);
    }, 10, 2 );
});

/* ✅ AUTOMATICALLY ADD TITLE ATTRIBUTE TO LINKS 4 NEW TABS */

add_filter('the_content', function($content) {
    return preg_replace_callback(
        '/<a\s+([^>]*?)target="_blank"([^>]*)>(.*?)<\/a>/i',
        function ($matches) {
            // Skip if title already exists
            if (strpos($matches[1] . $matches[2], 'title=') !== false) return $matches[0];
            // Inject title attribute
            return str_replace(
                '<a ',
                '<a title="Opens in a new tab" ',
                $matches[0]
            );
        },
        $content
    );
});

/* ✅ == THEME RELATED (Blocksy) == */

// Disable Google Fonts in Blocksy Theme
add_filter('blocksy:typography:google:use-remote', '__return_false');
add_filter('blocksy_typography_font_sources', function ($sources) {
    unset($sources['google']);
    return $sources;
});
// Enqueue Flexy Styles (for Blocksy Post Slider without WooCommerce)
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('ct-flexy-styles');
});
// Add Shortcode Capability for Content Blocks
add_shortcode('blocksy_content_block', function($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);
    if (!$atts['id']) return '';
    $post = get_post($atts['id']);
    return $post ? apply_filters('the_content', $post->post_content) : '';
});

/* ✅ == ABOUT SHORTCODES == */

// Enable Shortcodes to run across Site
add_filter('widget_text', 'do_shortcode');
// Frontend: Create and display reusable blocks
function ericroth_get_block_content($id) {
    $content_post = get_post($id);
    return apply_filters('the_content', $content_post->post_content);
}
function ericroth_shortcode($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);
    return ericroth_get_block_content($atts['id']);
}
add_shortcode('reusable', 'ericroth_shortcode');
// Frontend: Display total Number of Posts
function total_posts($post_type) {
    return wp_count_posts($post_type)->publish;
}
function register_shortcodes() {
    $post_types = ['page', 'post', 'my-interests', 'my-traits'];
    foreach ($post_types as $type) {
        add_shortcode('total_' . str_replace('-', '_', $type), function() use ($type) {
            return total_posts($type);
        });
    }
}
if (!is_admin()) {
add_action('init', 'register_shortcodes');
}

/* ✅ == REDIRECT (Optimized for Efficiency) == */

add_action('init', function() {
    $redirects = [
        '/contact-me' => '/about-me/contact/',
        '/my-background' => '/professional/my-background/',
        '/my-traits/' => '/professional/my-background/my-traits/',
        '/my-blog/' => '/personal/my-blog/',
        '/my-interests/' => '/personal/my-interests/',
    ];
    $uri = trailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (isset($redirects[$uri])) {
        wp_redirect($redirects[$uri], 301);
        exit;
    }
});
?>
