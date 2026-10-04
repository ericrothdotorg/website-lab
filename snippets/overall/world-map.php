<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* ============================================================================
 * WORLD MAP — visitor tracking, map data, front-page cover, Site Reach map
 * ----------------------------------------------------------------------------
 * Replaces the snippets "Visitor Map" and "Frontpage Cover".
 * Scope: "Run snippet everywhere" (mandatory). The tracking beacon and the map
 * data are answered via admin-ajax and the cleanup runs via wp-cron; in both
 * contexts a front-end-only snippet would never register its hooks.
 *
 * MODULES
 *   0  Config ........ the few numbers you may want to change
 *   1  Tracking ...... PRODUCER. Beacon, geo lookup, er_live_visitors,
 *                      er_map_views, post views (er_track_post_views), daily
 *                      cleanup. Unchanged code from "Visitor Map". Everything
 *                      else on the site that counts visitors depends on it.
 *   2  Data API ...... one public, cached endpoint: er_map_data (scope live|full)
 *   3  Map base ...... SVG world, shared CSS, shared JS engine (window.ERMap)
 *   4  Cover ......... [er_frontpage_cover]            (front page)
 *   5  Site Reach .... [live_user_map]                 (map)
 *                      [currently_visited_pages]       (table)
 *                      [past_visited_pages]            (table)
 *
 * DEPENDENCIES
 *   4 and 5 use 2 + 3. 2 reads what 1 writes. 4 and 5 never use each other:
 *   module 4 can be changed or deleted and Site Reach keeps working, and the
 *   other way round. Changing module 3 affects both maps.
 *   Only one map per page (SVG ids in module 3 are fixed).
 * ========================================================================== */

/* ============================================================================
 * 0  CONFIG
 * ========================================================================== */

/**
 * ORIGIN — where the site is run from. The cover's arcs leave from here.
 * Latitude, longitude, decimal degrees, south and west negative.
 *   Zurich    47.37,   8.54
 *   Bangkok   13.76, 100.50
 * After changing it: purge LiteSpeed.
 */
defined( 'ER_COVER_ORIGIN' ) || define( 'ER_COVER_ORIGIN', array( 47.37, 8.54 ) );

defined( 'ER_MAP_LIVE_MIN' ) || define( 'ER_MAP_LIVE_MIN',   15 );   // "live" = seen within this many minutes
defined( 'ER_MAP_DAYS' ) || define( 'ER_MAP_DAYS',       90 );   // history window (cleanup in module 1 uses 90 as well)
defined( 'ER_MAP_PAST_MAX' ) || define( 'ER_MAP_PAST_MAX',   2000 ); // max. past places (~11 km cells) on the Site Reach map
defined( 'ER_MAP_PAGES_MAX' ) || define( 'ER_MAP_PAGES_MAX',  100 );  // rows in [past_visited_pages]
defined( 'ER_MAP_CACHE_LIVE' ) || define( 'ER_MAP_CACHE_LIVE', 30 );   // seconds the live answer is cached (cover)
defined( 'ER_MAP_CACHE_FULL' ) || define( 'ER_MAP_CACHE_FULL', 60 );   // seconds the full answer is cached (Site Reach)

/* The two framings. Map units: Miller projection, 2000 × 1466.4.
   Must match LAND (cover JS) and B (Site Reach JS). */
defined( 'ER_MAP_BOX_COVER' ) || define( 'ER_MAP_BOX_COVER', '0 101.1 2000 1292.9' ); // all land, 84°N – Antarctica
defined( 'ER_MAP_BOX_REACH' ) || define( 'ER_MAP_BOX_REACH', '0 101.1 2000 1013.1' ); // 84°N – 60°S


/* Shortcodes are registered on init (after every snippet has loaded), so these
   always win, even if an old snippet with the same shortcode names still runs. */
add_action( 'init', static function () {
	add_shortcode( 'er_frontpage_cover', 'er_map_cover_shortcode' );       // module 4
	add_shortcode( 'live_user_map', 'er_reach_map_shortcode' );             // module 5
	add_shortcode( 'currently_visited_pages', 'er_reach_current_shortcode' );
	add_shortcode( 'past_visited_pages', 'er_reach_past_shortcode' );
}, 20 );

/* Self-check (admins only): warns if the replaced snippets "Visitor Map" or
   "Frontpage Cover" are still being executed. Function names in this snippet
   differ from theirs, so that can never cause a fatal error, but their old
   tracking beacon and endpoints would still run alongside. */
add_action( 'admin_notices', static function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	foreach ( array( 'lum_track_visitor' => 'Visitor Map', 'er_cover_live_points' => 'Frontpage Cover' ) as $fn => $name ) {
		if ( function_exists( $fn ) ) {
			$file = ( new ReflectionFunction( $fn ) )->getFileName();
			printf(
				'<div class="notice notice-warning"><p><strong>World Map:</strong> the old snippet &bdquo;%s&ldquo; is still being executed (%s), although it should be inactive. Fix: Snippets &rarr; Settings &rarr; switch &bdquo;direct file-based execution&ldquo; off, save, switch it on again, save.</p></div>',
				esc_html( $name ),
				esc_html( $file )
			);
		}
	}
} );

/* ============================================================================
 * 1  TRACKING (producer) — logic unchanged from "Visitor Map"
 *    Only the PHP function names are new (er_trk_*), so this snippet can never
 *    collide with the old one. Kept on purpose: the AJAX action
 *    lum_background_track (cached pages carry the beacon with it), the cron
 *    hook lum_daily_cleanup (already scheduled in the database), the cookie
 *    lum_visitor_id and all transient keys (geo cache, rate limit, dedup).
 * ========================================================================== */

// ======================================
// CORE TRACKING FUNCTIONS
// ======================================

// Track current visitor with filtering
function er_trk_track_visitor() {
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
    if (er_trk_should_skip_tracking($request_uri)) {
        return;
    }
    // Instead of processing immediately, queue it for background execution
    er_trk_schedule_background_tracking();
}
add_action('template_redirect', 'er_trk_track_visitor');

// ======================================
// BACKGROUND PROCESSING
// ======================================

