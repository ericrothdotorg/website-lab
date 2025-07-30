<?php

// Remove query strings from static resources
function remove_cssjs_ver($src) {
    if (strpos($src, '?ver=')) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('style_loader_src', 'remove_cssjs_ver', 10, 2);
add_filter('script_loader_src', 'remove_cssjs_ver', 10, 2);

// Enable Dashicons on frontend
function load_dashicons_front_end() {
    wp_enqueue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'load_dashicons_front_end');

// Disable emojis
function disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', 'disable_emojis_tinymce');
    add_filter('wp_resource_hints', 'disable_emojis_remove_dns_prefetch', 10, 2);
}
add_action('init', 'disable_emojis');

function disable_emojis_tinymce($plugins) {
    return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
}
function disable_emojis_remove_dns_prefetch($urls, $relation_type) {
    if ($relation_type === 'dns-prefetch') {
        $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
        $urls = array_diff($urls, [$emoji_svg_url]);
    }
    return $urls;
}

// Preconnect & DNS Prefetch
add_action('wp_head', function () {
    echo '
    <link rel="preconnect" href="https://www.clarity.ms" crossorigin>
    <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://secure.gravatar.com">
    ';
});

// Scripts & UI Features in Footer
add_action('wp_footer', function () {
    ?>
    <!-- Microsoft Clarity -->
    <script>
        (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "eic7b2e9o1");
    </script>
    <!-- Google Tag Manager -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-X88D2RT23H"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-X88D2RT23H');
    </script>
    <!-- Scroll Indicator -->
    <style>
        .scroll-indicator-container { width: 100%; }
        .scroll-indicator-bar {
            will-change: width; width: 0%; position: fixed; bottom: 0; height: 5px;
            background: #c53030; z-index: 99999;
        }
    </style>
    <script>
        function scrollIndicator() {
            requestAnimationFrame(() => {
                const scroll = document.documentElement.scrollTop || document.body.scrollTop;
                const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                document.getElementById("my_scroll_indicator").style.width = (scroll / height) * 100 + "%";
            });
        }
        window.addEventListener("scroll", scrollIndicator);
    </script>
    <div class="scroll-indicator-container">
        <div class="scroll-indicator-bar" id="my_scroll_indicator"></div>
    </div>
    <!-- Auto Insert Current Year -->
    <script>
        const yearEl = document.getElementById("current-year");
        if (yearEl) yearEl.textContent = new Date().getFullYear();
    </script>
    <!-- Accordion Toggle -->
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
                        if (["Enter", " "].includes(e.key)) {
                            e.preventDefault();
                            detail.open = !detail.open;
                        }
                    });
                }
            });
        });
    </script>
    <!-- Flexy Animation (Blocksy) -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll('.flexy-container').forEach(el => {
                el.classList.add('daneden-slideInUp');
            });
        });
    </script>
    <!-- Instant Page -->
    <script src="https://instant.page/5.2.0" type="module" integrity="sha384-jnZyxPjiipYXnSU0ygqeac2q7CVYMbh84q0uHVRRxEtvFPiQYbXWUorga2aqZJ0z"></script>
    <?php
}, 10);

// Improve Largest Contentful Paint (LCP)
add_action('template_redirect', function () {
    ob_start(function ($html) {
        $count = 0;
        $html = preg_replace_callback('/<img[^>]+>/i', function ($imgMatch) use (&$count) {
            $img = $imgMatch[0];
            if ($count === 0 && preg_match('/width=["\'](\d{4,})["\']/', $img)) {
                $count++;
                $img = preg_replace('/\sloading=["\']lazy["\']/', '', $img);
                $img = str_replace('<img', '<img fetchpriority="high"', $img);
                $img = preg_replace('/sizes=["\'][^"\']*["\']/', 'sizes="(max-width:600px)100vw,(max-width:1024px)80vw,1000px"', $img);
                if (!strpos($img, 'sizes=')) {
                    $img = str_replace('<img', '<img sizes="(max-width:600px)100vw,(max-width:1024px)80vw,1000px"', $img);
                }
            }
            return $img;
        }, $html);
        if (preg_match('/<img[^>]+width=["\'](\d{4,})["\'][^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $image_url = $match[2];
            $html = preg_replace('/<head>/', '<head><link rel="preload" as="image" href="' . esc_url($image_url) . '" fetchpriority="high">', $html, 1);
        }
        return $html;
    });
});

// Automatically add ALT Text to Images
add_action('init', function() {
    add_filter('wp_get_attachment_image', function($html, $attachment_id) {
        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $title = get_the_title($attachment_id);
        $src = wp_get_attachment_url($attachment_id);
        $is_decorative = preg_match('/(divider|icon|bg|decor|spacer)/i', $src);
        if ($is_decorative) {
            $alt = '';
        } elseif (empty($alt)) {
            $alt = $title;
        }
        if (strpos($html, '<a') !== false && strpos($html, $alt) !== false) {
            $alt = '';
        }
        $alt = esc_attr(mb_substr(wp_strip_all_tags($alt), 0, 100));
        return preg_replace('/alt=["\'](.*?)["\']/', 'alt="' . $alt . '"', $html);
    }, 10, 2);
});

// Automatically add title to <a> with target="_blank"
add_filter('the_content', function($content) {
    return preg_replace_callback(
        '/<a\s+([^>]*?)target="_blank"([^>]*)>(.*?)<\/a>/i',
        function ($matches) {
            if (strpos($matches[1] . $matches[2], 'title=') !== false) return $matches[0];
            return str_replace('<a ', '<a title="Opens in a new tab" ', $matches[0]);
        },
        $content
    );
});

// Blocksy – Disable Google Fonts
add_filter('blocksy:typography:google:use-remote', '__return_false');
add_filter('blocksy_typography_font_sources', function($sources) {
    unset($sources['google']);
    return $sources;
});

// Blocksy – Enqueue Flexy Styles
add_action('wp_enqueue_scripts', fn() => wp_enqueue_style('ct-flexy-styles'));

// Shortcodes: Retrieve IDs and return Content
add_shortcode('reusable', 'get_reusable_block');
add_shortcode('blocksy_content_block', 'get_reusable_block');
function get_reusable_block($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);
    $post = get_post($atts['id']);
    return $post ? apply_filters('the_content', $post->post_content) : '';
}

// Shortcodes: [total_post], [total_page], etc.
function total_posts($post_type) {
    return wp_count_posts($post_type)->publish;
}
function register_shortcodes() {
    foreach (['post', 'page', 'my-interests', 'my-traits'] as $type) {
        add_shortcode('total_' . str_replace('-', '_', $type), fn() => total_posts($type));
    }
}
if (!is_admin()) {
    add_action('init', 'register_shortcodes');
}

// Custom Redirects (SEO-friendly)
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
