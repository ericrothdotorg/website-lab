<?php 

defined('ABSPATH') || exit;

if (!is_admin()) return;

function initialize_custom_dashboard() {
    if (!is_admin() || !current_user_can('manage_options')) {
    return;
    }

    // 📇 Add emoji icons for core widgets
    add_action('wp_dashboard_setup', function() {
        $widgets = [
            'dashboard_right_now'  => '📇 At a Glance',
        ];
        foreach ($widgets as $widget_id => $title) {
            global $wp_meta_boxes;
            if (isset($wp_meta_boxes['dashboard']['normal']['core'][$widget_id])) {
                $wp_meta_boxes['dashboard']['normal']['core'][$widget_id]['title'] = $title;
            }
        }
    });

    // 📇 Add CPTs to "At a Glance"
    add_filter('dashboard_glance_items', function ($items) {
        $post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
        foreach ($post_types as $pt) {
            $count = wp_count_posts($pt->name)->publish;
            if ($count) {
                $items[] = sprintf(
                    '<a href="edit.php?post_type=%1$s">%2$s %3$s</a>',
                    esc_attr($pt->name),
                    number_format_i18n($count),
                    esc_html($pt->labels->name)
                );
            }
        }
        return $items;
    });

    // 🎨 Add Theme Snapshot and Buttons
    add_action('wp_dashboard_setup', function() {
        wp_add_dashboard_widget('custom_theme_snapshot', '🎨 Theme Snapshot', function() {
            $theme = wp_get_theme();
            $theme_dir = get_theme_root() . '/' . $theme->get_stylesheet();
            $theme_last_updated = filemtime($theme_dir . '/style.css');
            $customizer_url = admin_url('customize.php');
            $changelog_url = 'https://ericroth.org/wp-admin/admin.php?page=ct-dashboard#/changelog';
            printf('<p>Theme is <strong>%s</strong> (v%s). Last updated: <strong>%s</strong></p>',
                esc_html($theme->get('Name')),
                esc_html($theme->get('Version')),
                esc_html(date('F j, Y', $theme_last_updated))
            );
            echo '<div style="display: flex; gap: 10px;">';
            echo '<a class="button" href="' . esc_url($customizer_url) . '" target="_blank" rel="noopener noreferrer">Open Customizer</a>';
            echo '<a class="button" href="' . esc_url($changelog_url) . '" target="_blank" rel="noopener noreferrer">View Changelog</a>';
            echo '</div>';
        });
    });

    // 📊 Add Analytics Toolkit
    add_action('wp_dashboard_setup', function () {
        wp_add_dashboard_widget('custom_analytics_toolkit', '📊 Analytics Toolkit', function () {
            // Define URLs for external Tools
            $site_url = 'https://ericroth.org/';
            $pagespeed_url = 'https://pagespeed.web.dev/report?url=' . urlencode($site_url);
            $webpagetest_url = 'https://www.webpagetest.org/?url=' . urlencode($site_url);
            $wave_url = 'https://wave.webaim.org/report#/' . urlencode($site_url);
            // External Analytics Buttons
            echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            echo '<a href="https://analytics.google.com/analytics/web/#/p438219493/reports/overview" target="_blank" class="button">📈 GoogleAnalytics</a>';
            echo '<a href="https://clarity.microsoft.com/projects/view/eic7b2e9o1/dashboard" target="_blank" class="button">📉 MS Clarity</a>';
            echo '<a href="' . esc_url($pagespeed_url) . '" target="_blank" class="button">⚡ PageSpeed</a>';
            echo '<a href="' . esc_url($webpagetest_url) . '" target="_blank" class="button">🧪 WebPageTest</a>';
            echo '<a href="' . esc_url($wave_url) . '" target="_blank" class="button">♿ Accessibility Audit</a>';
            echo '</div>';
            // Gather Site Metrics
            $media_count = wp_count_attachments();
            $total_media = array_sum((array) $media_count);
            global $wpdb;
            $tables = $wpdb->get_col('SHOW TABLES');
            $db_table_count = count($tables);
            $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            if (filter_var($visitor_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $visitor_ip = gethostbyname(gethostname());
            }
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugin_count = count(get_plugins());
            // Metrics Display Section
            echo '<div style="margin-top:15px; display: flex; flex-wrap: wrap; gap: 20px;">';
            // Media Files and DB Tables
            echo '<div style="width: calc(50% - 10px);">';
            echo '<p>🖼️ Media Files: <strong>' . $total_media . '</strong></p>';
            echo '<p>🧵 DB Tables: <strong>' . $db_table_count . '</strong></p>';
            echo '</div>';
            // Visitor IP and Plugin Count
            echo '<div style="width: calc(50% - 10px);">';
            echo '<p>🧍 Your IP: <strong>' . esc_html($visitor_ip) . '</strong></p>';
            echo '<p>🔌 Active Plugins Installed: <strong>' . $plugin_count . '</strong></p>';
            echo '</div>';
            // Broken YT Links Checker
            echo '<div style="width: calc(50% - 10px);">';
            echo '<form method="post">';
            echo '<button type="submit" name="check_broken_yt" class="button" style="margin-bottom:10px;">🔍 Broken YT Links</button>';
            echo '</form>';
            // Run broken YT Links Checker with Button
            if (isset($_POST['check_broken_yt'])) {
                $broken_links = 0;
                $broken_posts = [];
                $args = [
                    'post_type' => ['post', 'page', 'my-interests', 'my-traits'],
                    'posts_per_page' => -1,
                ];
                $query = new WP_Query($args);
                foreach ($query->posts as $post) {
                    preg_match_all('/https:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $post->post_content, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $video_id) {
                            $url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$video_id}&format=json";
                            $response = wp_remote_get($url);
                            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                                $broken_links++;
                                $broken_posts[$post->ID][] = $video_id;
                            }
                        }
                    }
                }
                // Output broken YT Links Checker Results
                echo '<div id="broken-yt-results" style="margin-top: 10px;">';
                echo '<p>🔴 Broken YT Links: <strong style="color: red;">' . $broken_links . '</strong></p>';
                if (!empty($broken_posts)) {
                    echo '<details style="margin-top: 15px;"><summary style="cursor: pointer; margin-bottom: 15px;"><strong>Broken Links Locations</strong></summary><ul>';
                    foreach ($broken_posts as $post_id => $video_ids) {
                        $title = get_the_title($post_id);
                        $edit_link = get_edit_post_link($post_id);
                        echo '<li><a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($title) . '</a>: ';
                        echo implode(', ', array_map('esc_html', $video_ids)) . '</li>';
                    }
                    echo '</ul></details>';
                }
                echo '</div>';
            }
            echo '</div>';
            // Design Block Tracker Button
            echo '<div style="width: calc(50% - 10px);">';
            echo '<a href="https://ericroth.org/wp-admin/tools.php?page=design-block-tracker" class="button" style="margin-bottom: 10px;">🎨 Design Block Tracker</a>';
            echo '</div>';

            echo '</div>';
        });
    });

    // 🌀 Add Hostinger Stuff Buttons
    add_action('wp_dashboard_setup', function() {
        wp_add_dashboard_widget('custom_hostinger_stuff', '🌀 Hostinger Stuff', function() {
            echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            echo '<a href="https://auth.hostinger.com/login" target="_blank" class="button">🔐 Login</a>';
            echo '<a href="https://mail.hostinger.com/" target="_blank" class="button">📬 Webmail</a>';
            echo '<a href="https://ericroth.org/wp-admin/admin.php?page=hostinger-ai-assistant" target="_blank" class="button">🧠 AI</a>';
            echo '</div>';
        });
    });

    // 🗓️ Add Recent Site Activity
    add_action('wp_dashboard_setup', function () {
        wp_add_dashboard_widget('custom_activity_alerts', '🗓️ Recent Site Activity', function () {
            global $wpdb;
            $format_count = fn($num) => number_format_i18n($num, 0);
            // 🧍 Subscribers
            $subs_today = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}subscribers
                WHERE DATE(subscription_date) = CURDATE()
            ");
            $subs_total = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}subscribers
            ");
            // 📬 Contact Messages
            $contact_today = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}contact_messages
                WHERE DATE(submitted_at) = CURDATE()
            ");
            $contact_total = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}contact_messages
            ");
            // 🙋 Forum Users (Asgaros)
            $forum_users_today = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->users}
                WHERE DATE(user_registered) = CURDATE()
            ");
            $forum_users_total = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->users}
            ");
            // 💬 Forum Posts (Asgaros)
            $forum_today = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}forum_posts
                WHERE DATE(date) = CURDATE()
            ");
            $forum_total = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}forum_posts
            ");
            // 👍 Likes
            $likes_today = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}postmeta
                WHERE meta_key = 'like_timestamp' AND DATE(meta_value) = CURDATE()
            ");
            $likes_total = $wpdb->get_var("
                SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->prefix}postmeta
                WHERE meta_key = 'likes'
            ");
            // 👎 Dislikes
            $dislikes_today = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}postmeta
                WHERE meta_key = 'dislike_timestamp' AND DATE(meta_value) = CURDATE()
            ");
            $dislikes_total = $wpdb->get_var("
                SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->prefix}postmeta
                WHERE meta_key = 'dislikes'
            ");
            echo '<ul style="font-size: 14px; line-height: 1.5;">';
            echo '<li>🧍 Subscribers: <strong>' . intval($subs_today) . '</strong> today / <strong>' . $format_count($subs_total) . '</strong> total</li>';
            echo '<li>📬 Contact Messages: <strong>' . intval($contact_today) . '</strong> today / <strong>' . $format_count($contact_total) . '</strong> total</li>';
            echo '<li>🙋 Forum Users: <strong>' . intval($forum_users_today) . '</strong> today / <strong>' . $format_count($forum_users_total) . '</strong> total</li>';
            echo '<li>💬 Forum Posts: <strong>' . intval($forum_today) . '</strong> today / <strong>' . $format_count($forum_total) . '</strong> total</li>';
            echo '<li>👍 Likes: <strong>' . intval($likes_today) . '</strong> today / <strong>' . $format_count($likes_total) . '</strong> total</li>';
            echo '<li>👎 Dislikes: <strong>' . intval($dislikes_today) . '</strong> today / <strong>' . $format_count($dislikes_total) . '</strong> total</li>';
            echo '</ul>';
            // Today's 👍 Liked / 👎 Disliked Content (Aggregated)
            $engagement_summary = $wpdb->get_results("
                SELECT post_id,
                    SUM(CASE WHEN meta_key = 'like_timestamp' THEN 1 ELSE 0 END) AS likes,
                    SUM(CASE WHEN meta_key = 'dislike_timestamp' THEN 1 ELSE 0 END) AS dislikes
                FROM {$wpdb->prefix}postmeta
                WHERE meta_key IN ('like_timestamp', 'dislike_timestamp')
                AND DATE(meta_value) = CURDATE()
                GROUP BY post_id
                ORDER BY MAX(meta_value) DESC
            ");
            if ($engagement_summary) {
                echo '<h4 style="margin-top: 25px;">Today\'s Liked / Disliked Content</h4>';
                echo '<ul style="font-size: 14px; line-height: 1.5;">';
                foreach ($engagement_summary as $row) {
                    $post_id = intval($row->post_id);
                    $post = get_post($post_id);
                    if ($post) {
                        $type = get_post_type($post_id);
                        $title = esc_html(get_the_title($post_id));
                        $view_link = get_permalink($post_id);
                        $likes = intval($row->likes);
                        $dislikes = intval($row->dislikes);
                        $summary = [];
                        if ($likes > 0) $summary[] = "👍 <strong>{$likes}</strong>";
                        if ($dislikes > 0) $summary[] = "👎 <strong>{$dislikes}</strong>";
                        $summary_text = implode(' / ', $summary);
                        echo "<li>{$summary_text} → <a href='{$view_link}' target='_blank'><strong>{$title}</strong></a> <em>({$type})</em></li>";
                    }
                }
                echo '</ul>';
            }
        });
    });

    // 🧹 Add Optimize & Clean-Up Buttons
    add_action('wp_dashboard_setup', function () {
        wp_add_dashboard_widget(
            'custom_optimize_and_cleanup',
            '🧹 Optimize & Clean-Up',
            'custom_render_optimize_and_cleanup'
        );
    });
    function custom_render_optimize_and_cleanup() {
        if (isset($_POST['er_run_full_cleanup']) && current_user_can('manage_options')) {
            $result = custom_run_full_inno_db_cleanup();
            echo "<div class='notice notice-success is-dismissible'><p>$result</p></div>";
        }
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">';
        echo '<form method="post" style="margin: 0;">';
        echo '<button type="submit" name="er_run_full_cleanup" class="button">🛠️ Run InnoDB Cleanup</button>';
        echo '</form>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=litespeed-db_optm')) . '" class="button" target="_blank">🛢️ LiteSpeed Database</a>';
        echo '</div>';
        global $wpdb;
        $postmeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
        $status_color = ($postmeta_count > 50000) ? 'red' : (($postmeta_count > 10000) ? 'orange' : 'green');
        $status_note = ($postmeta_count > 50000) ? 'Consider running a cleanup.' : (($postmeta_count > 10000) ? 'Moderate bloat detected.' : 'Healthy state.');
        echo '<div style="margin-top: 10px;">';
        echo '<p style="font-size: 14px; margin: 5px 0;">Post Meta Rows: <strong>' . number_format_i18n($postmeta_count) . '</strong> ';
        echo '<span style="color:' . esc_attr($status_color) . ';">– ' . esc_html($status_note) . '</span></p>';
        echo '</div>';
    }
    function custom_run_full_inno_db_cleanup() {
        global $wpdb;
        $deleted_total = 0;
        $deleted_total += $wpdb->query("
            DELETE pm FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.ID IS NULL
        ");
        $deleted_total += $wpdb->query("
            DELETE tr FROM {$wpdb->term_relationships} tr
            LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
            WHERE p.ID IS NULL
        ");
        $safe_postmeta_keys = [
            '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date',
            '_last_viewed_timestamp', 'litespeed-optimize-set', 'litespeed-optimize-size'
        ];
        foreach ($safe_postmeta_keys as $key) {
            $deleted_total += $wpdb->query(
                $wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $key)
            );
        }
        $deleted_total += $wpdb->query("
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key = '_menu_item_target' AND (meta_value IS NULL OR meta_value = '')
        ");
        $deleted_total += $wpdb->query("
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key LIKE '_oembed_%' OR meta_key LIKE '_oembed_time_%'
        ");
        $deleted_total += $wpdb->query("
            DELETE um FROM {$wpdb->usermeta} um
            LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id
            WHERE u.ID IS NULL
        ");
        $safe_usermeta_keys = ['_session_tokens', '_last_activity', '_woocommerce_persistent_cart'];
        foreach ($safe_usermeta_keys as $key) {
            $deleted_total += $wpdb->query(
                $wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $key)
            );
        }
        $deleted_total += $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'
        ");
        $deleted_total += $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()
        ");
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}actionscheduler_actions'")) {
            $deleted_total += $wpdb->query("
                DELETE FROM {$wpdb->prefix}actionscheduler_actions
                WHERE status = 'complete' AND scheduled_date_gmt < NOW() - INTERVAL 30 DAY
            ");
        }
        $tables = ['postmeta', 'usermeta', 'options', 'term_relationships'];
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}$table");
        }
        return "✅ Full cleanup complete. Total rows deleted: $deleted_total. Tables optimized.";
    }

    // 📰 Add RSS Feed Reader
    add_action('wp_dashboard_setup', function () {
        wp_add_dashboard_widget('custom_rss_widget', '📡 RSS Feed: Blog & Interests', 'custom_render_rss_widget');
    });
    function custom_render_rss_widget() {
        $feeds = [
            ['label' => 'My Blog', 'url' => 'https://ericroth.org/feed/'],
            ['label' => 'My Interests', 'url' => 'https://ericroth.org/feed/?post_type=my-interests']
        ];
        // Grab number of items from settings -> reading
        $max_items = (int) get_option('posts_per_rss');
        if (!$max_items || $max_items < 1) {
            $max_items = 10; // Fallback in case it's unset or invalid
        }
        echo '<div class="rss-widget-wrapper external" style="font-size: 14px;">';
        echo '<ul class="rss-tab-nav" style="display: flex; gap: 10px; list-style: none; margin: 0 0 10px;">';
        foreach ($feeds as $i => $feed) {
            echo '<li><button class="rss-tab-btn" data-tab="external-tab-' . $i . '" style="padding: 6px 12px;">' . esc_html($feed['label']) . '</button></li>';
        }
        echo '</ul>';
        foreach ($feeds as $i => $feed) {
            echo '<div id="external-tab-' . $i . '" class="rss-tab-content" style="display: none;">';
            if (!function_exists('fetch_feed')) {
                require_once ABSPATH . WPINC . '/feed.php';
            }
            delete_transient('feed_' . md5($feed['url']));
            $rss = fetch_feed($feed['url']);
            if (!is_wp_error($rss)) {
                $items = $rss->get_items(0, $max_items);
                foreach ($items as $index => $item) {
                    $title = esc_html($item->get_title());
                    $link  = esc_url($item->get_permalink());
                    $desc  = wp_strip_all_tags($item->get_description());
                    $excerpt = $desc ? wp_trim_words($desc, 30) : 'No description available';
                    $date  = $item->get_date('U') ? date_i18n('F j, Y', $item->get_date('U')) : 'Unknown date';
                    echo '<div class="rss-item" data-index="' . $index . '" style="display: none; margin-bottom: 15px;">';
                    echo '<div><a href="' . $link . '" target="_blank" style="font-weight: bold;">' . $title . '</a> – ';
                    echo '<span style="color: #666; font-size: 12px;">🗓️ Published: <strong>' . esc_html($date) . '</strong></span></div>';
                    echo '<p style="margin: 5px 0;">' . esc_html($excerpt) . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<p>🚫 Error fetching feed: ' . esc_html($rss->get_error_message()) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    // 🗂️ Pages & Traits Snapshot
    add_action('wp_dashboard_setup', function () {
        wp_add_dashboard_widget('custom_snapshot_widget', '🗂️ Pages & Traits Snapshot', 'custom_render_snapshot_widget');
    });
    function custom_render_snapshot_widget() {
        $tabs = [
            ['label' => 'My Pages', 'type' => 'page', 'exclude' => [179, 14581]],
            ['label' => 'My Traits', 'type' => 'my-traits', 'exclude' => []]
        ];
        echo '<div class="rss-widget-wrapper local" style="font-size:14px;">';
        echo '<ul class="rss-tab-nav" style="display:flex; gap:10px; list-style:none; margin:0 0 10px;">';
        foreach ($tabs as $i => $tab) {
            echo '<li><button class="rss-tab-btn" data-tab="local-tab-' . $i . '" style="padding:6px 12px;">' . esc_html($tab['label']) . '</button></li>';
        }
        echo '</ul>';
        foreach ($tabs as $i => $tab) {
            echo '<div id="local-tab-' . $i . '" class="rss-tab-content" style="display:none;">';
            $query = new WP_Query([
                'post_type' => $tab['type'],
                'post_status' => 'publish',
                'posts_per_page' => 250,
                'orderby' => 'modified',
                'order' => 'DESC',
                'post__not_in' => $tab['exclude']
            ]);
            if ($query->have_posts()) {
                foreach ($query->posts as $index => $post) {
                    setup_postdata($post);
                    $title = esc_html(get_the_title($post));
                    $link = esc_url(get_permalink($post));
                    $desc = wp_strip_all_tags(do_shortcode($post->post_content));
                    $excerpt = get_the_excerpt($post) ?: wp_trim_words($desc, 25);
                    $date = get_the_modified_date('U', $post) ? date_i18n('F j, Y', get_the_modified_date('U', $post)) : 'Unknown date';
                    echo '<div class="rss-item" data-index="' . $index . '" style="display:none; margin-bottom:15px;">';
                    echo '<div><a href="' . $link . '" target="_blank" style="font-weight:bold;">' . $title . '</a> – <span style="color:#666; font-size:12px;">✏️ Updated: <strong>' . esc_html($date) . '</strong></span></div>';
                    echo '<p style="margin:5px 0;">' . esc_html($excerpt) . '</p>';
                    echo '</div>';
                }
                wp_reset_postdata();
            } else {
                echo '<p>No published items found for ' . esc_html($tab['label']) . '.</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    add_action('admin_footer', function () {
        echo '<style>
            [id^="custom_"] .postbox-header .hndle,
                #dashboard_right_now .postbox-header .hndle {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                gap: 5px;
            }
    </style>';
        echo <<<HTML
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.rss-widget-wrapper').forEach(widget => {
            const tabNav = widget.querySelector('.rss-tab-nav');
            const tabButtons = widget.querySelectorAll('.rss-tab-btn');
            const tabContents = widget.querySelectorAll('.rss-tab-content');
            const navGroup = document.createElement('div');
            navGroup.style.marginLeft = 'auto';
            navGroup.style.display = 'flex';
            navGroup.style.gap = '6px';
            const prevBtn = document.createElement('button');
            prevBtn.innerHTML = '⬅️';
            prevBtn.title = 'Previous';
            prevBtn.style.padding = '6px';
            prevBtn.style.cursor = 'pointer';
            const nextBtn = document.createElement('button');
            nextBtn.innerHTML = '➡️';
            nextBtn.title = 'Next';
            nextBtn.style.padding = '6px';
            nextBtn.style.cursor = 'pointer';
            navGroup.appendChild(prevBtn);
            navGroup.appendChild(nextBtn);
            tabNav.appendChild(navGroup);
            tabNav.style.display = 'flex';
            tabNav.style.flexWrap = 'wrap';
            tabNav.style.alignItems = 'center';
            tabButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabContents.forEach(tab => tab.style.display = 'none');
                    const target = widget.querySelector('#' + btn.dataset.tab);
                    if (target) {
                        target.style.display = 'block';
                        resetPagination(target);
                    }
                });
            });
            if (tabButtons.length) tabButtons[0].click();
            widget.querySelectorAll('.rss-tab-content').forEach(tab => {
                const items = tab.querySelectorAll('.rss-item');
                let currentStart = 0;
                const batchSize = 5;
                function renderBatch(start) {
                    items.forEach((item, i) => {
                        item.style.display = (i >= start && i < start + batchSize) ? 'block' : 'none';
                    });
                    currentStart = start;
                    prevBtn.style.display = currentStart === 0 ? 'none' : 'inline-block';
                    nextBtn.style.display = currentStart + batchSize >= items.length ? 'none' : 'inline-block';
                }
                renderBatch(0);
                prevBtn.addEventListener('click', () => {
                    if (tab.style.display === 'block' && currentStart - batchSize >= 0) {
                        renderBatch(currentStart - batchSize);
                    }
                });
                nextBtn.addEventListener('click', () => {
                    if (tab.style.display === 'block' && currentStart + batchSize < items.length) {
                        renderBatch(currentStart + batchSize);
                    }
                });
                function resetPagination(container) {
                    if (container === tab) renderBatch(0);
                }
            });
        });
    });
    </script>
HTML;
    });

}

add_action('admin_init', 'initialize_custom_dashboard');
