<?php
defined('ABSPATH') || exit;

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
// WORDPRESS CORE OPTIMIZATIONS
// ======================================

// Disable WordPress emoji Scripts and Styles
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

// Disable Pingbacks and Trackbacks
add_filter('pings_open', '__return_false');
add_filter('pre_ping', '__return_empty_array');

// Remove "Edit" Link in Frontend
add_filter('edit_post_link', '__return_false', 10, 1);

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

// Load Block CSS individually (only Blocks actually used on Page)
add_filter('should_load_separate_core_block_assets', '__return_true');

// Defer non-critical CSS to reduce render-blocking
$critical_css_handles = [
    'global-styles',			// WordPress global Styles
    'blocksy-dynamic-global',	// THEME RELATED: CRITICAL - Blocksy Layout / Positioning
    'ct-main-styles',			// THEME RELATED: Blocksy core Styles
    'ct-page-title-styles',		// THEME RELATED: Page Title (above Fold)
    'ct-flexy-styles',			// THEME RELATED: Flexy Animations
];
add_filter('style_loader_tag', function($html, $handle) use ($critical_css_handles) {
    if (in_array($handle, $critical_css_handles)) {
        return $html;
    }
    // Defer non-critical Stylesheets with noscript Fallback
    $preload_html = str_replace(
        "rel='stylesheet'", 
        "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
        $html
    );
    return $preload_html . '<noscript>' . $html . '</noscript>';
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
    if (is_front_page()) { // Add Cover Block Poster on Front Page
        preg_match('/poster="([^"]+)"/', $post->post_content, $m);
        if (!empty($m[1])) $preload_images[] = ['url' => $m[1], 'type' => 'single'];
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
// BLOCKSY OPTIMIZATIONS - THEME RELATED
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

// Enable Blocksy Flexy Animation Styles
add_action('wp_enqueue_scripts', fn() => wp_enqueue_style('ct-flexy-styles'));

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

// Prevent cached Previews (with timestamp)
add_filter('preview_post_link', function($url){
    return add_query_arg('ts', time(), $url);
});

// Prevent cached Frontend for logged-in Editors
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
add_shortcode('blocksy_content_block', 'get_reusable_block'); // THEME RELATED
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
        foreach (['post', 'page', 'my-interests', 'my-quotes', 'my-traits'] as $type) {
            add_shortcode('total_' . str_replace('-', '_', $type), function() use ($type) {
                return get_post_count_cached($type);
            });
        }
    });
}

// [total_post], [total_page], etc. - Clear Cache when published / deleted
add_action('transition_post_status', function($new_status, $old_status, $post) {
    if ($new_status !== $old_status) {
        delete_transient('post_count_' . $post->post_type);
    }
}, 10, 3);

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
    // Get the RAW Post Content
    $raw_content = $post->post_content;
    // Get the RENDERED Content for Text / HTML counting
    $content = apply_filters('the_content', $post->post_content);
    $text = wp_strip_all_tags($content);
    // Calculate Statistics
    $word_count = str_word_count($text);
    $stats = [
        'words' => $word_count,
        'minutes' => ceil($word_count / READING_SPEED_WPM),
        'chars' => strlen($text),
        'paragraphs' => substr_count($content, '</p>'),
    ];
    // Count Images from rendered Content
    $stats['images'] = substr_count($content, '<img');
    // Count Videos - Simply count Figures with is-type-video Class
    $stats['videos'] = substr_count($raw_content, 'is-type-video');
    // Count Titles
    $title_matches = [];
    $stats['titles'] = preg_match_all('/<h[1-6][^>]*>.*?<\/h[1-6]>/s', $content, $title_matches);
    // Count Links
    preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $matches);
    $site_url = home_url();
    $internal = $external = 0;
    foreach ($matches[1] ?? [] as $url) {
        (strpos($url, $site_url) === 0 || strpos($url, '/') === 0) ? $internal++ : $external++;
    }
    // Sentence Analysis
    $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
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

// [post_stats] - Clear Cache when Post is updated
add_action('save_post', function($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    delete_transient('post_stats_' . $post_id);
});

