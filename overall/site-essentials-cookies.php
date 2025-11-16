<?php

// ----------------------------------------
//  PERFORMANCE OPTIMIZATIONS
// ----------------------------------------

// Remove Query Strings from Static Resources
function remove_cssjs_ver($src) {
    if (strpos($src, '?ver=')) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('style_loader_src', 'remove_cssjs_ver', 10, 2);
add_filter('script_loader_src', 'remove_cssjs_ver', 10, 2);

// Disable Emojis
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

// Improve Largest Contentful Paint (LCP)
add_action('template_redirect', function () {
    ob_start(function ($html) {
        $count = 0;
        $html = preg_replace_callback('/<img[^>]+>/i', function ($imgMatch) use (&$count) {
            $img = $imgMatch[0];
            if ($count === 0 && preg_match('/width=["\'](\d{3,})["\']/', $img)) {
                $count++;
                $img = preg_replace('/\sloading=["\']lazy["\']/', '', $img);
                $img = str_replace('<img', '<img fetchpriority="high"', $img);
                $img = preg_replace('/sizes=["\'][^"\']*["\']/', 'sizes="(max-width: 600px)100vw,(max-width: 1024px)80vw,1000px"', $img);
                if (!strpos($img, 'sizes=')) {
                    $img = str_replace('<img', '<img sizes="(max-width: 600px)100vw,(max-width: 1024px)80vw,1000px"', $img);
                }
            }
            return $img;
        }, $html);
        if (preg_match('/<img[^>]+width=["\'](\d{3,})["\'][^>]*src=["\']([^"\']+)["\'][^>]*>/i', $html, $match)) {
            $image_url = $match[2];
            $html = preg_replace('/<head>/', '<head><link rel="preload" as="image" href="' . esc_url($image_url) . '" fetchpriority="high">', $html, 1);
        }
        return $html;
    });
});

// ----------------------------------------
//  FRONTEND ASSETS
// ----------------------------------------

// Enable Dashicons on Frontend
function load_dashicons_front_end() {
    wp_enqueue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'load_dashicons_front_end');

// Blocksy – Disable Google Fonts
add_filter('blocksy:typography:google:use-remote', '__return_false');
add_filter('blocksy_typography_font_sources', function($sources) {
    unset($sources['google']);
    return $sources;
});

// Blocksy – Enqueue Flexy Styles
add_action('wp_enqueue_scripts', fn() => wp_enqueue_style('ct-flexy-styles'));

// Preconnect & DNS Prefetch
add_action('wp_head', function () {
    echo '
    <link rel="preconnect" href="https://www.clarity.ms" crossorigin>
    <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://secure.gravatar.com">
    ';
});

// ----------------------------------------
//  SHORTCODES
// ----------------------------------------

// Enable Shortcodes in Widgets
add_filter('widget_text', 'do_shortcode');
add_filter('widget_block_content', 'do_shortcode');

// Shortcode: [reusable id="123"]
add_shortcode('reusable', 'get_reusable_block');
add_shortcode('blocksy_content_block', 'get_reusable_block');
function get_reusable_block($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);
    $post_id = intval($atts['id']);
    $post = get_post($post_id);
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

// Shortcode: [post_stats]
add_shortcode('post_stats', function() {
    global $post;
    if (!$post) return '';
    // Try to get cached stats
    $transient_key = 'post_stats_' . $post->ID;
    $cached_output = get_transient($transient_key);
    
    if ($cached_output !== false) {
        return $cached_output;
    }
    // If not cached, calculate stats
    $content = apply_filters('the_content', $post->post_content);
    $text    = strip_tags(strip_shortcodes($content));
    $word_count = str_word_count($text);
    $minutes    = ceil($word_count / 200);
    $char_count = strlen($text);
    $paragraphs = substr_count($content, '</p>');
    $images     = substr_count($content, '<img');
    preg_match_all('/<video[^>]*>/i', $content, $video_matches);
    preg_match_all('/<iframe[^>]*(?:youtube\.com|vimeo\.com)[^>]*>/i', $content, $embed_matches);
    $videos = count($video_matches[0]) + count($embed_matches[0]);
    preg_match_all('/<h[1-6][^>]*>.*?<\/h[1-6]>/', $content, $heading_matches);
    $titles = count($heading_matches[0]);
    preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $matches);
    $internal_links = 0;
    $external_links = 0;
    $site_url = home_url();
    if (!empty($matches[1])) {
        foreach ($matches[1] as $url) {
            if (strpos($url, $site_url) === 0 || strpos($url, '/') === 0) {
                $internal_links++;
            } else {
                $external_links++;
            }
        }
    }
    $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $sentence_count = count($sentences);
    $avg_words_per_sentence = $sentence_count > 0 ? round($word_count / $sentence_count, 1) : 0;
    // Format numbers with thousands separator
    $word_count_fmt     = number_format($word_count, 0, '.', "'");
    $char_count_fmt     = number_format($char_count, 0, '.', "'");
    $paragraphs_fmt     = number_format($paragraphs, 0, '.', "'");
    $titles_fmt         = number_format($titles, 0, '.', "'");
    $images_fmt         = number_format($images, 0, '.', "'");
    $videos_fmt         = number_format($videos, 0, '.', "'");
    $internal_links_fmt = number_format($internal_links, 0, '.', "'");
    $external_links_fmt = number_format($external_links, 0, '.', "'");
    $sentence_count_fmt = number_format($sentence_count, 0, '.', "'");
    // Return output
    $output = '<div class="post-stats">'
         . '<span class="word-count"><strong>'.$word_count_fmt.'</strong> Words</span> • '
         . '<span class="read-time">Read Time: <strong>'.$minutes.'</strong> Min.</span><br>'
         . '<span class="char-count"><strong>'.$char_count_fmt.'</strong> Characters</span> • '
         . '<span class="paragraph-count"><strong>'.$paragraphs_fmt.'</strong> Paragraphs</span><br>'
         . '<span class="sentence-count"><strong>'.$sentence_count_fmt.'</strong> Sentences</span> • '
         . '<span class="avg-words-sentence"><strong>'.$avg_words_per_sentence.'</strong> Words per Sentence</span><br>'
         . '<span class="internal-link-count"><strong>'.$internal_links_fmt.'</strong> Internal Links</span> • '
         . '<span class="external-link-count"><strong>'.$external_links_fmt.'</strong> External Links</span><br>'
         . '<span class="title-count"><strong>'.$titles_fmt.'</strong> Titles</span> • '
         . '<span class="image-count"><strong>'.$images_fmt.'</strong> Images</span> • '
         . '<span class="video-count"><strong>'.$videos_fmt.'</strong> Videos</span>'
         . '</div>';
    // Cache for 24 hours (86400 seconds)
    set_transient($transient_key, $output, 86400);
    return $output;
});
// Clear cache when post is updated
add_action('save_post', function($post_id) {
    delete_transient('post_stats_' . $post_id);
});

