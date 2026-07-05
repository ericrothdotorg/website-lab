<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ======================================
// CORE TRACKING FUNCTIONS
// ======================================

// Track current visitor with filtering
function lum_track_visitor() {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }
	if (is_404()) {
        return;
    }
    // Don't track if it's a REST API request
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    // Filter out unwanted requests
    if (lum_should_skip_tracking($request_uri)) {
        return;
    }
    // Instead of processing immediately, queue it for background execution
    lum_schedule_background_tracking();
}
add_action('template_redirect', 'lum_track_visitor');

// ======================================
// BACKGROUND PROCESSING
// ======================================

// Schedule background tracking via AJAX
function lum_schedule_background_tracking() {
    // Use a small inline script to fire off an async request
    add_action('wp_footer', function() {
        $nonce = wp_create_nonce('lum_track_nonce');
        ?>
        <script>
        (function() {
            // Don't track if user is a bot (client-side check)
            if (/(bot|crawl|spider|slurp)/i.test(navigator.userAgent)) {
                return;
            }
			// Fire tracking as a non-blocking beacon, after load
            function fireTracking() {
				// Set a persistent Visitor ID Cookie if not already set
				if (!document.cookie.split(';').some(c => c.trim().startsWith('lum_visitor_id='))) {
					const vid = 'v_' + Math.random().toString(36).substr(2, 12) + Date.now().toString(36);
					document.cookie = 'lum_visitor_id=' + vid + '; path=/; max-age=' + (365*24*60*60) + '; SameSite=Lax';
				}
                var body = 'action=lum_background_track&nonce=<?php echo $nonce; ?>&page_url=' + encodeURIComponent(window.location.href);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', body);
                } else {
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, keepalive: true });
                }
            }
            if (document.readyState === 'complete') {
                ('requestIdleCallback' in window) ? requestIdleCallback(fireTracking) : setTimeout(fireTracking, 0);
            } else {
                window.addEventListener('load', function() {
                    ('requestIdleCallback' in window) ? requestIdleCallback(fireTracking) : setTimeout(fireTracking, 0);
                });
            }
        })();
        </script>
        <?php
    }, 99); // Low priority to execute last
}

// Background tracking handler
function lum_background_track() {
    // Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'lum_track_nonce')) {
        wp_die('Invalid request');
    }
    // Get or create a persistent visitor ID via Cookie
    $visitor_id = isset($_COOKIE['lum_visitor_id']) ? sanitize_text_field($_COOKIE['lum_visitor_id']) : '';
    if (empty($visitor_id)) {
        // Can't set cookies in AJAX response usefully, so fall back to an ID derived from the anonymized IP (keeps privacy consistent)
        $visitor_id = 'v_' . md5(lum_anonymize_ip(lum_get_client_ip()) . $_SERVER['HTTP_USER_AGENT']);
    }
    // Now do the actual Tracking
    global $wpdb;
    $table_name = $wpdb->prefix . 'er_live_visitors';
    $ip_address = lum_get_client_ip();
    $ip_address = lum_anonymize_ip($ip_address);
    $raw_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : get_site_url() . '/';
	$page_url = lum_clean_page_url(parse_url($raw_url, PHP_URL_PATH));
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    // Skip known bots (double-check server-side)
    if (lum_is_bot($user_agent)) {
        wp_die('Bot detected');
    }
	$geo_data = lum_get_geolocation($ip_address);
    if ($geo_data) {
        $current_time = current_time('mysql');
        // Single atomic Query - Insert new Visitor or update existing on duplicate IP
		$wpdb->query($wpdb->prepare(
			"INSERT INTO $table_name
				(visitor_id, ip_address, latitude, longitude, city, country, country_code, page_url, user_agent, last_seen, visit_time)
			VALUES
				(%s, %s, %f, %f, %s, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				last_seen = VALUES(last_seen),
				page_url = VALUES(page_url),
				user_agent = VALUES(user_agent)",
			$visitor_id,
			$ip_address,
			$geo_data['lat'],
			$geo_data['lon'],
			$geo_data['city'],
			$geo_data['country'],
			$geo_data['countryCode'],
			$page_url,
			$user_agent,
			$current_time,
			$current_time
		));
		// Map's own per-view log (for the past table's reconcilable "X views from Y locations"). Independent of er_post_stats and the Views snippet.
		// DEDUP: one row per visitor per page per 24h. The Past-list "views" column therefore reads as unique daily visitors, not raw page loads. Keyed on visitor_id + page_url, mirroring the geo-cache transient pattern used above. Does NOT affect the map dots (those read from er_live_visitors).
		$dedup_key = 'lum_view_' . md5($visitor_id . '|' . $page_url);
		if (get_transient($dedup_key) === false) {
			$wpdb->insert(
				$wpdb->prefix . 'er_map_views',
				array(
					'page_url'  => $page_url,
					'city'      => !empty($geo_data['city']) ? $geo_data['city'] : null,
					'viewed_at' => $current_time,
				),
				array('%s', '%s', '%s')
			);
			set_transient($dedup_key, 1, DAY_IN_SECONDS);
		}
    }
    wp_die('OK'); // Important for AJAX
}
add_action('wp_ajax_lum_background_track', 'lum_background_track');
add_action('wp_ajax_nopriv_lum_background_track', 'lum_background_track');

