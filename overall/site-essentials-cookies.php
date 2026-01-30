<?php
// ======================================
// CONFIGURATION CONSTANTS
// ======================================

// Content & SEO
define('ALT_TEXT_MAX_LENGTH', 100);          // Maximum Characters for Image ALT Text
define('READING_SPEED_WPM', 200);            // Words per Minute for read Time Calculation

// Cache Durations (in Seconds)
define('CACHE_POST_STATS', 3600);            // 1 hour - Post Statistics Cache
define('CACHE_POST_COUNT', 3600);            // 1 hour - Post Count Cache

// Cookie & Analytics
define('COOKIE_MAX_AGE', 31536000);          // 1 year in Seconds
define('CLARITY_TIMEOUT', 2000);             // Clarity load Timeout (MS)
define('CLARITY_ID', 'eic7b2e9o1');          // Microsoft Clarity ID

// Regex Patterns (compiled once for Performance)
define('REGEX_DECORATIVE_IMAGES', '/(divider|icon|bg|decor|spacer)/i');
define('REGEX_LOGO_PATTERNS', '/logo|icon|avatar|emoji|placeholder|data:image\/svg/i');

// ======================================
// HELPER FUNCTIONS
// ======================================

// Helper: Recursively find all Image Blocks with priority-high Class
function find_priority_high_images($blocks, &$images = []) {
    foreach ($blocks as $block) {
        // Check if this Block is an Image with priority-high
        if ($block['blockName'] === 'core/image') {
            $class = $block['attrs']['className'] ?? '';
            if (strpos($class, 'priority-high') !== false) {
                // Try to get Image URL from Block Attributes
                $image_id = $block['attrs']['id'] ?? null;
                if ($image_id) {
                    $image_url = wp_get_attachment_image_url($image_id, 'full');
                    if ($image_url) {
                        $images[] = $image_url;
                    }
                }
                // Fallback: Parse HTML for src
                elseif (isset($block['innerHTML']) && preg_match('/src=["\']([^"\']+)["\']/', $block['innerHTML'], $match)) {
                    $images[] = $match[1];
                }
            }
        }
        // Recursively search innerBlocks (for nested Blocks like Columns, Groups, etc.)
        if (!empty($block['innerBlocks'])) {
            find_priority_high_images($block['innerBlocks'], $images);
        }
    }
    return $images;
}

// Helper: Get cached Post Count for a Post Type
function get_post_count_cached($type) {
    $cache_key = 'post_count_' . $type;
    $count = get_transient($cache_key);
    if ($count === false) {
        $count = wp_count_posts($type)->publish;
        set_transient($cache_key, $count, CACHE_POST_COUNT);
    }
    return $count;
}

// Helper: Add external-link Class to Anchor Tag
function add_external_link_class($tag) {
    $attrs = $tag;
    if (strpos($attrs, 'class=') !== false) {
        return preg_replace('/class=(["\'])(.*?)\1/', 'class=$1$2 external-link$1', $attrs);
    }
    return str_replace('<a ', '<a class="external-link" ', $attrs);
}

// ======================================
// JQUERY & CORE SCRIPTS
// ======================================

// Replace WP jQuery with self-hosted Version
if (!is_admin()) {
    add_action('wp_enqueue_scripts', function () {
        wp_deregister_script('jquery');
        wp_register_script('jquery', home_url('/my-assets/jquery-3.7.1.min.js'), [], '3.7.1', [
            'strategy'  => 'defer',
            'in_footer' => true,
        ]);
        wp_enqueue_script('jquery');
    }, 11); // Priority 11 required: WP core enqueues jQuery at priority 10
}

// ======================================
// PERFORMANCE OPTIMIZATIONS
// ======================================

// Remove QUERY Strings from static Resources for better Caching
add_filter('style_loader_src', fn($src) => remove_query_arg('ver', $src), 10, 2);
add_filter('script_loader_src', fn($src) => remove_query_arg('ver', $src), 10, 2);

