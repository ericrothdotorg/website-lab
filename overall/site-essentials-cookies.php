<?php

// ======================================
// JQUERY & CORE SCRIPTS
// ======================================

// Replace WordPress jQuery with self-hosted version
if (!is_admin()) {
    add_action('wp_enqueue_scripts', function () {
        if (!wp_script_is('jquery', 'enqueued')) {
            wp_deregister_script('jquery');
            wp_register_script('jquery', home_url('/my-assets/jquery-3.7.1.min.js'), [], '3.7.1', true);
            wp_enqueue_script('jquery');
        }
    }, 11);
}

// ======================================
// PERFORMANCE OPTIMIZATIONS
// ======================================

// Remove query strings from static resources for better caching
add_filter('style_loader_src', fn($src) => remove_query_arg('ver', $src), 10, 2);
add_filter('script_loader_src', fn($src) => remove_query_arg('ver', $src), 10, 2);

// Disable WordPress emoji scripts and styles
add_action('init', function() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', fn($plugins) => is_array($plugins) ? array_diff($plugins, ['wpemoji']) : []);
    add_filter('wp_resource_hints', function($urls, $relation_type) {
        if ($relation_type === 'dns-prefetch') {
            $emoji_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
            $urls = array_diff($urls, [$emoji_url]);
        }
        return $urls;
    }, 10, 2);
});

// Optimize Largest Contentful Paint (LCP) - first image gets priority
add_action('template_redirect', function () {
    ob_start(function ($html) {
        $count = 0;
        // Add fetchpriority="high" to first large image
        $html = preg_replace_callback('/<img[^>]+>/i', function ($match) use (&$count) {
            $img = $match[0];
            if ($count === 0 && preg_match('/width=["\'](\d{3,})["\']/', $img)) {
                $count++;
                $img = preg_replace('/\sloading=["\']lazy["\']/', '', $img);
                $img = str_replace('<img', '<img fetchpriority="high"', $img);
                if (!strpos($img, 'sizes=')) {
                    $img = str_replace('<img', '<img sizes="(max-width: 600px)100vw,(max-width: 1024px)80vw,1000px"', $img);
                }
            }
            return $img;
        }, $html);
        
        // Preload first large image
        if (preg_match('/<img[^>]+width=["\'](\d{3,})["\'][^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $html = preg_replace('/<head>/', '<head><link rel="preload" as="image" href="' . esc_url($match[2]) . '" fetchpriority="high">', $html, 1);
        }
        return $html;
    });
});

// Set responsive image sizes (aligned with Blocksy breakpoints)
add_filter('wp_get_attachment_image_attributes', function ($attr) {
    $attr['sizes'] = '(max-width: 480px) 300px, (max-width: 768px) 768px, (max-width: 1024px) 1024px, 1536px';
    return $attr;
}, 10, 3);

// ======================================
// BLOCKSY THEME OPTIMIZATIONS
// ======================================

// Disable Google fonts (using local fonts instead)
add_filter('blocksy:typography:google:use-remote', '__return_false');
add_filter('blocksy_typography_font_sources', function($sources) {
    unset($sources['google']);
    return $sources;
});

// Enable Blocksy flexy animation styles
add_action('wp_enqueue_scripts', fn() => wp_enqueue_style('ct-flexy-styles'));

// Strip unused Blocksy extensions for faster loading
add_action('wp_enqueue_scripts', function() {
    $extensions = ['newsletter', 'woocommerce', 'trending', 'cookie-consent', 'local-google-fonts', 
                   'portfolio', 'shop-extra', 'advanced-menu', 'contact-form', 'popups', 'color-mode-switcher'];
    foreach ($extensions as $ext) {
        wp_dequeue_style("blocksy-ext-{$ext}");
        wp_deregister_style("blocksy-ext-{$ext}");
        wp_dequeue_script("blocksy-ext-{$ext}");
        wp_deregister_script("blocksy-ext-{$ext}");
    }
}, 100);

// ======================================
// FRONTEND ASSETS
// ======================================

// Enable dashicons on frontend (for icons in content)
add_action('wp_enqueue_scripts', fn() => wp_enqueue_style('dashicons'));

// Preconnect to external services for faster loading
add_action('wp_head', function () {
    echo '<link rel="preconnect" href="https://www.clarity.ms" crossorigin>
          <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
          <link rel="dns-prefetch" href="https://secure.gravatar.com">';
});

// ======================================
// SHORTCODES
// ======================================

// Enable shortcodes in widgets and blocks
add_filter('widget_text', 'do_shortcode');
add_filter('widget_block_content', 'do_shortcode');

// [reusable id="123"] - Display reusable content blocks
add_shortcode('reusable', 'get_reusable_block');
add_shortcode('blocksy_content_block', 'get_reusable_block');
function get_reusable_block($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);
    $post = get_post(intval($atts['id']));
    return $post ? apply_filters('the_content', $post->post_content) : '';
}