// ----------------------------------------
//  SEO / UX & ACCESSIBILITY ENHANCEMENTS
// ----------------------------------------

// Automatically Add ALT Text to Images
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

// Automatically Add Title to Links with target="_blank"
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

// Custom Redirects (SEO-Friendly)
add_action('template_redirect', function() {
    $redirects = [
        '/contact-me/'      => '/about-me/contact/',
        '/services/'        => '/professional/',
        '/my-background/'   => '/professional/my-background/',
        '/my-blog/'         => '/personal/my-blog/',
        '/my-interests/'    => '/personal/my-interests/',
    ];
    $uri = trailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (isset($redirects[$uri])) {
        wp_redirect($redirects[$uri], 301);
        exit;
    }
});

// ---------------------------------------------
//  COOKIE CONSENT, ANALYTICS & UI ENHANCEMENTS
// ---------------------------------------------

add_action('wp_footer', function () {
    ?>

    <!-- Cookie Consent Notice (from Gijo) -->
    <p id="cookie-notice" role="region" aria-live="polite" aria-label="Cookie notice" aria-hidden="true" style="visibility: hidden;">
        We serve <strong>cookies</strong> to enhance your browsing experience. Learn more about it in our 
        <a href="https://ericroth.org/this-site/site-policies/">Site Policies</a><br>
        <span style="display: block; text-align: center;">
            <button type="button" onclick="acceptCookie();" aria-label="Accept all cookies"><span>Accept All</span></button>
            <button type="button" onclick="rejectCookie();" aria-label="Reject optional cookies" class="reject-btn"><span>Essential Only</span></button>
        </span>
    </p>
    <style>
        #cookie-notice {text-align: justify; color: #ffffff; font-family: inherit; background: rgba(0,0,0,0.75); padding: 20px; position: fixed; bottom: 15px; left: 15px; width: 100%; max-width: 300px; border-radius: 5px; margin: 0; z-index: 10000; box-sizing: border-box}
        #cookie-notice button {font-weight: normal; color: #ffffff; background: #1e73be; border-radius: 5px; padding: 8px; margin-top: 15px; width: 48%; cursor: pointer; border: none; margin-left: 2%; display: inline-block;}
        #cookie-notice button:first-child {margin-left: 0;}
        #cookie-notice button:hover {background: #c53030;}
        #cookie-notice button:hover span {display: none;}
        #cookie-notice button:hover::before {content: "Accept";}
        #cookie-notice button.reject-btn {background: #262626;}
        #cookie-notice button.reject-btn:hover {background: #262626;}
        #cookie-notice button.reject-btn:hover::before {content: "Reject";}
        #cookie-notice button:focus-visible {outline: 2px solid #ffffff; outline-offset: 2px;}
        @media only screen and (max-width: 480px) {
        #cookie-notice {max-width: 100%; bottom: 0; left: 0; border-radius: 0}
        #cookie-notice button {width: 48%; font-size: 14px; padding: 8px 5px;}
        }
    </style>

    <!-- MICROSOFT Clarity & GOOGLE Analytics with Cookie Consent -->
    <script>
        function acceptCookie() {
        const isSecure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = "cookieaccepted=1; max-age=31536000; path=/; SameSite=Lax" + isSecure;
            const notice = document.getElementById("cookie-notice");
            if (notice) {
                notice.style.visibility = "hidden";
                notice.setAttribute("aria-hidden", "true");
            }
            // Send consent Signal to Clarity
            if (window.clarity) {
                clarity("consent");
            }
            // Trigger Analytics loading if not already loaded
            if (window.loadAnalyticsNow) {
                window.loadAnalyticsNow();
            }
        }
        function rejectCookie() {
        const isSecure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = "cookieaccepted=0; max-age=31536000; path=/; SameSite=Lax" + isSecure;
        const notice = document.getElementById("cookie-notice");
        if (notice) {
            notice.style.visibility = "hidden";
            notice.setAttribute("aria-hidden", "true");
        }
        // Don't send consent to Clarity - Don't load Analytics
        }
        document.addEventListener('DOMContentLoaded', function () {
            // Check for existing Cookie Consent
            const hasConsent = document.cookie.indexOf("cookieaccepted=1") >= 0;
            // Show Cookie Notice if no Consent yet
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
                
                // Load MICROSOFT Clarity
                (function(c,l,a,r,i,t,y){
                    c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                    t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                    y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
                })(window, document, "clarity", "script", "eic7b2e9o1");
                // Send consent to Clarity with polling
                let clarityAttempts = 0;
                const clarityInterval = setInterval(function() {
                    if (window.clarity && hasConsent) {
                        clarity("consent");
                        clearInterval(clarityInterval);
                    } else if (clarityAttempts++ > 20) {
                        clearInterval(clarityInterval); // Stop after 2 seconds
                    }
                }, 100);
                
                // Load GOOGLE Analytics
                var s = document.createElement('script');
                s.async = true;
                s.src = "https://www.googletagmanager.com/gtag/js?id=G-X88D2RT23H";
                document.head.appendChild(s);
                s.onload = function() {
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    window.gtag = gtag;
                    gtag('js', new Date());
                    gtag('config', 'G-X88D2RT23H');
                };
            }

            // Make loadAnalytics available globally for Cookie Acceptance
            window.loadAnalyticsNow = loadAnalytics;
            // Only auto-load if User has already consented
            if (hasConsent) {
                const timeoutId = setTimeout(loadAnalytics, 5000);
                ['click','scroll','keydown','mousemove','touchstart'].forEach(function(event) {
                    window.addEventListener(event, function() {
                        clearTimeout(timeoutId);
                        loadAnalytics();
                    }, { once: true, passive: true });
                });
            }
            // If no Consent yet, wait for User to click "Accept"
        });
    </script>

    <!-- Scroll Progress Indicator -->
    <style>
        .scroll-indicator-container {width: 100%;}
        .scroll-indicator-bar {will-change: width; width: 0%; position: fixed; bottom: 0; height: 5px; background: #c53030; z-index: 5000;}
    </style>
    <script>
        let ticking = false;
        function scrollIndicator() {
            const scroll = document.documentElement.scrollTop || document.body.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const indicator = document.getElementById("my_scroll_indicator");
            if (indicator) {
                indicator.style.width = (scroll / height) * 100 + "%";
            }
            ticking = false;
        }
        window.addEventListener("scroll", function() {
            if (!ticking) {
                requestAnimationFrame(scrollIndicator);
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

    <!-- Accordion Toggle (Single Open) -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const details = document.querySelectorAll("details.details-accordion");
            if (details.length === 0) return;
            
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

    <!-- Flexy Animation (Blocksy Theme) -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const flexyElements = document.querySelectorAll('.flexy-container');
            if (flexyElements.length === 0) return;
            // Use IntersectionObserver to animate when elements come into view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('daneden-slideInUp');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            flexyElements.forEach(el => observer.observe(el));
        });
    </script>

    <?php
}, 10);

?>
