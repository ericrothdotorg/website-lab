<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* ============================================================================
 * VISITOR TRACKING — the site's only source of real visitor data
 * ----------------------------------------------------------------------------
 * Scope: "Run snippet everywhere" (mandatory). The beacon is answered via
 * admin-ajax and the cleanup runs via wp-cron; in both contexts a
 * front-end-only snippet would never register its hooks.
 *
 * WHAT IT DOES
 *   Every front-end page view sends one background request after load. The
 *   handler resolves the city (ip-api.com, anonymised IP), writes
 *   er_live_visitors (one row per visitor) and er_map_views (one row per
 *   visitor, page and day), reports the page view for the view counter and
 *   answers with the visitor's own position.
 *
 * OWNS
 *   Tables er_live_visitors and er_map_views: created here if missing,
 *   cleaned here (ER_TRK_DAYS, daily cron lum_daily_cleanup).
 *
 * PROVIDES (used by World Map)
 *   er_trk_visitor_id()      id of the current visitor
 *   er_trk_browser( $ua )    browser name from a user agent
 *   In the browser: event "er:visitor", window.ERVisitor and sessionStorage
 *   "er_visitor", each { lat, lon, city, cc, page, browser, seen }, as soon
 *   as the answer to the beacon arrives.
 *
 * USES
 *   er_track_post_views( $post_id ) — optional; without it the map data is
 *   still written, only the view counter is not.
 *
 * LEGACY NAMES, KEPT ON PURPOSE
 *   Cookie lum_visitor_id (renaming would make every visitor new), cron hook
 *   lum_daily_cleanup (already scheduled), AJAX action lum_background_track
 *   and transient keys lum_* (running 24-hour locks).
 * ========================================================================== */

defined( 'ER_TRK_DAYS' ) || define( 'ER_TRK_DAYS', 90 );   // days both tables keep their rows (World Map shows the same window)

// ======================================
// TABLES
// ======================================

// Creates both tables if they are missing (new install, partial restore).
// CREATE TABLE IF NOT EXISTS never touches an existing table. Runs once per
// schema version on init, and daily from the cleanup as a safety net.
function er_trk_install_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}er_live_visitors (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        visitor_id varchar(64) NOT NULL,
        ip_address varchar(45) NOT NULL,
        latitude decimal(10,8) DEFAULT NULL,
        longitude decimal(11,8) DEFAULT NULL,
        city varchar(100) DEFAULT NULL,
        country varchar(100) DEFAULT NULL,
        country_code varchar(10) DEFAULT NULL,
        page_url text DEFAULT NULL,
        user_agent text DEFAULT NULL,
        last_seen datetime NOT NULL,
        visit_time datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY unique_visitor_id (visitor_id),
        KEY last_seen (last_seen)
    ) {$charset}");
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}er_map_views (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        page_url varchar(255) NOT NULL,
        city varchar(100) DEFAULT NULL,
        viewed_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY idx_viewed_at (viewed_at)
    ) {$charset}");
}
add_action('init', function() {
    if (get_option('er_trk_schema') !== '1') {
        er_trk_install_tables();
        update_option('er_trk_schema', '1');
    }
});

// ======================================
// VISITOR ID
// ======================================

