<?php
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
            
            // Fire async tracking request
            setTimeout(function() {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    // Optional: handle response if needed
                };
                xhr.send('action=lum_background_track&nonce=<?php echo $nonce; ?>');
            }, 1000); // Small delay to ensure page loads first
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
    
    // Now do the actual tracking (moved from original lum_track_visitor)
    global $wpdb;
    $table_name = $wpdb->prefix . 'live_visitors';
    $ip_address = lum_get_client_ip();
    $ip_address = lum_anonymize_ip($ip_address);
    $request_uri = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) : '/';
    
    // Clean the URL
    $page_url = lum_clean_page_url($request_uri);
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    
    // Skip known bots (double-check server-side)
    if (lum_is_bot($user_agent)) {
        wp_die('Bot detected');
    }
    
    $geo_data = lum_get_geolocation($ip_address);
    
    if ($geo_data) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE ip_address = %s ORDER BY last_seen DESC LIMIT 1",
            $ip_address
        ));
        
        $current_time = current_time('mysql');
        
        if ($existing) {
            $wpdb->update(
                $table_name,
                array(
                    'last_seen' => $current_time,
                    'page_url' => $page_url,
                    'user_agent' => $user_agent
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'visitor_id' => uniqid('visitor_', true),
                    'ip_address' => $ip_address,
                    'latitude' => $geo_data['lat'],
                    'longitude' => $geo_data['lon'],
                    'city' => $geo_data['city'],
                    'country' => $geo_data['country'],
                    'country_code' => $geo_data['countryCode'],
                    'page_url' => $page_url,
                    'user_agent' => $user_agent,
                    'last_seen' => $current_time,
                    'visit_time' => $current_time
                ),
                array('%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
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
    $table_name = $wpdb->prefix . 'live_visitors';
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
}
add_action('lum_daily_cleanup', 'lum_cleanup_old_visitors');

// Schedule daily cleanup
function lum_schedule_cleanup() {
    if (!wp_next_scheduled('lum_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'lum_daily_cleanup');
    }
    // Add indexes once if not already present (speeds up IP lookups and date range queries)
    if (!get_option('lum_indexes_created')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_visitors';
        $wpdb->query("ALTER TABLE $table_name ADD INDEX IF NOT EXISTS idx_ip (ip_address), ADD INDEX IF NOT EXISTS idx_last_seen (last_seen)");
        update_option('lum_indexes_created', true);
    }
}
add_action('init', 'lum_schedule_cleanup');

// ======================================
// IP & GEOLOCATION HANDLING
// ======================================

// Get client IP address
function lum_get_client_ip() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
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
        'sslverify' => false
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
    $table_name = $wpdb->prefix . 'live_visitors';
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

// Shortcode
function lum_map_shortcode($atts) {
    $atts = shortcode_atts(array(
        'height' => '600px',
        'zoom' => '2'
    ), $atts);
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), null, true);
    wp_enqueue_script('terminator-js', 'https://unpkg.com/@joergdietrich/leaflet.terminator@1.0.0/L.Terminator.js', array('leaflet-js'), null, true);
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
            <p style="color: #999;">Loading...</p>
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
    .popup-title {font-size: 16px; font-weight: 600; margin-bottom: 10px; color: #333;}
    .popup-info {font-size: 13px; line-height: 1.8; color: #666;}
    .popup-info strong {color: #333; display: inline-block; min-width: 80px;}
    .popup-badge {display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 5px;}
    .badge-live {background: #d4edda; color: #155724;}
    .badge-past {background: #d1ecf1; color: #0c5460;}
    .leaflet-control-attribution a {pointer-events: auto !important;}
    </style>

    <script>
    (function() {
        let map, liveMarkers = [], pastMarkers = [], terminator;
        let isFirstLoad = true;
        let userHasInteracted = false;

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
            setInterval(loadMapData, 15000); // Refresh every 15 seconds
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
                    console.log('Map data loaded:', data); // Added console logging
                    if (data.success) {
                        updateMarkers(data.data, isFirstLoad);
                        updateStats(data.data.counts);
                        updatePagesList(data.data.live);
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
                    🌍 ${user.city || 'Unknown City'}${badge}
                </div>
                <div class="popup-info">
                    <div><strong>Country:</strong> ${user.country} ${getFlagEmoji(user.country_code)}</div>
                    <div><strong>Last Seen:</strong> ${timeAgo}</div>
                    <div><strong>Page:</strong> ${truncateUrl(displayUrl)}</div>
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
                pagesListEl.innerHTML = '<p style="color: #999;">No live visitors</p>';
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
            let html = '<table style="width: 100%; border-collapse: collapse;">';
            html += '<thead><tr style="border-bottom: 2px solid #ddd;"><th style="text-align: left; padding: 8px;">Page</th><th style="text-align: left; padding: 8px;">Visitors</th><th style="text-align: left; padding: 8px;">Location</th></tr></thead><tbody>';
            Array.from(pageMap.entries()).forEach(([url, data]) => {
                const escapeHtml = (str) => str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                const safeUrl = escapeHtml(url);
                const safeCity = escapeHtml(data.city || '');
                const safeCountry = escapeHtml(data.country || '');
                html += `<tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 8px;"><a href="${safeUrl}" target="_blank">${safeUrl}</a></td>
                    <td style="padding: 8px;">${data.count}</td>
                    <td style="padding: 8px;">${safeCity}, ${safeCountry}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            pagesListEl.innerHTML = html;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initMap);
        } else {
            initMap();
        }
    })();
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('live_user_map', 'lum_map_shortcode');