// Schedule background tracking via AJAX
function er_trk_schedule_background_tracking() {
    // Use a small inline script to fire off an async request
    add_action('wp_footer', function() {
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
				// Send as form-encoded so PHP populates $_POST and admin-ajax
				// routes the action. A raw sendBeacon string would go out as
				// text/plain, which PHP does NOT parse into $_POST -> admin-ajax
				// sees no action and returns HTTP 400.
                var params = new URLSearchParams();
                params.set('action', 'lum_background_track');
                params.set('page_url', window.location.href);
				params.set('post_id', '<?php echo (int) ((is_singular() && empty($GLOBALS['er_synthetic_page'])) ? get_the_ID() : 0); ?>');
                var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                if (navigator.sendBeacon) {
                    var blob = new Blob([params.toString()], { type: 'application/x-www-form-urlencoded' });
                    navigator.sendBeacon(ajaxUrl, blob);
                } else {
                    fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString(), keepalive: true });
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
function er_trk_background_track() {
    // Runs for logged-out visitors on full-page-cached pages, so a nonce baked
    // into the cached footer is unreliable. This endpoint only writes an
    // anonymized, geo-only visitor row (no auth state), so it is guarded by:
    // a POST-only check, a per-IP rate limit, and the server-side bot filter
    // already applied further below — no nonce, no host-string matching.
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wp_die('Invalid request');
    }
    // Per-IP rate limit: cap writes from one IP to protect against flooding.
    $rl_key = 'lum_track_rl_' . md5(er_trk_anonymize_ip(er_trk_get_client_ip()));
    $rl_hits = (int) get_transient($rl_key);
    if ($rl_hits >= 30) { // max ~30 tracking writes per IP per minute
        wp_die('Rate limited');
    }
    set_transient($rl_key, $rl_hits + 1, MINUTE_IN_SECONDS);
    // Get or create a persistent visitor ID via Cookie
    $visitor_id = isset($_COOKIE['lum_visitor_id']) ? sanitize_text_field($_COOKIE['lum_visitor_id']) : '';
    if (empty($visitor_id)) {
        // Can't set cookies in AJAX response usefully, so fall back to an ID derived from the anonymized IP (keeps privacy consistent)
        $visitor_id = 'v_' . md5(er_trk_anonymize_ip(er_trk_get_client_ip()) . $_SERVER['HTTP_USER_AGENT']);
    }
    // Now do the actual Tracking
    global $wpdb;
    $table_name = $wpdb->prefix . 'er_live_visitors';
    $ip_address = er_trk_get_client_ip();
    $ip_address = er_trk_anonymize_ip($ip_address);
    $raw_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : get_site_url() . '/';
	$page_url = er_trk_clean_page_url(parse_url($raw_url, PHP_URL_PATH));
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    // Skip known bots (double-check server-side)
    if (er_trk_is_bot($user_agent)) {
        wp_die('Bot detected');
    }
	$geo_data = er_trk_get_geolocation($ip_address);
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
    // View-Zaehlung: Gleiche Regel wie die Map (1 Besucher / 1 Seite / 24h).
    // Eigener Dedup-Key und bewusst ausserhalb des Geo-Blocks, damit ein Ausfall der Geo-API die Zaehlung nicht blockiert.
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if ($post_id && !is_user_logged_in() && function_exists('er_track_post_views')) {
        $view_key = 'er_view_' . md5($visitor_id . '|' . $post_id);
        if (get_transient($view_key) === false) {
            er_track_post_views($post_id);
            set_transient($view_key, 1, DAY_IN_SECONDS);
        }
    }
    wp_die('OK'); // Important for AJAX
}
add_action('wp_ajax_lum_background_track', 'er_trk_background_track');
add_action('wp_ajax_nopriv_lum_background_track', 'er_trk_background_track');

// ======================================
// FILTERING & UTILITIES
// ======================================

// Check if request should be skipped
function er_trk_should_skip_tracking($uri) {
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
function er_trk_clean_page_url($uri) {
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
function er_trk_is_bot($user_agent) {
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
function er_trk_cleanup_old_visitors() {
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
add_action('lum_daily_cleanup', 'er_trk_cleanup_old_visitors');

// Schedule daily cleanup
function er_trk_schedule_cleanup() {
    if (!wp_next_scheduled('lum_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'lum_daily_cleanup');
    }
}
add_action('init', 'er_trk_schedule_cleanup');

// ======================================
// IP & GEOLOCATION HANDLING
// ======================================

// Get client IP address
// Uses REMOTE_ADDR only. The site is not behind a trusted reverse proxy that
// sets a real-IP header, so forwarded headers (X-Forwarded-For, Client-IP,
// etc.) are visitor-controlled and spoofable — trusting them would let anyone
// place themselves anywhere on the map and poison the IP-based visitor ID.
// This also matches how every other snippet on the site reads the client IP.
function er_trk_get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
}

// Anonymize IP address (IPv4 and IPv6)
function er_trk_anonymize_ip($ip) {
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
function er_trk_get_geolocation($ip) {
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

/* ============================================================================
 * 2  DATA API — admin-ajax.php?action=er_map_data&scope=live|full
 *    Public, read-only, no nonce (pages are full-page cached).
 *    Every visitor gets the same answer, so it is built at most once per
 *    cache period, no matter how many people are watching.
 *
 *    Time zone: last_seen / viewed_at are stored in site time
 *    (current_time('mysql')), the database clock (NOW()) runs on UTC.
 *    So every cutoff and every "ago" is computed against site time from PHP,
 *    never against NOW().
 * ========================================================================== */

add_action( 'wp_ajax_er_map_data', 'er_map_data' );
add_action( 'wp_ajax_nopriv_er_map_data', 'er_map_data' );

function er_map_data() {
	$scope = ( isset( $_REQUEST['scope'] ) && 'full' === $_REQUEST['scope'] ) ? 'full' : 'live';
	$key   = 'er_map_' . $scope;
	$data  = get_transient( $key );
	if ( false === $data ) {
		$data = 'full' === $scope ? er_map_build_full() : er_map_build_live();
		set_transient( $key, $data, 'full' === $scope ? ER_MAP_CACHE_FULL : ER_MAP_CACHE_LIVE );
	}
	wp_send_json_success( $data );
}

// Site-time timestamp string, $seconds ago.
function er_map_site_time( $seconds = 0 ) {
	return wp_date( 'Y-m-d H:i:s', time() - (int) $seconds );
}

// Cover: live places only, one per ~11 km cell, newest first. [[lat, lon], …]
function er_map_build_live() {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ROUND(latitude, 1) AS lat, ROUND(longitude, 1) AS lon
		   FROM {$wpdb->prefix}er_live_visitors
		  WHERE last_seen >= %s
		    AND latitude IS NOT NULL AND longitude IS NOT NULL
		    AND NOT (latitude = 0 AND longitude = 0)
		  GROUP BY lat, lon
		  ORDER BY MAX(last_seen) DESC
		  LIMIT 100",
		er_map_site_time( ER_MAP_LIVE_MIN * MINUTE_IN_SECONDS )
	) );
	return array_map(
		static fn( $r ) => array( (float) $r->lat, (float) $r->lon ),
		(array) $rows
	);
}

// Site Reach: live visitors, past places, most-visited pages.
function er_map_build_full() {
	global $wpdb;
	$lv       = $wpdb->prefix . 'er_live_visitors';
	$now      = er_map_site_time( 0 );
	$live_cut = er_map_site_time( ER_MAP_LIVE_MIN * MINUTE_IN_SECONDS );
	$since    = er_map_site_time( ER_MAP_DAYS * DAY_IN_SECONDS );

	// Live: one entry per visitor. Coordinates ~1 km, path only, browser name only.
	// Country names are not sent: the browser derives them from the code.
	$live = $wpdb->get_results( $wpdb->prepare(
		"SELECT latitude, longitude, city, country_code, page_url, user_agent,
		        TIMESTAMPDIFF(SECOND, last_seen, %s) AS ago
		   FROM $lv
		  WHERE last_seen >= %s
		    AND latitude IS NOT NULL AND longitude IS NOT NULL
		    AND NOT (latitude = 0 AND longitude = 0)
		  ORDER BY last_seen DESC
		  LIMIT 200",
		$now, $live_cut
	) );

	// Past: grouped per ~11 km cell; n = visitors there, ago = most recent one.
	$past = $wpdb->get_results( $wpdb->prepare(
		"SELECT ROUND(latitude, 1) AS lat, ROUND(longitude, 1) AS lon,
		        MAX(city) AS city, MAX(country_code) AS cc,
		        COUNT(*) AS n, TIMESTAMPDIFF(SECOND, MAX(last_seen), %s) AS ago
		   FROM $lv
		  WHERE last_seen >= %s AND last_seen < %s
		    AND latitude IS NOT NULL AND longitude IS NOT NULL
		    AND NOT (latitude = 0 AND longitude = 0)
		  GROUP BY lat, lon
		  ORDER BY MAX(last_seen) DESC
		  LIMIT %d",
		$now, $since, $live_cut, ER_MAP_PAST_MAX
	) );

	$path = static function ( $url ) {
		$p = wp_parse_url( (string) $url, PHP_URL_PATH );
		return $p ? $p : '/';
	};

	return array(
		'live'  => array_map( static fn( $r ) => array(
			'lat'     => round( (float) $r->latitude, 2 ),
			'lon'     => round( (float) $r->longitude, 2 ),
			'city'    => (string) $r->city,
			'cc'      => (string) $r->country_code,
			'page'    => $path( $r->page_url ),
			'browser' => er_map_browser( $r->user_agent ),
			'ago'     => max( 0, (int) $r->ago ),
		), (array) $live ),
		// Compact rows: [lat, lon, visitors, seconds ago, city, country code]
		'past'  => array_map( static fn( $r ) => array(
			(float) $r->lat,
			(float) $r->lon,
			(int) $r->n,
			max( 0, (int) $r->ago ),
			(string) $r->city,
			(string) $r->cc,
		), (array) $past ),
		'pages' => er_map_top_pages( $since, $path ),
	);
}

/* Most-visited pages from the map's own view log (one row per visitor, page
   and day). Pagination (/page/N/) is folded into its base page. Only URLs that
   resolve to a post or page are listed; that check is remembered for a day. */
function er_map_top_pages( $since, $path ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT page_url, city, COUNT(*) AS n
		   FROM {$wpdb->prefix}er_map_views
		  WHERE viewed_at >= %s
		  GROUP BY page_url, city",
		$since
	) );

	$pages = array();
	foreach ( (array) $rows as $r ) {
		$key = rtrim( preg_replace( '#/page/\d+/?$#', '', (string) $r->page_url ), '/' ) . '/';
		if ( ! isset( $pages[ $key ] ) ) {
			$pages[ $key ] = array( 'views' => 0, 'cities' => array() );
		}
		$pages[ $key ]['views'] += (int) $r->n;
		if ( '' !== (string) $r->city ) {
			$pages[ $key ]['cities'][ $r->city ] = true;
		}
	}
	uasort( $pages, static fn( $a, $b ) => $b['views'] <=> $a['views'] );

	$known = get_transient( 'er_map_post_urls' );
	$known = is_array( $known ) ? $known : array();
	$dirty = false;
	$out   = array();
	foreach ( $pages as $url => $p ) {
		if ( ! isset( $known[ $url ] ) ) {
			$known[ $url ] = url_to_postid( $url ) > 0 ? 1 : 0;
			$dirty         = true;
		}
		if ( ! $known[ $url ] ) {
			continue;
		}
		$out[] = array(
			'url'       => $path( $url ),
			'views'     => $p['views'],
			'locations' => count( $p['cities'] ),
		);
		if ( count( $out ) >= ER_MAP_PAGES_MAX ) {
			break;
		}
	}
	if ( $dirty ) {
		set_transient( 'er_map_post_urls', $known, DAY_IN_SECONDS );
	}
	return $out;
}

// Browser name only; the full user agent never leaves the server.
function er_map_browser( $ua ) {
	$ua = (string) $ua;
	if ( '' === $ua ) return 'Unknown';
	if ( false !== strpos( $ua, 'Edg' ) ) return 'Edge';
	if ( false !== strpos( $ua, 'OPR' ) ) return 'Opera';
	if ( false !== strpos( $ua, 'Firefox' ) || false !== strpos( $ua, 'FxiOS' ) ) return 'Firefox';
	if ( false !== strpos( $ua, 'Chrome' ) || false !== strpos( $ua, 'CriOS' ) ) return 'Chrome';
	if ( false !== strpos( $ua, 'Safari' ) ) return 'Safari';
	return 'Other';
}

/* ============================================================================
 * 3  MAP BASE — shared by modules 4 and 5
 *    er_map_layers( 'cover' | 'reach' ) returns the map markup.
 *    er_map_base_assets() prints the shared CSS once and queues the engine JS.
 * ========================================================================== */

function er_map_base_assets() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	add_action( 'wp_footer', 'er_map_engine_script', 19 ); // before the modules (20)
	return '<style id="er-map-css">' . er_map_base_css() . '</style>';
}

function er_map_engine_script() {
	wp_print_inline_script_tag( er_map_engine_js(), array( 'id' => 'er-map-js' ) );
}

/* Layer order — cover: land, markers, shade, scrim.
                 reach: land, shade, past dots, markers, zoom buttons. */
function er_map_layers( $mode ) {
	$cover = 'cover' === $mode;
	$open  = '<svg class="%s" viewBox="' . ( $cover ? ER_MAP_BOX_COVER : ER_MAP_BOX_REACH )
		. '" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" focusable="false">';

	$land = sprintf( $open, 'erc-map' )
		. '<rect class="erc-ocean" x="-9000" y="-9000" width="20000" height="20000"/>'
		. '<path class="erc-graticule" d="M166.7 0L166.7 1466.4M333.3 0L333.3 1466.4M500.0 0L500.0 1466.4M666.7 0L666.7 1466.4M833.3 0L833.3 1466.4M1000.0 0L1000.0 1466.4M1166.7 0L1166.7 1466.4M1333.3 0L1333.3 1466.4M1500.0 0L1500.0 1466.4M1666.7 0L1666.7 1466.4M1833.3 0L1833.3 1466.4M0 1114.2L2000.0 1114.2M0 905.0L2000.0 905.0M0 733.2L2000.0 733.2M0 561.4L2000.0 561.4M0 352.2L2000.0 352.2"/>'
		. '<path class="erc-land" d="' . er_map_land_d() . '"/>'
		. '</svg>';

	$bands = '';
	foreach ( array( 0, -3, -6, -9, -12, -15, -18, -21 ) as $alt ) {
		$bands .= '<path class="erc-night" data-alt="' . $alt . '" d=""/>';
	}
	$shade = sprintf( $open, 'erc-shade' )
		. '<defs>'
		. '<filter id="erc-soft" x="-12%" y="-12%" width="124%" height="124%" color-interpolation-filters="sRGB"><feGaussianBlur stdDeviation="14"/></filter>'
		. '<radialGradient id="erc-sun-glow"><stop offset="0%" stop-color="#ffd89c" stop-opacity=".55"/><stop offset="35%" stop-color="#ffc56e" stop-opacity=".22"/><stop offset="100%" stop-color="#ffc56e" stop-opacity="0"/></radialGradient>'
		. '<clipPath id="erc-night-clip"><path class="erc-nightclip" d=""/></clipPath>'
		. '<path id="erc-lights-sm" vector-effect="non-scaling-stroke" d="' . er_map_lights_d( 'sm' ) . '"/>'
		. '<path id="erc-lights-lg" vector-effect="non-scaling-stroke" d="' . er_map_lights_d( 'lg' ) . '"/>'
		. '</defs>'
		. '<circle class="erc-sun" r="900" cx="-9999" cy="0"/><circle class="erc-sun" r="900" cx="-9999" cy="0"/>'
		. '<g filter="url(#erc-soft)">' . $bands . '</g>'
		. '<g class="erc-lights" clip-path="url(#erc-night-clip)">'
		. '<use href="#erc-lights-sm" class="erc-halo"/><use href="#erc-lights-lg" class="erc-halo erc-lg"/>'
		. '<use href="#erc-lights-sm" class="erc-core"/><use href="#erc-lights-lg" class="erc-core erc-lg"/>'
		. '</g>'
		. ( $cover ? '<path class="erc-arcs" d=""/><path class="erc-arc" d=""/>' : '' )
		. '</svg>';

	if ( $cover ) {
		return $land
			. '<div class="erc-markers"><span class="erc-origin"><span class="erc-oping"></span><span class="erc-oring"></span><span class="erc-odot"></span></span><span class="erc-me"><span class="erc-ring"></span><span class="erc-ring erc-ring-2"></span><span class="erc-dot"></span></span><span class="erc-others"></span></div>'
			. $shade
			. '<div class="erc-scrim"></div>';
	}

	return $land
		. $shade
		. sprintf( $open, 'erc-dots' ) . '<path class="erc-past" d=""/><path class="erc-past erc-lg" d=""/></svg>'
		. '<div class="erc-markers"><span class="erc-others"></span><span class="erm-pin" hidden><span class="erm-card" role="status"></span></span></div>'
		. '<div class="erm-zoom"><button type="button" data-zoom="2" aria-label="Zoom in">+</button><button type="button" data-zoom="0.5" aria-label="Zoom out">&minus;</button></div>';
}

// Land, Miller projection, 2000 × 1466.4, clipped to x 0–2000 (Natural Earth).
function er_map_land_d() {
	return 'M669 1317l-3.2 12.3-11.7-1.8-12.4 .8-6.9-4.3-3.1-4.3 24.5 1.8 7.1-9.7zM115.5 1310.1l-10.7 1.8-7.2-4.4-7.1-8.5 3.4-4.5 10.3 1.9 9.8 8.1zM749.1 1292.4l6.9 5.2 2.4 7.4 .9 11.8-17.7 7.3-22.1 5.6-13.1-.8-7.3-4.3 1-5.3 11.8-3.5 4.8-4.2 13-19.2 11.1-2.6zM326.6 1240.8l7.2 1.7 6.6-1.9-8.4 6.6-7.7-.9-5.5-3.8 1.2-3.6zM302.4 1240.6l8.5 4.2-18-4.4 4-2.3zM450.1 1224.3l6.1 1.4 6.1-1.2 3.2 5.9-25.4-.2-5.7-2-2.9-4.3 3.5-1.8zM619.7 1214.3l.7 4.6-2.5 7.9-12.8 3.5-7.3-.2 2.8-4.1-12.8 2.8-4.2-3-.3-4.3 6.1-4 10.2-.8 1.8-16.9 3.2-4.6 5.1-1.5 2.9 3.6zM0 1380.7l5.2-8.4 10 4.6 7.4-5.1 8.6 6.3 8.3-6.9 16.3-2.5 16.3 9.9 28.3 8.4 21.4 3.5 16-4.1 23.6 2.9 13.4 4.7 30.2-8.5 1.2-6.9-21.9-.6-18-3.4-4.7-5.6-14.9-3.1 5.2-17.2-1.1-5.6-9.3-3.6-4.2-4.7-8.6-4.1 13.5 .7 12.8-2 8.1 4.3 19-8.6 4.5-4.3-2-5.3-15.3-7.1-32.2-3.6-3.6-4.6-11.6-8.1-1.7-13.4 7.7 4.8 18-2.8 4.5 5.1 8.9-1.1 20.6-9.6 8.4-1.2-2.2-8.5 1.7-3.9 7.1-2 3.3 3.7 14.9-5 15.5-1.3 20.2-7.4 8.2 1.5 8.2-1.5 15.1 1.7 7.2-1.5 15.8 2.2 24.1 0 7.6-.5 12.4-4.9 7 2.4 12.6-5.8 9.1 10.9 5.8-3.2 6.6 4.1 13.9 4.4 15-2.7 23.5 4 2.9-4.8-6.3-7.6-7.2-.8-3.2-4.1-3.1-11.8 25.2 3.1 5.7 3.1 2.4 3.7 7.5 .7 21.6-4.8 5.7 2.5 7.4-.9 4.8-8.1 4.5 4.8 6.4 1.9 6.9-1.1 4.6 4.2 20.7 4 6.5-7.8 5.6 4.2 7.6-1 9.4 5.9 7.4-1.1 11.5-5 21.6-4.2 8.7-5.6 .7-8.7-7.2-19.4 6.2-15.2-1.7-7.8 2.7-4.3 14.9-11.7 5.2-6.4 32.3-14.5 3.3 2.3-2.1 2.9-8 4.5-4.2-1.4-4.5 .9-10.6 7.1-.4 6.9 2.6 3.1-9 2.9-9.8 10.1-.9 3.5 4.9 7 8.8 5.3 9.4 18.6 .7 9.4 3.8 12.2-.7 5.6-3.1 4.5-3.2 3.6-7.4 1.5-5.9 7.6-8.4 4.2-21.9 6.7-4.4 4.7-36.9 .9 1.7 4.6 14.7 5.3 3.5 4.2-6.2 3.8-9.6-1.2-7.9 3.1-.6 9.8 6.6 4.2 1.2 4.6 7 4.8 11.8 2 28.1 11.7 27.4 5.7 19.8 8.5 8.1 11.6 6.8-4.9 18.8-8.3 21.4-7.2 13.9-.2 24.8 5 3.6-5.8 7.7-3.9 14-.3 45.3-9.7 8.6-3.2-6.3-9 0-4.6-33.1 2.4-1.5-4.6 .7-9.1 2.5-2.6 17.3-5.6 13.5-7 5-4.5 35.7-6.6 27.4-11 10.2-6.9 1.6-4.3-5.9-2.6 2-4.4 3.7-3.3 17.5-7.9 11.1-11.8 6.6 .7 2.7 3.4 6.7 .4 .2-3.8 2.8-4 6 1 1.4 3.8 6.7 .6 14.1-3 6.3 .6 2.4 4.2 6.1-3.4 30.1-8.6 8.1-5.6 4.2 2.5 5.7-1.4 7.2 8.3 6.3-2 2.5-3.9 5.7-2.8 7.3 .6 2.1 3.7 4.6-3.7 18.4-1.3 12.2 1.7 6.2 6.3 6.1-1.8 19-.6 27.2-8 4.3-2.7 6.2-8.5 5.7 1.5 2.2 3.4 4.8 2.3 5.8-.8 8 6 5.7-2.3 1.9-4.2 10.8-5 12-3.2 13.3-6.3 5.2 1.1 8.6-5.9 5.2 .1 4.6-2.2 1.1-3.2 4.6-2.6 15.3-4 10.1 1.5 4.5 2.5 .5 4 8.3 5.7 6.6 1.1 8.3 5.2 5.3 .6 9.3-5.8 15.8 4.3 11 .7 4.4 12.5-.7 4.4-9.7 6.3 .8 3.9 6.2-.2-.8 4-5.4 8 4.2 3.2 6.5 1.1 6.4-1.9 4.9-7.8 6.5-6.2 4.4-8.4 21-4.1 8.1-11 10.1-4.3 6.2-4.9 4.1-1.4 22.1-.6 4-2.6 2.8-6.1 4.7 6.9 4.7 1.8 25-.4 8.9 2.6 16.1-2.6 5.8 1.3 17.3-15.6 7.8 3.8 10.8 8.9 10.6 .2 11.9-2.4 8.4-5.3 10.3-2.3 11.1 7.6 6.1-.4 10.5 4.8 6.9 .9 5.8-.7 8.1-5.9 5-.7 10.7 2.2 10.3-1.5 10 1.8 11-3.1 22.1-1.9 1.7-8.3 3.5 2.5 5.1 11 4.7 1.7 31.1-1.5 13.5 1.1 3.9 2.9-1.1 3.6 3.6 2.7 12.2 4.7 20.3 4.8 6.3 .2 3.6-3.3 14.1 8 13.1 2.1 2.8 3.9 10.5 5.9 31.9 2.6 17.7 5.8 3.9 3-.6 4-10 16.6-7.3 1.7-3.3 3.8-7.2 2.4-10.3 11.9-3.7 8.9-.4 9.5 6.9 13.3 10.4 1.7 2.2 5.2-18.5 4.6-10.6 .5-4.7 7.1-1 5.9-5.3 9.6 7.4 4.4 2.8 5.4 11.6 9.5 16.1 8.6 12.7 4.4 2.8 6.9 16 3.1 5.3 5.4 15.3-3.7 22.3 8.1zM623.6 1065.3l7.2 4.6 7.8 1.9-2.5 3.9-5.3 .4-2.8-2.7-1.8 3.1-4.8 2.4-6-.9-16.9-7.7-13.3-12.6 19.8 9.3 4.6-8.6 5.1-3.1 4 .9zM674.7 1044.8l4.5 3.3-1.7 2.6-7.5 2.2-2.5-2.6-4.7 3.4-2.8-3.4 6.7-4.4 4.7 1.8zM1390.5 1034.7l-8.6 .4 1.1-8.2 8.8 3.1zM1807.8 973.2l5.3 2.3 10.7-1.8 .4 7.9-1.9 2.3-.6 5.4-1.9-1.8-3.9 4.7-4.5-.6-7.4-16 .1-3zM1961.2 974l1.3 2.7 3.9-2.6 1.6 5.6-8.5 10.7 2.1 3.3-4.3 0-4.8 2.6-4.6 11.5-7.2 5.1-14.7-3-.9-2.5 2.9-5.2 7-6.7 12.4-7.4 7.9-10.1 .8-3.7 3.9-3.1zM1970.1 943.2l4 6.7 .1-4.4 2.5 1.8 .8 4.8 4.5 2.1 9.8-1.2-3.1 9.5-4.2-.1-1 4.8-5.7 9.3-4.2 2.6-3.3-2.7 3.2-5.4-1.8-3.6-6-2.6 .1-2.4 4.1-2.3 .6-9.1-2.1-5.4-9.3-12.7 2.1-.5 3 3.5 4.3 1.7zM1928.4 858.3l-2.1 1.4-7-4.2-8-9.1 5.5 2zM1991 830.5l1.9 1.6-.9 3-6.5 .1-.6-2.5 2.2-2zM0 826l0-2.6 1.1-.4zM2000 826l-7.1 2.6-.7-2.1 7.8-3.1zM1928.4 816.8l.9 4.6-3.5-2-.1-4.4zM1278.1 809l1.8 12.2-1 1.6-1.9-3.3-1 1.7 .5 6.6-14.8 46.8-9.4 4-7.6-3.7-3.9-13 .5-8.4 2.6-1 3.1-10-2.8-11.6 2.7-6.8 10.4-2.5 7.7-6.8 1.7-2.8-.8-2.4 2.4 .7 5-9.9zM1797.6 810.1l2 4.5 3.5-2.2 4.5 4.7 .7 7.4 5 15.3 13.6 8.2-.7 1.4 5.3 10 2.3-1.3 2.2 2.5 1.4-.9 .9 6.2 10.9 10.7 1.6 4.8-.3 7 2.7 5.1-2.7 17.1-3.6 10-4.1 3-7.6 16.5-1.9 11.2-3.2 2.2-6.2 .2-11 8-8-4 .8-3.4-7.9 5.9-16.5-5.1-3.6-4-2.3-8-2.7-2.6-5.4-.8 1.9-3-1.4-4.7-2.7 4.4-4.9 1.1 5.9-10.1-.5-4.6-8 7.4-2.1 5-4.3-2.6 .1-3.3-6.4-6.9 1.1-1.4-16.4-6.9-10 .6-13.5 4.2-5.3-.4-10.7 4.6-3.1 5.8-20.9 .6-10.4 6.8-7.8-.3-8.9-5.2 .2-3.5 3.7-2.3-.2-10.2-2.9-6.2-.7-6.9-9.4-20.1 2.4 2.6-1.9-5.5 4.4 4-4.6-11.3 4.2-15.4 .4 4.4 2.3-4 11.5-6.6 4.1 .3 10-4.6 8.9-1.5 7.7-8.6 .4-5.4 3.9-4.9 2.3 5 2.4-1.2-2-2.7 1.8-2.7 2.4 1.2 .7-4.3 7.3-7.6 2.4 .6 .1-1.4 5.1-1.6 7.2 6 7 .6-1.2-3.1 5.2-6.1-.9-1.4 2.5-3.2 3.3-2 7.5-.4-.1-2.9-4-1.8 2.9-.8 16.4 6.3 6.6-2.2 2.5 2.8-3.5 5.3-2 .2 .7 2.2-3.2 7.2 26.2 15.5 3.6-1.9 4.6-13.3-1-7.6 3.5-15 2.1-2.1 1.5 2.7 4 9.5zM1670.6 790.3l-9.7-3.8 5.2-1.1 4.9 3.4zM1893.6 788.2l-5.5-.4-.9-3.1zM1898.2 786.7l-.8 1-5.3-8.2 1.9 0zM1691.3 789.7l-5.4 .6 2.9-5.3 6.1-3.6 12.5-1.4-12.5 5.5zM1655 778.3l5.4 1 1.4 2.4-13.2 1.8 1.9-3.2 3-.1zM1682.8 778.3l-.8 3.1-8.4 1.6-7.4-.7 0-2.1 4.4-1.1 3.5 1.6zM1603.5 770.9l10.6 .6 1.2-2.3 10.3 2.6 2.1 3.7 8.3 1 6.8 3.3-6.3 2.1-6.1-2.2-28.9-3.3-10.1-2.3-.9-2.4-5.1-.4 3.8-5.3 6.7 .3 6.8 2.6zM1748.5 767.8l-2.9 3.8-.5-4.2 2.1-3.9zM1866 771.1l-4-1.5-3.6-7.8 8.4 7.8zM1844.4 763.7l-9.7 4.6-10.7-3.1 .5-1.8 8 .4 1.6-2.8 .6 3 3.1-.5 4.7-3.9-.6-3.2 4.4 .8zM1706.9 752.4l-2 1.9-3.9-1-1.1-2.4 5.7-.3zM1724.8 750.4l2.1 4.2-4.7-2.3-11.6-.2 1.3-3.1zM1850.8 758.2l-1.8 1.5-2.3-5.4-9.7-5.9 1.6-1.3 7.2 4.1 4.3 4.1zM1745.2 739.6l1.6 9 5.7 3.3 4.7-5.9 6.4-3.3 4.9 0 34.7 12 7 5.6 .8 3.3 9.3 3.4 1.3 3-5.1 .6 1.2 3.7 5 3.7 3.6 5.9 3.2-.2-.2 2.5 4.3 1-1.7 1 5.9 2.4-.6 1.6-15.5-2.5-7.4-6.7-2.9-4.9-7.3-2.4-8.1 3.4 .7 4.1-4.3 2-8.9-1.2-4.9-4.6-5.7-1.1-1.3 1.6-7.1 .1 2.4-4.5 3.5-1.6-4.1-10.7-15.4-5.2-8.3-5.1-3.8 3.2-1.3-4.5-4.2-2.7 9.9-1.9-.4-1.5-8.2 0-2.2-3.3-4.9-1-2.4-2.8 10.3-3.1 9 2.2zM1695.8 725.3l-4.5 5.5-4.2 1.1-19.4 0-.8 4.2 5 4.9 3-2.5 10.3-1.9-.4 2.6-2.4-.8-2.5 3.2-4.9 2.2 5.3 7.1-1 1.9 5 6.4-.1 3.7-2.9 1.6-2.2-1.9 2.7-4.6-5.5 2.2-1.4-1.6 .8-2.1-4-3.3 .4-5.4-3.7 1.7 .7 14.4-3.6 .8-2.3-1.6 1.5-5.1-.8-5.4-2.4 0-1.7-3.8 7.1-18.7 4.7-4.2 11.3 2.4 6.4-.2 5.5-4zM1714.9 726.9l-.3 4.9-2.8-.6-.9 3.4 2.3 2.9-1.5 .7-3.9-10.6 1.1-4.5 1.8-2 .4 3 3.3 .5zM1587.9 765.7l-6.2 .2-11.8-9.2-13.6-19.9-4.8-4.6-3.7-9.1-17.9-17.5-.5-2.9 12.2 1.3 17.5 17.5 5.7 .1 12.1 11-2.2 4.6 5.1 2 2.9 7 4.1 .5 2.7 3.5zM1654.9 723l6.2 5.2-6.6 .6-1.8 3.8 .2 5.1-5.3 3.8-2.3 14-.8-2-6.4 2.5-2.2-3.4-6.7-2.1-6.6 2-2-2.7-8.2-.3-.9-7.5-5.4-6.2-.2-10.1 3.3-3.6 4.1 1.9 4.3-1.1 1.1-4.7 9.1-2.2 20.7-21.3 2.2-.1 3.1 5.3 8.3 3.2-.4 2.2-3.7 .3 1 2.7-4.1 1.9-3.2 5 4.1 5.3zM1702.1 686.3l.9 6.9-1.9 5.1-2-5.7-2.6 2.8 1.7 4.1-1.5 2.7-6.6-3.3-1.5-4 1.6-2.7-3.5-2.6-8.4 5.2-1-1.6 2.2-4.7 6.5-3.7 2 2.5 4.2-1.5 .9-2.5 4-.2-.4-4.3 4.5 2.7zM1451.2 698.7l-4.8 1.3-2.7-4.4-.9-8.1 2.5-9.1 3.8 3.2 5.3 9.7-.9 5.8zM661.5 676.8l-5.7 .2 1.5-3.8 3.2-.7zM1688.8 675.9l-5.5 7-3.4-3.8 3.1-6.6 3.1-.3-.9 3.8 4.1-5.5zM1658.4 681.3l-7.4 5.3 13-16.8 .9 4.6zM1677.1 666.8l6.9 1.8-.1 2.3-6.1 4.1zM1697.2 665.3l1.6 6.3-4.3-1.5 1.5 5.3-2.7 1.3-.2-3.9-1.7-.3-.8-3.4 3.2 .4 0-2.1-3.4-4.3 5.3 .1zM1675.2 660.2l-1.5 4.9-5.2-7.2 4.7 .3zM1674 629.2l3.4 1.7 1.7-1.5-.4 3.8 1.9 4.1-1.4 4.8-3.3 1.9 .4 9.1 5.4-.1 6.9 3.2 .7 7-6.4-5.7-1.4 2-3.5-3.4-7.8-.4 2-3.8-1.7-1.3-.7 2.1-2.7-3.3-1.1-7.9 2.3 1.9 2.3-14.2zM635.6 630.8l-1.4 1.5-7.5 .1-.3-2.4 5.4-.8zM572.8 632.9l-1.7 .9-6.3-3 3-1.7 5 .7 3.9 3zM596.8 621.4l14.6 1.2 1 2.1 3-.1-.2 1.7 5.2 2.3-2 2.3-7-1.2-3.2 1.4-.8-1.4-4.1 4.7-1.7-2.5-3.7-1-8.6 1-3-1.7 .5-1.9 9.3 1.3 2-1.3-2.5-4.7-3.5-.9 1.3-1.6zM1613 628.2l-4.8 2.8-4.6-1.8-.1-4.9 2.7-2.6 9.3-1.5 1.2 2.2zM135.9 625.9l-2.2 .1 .4-6.9 5.9 4.3zM557.3 604.5l2.2 2.2 5.2-.7 10.2 7.6 5.1 1.1-.4 1.7 8.3 2.6-.7 1.3-19.2 1.1 3.7-3.2-5.8-1.9-3.2-4.9-17.3-3.5-1.9-1.1 2.1-1.5-5.5-.3-7.1 4.5-5.1 .1 6.7-5.2 8.3-2.3zM569.2 598.7l-1.3 .3-3.5-5.1 1.2-3.7 1.7 .2zM1673.2 604.4l-2.4 4.8-3.5-9.3 7.7-10.3 2.5 1.8zM567.7 582l-6.1 1-.4-2.2 6.3-.3zM1748 535.9l.7 2.1-3.1 3.8-2.3-2-2.9 1.4-1.4 3.6-3.6-1.7 0-3 3.1-3.7 3.1 .7 2.3-2.6zM1192.1 526.3l-3.8 2.7 .6 1.7-5.7 2.5-2.7-.8-1.3-2.5zM1131.7 526.1l3 2.1 11.4 .4-.7 1.9-8 .5-6.8-2.2zM1086.2 509.9l-2.3 10.4-14.8-6.4 .7-3.3zM1051.2 490.5l3.3 4.6-.8 8.7-2.5-.4-2.3 2.2-2.1-1.8-1.5-11.6 3.1 .3zM1783.2 516.9l-2.1 5.1 1 3.2-2.9 4.4-7.1 3-9.8 .4-7.9 7.1-3.7-2.4-.3-4.6-9.6 1.3-6.6 3-6.5 .1 5.6 4.6-3.7 10.5-3.6 2.5-2.7-2.3 1.4-5.6-3.5-1.8-2.3-4.2 5.3-1.9 12.6-11.5 11-1.9 6 1.3 5.8-11.3 3.7 3 11.3-8.9 3.5-7.9-1-7.4 2.4-4.1 5.9-1.3 3 9.2-.2 5.3-5.1 6.5zM1053.1 484.2l-1.8 5.1-2.5-1.3-1.3-4.5 4.7-5.1zM1799.5 470.6l3.9 1.4 3.9-2.9 1.3 7.6-8.3 1.9-4.8 6.6-8.8-4.5-3 7.3-6.2 .1-.7-6.7 2.7-5.1 5.9-.4 3.3-14.8zM646.3 454.2l9.2 .7-4.8 3.3-7.1-2.9-1.3-2.4 2.1-2.1zM656.6 436.1l-9.9-2.1-5.1-3.4 9.2 1.2zM313.8 440.4l-2.8 1-9.1-3.3-7.6-7.1-5.7-1.3-1.7-5.7 14.5 3.5 4.6 5.9 5.5 3zM688.1 424.6l-3.6 6.4 3.6-2.4 3.7 1.5-1.9 2.5 4.9 2 2.6-1.7 5.5 2.2-1.7 5.2 3.9-1.2 2.4 8.2-2.3 6.1-6.2-1 1.2-5.8-1.5-.9-6.5 6.1-3.3-.2 3.9-3.3-5.3-1.7-16.8 .2-.8-2.1 3.4-2.5-2.4-1.9 4.7-4.3 5.7-11.6 8.3-6.7 2.6 .3zM262.7 399.6l5.4-.6-1.7 8.6 4.8 6-2.2 0-8.2-9.2-.7-5.8zM1798 424.2l5.6 12.8-8.2-2.3-3.4 10.3 5.4 7.2-.1 4.9-4.3-4.2-3.6 5.3-1-20.3 1.5-15.2-3.3-7.2 .5-10.3 5.2-3.5-2.2-3.5 2.4-1.1zM962.3 413l-9.9 4.4-7.8-1.1 4.5-7.8-2.9-7.7 11.7-9.6 4.7-.3 5.9 4.8-2.9 5.2 .9 5.4zM1070.5 387.5l-3.3 6.3-5.8-4.4-.8-3.2 8.1-2.6zM150 375.7l-5.6 3-2.8-2.1-.9-3.7 8-4.1 3.7 .6 2.4 2.5zM983.3 363.5l-5.9 8.7 11.7-1.1-1.4 6.5-5 7.1 5.7 .5 5.4 9.9 3.8 1.3 5 11.6 6.8 1.5-.7 4.7-2.9 2.2 2.3 3.8-5 3.9-16.9 1.9-2.6-1.4-3.7 3.4-5.1-.8-3.9 2.7-3-1.4 8.1-7.7 5-1.6-8.7-1.2-1.6-3 5.8-2.3-3-4 1-4.9 8.3 .6 .8-4.4-3.8-4.8-6.7-1.3-1.3-2.1 2-3.5-1.9-2.1-2.9 3.6-.4-7.5-2.8-4 2-8.2 4.4-6.6zM80.1 353l-3.4 1.3-7-3.8 9.9-.7zM559.6 334l-2.1 4.5-4-3.3 2.4-3.1zM545 329.2l-6.5 4.8-3.9-.2-1.2-2.3 4.1-4 7.6 .1zM45.9 319.9l16.9 4.2-4.6 2.8-6.4-3.5-4.9 .5-1.3-.7zM526.9 303l1 4 2.9-1.4 15.6 8.3 .5 4.2 4.1-.7 4 3-5 2.7-8.6-2.1-3.1-4-13.4 9.3-1.9-5.2-7.6 .9 4.9-4.4 2.6-15.3zM919.4 295.7l-1.3 5.9 6.3 6.2-7.2 6.9-20.9 7.7-22.8-4.1 5.5-3.9-12.1-4.4 9.8-1.8-.2-2.6-11.7-2.2 3.8-5.9 8.4-1.4 8.7 6.2 8.4-5 7 2.6 9.1-4.9zM578.5 289.2l-6.2 .5-1.4-4.6 2.4-5.4 5.1-1.3 4.3 2.6-.6 5.4zM0 271.9l28.2 16.8-.5 5.8 3.7 2.3-1.2-6.8 15 1.4 10.9 8.7-5.5 4-9.1 .9-.1 8.8-2.3 1.9-5.2-.3-11.6-5.7-1.2-3.9-5.7-1.5-6.3 1.1-3-3.1 1.2-3.4-6.7 2.1 2.6 4.3-3.2 3.9zM2000 271.9l0 37.3-7.2 4-7.2-.7 5 4.8 5.9 9.6 .6 3.6-1.4 2.3-10.3-1.9-20.5 7.4-16.6 11.1-2 3.8-8-5.8-14.4 6.6-2.6-3.1-5.3 3.6-7.4-1.2-1.8 5.4-6.7 7.9 .2 3.3 6.3 1.8-.7 11.5-5.2 .3-2.4 6.5 2.4 3.4-9.8 3.9-1.9 8.7-8.3 1.8-1.6 7.6-8 6.9-7.6-33 2.7-10.9 4.7-4.7 .3-3.8 8.6-1.8 19.5-18.7 10-6.7 4.4-12.1-6.7 .8-3.3 7-14.1 9.3-4.6-10.4-14.3 2.9-13.9 14 4.6 5.1-21 3 .4-6-8.7-1.2-6.8 4-17-1.4-18.3 2.4-39.3 34.2 8.8 1 2.7 4.8 5.4 1.7 3.5-3.8 6.1 .5 8.1 8.3 .1 6.4-4.3 7.4-3 20.2-8.3 10.2-1.9 4.9-18.6 19.9-7.4 4-3.5 .1-3.5-3.3-12.9 9-1.6 2.3 .2 4.7-12.1 7.4-.8 3.6 5.4 3.9 6.1 11.7 .1 7.3-2.1 3.5-14.5 4.3 .4-8.1-2.4-6.6 4.1-1.1-3.8-5.5-2.7-1.2-2.3 1.8-3.2-2.9 2.9-3.6 .5-5.7-5.8-2.5-17.9 6.7 3-3-1.2-2.5 4.4-4.4-2.9-3.5-11.1 6.9-3.5 4.2-5.4 .3-2.8 3.1 2.9 4.3 4.5 1.1 .2 2.9 4.4 1.8 6.2-4.5 8.6 2.6 .9 3.4-7.9 1.8-10.8 11 5.9 3.4 2.2 6.2 7.2 10.4-.1 4.5-3.5 1.7 1.3 3.3 3.3 1.9-2.3 9.7-3.1 .5-13.7 21.4-15.4 10.3-6.2 .7-3.4 2.6-1.9-1.9-3.2 2.9-13.6 3.8-1.9 6.1-3.1 .4-1.5-4.2 1.4-2.3-7.5-1.8-10 5.9-4.7 5.4-1.2 4 9.5 13.6 8.4 8.1 2.5 10.5-.7 9.9-22.5 17.2-2-3.6 1.6-3.8-4.2-3.2-4.6-.8-5.1-8.7-5-2.6-4.7 .1 .8-4.5-4.9 .1-.4 6.2-4.8 13.2 .3 4 3.7 .2 3.2 10 13.9 10.6 2.3 3.7 .7 11.5 4.1 8.3-4 .4-11.8-8.5-6.6-14.2-.7-6.5-8.8-10.7-.9 3.3-1-3.1 3.4-17.3-1.9-3.4 .5-6.1-2.3-2.9-5.2-18.7-10 6.9-6.5-1.9 1.9-7-1.2-5.4-4.3-6.6 .7-2.1-3.3-.8-3.9-4.7-5.3-12.2-5.1-.2-1.3 5.7-6.9-1.3-.8 2.1-10.6 1.1 .3 4.4-2.9 3.4-8 3.9-16 14.2 0 2.6-10.3 3.7-1.7 4.4 1.4 12-2.3 5.4 0 9.6-2.9 .2-2.5 4.3 1.6 1.9-5 1.5-4.1 5.5-5.3-5.3-4.7-13.5-4.9-8.1-2.3-10.6-5.1-7.8-5-30.8-8.1 3.4-3.9-.7-7.2-7 2.6-2.1-1.6-2.3-10.6-6.5-6-8.7-27 2-22.8-3.9-2.4-7.3-2.6-1-9.9 3.9-6.8-2-5.6-4.6-5.4-1.7-7.8-13.8-3 1-3.5-2-2.1 2.4-3.3-.3 .7 4 4 9.8 7.4 6-.2 4.4 3.9 7.1-.4-4.4 3-3.7 1.7 1.9-1.1 6.9 2.3 3.6 12.3-.6 13-13.5 .2 8.7 2.5 4.1 10.5 3.9 6 7.4-7.4 10.9-3.6 1.1-.8 7.5-6 2.1-1.8 4-5.6 1.4 0 2.4-16.1 4.8-1.2 4.5-14.4 5-5 4-16.9 4-3.5 3.4-8.4 .3-4.9-14.6 1.1-.2-.8-8.7-8-10.8-1.6-4.7-6.3-4.9-3.7-5.6-.4-7.5-3.1-6.5-5.6-3.5-3.1-7.8-10-14.7-2.8 .1 1.6-8.7-5.5 11.1-4.4-4.6-4-8.7 9.4 22.3 8.8 13.1-.9 4.9 7.4 6.4 3.4 19.6 5.2 3.5 4.8 11.9 22.5 20-.2 2.4-3.2 1.3 7.8 7.3 2.8 0 36.1-8.9-.4 7.8-8.9 21.4-9.5 14.4-6.5 7.6-19 14.3-8.7 11-7.3 4.9-3.7 9.9-2.2 1.8-2.6 6.9 .4 3.1 3.5 2.1-1.4 9.2 7.2 12.7 1.6 22.2-3.8 8-3.5 3.5-11.4 5-14.5 12.6-.5 4.2 4.8 9.2-.6 11.9-16 9.5 1.9 2.9-2.5 12.5-13.4 17.2-10.2 10.1-12.8 5.5-.8 1.8-17.8-.5-16.4 5.9-7.6-5.9-1.8-7.8 1.8-1.1-.2-4.8-16.7-27.7-5.3-29.4-13.7-23.3-.1-13 4.1-12.8 6.3-8.5 .2-7.4-4.5-8.7 2-3.4-7.3-19.7-17.3-21.8 5.5-23.3-2.1-3.7-2.6-.9-2.5-4.8-14.4 2.8-8.8-11.2-13.6 .7-21.3 8-14.9-2.5-18.5 4.5-27.6-19.2-1.7-6.2-7.4-7.3-1.5-3.8-6.9-3.6-2.9-3.6-.6-8.1-5-6.4 5.1-5.1 2.4-8.8 0-16.8-4.4-5.3 .5-5.1 3.9-4.7 1.6-6.1 5-4.7 3.6-10.2 3.7-2.2 3.5-6.1 2.9-2.4 5.2-.7 11.8-10.8-1.4-7.6 2.8-8.5 3.6-4.2 9.7-5.4 5.4-10.4 4.1 0 3.4 2.7 13.4 1.1 20.3-9.1 21.4-.8 5.2-2.5 12 1.1 6-2.6 3.9 .8-.1 3.2 4.7-2.3 .4 1.2-2.8 3.1 0 2.9 1.9 1.6-.7 5.5-3.7 3.1 1.1 3.4 2.8 .1 3.5 4 20.9 5.4 2.6 5.4 18.7 6.8 5.4-4.4-1.3-4.7 1.8-3 7.8-3.7 7.5 1.3 1.9 2.7 9.4 1.8 1.3 2 7.4-.1 13.4 4.4 6.6-3.7 4.9-.5 4 .8 1.5 3 1.3-2 8.7 1.8 4.4-3.5 8-19.3 .8-7.4-2-2.8 2.1-2.4-8-1-3.9 3.7-8.4 .7-4.5-3.4-6-.2-1.3 2.6-3.8 .8-5.4-3.4-6 .1-3.3-6.3-4.1-3.6 2.7-5-3.5-3.2 6.2-6.2 8.5-.3 2.4-5 10.5 .9 13.2-6.2 9.2-.2 17.7 7.3 11.2-.4 6.6-3.5-.6-7.4-26.5-17.7 4-1.1 4.6-5.8-3.1-2.7 8.2-2.9-.2-1.5-18.3 4.3-4.8 2.6 .4 4.3 2.7 1.7 5.7-.4-1.1 2.4-13.7 5.2-3-1.4 1.2-3.2-6.1-2 6.3-3.7-1.6-1.5-8.6-1.8-.4-2.6-5.2 .9-6.2 10.7-4.4 .8-1.5 8.2-4.9 7.6 2.4 6.4 4.9 2.2-1.1 1.6-6.6 .4-7 5.5-1.6-4.4-6.3-.8-6.7 1.7 3.8 3.7-2.8 1.1-3.1 0-2.9-3.4-1.1 1.5 4 6.9-2.1 1.4 5.9 4.9 .1 3.6-5.2-1.7 1.7 3.3-3.6 .7 2.1 5.6-3.6 .1-4.6-2.8-3.1-9.4-6.4-10.4-3.1-2.2 .8-9.7-3.7-3.8-15.9-8.2-4.7-5 1.1-.5-2.6-5.2-3.6-1.1-1.7 3-1.6-2.3 1.5-3.2-4.4-1-4.5 2.5-.4 5.3 1.8 3.5 5.3 3.4 2.8 5.6 6.1 5.4 4.4 0 1.3 1.4-1.5 1.4 9 4.4 5.4 4.6-1.1 2.4-3-3.1-4.9-1.1-2.3 4.3 4 2.4-.7 3.4-2.3 .4-2.9 5.5-2.4 .5 2.4-6.8-3.9-7.1-9.9-7.5-4.1-.4-4.3-3-8.9-8.2-1.7-6.7-7.3-3-13.1 8.3-11-1.8-8.1 2.2-.3 7.9-5.3 4.5-7.1 1.4-6.1 11.1 2.2 3.8-3.2 2.8-1.2 4.2-4.2 1.3-3.9 4.9-12.4 0-5.6 4.6-2.7-.5-3.6-5.8-5.2-1-2.3 1.7-5.7-.2 .3-9-3.8-3 4.2-13.3-1.2-12.1-2.3-3 7.9-4.8 33.7 2.2 2.9-4.1 1.1-13.7-9.9-10.9-8.5-2.7-.5-5.2 7.2-1.5 9.3 1.8-1.8-8.1 5.3 3.1 12.9-5.7 1.7-6 12.2-4.9 4.9-11 7.6-3.2 4.6 .3 1.1-1.6 4.6-.5 1 1.7 3.8-3.7-3.8-11.6-.1-8 2.5-4.5 4.9-.5 6.4-4.4-1.8 6.7 .6 2.2 3 1.2-7 7.7 1.6 6.7 5.6 1.8 0 2.8 8.8-3.6 8.9 5.5 19.4-8.4 5.6 1.3 .4 1.9 5.3 .1 1.3-3.4 7.7-2.5-1-12.5 2.7-5 5.2-2.7 4.5 5.9 4.4-.1 1.7-10.9-2 1-3.5-2.9-.5-4.7 14-3.4 11.8 1.1 6.3-4.6-5.8-4-10.1 .7-18.8 4.8-3.3-4.5-5.3-2.8 1.2-8.3-2.7-7.8 2.7-5 5-5.6 16.4-11.5-.6-3.9-7.7-4.3-9.6 2.6-5.3 6.4 .8 5.4-19.5 14.7-4.1 12.1 9.3 10.6-5.1 9.2-5.8 2-2.1 13.5-3.2 7.4-6.7-.8-3.2 6.2-6.4 .3-10.6-27.7-3.8-5.1-10.9 9.5-7.4 1.9-7.7-4.2-3.8-28.2 5.2-5.5 14.6-7.4 11-9.1 23.5-30.6 24.6-19.5 12.2-4.4 9.1 .6 8.5-8.4 10.1 .5 10-2.1 17.4 7.5-7.2 2.6 6.1 6.3 5.7-3.5 9.1 6 15.3 2.3 21 10.9 4.2 4.5 .4 6.2-6.2 4.9-9.1 2.5-24.8-7.1-4 1.2 9 6.8 .7 13.4 11.5 5.1 .8-4.3-3.5-4 3.6-3.3 13.5 5.6 4.7-2.2-3.8-6.6 13-9 5.1 .5 5.2 3.2 3.2-6.3-4.6-5.6 2.7-5.7-4.1-5.9 15.6 3.1 3.1 5.3-7 1.2 0 5.2 4.4 3.2 8.6-2 1.3-6 31-12.8 4.2 .5-5.4 5.8 6.8 1 4-3.3 10.4-.2 8.3-4 6.3 5.8 6.3-6.4-5.8-5.7 2.9-3.2 16.4 3 27.8 14.1 3.7-5.1-5.6-5.1-.2-2.1-6.7-.9 1.9-4.7-3.2-11 10.3-9.3 3.6-9.5 4.2-2.1 14.7 2.8 1.1 5.8-5.2 8.4 3.4 3.2 1.8 7.1-1.3 13.5 6.2 5.9-2.4 6.4-10.9 13.3 6.4 1.4 2.2-3.3 6.1-2.4 1.5-4.7 4.8-4.5-3.3-5.4 2.6-6.4-6.1-.8-1.3-5.4 4.4-10-7.2-8.3 10-7-1.3-7.4 2.8-.3 2.9 5.9-2.2 9.9 5.9 1.9-2.5-7.4 9.3-4.1 11.5-.5 10.3 5.9-5-8.7-.5-11.3 9.7-2.2 25.4-.9-4.6-5.7 6.5-7.3 6.3-.3 10.8-5.6 14.7-1.5 1.9-3.1 14.6-1.1 4.5 2.6 12.5-6.1 10.2 .2 1.5-5 5.3-5 13.1-4.8 9.6 3.8-7.6 2.9 12.6 1.8 1.5 5.7 5.1-2.8 16.2 .2 12.5 5.6 4.5 4.3-1.4 5.8-24.9 12.7 15.1 4.3 5-2.1 2.8 6.9 2.5-2.7 8.8-1.7 17.9 1.7 1.3 5 23.3 1.6 .3-8.2 20.6 1.9 9 5.6 2.6 6.7-3.3 4.4 6.9 8.1 8.8 4.1 5.3-10.7 9 4.6 9.4-2.8 10.8 3.2 4.1-2.9 9.1 1.5-4.1-9.7 7.4-4.5 50.2 6.8 4.7 6.2 14.5 7.7 22.5-1.9 11 1.7 4.6 4.2-.6 7.2 6.8 2.8 7.5-2 20.3 1.7 10.5-1.1 9.7 8.7 6.9-3.1-4.5-6.3 2.5-4.4 17.7 2.8 11.5-.6 16 4.7zM1272.8 490l2.9 4.7 4.3 2.1-4.6 .5-1.9 7.3-2.1 1.5 1.9 8 5.3 1.3 3.9 3.2 7.9 1.2 8.6-1.7 .3-12.8-4.3-2.2 1.4-4.5-3.6-.4 1.2-5.5 5.2 1.6 4.9-2.1-5.6-7.8-4.5 1.7-.6 4.9-1.7-11.1-6.5-2.3-5.7-10 5.4 .6 .2-5 9.6-.1 0-11-10.3-1.4-11.6 4.5-2.5 4.2-5.4 1.1-5.5 7.1 5 6.5-.5 4.5zM468.6 270.5l-3.4 3.4-7.5-3-4.5 1.1-7.6-4.4 8.7-7.3 9.3 4.6zM2000 246.4l0 6.9-6.1 .6-1-3.3zM0 246.4l5.4-.4 8.1 2.9-6.2 3.8-7.3 .6zM497 266.7l-.1 9.9 7.5-7.6 6.6 6.2-1.7 7.2 5.4 6.3 5.8-6.8 4.1-8.3 .3-10.7 16.1 2.2 7.4 4.8 .4 4.9-4.2 5.1 4 5.1-.8 4.6-10.8 6.5-7.8 1.4-5.7-2.8-8.6 16.3-6.5 6-7.9 .6-4.4 3.8-.4 5.7-6.4 1.1-6.8 7-6.1 9.5-2.1 6.6-.3 9.6 8.1 1.4 5.1 13.6 7.8-1.6 10.3 3.5 9.6 6.7 12.8 5.4 15.2 1.2-.9 6.7 1.7 7.6 4.1 8.4 8.2 7 4.3-2.4 3-7.6-2.9-12-3.9-4 8.9-3.6 6.3-5.5 3.1-5.4-.5-5.3-3.8-6.8-6.7-6.1 6.5-8.6-4.2-20.9 3.8-2 15.3 3.2 4.6-2.3 12 7.9 1.7 3.3 9.9 .6 1.7 17.5 5 1.3 4.1 4.8 8-4.5 9-13 17.7 27.4-2.2 5 12.4 8.9 8.8 2 3.6 2.4 2.2 6.5 4.3 1 2.2 2.9 .4 8.4-8 5.4-9.1 2.6-7 6-9.4 1.2-26-1.1-4.6 5.2-7.1 3.2-14.4 15.9 4.7-1.2 8.9-9.2 11.7-6 8.3-.7 4.9 3.5-5.3 4.8 3.6 12.9 7.2 3.4 9.2-1 5.6-7.8 .4 5.1 3.6 2.5-6.9 4.5-17.8 6.8-6.2 4.9-4.3-.5-.2-5.7 9.7-5.7-15.1 1.1 1 2.2-17.5 7.7-3.2 4.4-.8 4.7 1.9 3.5 2.3 .2-.6-2.5 1.6 1.5-.4 1.9-20.8 4.7 8.2-1.2 1.6 1.2-7.8 2-3.4-.8-1.6 1.8 1.6 .3-1.2 4.7-4.1 5-3.4-3.6 2.6 7.1-4.9 7.7 1.2-4.7-2.8-2.4-.7-5.4-1 2.8 1.1 4.1-3.5-1 3.7 2.1 3.2 15-3.5 4.7-5.8 1.9-9.2 6.3-12.7 12.7 .2 8.6 6.9 19-1.8 10-4.4 0-3-4-6.3-12 1.1-4-1.5-3.3-6.5-6-5.6 2.7-7.2-4.6-15.5 .5-2.3 .9 2.1 5.3-5.2 1.1-4-.2-4.1-3.2-12.4-.2-15.2 8.5-4.3 5.5 1.2 9.1-3.1 9.4-.9 10.7 3.8 10.5 7.1 10.5 5.9 1.5 2.3 2.4 16.8-4.2 3.5-2.4 2.7-9.9 9.7-2.8 8.3-.3 1.1 4-4.3 7 1 1-2.2 6.9-2.6-1.3 1.1 .8-1.4 10.4-3.2 3.7 4.5 1.1 17.5-1.7 8.7 4.1 1.3 5.5-3.5 18.1 8.9 11.8 7 .8 7.6-4.3 15.2 5.5 6.5-4.5 1.1-6.6 3.1-2.6 8.3-.8 9.3-6.8 3.4 1.8-1.3 3.2-3.2 .7 1.7 5.5-2.4 3.2 2.1 4.5 2.4-.4 1.2-4.1-2-6.2 6.9-2.3-.7-2.6 1.9-1.8 2 4 3.9 0 3.8 5 11-.5 7.4 3.2 3.2-3.1 13.5-.5-4.7 1.7 1.9 2.7 4.4 .4 4.2 2.7 .9 4.5 2.9-.1 5.9 3.4 3.4 3.6 .1 2.9 7.3 4.8 17.7 1.2 6 1.9 6.8 7 1.9-.3 4.5 12.8 3 .9 .1 3.9-4.2 4.6 1.8 1.6 9.8 .9 .2 5.6 4.2-3.7 16.2 5.4 2.7 3.3-.9 3.1 6.5-1.8 10.8 3 8.3-.2 15.3 10.8 9 1.8 2 1.8 2.8 10.5-2.2 9.2-19.7 22.8-3.3 27.4-2.7 10-5.6 7.5-1 6-4.5 2.5-1.3 3.6-14.8 2.2-16.6 9-4.7 5.9-2.2 16.7-3.9 3.4-6.2 10.7-8.6 7.7-8.6 13.4-6.3 3.5-7.1-.6-5.1-2.7-3.8 .2-3.4-3.5-.4 3.3 7.1 5.4-.8 4.4 3.5 2.7-.3 3.1-5.3 8.3-8.3 3.4-17.2 .7 1 12.1-9 3.3-5.3-2.5-2.2 1.8 .8 6.6 3.8 2 3-2.1 1.6 3.5-9.5 6.2-2.2 10.5-5.2 .1-4.4 3.5-1.6 5.2 5.5 5.1 5.3 1.4-1.9 6.4-6.6 4-3.6 8.4-5 2.9-2.3 3.4 1.8 7.6 3.7 4.3-7.3-.4-7.7 4.5-.9 7.1-8.6-2.3-13.3-9.5-3.7-26.2 2.4-6.8 5.9-5.5-8.5-2 5.3-6.1 1.9-11.5 6.2 2.4 2.9-14-3.7-1.7-1.8 8.4-3.5-1 3.7-21.8 2.5-4.5-2-13.5 2.3-.2 9.6-29.6-1.3-9.2 1.7-5-.7-7.5 3.3-7.4 4.5-36.8-.4-9.5-1.2-8.1-5.5-3.3-.5-2.4-25.3-15.4-2.3-4.7 .9-1.7-19.4-35.6-8.3-5.9 1.8-2.5-2.7-5.3 9.1-11.5-1.2-2.5-2.1 2.6-3.3-2.4 .2-6.6 1.9-.9 2.7-9.3 6.9-3.4-.7-1.7 2-.4 1.1-4.8 2.7-.4 4.5-6.4-2-1.3 .1-14.5-4.1-4.6-1.2-3 1.3-1.5-5.2-3.8-2.4 .3-5.1 4.8 2.6 3-4.9 1.8-.9-3.3-2.6 .6-1.1-2.3-6.1-1-.2 1.2-3.6-2-.7-3.4-7.5-5.8-.7 2.9-3.1-2.1 0-4.6-1.6-.8 1.3-1.1-10.9-10.2 2-.4-1-1.8-5.5 .8-15.3-4.4-11.8-9.6-7.4-3.3-10.3 3.1-23.8-8.6-6-4.3-8.8-2.1-11.1-9.6-1.3-2.8 1.9-.6 .7-5.1-4.3-7.9-13.1-14.1-4.8-2.4-.2-5.2-6.1-4.3-1.4-4.1-3-.5-5.8-6.1-5.1-13.5-4-2.5-5.1-1.4 .6 10 17 21.1 5.3 14 2.7 .2 4.3 5.3-3.5 3.2-1.5-3.6-10.4-7.7-.7-7.5-15.3-10.2 2.7-.1 2.3-5-7.6-6-9.8-21.4-6.8-6.1-11.7-3.6-10.5-20.2-6.8-7.5-.7-5.3-3-3.6 1-11.2-1.8-5.1 3.6-18.8-1-9.3-3.4-9.3 .7-1.4 8 2.4 3 6.7 1.3-1.9-2.7-11.6-15.5-10.3-10.1-3-3.1-6.5 .8-4.6-7.1-3.2-1-6-6.7-5.6-.1-3.9-8-5.4-1.5-6.8-7.2-6.4-3-7.6-14.1-.7-6.6-2.4-11.4-8.4-15.1-4.6-7.7 .7-17.5-7.4-6.2 1.8 1.2 5.8-20.6 6.8-.8-4.8 2.5-8.2 5.9-2.6-1.5-2.1-18.9 16.1 4.1 4-5.2 5.8-11.6 5.8-1.4 3.5-8.6 4.1-1.8 3.7-35.3 12.3-.8-1.3 17.4-10.2 6.9-.9 15.8-12.4 3.7-10.9-6.4 2.5-1.8-1.4-3 3-3.6-4.2-1.5 3-2.1-4.1-5.5 3.3-3.4 0 .5-7.9-3.6-3-7.2 1.6-8.5-5.9 0-4.8-4.3-3.6 8.7-14.2 8.3 .8 4.5-4.2 4 .7 4.2-2.7-1-4.1-3.1-1.5 4.1-3.5-9.3 2-1.7 2-4.4-2-7.8 1.1-8.2-2.2-9.3-8.9 20.2-8.4 4.5 0-.7 4.7 11.7-.4-4.5-5.7-6.9-3.6-9.2-8.8-7.7-3 3.1-5 9.9-.4 7-4.4 1.3-4.8 5.7-4.7 15.9-5.7 5.2 .7 8.5-5.4 8.4 2.1 4 4.6 2.5-1.9 9.4 .6-.3 2.3 8.5 1.7 5.6-1 26.7 5.4 7.4-1.6 39.3 12.3 11.6-7.1 8.3 1.2 17.4-6.9 3.8 4.2 4.2-2.3 1.2-4.7 3.9 1 9.4 8.9 7.3-6.7 .8 7.5 6.8-1.6 2.1-2.9 6.8 .6 21.4 7.7 13.1 1 7.5 4.9-7.8 4.8 10.1 2 19.7-2.8 5.9 5.7 6-4.8-5.6-4 3.6-3.3 11.2-1.4 10 7.5 6.2-.8 9.9 4.3 16.7-1.3-.7-5.9 5-1.7 8.6 3.3 0 9 3.5-7.6 4.5 .2 2.5-9.7-12.5-10 .5-11.1 6.6-7.5 7.3 1.7 5.6 4.5 7.6 11.4-5 4.9zM365.7 229.6l-2.7 5 12.3-3.2 7.7 5.3 6.3-5.4 5.1 3.5 4.5 10.2 2.8-4.3-3.9-10.8 4.9-1.5 5.5 1.7 6.2 4.3 5.2 17.3 19.4 9.7-.6 4.4-9.1 .8 3.5 3.8-1.9 3.5-19.6-4.1-16.9 3.9-23.9 2.3-3-4.6-7.6-2.6-4.9 1.1-6.9-7.8 27.4-4.1-10.8-2.3-19.7 .6-3-3.7 12.9-4.1-8.5 .1-9.7-2.7 8.5-11.9 14.9-6.4zM419.4 226.4l-4.8 7-8.7-7.4 9.3-1.9zM575.9 229.8l.5 2.9-11.9-.5-6.1 1.4-7.7-6.2 .2-3.9 15.4 .4zM519.1 229.2l4.4 6.6 5.1-8.5 14.1-4.4 9.5 11-.8 6.9 11-3.1 5.2-4.2 20 10.3 .7 4.5 10.3-2.3 5.8 6.4 13.4 4 4.9 4.1 5.2 9.2-10.2 4.5 13.1 6.3 8.8 2.1 8 8.7 8.8 .6-1.8 6.5-9.7 10.6-6.8-3.9-8.8-8.8-7.2 1.2-.7 5.2 15.7 11.8 3.6 8.8-1.9 6.3-20.9-9.4 13.6 12.8 .9 3-15.1-3.5-11.9-5-6.7-4.3 1.9-2.4-16.4-8.9 .1 2.6-16 1.4-4.7-3.1 3.6-6.6 21.9-1.3-1.8-3.3 9.1-13.7-1.6-4.1-2.1-3.3-8.5-4.7-11.3-3.3 3.6-2.4-5.9-6.1-4.9-.6-4.4-3.4-2.9 3-10.1 1.2-41-6.6-4.6-3.5 5.8-4.7-7.9 0-1.7-10.5 4.2-9.4 5.7-4.3 14.4-2.9zM442.5 221.9l6.6 2.2 9.9-1.3 1.4 3.1-5.1 5.1 8.4 4.5-1 9.4-9.1 4-5.4-.9-17.6-11.9 .1-3.4 11.3 1.3-6.1-6.9zM1797.8 228.6l-20.8-1.6 5.3-4.3 6.9-1 7.9 4.1zM482.2 233.3l-5.9 7.8-6.4-.3-3.4-9.3 .1-5.3 2.9-4.5 5.5-3 11.6 .4 10.6 2.6-8.3 9.6zM330.8 247.6l-14.6 5-3-4.4-12.8-5.4 11-19.2-5.4-6.6 18.8-1.7 7.9 2.3 14.2 .6 11.4 7.6-20.6 10.2-6.9 7.3zM1837.4 208.3l-6.4 4.4-8.9-1-10.3-4.4 1.3-3.7zM479.9 209.4l-3 4.3-8.1-.8-6.7-2.9 3-5 7.9-3.1zM1806 202.9l-4.3 8.3-20.5-.3-9.2 2.6-11-7.2 3-7.8 7.3-2.1 14.7 .5zM452.8 189.7l4.2 5.3 .2 5.9-2.5 8.3-9.2 1.1-6-1.7 .1-6.6-9.1 .9-.3-8.8 6 .3 8.3-3.9 7.8 .7zM398.8 195.7l2.2 4 4.9-1.9 5.9 .5 .9 5.5-3.3 5.3-18.8 1.8-14.1 4.7-8.4 .3-.7-3.6 11.5-4.9-25.1 1.3-7.8-2 7.6-11 5.3-3.2 15.6 3.8 9.9 6.8 9.7 .8-8-10.9 5.1-4.2 5.7 1.4zM1319.6 254.5l-21.4-.5-1.5-4.5-10-2.7-.8-5.6 5.7-2.2-.2-5.7 11-9.1-5.1-1.3 13.3-9.6-1.5-5 30.7-13.2 18.5-2.2 9.5-4.3 10.9-1.5 3.8 4.6-3.7 3.6-36.7 11-17.2 10.5-17 20.7 1.1 8.6zM474 185.3l6.1 3.7 11 0 4.8 3.8-1.3 4.3 9.9 5.3 15.6 1.4 8.8-2.4 20.4-.2 5.9 4.2 1.3 4.6-3.5 3-8.3 2.3-7.1-1.3-27.3 1.9-23.8-4.9-2.6-11.7-5.5-5-11.5-1.4-6.5-3.6 2.1-4.8zM354.4 178.8l-.7 9.1-4.3 4-24.4 7.2-7.5-2.5 20.8-16.2zM478.7 180.3l-13-.4-1.5-3.3 11.2 .1 3.9 2.2zM387.8 178.2l-10.3 3.4-8.2-3.8 4.4-3.8 8.2-1.3 7.8 1.9zM1137.4 176.3l-12.4 4.9-9.8-2.8 3.8-3-3.4-3.9 11.5-2.4 2.2 4.5zM390.8 167.3l-6.8 2.4-9.2 0 .1-1.8 5.7-3.6zM467.6 173.9l-8.2 2.5-4.5-2.8-2.9-9.6 10.5 1.3 6.6 4.2zM444.1 170.7l2.2 5-9.1-1.3-9.1-4-12.4-.4 5.3-3.6-6.7-3-.4-4.7 26 6.2zM1583.8 170.9l-31.4 4.6 10.2-16 4.6-1.4 18.2 7.9zM1101.4 153.7l18.3 9.3-14 4.8-3.1 8.9-4.8 2.2-2.7 9.7-6.7 .5-11.9-7.2 5-4.2-8.3-3.4-10.8-10.3-4.4-9.7 15.2-4.5 3 4.4 7.9-.2 2.1-4.3 8.2-.4zM1141.4 144.7l10.9 4.5-8.3 6.8-16.1 1.5-16.4-2.1-1-3.5-7.9-.2-6.1-5.8 17.2-3.6 8 3.1 5.6-3.9zM1284.1 142.9l-12.5 2.7-.7 2.1-6.5 2.1-6-3 3.1-4-12.3-.4 19.2-2.5 1.2 3.5 8.4-5.3 8.2 2.9zM1555.2 163.9l-12.1 1.5-15.5-3.5-9.2-4.8-4.3-9-7.5-2.5 14.4-8.9 12-2.9 10.8 6.6 12.8 12.3zM516.6 154.2l6.6 4-7.6 3.7-10.2 9.2-9.9 .9-11.5-1.6-6-4.9 .1-4.5 4.4-3.3-10.1 .1-6.2-4.1-3.5-5.8 7.7-9.6 5.7-.9-2.4-3 12.9-.7 7.1 7 18.5 5.2zM619.4 108.7l26.8 2.8 10.2 3.8-.3 3.6-13.5 5.9-13.5 2.7-5 3 12.1-.1-22.1 11.6-9.6 10.5-11.4 2-3.6 2.6-16.8 1.3 7.7 1.6-3.9 2.2 4.6 6-5.2 4.2-8.6 3.4-2.7 4.6-7.7 3.6 .8 2.6 9.5-.4 .1 2.8-14.9 6.9-14.5-3.1-16.3 1.7-18.8-2-.7-5.5 10.3-2.7-2.7-8.5 3.4-.9 14.8 5.2-7.6-7.7-9-2.3 4.5-4.7 9.9-2.9 1.5-4.4-7.8-4.8-2.4-6.6 19.6 2 8.7-4.7-12.5-1.5-19.5 .8-9.8-4.3-4.6-5.3-6.5-3.9-1.2-4.6 25.6-5.1 8.2-5.1 6.9 .7 6 3.8 4.2-7.4 17.3-3.7 17-.6 2.9 1.5 16.1-2.4zM849.4 102.9l34.8 11.1-10.3 5.2-51.1 1.9 2.8 2.4 19.6-1.5 16.8 4.7 10.8-4.1 4.6 4.8-6.1 7.7 14.1-4.9 27-5.2 16.6 2.6 3.2 5.7-22.7 9.2-3.1 3-17.8 2.2 12.9 .6-11 17.2 .2 13.4 6.7 7.7-8.7 .5-9.2 3.6 10.3 6.1 1.3 9.6-5.9 1.1 7.2 9.5-12.4 .7 6.5 4.5-1.9 3.8-15.5 1.6 6.9 7.2 .1 4.7-11-4.4-2.8 2.8 7.5 2.7 7.2 6.3 2.1 8.1-9.9 2-11.1-9.8 1.9 6.9-6.5 5.3 22.3 1-30 16.3-22.3 3.3-5.8 3.7-7.7 9.9-12 6.5-19.2 4.7-4.7 5.6-.1 6.3-2.8 5.8-9.1 7 2.2 6.7-5.3 15.2-7.8 .5-8.2-6.8-11.1 0-5.4-4.7-3.7-8.3-9.7-10.9-2.8-5.7-.7-8.1-7.7-8.4 2-6.9-3.7-3.3 5.5-11.1 8.3-3.6 3.4-11.7-14.4 6.3-6.8-3.2-.4-6.7 2.2-5.3 16.5 2.5-14.5-9.9-5.6 1.4-4.6-2.6 6.2-9.6-14.5-22.9-7-4.3 0-4.7-14.9-6.6-40.2 .5-16.1-11 14.6-3.7 11.2-.6-23.8-3.1-12.5-4.9 .8-4.6 41.3-11.9 2.2-4.5-15-4.6 4.8-5.1 19.3-9 8-1.4-2.3-6 13.2-3.5 34.1-2.3 6.1 4.2 14.7-7.4 32.6 10.5-13.2-7.3 .8-5.8 18.6-8.3 19.5 .7 7.1-5.2 19.7-1.4z';
}

// City lights: 750k+ cities (Natural Earth); each "m" offsets the preceding .1 segment.
function er_map_lights_d( $size ) {
	return 'lg' === $size
		? 'M343 537h.1m105.9 87h.1m62.9-138h.1m41.9 101h.1m4.9-113h.1m12.9 326h.1m9.9-302h.1m5.9 210h.1m.9-215h.1m17.9 433h.1m68.9 7h.1m64.9-67h.1m14.9-21h.1m3.9 18h.1m219.9-367h.1m18.9-77h.1m13.9 19h.1m5.9 259h.1m54.9 85h.1m10.9-25h.1m75.9-266h.1m12.9 70h.1m34.9-175h.1m37.9 155h.1m38.9-15h.1m85.9 66h.1m30.9 11h.1m1.9 23h.1m7.9-74h.1m15.9 17h.1m1.9 92h.1m4.9-25h.1m9.9 24h.1m.9-19h.1m43.9-35h.1m10.9-7h.1m55.9 57h.1m18.9 70h.1m14.9-162h.1m.9 109h.1m-.1 95h.1m36.9-166h.1m3.9 4h.1m-.1 1h.1m.9-49h.1m11.9-59h.1m3.9 5h.1m20.9 147h.1m2.9-97h.1m-.1 37h.1m30.9-77h.1m46.9 18h.1m22.9-6h.1'
		: 'M123 613h.1m192.9-178h.1m1.9 26h.1m1.9-14h.1m-.1 66h.1m2.9 3h.1m1.9-8h.1m22.9 28h.1m.9 8h.1m.9 2h.1m7.9-1h.1m1.9-22h.1m5.9-101h.1m2.9-19h.1m7.9 137h.1m.9-47h.1m5.9 55h.1m18.9 44h.1m4.9-62h.1m-.1 21h.1m2.9 19h.1m5.9-70h.1m7.9 88h.1m.9 29h.1m8.9-3h.1m3.9-6h.1m3.9-21h.1m9.9-22h.1m.9 61h.1m1.9-19h.1m.9-47h.1m.9-32h.1m.9 17h.1m2.9-1h.1m4.9-54h.1m-.1 33h.1m2.9 40h.1m3.9-59h.1m7.9-39h.1m14.9 186h.1m1.9-144h.1m.9 23h.1m-.1 31h.1m1.9 54h.1m1.9 42h.1m7.9-179h.1m2.9 176h.1m2.9-131h.1m2.9-23h.1m-.1 165h.1m9.9-161h.1m-.1 34h.1m1.9 140h.1m4.9-195h.1m3.9 91h.1m-.1 28h.1m3.9-113h.1m-.1 70h.1m1.9 11h.1m7.9-74h.1m-.1 250h.1m1.9-63h.1m2.9 95h.1m.9-299h.1m.9 46h.1m.9 209h.1m4.9-257h.1m.9 37h.1m1.9-8h.1m1.9-3h.1m-.1 129h.1m.9 82h.1m.9-195h.1m2.9-57h.1m.9 213h.1m-.1 23h.1m3.9-26h.1m6.9-211h.1m1.9 30h.1m.9 456h.1m3.9-318h.1m3.9 44h.1m-.1 251h.1m.9-99h.1m1.9-342h.1m1.9 141h.1m4.9 5h.1m5.9 294h.1m2.9-97h.1m.9-150h.1m5.9-1h.1m4.9-45h.1m4.9 256h.1m4.9 28h.1m5.9-81h.1m13.9 90h.1m3.9-173h.1m12.9 127h.1m7.9-57h.1m-.1 115h.1m8.9-87h.1m18.9 57h.1m9.9-78h.1m-.1 50h.1m3.9 13h.1m.9-149h.1m2.9 81h.1m3.9 41h.1m15.9-116h.1m7.9 15h.1m13.9 86h.1m9.9-94h.1m-.1 52h.1m14.9-19h.1m2.9-22h.1m1.9 13h.1m96.9-127h.1m20.9 29h.1m1.9 6h.1m13.9 12h.1m8.9-191h.1m6.9 44h.1m-.1 112h.1m1.9-124h.1m3.9-2h.1m2.9-132h.1m1.9 110h.1m4.9 21h.1m3.9-151h.1m1.9 319h.1m3.9-285h.1m1.9 58h.1m3.9-73h.1m.9 7h.1m2.9 253h.1m4.9-198h.1m-.1 60h.1m.9-24h.1m.9 200h.1m7.9-3h.1m.9-225h.1m3.9 15h.1m-.1 169h.1m1.9 40h.1m2.9-273h.1m-.1 94h.1m4.9 173h.1m1.9-278h.1m-.1 10h.1m-.1 264h.1m2.9-276h.1m-.1 48h.1m2.9 17h.1m.9 221h.1m.9 5h.1m1.9-246h.1m4.9 249h.1m1.9-31h.1m.9 8h.1m.9-219h.1m3.9-16h.1m-.1 218h.1m.9-237h.1m2.9 33h.1m2.9 249h.1m1.9-308h.1m.9 116h.1m2.9-166h.1m1.9 120h.1m1.9-30h.1m-.1 269h.1m4.9-226h.1m.9-99h.1m2.9 157h.1m-.1 123h.1m.9-256h.1m-.1 100h.1m4.9-18h.1m.9-64h.1m3.9 237h.1m.9 91h.1m2.9 47h.1m2.9-361h.1m9.9-85h.1m.9 571h.1m.9-220h.1m2.9-261h.1m3.9 42h.1m.9-61h.1m-.1 119h.1m2.9-82h.1m2.9-53h.1m6.9 353h.1m2.9-272h.1m2.9-13h.1m.9 286h.1m.9-255h.1m.9-81h.1m5.9-80h.1m2.9 578h.1m2.9-460h.1m5.9 40h.1m1.9-108h.1m-.1 397h.1m2.9 84h.1m.9-62h.1m-.1 59h.1m3.9-382h.1m4.9 57h.1m.9 190h.1m.9-391h.1m1.9 73h.1m.9 29h.1m.9 378h.1m-.1 71h.1m7.9-392h.1m-.1 134h.1m.9 0h.1m-.1 85h.1m-.1 150h.1m1.9-382h.1m9.9 50h.1m.9-108h.1m1.9 77h.1m-.1 33h.1m.9-13h.1m2.9 11h.1m.9-119h.1m.9 110h.1m2.9 200h.1m.9-217h.1m3.9-79h.1m4.9 239h.1m2.9-266h.1m-.1 195h.1m-.1 159h.1m1.9-177h.1m-.1 162h.1m.9-307h.1m-.1 163h.1m18.9-90h.1m-.1 146h.1m3.9-286h.1m.9 275h.1m.9-10h.1m.9-208h.1m-.1 58h.1m1.9-10h.1m.9 175h.1m1.9 60h.1m3.9-304h.1m.9 93h.1m2.9 83h.1m3.9 246h.1m1.9-282h.1m.9 7h.1m3.9-11h.1m1.9-168h.1m3.9 110h.1m1.9-90h.1m6.9 184h.1m.9-45h.1m4.9 19h.1m14.9 26h.1m3.9-196h.1m.9-25h.1m18.9 153h.1m5.9-144h.1m3.9 13h.1m30.9 169h.1m7.9 29h.1m1.9-81h.1m1.9 26h.1m.9-44h.1m7.9 117h.1m3.9-70h.1m-.1 23h.1m7.9 54h.1m.9-75h.1m-.1 45h.1m.9 23h.1m.9-215h.1m1.9 229h.1m-.1 8h.1m1.9-81h.1m-.1 92h.1m1.9-161h.1m1.9 57h.1m-.1 9h.1m-.1 6h.1m-.1 110h.1m.9-14h.1m.9-26h.1m1.9-68h.1m.9 27h.1m-.1 10h.1m-.1 15h.1m-.1 65h.1m.9-114h.1m-.1 78h.1m.9 43h.1m2.9-13h.1m.9-188h.1m-.1 81h.1m-.1 115h.1m-.1 14h.1m1.9-177h.1m.9 61h.1m-.1 1h.1m-.1 31h.1m1.9-35h.1m.9 11h.1m.9-4h.1m-.1 10h.1m-.1 84h.1m-.1 10h.1m2.9-5h.1m.9-105h.1m.9 46h.1m4.9-12h.1m1.9-19h.1m1.9 58h.1m1.9-61h.1m1.9 34h.1m1.9-1h.1m.9-24h.1m5.9-197h.1m-.1 197h.1m1.9 45h.1m9.9-46h.1m.9-13h.1m-.1 26h.1m2.9 18h.1m1.9-15h.1m.9-6h.1m2.9 1h.1m3.9-126h.1m4.9 122h.1m5.9 9h.1m11.9-19h.1m-.1 22h.1m5.9-223h.1m17.9 225h.1m-.1 13h.1m-.1 17h.1m13.9 74h.1m2.9-122h.1m5.9 112h.1m.9 36h.1m6.9-219h.1m-.1 196h.1m4.9-125h.1m-.1 41h.1m6.9-108h.1m.9 33h.1m3.9-5h.1m-.1 30h.1m-.1 168h.1m.9-81h.1m.9-105h.1m.9 199h.1m2.9-230h.1m-.1 82h.1m1.9-107h.1m-.1 49h.1m2.9 25h.1m-.1 34h.1m.9-171h.1m3.9 327h.1m2.9-129h.1m.9-39h.1m2.9-69h.1m.9 10h.1m3.9-51h.1m1.9 111h.1m.9-15h.1m-.1 24h.1m-.1 158h.1m6.9-279h.1m4.9 19h.1m-.1 31h.1m-.1 37h.1m.9 194h.1m-.1 4h.1m1.9-213h.1m-.1 7h.1m2.9-40h.1m.9 44h.1m-.1 27h.1m3.9-92h.1m-.1 10h.1m7.9 48h.1m-.1 348h.1m3.9-316h.1m1.9-81h.1m-.1 19h.1m-.1 6h.1m.9-10h.1m.9 15h.1m.9-19h.1m2.9-12h.1m-.1 76h.1m.9-65h.1m1.9 62h.1m.9-43h.1m.9-66h.1m-.1 56h.1m1.9 46h.1m.9 177h.1m3.9-238h.1m-.1 28h.1m-.1 8h.1m-.1 45h.1m1.9-52h.1m-.1 20h.1m-.1 23h.1m.9-103h.1m2.9 21h.1m.9 48h.1m.9-57h.1m1.9 190h.1m.9-221h.1m3.9 17h.1m2.9-5h.1m1.9 190h.1m.9-227h.1m4.9 5h.1m.9 245h.1m.9-226h.1m1.9 221h.1m.9-189h.1m3.9-32h.1m.9-13h.1m-.1 55h.1m.9 14h.1m2.9-7h.1m.9 62h.1m7.9-54h.1m6.9-78h.1m-.1 87h.1m3.9-76h.1m7.9 71h.1m17.9-4h.1m6.9 0h.1m8.9 405h.1m12.9-425h.1m1.9-32h.1m19.9 476h.1m34.9-25h.1m9.9-39h.1m120.9 58h.1';
}

function er_map_base_css() {
	return <<<'CSS'
/* ---- Layers (module 3, both maps) ---- */
.erm > svg,
.erm > .erc-scrim,
.erm > .erc-markers{position: absolute; inset: 0; display: block; width: 100%; height: 100%; max-width: none; margin: 0}
.erm > .erc-markers{z-index: 1}
/* The static map paints with the page; only the JS-drawn layer (night, lights, arcs) fades in. */
.erm > .erc-shade{opacity: 0; transition: opacity .9s ease-out}
.erm.is-ready > .erc-shade{opacity: 1}

.erm .erc-ocean{fill: var(--er-c-ocean)}
.erm .erc-graticule{fill: none; stroke: var(--er-c-accent); stroke-width: 1; vector-effect: non-scaling-stroke; opacity: 0.05}
.erm .erc-land{fill: var(--er-c-day); opacity: 0.85}
.erm .erc-night{fill: var(--er-c-veil); opacity: var(--er-c-band)}
.erm .erc-sun{fill: url(#erc-sun-glow)}
.erm .erc-lights{fill: none; stroke-linecap: round}
.erm .erc-halo{stroke: var(--er-c-light); stroke-width: 7; opacity: 0.20}
.erm .erc-core{stroke: var(--er-c-light-hi); stroke-width: 2.2; opacity: 0.95}
.erm .erc-halo.erc-lg{stroke-width: 13; opacity: 0.24}
.erm .erc-core.erc-lg{stroke-width: 3.4; opacity: 1}

/* ---- Live visitor marker (green, pulsing). HTML, not SVG: animations stay on the compositor. ---- */
.erm .erc-me{position: absolute; left: 0; top: 0; transition: opacity .8s ease}
.erm .erc-others .erc-me{animation: erc-fade .8s ease-out both}
.erm .erc-dot,
.erm .erc-ring,
.erm .erc-hello{position: absolute; border-radius: 50%}
.erm .erc-dot{left: -7px; top: -7px; width: 14px; height: 14px; background: #28a745; box-shadow: 0 0 0 2px rgba(40,167,69,.35)}
.erm .erc-ring,
.erm .erc-hello{left: -7.42px; top: -7.42px; width: 14.83px; height: 14.83px; opacity: 0}
.erm .erc-ring{box-shadow: inset 0 0 0 0.83px #28a745; animation: erc-pulse 2.4s cubic-bezier(.2,.7,.3,1) infinite}
.erm .erc-ring-2{animation-delay: 1.2s}
/* Only the most recent places pulse; the rest stay as still dots. */
.erm .erc-me.is-still .erc-ring{display: none}
.erm .erc-hello{box-shadow: inset 0 0 0 0.4px #28a745; animation: erc-hello 1.8s cubic-bezier(.2,.7,.3,1)}
@keyframes erc-pulse{0%{transform: scale(1); opacity: 0.85} 75%, 100%{transform: scale(6); opacity: 0}}
@keyframes erc-fade{from{opacity: 0}}
@keyframes erc-hello{0%{transform: scale(1); opacity: 1} 100%{transform: scale(10); opacity: 0}}

@media (prefers-reduced-motion: reduce){
	.erm > .erc-shade{transition: none}
	.erm .erc-ring{animation: none; opacity: 0.5; left: -18.05px; top: -18.05px; width: 36.1px; height: 36.1px; box-shadow: inset 0 0 0 2.5px #28a745}
	.erm .erc-others .erc-me{animation: none}
	.erm .erc-hello{display: none}
}
CSS;
}

function er_map_engine_js() {
	return <<<'JS'
/* ER Map engine (module 3) — geometry shared by the cover and Site Reach.
   ERMap.stage(root) binds it to one map: viewBox, pinned HTML markers, night. */
window.ERMap = window.ERMap || (() => {
	const RAD = Math.PI / 180;
	const W = 2000;
	const K = W / (2 * Math.PI);
	const miller = (lat) => 1.25 * Math.log(Math.tan(Math.PI / 4 + 0.4 * lat * RAD)) * K;
	const H = 2 * miller(90);
	const SAME_PLACE = 1.5;

	const f1 = (n) => n.toFixed(1);
	const x = (lon) => (lon + 180) / 360 * W;
	const y = (lat) => H / 2 - miller(Math.max(-89.99, Math.min(89.99, lat)));
	const near = (a, b) => Math.abs(a.lat - b.lat) < SAME_PLACE && Math.abs(a.lon - b.lon) < SAME_PLACE;

	/* ---- Sun and night ---- */

	function subsolar(date) {
		const n = date.getTime() / 86400000 + 2440587.5 - 2451545.0;
		const L = (280.460 + 0.9856474 * n) % 360;
		const g = ((357.528 + 0.9856003 * n) % 360) * RAD;
		const lam = (L + 1.915 * Math.sin(g) + 0.020 * Math.sin(2 * g)) * RAD;
		const eps = (23.439 - 0.0000004 * n) * RAD;
		const dec = Math.asin(Math.sin(eps) * Math.sin(lam));
		const ra = Math.atan2(Math.cos(eps) * Math.sin(lam), Math.cos(lam));
		let gmst = (18.697374558 + 24.06570982441908 * n) % 24;
		if (gmst < 0) gmst += 24;
		const lon = ((-(gmst * 15 - ra / RAD) + 180) % 360 + 360) % 360 - 180;
		return { lat: dec / RAD, lon, dec };
	}

	const norm180 = (a) => ((a / RAD + 180) % 360 + 360) % 360 - 180;

	/* Night below altitude h0. If the dark pole is inside the band (|dec| > |h0|)
	   there is one root per meridian, closed over the pole; otherwise the band is
	   a loop around the antisolar point with two roots per meridian. Do not
	   replace with root-picking by continuity: it collapses the deeper bands. */
	function bandShape(h0, s) {
		const sinH0 = Math.sin(h0 * RAD);
		const MIN_DEC = 0.35 * RAD;
		const dec = Math.abs(s.dec) < MIN_DEC ? (s.dec < 0 ? -MIN_DEC : MIN_DEC) : s.dec;
		const poleIn = Math.abs(dec) > -h0 * RAD;
		const line = [];
		const loops = [];
		let run = null;

		for (let lon = -195; lon <= 195; lon++) {
			const ha = (lon - s.lon) * RAD;
			const A = Math.sin(dec);
			const B = Math.cos(dec) * Math.cos(ha);
			const R = Math.hypot(A, B);
			const phi = Math.atan2(B, A);
			const q = R < 1e-9 ? 0 : sinH0 / R;
			const roots = [];
			if (q >= -1 && q <= 1) {
				const a = Math.asin(q);
				for (const c of [norm180(a - phi), norm180(Math.PI - a - phi)]) {
					if (c >= -90 && c <= 90) roots.push(c);
				}
			}

			if (poleIn) {
				const lat = roots.length
					? (dec > 0 ? Math.max(...roots) : Math.min(...roots))
					: ((B < sinH0) === (dec > 0) ? 90 : -90);
				line.push([lon, lat]);
			} else if (roots.length === 2) {
				if (!run) loops.push(run = []);
				run.push([lon, Math.max(...roots), Math.min(...roots)]);
			} else {
				run = null;
			}
		}
		return poleIn ? { line } : { loops };
	}

	const toD = (pts) => pts.map((p, i) => (i ? 'L' : 'M') + f1(x(p[0])) + ' ' + f1(y(p[1]))).join('');


	function nightD(h0, s, closeY) {
		const shape = bandShape(h0, s);
		return shape.line
			? toD(shape.line) + 'L' + (W + 200) + ' ' + closeY + 'L-200 ' + closeY + 'Z'
			: shape.loops.map((r) => toD(
				r.map((p) => [p[0], p[1]]).concat(r.slice().reverse().map((p) => [p[0], p[2]]))
			) + 'Z').join('');
	}

	/* ---- Great circles ---- */

	function gcPoints(a, b, n) {
		const [p, q] = [a, b].map((v) => {
			const la = v.lat * RAD, lo = v.lon * RAD;
			return [Math.cos(la) * Math.cos(lo), Math.cos(la) * Math.sin(lo), Math.sin(la)];
		});
		const om = Math.acos(Math.max(-1, Math.min(1, p[0] * q[0] + p[1] * q[1] + p[2] * q[2])));
		const so = Math.sin(om);
		if (so < 1e-6) return null;
		const out = [];
		for (let i = 0; i <= n; i++) {
			const k1 = Math.sin((1 - i / n) * om) / so;
			const k2 = Math.sin(i / n * om) / so;
			const X = k1 * p[0] + k2 * q[0], Y = k1 * p[1] + k2 * q[1], Z = k1 * p[2] + k2 * q[2];
			out.push([Math.atan2(Z, Math.hypot(X, Y)) / RAD, Math.atan2(Y, X) / RAD]);
		}
		return out;
	}

	function gcPath(pts, from, to) {
		let d = '';
		let prev = null;
		for (let i = from; i <= to; i++) {
			const [la, lo] = pts[i];
			if (prev && Math.abs(lo - prev[1]) > 180) {
				const edge = lo > prev[1] ? -180 : 180;
				const lo2 = lo > prev[1] ? lo - 360 : lo + 360;
				const lc = f1(y(prev[0] + (la - prev[0]) * (edge - prev[1]) / (lo2 - prev[1])));
				d += 'L' + f1(x(edge)) + ' ' + lc + 'M' + f1(x(-edge)) + ' ' + lc;
			}
			d += (prev ? 'L' : 'M') + f1(x(lo)) + ' ' + f1(y(la));
			prev = pts[i];
		}
		return d;
	}


	/* ---- Stage: one map instance ---- */

	function stage(root) {
		const svgs = root.querySelectorAll('svg');
		const bands = root.querySelectorAll('.erc-night');
		const suns = root.querySelectorAll('.erc-sun');
		const nightClip = root.querySelector('.erc-nightclip');
		const anchors = new Map();
		let box = svgs.length ? svgs[0].getAttribute('viewBox') : '';

		const st = {
			/* x0, y0: top-left of the visible area in map units; upp: map units per CSS px. */
			view: { x0: 0, y0: 0, upp: 1 },
			place(el, p) {
				el.style.transform = 'translate(' + f1((p.x - st.view.x0) / st.view.upp) + 'px,' + f1((p.y - st.view.y0) / st.view.upp) + 'px)';
			},
			pin(el, pt) {
				const p = { x: x(pt.lon), y: y(pt.lat) };
				anchors.set(el, p);
				st.place(el, p);
				return p;
			},
			unpin(el) {
				anchors.delete(el);
			},
			/* nextBox: the viewBox to print; unchanged boxes are not written again. */
			setView(view, nextBox) {
				st.view = view;
				if (nextBox && nextBox !== box) {
					box = nextBox;
					svgs.forEach((svg) => svg.setAttribute('viewBox', nextBox));
				}
				anchors.forEach((p, el) => st.place(el, p));
			},
			/* Night bands, light clip and sun glow for now. Returns the subsolar point. */
			drawNight() {
				const s = subsolar(new Date());
				const closeY = f1(s.dec < 0 ? -400 : H + 400);
				bands.forEach((band) => {
					const alt = band.getAttribute('data-alt');
					const d = nightD(parseFloat(alt), s, closeY);
					band.setAttribute('d', d);
					if (nightClip && alt === '-6') nightClip.setAttribute('d', d);
				});
				if (suns.length === 2) {
					const sx = x(s.lon);
					const sy = f1(y(s.lat));
					suns[0].setAttribute('cx', f1(sx));
					suns[1].setAttribute('cx', f1(sx + (sx < W / 2 ? W : -W)));
					suns.forEach((el) => el.setAttribute('cy', sy));
				}
				return s;
			}
		};
		return st;
	}

	return { RAD, W, H, f1, x, y, near, subsolar, gcPoints, gcPath, stage };
})();
JS;
}

/* ============================================================================
 * 4  COVER — [er_frontpage_cover]
 *    World map behind the front-page hero: day / night, city lights, the
 *    viewer (placed by device time zone), arcs from the origin to live
 *    visitors, clock. Place it first, as a direct child of a Group block.
 *    Everything cover-specific lives here; it can be redesigned freely.
 * ========================================================================== */


function er_map_cover_shortcode( $atts ) {
	static $assets_done = false;

	$atts  = shortcode_atts( array( 'clock' => 'bottom-left' ), $atts, 'er_frontpage_cover' );
	$clock = in_array( $atts['clock'], array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ), true )
		? $atts['clock']
		: '';

	$html = '';
	if ( ! $assets_done ) {
		$assets_done = true;
		$html       .= er_map_base_assets();
		$html       .= '<style id="er-cover-css">' . er_map_cover_css() . '</style>';
		add_action( 'wp_footer', 'er_map_cover_script', 20 );
	}

	$config = wp_json_encode( array(
		'ajax'   => admin_url( 'admin-ajax.php' ),
		'origin' => ER_COVER_ORIGIN,
	) );

	$html .= sprintf(
		'<div class="er-cover erm" aria-hidden="true" data-config="%s"%s>%s</div>',
		esc_attr( $config ),
		$clock ? ' data-clock="' . esc_attr( $clock ) . '"' : '',
		er_map_layers( 'cover' )
	);

	if ( $clock ) {
		$html .= '<div class="erc-clock" aria-hidden="true"><span class="erc-local"></span><span class="erc-sep">|</span><span class="erc-utc"></span></div>';
	}

	return $html;
}

function er_map_cover_script() {
	wp_print_inline_script_tag( er_map_cover_js(), array( 'id' => 'er-cover-js' ) );
}

function er_map_cover_css() {
	return <<<'CSS'
.wp-block-group:has(> .er-cover){
	--er-c-day: var(--color-9);
	--er-c-accent: var(--color-1);
	--er-c-veil: var(--color-6);
	--er-c-ocean: #22405e;
	--er-c-light: #ffc56e;
	--er-c-light-hi: #fff1d2;
	--er-c-clock: #e1e8ed;
	--er-c-band: 0.1875;
	--erc-fs: clamp(10px, 1.05vw, 14px);
	--erc-gx: clamp(14px, 3vw, 34px);
	--erc-gy: clamp(12px, 2.4vh, 26px);
	position: relative;
	display: flex;
	flex-direction: column;
	justify-content: center;
	justify-content: safe center;
	min-height: 100svh;
	width: auto;
	max-width: none;
	margin: 0;
	/* Hero blocks animate in with transforms; the cover owns its box and clips them. */
	overflow: clip;
	background-color: var(--er-c-veil);
}
:has(> .wp-block-group > .er-cover),
:has(> * > .wp-block-group > .er-cover){margin-top: 0}
:empty:has(+ .wp-block-group > .er-cover){display: none}
/* width: constrained layout's auto margins cancel flex stretch. */
.wp-block-group:has(> .er-cover) > :not(.er-cover, .erc-clock){position: relative; z-index: 1; width: 100%}
.wp-block-group:has(> .er-cover) > :is(.er-cover, .erc-clock) + :not(.erc-clock){margin-top: 0}

div.er-cover{
	position: absolute; inset: 0; z-index: 0;
	display: block; width: 100%; height: 100%; max-width: none; margin: 0;
	overflow: hidden; pointer-events: none;
}
@keyframes erc-in{to{opacity: 1}}

.er-cover > .erc-scrim{background: radial-gradient(62% 62% at 50% 50%, rgba(4,7,13,.429), rgba(4,7,13,.22) 55%, rgba(4,7,13,0))}
/* Arcs are drawn complete in one go: no per-frame redraws. */
.er-cover .erc-arcs,
.er-cover .erc-arc{fill: none; stroke-linecap: round; vector-effect: non-scaling-stroke}
.er-cover .erc-arcs{stroke: var(--er-c-light); stroke-width: 1.1; opacity: 0.45}
.er-cover .erc-arc{stroke: var(--er-c-light); stroke-width: 1.5; opacity: 0.55}

/* The viewer's own marker and the origin fade in once placed. */
.er-cover .erc-origin{position: absolute; left: 0; top: 0; transition: opacity .8s ease}
.er-cover .erc-markers > .erc-me,
.er-cover .erc-origin{opacity: 0}
.er-cover .erc-me.is-on,
.er-cover .erc-origin.is-on{opacity: 1}
.er-cover .erc-odot,
.er-cover .erc-oring,
.er-cover .erc-oping{position: absolute; border-radius: 50%}
.er-cover .erc-odot{
	left: -4.5px; top: -4.5px; width: 9px; height: 9px;
	background: #ffe2b0; box-shadow: 0 0 0 2px rgba(255,197,110,.22), 0 0 7px 1px rgba(255,190,90,.25);
}
.er-cover .erc-origin.is-on .erc-odot{transition: box-shadow 2s ease, background-color 2s ease}
.er-cover .erc-origin.is-night .erc-odot{background: #fff4dc; box-shadow: 0 0 0 3px rgba(255,197,110,.40), 0 0 22px 8px rgba(255,180,80,.58)}
.er-cover .erc-oring,
.er-cover .erc-oping{left: -12px; top: -12px; width: 24px; height: 24px}
.er-cover .erc-oring{box-shadow: inset 0 0 0 1px rgba(255,197,110,.55)}
.er-cover .erc-oping{box-shadow: inset 0 0 0 1.2px var(--er-c-light); opacity: 0}
.er-cover .erc-origin.is-out .erc-oping{animation: erc-out 1.8s cubic-bezier(.2,.7,.3,1)}
@keyframes erc-out{0%{transform: scale(1); opacity: 1} 100%{transform: scale(5); opacity: 0}}

.erc-clock{
	position: absolute; z-index: 1; left: var(--erc-gx); bottom: var(--erc-gy);
	font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
	font-size: var(--erc-fs); letter-spacing: .04em; white-space: nowrap;
	color: var(--er-c-clock); opacity: 0; pointer-events: none;
}
.erc-clock .erc-sep{margin: 0 .6em; opacity: 0.5}
.er-cover.is-ready ~ .erc-clock{animation: erc-in 1.2s ease-out 1.65s forwards}
.er-cover[data-clock$="right"] ~ .erc-clock{left: auto; right: var(--erc-gx)}
.er-cover[data-clock^="top"] ~ .erc-clock{bottom: auto; top: var(--erc-gy)}
/* Vignette behind the clock; keeps it above 4.5:1 over pale land. */
.er-cover[data-clock]::after{
	content: ""; position: absolute; font-size: var(--erc-fs); width: 46em; height: 16em;
	left: calc(var(--erc-gx) - 13.5em); bottom: calc(var(--erc-gy) - 7.4em);
	background: radial-gradient(closest-side, rgba(7,12,18,.62), rgba(7,12,18,.5) 50%, rgba(7,12,18,.22) 78%, rgba(7,12,18,0));
}
.er-cover[data-clock$="right"]::after{left: auto; right: calc(var(--erc-gx) - 13.5em)}
.er-cover[data-clock^="top"]::after{bottom: auto; top: calc(var(--erc-gy) - 7.4em)}

@media (prefers-reduced-motion: reduce){
	.erc-clock, .er-cover.is-ready ~ .erc-clock{animation: none; opacity: 1}
	.er-cover .erc-origin,
	.er-cover .erc-origin.is-on .erc-odot{transition: none}
}
CSS;
}

function er_map_cover_js() {
	return <<<'JS'
(() => {
	const root = document.querySelector('.er-cover');
	const E = window.ERMap;
	if (!root || !E) return;

	const { RAD, W, f1, x, y, near, gcPoints, gcPath } = E;
	const st = E.stage(root);
	const $ = (sel) => root.querySelector(sel);
	const meEl = $('.erc-markers > .erc-me');
	const othersEl = $('.erc-others');
	const originEl = $('.erc-origin');
	const arcsEl = $('.erc-arcs');
	const arcEl = $('.erc-arc');
	const clockEl = root.parentElement.querySelector(':scope > .erc-clock');

	let config = {};
	try { config = JSON.parse(root.dataset.config || '{}'); } catch (e) {}

	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const POLL_MS = 90000;
	const HIGHLIGHT_MAX = 12;

	const TZ = {
		"Europe/Zurich":[47.37,8.54],"Europe/Berlin":[52.52,13.40],"Europe/London":[51.51,-0.13],
		"Europe/Paris":[48.86,2.35],"Europe/Madrid":[40.42,-3.70],"Europe/Rome":[41.90,12.50],
		"Europe/Vienna":[48.21,16.37],"Europe/Amsterdam":[52.37,4.90],"Europe/Brussels":[50.85,4.35],
		"Europe/Stockholm":[59.33,18.07],"Europe/Oslo":[59.91,10.75],"Europe/Copenhagen":[55.68,12.57],
		"Europe/Helsinki":[60.17,24.94],"Europe/Warsaw":[52.23,21.01],"Europe/Prague":[50.08,14.44],
		"Europe/Budapest":[47.50,19.04],"Europe/Bucharest":[44.43,26.10],"Europe/Athens":[37.98,23.73],
		"Europe/Lisbon":[38.72,-9.14],"Europe/Dublin":[53.35,-6.26],"Europe/Moscow":[55.75,37.62],
		"Europe/Kyiv":[50.45,30.52],"Europe/Kiev":[50.45,30.52],"Europe/Istanbul":[41.01,28.98],
		"Europe/Zagreb":[45.81,15.98],"Europe/Belgrade":[44.79,20.45],"Europe/Sofia":[42.70,23.32],
		"Europe/Vilnius":[54.69,25.28],"Europe/Riga":[56.95,24.11],"Europe/Tallinn":[59.44,24.75],
		"Europe/Luxembourg":[49.61,6.13],"Europe/Malta":[35.90,14.51],"Europe/Ljubljana":[46.06,14.51],
		"Europe/Bratislava":[48.15,17.11],"Atlantic/Reykjavik":[64.15,-21.94],
		"America/New_York":[40.71,-74.01],"America/Chicago":[41.88,-87.63],"America/Denver":[39.74,-104.99],
		"America/Los_Angeles":[34.05,-118.24],"America/Phoenix":[33.45,-112.07],"America/Anchorage":[61.22,-149.90],
		"America/Toronto":[43.65,-79.38],"America/Vancouver":[49.28,-123.12],"America/Edmonton":[53.55,-113.49],
		"America/Winnipeg":[49.90,-97.14],"America/Halifax":[44.65,-63.58],"America/Montreal":[45.50,-73.57],
		"America/Mexico_City":[19.43,-99.13],"America/Bogota":[4.71,-74.07],"America/Lima":[-12.05,-77.04],
		"America/Santiago":[-33.45,-70.67],"America/Argentina/Buenos_Aires":[-34.60,-58.38],
		"America/Sao_Paulo":[-23.55,-46.63],"America/Caracas":[10.49,-66.88],"America/Panama":[8.98,-79.52],
		"America/Havana":[23.11,-82.37],"America/Costa_Rica":[9.93,-84.08],"Pacific/Honolulu":[21.31,-157.86],
		"Asia/Tokyo":[35.68,139.65],"Asia/Seoul":[37.57,126.98],"Asia/Shanghai":[31.23,121.47],
		"Asia/Hong_Kong":[22.32,114.17],"Asia/Singapore":[1.35,103.82],"Asia/Bangkok":[13.76,100.50],
		"Asia/Jakarta":[-6.21,106.85],"Asia/Manila":[14.60,120.98],"Asia/Kolkata":[22.57,88.36],
		"Asia/Calcutta":[22.57,88.36],"Asia/Karachi":[24.86,67.01],"Asia/Dhaka":[23.81,90.41],
		"Asia/Dubai":[25.20,55.27],"Asia/Riyadh":[24.71,46.68],"Asia/Tehran":[35.69,51.39],
		"Asia/Jerusalem":[31.77,35.21],"Asia/Baghdad":[33.31,44.37],"Asia/Kathmandu":[27.72,85.32],
		"Asia/Colombo":[6.93,79.86],"Asia/Taipei":[25.03,121.57],"Asia/Kuala_Lumpur":[3.14,101.69],
		"Asia/Ho_Chi_Minh":[10.82,106.63],"Asia/Almaty":[43.24,76.89],"Asia/Tashkent":[41.30,69.24],
		"Asia/Yekaterinburg":[56.84,60.65],"Asia/Novosibirsk":[55.01,82.93],"Asia/Vladivostok":[43.12,131.89],
		"Africa/Cairo":[30.04,31.24],"Africa/Lagos":[6.52,3.38],"Africa/Nairobi":[-1.29,36.82],
		"Africa/Johannesburg":[-26.20,28.05],"Africa/Casablanca":[33.57,-7.59],"Africa/Accra":[5.60,-0.19],
		"Africa/Algiers":[36.75,3.06],"Africa/Tunis":[36.81,10.18],"Africa/Addis_Ababa":[9.03,38.74],
		"Africa/Kinshasa":[-4.32,15.31],
		"Australia/Sydney":[-33.87,151.21],"Australia/Melbourne":[-37.81,144.96],
		"Australia/Brisbane":[-27.47,153.03],"Australia/Perth":[-31.95,115.86],
		"Australia/Adelaide":[-34.93,138.60],"Australia/Darwin":[-12.46,130.84],
		"Australia/Hobart":[-42.88,147.32],"Pacific/Auckland":[-36.85,174.76],"Pacific/Fiji":[-18.14,178.44]
	};

	const toLatLon = (o) => Array.isArray(o) && Number.isFinite(+o[0]) && Number.isFinite(+o[1])
		&& Math.abs(o[0]) <= 90 && Math.abs(o[1]) <= 180 ? { lat: +o[0], lon: +o[1] } : null;

	const origin = toLatLon(config.origin);
	const me = (() => {
		let tz;
		try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) {}
		if (tz && TZ[tz]) return { lat: TZ[tz][0], lon: TZ[tz][1] };
		return { lat: 30, lon: Math.max(-180, Math.min(180, -new Date().getTimezoneOffset() / 4)) };
	})();

	/* ---- Night (engine) + origin glow ---- */

	function draw() {
		const s = st.drawNight();
		if (originEl && origin) {
			const sinAlt = Math.sin(origin.lat * RAD) * Math.sin(s.dec)
				+ Math.cos(origin.lat * RAD) * Math.cos(s.dec) * Math.cos((origin.lon - s.lon) * RAD);
			originEl.classList.toggle('is-night', sinAlt < Math.sin(-6 * RAD));
		}
	}

	/* ---- Framing ---- */

	/* Vertical extent of .erc-land in map units. Must match ER_MAP_BOX_COVER. */
	const LAND = { top: 101.1, bot: 1394 };

	/* Cover the box with land: landscape crops latitude, portrait crops longitude. */
	function fit(w, h) {
		if (w === undefined) {
			const r = root.getBoundingClientRect();
			w = r.width;
			h = r.height;
		}
		if (!w || !h) return;
		const band = LAND;
		const artH = band.bot - band.top;
		const aspect = w / h;
		const wide = aspect >= W / artH;
		const vw = wide ? W : artH * aspect;
		const vh = wide ? W / aspect : artH;
		const cy = Math.min(Math.max(y(12), band.top + vh / 2), band.bot - vh / 2);
		const x0 = (W - vw) / 2;
		const y0 = cy - vh / 2;
		/* With preserveAspectRatio slice, a full-width box of the same height renders
		   exactly like [x0 y0 vw vh], so portrait and ordinary landscape boxes keep the
		   printed viewBox and the SVGs are not laid out again. */
		st.setView({ x0, y0, upp: vw / w }, '0 ' + f1(y0) + ' ' + W + ' ' + f1(vh));
	}

	/* ---- Arcs ---- */

	let arcPaths = new Map();

	function showOrigin() {
		if (!originEl || !origin || near(me, origin)) return false;
		originEl.classList.add('is-on');
		return true;
	}

	/* Arcs to other live visitors: one path, rebuilt only when the list changes. */
	function arcsTo(targets) {
		if (!origin || !arcsEl) return;
		const next = new Map();
		targets.forEach((pt) => {
			if (near(pt, origin)) return;
			const key = pt.lat + ',' + pt.lon;
			let d = arcPaths.get(key);
			if (d === undefined) {
				const pts = gcPoints(origin, pt, 64);
				if (!pts) return;
				d = gcPath(pts, 0, pts.length - 1);
			}
			next.set(key, d);
		});
		arcPaths = next;
		showOrigin();
		arcsEl.setAttribute('d', Array.from(next.values()).join(''));
	}

	/* The viewer's own arc, drawn complete. */
	function drawMine() {
		if (!showOrigin()) return;
		if (!reduce) originEl.classList.add('is-out');
		const pts = arcEl && gcPoints(origin, me, 120);
		if (pts) arcEl.setAttribute('d', gcPath(pts, 0, pts.length - 1));
	}

	/* ---- Other live visitors ---- */

	let others = new Map();
	let lastSig = '';
	let firstPoll = true;
	let pollTimer = 0;
	const canPoll = Boolean(config.ajax && othersEl);

	function addOther(pt, arriving) {
		const el = document.createElement('span');
		el.className = 'erc-me';
		el.innerHTML = '<span class="erc-ring"></span><span class="erc-ring erc-ring-2"></span><span class="erc-dot"></span>';
		const offset = Math.random() * 2.4;
		const rings = el.querySelectorAll('.erc-ring');
		rings[0].style.animationDelay = (-offset).toFixed(2) + 's';
		rings[1].style.animationDelay = (1.2 - offset).toFixed(2) + 's';
		if (arriving && !reduce) {
			const hello = document.createElement('span');
			hello.className = 'erc-hello';
			hello.addEventListener('animationend', () => hello.remove(), { once: true });
			el.append(hello);
		}
		st.pin(el, pt);
		othersEl.append(el);
		return el;
	}

	function drawOthers(list) {
		const sig = JSON.stringify(list);
		if (sig === lastSig) return;
		lastSig = sig;

		const next = new Map();
		const targets = [];
		for (const item of list) {
			if (!Array.isArray(item)) continue;
			const pt = { lat: +item[0], lon: +item[1] };
			if (!Number.isFinite(pt.lat) || !Number.isFinite(pt.lon) || near(pt, me)) continue;
			const key = pt.lat + ',' + pt.lon;
			if (next.has(key)) continue;
			const el = others.get(key) || addOther(pt, !firstPoll);
			el.classList.toggle('is-still', targets.length >= HIGHLIGHT_MAX);
			next.set(key, el);
			targets.push(pt);
		}
		others.forEach((el, key) => {
			if (next.has(key)) return;
			el.remove();
			st.unpin(el);
		});
		others = next;
		firstPoll = false;
		arcsTo(targets.slice(0, HIGHLIGHT_MAX));
	}

	function fetchOthers() {
		fetch(config.ajax + '?action=er_map_data&scope=live', { credentials: 'same-origin' })
			.then((r) => (r.ok ? r.json() : null))
			.then((j) => { if (j && Array.isArray(j.data)) drawOthers(j.data); })
			.catch(() => {});
	}

	function startPolling() {
		if (!canPoll || pollTimer) return;
		fetchOthers();
		pollTimer = setInterval(fetchOthers, POLL_MS);
	}

	function stopPolling() {
		clearInterval(pollTimer);
		pollTimer = 0;
	}

	/* ---- Clock ---- */

	const localEl = clockEl && clockEl.querySelector('.erc-local');
	const utcEl = clockEl && clockEl.querySelector('.erc-utc');
	const pad = (n) => String(n).padStart(2, '0');
	let clockTimer = 0;

	function tick() {
		if (!localEl || !utcEl) return;
		const d = new Date();
		localEl.textContent = 'Local ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
		utcEl.textContent = 'UTC ' + d.toISOString().slice(11, 19);
		clearTimeout(clockTimer);
		clockTimer = setTimeout(tick, 1005 - d.getMilliseconds());
	}

	/* ---- Start ---- */

	let live = false;

	if (meEl) st.pin(meEl, me);
	if (originEl && origin) st.pin(originEl, origin);

	let resizeTimer = 0;
	let fitted = false;
	const onResize = (entries) => {
		const cr = entries && entries[0] && entries[0].contentRect;
		const run = () => (cr ? fit(cr.width, cr.height) : fit());
		if (!fitted) {
			fitted = true;
			run();
			return;
		}
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(run, 120);
	};
	if ('ResizeObserver' in window) {
		new ResizeObserver(onResize).observe(root);
	} else {
		onResize();
		window.addEventListener('resize', () => onResize(), { passive: true });
	}

	/* Night, lights, the viewer's dot and arc are computed after the first frame
	   (not inside the parse task) and fade in together. */
	requestAnimationFrame(() => setTimeout(() => {
		try {
			draw();
			drawMine();
			if (meEl) meEl.classList.add('is-on');
		} finally {
			root.classList.add('is-ready');
			live = true;
			setInterval(draw, 60000);
		}
	}, 0));

	document.addEventListener('visibilitychange', () => {
		if (document.hidden) {
			stopPolling();
			return;
		}
		tick();
		if (live) draw();
		startPolling();
	});

	tick();
	setTimeout(() => { if (!document.hidden) startPolling(); }, 2600);
})();
JS;
}

/* ============================================================================
 * 5  SITE REACH — [live_user_map], [currently_visited_pages], [past_visited_pages]
 *    Interactive map (drag, pinch, ctrl+wheel, +/− and double-click to zoom;
 *    tap a dot for details) plus two tables. One request per minute feeds all
 *    three; polling pauses while the tab is hidden.
 *    Shortcode names are the old ones, so the page content stays as it is.
 * ========================================================================== */


// CSS once per page, JS once in the footer. Shared by all three shortcodes.
function er_reach_assets() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	add_action( 'wp_footer', 'er_reach_script', 20 );
	return '<style id="er-reach-css">' . er_reach_css() . '</style>';
}

function er_reach_script() {
	$js = str_replace( '__ER_AJAX__', wp_json_encode( admin_url( 'admin-ajax.php' ) ), er_reach_js() );
	wp_print_inline_script_tag( $js, array( 'id' => 'er-reach-js' ) );
}

function er_reach_map_shortcode() {
	return er_map_base_assets()
		. er_reach_assets()
		. '<div class="er-reach erm" role="region" aria-label="World map of live and past visitors">'
		. er_map_layers( 'reach' )
		. '</div>'
		. '<div class="erm-stats">'
		. '<span>Live Visitors: <strong id="live-count">0</strong><span class="erm-sep">·</span>Last Updated: <strong id="last-update">&ndash;</strong></span>'
		. '<span><span class="erm-key"><i class="is-live"></i>Live Visitors</span><span class="erm-key"><i class="is-past"></i>Past Visitors</span></span>'
		. '</div>';
}

function er_reach_current_shortcode() {
	return er_reach_assets()
		. '<div class="wp-block-table lum-pages-table" id="lum-current-pages"><p class="erm-muted">Loading&hellip;</p></div>';
}

function er_reach_past_shortcode() {
	return er_reach_assets()
		. '<div class="wp-block-table lum-pages-table" id="lum-past-pages"><p class="erm-muted">Loading&hellip;</p></div>';
}

function er_reach_css() {
	return <<<'CSS'
.er-reach{
	--er-c-day: var(--color-9, #8da6b9);
	--er-c-accent: var(--color-1, #1e73be);
	--er-c-veil: var(--color-6, #070c12);
	--er-c-ocean: #22405e;
	--er-c-light: #ffc56e;
	--er-c-light-hi: #fff1d2;
	--er-c-band: 0.1875;
	--er-c-past: var(--color-1, #1e73be);
	position: relative;
	width: 100%;
	aspect-ratio: 2000 / 1013;
	min-height: 260px;
	max-height: 80vh;
	border-radius: 15px;
	overflow: hidden;
	background: var(--er-c-ocean);
	/* Unzoomed: vertical swipes scroll the page. Zoomed: the map owns all gestures. */
	touch-action: pan-y;
	cursor: grab;
	-webkit-user-select: none; user-select: none;
	-webkit-tap-highlight-color: transparent;
}
.er-reach.is-zoomed{touch-action: none}
.er-reach.is-dragging{cursor: grabbing}
/* During a gesture the layers are moved with one transform; the viewBox is set on release. */
.er-reach > svg,
.er-reach > .erc-markers{transform-origin: 0 0}
.er-reach > .erc-markers{pointer-events: none}

.er-reach .erc-past{fill: none; stroke: var(--er-c-past); stroke-width: 5; stroke-linecap: round; vector-effect: non-scaling-stroke; opacity: 0.9}
.er-reach .erc-past.erc-lg{stroke-width: 8}

.er-reach .erm-pin{position: absolute; left: 0; top: 0; z-index: 2; pointer-events: auto}
.er-reach .erm-pin[hidden]{display: none}
.er-reach .erm-card{
	position: absolute; left: 0; bottom: 12px; transform: translateX(-50%);
	width: max-content; min-width: 190px; max-width: 260px; padding: 10px 12px;
	background: #fff; color: #333; border-radius: 10px; box-shadow: 0 4px 18px rgba(0,0,0,.3);
	font-size: var(--er-fs-xs, 13px); line-height: 1.6; cursor: auto;
	-webkit-user-select: text; user-select: text;
}
.er-reach .is-below .erm-card{bottom: auto; top: 12px}
.er-reach .is-left .erm-card{transform: translateX(-16px)}
.er-reach .is-right .erm-card{transform: translateX(calc(-100% + 16px))}
.er-reach .erm-card b{display: block; font-size: var(--er-fs-sm, 14px); margin-bottom: 4px; color: #222}
.er-reach .erm-card a{color: var(--color-1, #1e73be)}
.er-reach .erm-badge{display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 10px; font-size: .8em; font-weight: 600; background: #d1ecf1; color: #0c5460; vertical-align: 1px}
.er-reach .erm-badge.is-live{background: #d4edda; color: #155724}

.er-reach .erm-zoom{position: absolute; z-index: 2; left: 10px; top: 10px; display: flex; flex-direction: column; gap: 4px}
.er-reach .erm-zoom button{
	width: 32px; height: 32px; padding: 0; border: 0; border-radius: 8px;
	background: rgba(7,12,18,.6); color: #e1e8ed; font: 600 18px/1 system-ui, sans-serif; cursor: pointer;
}
.er-reach .erm-zoom button:hover{background: rgba(7,12,18,.85)}
.er-reach .erm-zoom button:focus-visible{outline: 2px solid var(--color-1, #1e73be); outline-offset: 2px}

.erm-stats{display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 20px; margin-top: 12px; padding: 0 4px}
.erm-stats .erm-sep{margin: 0 .6em; opacity: .5}
.erm-stats .erm-key{display: inline-flex; align-items: center; gap: 6px; margin-left: 14px}
.erm-stats .erm-key i{display: inline-block; width: 10px; height: 10px; border-radius: 50%}
.erm-stats .erm-key i.is-live{background: #28a745; box-shadow: 0 0 0 2px rgba(40,167,69,.3)}
.erm-stats .erm-key i.is-past{background: var(--color-1, #1e73be);}
.erm-muted{color: #999}

/* Tables — scoped to .lum-pages-table so nothing leaks into other tables. */
.lum-pages-table{max-height: 350px; overflow-y: auto}
.lum-pages-table table{table-layout: fixed !important; width: 100% !important}
.lum-pages-table col.col-page{width: 58%}
.lum-pages-table col.col-visitors{width: 15%}
.lum-pages-table col.col-location{width: 27%}
.lum-pages-table td:first-child{overflow-wrap: anywhere}
@media (max-width: 600px){
	.lum-pages-table table th,
	.lum-pages-table table td{font-size: var(--er-fs-sm)}
}
CSS;
}

function er_reach_js() {
	return <<<'JS'
(() => {
	const AJAX = __ER_AJAX__;
	const POLL_MS = 60000;

	const esc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	const pretty = (s) => { try { return decodeURIComponent(String(s)); } catch (e) { return String(s); } };
	const label = (path) => (path === '/' ? 'Home' : pretty(path));
	const ago = (s) => (s < 60 ? s + ' seconds ago' : s < 3600 ? Math.floor(s / 60) + ' minutes ago'
		: s < 86400 ? Math.floor(s / 3600) + ' hours ago' : Math.floor(s / 86400) + ' days ago');
	const regions = (() => { try { return new Intl.DisplayNames(['en'], { type: 'region' }); } catch (e) { return null; } })();
	const country = (cc) => { try { return cc && regions ? regions.of(cc.toUpperCase()) : (cc || ''); } catch (e) { return cc || ''; } };
	const flag = (cc) => (/^[A-Za-z]{2}$/.test(cc || '') ? String.fromCodePoint(...cc.toUpperCase().split('').map((c) => 127397 + c.charCodeAt(0))) : '');

	/* ================= Map ================= */

	function initMap(root) {
		const E = window.ERMap;
		if (!root || !E) return null;

		const { W, f1, x, y } = E;
		const st = E.stage(root);
		const MAX_Z = 8;
		/* World band in map units: 84°N – 60°S. Must match ER_MAP_BOX_REACH. */
		const B = { x0: 0, y0: 101.1, x1: W, y1: 1114.2 };
		const layers = Array.from(root.children).filter((el) => el.matches('svg, .erc-markers'));
		const pastSm = root.querySelector('.erc-past:not(.erc-lg)');
		const pastLg = root.querySelector('.erc-past.erc-lg');
		const othersEl = root.querySelector('.erc-others');
		const pin = root.querySelector('.erm-pin');
		const card = root.querySelector('.erm-card');
		const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		const clamp = (v, a, b) => Math.min(Math.max(v, a), b);

		let w = 0, h = 0, cur = null;
		let pts = [];              // every clickable point, with map coordinates
		let live = new Map();      // key -> marker element
		let firstData = true;

		/* ---- View: z = zoom (1 = whole band), cx/cy = centre in map units ---- */

		function base() {
			const bw = B.x1 - B.x0, bh = B.y1 - B.y0, a = w / h;
			return a >= bw / bh ? { vw: bw, vh: bw / a } : { vw: bh * a, vh: bh };
		}
		function viewFor(z, cx, cy) {
			z = clamp(z, 1, MAX_Z);
			const b = base();
			const vw = b.vw / z, vh = b.vh / z;
			cx = clamp(cx, B.x0 + vw / 2, B.x1 - vw / 2);
			cy = clamp(cy, B.y0 + vh / 2, B.y1 - vh / 2);
			return { z, cx, cy, x0: cx - vw / 2, y0: cy - vh / 2, vw, vh, upp: vw / w };
		}
		/* Zoom v by factor f, keeping the map point under screen point (px, py) in place. */
		function zoomed(v, px, py, f) {
			const z = clamp(v.z * f, 1, MAX_Z);
			const b = base();
			const upp = b.vw / z / w;
			const mx = v.x0 + px * v.upp, my = v.y0 + py * v.upp;
			return viewFor(z, mx - px * upp + b.vw / z / 2, my - py * upp + b.vh / z / 2);
		}
		function commit(v) {
			cur = v;
			layers.forEach((el) => { el.style.transform = ''; });
			st.setView({ x0: v.x0, y0: v.y0, upp: v.upp }, [v.x0, v.y0, v.vw, v.vh].map((n) => n.toFixed(2)).join(' '));
			root.classList.toggle('is-zoomed', v.z > 1.001);
		}
		/* Show v without touching the SVGs: one transform on each layer (compositor only). */
		function preview(v) {
			const s = cur.upp / v.upp;
			const t = 'translate(' + f1((cur.x0 - v.x0) / v.upp) + 'px,' + f1((cur.y0 - v.y0) / v.upp) + 'px) scale(' + s.toFixed(4) + ')';
			layers.forEach((el) => { el.style.transform = t; });
		}

		/* ---- Size ---- */

		let resizeTimer = 0;
		const onSize = (cw, ch) => {
			if (!cw || !ch) return;
			w = cw; h = ch;
			commit(cur ? viewFor(cur.z, cur.cx, cur.cy) : viewFor(1, W / 2, y(20)));
		};
		if ('ResizeObserver' in window) {
			let first = true;
			new ResizeObserver((entries) => {
				const cr = entries[0].contentRect;
				if (first) { first = false; onSize(cr.width, cr.height); return; }
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(() => onSize(cr.width, cr.height), 120);
			}).observe(root);
		} else {
			const r = root.getBoundingClientRect();
			onSize(r.width, r.height);
		}

		/* ---- Gestures: drag, pinch, ctrl+wheel / trackpad pinch, double-click, buttons ---- */

		const ptrs = new Map();
		let seg = null, tv = null, tap = null, lastTap = null, wheelTimer = 0;
		const pos = (e) => { const r = root.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; };
		const segStart = () => { seg = { sv: tv || cur, p0: new Map(Array.from(ptrs, ([k, p]) => [k, { x: p.x, y: p.y }])) }; };

		root.addEventListener('pointerdown', (e) => {
			if (!cur || e.button > 0 || e.target.closest('.erm-pin, .erm-zoom')) return;
			try { root.setPointerCapture(e.pointerId); } catch (err) {}
			const p = pos(e);
			ptrs.set(e.pointerId, p);
			tap = ptrs.size === 1 ? { x: p.x, y: p.y, moved: 0, type: e.pointerType } : null;
			segStart();
		});

		root.addEventListener('pointermove', (e) => {
			if (!ptrs.has(e.pointerId) || !seg) return;
			ptrs.set(e.pointerId, pos(e));
			const list = Array.from(ptrs);
			const sv = seg.sv;
			if (list.length === 1) {
				const [id, p] = list[0];
				const q = seg.p0.get(id);
				if (!q) return;
				const dx = p.x - q.x, dy = p.y - q.y;
				if (tap) {
					tap.moved = Math.max(tap.moved, Math.hypot(dx, dy));
					if (tap.moved < 5) return;
				}
				tv = viewFor(sv.z, sv.cx - dx * sv.upp, sv.cy - dy * sv.upp);
			} else {
				const [ia, a] = list[0], [ib, b] = list[1];
				const a0 = seg.p0.get(ia), b0 = seg.p0.get(ib);
				if (!a0 || !b0) { segStart(); return; }
				const d0 = Math.hypot(b0.x - a0.x, b0.y - a0.y), d = Math.hypot(b.x - a.x, b.y - a.y);
				if (d0 < 10) return;
				const m0 = { x: (a0.x + b0.x) / 2, y: (a0.y + b0.y) / 2 };
				const m = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
				const z = clamp(sv.z * d / d0, 1, MAX_Z);
				const bs = base();
				const upp = bs.vw / z / w;
				const mx = sv.x0 + m0.x * sv.upp, my = sv.y0 + m0.y * sv.upp;
				tv = viewFor(z, mx - m.x * upp + bs.vw / z / 2, my - m.y * upp + bs.vh / z / 2);
			}
			root.classList.add('is-dragging');
			preview(tv);
		});

		const endPointer = (e) => {
			if (!ptrs.delete(e.pointerId)) return;
			if (ptrs.size) { segStart(); return; }
			root.classList.remove('is-dragging');
			if (tv) {
				commit(tv);
			} else if (tap && e.type === 'pointerup') {
				onTap(tap);
			}
			tv = null; seg = null; tap = null;
		};
		root.addEventListener('pointerup', endPointer);
		root.addEventListener('pointercancel', endPointer);

		root.addEventListener('dblclick', (e) => {
			if (!cur || e.target.closest('.erm-pin, .erm-zoom')) return;
			const p = pos(e);
			commit(zoomed(cur, p.x, p.y, 2));
		});

		root.addEventListener('wheel', (e) => {
			if (!cur || !e.ctrlKey) return; // plain wheel keeps scrolling the page
			e.preventDefault();
			const p = pos(e);
			tv = zoomed(tv || cur, p.x, p.y, Math.exp(-e.deltaY * 0.01));
			preview(tv);
			clearTimeout(wheelTimer);
			wheelTimer = setTimeout(() => { if (tv) commit(tv); tv = null; }, 150);
		}, { passive: false });

		root.querySelectorAll('.erm-zoom button').forEach((btn) => {
			btn.addEventListener('click', () => {
				if (cur) commit(zoomed(cur, w / 2, h / 2, parseFloat(btn.dataset.zoom) || 1));
			});
		});

		/* ---- Popup: nearest dot within reach of the tap ---- */

		function onTap(t) {
			const now = Date.now();
			if (t.type === 'touch' && lastTap && now - lastTap.t < 320 && Math.hypot(t.x - lastTap.x, t.y - lastTap.y) < 30) {
				lastTap = null;
				commit(zoomed(cur, t.x, t.y, 2));
				return;
			}
			lastTap = { x: t.x, y: t.y, t: now };
			let best = null, bd = Infinity;
			for (const p of pts) {
				const d = Math.hypot((p.mx - cur.x0) / cur.upp - t.x, (p.my - cur.y0) / cur.upp - t.y) - (p.live ? 6 : 0);
				if (d < bd) { bd = d; best = p; }
			}
			if (best && bd < 16) show(best); else hide();
		}

		function popupHtml(p) {
			const place = '<b>' + esc(p.city || 'Unknown City') + (p.live ? '<span class="erm-badge is-live">LIVE</span>' : '<span class="erm-badge">PAST</span>') + '</b>'
				+ esc(country(p.cc)) + ' ' + flag(p.cc) + '<br>';
			return p.live
				? place + 'Page: <a href="' + esc(p.page) + '">' + esc(label(p.page)) + '</a><br>Browser: ' + esc(p.browser) + '<br>Last seen: ' + ago(p.ago)
				: place + 'Visitors: ' + p.n.toLocaleString() + '<br>Last seen: ' + ago(p.ago);
		}

		function show(p) {
			card.innerHTML = popupHtml(p);
			pin.hidden = false;
			st.pin(pin, p);
			const sx = (p.mx - cur.x0) / cur.upp, sy = (p.my - cur.y0) / cur.upp;
			pin.classList.toggle('is-below', sy < 150);
			pin.classList.toggle('is-left', sx < 130);
			pin.classList.toggle('is-right', sx > w - 130);
		}
		function hide() {
			pin.hidden = true;
			st.unpin(pin);
		}
		document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });

		/* ---- Data ---- */

		function addLive(p, arriving) {
			const el = document.createElement('span');
			el.className = 'erc-me';
			el.innerHTML = '<span class="erc-ring"></span><span class="erc-ring erc-ring-2"></span><span class="erc-dot"></span>';
			const offset = Math.random() * 2.4;
			const rings = el.querySelectorAll('.erc-ring');
			rings[0].style.animationDelay = (-offset).toFixed(2) + 's';
			rings[1].style.animationDelay = (1.2 - offset).toFixed(2) + 's';
			if (arriving && !reduce) {
				const hello = document.createElement('span');
				hello.className = 'erc-hello';
				hello.addEventListener('animationend', () => hello.remove(), { once: true });
				el.append(hello);
			}
			st.pin(el, p);
			othersEl.append(el);
			return el;
		}

		function update(data) {
			const next = [];
			const dots = { sm: '', lg: '' };
			(data.past || []).forEach((r) => {
				const p = { lat: r[0], lon: r[1], n: r[2], ago: r[3], city: r[4], cc: r[5] };
				p.mx = x(p.lon); p.my = y(p.lat);
				dots[p.n >= 5 ? 'lg' : 'sm'] += 'M' + f1(p.mx) + ' ' + f1(p.my) + 'h.1';
				next.push(p);
			});
			pastSm.setAttribute('d', dots.sm);
			pastLg.setAttribute('d', dots.lg);

			const keep = new Map();
			(data.live || []).forEach((p, i) => {
				p.live = true;
				p.mx = x(p.lon); p.my = y(p.lat);
				next.push(p);
				const key = p.lat + ',' + p.lon;
				if (keep.has(key)) return;
				const el = live.get(key) || addLive(p, !firstData);
				el.classList.toggle('is-still', keep.size >= 12);
				keep.set(key, el);
			});
			live.forEach((el, key) => {
				if (keep.has(key)) return;
				el.remove();
				st.unpin(el);
			});
			live = keep;
			pts = next;
			firstData = false;
		}

		/* Night now, then every minute; fades in with the first paint. */
		const night = () => st.drawNight();
		requestAnimationFrame(() => setTimeout(() => {
			try { night(); } finally {
				root.classList.add('is-ready');
				setInterval(night, 60000);
			}
		}, 0));
		document.addEventListener('visibilitychange', () => { if (!document.hidden) night(); });

		return { update };
	}

	/* ================= Tables ================= */

	const cols = '<colgroup><col class="col-page"><col class="col-visitors"><col class="col-location"></colgroup>';
	const link = (path) => '<a href="' + esc(path) + '">' + esc(label(path)) + '</a>';

	function renderCurrent(el, list) {
		if (!list || !list.length) { el.innerHTML = '<p class="erm-muted">No live visitors</p>'; return; }
		const pages = new Map();
		list.forEach((u) => {
			if (!pages.has(u.page)) pages.set(u.page, { count: 0, city: u.city, country: country(u.cc) });
			pages.get(u.page).count++;
		});
		let html = '<table>' + cols + '<thead><tr><th>Page</th><th>Visitors</th><th>Location</th></tr></thead><tbody>';
		pages.forEach((d, page) => {
			html += '<tr><td>' + link(page) + '</td><td>' + d.count + '</td><td>' + esc(d.city) + (d.city && d.country ? ', ' : '') + esc(d.country) + '</td></tr>';
		});
		el.innerHTML = html + '</tbody></table>';
	}

	function renderPast(el, list) {
		if (!list || !list.length) { el.innerHTML = '<p class="erm-muted">No past visitors</p>'; return; }
		let html = '<table>' + cols + '<thead><tr><th>Page</th><th>Views</th><th>Location</th></tr></thead><tbody>';
		list.forEach((p) => {
			html += '<tr><td>' + link(p.url) + '</td><td>' + Number(p.views).toLocaleString() + '</td><td>'
				+ p.locations + (p.locations === 1 ? ' location' : ' locations') + '</td></tr>';
		});
		el.innerHTML = html + '</tbody></table>';
	}

	/* ================= One poll for everything ================= */

	const map = initMap(document.querySelector('.er-reach'));
	const curEl = document.getElementById('lum-current-pages');
	const pastEl = document.getElementById('lum-past-pages');
	const countEl = document.getElementById('live-count');
	const timeEl = document.getElementById('last-update');
	if (!map && !curEl && !pastEl) return;

	function render(d) {
		if (map) map.update(d);
		if (curEl) renderCurrent(curEl, d.live);
		if (pastEl) renderPast(pastEl, d.pages);
		if (countEl) countEl.textContent = (d.live || []).length;
		if (timeEl) timeEl.textContent = new Date().toLocaleTimeString();
	}

	let timer = 0;
	function load() {
		fetch(AJAX + '?action=er_map_data&scope=full', { credentials: 'same-origin' })
			.then((r) => (r.ok ? r.json() : null))
			.then((j) => { if (j && j.success && j.data) render(j.data); })
			.catch(() => {});
	}
	function start() { if (!timer) { load(); timer = setInterval(load, POLL_MS); } }
	function stop() { clearInterval(timer); timer = 0; }
	document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
	if (!document.hidden) start();
})();
JS;
}