// ======================================
// FILTERING & UTILITIES
// ======================================

// Check if request should be skipped
function lum_should_skip_tracking($uri) {
    // File extensions to ignore (assets)
    $skip_extensions = array(
        'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'otf', // fonts
        'mp4', 'webm', 'ogg', 'mp3', 'wav', // media
        'pdf', 'zip', 'tar', 'gz', // documents / archives
        'xml', 'json', 'txt' // data files
    );
    // Check file extension
    $path = parse_url($uri, PHP_URL_PATH);
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    if (in_array(strtolower($extension), $skip_extensions)) {
        return true;
    }
    // Skip common WP technical URLs and old stuff
    $skip_patterns = array(
        // WordPress Core
        '/wp-admin/',
        '/wp-content/',
        '/wp-includes/',
        '/wp-json/',
        '/wp-login',
        '/wp-cron.php',
        '/xmlrpc.php',
        '/embed/',
        '/trackback/',
        // Cache & Performance
        '/litespeed/',
        '/cache/',
        '/amp/',
        // SEO & Standards
        '/.well-known/',
        'robots.txt',
        'sitemap',
        'feed',
        // Assets & Technical
        '/favicon.ico',
        '.map',
        // Query Parameters
        '?replytocom=',
        'preview=true',
        // Site-Specific Legacy
        '/site-forum/',
        '/jAlbums/',
    );
    foreach ($skip_patterns as $pattern) {
        if (strpos($uri, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

// Clean and normalize page URLs
function lum_clean_page_url($uri) {
    // Remove query parameters for cleaner display (optional)
    $clean_uri = strtok($uri, '?');
    // Get the site URL to create full URLs
    $site_url = get_site_url();
    // If it's just root, return full URL
    if ($clean_uri === '/' || $clean_uri === '') {
        return $site_url . '/';
    }
    // Return full URL for display
    return $site_url . $clean_uri;
}

// Detect if user agent is a bot
function lum_is_bot($user_agent) {
    if (empty($user_agent)) {
        return true;
    }
    $bot_patterns = array(
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
        'googlebot', 'bingbot', 'yahoo', 'baiduspider',
        'facebookexternalhit', 'twitterbot', 'rogerbot',
        'linkedinbot', 'embedly', 'showyoubot', 'outbrain',
        'pinterest', 'slackbot', 'vkshare', 'w3c_validator',
        'redditbot', 'applebot', 'whatsapp', 'flipboard',
        'tumblr', 'bitlybot', 'skypeuripreview', 'nuzzel',
        'discordbot', 'qwantify', 'pinterestbot', 'bitrix',
        'semrushbot', 'ahrefsbot', 'dotbot', 'mj12bot'
    );
    $user_agent_lower = strtolower($user_agent);
    foreach ($bot_patterns as $pattern) {
        if (strpos($user_agent_lower, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

// ======================================
// DATABASE MAINTENANCE
// ======================================

// Cleanup old visitor records daily
function lum_cleanup_old_visitors() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'er_live_visitors';
    $threshold = date('Y-m-d H:i:s', strtotime('-90 days'));
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE last_seen < %s",
        $threshold
    ));
    if ($deleted === false) {
        error_log('Live User Map: Cleanup failed - database error');
    } else {
        error_log("Live User Map: Cleaned up $deleted old visitor records");
    }
    // Prune the map's own view log on the same 90-day cutoff, so both tables
    // age in lockstep and er_map_views stays bounded.
    $views_table = $wpdb->prefix . 'er_map_views';
    $deleted_views = $wpdb->query($wpdb->prepare(
        "DELETE FROM $views_table WHERE viewed_at < %s",
        $threshold
    ));
    if ($deleted_views === false) {
        error_log('Live User Map: View-log cleanup failed - database error');
    } else {
        error_log("Live User Map: Cleaned up $deleted_views old view-log records");
    }
}
add_action('lum_daily_cleanup', 'lum_cleanup_old_visitors');

// Schedule daily cleanup
function lum_schedule_cleanup() {
    if (!wp_next_scheduled('lum_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'lum_daily_cleanup');
    }
}
add_action('init', 'lum_schedule_cleanup');

// ======================================
// IP & GEOLOCATION HANDLING
// ======================================

// Get client IP address
// Uses REMOTE_ADDR only. The site is not behind a trusted reverse proxy that
// sets a real-IP header, so forwarded headers (X-Forwarded-For, Client-IP,
// etc.) are visitor-controlled and spoofable — trusting them would let anyone
// place themselves anywhere on the map and poison the IP-based visitor ID.
// This also matches how every other snippet on the site reads the client IP.
function lum_get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

// Anonymize IP address (IPv4 and IPv6)
function lum_anonymize_ip($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return preg_replace('/\.\d+$/', '.0', $ip);
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $ip = inet_pton($ip);
        if ($ip !== false) {
            $ip = substr($ip, 0, 8) . str_repeat("\0", 8);
            return inet_ntop($ip);
        }
    }
    return $ip;
}

// Get geolocation from IP - OPTIMIZED VERSION
function lum_get_geolocation($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    $transient_key = 'lum_geo_' . md5($ip);
    $cached = get_transient($transient_key);
    if ($cached !== false) {
        return $cached;
    }
    // Use a local file-based cache for even faster subsequent lookups
    $cache_dir = WP_CONTENT_DIR . '/cache/lum-geo/';
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    $cache_file = $cache_dir . md5($ip) . '.json';
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 30 * DAY_IN_SECONDS) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            set_transient($transient_key, $cached_data, 30 * DAY_IN_SECONDS);
            return $cached_data;
        }
    }
    // Add a lock to prevent duplicate requests
    $lock_key = $transient_key . '_lock';
    if (get_transient($lock_key)) {
        return false; // Another request is processing
    }
    set_transient($lock_key, true, 30); // 30 second lock
	$response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,lat,lon", array(
        'timeout' => 5, // Reduced timeout for background processing
        // NB: ip-api.com's free tier is HTTP-only (HTTPS requires their paid Pro plan), so there is no TLS handshake on this request. No sslverify flag is needed or meaningful here.
    ));
    delete_transient($lock_key);
    if (is_wp_error($response)) {
        error_log('LUM Geo Error: ' . $response->get_error_message());
        return false;
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (isset($data['status']) && $data['status'] === 'success') {
        // Cache to file for persistence
        file_put_contents($cache_file, json_encode($data));
        set_transient($transient_key, $data, 30 * DAY_IN_SECONDS);
        return $data;
    }
    return false;
}

// ======================================
// AJAX ENDPOINTS
// ======================================

// AJAX endpoint to get map data
function lum_get_map_data() {
    // More permissive nonce check
    if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'lum_map_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'er_live_visitors';
    $current_time = current_time('timestamp');
    $live_threshold = date('Y-m-d H:i:s', $current_time - (15 * 60)); // Increased to 15 minutes
    $history_threshold = date('Y-m-d H:i:s', $current_time - (90 * 24 * 60 * 60));
    $live_users = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            id, latitude, longitude, city, country, country_code, 
            page_url, last_seen, user_agent,
            TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago
        FROM $table_name 
        WHERE last_seen >= %s 
        AND latitude IS NOT NULL 
        AND longitude IS NOT NULL
        AND latitude != 0
        AND longitude != 0
        ORDER BY last_seen DESC",
        $live_threshold
    ));
    $past_users = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            id, latitude, longitude, city, country, country_code, 
            page_url, last_seen, user_agent,
            TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago
        FROM $table_name 
        WHERE last_seen < %s 
        AND last_seen >= %s
        AND latitude IS NOT NULL 
        AND longitude IS NOT NULL
        AND latitude != 0
        AND longitude != 0
        ORDER BY last_seen DESC
        LIMIT 500",
        $live_threshold,
        $history_threshold
    ));
    // Attach a reconcilable 90-day view count and location count per page, both
    // read from the map's OWN view log (er_map_views) — same rows, same window,
    // so locations can never exceed views. Independent of er_post_stats and the
    // Views snippet. Non-resolving archive URLs are flagged for hiding.
    $mv_table  = $wpdb->prefix . 'er_map_views';
    $mv_since  = $history_threshold;
    $mv_cache  = array();
    foreach ($past_users as $u) {
        // Normalize list/archive pagination (/page/N/) to its base page so those
        // views fold into the real page's row. Only targets the literal /page/N/
        // segment — single-post <!--nextpage--> URLs (/slug/2/) are NOT affected.
        $base_url = preg_replace('#/page/\d+/?$#', '/', $u->page_url);
        $u->page_url = $base_url; // dots already plotted; this only groups the table
        if (!array_key_exists($base_url, $mv_cache)) {
            // Match the exact base URL or its /page/N/ pagination variants only,
            // so sibling pages (e.g. /photo-album-2/) are never wrongly merged.
            $base_trim = rtrim($base_url, '/');
            $like_page = $wpdb->esc_like($base_trim) . '/page/%';
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT COUNT(*) AS views,
                        COUNT(DISTINCT NULLIF(city, '')) AS locations
                 FROM $mv_table
                 WHERE (page_url = %s OR page_url = %s OR page_url LIKE %s)
                 AND viewed_at >= %s",
                $base_url, $base_trim, $like_page, $mv_since
            ));
            $mv_cache[$base_url] = array(
                'is_post'   => url_to_postid($base_url) > 0,
                'views'     => $row ? (int) $row->views : 0,
                'locations' => $row ? (int) $row->locations : 0,
            );
        }
        $u->is_post   = $mv_cache[$base_url]['is_post'];
        $u->views     = $mv_cache[$base_url]['views'];
        $u->locations = $mv_cache[$base_url]['locations'];
    }
    // Trim coordinate precision to 4 decimal places to reduce JSON payload size
    $round_coords = function($users) {
        return array_map(function($u) {
            $u->latitude  = round((float) $u->latitude,  4);
            $u->longitude = round((float) $u->longitude, 4);
            return $u;
        }, $users);
    };
    wp_send_json_success(array(
        'live' => $round_coords($live_users),
        'past' => $round_coords($past_users),
        'counts' => array(
            'live' => count($live_users),
            'past' => count($past_users)
        )
    ));
}
add_action('wp_ajax_lum_get_map_data', 'lum_get_map_data');
add_action('wp_ajax_nopriv_lum_get_map_data', 'lum_get_map_data');

