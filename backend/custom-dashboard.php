<?php
defined('ABSPATH') || exit;

function initialize_custom_dashboard() {
    if (!is_admin() || !current_user_can('manage_options')) {
    return;
    }

	// ======================================
    // 📇 ADD EMOJI ICONS FOR CORE WIDGETS
    // ======================================

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

    // ======================================
	// 📇 ADD CPTS TO "AT A GLANCE"
	// ======================================

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

    // ======================================
	// 🎨 ADD THEME SNAPSHOT AND BUTTONS
	// ======================================

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

    // ======================================
	// 🌀 ADD HOSTINGER STUFF BUTTONS
	// ======================================

    add_action('wp_dashboard_setup', function() {
        wp_add_dashboard_widget('custom_hostinger_stuff', '🌀 Hostinger Stuff', function() {
            echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
            echo '<a href="https://auth.hostinger.com/login" target="_blank" class="button">🔐 Login</a>';
            echo '<a href="https://mail.hostinger.com/" target="_blank" class="button">📬 Webmail</a>';
            echo '<a href="https://ericroth.org/wp-admin/admin.php?page=hostinger-ai-assistant" target="_blank" class="button">🧠 AI</a>';
            echo '</div>';
        });
    });


    // ======================================
	// 🗓️ ADD RECENT SITE ACTIVITY
	// ======================================

    add_action('wp_dashboard_setup', function () {
        wp_add_dashboard_widget('custom_activity_alerts', '🗓️ Recent Site Activity', function () {
            global $wpdb;
            $format_count = fn($num) => number_format_i18n($num, 0);
            // 📬 Contact Messages
            $contact_today = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}contact_messages
                WHERE DATE(submitted_at) = CURDATE()
            ");
            $contact_total = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}contact_messages
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
			// 👁️ Views
            $views_today = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}postmeta
                WHERE meta_key = 'view_timestamp' AND DATE(meta_value) = CURDATE()
            ");
            $views_total = $wpdb->get_var("
                SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->prefix}postmeta
                WHERE meta_key = '_er_post_views'
            ");
            echo '<ul style="font-size: 14px; line-height: 1.5;">';
			echo '<li>📬 Contact Messages: <strong style="color: red;">' . $format_count($contact_today) . '</strong> today / <strong>' . $format_count($contact_total) . '</strong> total</li>';
			echo '<li>👍 Likes: <strong style="color: red;">' . $format_count($likes_today) . '</strong> today / <strong>' . $format_count($likes_total) . '</strong> total</li>';
			echo '<li>👎 Dislikes: <strong style="color: red;">' . $format_count($dislikes_today) . '</strong> today / <strong>' . $format_count($dislikes_total) . '</strong> total</li>';
			echo '<li>👁️ Views: <strong style="color: red;">' . $format_count($views_today) . '</strong> today / <strong>' . $format_count($views_total) . '</strong> total</li>';
            echo '</ul>';
        });
    });

    // ======================================
	// 📊 ADD ANALYTICS TOOLKIT
	// ======================================

	add_action('wp_dashboard_setup', function () {
		wp_add_dashboard_widget('custom_analytics_toolkit', '📊 Analytics Toolkit', function () {

			// Define URLs for external Tools
			$site_url = 'https://ericroth.org/';
			$pagespeed_url = 'https://pagespeed.web.dev/report?url=' . urlencode($site_url);
			$webpagetest_url = 'https://www.webpagetest.org/?url=' . urlencode($site_url);
			$wave_url = 'https://wave.webaim.org/report#/' . urlencode($site_url);
			$clarity_url = 'https://clarity.microsoft.com/projects/view/eic7b2e9o1/dashboard';
			$w3_url = 'https://www.w3.org/developers/tools/';

			// START External Analytics Buttons Section
			echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
				echo '<a href="' . esc_url($pagespeed_url) . '" target="_blank" class="button">🚀 PageSpeed</a>';
				echo '<a href="' . esc_url($webpagetest_url) . '" target="_blank" class="button">🔥 WebPageTest</a>';
				echo '<a href="' . esc_url($wave_url) . '" target="_blank" class="button">♿ Accessibility</a>';
			echo '</div>';
			// END External Analytics Buttons Section

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

			// START Site Metrics Section
			echo '<div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">';
				// START Column 1: Media Files and DB Tables
				echo '<div style="width: calc(50% - 5px);">';
					echo '<p>🖼️ Media Files: <strong>' . $total_media . '</strong></p>';
					echo '<p style="margin-top: -5px;">🧵 InnoDB Tables: <strong>' . $db_table_count . '</strong></p>';
				echo '</div>'; // END Column 1
				// START Column 2: Visitor IP and Plugin Count
				echo '<div style="width: calc(50% - 5px);">';
					echo '<p>🧍 Your IP: <strong>' . esc_html($visitor_ip) . '</strong></p>';
					echo '<p style="margin-top: -5px;">🔌 Active Plugins Installed: <strong>' . $plugin_count . '</strong></p>';
				echo '</div>'; // END Column 2
			echo '</div>';
			// END Site Metrics Section

			// START Tools & Actions Section
			echo '<div style="margin-top: 15px;">';
				// START Design Blocks and Site Analytics Row
				echo '<div style="margin-bottom: 10px; display: flex; gap: 10px; flex-wrap: wrap;">';
					echo '<a href="https://ericroth.org/wp-admin/tools.php?page=design-block-tracker" class="button">🎨 Design Blocks</a>';
					echo '<a href="' . esc_url($clarity_url) . '" target="_blank" class="button">📈 MS Clarity</a>';
				echo '</div>'; // END Design Blocks and Site Analytics Row
				// START Action Buttons Row
				echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
					echo '<form method="post" style="margin: 0;">';
						echo '<button type="submit" name="check_broken_yt" class="button">🔍 Broken YT Links</button>';
					echo '</form>';
					echo '<a href="' . esc_url($w3_url) . '" target="_blank" class="button">🛠️ W3.org Dev Tools</a>';
				echo '</div>'; // END Action Buttons Row
			echo '</div>';
			// END Tools & Actions Section

			// Always get latest cached Results
			$cached_results = get_option('custom_broken_yt_results', []);
			$last_check = get_option('custom_last_yt_check', 0);
			$check_type = get_option('custom_yt_check_type', '');
			// Run manual Check if Button pressed
			if (isset($_POST['check_broken_yt'])) {
				$result = custom_check_broken_yt_links();
				update_option('custom_broken_yt_results', $result);
				update_option('custom_last_yt_check', time());
				delete_option('custom_yt_check_type');
				$cached_results = $result;
				$last_check = time();
			}

			// START Broken YT Links Results Section
			echo '<div style="margin-top: 30px;">';
				$broken_count = !empty($cached_results['broken_count']) ? $cached_results['broken_count'] : 0;
				echo '<p>🔴 Broken YT Links: <strong style="color: red;">' . $broken_count . '</strong></p>';
				// Show last Check Timestamp if available
				if ($last_check) {
					$tag = $check_type ? ' <strong>(' . esc_html($check_type) . ' check)</strong>' : '';
					echo '<p><em>Last checked: ' . 
						esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_check)) . 
						$tag . '</em></p>';
				} else {
					echo '<p><em>Last checked: Never</em></p>';
				}
				// Show Broken YT Links Details
				if (!empty($cached_results['broken_posts'])) {
					echo '<details style="margin-top: 15px;">';
						echo '<summary style="cursor: pointer; margin-bottom: 15px;"><strong>Broken Links Locations</strong></summary>';
						echo '<ul>';
						foreach ($cached_results['broken_posts'] as $post_id => $video_ids) {
							$title = get_the_title($post_id);
							$edit_link = get_edit_post_link($post_id);
							echo '<li><a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($title) . '</a>: ';
							echo implode(', ', array_map('esc_html', $video_ids)) . '</li>';
						}
						echo '</ul>';
					echo '</details>';
				}
			echo '</div>';
			// END Broken YT Links Results Section
		});
	});

	function custom_check_broken_yt_links() {
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
				$excluded_video_ids = ['-cW']; // Add Video IDs to exclude from Check
				foreach ($matches[1] as $video_id) {
					if (in_array($video_id, $excluded_video_ids)) {
						continue;
					}
					$url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$video_id}&format=json";
					$response = wp_remote_get($url);
					if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
						$broken_links++;
						$broken_posts[$post->ID][] = $video_id;
					}
				}
			}
		}
		return [
			'broken_count' => $broken_links,
			'broken_posts' => $broken_posts
		];
	}

    // ======================================
	// 🧹 ADD OPTIMIZE & CLEAN-UP BUTTONS
	// ======================================

	add_action('wp_dashboard_setup', function () {
		wp_add_dashboard_widget(
			'custom_optimize_and_cleanup',
			'🧹 Optimize & Clean-Up',
			'custom_render_innodb_cleanup'
		);
	});

	function custom_render_innodb_cleanup() {
		// Render InnoDB cleanup dashboard widget - Display stats, buttons and handle form submission
		if (isset($_POST['er_run_full_cleanup'])) {
			if (!current_user_can('manage_options')) {
				wp_die('Unauthorized access');
			}
			check_admin_referer('custom_cleanup_action', 'custom_cleanup_nonce');
			$result = custom_run_innodb_cleanup();
			update_option('custom_last_cleanup', time());
			update_option('custom_last_cleanup_result', $result['message']);
			update_option('custom_last_cleanup_success', $result['success']);
		}
		echo '<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">';
		echo '<form method="post" style="margin: 0;">';
		wp_nonce_field('custom_cleanup_action', 'custom_cleanup_nonce');
		echo '<button type="submit" name="er_run_full_cleanup" class="button">🧵 InnoDB Cleanup</button>';
		echo '</form>';
		// Add additional buttons (act just as links)
		echo '<a href="' . esc_url(admin_url('admin.php?page=litespeed-db_optm')) . '" class="button" target="_blank">🛢️ LiteSpeed DB</a>';
		$nonce = wp_create_nonce('purge');
		echo '<a href="' . esc_url(admin_url('index.php?LSCWP_CTRL=purge&LSCWP_NONCE=' . $nonce . '&litespeed_type=purge_all')) . '" class="button">⚡ Purge All</a>';
		echo '</div>';
		global $wpdb;
		$postmeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
		$commentmeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta}");
		$termmeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->termmeta}");
		$usermeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta}");
		$total_meta_count = $postmeta_count + $commentmeta_count + $termmeta_count + $usermeta_count;
		$get_status = function($count) {
			if ($count > 50000) return ['red', 'Consider running a cleanup.'];
			if ($count > 10000) return ['orange', 'Moderate bloat detected.'];
			return ['green', 'Healthy state.'];
		};
		echo '<div style="margin-top: 15px;">';
		$pm_status = $get_status($postmeta_count);
		echo '<p style="margin: 5px 0;">Content Meta Rows: <strong>' . number_format_i18n($postmeta_count) . '</strong> ';
		echo '<span style="color:' . esc_attr($pm_status[0]) . ';">– ' . esc_html($pm_status[1]) . '</span></p>';
		$cm_status = $get_status($commentmeta_count);
		echo '<p style="margin: 5px 0;">Comment Meta Rows: <strong>' . number_format_i18n($commentmeta_count) . '</strong> ';
		echo '<span style="color:' . esc_attr($cm_status[0]) . ';">– ' . esc_html($cm_status[1]) . '</span></p>';
		$tm_status = $get_status($termmeta_count);
		echo '<p style="margin: 5px 0;">Term Meta Rows: <strong>' . number_format_i18n($termmeta_count) . '</strong> ';
		echo '<span style="color:' . esc_attr($tm_status[0]) . ';">– ' . esc_html($tm_status[1]) . '</span></p>';
		$um_status = $get_status($usermeta_count);
		echo '<p style="margin: 5px 0;">User Meta Rows: <strong>' . number_format_i18n($usermeta_count) . '</strong> ';
		echo '<span style="color:' . esc_attr($um_status[0]) . ';">– ' . esc_html($um_status[1]) . '</span></p>';
		echo '<p style="margin: 5px 0;">TOTAL Meta Rows: <strong>' . number_format_i18n($total_meta_count) . '</strong></p>';
		$last_cleanup = get_option('custom_last_cleanup');
		$last_result = get_option('custom_last_cleanup_result');
		$last_success = get_option('custom_last_cleanup_success', true);
		if ($last_cleanup) {
			if ($last_result) {
				$result_color = $last_success ? 'green' : 'red';
				echo '<p style="margin: 10px 0; color: ' . esc_attr($result_color) . ';"><strong>' . esc_html($last_result) . '</strong></p>';
			}
			echo '<p style="margin: 5px 0;"><em>Last cleanup: ' . 
				esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_cleanup)) . '</em></p>';
		}
		echo '</div>';
	}

	function custom_run_innodb_cleanup() {
		// Execute InnoDB cleanup operations - Delete orphaned data and optimize tables
		global $wpdb;
		$deleted_total = 0;
		$errors = [];
		// Helper function to safely execute and count deletions
		$safe_delete = function($query, $operation_name) use ($wpdb, &$deleted_total, &$errors) {
			$result = $wpdb->query($query);
			if ($result === false) {
				$errors[] = $operation_name . ' failed: ' . $wpdb->last_error;
				return 0;
			}
			return (int)$result;
		};
		// Delete orphaned postmeta
		$deleted_total += $safe_delete("
			DELETE pm FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.ID IS NULL
		", 'Orphaned postmeta cleanup');
		// Delete orphaned term relationships
		$deleted_total += $safe_delete("
			DELETE tr FROM {$wpdb->term_relationships} tr
			LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
			WHERE p.ID IS NULL
		", 'Orphaned term relationships cleanup');
		// Remove specific safe postmeta keys
		$safe_postmeta_keys = [
			'_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date',
			'_last_viewed_timestamp', 'litespeed-optimize-set', 'litespeed-optimize-size'
		];
		foreach ($safe_postmeta_keys as $key) {
			$deleted_total += $safe_delete(
				$wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $key),
				"Postmeta cleanup ({$key})"
			);
		}
		// Delete empty menu item targets
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->postmeta}
			WHERE meta_key = '_menu_item_target' AND (meta_value IS NULL OR meta_value = '')
		", 'Empty menu item targets cleanup');
		// Delete oEmbed cache
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->postmeta}
			WHERE meta_key LIKE '_oembed_%' OR meta_key LIKE '_oembed_time_%'
		", 'oEmbed cache cleanup');
		// Delete orphaned usermeta
		$deleted_total += $safe_delete("
			DELETE um FROM {$wpdb->usermeta} um
			LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id
			WHERE u.ID IS NULL
		", 'Orphaned usermeta cleanup');
		// Remove specific safe usermeta keys
		$safe_usermeta_keys = ['_session_tokens', '_last_activity', '_woocommerce_persistent_cart'];
		foreach ($safe_usermeta_keys as $key) {
			$deleted_total += $safe_delete(
				$wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $key),
				"Usermeta cleanup ({$key})"
			);
		}
		// Delete transient options
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'
		", 'Transient cleanup');
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()
		", 'Expired transient timeout cleanup');
		// Delete old completed ActionScheduler actions if table exists
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}actionscheduler_actions'") === "{$wpdb->prefix}actionscheduler_actions") {
			$deleted_total += $safe_delete("
				DELETE FROM {$wpdb->prefix}actionscheduler_actions
				WHERE status = 'complete' AND scheduled_date_gmt < NOW() - INTERVAL 30 DAY
			", 'ActionScheduler cleanup');
		}
		// Delete orphaned commentmeta
		$deleted_total += $safe_delete("
			DELETE cm FROM {$wpdb->commentmeta} cm
			LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
			WHERE c.comment_ID IS NULL
		", 'Orphaned commentmeta cleanup');
		// Delete empty auto-draft posts
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->posts}
			WHERE post_status = 'auto-draft' AND post_content = ''
		", 'Auto-draft cleanup');
		// Delete orphaned termmeta
		$deleted_total += $safe_delete("
			DELETE tm FROM {$wpdb->termmeta} tm
			LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
			WHERE t.term_id IS NULL
		", 'Orphaned termmeta cleanup');
		// Delete old spam comments
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->comments}
			WHERE comment_approved = 'spam' AND comment_date < NOW() - INTERVAL 30 DAY
		", 'Spam comments cleanup');
		// Delete old like timestamps
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->postmeta}
			WHERE meta_key = 'like_timestamp' 
			AND meta_value < DATE_SUB(NOW(), INTERVAL 30 DAY)
		", 'Old like timestamps cleanup');
		// Delete old dislike timestamps
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->postmeta}
			WHERE meta_key = 'dislike_timestamp' 
			AND meta_value < DATE_SUB(NOW(), INTERVAL 30 DAY)
		", 'Old dislike timestamps cleanup');
		// Delete old view timestamps
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->postmeta}
			WHERE meta_key = 'view_timestamp' 
			AND meta_value < DATE_SUB(NOW(), INTERVAL 30 DAY)
		", 'Old view timestamps cleanup');
		// Delete old trash posts
		$deleted_total += $safe_delete("
			DELETE FROM {$wpdb->posts}
			WHERE post_status = 'trash' AND post_modified < NOW() - INTERVAL 30 DAY
		", 'Trash posts cleanup');
		// Optimize main tables
		$tables = ['postmeta', 'usermeta', 'options', 'term_relationships'];
		$optimized_count = 0;
		foreach ($tables as $table) {
			$result = $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}{$table}");
			if ($result !== false) {
				$optimized_count++;
			} else {
				$errors[] = "Failed to optimize {$table}: " . $wpdb->last_error;
			}
		}
		// Build result message
		if (!empty($errors)) {
			$error_msg = implode(' | ', $errors);
			return [
				'success' => false,
				'message' => "⚠️ Partial cleanup: {$deleted_total} rows deleted → {$optimized_count} tables optimized. Errors: {$error_msg}"
			];
		}
		return [
			'success' => true,
			'message' => "✅ Total rows deleted: {$deleted_total} → {$optimized_count} tables optimized."
		];
	}

    // ======================================
	// 📰 ADD RSS FEED READER
	// ======================================

    add_action('wp_dashboard_setup', function () {
        wp_add_dashboard_widget('custom_rss_widget', '📡 RSS Feed: Blog & Interests', 'custom_render_rss_widget');
    });

    function custom_render_rss_widget() {
        $feeds = [
            ['label' => 'My Blog', 'url' => 'https://ericroth.org/feed/'],
            ['label' => 'My Interests', 'url' => 'https://ericroth.org/feed/?post_type=my-interests']
        ];
        // Grab Number of Items from Settings -> Reading
        $max_items = (int) get_option('posts_per_rss');
        if (!$max_items || $max_items < 1) {
            $max_items = 10; // Fallback in Case it's unset or invalid
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

    // ======================================
	// 🗂️ PAGES & TRAITS SNAPSHOT
	// ======================================

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
			// Prev/Next navigation group
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
			// Attach tab switching
			tabButtons.forEach(btn => {
				btn.addEventListener('click', () => {
					tabContents.forEach(tab => tab.style.display = 'none');
					const target = widget.querySelector('#' + btn.dataset.tab);
					if (target) {
						target.style.display = 'block';
						// resetPagination is now bound per tab
						target.resetPagination();
					}
				});
			});
			if (tabButtons.length) tabButtons[0].click();
			// Attach pagination per tab
			tabContents.forEach(tab => {
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
				// Bind resetPagination directly to the tab element
				tab.resetPagination = () => renderBatch(0);
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
			});
		});
	});
	</script>

HTML;
});

}

add_action('admin_init', 'initialize_custom_dashboard');