// Defer non-critical CSS to reduce render-blocking
$critical_css_handles = [
    'blocksy-dynamic-global',	// CRITICAL - Blocksy Layout / Positioning
    'ct-main-styles',			// Blocksy core Styles
    'global-styles',			// WordPress global Styles
    'ct-page-title-styles',		// Page Title (above Fold)
    'ct-flexy-styles',			// Flexy Animations
    'wp-block-library',			// Gutenberg Blocks (if used in Content)
];
add_filter('style_loader_tag', function($html, $handle) use ($critical_css_handles) {
    if (in_array($handle, $critical_css_handles)) {
        return $html;
    }
    // Defer non-critical Stylesheets
    return str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $html);
}, 10, 2);

// ======================================
// LCP OPTIMIZATIONS
// ======================================

// Preload LCP Images in <head> (early Priority for maximum Impact)
add_action('wp_head', function() {
    global $post;
    // Only on single Posts / Pages
    if (!is_singular() || !$post) return;
    $preload_images = [];
    
    // 1. Add featured Image to preload List
    if (has_post_thumbnail()) {
        $image_id = get_post_thumbnail_id();
        $mobile_url = wp_get_attachment_image_url($image_id, 'large');
        $desktop_url = wp_get_attachment_image_url($image_id, 'full');
        if ($mobile_url && $desktop_url) {
            $preload_images[] = [
                'mobile' => $mobile_url,
                'desktop' => $desktop_url,
                'type' => 'responsive'
            ];
        }
    }
    
    // 2. Find Images with priority-high Class in Content (recursively)
    if (has_blocks($post->post_content)) {
        $blocks = parse_blocks($post->post_content);
        $priority_images = find_priority_high_images($blocks);
        foreach ($priority_images as $url) {
            $preload_images[] = [
                'url' => $url,
                'type' => 'single'
            ];
        }
    }
    
    // 3. Output preload Links for all collected Images
    foreach ($preload_images as $img) {
        if ($img['type'] === 'responsive') {
            // Preload 'large' for Mobile, 'full' for Desktop
            echo '<link rel="preload" as="image" href="' . esc_url($img['mobile']) . '" media="(max-width: 768px)" fetchpriority="high">' . "\n";
            echo '<link rel="preload" as="image" href="' . esc_url($img['desktop']) . '" media="(min-width: 769px)" fetchpriority="high">' . "\n";
        } else {
            // For priority-high Images, use single URL (already in content)
            echo '<link rel="preload" as="image" href="' . esc_url($img['url']) . '" fetchpriority="high">' . "\n";
        }
    }
}, 1);

// Add fetchpriority="high" and loading="eager" to priority-high Images in HTML
add_filter('render_block', function($html, $block) {
    if ($block['blockName'] !== 'core/image') return $html;
    $cls = $block['attrs']['className'] ?? '';
    if (strpos($cls, 'priority-high') === false) return $html;
    return preg_replace(
        '/<img(?![^>]*fetchpriority)([^>]+)>/',
        '<img$1 fetchpriority="high" loading="eager">',
        $html,
        1
    );
}, 10, 2);

// ======================================
// BLOCKSY THEME OPTIMIZATIONS
// ======================================

// Switch from lazy to eager for featured Images (ct-media-container)
add_filter('blocksy:frontend:dynamic-data:post-featured-image:html', function($html) {
    if (!is_singular()) return $html;
    // Remove loading="lazy"
    $html = preg_replace('/\s*loading=["\']lazy["\']/', '', $html);
    // Add fetchpriority="high" if not present
    if (strpos($html, 'fetchpriority') === false) {
        $html = str_replace('<img', '<img fetchpriority="high"', $html);
    }
    // Add loading="eager"
    if (strpos($html, 'loading=') === false) {
        $html = str_replace('<img', '<img loading="eager"', $html);
    }
    return $html;
}, 10);