// ======================================
// FRONTEND DISPLAY
// ======================================

// Load Assets
function lum_enqueue_map_assets() {
    if (is_page(152324)) {
        wp_enqueue_style('leaflet-css', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css');
        wp_enqueue_script('leaflet-js', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js', [], null, true);
        wp_enqueue_script('terminator-js', get_stylesheet_directory_uri() . '/my-assets/L.Terminator.js', ['leaflet-js'], null, true); // From original Source
    }
}
add_action('wp_enqueue_scripts', 'lum_enqueue_map_assets');

// Shortcode
function lum_map_shortcode($atts) {
	nocache_headers();
    $atts = shortcode_atts(array(
        'height' => '600px',
        'zoom' => '2'
    ), $atts);
    ob_start();
    ?>
    <div id="live-user-map" style="height: <?php echo esc_attr($atts['height']); ?>; width: 100%; border-radius: 15px; position: relative; z-index: 1;"></div>
	<div id="map-stats" style="margin-top: 15px; padding: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
		<div style="display: flex; align-items: center; font-size: 1em;">
			<span>Live Visitors: <strong><span id="live-count">0</span></strong></span>
			<span style="margin-left: 15px;">Last Updated: <strong><span id="last-update">-</span></strong></span>
		</div>
		<div style="display: flex; align-items: center; font-size: 1em;">
			<span><strong>Legend:</strong></span>
			<span style="display: inline-flex; align-items: center; margin-left: 15px;">
				<span style="display: inline-block; width: 12px; height: 12px; background: #28a745; border-radius: 50%; border: 2px solid rgba(40, 167, 69, 0.3); margin-right: 5px;"></span>
				Live Visitors
			</span>
			<span style="display: inline-flex; align-items: center; margin-left: 15px;">
				<span style="display: inline-block; width: 12px; height: 12px; background: #007bff; border-radius: 50%; border: 2px solid rgba(0, 123, 255, 0.5); opacity: 0.8; margin-right: 5px;"></span>
				Past Visitors
			</span>
		</div>
	</div>
    <div class="wp-block-table" id="visited-pages">
        <h3 style="margin-top: 0; margin-bottom: 25px; font-weight: 700; font-size: 20px; line-height: 1.5;">Currently Visited Pages</h3>
        <div id="pages-list" style="max-height: 350px; overflow-y: auto;">
            <p style="color: #999999;">Loading...</p>
        </div>
    </div>
	<div class="wp-block-table" id="past-visited-pages">
        <h3 style="margin-top: 25px; margin-bottom: 25px; font-weight: 700; font-size: 20px; line-height: 1.5;">Past Visited Pages</h3>
        <div id="past-pages-list" style="max-height: 350px; overflow-y: auto;">
            <p style="color: #999999;">Loading...</p>
        </div>
    </div>

    <style>
	
    #live-user-map {position: relative !important; z-index: 1 !important;}
    .leaflet-container {position: relative !important; z-index: 1 !important;}
    @keyframes pulse {
        0% {box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);}
        70% {box-shadow: 0 0 0 20px rgba(40, 167, 69, 0);}
        100% {box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);}
    }
    .leaflet-popup-content {margin: 15px; min-width: 220px;}
    .popup-title {font-size: var(--er-fs-sm); font-weight: 600; margin-bottom: 10px; color: #333333;}
    .popup-info {font-size: var(--er-fs-xs); line-height: 1.8; color: #666666;}
    .popup-info strong {color: #333333; display: inline-block; min-width: 80px;}
    .popup-badge {display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: var(--er-fs-xs); font-weight: 600; margin-left: 5px;}
    .badge-live {background: #d4edda; color: #155724;}
    .badge-past {background: #d1ecf1; color: #0c5460;}
    .leaflet-control-attribution a {pointer-events: auto !important;}
	
	#visited-pages table,
	#past-visited-pages table {
		table-layout: fixed !important;
		width: 100% !important;
	}
	
	#visited-pages col.col-page,
	#past-visited-pages col.col-page {width: 58%;}
	#visited-pages col.col-visitors,
	#past-visited-pages col.col-visitors {width: 15%;}
	#visited-pages col.col-location,
	#past-visited-pages col.col-location {width: 27%;}

	#visited-pages .url-short,
	#past-visited-pages .url-short {display: none;}

	@media (max-width: 600px) {
			#visited-pages .url-full,
			#past-visited-pages .url-full {display: none;}
			#visited-pages .url-short,
			#past-visited-pages .url-short {display: inline;}
			#visited-pages table th,
			#visited-pages table td,
			#past-visited-pages table th,
			#past-visited-pages table td {font-size: var(--er-fs-sm);}
		}

    </style>

    <script>
	(function() {
        let map, liveMarkers = [], pastMarkers = [], terminator;
        let isFirstLoad = true;
        let userHasInteracted = false;
        // Shared HTML escaper — used by both the map popups and the pages list to neutralise untrusted values (city/country from the geo API, page_url from the visitor's own request) before innerHTML/bindPopup
        const escapeHtml = (str) => String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        // Decode percent-encoded UTF-8 (e.g. %CF%83 -> σ) for display only; falls back to raw string if malformed
        const prettyUrl = (str) => { try { return decodeURIComponent(String(str)); } catch (e) { return String(str); } };
        function initMap() {
            map = L.map('live-user-map', {
                center: [20, 0],
                zoom: <?php echo intval($atts['zoom']); ?>,
                worldCopyJump: true
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> Contributors',
                maxZoom: 18
            }).addTo(map);
            map.on('zoomstart movestart', function() {
                userHasInteracted = true;
            });
            setTimeout(() => {
                document.querySelectorAll('.leaflet-control-attribution a').forEach(link => {
                    link.setAttribute('target', '_blank');
                    link.setAttribute('rel', 'noopener noreferrer');
                });
            }, 100);
            terminator = L.terminator().addTo(map);
            setInterval(function() {
                terminator.setTime();
            }, 60000);
            loadMapData();
            setInterval(loadMapData, 60000); // Refresh every 60 seconds
        }
        function loadMapData() {
            const url = '<?php echo admin_url('admin-ajax.php'); ?>?action=lum_get_map_data&nonce=<?php echo wp_create_nonce('lum_map_nonce'); ?>&_=' + Date.now(); // Added cache-busting
            fetch(url, {
                method: 'GET',
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateMarkers(data.data, isFirstLoad);
                        updateStats(data.data.counts);
                        updatePagesList(data.data.live);
						updatePastPagesList(data.data.past);
                        isFirstLoad = false;
                    } else {
                        console.error('AJAX returned error:', data);
                    }
                })
                .catch(error => {
                    console.error('Error loading map data:', error);
                    document.getElementById('pages-list').innerHTML = '<p style="color: #dc3545;">Error loading data. Check console.</p>';
                });
        }

        function updateMarkers(data, skipZoom) {
            // Better cleanup
            liveMarkers.forEach(marker => {
                marker.closePopup();
                marker.off();
                map.removeLayer(marker);
            });
            pastMarkers.forEach(marker => {
                marker.closePopup();
                marker.off();
                map.removeLayer(marker);
            });
            liveMarkers = [];
            pastMarkers = [];
            const allLatLngs = [];
            data.live.forEach((user) => {
                const icon = L.divIcon({
                    className: '',
                    html: '<div style="background: #28a745; width: 16px; height: 16px; border-radius: 50%; border: 3px solid rgba(40, 167, 69, 0.3); animation: pulse 2s infinite; box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); position: absolute; margin-left: -8px; margin-top: -8px;"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                });
                const lat = parseFloat(user.latitude);
                const lng = parseFloat(user.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = L.marker([lat, lng], { icon: icon })
                        .bindPopup(createPopup(user, true))
                        .addTo(map);
                    liveMarkers.push(marker);
                    allLatLngs.push([lat, lng]);
                }
            });
            data.past.forEach((user) => {
                const icon = L.divIcon({
                    className: '',
                    html: '<div style="background: #007bff; width: 12px; height: 12px; border-radius: 50%; border: 2px solid rgba(0, 123, 255, 0.5); opacity: 0.8; position: absolute; margin-left: -6px; margin-top: -6px;"></div>',
                    iconSize: [12, 12],
                    iconAnchor: [6, 6]
                });
                const lat = parseFloat(user.latitude);
                const lng = parseFloat(user.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    const marker = L.marker([lat, lng], { icon: icon })
                        .bindPopup(createPopup(user, false))
                        .addTo(map);
                    pastMarkers.push(marker);
                    allLatLngs.push([lat, lng]);
                }
            });
            if (!skipZoom && !userHasInteracted && allLatLngs.length > 0) {
                if (allLatLngs.length > 1) {
                    const bounds = L.latLngBounds(allLatLngs);
                    map.fitBounds(bounds, { padding: [50, 50], maxZoom: 10 });
                } else if (allLatLngs.length === 1) {
                    map.setView(allLatLngs[0], 5);
                }
            }
        }
        
        function createPopup(user, isLive) {
            const timeAgo = formatTimeAgo(parseInt(user.seconds_ago));
            const badge = isLive ? 
                '<span class="popup-badge badge-live">LIVE</span>' : 
                '<span class="popup-badge badge-past">PAST</span>';
            const browser = parseBrowser(user.user_agent);
            // Extract just the path from full URL for display
            const displayUrl = user.page_url.replace(/^https?:\/\/[^\/]+/, '') || '/';
			return `
                <div class="popup-title">
                    🌍 ${escapeHtml(user.city || 'Unknown City')}${badge}
                </div>
                <div class="popup-info">
                    <div><strong>Country:</strong> ${escapeHtml(user.country || '')} ${getFlagEmoji(user.country_code)}</div>
                    <div><strong>Last Seen:</strong> ${timeAgo}</div>
                    <div><strong>Page:</strong> ${escapeHtml(truncateUrl(displayUrl))}</div>
                    <div><strong>Browser:</strong> ${browser}</div>
                    <div><strong>Location:</strong> ${parseFloat(user.latitude).toFixed(4)}, ${parseFloat(user.longitude).toFixed(4)}</div>
                </div>
            `;
        }
        
        function formatTimeAgo(seconds) {
            if (seconds < 60) return `${seconds} seconds ago`;
            if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
            return `${Math.floor(seconds / 86400)} days ago`;
        }
        
        function truncateUrl(url) {
            return url.length > 40 ? url.substring(0, 40) + '...' : url;
        }

		function shortenUrl(url) {
            try {
                const u = new URL(url);
                return u.pathname === '/' ? 'Home' : prettyUrl(u.pathname);
            } catch (e) {
                return url;
            }
        }
        
        function parseBrowser(ua) {
            if (!ua) return 'Unknown';
            if (ua.includes('Edge')) return 'Edge';
            if (ua.includes('Chrome')) return 'Chrome';
            if (ua.includes('Firefox')) return 'Firefox';
            if (ua.includes('Safari')) return 'Safari';
            return 'Other';
        }
        
        function getFlagEmoji(countryCode) {
            if (!countryCode) return '';
            const codePoints = countryCode
                .toUpperCase()
                .split('')
                .map(char => 127397 + char.charCodeAt());
            return String.fromCodePoint(...codePoints);
        }
        
        function updateStats(counts) {
            const liveCountEl = document.getElementById('live-count');
            const lastUpdateEl = document.getElementById('last-update');
            if (liveCountEl) {
                liveCountEl.textContent = counts.live;
            }
            if (lastUpdateEl) {
                lastUpdateEl.textContent = new Date().toLocaleTimeString();
            }
        }
        
        function updatePagesList(liveUsers) {
            const pagesListEl = document.getElementById('pages-list');
            if (!pagesListEl) return;
            if (liveUsers.length === 0) {
                pagesListEl.innerHTML = '<p style="color: #999999;">No live visitors</p>';
                return;
            }
            const pageMap = new Map();
            liveUsers.forEach(user => {
                const url = user.page_url;
                if (!pageMap.has(url)) {
                    pageMap.set(url, { count: 0, city: user.city, country: user.country });
                }
                pageMap.get(url).count++;
            });
            let html = '<table>';
			html += '<colgroup><col class="col-page"><col class="col-visitors"><col class="col-location"></colgroup>';
            html += '<thead><tr><th>Page</th><th>Visitors</th><th>Location</th></tr></thead><tbody>';
            Array.from(pageMap.entries()).forEach(([url, data]) => {
                const safeUrl = escapeHtml(url);
                const safeCity = escapeHtml(data.city || '');
                const safeCountry = escapeHtml(data.country || '');
                html += `<tr>
                    <td><a href="${safeUrl}" target="_blank"><span class="url-full">${escapeHtml(prettyUrl(url))}</span><span class="url-short">${escapeHtml(shortenUrl(url))}</span></a></td>
                    <td>${data.count}</td>
                    <td>${safeCity}, ${safeCountry}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            pagesListEl.innerHTML = html;
        }

		function updatePastPagesList(pastUsers) {
            const pagesListEl = document.getElementById('past-pages-list');
            if (!pagesListEl) return;
            if (pastUsers.length === 0) {
                pagesListEl.innerHTML = '<p style="color: #999999;">No past visitors</p>';
                return;
            }
            const pageMap = new Map();
            pastUsers.forEach(user => {
                if (!user.is_post) return;
                const url = user.page_url;
                if (!pageMap.has(url)) {
                    pageMap.set(url, { views: user.views || 0, locations: user.locations || 0 });
                }
            });
            if (pageMap.size === 0) {
                pagesListEl.innerHTML = '<p style="color: #999999;">No past visitors</p>';
                return;
            }
            let html = '<table>';
			html += '<colgroup><col class="col-page"><col class="col-visitors"><col class="col-location"></colgroup>';
            html += '<thead><tr><th>Page</th><th>Views</th><th>Location</th></tr></thead><tbody>';
            Array.from(pageMap.entries())
                .sort((a, b) => b[1].views - a[1].views)
				.forEach(([url, data]) => {
                    const safeUrl = escapeHtml(url);
                    const locCount = data.locations;
                    const locLabel = locCount + (locCount === 1 ? ' location' : ' Locations');
                    html += `<tr>
                        <td><a href="${safeUrl}" target="_blank"><span class="url-full">${escapeHtml(prettyUrl(url))}</span><span class="url-short">${escapeHtml(shortenUrl(url))}</span></a></td>
                        <td>${Number(data.views).toLocaleString()}</td>
                        <td>${escapeHtml(locLabel)}</td>
                    </tr>`;
                });
            html += '</tbody></table>';
            pagesListEl.innerHTML = html;
        }

		window.addEventListener('load', initMap);
    })();
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('live_user_map', 'lum_map_shortcode');
