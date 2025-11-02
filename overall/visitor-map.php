<?php

// Track current visitor with filtering
function lum_track_visitor() {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }
    
    // Don't track if it's a REST API request
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'live_visitors';
    
    $ip_address = lum_get_client_ip();
    $ip_address = lum_anonymize_ip($ip_address);
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    
    // Filter out unwanted requests
    if (lum_should_skip_tracking($request_uri)) {
        return;
    }
    
    // Clean the URL - remove query parameters for cleaner display
    $page_url = lum_clean_page_url($request_uri);
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    
    // Skip known bots
    if (lum_is_bot($user_agent)) {
        return;
    }
    
    $geo_data = lum_get_geolocation($ip_address);
    
    if ($geo_data) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE ip_address = %s ORDER BY last_seen DESC LIMIT 1",
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
}
add_action('wp', 'lum_track_visitor');

// Check if request should be skipped
function lum_should_skip_tracking($uri) {
    // File extensions to ignore (assets)
    $skip_extensions = array(
        'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'otf', // fonts
        'mp4', 'webm', 'ogg', 'mp3', 'wav', // media
        'pdf', 'zip', 'tar', 'gz', // documents/archives
        'xml', 'json', 'txt' // data files
    );
    
    // Check file extension
    $path = parse_url($uri, PHP_URL_PATH);
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    
    if (in_array(strtolower($extension), $skip_extensions)) {
        return true;
    }
    
    // Skip common WordPress technical URLs
    $skip_patterns = array(
        '/wp-content/',
        '/wp-includes/',
        '/wp-json/',
        'robots.txt',
        'sitemap',
        'feed',
        '/litespeed/',
        '/cache/',
        '.map' // source maps
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

// Cleanup old visitor records daily
function lum_cleanup_old_visitors() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'live_visitors';
    $threshold = date('Y-m-d H:i:s', strtotime('-90 days'));
    
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE last_seen < %s",
        $threshold
    ));
}
add_action('lum_daily_cleanup', 'lum_cleanup_old_visitors');

// Schedule daily cleanup
function lum_schedule_cleanup() {
    if (!wp_next_scheduled('lum_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'lum_daily_cleanup');
    }
}
add_action('wp', 'lum_schedule_cleanup');

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

// Get geolocation from IP
function lum_get_geolocation($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    
    $transient_key = 'lum_geo_' . md5($ip);
    $cached = get_transient($transient_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,lat,lon");
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['status']) && $data['status'] === 'success') {
        set_transient($transient_key, $data, 30 * DAY_IN_SECONDS);
        return $data;
    }
    
    return false;
}

// AJAX endpoint to get map data
function lum_get_map_data() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'live_visitors';
    
    $current_time = current_time('timestamp');
    $live_threshold = date('Y-m-d H:i:s', $current_time - (5 * 60));
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
        LIMIT 1000",
        $live_threshold,
        $history_threshold
    ));
    
    wp_send_json_success(array(
        'live' => $live_users,
        'past' => $past_users,
        'counts' => array(
            'live' => count($live_users),
            'past' => count($past_users)
        )
    ));
}
add_action('wp_ajax_lum_get_map_data', 'lum_get_map_data');
add_action('wp_ajax_nopriv_lum_get_map_data', 'lum_get_map_data');

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
        <div style="display: flex; gap: 15px; align-items: center; font-size: 1em;">
            <span>Live Visitors: <strong><span id="live-count">0</span></strong></span>
            <span>Last Updated: <span id="last-update">-</span></span>
        </div>
        <div style="display: flex; gap: 15px; align-items: center; font-size: 1em;">
            <span><strong>Legend:</strong></span>
            <span style="display: inline-flex; align-items: center; gap: 5px;">
                <span style="display: inline-block; width: 12px; height: 12px; background: #28a745; border-radius: 50%; border: 2px solid rgba(40, 167, 69, 0.3);"></span>
                Live Visitors
            </span>
            <span style="display: inline-flex; align-items: center; gap: 5px;">
                <span style="display: inline-block; width: 12px; height: 12px; background: #007bff; border-radius: 50%; border: 2px solid rgba(0, 123, 255, 0.5); opacity: 0.8;"></span>
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
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
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
            setInterval(loadMapData, 15000);
        }
        
        function loadMapData() {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=lum_get_map_data')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateMarkers(data.data, isFirstLoad);
                        updateStats(data.data.counts);
                        updatePagesList(data.data.live);
                        isFirstLoad = false;
                    }
                })
                .catch(error => console.error('Error loading map data:', error));
        }
        
        function updateMarkers(data, skipZoom) {
            liveMarkers.forEach(marker => map.removeLayer(marker));
            pastMarkers.forEach(marker => map.removeLayer(marker));
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
            if (ua.includes('Chrome')) return 'Chrome';
            if (ua.includes('Firefox')) return 'Firefox';
            if (ua.includes('Safari')) return 'Safari';
            if (ua.includes('Edge')) return 'Edge';
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
                html += `<tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 8px;"><a href="${url}" target="_blank">${url}</a></td>
                    <td style="padding: 8px;">${data.count}</td>
                    <td style="padding: 8px;">${data.city}, ${data.country}</td>
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