// [merged_tag_cloud] - Display merged Tag Clouds
define( 'MERGED_TAG_CLOUD_TAXONOMIES', 'things, topics, interest_tag, groups, types, category, post_tag' );
define( 'MERGED_TAG_CLOUD_NUMBER',     45 );
define( 'MERGED_TAG_CLOUD_SEPARATOR',  '' ); // Define in Shortcode (if none it defaults to none)
function merged_tag_cloud_shortcode( $atts ) {
    $atts = shortcode_atts([
        'taxonomies' => MERGED_TAG_CLOUD_TAXONOMIES,
        'smallest'   => 14,
        'largest'    => 14,
        'unit'       => 'px',
        'number'     => MERGED_TAG_CLOUD_NUMBER,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'class'      => 'is-style-default',
        'style'      => 'text-align: center;',
        'separator'  => MERGED_TAG_CLOUD_SEPARATOR,
    ], $atts );
    $cache_key = 'merged_tag_cloud_' . md5( $atts['taxonomies'] . $atts['number'] . $atts['separator'] );
    $output = get_transient( $cache_key );
    if ( $output !== false ) return $output;
    $taxonomies = array_map( 'trim', explode( ',', $atts['taxonomies'] ) );
    $all_terms  = [];
    foreach ( $taxonomies as $tax ) {
        $terms = get_terms([
            'taxonomy'   => $tax,
            'hide_empty' => true,
        ]);
        if ( ! is_wp_error( $terms ) ) {
            $all_terms = array_merge( $all_terms, $terms );
        }
    }
    $seen = [];
    $all_terms = array_filter( $all_terms, function( $term ) use ( &$seen ) {
        $key = $term->taxonomy . '_' . $term->term_id;
        if ( isset( $seen[$key] ) ) return false;
        $seen[$key] = true;
        return true;
    });
    if ( empty( $all_terms ) ) return '<p>No tags found.</p>';
    $counts    = array_column( $all_terms, 'count' );
    $min_count = min( $counts );
    $max_count = max( $counts );
    $spread    = $max_count - $min_count ?: 1;
    $range     = $atts['largest'] - $atts['smallest'];
    usort( $all_terms, fn( $a, $b ) => strcmp( $a->name, $b->name ) );
    if ( $atts['number'] > 0 ) {
        $all_terms = array_slice( $all_terms, 0, (int) $atts['number'] );
    }
    $links = [];
    foreach ( $all_terms as $term ) {
        $size = $atts['smallest'] + ( $range * ( $term->count - $min_count ) / $spread );
        $size = round( $size, 1 );
        $link = get_term_link( $term );
        if ( is_wp_error( $link ) ) continue;
        $links[] = sprintf(
            '<a href="%s" style="font-size: %s%s;" title="%s (%d)">%s <span class="tag-count">(%d)</span></a>',
            esc_url( $link ),           // href
            $size,                      // font-size Value
            esc_attr( $atts['unit'] ),  // font-size Unit
            esc_html( $term->name ),    // Hover Tooltip - Name
            $term->count,               // Hover Tooltip - Count
            esc_html( $term->name ),    // Visible on Page - Name
            $term->count                // Visible on Page - Count
        );
    }
    $separator = $atts['separator'] !== ''
        ? ' <span class="tag-separator">' . esc_html( $atts['separator'] ) . '</span> '
        : ' ';
    $output  = '<div class="' . esc_attr( $atts['class'] ) . '" style="' . esc_attr( $atts['style'] ) . '">';
    $output .= implode( $separator, $links );
    $output .= '</div>';
    set_transient( $cache_key, $output, HOUR_IN_SECONDS * 12 );
    return $output;
}
add_shortcode( 'merged_tag_cloud', 'merged_tag_cloud_shortcode' );

// [merged_tag_cloud] - Clear Cache when Terms are added, edited or deleted
add_action( 'created_term', 'merged_tag_cloud_clear_cache' );
add_action( 'edited_term',  'merged_tag_cloud_clear_cache' );
add_action( 'deleted_term', 'merged_tag_cloud_clear_cache' );
function merged_tag_cloud_clear_cache() {
    delete_transient( 'merged_tag_cloud_' . md5( MERGED_TAG_CLOUD_TAXONOMIES . MERGED_TAG_CLOUD_NUMBER . MERGED_TAG_CLOUD_SEPARATOR ) );
}