// Disable Google Fonts (using local Fonts instead)
add_filter('blocksy:typography:google:use-remote', '__return_false');
add_filter('blocksy_typography_font_sources', function($sources) {
    unset($sources['google']);
    return $sources;
});

// Enable Blocksy Flexy Animation Styles
add_action('wp_enqueue_scripts', fn() => wp_enqueue_style('ct-flexy-styles'));

// Strip unused Blocksy Extensions for faster Loading
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

// Enable Dashicons on Frontend (for Icons in Content)
add_action('wp_enqueue_scripts', function () {
    global $post;
    if ($post && strpos($post->post_content, 'dashicons-') !== false) {
        wp_enqueue_style('dashicons');
    }
});

// Preconnect to external Services
add_action('wp_head', function () {
    echo '<link rel="dns-prefetch" href="https://secure.gravatar.com">' . "\n";
    echo '<link rel="dns-prefetch" href="https://www.clarity.ms">' . "\n";
    echo '<link rel="preconnect" href="https://www.clarity.ms" crossorigin>' . "\n";
}, 10);

// Prevent cached previews (with timestamp)
add_filter('preview_post_link', function($url){
    return add_query_arg('ts', time(), $url);
});

// Prevent cached frontend for logged-in editors
add_action('template_redirect', function () {
    if (!is_user_logged_in()) return;
    if (!current_user_can('edit_posts')) return;
    nocache_headers();
});

// ======================================
// SHORTCODES
// ======================================

// Enable Shortcodes in Widgets and Blocks
add_filter('widget_text', 'do_shortcode');
add_filter('widget_block_content', 'do_shortcode');

// [reusable id="123"] - Display reusable Content Blocks
add_shortcode('reusable', 'get_reusable_block');
add_shortcode('blocksy_content_block', 'get_reusable_block');
function get_reusable_block($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);
    $id = absint($atts['id']);
    if (!$id) return '';
    // Additional Security: Check Post Type
    $post = get_post($id);
    if (!$post || $post->post_status !== 'publish' || $post->post_type !== 'wp_block') return '';
    return apply_filters('the_content', $post->post_content);
}

// [total_post], [total_page], etc. - Display Post Type Counts
if (!is_admin()) {
    add_action('init', function() {
        foreach (['post', 'page', 'my-interests', 'my-traits'] as $type) {
            add_shortcode('total_' . str_replace('-', '_', $type), function() use ($type) {
                return get_post_count_cached($type);
            });
        }
    });
}

// [post_stats] - Comprehensive Content Statistics
add_shortcode('post_stats', function() {
    global $post;
    if (!$post) return '';
    // Check Cache first
    $cache_key = 'post_stats_' . $post->ID;
    $cached_output = get_transient($cache_key);
    if ($cached_output !== false) {
        return $cached_output;
    }
    $content = apply_filters('the_content', $post->post_content);
    $text = strip_tags(strip_shortcodes($content));
    // Calculate Statistics
    $word_count = str_word_count($text);
    $stats = [
        'words' => $word_count,
        'minutes' => ceil($word_count / READING_SPEED_WPM),
        'chars' => strlen($text),
        'paragraphs' => substr_count($content, '</p>'),
        'images' => substr_count($content, '<img'),
        'videos' => preg_match_all('/<video[^>]*>/i', $content) + preg_match_all('/<iframe[^>]*(?:youtube\.com|vimeo\.com)[^>]*>/i', $content),
        'titles' => preg_match_all('/<h[1-6][^>]*>.*?<\/h[1-6]>/', $content)
    ];
    // Count Links
    preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $matches);
    $site_url = home_url();
    $internal = $external = 0;
    foreach ($matches[1] ?? [] as $url) {
        (strpos($url, $site_url) === 0 || strpos($url, '/') === 0) ? $internal++ : $external++;
    }
    // Sentence Analysis
    $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $sentence_count = count($sentences);
    $avg_words = $sentence_count > 0 ? round($stats['words'] / $sentence_count, 1) : 0;
    // Format Output with Thousands Separators
    $fmt = fn($n) => number_format($n, 0, '.', ",");
    $output = '<div class="post-stats">'
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
    set_transient($cache_key, $output, CACHE_POST_STATS);
    return $output;
});