// [total_post], [total_page], etc. - Display post type counts
if (!is_admin()) {
    add_action('init', function() {
        foreach (['post', 'page', 'my-interests', 'my-traits'] as $type) {
            add_shortcode('total_' . str_replace('-', '_', $type), 
                fn() => wp_count_posts($type)->publish
            );
        }
    });
}

// [post_stats] - Comprehensive content statistics
add_shortcode('post_stats', function() {
    global $post;
    if (!$post) return '';
    
    $content = apply_filters('the_content', $post->post_content);
    $text = strip_tags(strip_shortcodes($content));
    
    // Calculate statistics
    $stats = [
        'words' => str_word_count($text),
        'minutes' => ceil(str_word_count($text) / 200),
        'chars' => strlen($text),
        'paragraphs' => substr_count($content, '</p>'),
        'images' => substr_count($content, '<img'),
        'videos' => preg_match_all('/<video[^>]*>/i', $content) + preg_match_all('/<iframe[^>]*(?:youtube\.com|vimeo\.com)[^>]*>/i', $content),
        'titles' => preg_match_all('/<h[1-6][^>]*>.*?<\/h[1-6]>/', $content)
    ];
    
    // Count links
    preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $matches);
    $site_url = home_url();
    $internal = $external = 0;
    foreach ($matches[1] ?? [] as $url) {
        (strpos($url, $site_url) === 0 || strpos($url, '/') === 0) ? $internal++ : $external++;
    }
    
    // Sentence analysis
    $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $sentence_count = count($sentences);
    $avg_words = $sentence_count > 0 ? round($stats['words'] / $sentence_count, 1) : 0;
    
    // Format output with thousands separators
    $fmt = fn($n) => number_format($n, 0, '.', "'");
    
    return '<div class="post-stats">'
         . '<span><strong>' . $fmt($stats['words']) . '</strong> Words</span> • '
         . '<span>Read Time: <strong>' . $stats['minutes'] . '</strong> Min.</span><br>'
         . '<span><strong>' . $fmt($stats['chars']) . '</strong> Characters</span> • '
         . '<span><strong>' . $fmt($stats['paragraphs']) . '</strong> Paragraphs</span><br>'
         . '<span><strong>' . $fmt($sentence_count) . '</strong> Sentences</span> • '
         . '<span><strong>' . $avg_words . '</strong> Words / Sentence</span><br>'
         . '<span><strong>' . $fmt($internal) . '</strong> Internal Links</span> • '
         . '<span><strong>' . $fmt($external) . '</strong> External Links</span><br>'
         . '<span><strong>' . $fmt($stats['titles']) . '</strong> Titles</span> • '
         . '<span><strong>' . $fmt($stats['images']) . '</strong> Images</span> • '
         . '<span><strong>' . $fmt($stats['videos']) . '</strong> Videos</span>'
         . '</div>';
});

// ======================================
// SEO & ACCESSIBILITY
// ======================================

// Auto-generate ALT text for images (or mark decorative images)
add_filter('wp_get_attachment_image', function($html, $attachment_id) {
    $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    $src = wp_get_attachment_url($attachment_id);
    
    // Mark decorative images with empty alt
    if (preg_match('/(divider|icon|bg|decor|spacer)/i', $src)) {
        $alt = '';
    } elseif (empty($alt)) {
        $alt = get_the_title($attachment_id);
    }
    
    // Remove alt if image is wrapped in link with same text
    if (strpos($html, '<a') !== false && strpos($html, $alt) !== false) {
        $alt = '';
    }
    
    $alt = esc_attr(mb_substr(wp_strip_all_tags($alt), 0, 100));
    return preg_replace('/alt=["\'](.*?)["\']/', 'alt="' . $alt . '"', $html);
}, 10, 2);