// ======================================
// SEO & ACCESSIBILITY
// ======================================

// Auto-generate ALT Text for Images (or mark decorative Images)
add_filter('wp_get_attachment_image', function($html, $attachment_id) {
    $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    $src = wp_get_attachment_url($attachment_id);
    // Mark decorative Images with empty ALT
	if ($src && (preg_match(REGEX_DECORATIVE_IMAGES, $src) || preg_match(REGEX_LOGO_PATTERNS, $src))) {
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
    return preg_replace('/alt=["\'](.*?)["\']/', 'alt="' . $alt . '"', $html, 1);
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
// REDIRECTS
// ======================================

// SEO-friendly 301 Redirects for old URLs
add_action('template_redirect', function() {
    $redirects = [
        '/contact-me/'		=> '/about-me/contact/',
        '/services/'		=> '/professional/',
        '/my-background/'	=> '/professional/my-background/',
        '/my-blog/'			=> '/personal/my-blog/',
        '/my-interests/'	=> '/personal/my-interests/',
		'/my-quotes/'		=> '/about-me/my-quotes/',
    ];
    $uri = isset($_SERVER['REQUEST_URI']) ? trailingslashit(parse_url(sanitize_text_field($_SERVER['REQUEST_URI']), PHP_URL_PATH)) : '';
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
	
	<div id="cookie-notice" role="region" aria-live="polite" aria-label="Cookie notice" aria-hidden="true" style="visibility: hidden; text-align: justify; color: var(--color-8); font-family: inherit; background: var(--color-10); padding: 15px 20px 20px 20px; position: fixed; bottom: 15px; left: 15px; width: 100%; max-width: 300px; border-radius: 10px; z-index: 10000; box-sizing: border-box;">
		<button type="button" id="cookie-close" aria-label="Close cookie notice">ⓧ</button>
		We serve <strong>cookies</strong> to enhance your browsing experience. Learn more in our
		<a href="https://ericroth.org/this-site/site-policies/">Site Policies</a><br>
		<span style="display: block; text-align: center;">
			<button type="button" id="cookie-accept" aria-label="Accept all cookies"><span style="font-weight: bold;">Accept</span></button>
			<button type="button" id="cookie-reject" aria-label="Reject optional cookies" class="reject-btn"><span style="font-weight: bold;">Essentials</span></button>
		</span>
	</div>

	<style>
		#cookie-close {position: absolute; top: -20px; right: -20px; background: transparent !important; border: none !important; color: var(--color-3); font-size: 1.5rem; line-height: 1; cursor: pointer; padding: 0 !important; margin: 0 !important;}
		#cookie-notice button:not(#cookie-close) {position: relative; font-weight: normal; color: var(--color-8); background: var(--color-4); border-radius: 5px; padding: 8px; margin-top: 15px; width: 48%; cursor: pointer; border: none; margin-left: 2%; display: inline-block; overflow: hidden;}
		#cookie-notice button:not(#cookie-close):first-child {margin-left: 0;}
		#cookie-accept {background: var(--color-4);}
		#cookie-accept:hover {background: var(--color-3) !important;}
		#cookie-accept:hover span {opacity: 0;}
		#cookie-accept:hover::before {content: "All"; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); color: var(--color-8);}
		#cookie-reject {background: var(--color-4);}
		#cookie-reject:hover {background: var(--color-3) !important;}
		#cookie-reject:hover span {opacity: 0;}
		#cookie-reject:hover::before {content: "Only"; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); color: var(--color-8);}
		#cookie-notice button:focus-visible {outline: 2px solid var(--color-8); outline-offset: 2px;}
		@media (max-width: 480px) {#cookie-notice {max-width: 100%; bottom: 0; left: 0; border-radius: 0;} #cookie-notice button:not(#cookie-close) {width: 48%; font-size: 14px; padding: 8px 5px;}}
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
		.scroll-indicator-bar {will-change: width; width: 0%; position: fixed; bottom: 0; height: 5px; background: var(--color-2); z-index: 5000}
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

	<!-- Consolidated JavaScript - Single DOMContentLoaded -->

	<script>
		document.addEventListener('DOMContentLoaded', function() {
		
			// Cookie Consent Banner
			const hasConsent = document.cookie.indexOf("cookieaccepted=") >= 0;
			if (!hasConsent) {
				const notice = document.getElementById("cookie-notice");
				if (notice) {
					setTimeout(function() {
						notice.style.visibility = "visible";
						notice.setAttribute("aria-hidden", "false");
					}, <?php echo is_front_page() ? 2500 : 0; ?>); // Delayed only on Frontpage
					document.getElementById("cookie-accept").addEventListener('click', function() {
						window.setCookie(1);
						window.hideCookieBanner();
					});
					document.getElementById("cookie-reject").addEventListener('click', function() {
						window.setCookie(0);
						window.hideCookieBanner();
					});
					document.getElementById("cookie-close").addEventListener('click', function() {
						window.setCookie(0);
						window.hideCookieBanner();
					});
				}
			}
			
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

			// Flexy Animation - THEME RELATED
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

			// External Link Processing - Client-side Version
				const siteUrl = '<?php echo esc_js(home_url('/')); ?>';
				const excludeDomains = [
					'ericroth.org',
					'ericroth-org',
					'1drv.ms',
					'paypal.com',
					'librarything.com',
					'themoviedb.org',
					'facebook.com',
					'github.com',
					'linkedin.com',
					'patreon.com',
					'bsky.app',
					'bsky.social',
					'about.me',
					'buymeacoffee.com'
					// ← Add any other Domains if needed
				];
				document.querySelectorAll('a[href]').forEach(function(link) {
					let href = link.getAttribute('href');
					if (!href) return;
					// Normalize protocol-relative URLs (//example.com → https://example.com or http://)
					if (href.startsWith('//')) {
						href = window.location.protocol + href;
					}
					// Extract Classes once (cheaper than repeated className checks)
					const className = link.className || '';
					const attrs = link.getAttribute('class') || ''; // for contains checks
					// Quick Skip Conditions (mirroring your PHP logic)
					const skipReasons = [
						// Structural / Special Links
						href.startsWith('#'),
						href.startsWith('/'),
						href.startsWith('tel:'),
						href.startsWith('javascript'),
						href.includes('?cat='),
						// Excluded Domains
						excludeDomains.some(domain => href.includes(domain)),
						// Exclusion with Class neli (Data attr or Class)
						link.hasAttribute('data-neli') || attrs.includes('neli'),
						// Figure / Embed Links (Image & Video Blocks)
						link.closest('figure.wp-block-image') || 
						link.closest('.wp-block-embed__wrapper'),
						// WP UI / Button / Social Links
						className.includes('wp-block-button__link') ||
						className.includes('button') ||
						className.includes('page-numbers') ||
						className.includes('wp-block-social-link') ||
						className.includes('wp-social-link'),
						// Links that wrap iframes (YouTube / Vimeo Embeds etc.)
						link.querySelector('iframe')
					];
					if (skipReasons.some(Boolean)) {
						// Optional: Clean up temporary Data Attributes
						if (link.hasAttribute('data-neli'))      link.removeAttribute('data-neli');
						if (link.hasAttribute('data-figure-link')) link.removeAttribute('data-figure-link');
						return;
					}
					// Apply the Changes we actually want
					link.classList.add('external-link');
					// Add Title for new-tab Links if missing
					if (link.target === '_blank' && !link.hasAttribute('title')) {
						link.setAttribute('title', 'Opens in a new tab');
					}
				});

		});
	</script>

	<!-- Global ARIA-Hidden Tabindex Fix (Sliders, Modals, Dynamic Content) -->

	<script>
	(function() {
		'use strict';
		function fixAriaHiddenFocus() {
			document.querySelectorAll('[aria-hidden="true"]').forEach(container => {
				// CRITICAL: Fix the Container itself if it has a tabindex (e.g. Slick Slide Containers)
				if (container.hasAttribute('tabindex') && container.getAttribute('tabindex') !== '-1') {
					container.setAttribute('tabindex', '-1');
				}
				// Fix focusable Elements inside the Container
				const focusable = container.querySelectorAll(
					'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
				);
				focusable.forEach(el => el.setAttribute('tabindex', '-1'));
			});
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fixAriaHiddenFocus);
		} else {
			fixAriaHiddenFocus();
		}
		const observer = new MutationObserver(mutations => {
			const hasAriaChange = mutations.some(m => 
				m.type === 'attributes' && m.attributeName === 'aria-hidden'
			);
			if (hasAriaChange) fixAriaHiddenFocus();
		});
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				observer.observe(document.body, {
					attributes: true,
					attributeFilter: ['aria-hidden'],
					subtree: true
				});
			});
		} else {
			observer.observe(document.body, {
				attributes: true,
				attributeFilter: ['aria-hidden'],
				subtree: true
			});
		}
	})();
	</script>

	<!-- Tabs Styles & Script (Conditional Load) -->

	<?php if (is_page(array('17552'))) { ?>

	<style>
		.tabs {overflow: hidden}
		.tabs button {float: left; padding: 7.5px 10px; margin: 0px 2.5px; color: var(--color-1); font-weight: bold; background: var(--color-8); background: linear-gradient(0deg, rgba(255, 255, 255, 1) 0%, rgba(230, 230, 230, 0.75) 100%); border: solid var(--color-9); border-width: 1px 1px 0 1px; border-radius: 5px 5px 0 0; cursor: pointer}
		.tabs button:hover {color: var(--color-2)}
		.tab-content {display: none; background: var(--color-8); border: 1px solid var(--color-9); border-radius: 5px 15px 15px 15px; padding: 2.5rem 1.5rem 2.5rem 2.5rem}
		body.dark-mode .tabs button {background: linear-gradient(0deg, rgba(14, 24, 37, 1) 0%, rgba(51, 51, 51, 0.75) 100%); border: solid var(--color-4); border-width: 1px 1px 0 1px}
		body.dark-mode .tab-content {background: var(--color-10); border: 1px solid var(--color-4)}
	</style>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Tabs Setup
			function setupTabs(containerId) {
				const container = document.getElementById(containerId);
				if (!container) return;
				const tabs = container.querySelectorAll('.tab-links');
				const tabContents = container.querySelectorAll('.tab-content');
				// Show first Tab by Default and set ARIA
				if (tabContents.length > 0) {
					tabContents[0].style.display = 'block';
					if (tabs.length > 0) {
						tabs[0].setAttribute('aria-selected', 'true');
						tabs[0].setAttribute('tabindex', '0');
					}
				}
				// Initialize remaining Tabs
				for (let i = 1; i < tabs.length; i++) {
					tabs[i].setAttribute('aria-selected', 'false');
					tabs[i].setAttribute('tabindex', '-1');
				}
				function activateTab(tab) {
					const tabId = tab.getAttribute('data-tab');
					// Update Content visibility
					tabContents.forEach(content => {
						content.style.display = (content.id === tabId) ? 'block' : 'none';
					});
					// Update ARIA States
					tabs.forEach(t => {
						if (t === tab) {
							t.setAttribute('aria-selected', 'true');
							t.setAttribute('tabindex', '0');
						} else {
							t.setAttribute('aria-selected', 'false');
							t.setAttribute('tabindex', '-1');
						}
					});
				}
				tabs.forEach(tab => {
					// Mouseover for standard Users
					tab.addEventListener('mouseover', () => activateTab(tab));
					// Click for Accessibility
					tab.addEventListener('click', () => activateTab(tab));
					// Keyboard Support
					tab.addEventListener('keydown', (e) => {
						if (['Enter', ' '].includes(e.key)) {
							e.preventDefault();
							activateTab(tab);
						}
					});
				});
			}
			setupTabs('countries-tabs');
			setupTabs('cities-tabs');
		});
	</script>

	<?php } ?>

	<!-- MS Clarity Analytics -->

	<?php if (!current_user_can('administrator')) : ?>

	<script>
	(function(){
		function loadClarity(){
			(function(c,l,a,r,i,t,y){
				c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
				t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
				t.rel='noopener';
				y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
			})(window, document, "clarity", "script", "<?php echo CLARITY_ID; ?>");
		}
		if('requestIdleCallback'in window){
			requestIdleCallback(loadClarity,{timeout:<?php echo CLARITY_TIMEOUT;?>});
		}else{
			setTimeout(loadClarity,1500);
		}
	})();
	</script>

	<?php endif; ?>

<?php
});
