<?php
defined('ABSPATH') || exit;

function initialize_custom_dashboard() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

	// ======================================
    // 📇 AT A GLANCE
    // ======================================

	// Add Emojis
	add_action('wp_dashboard_setup', 'custom_add_dashboard_widget_icons');
	function custom_add_dashboard_widget_icons() {
        global $wp_meta_boxes;
        if (isset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now'])) {
            $wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']['title'] = '📇 At a Glance';
        }
    }

	// Add CPTs
	add_filter('dashboard_glance_items', 'custom_filter_dashboard_glance_items');
	function custom_filter_dashboard_glance_items($items) {
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
    }

    // ======================================
	// 🎨 THEME SNAPSHOT
	// ======================================

	add_action('wp_dashboard_setup', 'custom_register_theme_snapshot_widget');
	function custom_register_theme_snapshot_widget() {
        wp_add_dashboard_widget('custom_theme_snapshot', '🎨 Theme Snapshot', 'custom_render_theme_snapshot_widget');
    }

	function custom_render_theme_snapshot_widget() {
        $theme = wp_get_theme();
        $theme_dir = get_theme_root() . '/' . $theme->get_stylesheet();
        $updated = date('F j, Y', filemtime($theme_dir . '/style.css'));
        
        printf('<p>Theme is <strong>%s</strong> (v%s). Last updated: <strong>%s</strong></p>',
            esc_html($theme->get('Name')),
            esc_html($theme->get('Version')),
            esc_html($updated)
        );
        echo '<div style="display: flex; gap: 10px;">';
        echo '<a class="button" href="' . esc_url(admin_url('customize.php')) . '" target="_blank" rel="noopener noreferrer">Open Customizer</a>';
        echo '<a class="button" href="https://ericroth.org/wp-admin/admin.php?page=ct-dashboard#/changelog" target="_blank" rel="noopener noreferrer">View Changelog</a>';
        echo '</div>';
    }

    // ======================================
	// 🌀 HOSTING & CODE REPO
	// ======================================

	add_action('wp_dashboard_setup', 'custom_register_hosting_repo_widget');
	function custom_register_hosting_repo_widget() {
        wp_add_dashboard_widget('hosting_code_repo', '🌀 Hosting & Code Repo', 'custom_render_hosting_repo_widget');
    }

	function custom_render_hosting_repo_widget() {
        $links = [
            '🔐 Login' => 'https://auth.hostinger.com/login',
            '📬 Webmail' => 'https://mail.hostinger.com/',
            '🧠 AI' => 'https://ericroth.org/wp-admin/admin.php?page=hostinger-ai-assistant',
            '💾 GitHub' => 'https://github.com/ericrothdotorg'
        ];
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        foreach ($links as $label => $url) {
            echo '<a href="' . esc_url($url) . '" target="_blank" class="button">' . esc_html($label) . '</a>';
        }
        echo '</div>';
    }

    // ======================================
	// 🤖 AI CHATBOTS
	// ======================================

	add_action('wp_dashboard_setup', 'custom_register_ai_chatbots_widget');
	function custom_register_ai_chatbots_widget() {
        wp_add_dashboard_widget('ai_chatbots', '🤖 AI Chatbots', 'custom_render_ai_chatbots_widget');
    }

	function custom_render_ai_chatbots_widget() {
        $bots = [
            '✨ Copilot' => 'https://copilot.microsoft.com/',
            '✨ ChatGPT' => 'https://chatgpt.com/',
            '✨ Claude' => 'https://claude.ai/',
            '✨ DS' => 'https://chat.deepseek.com/'
        ];
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        foreach ($bots as $label => $url) {
            echo '<a href="' . esc_url($url) . '" target="_blank" class="button">' . esc_html($label) . '</a>';
        }
        echo '</div>';
    }

    // ======================================
	// 🎁 SPONSOR CHANNELS
	// ======================================

	add_action('wp_dashboard_setup', 'custom_register_sponsor_channels_widget');
	function custom_register_sponsor_channels_widget() {
        wp_add_dashboard_widget('sponsor_channels', '🎁 Sponsor Channels', 'custom_render_sponsor_channels_widget');
    }

	function custom_render_sponsor_channels_widget() {
        $channels = [
            '💰 GitHub' => 'https://github.com/ericrothdotorg',
			'💰 Patreon' => 'https://www.patreon.com/cw/ericrothdotorg',
            '💰 PayPal' => 'https://www.paypal.com/paypalme/ericrothdotorg',
            '💰 BMC' => 'https://buymeacoffee.com/ericrothdotorg'
        ];
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        foreach ($channels as $label => $url) {
            echo '<a href="' . esc_url($url) . '" target="_blank" class="button">' . esc_html($label) . '</a>';
        }
        echo '</div>';
    }

    // ======================================
	// 🗓️ RECENT SITE ACTIVITY
	// ======================================

	add_action('wp_dashboard_setup', 'custom_register_activity_widget');
	function custom_register_activity_widget() {
        wp_add_dashboard_widget('custom_activity_alerts', '🗓️ Recent Site Activity', 'custom_render_activity_widget');
    }

	function custom_render_activity_widget() {
        $cached = get_transient('custom_activity_stats');
        if ($cached === false) {
            global $wpdb;
            $cached = [
                'contact_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}contact_messages WHERE DATE(submitted_at) = CURDATE()"),
                'contact_total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}contact_messages"),
                'views_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = 'view_timestamp' AND DATE(meta_value) = CURDATE()"),
                'views_total' => $wpdb->get_var("SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_er_post_views'"),
                'likes_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = 'like_timestamp' AND DATE(meta_value) = CURDATE()"),
                'likes_total' => $wpdb->get_var("SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->prefix}postmeta WHERE meta_key = 'likes'"),
                'dislikes_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = 'dislike_timestamp' AND DATE(meta_value) = CURDATE()"),
                'dislikes_total' => $wpdb->get_var("SELECT SUM(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->prefix}postmeta WHERE meta_key = 'dislikes'")
            ];
            set_transient('custom_activity_stats', $cached, 5 * MINUTE_IN_SECONDS);
        }
        
        $format = fn($num) => number_format_i18n($num, 0);
        echo '<ul style="font-size: 14px; line-height: 1.5;">';
        echo '<li>📬 Contact Messages: <strong style="color: red;">' . $format($cached['contact_today']) . '</strong> today / <strong>' . $format($cached['contact_total']) . '</strong> total</li>';
        echo '<li>👁️ Views: <strong style="color: red;">' . $format($cached['views_today']) . '</strong> today / <strong>' . $format($cached['views_total']) . '</strong> total</li>';
        echo '<li>👍 Likes: <strong style="color: red;">' . $format($cached['likes_today']) . '</strong> today / <strong>' . $format($cached['likes_total']) . '</strong> total</li>';
        echo '<li>👎 Dislikes: <strong style="color: red;">' . $format($cached['dislikes_today']) . '</strong> today / <strong>' . $format($cached['dislikes_total']) . '</strong> total</li>';
        echo '</ul>';
    }

    // ======================================
	// 📊 ANALYTICS TOOLKIT
	// ======================================

	add_action('wp_dashboard_setup', function () {
		wp_add_dashboard_widget('custom_analytics_toolkit', '📊 Analytics Toolkit', 'custom_render_analytics_toolkit');
	});

	function custom_render_analytics_toolkit() {
		custom_handle_youtube_check_submission();
		custom_render_external_tools_buttons();
		custom_render_site_metrics();
		custom_render_tools_and_actions();
		custom_render_youtube_check_results();
	}

	function custom_handle_youtube_check_submission() {
		if (!isset($_POST['check_broken_yt'])) return;
		check_admin_referer('check_broken_yt_action', 'check_broken_yt_nonce');
		$result = custom_check_broken_yt_links();
		update_option('custom_broken_yt_results', $result);
		update_option('custom_last_yt_check', time());
		delete_option('custom_yt_check_type');
	}

	function custom_render_external_tools_buttons() {
		$site_url = 'https://ericroth.org/';
		$urls = [
			'googlerich' => 'https://search.google.com/test/rich-results?url=' . urlencode($site_url),
			'schemaorg' => 'https://validator.schema.org/?url=' . urlencode($site_url),
			'pagespeed' => 'https://pagespeed.web.dev/report?url=' . urlencode($site_url) . '&hl=en',
			'webpagetest' => 'https://www.webpagetest.org/?url=' . urlencode($site_url),
			'wave' => 'https://wave.webaim.org/report#/' . urlencode($site_url)
		];
		echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
		echo '<a href="' . esc_url($urls['googlerich']) . '" target="_blank" class="button">🧩 Google Rich</a>';
		echo '<a href="' . esc_url($urls['schemaorg']) . '" target="_blank" class="button">🧩 schema.org</a>';
		echo '</div>';
		echo '<div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">';
		echo '<a href="' . esc_url($urls['pagespeed']) . '" target="_blank" class="button">🚀 PageSpeed</a>';
		echo '<a href="' . esc_url($urls['webpagetest']) . '" target="_blank" class="button">🚀 WebPageTest</a>';
		echo '<a href="' . esc_url($urls['wave']) . '" target="_blank" class="button">♿ Accessibility</a>';
		echo '</div>';
	}

	function custom_render_site_metrics() {
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
		
		echo '<div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">';
		echo '<div style="width: calc(50% - 5px);">';
		echo '<p>🖼️ Media Files: <strong>' . $total_media . '</strong></p>';
		echo '<p style="margin-top: -5px;">🧵 InnoDB Tables: <strong>' . $db_table_count . '</strong></p>';
		echo '</div>';
		echo '<div style="width: calc(50% - 5px);">';
		echo '<p>🧊 Your IP: <strong>' . esc_html($visitor_ip) . '</strong></p>';
		echo '<p style="margin-top: -5px;">🔌 Active Plugins Installed: <strong>' . $plugin_count . '</strong></p>';
		echo '</div>';
		echo '</div>';
	}

	function custom_render_tools_and_actions() {
		echo '<div style="margin-top: 15px;">';
		echo '<div style="margin-bottom: 10px; display: flex; gap: 10px; flex-wrap: wrap;">';
		echo '<a href="https://ericroth.org/wp-admin/themes.php?page=design-block-tracker" class="button">🎨 Design Blocks</a>';
		echo '<a href="https://clarity.microsoft.com/projects/view/eic7b2e9o1/dashboard" target="_blank" class="button">📈 MS Clarity</a>';
		echo '</div>';
		echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
		echo '<form method="post" style="margin: 0;">';
		wp_nonce_field('check_broken_yt_action', 'check_broken_yt_nonce');
		echo '<button type="submit" name="check_broken_yt" class="button">🔍 Broken YT Links</button>';
		echo '</form>';
		echo '<a href="https://www.w3.org/developers/tools/" target="_blank" class="button">🛠️ W3.org Dev Tools</a>';
		echo '</div>';
		echo '</div>';
	}

	function custom_check_broken_yt_links() {
		$broken_links = 0;
		$broken_posts = [];
		$checked_videos = [];
		$args = [
			'post_type' => ['post', 'page', 'my-interests', 'my-traits'],
			'posts_per_page' => -1,
		];
		$query = new WP_Query($args);
		if ($query->have_posts()) {
			foreach ($query->posts as $post) {
				preg_match_all('/https:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $post->post_content, $matches);
				if (!empty($matches[1])) {
					$excluded_video_ids = ['-cW'];
					foreach ($matches[1] as $video_id) {
						if (in_array($video_id, $excluded_video_ids)) continue;
						if (isset($checked_videos[$video_id])) {
							if ($checked_videos[$video_id] === false) {
								$broken_links++;
								if (!in_array($video_id, $broken_posts[$post->ID] ?? [])) {
									$broken_posts[$post->ID][] = $video_id;
								}
							}
							continue;
						}
						$url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$video_id}&format=json";
						$response = wp_remote_get($url, ['timeout' => 10]);
						$is_broken = is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200;
						$checked_videos[$video_id] = !$is_broken;
						if ($is_broken) {
							$broken_links++;
							if (!in_array($video_id, $broken_posts[$post->ID] ?? [])) {
								$broken_posts[$post->ID][] = $video_id;
							}
						}
					}
				}
			}
		}
		wp_reset_postdata();
		return [
			'broken_count' => $broken_links,
			'broken_posts' => $broken_posts
		];
	}

	function custom_render_youtube_check_results() {
		$cached_results = get_option('custom_broken_yt_results', []);
		$last_check = get_option('custom_last_yt_check', 0);
		$check_type = get_option('custom_yt_check_type', '');
		echo '<div style="margin-top: 30px;">';
		$broken_count = !empty($cached_results['broken_count']) ? $cached_results['broken_count'] : 0;
		echo '<p>🔴 Broken YT Links: <strong style="color: red;">' . $broken_count . '</strong></p>';
		if ($last_check) {
			$tag = $check_type ? ' <strong>(' . esc_html($check_type) . ' check)</strong>' : '';
			echo '<p><em>Last checked: ' . 
				esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_check)) . 
				$tag . '</em></p>';
		} else {
			echo '<p><em>Last checked: Never</em></p>';
		}
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
	}

    // ======================================
	// 🧹 OPTIMIZE & CLEAN-UP
	// ======================================

	add_action('wp_dashboard_setup', function () {
		wp_add_dashboard_widget('custom_optimize_and_cleanup', '🧹 Optimize & Clean-Up', 'custom_render_innodb_cleanup');
	});

	function custom_render_innodb_cleanup() {
		custom_handle_cleanup_submission();
		custom_render_action_buttons();
		custom_render_database_stats();
		custom_render_cleanup_history();
	}

	function custom_handle_cleanup_submission() {
		if (!isset($_POST['er_run_full_cleanup'])) return;
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized access');
		}
		check_admin_referer('custom_cleanup_action', 'custom_cleanup_nonce');
		$result = custom_run_innodb_cleanup();
		update_option('custom_last_cleanup', time());
		update_option('custom_last_cleanup_result', $result['message']);
		update_option('custom_last_cleanup_success', $result['success']);
	}

	function custom_render_action_buttons() {
		echo '<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">';
		echo '<form method="post" style="margin: 0;">';
		wp_nonce_field('custom_cleanup_action', 'custom_cleanup_nonce');
		echo '<button type="submit" name="er_run_full_cleanup" class="button">🧵 InnoDB Cleanup</button>';
		echo '</form>';
		echo '<a href="' . esc_url(admin_url('admin.php?page=litespeed-db_optm')) . '" class="button" target="_blank">🛢️ LiteSpeed DB</a>';
		$nonce = wp_create_nonce('purge');
		echo '<a href="' . esc_url(admin_url('index.php?LSCWP_CTRL=purge&LSCWP_NONCE=' . $nonce . '&litespeed_type=purge_all')) . '" class="button">⚡ Purge All</a>';
		echo '</div>';
	}

	function custom_render_database_stats() {
		global $wpdb;
		$postmeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
		$commentmeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta}");
		$termmeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->termmeta}");
		$usermeta_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta}");
		$total = $postmeta_count + $commentmeta_count + $termmeta_count + $usermeta_count;
		
		echo '<div style="margin-top: 15px;">';
		custom_render_stat_row('Content Meta Rows', $postmeta_count);
		custom_render_stat_row('Comment Meta Rows', $commentmeta_count);
		custom_render_stat_row('Term Meta Rows', $termmeta_count);
		custom_render_stat_row('User Meta Rows', $usermeta_count);
		echo '<p style="margin: 5px 0;">TOTAL Meta Rows: <strong>' . number_format_i18n($total) . '</strong></p>';
		echo '</div>';
	}

	function custom_render_stat_row($label, $count) {
		$status = custom_get_health_status($count);
		echo '<p style="margin: 5px 0;">' . $label . ': <strong>' . number_format_i18n($count) . '</strong> ';
		echo '<span style="color:' . esc_attr($status[0]) . ';">— ' . esc_html($status[1]) . '</span></p>';
	}

	function custom_get_health_status($count) {
		if ($count > 50000) return ['red', 'Consider running a cleanup.'];
		if ($count > 10000) return ['orange', 'Moderate bloat detected.'];
		return ['green', 'Healthy state.'];
	}

	function custom_run_innodb_cleanup() {
		global $wpdb;
		$deleted_total = 0;
		$errors = [];
		
		$safe_delete = function($query, $operation_name) use ($wpdb, &$deleted_total, &$errors) {
			$result = $wpdb->query($query);
			if ($result === false) {
				$errors[] = $operation_name . ' failed: ' . $wpdb->last_error;
				return 0;
			}
			return (int)$result;
		};
		
		$deleted_total += custom_cleanup_orphaned_data($wpdb, $safe_delete);
		$deleted_total += custom_cleanup_postmeta($wpdb, $safe_delete);
		$deleted_total += custom_cleanup_usermeta($wpdb, $safe_delete);
		$deleted_total += custom_cleanup_transients($wpdb, $safe_delete);
		$deleted_total += custom_cleanup_old_data($wpdb, $safe_delete);
		$optimized_count = custom_optimize_tables($wpdb, $errors);
		
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

	function custom_cleanup_orphaned_data($wpdb, $safe_delete) {
		$deleted = 0;
		$deleted += $safe_delete("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL", 'Orphaned postmeta cleanup');
		$deleted += $safe_delete("DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.ID IS NULL", 'Orphaned term relationships cleanup');
		$deleted += $safe_delete("DELETE um FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE u.ID IS NULL", 'Orphaned usermeta cleanup');
		$deleted += $safe_delete("DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id WHERE c.comment_ID IS NULL", 'Orphaned commentmeta cleanup');
		$deleted += $safe_delete("DELETE tm FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON t.term_id = tm.term_id WHERE t.term_id IS NULL", 'Orphaned termmeta cleanup');
		return $deleted;
	}

	function custom_cleanup_postmeta($wpdb, $safe_delete) {
		$deleted = 0;
		$safe_keys = ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_last_viewed_timestamp', 'litespeed-optimize-set', 'litespeed-optimize-size'];
		foreach ($safe_keys as $key) {
			$deleted += $safe_delete($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $key), "Postmeta cleanup ({$key})");
		}
		$deleted += $safe_delete("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_menu_item_target' AND (meta_value IS NULL OR meta_value = '')", 'Empty menu item targets cleanup');
		$deleted += $safe_delete("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_oembed_%' OR meta_key LIKE '_oembed_time_%'", 'oEmbed cache cleanup');
		return $deleted;
	}

	function custom_cleanup_usermeta($wpdb, $safe_delete) {
		$deleted = 0;
		$safe_keys = ['_session_tokens', '_last_activity', '_woocommerce_persistent_cart'];
		foreach ($safe_keys as $key) {
			$deleted += $safe_delete($wpdb->prepare("DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", $key), "Usermeta cleanup ({$key})");
		}
		return $deleted;
	}

	function custom_cleanup_transients($wpdb, $safe_delete) {
		$deleted = 0;
		$deleted += $safe_delete("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' AND option_name NOT LIKE '_transient_timeout_%'", 'Transient cleanup');
		$deleted += $safe_delete("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()", 'Expired transient timeout cleanup');
		return $deleted;
	}

	function custom_cleanup_old_data($wpdb, $safe_delete) {
		$deleted = 0;
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}actionscheduler_actions'") === "{$wpdb->prefix}actionscheduler_actions") {
			$deleted += $safe_delete("DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE status = 'complete' AND scheduled_date_gmt < NOW() - INTERVAL 30 DAY", 'ActionScheduler cleanup');
		}
		$deleted += $safe_delete("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_content = ''", 'Auto-draft cleanup');
		$deleted += $safe_delete("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam' AND comment_date < NOW() - INTERVAL 1 DAY", 'Spam comments cleanup');
		$deleted += $safe_delete("DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'like_timestamp' AND meta_value < DATE_SUB(NOW(), INTERVAL 1 DAY)", 'Old like timestamps cleanup');
		$deleted += $safe_delete("DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'dislike_timestamp' AND meta_value < DATE_SUB(NOW(), INTERVAL 1 DAY)", 'Old dislike timestamps cleanup');
		$deleted += $safe_delete("DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'view_timestamp' AND meta_value < DATE_SUB(NOW(), INTERVAL 1 DAY)", 'Old view timestamps cleanup');
		$deleted += $safe_delete("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_modified < NOW() - INTERVAL 1 DAY", 'Trash posts cleanup');
		return $deleted;
	}

	function custom_optimize_tables($wpdb, &$errors) {
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
		return $optimized_count;
	}

	function custom_render_cleanup_history() {
		$last_cleanup = get_option('custom_last_cleanup');
		if (!$last_cleanup) return;
		$last_result = get_option('custom_last_cleanup_result');
		$last_success = get_option('custom_last_cleanup_success', true);
		if ($last_result) {
			$result_color = $last_success ? 'green' : 'red';
			echo '<p style="margin: 10px 0; color: ' . esc_attr($result_color) . ';"><strong>' . esc_html($last_result) . '</strong></p>';
		}
		echo '<p style="margin: 5px 0;"><em>Last cleanup: ' . 
			esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_cleanup)) . '</em></p>';
	}

	// ======================================
	// 📰 RSS FEED: MY BLOG
	// ======================================

	add_action('wp_dashboard_setup', 'custom_register_blog_rss_widget');
	function custom_register_blog_rss_widget() {
        wp_add_dashboard_widget('custom_blog_rss_widget', '📰 RSS Feed: My Blog', 'custom_render_blog_rss_widget');
    }

	function custom_render_blog_rss_widget() {
        $tabs = [
            ['label' => 'Blog', 'url' => 'https://ericroth.org/feed/']
        ];
        
        echo '<div class="rss-widget-wrapper" style="font-size: 14px;">';
		echo '<div class="rss-tab-nav" style="display: flex; align-items: center; margin: 0 0 10px;"><span class="rss-counter"></span></div>';
        
        $max_items = 40;
        
        foreach ($tabs as $i => $tab) {
            echo '<div id="tab-blog-' . $i . '" class="rss-tab-content" style="display: none;">';
            $items = custom_get_rss_items($tab['url'], $max_items);
            
            if (!is_wp_error($items)) {
                foreach ($items as $index => $item) {
                    $title = esc_html($item->get_title());
                    $link = esc_url($item->get_link());
                    $desc = wp_strip_all_tags($item->get_description());
                    $excerpt = $desc ? wp_trim_words($desc, 30) : 'No description available';
                    $date = $item->get_date('U') ? date_i18n('F j, Y', $item->get_date('U')) : 'Unknown date';
                    
					$post_id = '';
                    $edit_link = '';
                    $guid = $item->get_id();
                    if (preg_match('/p=(\d+)/', $guid, $matches)) {
                        $post_id = $matches[1];
                        $edit_link = 'https://ericroth.org/wp-admin/post.php?post=' . $post_id . '&action=edit';
                    }
                    
                    echo '<div class="rss-item" data-index="' . $index . '" style="display: none; margin-bottom: 15px;">';
                    echo '<div><a href="' . $link . '" target="_blank" style="font-weight: bold;">' . $title . '</a> – ';
                    echo '<span style="color: #666; font-size: 12px;">🗓️ Published: <strong>' . esc_html($date) . '</strong></span>';
                    if ($edit_link) {
                        echo ' – <a href="' . esc_url($edit_link) . '" target="_blank" style="color: #2271b1;">Edit</a>';
                    }
                    echo '</div>';
                    echo '<p style="margin: 5px 0;">' . esc_html($excerpt) . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<p>🚫 Error fetching feed: ' . esc_html($items->get_error_message()) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

	// ======================================
	// 📰 RSS FEED: MY INTERESTS
	// ======================================

	add_action('wp_dashboard_setup', 'custom_register_interests_rss_widget');
	function custom_register_interests_rss_widget() {
        wp_add_dashboard_widget('custom_interests_rss_widget', '📰 RSS Feed: My Interests', 'custom_render_interests_rss_widget');
    }

	function custom_render_interests_rss_widget() {
        $tabs = [
            ['label' => 'Interests', 'url' => 'https://ericroth.org/my-interests/feed/']
        ];
        
        echo '<div class="rss-widget-wrapper" style="font-size: 14px;">';
		echo '<div class="rss-tab-nav" style="display: flex; align-items: center; margin: 0 0 10px;"><span class="rss-counter"></span></div>';
        
        $max_items = 40;
        
        foreach ($tabs as $i => $tab) {
            echo '<div id="tab-interests-' . $i . '" class="rss-tab-content" style="display: none;">';
            $items = custom_get_rss_items($tab['url'], $max_items);
            
            if (!is_wp_error($items)) {
                foreach ($items as $index => $item) {
                    $title = esc_html($item->get_title());
                    $link = esc_url($item->get_link());
                    $desc = wp_strip_all_tags($item->get_description());
                    $excerpt = $desc ? wp_trim_words($desc, 30) : 'No description available';
                    $date = $item->get_date('U') ? date_i18n('F j, Y', $item->get_date('U')) : 'Unknown date';
                    
					$post_id = '';
                    $edit_link = '';
                    $guid = $item->get_id();
                    if (preg_match('/p=(\d+)/', $guid, $matches)) {
                        $post_id = $matches[1];
                        $edit_link = 'https://ericroth.org/wp-admin/post.php?post=' . $post_id . '&action=edit';
                    }
                    
                    echo '<div class="rss-item" data-index="' . $index . '" style="display: none; margin-bottom: 15px;">';
                    echo '<div><a href="' . $link . '" target="_blank" style="font-weight: bold;">' . $title . '</a> – ';
                    echo '<span style="color: #666; font-size: 12px;">🗓️ Published: <strong>' . esc_html($date) . '</strong></span>';
                    if ($edit_link) {
                        echo ' – <a href="' . esc_url($edit_link) . '" target="_blank" style="color: #2271b1;">Edit</a>';
                    }
                    echo '</div>';
                    echo '<p style="margin: 5px 0;">' . esc_html($excerpt) . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<p>🚫 Error fetching feed: ' . esc_html($items->get_error_message()) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

	// ======================================
	// --- RSS FEED: SHARED FUNCTION ---
	// ======================================

	function custom_get_rss_items($feed_url, $max_items) {
        if (!function_exists('fetch_feed')) {
            require_once ABSPATH . WPINC . '/feed.php';
        }
        delete_transient('feed_' . md5($feed_url));
        $rss = fetch_feed($feed_url);
        if (is_wp_error($rss)) return $rss;
        return $rss->get_items(0, $max_items);
    }

	// ======================================
	// --- INLINE CSS & JAVASCRIPT ---
	// ======================================

	add_action('admin_footer', 'custom_dashboard_inline_assets');
	function custom_dashboard_inline_assets() {
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
		const tabContent = widget.querySelector('.rss-tab-content');
		
		if (!tabNav || !tabContent) return;
		
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
		
		const items = tabContent.querySelectorAll('.rss-item');
		
		if (items.length === 0) {
			const counter = widget.querySelector('.rss-counter');
			if (counter) counter.textContent = 'No items found';
			return;
		}
		
		let currentStart = 0;
		const batchSize = 4;
		
		function renderBatch(start) {
			items.forEach((item, i) => {
				item.style.display = (i >= start && i < start + batchSize) ? 'block' : 'none';
			});
			currentStart = start;
			
			const counter = widget.querySelector('.rss-counter');
			if (counter) {
				const endItem = Math.min(start + batchSize, items.length);
				counter.textContent = 'Showing ' + (start + 1) + '-' + endItem + ' of ' + items.length;
			}
			
			prevBtn.style.display = currentStart === 0 ? 'none' : 'inline-block';
			nextBtn.style.display = currentStart + batchSize >= items.length ? 'none' : 'inline-block';
		}
		
		renderBatch(0);
		tabContent.style.display = 'block';
		
		prevBtn.addEventListener('click', () => {
			if (currentStart - batchSize >= 0) {
				renderBatch(currentStart - batchSize);
			}
		});
		
		nextBtn.addEventListener('click', () => {
			if (currentStart + batchSize < items.length) {
				renderBatch(currentStart + batchSize);
			}
		});
	});
});
</script>
HTML;
    }

} // Close Function: initialize_custom_dashboard

add_action('admin_init', 'initialize_custom_dashboard');