// Clear [total_post] Cache when Posts are published / deleted
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status !== $old_status) {
        delete_transient('post_count_' . $post->post_type);
    }
}, 10, 3);

// Clear [post_stats] Cache when Post is updated
add_action('save_post', function($post_id) {
    // Security Checks
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    delete_transient('post_stats_' . $post_id);
});

// ======================================
// SEO & ACCESSIBILITY
// ======================================

// Auto-generate ALT Text for Images (or mark decorative Images)
add_filter('wp_get_attachment_image', function($html, $attachment_id) {
    $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    $src = wp_get_attachment_url($attachment_id);
    // Mark decorative Images with empty ALT
    if (preg_match(REGEX_DECORATIVE_IMAGES, $src)) {
        $alt = '';
    } elseif (empty($alt)) {
        $alt = get_the_title($attachment_id);
    }
    // Remove ALT if Link has visible Text that matches the ALT (avoids Redundancy)
    if (preg_match('/<a[^>]*>([^<]+)<img/i', $html, $link_text_match)) {
        $link_text = trim(strip_tags($link_text_match[1]));
        if (!empty($link_text) && strcasecmp($link_text, $alt) === 0) {
            $alt = '';
        }
    }
    $alt = esc_attr(mb_substr(wp_strip_all_tags($alt), 0, ALT_TEXT_MAX_LENGTH));
    return preg_replace('/alt=["\'](.*?)["\']/', 'alt="' . $alt . '"', $html);
}, 10, 2);