// Add helpful title to external links
add_filter('the_content', function($content) {
    return preg_replace_callback('/<a\s+([^>]*?)target="_blank"([^>]*)>(.*?)<\/a>/i', function ($m) {
        return strpos($m[1] . $m[2], 'title=') !== false ? $m[0] : str_replace('<a ', '<a title="Opens in a new tab" ', $m[0]);
    }, $content);
});

// Add visual indicator class to external links (style with CSS)
add_filter('the_content', function ($content) {
    $exclude = ['ericroth.org', 'ericroth-org', '1drv.ms', 'paypal.com', 'librarything.com',
                'themoviedb.org', 'facebook.com', 'github.com', 'linkedin.com', 'youtube.com',
                'patreon.com', 'bsky.app', 'bsky.social'];
    
    return preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', function ($m) use ($exclude) {
        $href = $m[1];
        $tag = $m[0];
        
        // Skip internal/special links
        if ($href[0] === '#' || $href[0] === '/' || strpos($href, 'tel:') === 0 || 
            stripos($href, 'javascript') !== false || strpos($href, '?cat=') !== false) {
            return $tag;
        }
        
        // Skip whitelisted domains
        foreach ($exclude as $domain) {
            if (strpos($href, $domain) !== false) return $tag;
        }
        
        // Skip WordPress UI classes
        if (preg_match('/class=["\'][^"\']*(wp-block-button__link|button|neli|page-numbers)[^"\']*["\']/', $tag)) {
            return $tag;
        }
        
        // Add external-link class
        return strpos($tag, 'class=') !== false 
            ? preg_replace('/class=(["\'])(.*?)\1/', 'class=$1$2 external-link$1', $tag)
            : str_replace('<a ', '<a class="external-link" ', $tag);
    }, $content);
});

// ======================================
// REDIRECTS
// ======================================

// SEO-friendly 301 redirects for old URLs
add_action('template_redirect', function() {
    $redirects = [
        '/contact-me/'    => '/about-me/contact/',
        '/services/'      => '/professional/',
        '/my-background/' => '/professional/my-background/',
        '/my-blog/'       => '/personal/my-blog/',
        '/my-interests/'  => '/personal/my-interests/',
    ];
    
    $uri = trailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (isset($redirects[$uri])) {
        wp_redirect($redirects[$uri], 301);
        exit;
    }
});

// ======================================
// FOOTER SCRIPTS & UI ENHANCEMENTS
// ======================================