// Id of the current visitor: the cookie set by the beacon or, without it, a
// hash of the anonymised IP and the user agent. World Map uses the same
// function to recognise the viewer in its live data.
function er_trk_visitor_id() {
    if (!empty($_COOKIE['lum_visitor_id'])) {
        return substr(sanitize_text_field(wp_unslash($_COOKIE['lum_visitor_id'])), 0, 64);
    }
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return 'v_' . md5(er_trk_anonymize_ip(er_trk_get_client_ip()) . $user_agent);
}

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
            // Don't track if user is a bot (client-side check). navigator.webdriver is
            // true in remote-controlled browsers that declare themselves (many
            // scrapers and test tools); real visitors never have it. Google's page
            // speed test does not declare itself and is still tracked.
            if (navigator.webdriver || /(bot|crawl|spider|slurp|lighthouse|headlesschrome)/i.test(navigator.userAgent)) {
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
                // fetch with keepalive: survives leaving the page like sendBeacon,
                // but the answer can be read. It carries the visitor's own position,
                // published for the maps (see header: PROVIDES).
                fetch(ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString(), keepalive: true, credentials: 'same-origin' })
                    .then(function(r) { return r.ok ? r.json() : null; })
                    .then(function(j) {
                        if (!j || !j.success || !j.data) return;
                        var v = j.data;
                        v.seen = Date.now();
                        window.ERVisitor = v;
                        try { sessionStorage.setItem('er_visitor', JSON.stringify(v)); } catch (e) {}
                        document.dispatchEvent(new CustomEvent('er:visitor', { detail: v }));
                    })
                    .catch(function() {});
            }
            // Idle, but at most 2 s after load: without a timeout a busy page can
            // hold the beacon back much longer.
            function whenIdle() {
                ('requestIdleCallback' in window) ? requestIdleCallback(fireTracking, { timeout: 2000 }) : setTimeout(fireTracking, 0);
            }
            if (document.readyState === 'complete') {
                whenIdle();
            } else {
                window.addEventListener('load', whenIdle);
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
    $visitor_id = er_trk_visitor_id();
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
    $me = null;
    if ($geo_data) {
        $current_time = current_time('mysql');
        // Single atomic query: insert a new visitor, or update the existing row
        // (unique key: visitor_id). The location is refreshed as well, so a
        // visitor who travels is shown where they are now, not where they were
        // first seen.
		$wpdb->query($wpdb->prepare(
			"INSERT INTO $table_name
				(visitor_id, ip_address, latitude, longitude, city, country, country_code, page_url, user_agent, last_seen, visit_time)
			VALUES
				(%s, %s, %f, %f, %s, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				ip_address = VALUES(ip_address),
				latitude = VALUES(latitude),
				longitude = VALUES(longitude),
				city = VALUES(city),
				country = VALUES(country),
				country_code = VALUES(country_code),
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
		// DEDUP: one row per visitor per page per 24h. The Past-list "views" column therefore reads as unique daily visitors, not raw page loads. Keyed on visitor_id + page_url. Does NOT affect the map dots (those read from er_live_visitors).
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
		// The visitor's own position, sent back to their browser only.
		$me = array(
			'lat'     => (float) $geo_data['lat'],
			'lon'     => (float) $geo_data['lon'],
			'city'    => (string) $geo_data['city'],
			'cc'      => (string) $geo_data['countryCode'],
			'page'    => (string) (wp_parse_url($page_url, PHP_URL_PATH) ?: '/'),
			'browser' => er_trk_browser($user_agent),
		);
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
    // null when the location could not be resolved; the maps then simply
    // wait for the regular live data.
    wp_send_json_success($me);
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
        'lighthouse', 'headlesschrome', // page speed tests, automated browsers
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
// Rows are stored in site time, so the cutoff is computed in site time too
// (wp_date), never with date() or NOW(), which run on UTC.
function er_trk_cleanup_old_visitors() {
    global $wpdb;
    // Daily safety net: recreates a table that went missing (e.g. a partial restore).
    er_trk_install_tables();
    $table_name = $wpdb->prefix . 'er_live_visitors';
    $threshold = wp_date('Y-m-d H:i:s', time() - ER_TRK_DAYS * DAY_IN_SECONDS);
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE last_seen < %s",
        $threshold
    ));
    if ($deleted === false) {
        error_log('Visitor Tracking: cleanup of er_live_visitors failed - ' . $wpdb->last_error);
    }
    // Prune the view log on the same cutoff, so both tables age in lockstep.
    $views_table = $wpdb->prefix . 'er_map_views';
    $deleted_views = $wpdb->query($wpdb->prepare(
        "DELETE FROM $views_table WHERE viewed_at < %s",
        $threshold
    ));
    if ($deleted_views === false) {
        error_log('Visitor Tracking: cleanup of er_map_views failed - ' . $wpdb->last_error);
    }
    // Prune the file-based geo cache: entries older than 30 days are never read again.
    $geo_files = glob(WP_CONTENT_DIR . '/cache/lum-geo/*.json');
    if ($geo_files) {
        $geo_cut = time() - 30 * DAY_IN_SECONDS;
        foreach ($geo_files as $geo_file) {
            if (filemtime($geo_file) < $geo_cut) {
                @unlink($geo_file);
            }
        }
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

// Get geolocation from an (anonymised) IP
// Cached as one small JSON file per IP for 30 days (pruned by the daily
// cleanup). A file read costs no database query; the earlier extra transient
// layer did, and the weekly maintenance wiped it anyway.
function er_trk_get_geolocation($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    $cache_dir = WP_CONTENT_DIR . '/cache/lum-geo/';
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    $cache_file = $cache_dir . md5($ip) . '.json';
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 30 * DAY_IN_SECONDS) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            return $cached_data;
        }
    }
    // Add a lock to prevent duplicate requests
    $lock_key = 'lum_geo_' . md5($ip) . '_lock';
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
        file_put_contents($cache_file, json_encode($data));
        return $data;
    }
    return false;
}

// Browser name only; the full user agent never leaves the server.
function er_trk_browser($ua) {
    $ua = (string) $ua;
    if ('' === $ua) return 'Unknown';
    if (false !== strpos($ua, 'Edg')) return 'Edge';
    if (false !== strpos($ua, 'OPR')) return 'Opera';
    if (false !== strpos($ua, 'Firefox') || false !== strpos($ua, 'FxiOS')) return 'Firefox';
    if (false !== strpos($ua, 'Chrome') || false !== strpos($ua, 'CriOS')) return 'Chrome';
    if (false !== strpos($ua, 'Safari')) return 'Safari';
    return 'Other';
}