// Ensure all Image Links have accessible Names
add_filter('the_content', function ($content) {
    // Find all <a> Tags that contain only <img> Tags (Image Links)
    return preg_replace_callback(
        '/<a\s+([^>]*?)>\s*<img\s+([^>]*?)>\s*<\/a>/is',
        function ($matches) {
            $link_attrs = $matches[1];
            $img_attrs = $matches[2];
            // Check if Image has meaningful ALT Text
            $has_alt = preg_match('/alt=(["\'])(?!\s*\1)(.+?)\1/i', $img_attrs, $alt_match);
            // Check if Link already has Aria-Label or Title
            $has_aria_label = preg_match('/aria-label=/i', $link_attrs);
            $has_title = preg_match('/title=/i', $link_attrs);
            // If no accessible Name exists anywhere, add Aria-Label to the Link
            if (!$has_alt && !$has_aria_label && !$has_title) {
                // Extract href to create a meaningful Label
                if (preg_match('/href=(["\'])([^"\']+)\1/i', $link_attrs, $href_match)) {
                    $url = $href_match[2];
                    $domain = parse_url($url, PHP_URL_HOST);
                    $label = $domain ? "Link to $domain" : "Link to image";
                    $link_attrs = 'aria-label="' . esc_attr($label) . '" ' . $link_attrs;
                }
            }
            // If Image has empty ALT, use it from Link Title or create one
            elseif (preg_match('/alt=(["\'])\s*\1/i', $img_attrs)) {
                if ($has_title && preg_match('/title=(["\'])(.+?)\1/i', $link_attrs, $title_match)) {
                    // Use link title as alt text
                    $img_attrs = preg_replace('/alt=(["\'])\s*\1/i', 'alt="' . esc_attr($title_match[2]) . '"', $img_attrs);
                } elseif (preg_match('/href=(["\'])([^"\']+)\1/i', $link_attrs, $href_match)) {
                    // Generate ALT from URL
                    $url = $href_match[2];
                    $path = parse_url($url, PHP_URL_PATH);
                    $filename = basename($path);
                    $alt_text = ucfirst(str_replace(['-', '_'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
                    $img_attrs = preg_replace('/alt=(["\'])\s*\1/i', 'alt="' . esc_attr($alt_text) . '"', $img_attrs);
                }
            }
            return "<a $link_attrs><img $img_attrs></a>";
        },
        $content
    );
}, 15);

// ======================================
// LINK PROCESSING
// ======================================

// Process external Links (combined: Add Class + Title Attribute)
add_filter('the_content', function ($content) {
    // Domains to exclude from external Link Processing
    $exclude = ['ericroth.org', 'ericroth-org', '1drv.ms', 'paypal.com', 'librarything.com',
                'themoviedb.org', 'facebook.com', 'github.com', 'linkedin.com',
                'patreon.com', 'bsky.app', 'bsky.social', 'about.me', 'buymeacoffee.com'];
    // STEP 1: Pre-process Content for 'neli' Class and Figure Links (wp-block-image, wp-block-embed)
	$content = preg_replace_callback('/<(figure|div)[^>]*class=["\'][^"\']*neli[^"\']*["\'][^>]*>(.*?)<\/\1>/is', 
        function($m) {
            return preg_replace('/<a\s+/', '<a data-neli="true" ', $m[0]);
        }, 
        $content
    );
	$content = preg_replace_callback('/<figure[^>]*class=["\'][^"\']*(wp-block-image|wp-block-embed)[^"\']*["\'][^>]*>(.*?)<\/figure>/is', 
		function($m) {
			return preg_replace('/<a\s+/', '<a data-figure-link="true" ', $m[0]);
		}, 
		$content
	);
    // STEP 2: Process all Links
	return preg_replace_callback('/<a\s+([^>]+)>/i', function ($m) use ($exclude) {
        $tag = $m[0];
        $attrs = $m[1];
        // Extract href (if no href, skip Processing)
        if (!preg_match('/href=["\']([^"\']+)["\']/i', $attrs, $href_match)) {
            return $tag;
        }
        $href = $href_match[1];
		// Normalize protocol-relative URLs
		if (strpos($href, '//') === 0) {
			$href = (is_ssl() ? 'https:' : 'http:') . $href;
		}

        // --- SKIP CONDITIONS (return Link unchanged) ---
        
		// Skip: Whitelisted Domains
        foreach ($exclude as $domain) {
            if (strpos($href, $domain) !== false) return $tag;
        }
        // Skip: 'neli' Exclusion
        if (strpos($attrs, 'data-neli="true"') !== false) {
            return str_replace(' data-neli="true"', '', $tag);
        }
        if (preg_match('/class=["\'][^"\']*\bneli\b[^"\']*["\']/', $attrs)) {
            return $tag;
        }
		// Skip: Figure Links (wp-block-image, wp-block-embed)
		if (strpos($attrs, 'data-figure-link="true"') !== false) {
			return str_replace(' data-figure-link="true"', '', $tag);
		}
		// Skip: Other WP UI Elements
        if (preg_match('/class=["\'][^"\']*(wp-block-button__link|button|page-numbers|wp-block-social-link|wp-social-link|wp-social-link-youtube)[^"\']*["\']/', $attrs)) {
            return $tag;
        }
        // Skip: Internal / Special Links
        if ($href[0] === '#' || $href[0] === '/' || strpos($href, 'tel:') === 0 || 
            stripos($href, 'javascript') !== false || strpos($href, '?cat=') !== false) {
            return $tag;
        }
        
        // --- PROCESS LINKS (return Link changed) ---
        
        $modified = add_external_link_class($tag);
        // Add Title Attribute for Links opening in new Tab
        if (strpos($attrs, 'target="_blank"') !== false && strpos($attrs, 'title=') === false) {
            $modified = str_replace('<a ', '<a title="Opens in a new tab" ', $modified);
        }
        return $modified;
    }, $content);
}, 10);

// Remove external-Link Class from iFrame Wrappers (for embedded Vids)
add_filter('the_content', function ($content) {
    // Remove external-link Class from any <a> Tags that wrap iFrames
	return preg_replace_callback(
        '/<a\s+([^>]*class=["\'][^"\']*external-link[^"\']*["\'][^>]*)>\s*<iframe/i',
        function ($matches) {
            $attrs = $matches[1];
            // Remove external-link Class
            $attrs = preg_replace('/\bexternal-link\b\s*/', '', $attrs);
            // Clean up double Spaces in Class Attribute
            $attrs = preg_replace('/class=(["\'])\s+/', 'class=$1', $attrs);
            return "<a $attrs><iframe";
        },
        $content
    );
}, 11); // Priority 11 required: Runs after the external Link Filter above

// ======================================
// REDIRECTS
// ======================================

// SEO-friendly 301 Redirects for old URLs
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
			<button type="button" id="cookie-accept" aria-label="Accept all cookies"><span>Accept</span></button>
			<button type="button" id="cookie-reject" aria-label="Reject optional cookies" class="reject-btn"><span>Essentials</span></button>
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

	<!-- Cookie Consent Logic -->

	<script>
		// Cookie Helper Functions (defined outside DOMContentLoaded)
		(function() {
			window.setCookie = function(value) {
				const isSecure = location.protocol === 'https:';
				const cookie = 'cookieaccepted=' + value + '; max-age=<?php echo COOKIE_MAX_AGE; ?>; path=/; SameSite=Lax' + (isSecure ? '; Secure' : '');
				document.cookie = cookie;
			};
			window.hideCookieBanner = function() {
				const notice = document.getElementById("cookie-notice");
				if (notice) {
					notice.style.visibility = "hidden";
					notice.setAttribute("aria-hidden", "true");
				}
			};
		})();
	</script>

	<!-- Scroll Progress Indicator -->

	<style>
		.scroll-indicator-bar {will-change: width; width: 0%; position: fixed; bottom: 0; height: 5px; background: #c53030; z-index: 5000}
	</style>

	<script>
		(function() {
			let ticking = false;
			let lastPercentage = -1; // Track last Value to avoid unnecessary Updates
			function updateScroll() {
				// Read ALL Layout Properties ONCE per Frame
				const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
				const scrollHeight = document.documentElement.scrollHeight;
				const clientHeight = document.documentElement.clientHeight;
				const height = scrollHeight - clientHeight;
				const percentage = height > 0 ? Math.round((scrollTop / height) * 100) : 0;
				const indicator = document.getElementById("my_scroll_indicator");
				const container = indicator?.parentElement;
				// Only update if Percentage actually changed
				if (indicator && percentage !== lastPercentage) {
					indicator.style.width = percentage + "%";
					lastPercentage = percentage;
				}
				if (container) {
					container.setAttribute("aria-valuenow", percentage);
				}
				ticking = false;
			}
			window.addEventListener("scroll", () => {
				if (!ticking) {
					requestAnimationFrame(updateScroll);
					ticking = true;
				}
			}, { passive: true });
		})();
	</script>

	<div class="scroll-indicator-container" role="progressbar" aria-label="Page scroll progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
		<div class="scroll-indicator-bar" id="my_scroll_indicator"></div>
	</div>

	<!-- Tabs Styles (Conditional Load) -->

	<?php if (is_page(array('17552'))) { ?>
	<style>
		.tabs {overflow: hidden}
		.tabs button {float: left; padding: 7.5px 10px; margin: 0px 2.5px; color: #1e73be; font-weight: bold; background: rgb(255, 255, 255); background: linear-gradient(0deg, rgba(255, 255, 255, 1) 0%, rgba(230, 230, 230, 0.75) 100%); border: solid #c5c5c5; border-width: 1px 1px 0 1px; border-radius: 5px 5px 0 0; cursor: pointer}
		.tabs button:hover {color: #c53030}
		.tab-content {display: none; border: 1px solid #c5c5c5; border-radius: 5px 15px 15px 15px; padding: 2.5rem 1.5rem 2.5rem 2.5rem}
		body.dark-mode .tabs button {background: linear-gradient(0deg, rgba(26, 26, 26, 1) 0%, rgba(51, 51, 51, 0.75) 100%); border: solid #404040; border-width: 1px 1px 0 1px}
		body.dark-mode .tab-content {border: 1px solid #404040}
	</style>
	<?php } ?>

	<!-- Consolidated JavaScript - Single DOMContentLoaded -->

	<script>
		document.addEventListener('DOMContentLoaded', function() {

			// Cookie Consent Banner
			const hasConsent = document.cookie.indexOf("cookieaccepted=") >= 0;
			if (!hasConsent) {
				const notice = document.getElementById("cookie-notice");
				if (notice) {
					notice.style.visibility = "visible";
					notice.setAttribute("aria-hidden", "false");
					document.getElementById("cookie-accept").addEventListener('click', function() {
						window.setCookie(1);
						window.hideCookieBanner();
					});
					document.getElementById("cookie-reject").addEventListener('click', function() {
						window.setCookie(0);
						window.hideCookieBanner();
					});
				}
			}

			// Fix aria-hidden focusable Elements
			document.querySelectorAll('[aria-hidden="true"] a, [aria-hidden="true"] button, [aria-hidden="true"] input, [aria-hidden="true"] select, [aria-hidden="true"] textarea, [aria-hidden="true"] *[tabindex]:not([tabindex="-1"])').forEach(function(el) {
				el.setAttribute('tabindex', '-1');
			});

			// Auto-insert current Year
			const yearEl = document.getElementById("current-year");
			if (yearEl) yearEl.textContent = new Date().getFullYear();

			// Accordion (one open at a time)
			const details = document.querySelectorAll("details.details-accordion");
			if (details.length) {
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
			}

			// Flexy Animation (Blocksy Theme)
			const flexyElements = document.querySelectorAll('.flexy-container');
			if (flexyElements.length) {
				const observer = new IntersectionObserver(entries => {
					entries.forEach(entry => {
						if (entry.isIntersecting) {
							entry.target.classList.add('daneden-slideInUp');
							observer.unobserve(entry.target);
						}
					});
				}, { threshold: 0.1 });
				flexyElements.forEach(el => observer.observe(el));
			}
			<?php if (is_page(array('17552'))) { ?>

			// Tabs Setup
			function setupTabs(containerId) {
				const container = document.getElementById(containerId);
				if (!container) return;
				const tabs = container.querySelectorAll('.tab-links');
				const tabContents = container.querySelectorAll('.tab-content');
				if (tabContents.length > 0) {
					tabContents[0].style.display = 'block';
				}
				tabs.forEach(tab => {
					tab.addEventListener('mouseover', () => {
						const tabId = tab.getAttribute('data-tab');
						tabContents.forEach(content => {
							content.style.display = (content.id === tabId) ? 'block' : 'none';
						});
					});
				});
			}
			setupTabs('countries-tabs');
			setupTabs('cities-tabs');
			<?php } ?>
		});

	</script>

	<!-- MS Clarity Analytics -->

	<?php if (!current_user_can('administrator')) : ?>
	<script>
	(function(){
		if (document.documentElement.dataset.clarityLoaded) return;
		document.documentElement.dataset.clarityLoaded = "1";
		function loadClarity(){
			(function(c,l,a,r,i,t,y){
				c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
				t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
				t.rel = 'noopener';
				y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
			})(window, document, "clarity", "script", "<?php echo CLARITY_ID; ?>");
		}
		if ('requestIdleCallback' in window) {
			requestIdleCallback(loadClarity, { timeout: <?php echo CLARITY_TIMEOUT; ?> });
		} else {
			setTimeout(loadClarity, 1500);
		}
	})();
	</script>
	<?php endif; ?>

<?php
});