add_action('wp_footer', function () {
    ?>
    
    <!-- Cookie Consent Banner -->
    <p id="cookie-notice" role="region" aria-live="polite" aria-label="Cookie notice" aria-hidden="true" style="visibility: hidden;">
        We serve <strong>cookies</strong> to enhance your browsing experience. Learn more in our 
        <a href="https://ericroth.org/this-site/site-policies/">Site Policies</a><br>
        <span style="display: block; text-align: center;">
            <button type="button" onclick="acceptCookie();" aria-label="Accept all cookies"><span>Accept</span></button>
            <button type="button" onclick="rejectCookie();" aria-label="Reject optional cookies" class="reject-btn"><span>Essentials</span></button>
        </span>
    </p>
    
    <style>
        #cookie-notice {text-align: justify; color: #fff; font-family: inherit; background: rgba(0,0,0,0.75); padding: 20px; position: fixed; bottom: 15px; left: 15px; width: 100%; max-width: 300px; border-radius: 5px; z-index: 10000; box-sizing: border-box}
        #cookie-notice button {font-weight: normal; color: #fff; background: #1e73be; border-radius: 5px; padding: 8px; margin-top: 15px; width: 48%; cursor: pointer; border: none; margin-left: 2%; display: inline-block}
        #cookie-notice button:first-child {margin-left: 0}
        #cookie-notice button:hover {background: #c53030}
        #cookie-notice button:hover span {display: none}
        #cookie-notice button:hover::before {content: "All"}
        #cookie-notice button.reject-btn {background: #262626}
        #cookie-notice button.reject-btn:hover {background: #262626}
        #cookie-notice button.reject-btn:hover::before {content: "Only"}
        #cookie-notice button:focus-visible {outline: 2px solid #fff; outline-offset: 2px}
        @media (max-width: 480px) {
            #cookie-notice {max-width: 100%; bottom: 0; left: 0; border-radius: 0}
            #cookie-notice button {width: 48%; font-size: 14px; padding: 8px 5px}
        }
    </style>

    <!-- Analytics & Cookie Consent Logic -->
    <script>
        function acceptCookie() {
            const isSecure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = "cookieaccepted=1; max-age=31536000; path=/; SameSite=Lax" + isSecure;
            const notice = document.getElementById("cookie-notice");
            if (notice) {
                notice.style.visibility = "hidden";
                notice.setAttribute("aria-hidden", "true");
            }
            if (window.clarity) clarity("consent");
            if (window.loadAnalyticsNow) window.loadAnalyticsNow();
        }
        
        function rejectCookie() {
            const isSecure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = "cookieaccepted=0; max-age=31536000; path=/; SameSite=Lax" + isSecure;
            const notice = document.getElementById("cookie-notice");
            if (notice) {
                notice.style.visibility = "hidden";
                notice.setAttribute("aria-hidden", "true");
            }
        }
        
        document.addEventListener('DOMContentLoaded', function () {
            const hasConsent = document.cookie.indexOf("cookieaccepted=1") >= 0;
            
            // Show banner if no consent yet
            if (!hasConsent) {
                const notice = document.getElementById("cookie-notice");
                if (notice) {
                    notice.style.visibility = "visible";
                    notice.setAttribute("aria-hidden", "false");
                }
            }
            
            let loaded = false;
            function loadAnalytics() {
                if (loaded) return;
                loaded = true;
                
                // Microsoft Clarity
                (function(c,l,a,r,i,t,y){
                    c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
                })(window, document, "clarity", "script", "eic7b2e9o1");
                
                // Send consent to Clarity
                let attempts = 0;
                const interval = setInterval(() => {
                    if (window.clarity && hasConsent) {
                        clarity("consent");
                        clearInterval(interval);
                    } else if (attempts++ > 20) {
                        clearInterval(interval);
                    }
                }, 100);
                
                // Google Analytics
                const script = document.createElement('script');
                script.async = true;
                script.src = "https://www.googletagmanager.com/gtag/js?id=G-X88D2RT23H";
                document.head.appendChild(script);
                script.onload = () => {
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    window.gtag = gtag;
                    gtag('js', new Date());
                    gtag('config', 'G-X88D2RT23H');
                };
            }
            
            window.loadAnalyticsNow = loadAnalytics;
            
            // Auto-load analytics if user already consented
            if (hasConsent) {
                const timeout = setTimeout(loadAnalytics, 5000);
                ['click','scroll','keydown','mousemove','touchstart'].forEach(event => {
                    window.addEventListener(event, () => {
                        clearTimeout(timeout);
                        loadAnalytics();
                    }, { once: true, passive: true });
                });
            }
        });
    </script>

    <!-- Scroll Progress Indicator -->
    <style>
        .scroll-indicator-bar {will-change: width; width: 0%; position: fixed; bottom: 0; height: 5px; background: #c53030; z-index: 5000}
    </style>
    <script>
        let ticking = false;
        function updateScroll() {
            const scroll = document.documentElement.scrollTop || document.body.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const indicator = document.getElementById("my_scroll_indicator");
            if (indicator) indicator.style.width = (scroll / height) * 100 + "%";
            ticking = false;
        }
        window.addEventListener("scroll", () => {
            if (!ticking) {
                requestAnimationFrame(updateScroll);
                ticking = true;
            }
        }, { passive: true });
    </script>
    <div class="scroll-indicator-container">
        <div class="scroll-indicator-bar" id="my_scroll_indicator"></div>
    </div>

    <!-- Auto-Insert Current Year -->
    <script>
        const yearEl = document.getElementById("current-year");
        if (yearEl) yearEl.textContent = new Date().getFullYear();
    </script>

    <!-- Accordion (One Open at a Time) -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const details = document.querySelectorAll("details.details-accordion");
            if (!details.length) return;
            
            details.forEach(detail => {
                detail.addEventListener("toggle", () => {
                    if (detail.open) {
                        details.forEach(other => {
                            if (other !== detail) other.removeAttribute("open");
                        });
                    }
                });
                
                const summary = detail.querySelector("summary");
                if (summary) {
                    summary.setAttribute("tabindex", "0");
                    summary.addEventListener("keydown", e => {
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
        document.addEventListener("DOMContentLoaded", () => {
            const elements = document.querySelectorAll('.flexy-container');
            if (!elements.length) return;
            
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('daneden-slideInUp');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            
            elements.forEach(el => observer.observe(el));
        });
    </script>
    
    <?php
});
