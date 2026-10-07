<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ============================================================
// THEME-COUPLING MARKERS (search these before/after a theme switch):
//   THEME RELATED = hard coupling; breaks/orphans on switch — must fix.
//   THEME REVIEW  = soft coupling; reads a theme-defined value, won't
//                   break but the value shifts — verify.
// ============================================================

// ======================================
// SHARED HELPERS
// ======================================

// Renders a row of link buttons. Used by every widget that shows a button row,
// so spacing and markup stay identical everywhere. Optional label in front.
function custom_render_button_row(array $links, $style = '', $row_label = '') {
	echo '<div class="cd-widget cd-flex"' . ($style ? ' style="' . esc_attr($style) . '"' : '') . '>';
	if ($row_label) echo '<span class="cd-subhead cd-row-label">' . esc_html($row_label) . '</span>';
	foreach ($links as $label => $url) {
		echo '<a href="' . esc_url($url) . '" target="_blank" class="button">' . esc_html($label) . '</a>';
	}
	echo '</div>';
}

// One date style everywhere: "July 20, 2026", with time "October 5, 2026 - 07:43".
function custom_format_date($timestamp, $with_time = false) {
	return '<em class="cd-date">' . esc_html(wp_date($with_time ? 'F j, Y - H:i' : 'F j, Y', $timestamp)) . '</em>';
}

// ======================================
// 📇 AT A GLANCE
// ======================================

// Add CPTs, styled like the core rows: normal weight and the post type's own
// menu icon instead of the default circle.
add_filter('dashboard_glance_items', 'custom_filter_dashboard_glance_items');
function custom_filter_dashboard_glance_items($items) {
	$post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
	foreach ($post_types as $pt) {
		$count = wp_count_posts($pt->name)->publish;
		if ($count) {
			$icon = (is_string($pt->menu_icon) && strpos($pt->menu_icon, 'dashicons-') === 0) ? $pt->menu_icon : 'dashicons-admin-post';
			$items[] = sprintf(
				'<a href="edit.php?post_type=%1$s" target="_blank" class="cd-glance"><i class="dashicons %4$s" aria-hidden="true"></i>%2$s %3$s</a>',
				esc_attr($pt->name),
				number_format_i18n($count),
				esc_html($pt->labels->name),
				esc_attr($icon)
			);
		}
	}
	return $items;
}

// ======================================
// 🎨 THEME SNAPSHOT - THEME RELATED
// ======================================

function custom_render_theme_snapshot_widget() {
	$theme     = wp_get_theme();
	$theme_dir = get_theme_root() . '/' . $theme->get_stylesheet();
	$updated   = custom_format_date(filemtime($theme_dir . '/style.css'));

	printf('<p>Theme is <strong>%s</strong> (v%s). Last updated: %s</p>',
		esc_html($theme->get('Name')),
		esc_html($theme->get('Version')),
		$updated
	);
	// THEME RELATED — hardcoded parent-theme changelog URL (olliewp.com).
	// Points to the current theme's docs; update or remove on a theme switch.
	custom_render_button_row([
		'Site Editor'    => admin_url('site-editor.php'),
		'View Changelog' => 'https://olliewp.com/docs/ollie-block-theme/ollie-changelog/',
	]);
}

// ======================================
// 📌 EDITING RULES — FILE-BASED REMINDER
// ======================================

function custom_render_editing_rules_widget() {
	echo '<div style="line-height: 1.6;">';
	echo '<p style="margin: 0 0 10px;"><strong class="cd-alert">This Site is file-based.</strong></p>';
	echo '<ul style="margin: 0 0 10px; padding-left: 18px; list-style: disc;">';
	echo '<li>Templates &amp; Parts → Edit the Theme Files (<code>Templates/</code>, <code>Parts/</code>) directly.</li>';
	echo '<li>Styles → Edit <code>theme.json</code>, <code>style.css</code>, <code>snippet.css</code></li>';
	echo '<li><strong class="cd-alert">Never</strong> edit in Appearance → Editor cuz saving there creates a DB Copy that silently overrides the File.</li>';
	echo '</ul>';
	echo '<p style="margin: 0;"><span class="cd-success cd-bold">OK to edit in the Editor</span>: The <strong>Synced Patterns</strong> (→ <strong>Design Blocks</strong>) and the <strong>Nav Menus</strong> cuz these have no File Form.</p>';
	echo '</div>';
}

// ======================================
// 🌀 HOSTING & CODE REPO
// ======================================

function custom_render_hosting_repo_widget() {
	custom_render_button_row([
		'Login'   => 'https://auth.hostinger.com/login',
		'Webmail' => 'https://mail.hostinger.com/',
		'AI'      => admin_url('admin.php?page=hostinger-ai-assistant'),
	], '', 'Hostinger');
	custom_render_button_row([
		'GitHub'        => 'https://github.com/ericrothdotorg',
		'Design Blocks' => admin_url('themes.php?page=design-block-tracker'),
		'Snippets'      => admin_url('admin.php?page=snippets'),
	], 'margin-top: 6px;', 'Codes');
}

// ======================================
// 🔗 QUICK LINKS
// ======================================

function custom_render_quick_links_widget() {
	$groups = [
		'Chatbots' => [
			'Copilot'  => 'https://m365.cloud.microsoft/chat',
			'Frontier' => 'https://stride.microsoft.com/',
			'Claude'   => 'https://claude.ai/',
			'DS'       => 'https://chat.deepseek.com/',
		],
		'Sponsors' => [
			'GitHub'  => 'https://github.com/ericrothdotorg',
			'Patreon' => 'https://www.patreon.com/cw/ericrothdotorg',
			'PayPal'  => 'https://www.paypal.com/paypalme/ericrothdotorg',
			'BMC'     => 'https://buymeacoffee.com/ericrothdotorg',
		],
	];
	$first = true;
	foreach ($groups as $row_label => $links) {
		custom_render_button_row($links, $first ? '' : 'margin-top: 6px;', $row_label);
		$first = false;
	}
}

// ======================================
// 🗓️ RECENT SITE ACTIVITY
// ======================================

function custom_render_activity_widget() {
	global $wpdb;
	$table = $wpdb->prefix . 'er_post_stats';
	// Start of today in site time. er_post_stats stores site time; contact
	// messages and subscribers are stamped by MySQL in UTC, so they are
	// compared with the same moment in UTC. (CURDATE() would be the UTC day.)
	$today     = current_time('Y-m-d') . ' 00:00:00';
	$today_utc = get_gmt_from_date($today);

	$cached = get_transient('custom_activity_stats');
	if ($cached === false) {
		// Views, likes and dislikes come from the "Stats Engine" snippet — the same
		// source the frontend shortcodes read. If the engine is ever switched off,
		// this shows zeros instead of crashing.
		$stats = function_exists('er_stats_snapshot') ? er_stats_snapshot() : [
			'views_today'    => 0, 'views_total'    => 0, 'real_views_today'    => 0,
			'likes_today'    => 0, 'likes_total'    => 0, 'real_likes_today'    => 0,
			'dislikes_today' => 0, 'dislikes_total' => 0, 'real_dislikes_today' => 0,
		];
		$cached = array_merge($stats, [
			'contact_today'     => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}er_contact_messages WHERE submitted_at >= %s", $today_utc)),
			'contact_total'     => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_contact_messages"),
			'subscribers_today' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}er_subscribers WHERE status = 'active' AND created_at >= %s", $today_utc)),
			'subscribers_total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_subscribers WHERE status = 'active'"),
		]);
		set_transient('custom_activity_stats', $cached, 5 * MINUTE_IN_SECONDS);
	}

	// Posts reacted to today. Read live on every load, so a new vote shows up
	// immediately instead of waiting out the five-minute transient above.
	$posts_reacted = function($type) use ($wpdb, $table, $today) {
		return $wpdb->get_results($wpdb->prepare("
			SELECT DISTINCT p.ID, p.post_title, p.post_type
			FROM {$table} ps
			JOIN {$wpdb->posts} p ON p.ID = ps.post_id
			WHERE ps.type = %s AND ps.row_type = 'event'
			  AND ps.created_at >= %s
			  AND p.post_status = 'publish'
			ORDER BY p.post_title ASC
		", $type, $today));
	};
	$liked_today    = $posts_reacted('like');
	$disliked_today = $posts_reacted('dislike');

	$format = fn($num) => number_format_i18n((int) $num, 0);
	// Control line: how many of today's figures were recorded from real visitors.
	$real = fn($num) => ' <span class="cd-muted" style="font-size: 12px;">(' . number_format_i18n((int) $num, 0) . ' real)</span>';
	// Rows open themselves when there is something real to see, so a real vote
	// never hides behind a click.
	$fold = fn($posts) => empty($posts) ? 'none' : 'block';
	// Fingerprint of the current list. Changes when a different post is reacted to.
	$signature = fn($posts) => implode('.', array_map(fn($p) => (int) $p->ID, $posts));

	$render_today_list = function($posts) {
		if (empty($posts)) {
			echo '<li style="font-style: italic;">Nothing to review.</li>';
			return;
		}
		foreach ($posts as $p) {
			$type_label = ucfirst(str_replace('-', ' ', $p->post_type));
			echo '<li style="margin-bottom: 5px;">';
			echo '<a href="' . esc_url(get_edit_post_link($p->ID)) . '" target="_blank">' . esc_html($p->post_title) . '</a>';
			echo ' <span class="cd-muted" style="font-size: 11px;">(' . esc_html($type_label) . ')</span>';
			echo ' <a href="' . esc_url(get_permalink($p->ID)) . '" target="_blank" class="cd-muted" style="font-size: 11px;">↗</a>';
			echo '</li>';
		}
	};

	$reaction_row = function($label, $slug, $today_val, $real_val, $total_val, $posts)
	                use ($format, $real, $fold, $signature, $render_today_list) {
		echo '<li>' . $label . ': ';
		echo '<span class="cd-toggle cd-summary" data-target="' . esc_attr($slug) . '-today">' . $format($today_val) . ' today</span>' . $real($real_val);
		echo ' / <strong>' . $format($total_val) . '</strong> total';
		echo '<ul id="' . esc_attr($slug) . '-today" data-signature="' . esc_attr($signature($posts)) . '" style="display:' . $fold($posts) . '; margin: 8px 0 4px 16px; font-size: 13px; line-height: 1.8;">';
		$render_today_list($posts);
		echo '</ul></li>';
	};

	echo '<ul style="line-height: 1.5;">';
	echo '<li>Contact Messages: <strong class="cd-alert">' . $format($cached['contact_today']) . '</strong> today / <strong>' . $format($cached['contact_total']) . '</strong> total</li>';
	echo '<li>Subscribers: <strong class="cd-alert">' . $format($cached['subscribers_today']) . '</strong> new today / <strong>' . $format($cached['subscribers_total']) . '</strong> total</li>';
	echo '<li>Views: <strong class="cd-alert">' . $format($cached['views_today']) . '</strong> today' . $real($cached['real_views_today'] ?? 0) . ' / <strong>' . $format($cached['views_total']) . '</strong> total</li>';
	$reaction_row('Likes',    'likes',    $cached['likes_today'],    $cached['real_likes_today'] ?? 0,    $cached['likes_total'],    $liked_today);
	$reaction_row('Dislikes', 'dislikes', $cached['dislikes_today'], $cached['real_dislikes_today'] ?? 0, $cached['dislikes_total'], $disliked_today);
	echo '</ul>';
}

// ======================================
// 📊 ANALYSIS TOOLKIT
// ======================================

function custom_render_analysis_toolkit() {
	custom_handle_youtube_check_submission();
	custom_render_external_tools_buttons();
	custom_render_site_metrics();
	custom_render_tools_and_actions();
}

function custom_handle_youtube_check_submission() {
	if (!isset($_POST['check_broken_yt'])) return;
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized access');
	}
	check_admin_referer('check_broken_yt_action', 'check_broken_yt_nonce');
	update_option('custom_broken_yt_results', custom_check_broken_yt_links());
	update_option('custom_last_yt_check', time());
}

function custom_render_external_tools_buttons() {
	$site = urlencode(home_url('/'));
	custom_render_button_row([
		'Google Rich'   => 'https://search.google.com/test/rich-results?url=' . $site,
		'schema.org'    => 'https://validator.schema.org/?url=' . $site,
		'Accessibility' => 'https://wave.webaim.org/report#/' . $site,
	]);
}

function custom_render_site_metrics() {
	global $wpdb;
	$total_media    = array_sum((array) wp_count_attachments());
	$db_table_count = count($wpdb->get_col('SHOW TABLES'));

	// First public IPv4 we can find across the usual proxy headers.
	$visitor_ip = '';
	foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $header) {
		if (empty($_SERVER[$header])) continue;
		foreach (explode(',', $_SERVER[$header]) as $candidate) {
			$candidate = trim($candidate);
			if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$visitor_ip = $candidate;
				break 2;
			}
		}
	}

	// Shows the IPv4 when there is one, otherwise falls back to a city lookup.
	if ($visitor_ip !== '') {
		$cd_display = 'Your IP: <strong>' . esc_html($visitor_ip) . '</strong>';
	} else {
		// Cached per IP for a day: without it every dashboard load waited for
		// ip-api.com (up to 5 s) whenever you are on IPv6.
		$raw = $_SERVER['REMOTE_ADDR'] ?? '';
		$loc = get_transient('cd_place_' . md5($raw));
		if ($loc === false) {
			$loc = 'Unknown';
			if (filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				$resp = wp_remote_get("http://ip-api.com/json/{$raw}?fields=status,city,country", ['timeout' => 5]);
				if (!is_wp_error($resp)) {
					$data = json_decode(wp_remote_retrieve_body($resp), true);
					if (isset($data['status']) && $data['status'] === 'success') {
						$loc = trim(($data['city'] ?? '') . ', ' . ($data['country'] ?? ''), ', ');
					}
				}
			}
			set_transient('cd_place_' . md5($raw), $loc, DAY_IN_SECONDS);
		}
		$cd_display = 'Place: <strong>' . esc_html($loc) . '</strong>';
	}

	// Active plugins straight from the option: correct for the label, and no
	// scan of every plugin file on each dashboard load.
	$plugin_count = count((array) get_option('active_plugins', []));

	echo '<div class="cd-widget cd-flex cd-gap">';
	echo '<div style="width: calc(50% - 5px);">';
	echo '<p style="margin: 0 0 5px;">Media Files: <strong>' . number_format_i18n($total_media) . '</strong></p>';
	echo '<p style="margin: 0;">InnoDB Tables: <strong>' . number_format_i18n($db_table_count) . '</strong></p>';
	echo '</div>';
	echo '<div style="width: calc(50% - 5px);">';
	echo '<p style="margin: 0 0 5px;">' . $cd_display . '</p>';
	echo '<p style="margin: 0;">Active Plugins: <strong>' . number_format_i18n($plugin_count) . '</strong></p>';
	echo '</div>';
	echo '</div>';
}

function custom_render_tools_and_actions() {
	echo '<div class="cd-gap">';
	custom_render_button_row([
		'W3.org Dev Tools' => 'https://www.w3.org/developers/tools/',
		'MS Clarity'       => 'https://clarity.microsoft.com/projects/view/eic7b2e9o1/dashboard',
	], 'margin-bottom: 10px;');

	echo '<div class="cd-widget cd-flex" style="align-items: center; gap: 15px;">';
	echo '<form method="post" class="cd-form">';
	wp_nonce_field('check_broken_yt_action', 'check_broken_yt_nonce');
	echo '<button type="submit" name="check_broken_yt" class="button">Broken YT Links</button>';
	echo '</form>';

	$results      = get_option('custom_broken_yt_results', []);
	$last_check   = get_option('custom_last_yt_check', 0);
	$broken_count = !empty($results['broken_count']) ? (int) $results['broken_count'] : 0;

	echo '<div style="display: flex; flex-direction: column; line-height: 1.4;">';
	echo '<span>Broken YT Links: <strong class="cd-alert">' . $broken_count . '</strong></span>';
	echo '<span>Last checked: ' . ($last_check ? custom_format_date($last_check, true) : '<em class="cd-date">Never</em>') . '</span>';
	echo '</div>';
	echo '</div>';

	if (!empty($results['broken_posts'])) {
		echo '<details class="cd-details" style="margin-top: 10px;">';
		echo '<summary class="cd-summary" style="margin-bottom: 15px;">Broken Links Locations</summary>';
		echo '<ul>';
		foreach ($results['broken_posts'] as $post_id => $video_ids) {
			echo '<li><a href="' . esc_url(get_edit_post_link($post_id)) . '" target="_blank">' . esc_html(get_the_title($post_id)) . '</a>: ';
			echo esc_html(implode(', ', $video_ids)) . '</li>';
		}
		echo '</ul>';
		echo '</details>';
	}
	echo '</div>';
}

// Video IDs that always fail the oEmbed check but are fine in the page.
function custom_yt_excluded_ids() {
	return ['-cW'];
}

function custom_check_broken_yt_links() {
	$broken_links   = 0;
	$broken_posts   = [];
	$checked_videos = [];
	$excluded       = custom_yt_excluded_ids();

	$query = new WP_Query([
		'post_type'      => ['post', 'page', 'my-interests', 'my-traits'],
		'posts_per_page' => -1,
	]);

	foreach ($query->posts as $post) {
		preg_match_all('/https:\/\/(?:www\.)?youtube\.com\/(?:watch\?v=|embed\/)([a-zA-Z0-9_-]+)/', $post->post_content, $matches);
		if (empty($matches[1])) continue;
		
		// A block embed writes the same URL twice (block settings + wrapper),
		// so each video is only counted once per post.
		foreach (array_unique($matches[1]) as $video_id) {
			if (in_array($video_id, $excluded, true)) continue;

			// Each video is only fetched once, however many posts embed it.
			if (!isset($checked_videos[$video_id])) {
				$url = 'https://www.youtube.com/oembed?url=' . urlencode("https://www.youtube.com/watch?v={$video_id}") . '&format=json';
				$response = wp_remote_get($url, [
					'timeout'    => 10,
					'user-agent' => 'Mozilla/5.0 (compatible; LinkChecker/1.0)',
				]);
				$code = wp_remote_retrieve_response_code($response);
				$checked_videos[$video_id] = !(is_wp_error($response) || !in_array($code, [200, 401, 403], true));
			}

			if ($checked_videos[$video_id] === false) {
				$broken_links++;
				if (!in_array($video_id, $broken_posts[$post->ID] ?? [], true)) {
					$broken_posts[$post->ID][] = $video_id;
				}
			}
		}
	}
	wp_reset_postdata();

	return ['broken_count' => $broken_links, 'broken_posts' => $broken_posts];
}

// ======================================
// 🚀 PERFORMANCE
// ======================================

// Google PageSpeed results of the home page (mobile + desktop), tested on
// demand via "Run Speed Test".
//
// The browser calls Google directly and only sends the numbers to admin-ajax
// (custom_perf_save). Do not move the call to PHP: a test takes 30-60 s and
// Hostinger cuts such requests off.
//
// Owner of custom_perf_mobile and custom_perf_desktop (wp_options).
// API key: ER_PSI_KEY in wp-config.php.

function custom_perf_categories() {
	return [
		'performance'    => 'Performance',
		'accessibility'  => 'Accessibility',
		'best-practices' => 'Best Practices',
		'seo'            => 'SEO',
	];
}

// Lighthouse audit id => [short label, full name for the tooltip]
function custom_perf_metrics() {
	return [
		'first-contentful-paint'   => ['FCP',         'First Contentful Paint'],
		'largest-contentful-paint' => ['LCP',         'Largest Contentful Paint'],
		'total-blocking-time'      => ['TBT',         'Total Blocking Time'],
		'cumulative-layout-shift'  => ['CLS',         'Cumulative Layout Shift'],
		'speed-index'              => ['Speed Index', 'Speed Index'],
	];
}

// Stores one result sent by the browser. Only known keys are kept, scores
// are clamped to 0-100 and the metric values are plain text.
add_action('wp_ajax_custom_perf_save', function () {
	if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized', 403);
	check_ajax_referer('custom_perf_run');

	$strategy = (isset($_POST['strategy']) && $_POST['strategy'] === 'desktop') ? 'desktop' : 'mobile';
	$in = json_decode(wp_unslash($_POST['result'] ?? ''), true);
	if (!is_array($in) || !isset($in['scores']['performance'])) {
		wp_send_json_error('Invalid result');
	}

	$score = function ($v) {
		return is_numeric($v) ? max(0, min(100, (int) $v)) : null;
	};
	$scores = [];
	foreach (array_keys(custom_perf_categories()) as $cat) {
		$scores[$cat] = $score($in['scores'][$cat] ?? null);
	}
	$metrics = [];
	foreach (array_keys(custom_perf_metrics()) as $id) {
		$m = $in['metrics'][$id] ?? [];
		$metrics[$id] = [
			'value' => isset($m['value']) ? substr(sanitize_text_field($m['value']), 0, 20) : '–',
			'score' => $score($m['score'] ?? null),
		];
	}

	// Audits that are not green. Plain text only; the link must be http(s).
	$issues = [];
	foreach (array_slice((array) ($in['issues'] ?? []), 0, 40) as $i) {
		if (!is_array($i) || !isset($i['cat'], $i['title']) || !isset(custom_perf_categories()[$i['cat']])) continue;
		$issues[] = [
			'cat'   => $i['cat'],
			'title' => substr(sanitize_text_field($i['title']), 0, 200),
			'value' => substr(sanitize_text_field($i['value'] ?? ''), 0, 60),
			'score' => $score($i['score'] ?? null),
			'desc'  => substr(sanitize_textarea_field($i['desc'] ?? ''), 0, 600),
			'link'  => esc_url_raw($i['link'] ?? '', ['https', 'http']),
		];
	}

	update_option('custom_perf_' . $strategy, [
		'scores'  => $scores,
		'metrics' => $metrics,
		'issues'  => $issues,
		'time'    => time(),
	], false);

	ob_start();
	custom_perf_render_item_inner($strategy);
	wp_send_json_success(['html' => ob_get_clean()]);
});

// Colour after Google's thresholds (90+ good, 50+ needs work, below poor).
function custom_perf_class($score) {
	if ($score === null) return 'cd-perf-none';
	if ($score >= 90)    return 'cd-perf-good';
	if ($score >= 50)    return 'cd-perf-avg';
	return 'cd-perf-bad';
}

function custom_perf_label($strategy) {
	return $strategy === 'desktop' ? 'Desktop' : 'Mobile';
}

function custom_perf_render_item_inner($strategy) {
	$d = get_option('custom_perf_' . $strategy);
	$d = is_array($d) ? $d : [];
	$scores  = $d['scores']  ?? [];
	$metrics = $d['metrics'] ?? [];
	$perf    = $scores['performance'] ?? null;
	$time    = isset($d['time']) ? custom_format_date($d['time'], true) : '—';
	?>
	<div class="cd-perf-head">
		<div class="cd-perf-ring <?php echo esc_attr(custom_perf_class($perf)); ?>">
			<svg viewBox="0 0 36 36" aria-hidden="true">
				<circle class="cd-perf-track" cx="18" cy="18" r="15.9155"></circle>
				<circle class="cd-perf-bar" cx="18" cy="18" r="15.9155"
					stroke-dasharray="<?php echo (int) $perf; ?> 100"></circle>
			</svg>
			<span class="cd-perf-score"><?php echo $perf === null ? '–' : (int) $perf; ?></span>
		</div>
		<div>
			<strong><?php echo esc_html(custom_perf_label($strategy)); ?></strong><br>
			<span class="cd-muted">Last scanned:</span>
		</div>
	</div>
	<div class="cd-perf-stamp"><?php echo $time; ?></div>
	<div class="cd-perf-error"></div>

	<dl class="cd-perf-list">
		<?php foreach (custom_perf_categories() as $cat => $name) :
			if ($cat === 'performance') continue;
			$s = $scores[$cat] ?? null; ?>
			<dt><?php echo esc_html($name); ?></dt>
			<dd class="<?php echo esc_attr(custom_perf_class($s)); ?>"><?php echo $s === null ? '–' : (int) $s; ?></dd>
		<?php endforeach; ?>
	</dl>

	<dl class="cd-perf-list">
		<?php foreach (custom_perf_metrics() as $id => [$short, $full]) :
			$m = $metrics[$id] ?? ['value' => '–', 'score' => null]; ?>
			<dt title="<?php echo esc_attr($full); ?>"><?php echo esc_html($short); ?></dt>
			<dd class="<?php echo esc_attr(custom_perf_class($m['score'])); ?>"><?php echo esc_html($m['value']); ?></dd>
		<?php endforeach; ?>
	</dl>

	<?php
	if (isset($d['issues']) && is_array($d['issues'])) :
		$issues = $d['issues'];
		usort($issues, function ($a, $b) { return (int) $a['score'] <=> (int) $b['score']; });
		?>
		<details class="cd-perf-issues cd-details">
			<summary>Analysis (<?php echo count($issues); ?>)</summary>
			<?php if (!$issues) : ?>
				<p class="cd-muted">Nothing cost points in this run.</p>
			<?php endif; ?>
			<?php foreach (custom_perf_categories() as $cat => $name) :
				$list = array_filter($issues, function ($i) use ($cat) { return $i['cat'] === $cat; });
				if (!$list) continue; ?>
				<div class="cd-perf-cat cd-subhead"><?php echo esc_html($name); ?></div>
				<?php foreach ($list as $i) : ?>
					<details class="cd-perf-issue">
						<summary>
							<span class="cd-perf-dot <?php echo esc_attr(custom_perf_class($i['score'])); ?>"></span>
							<span><?php echo esc_html($i['title']); ?><?php if ($i['value'] !== '') : ?>
								<span class="cd-muted"> – <?php echo esc_html($i['value']); ?></span><?php endif; ?></span>
						</summary>
						<p><?php echo esc_html($i['desc']); ?>
							<?php if ($i['link']) : ?><a href="<?php echo esc_url($i['link']); ?>" target="_blank" rel="noopener">Learn more</a><?php endif; ?></p>
					</details>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</details>
	<?php endif; ?>
	<?php
}

function custom_perf_render_widget() {
	?>
	<div class="cd-widget cd-perf">
		<div class="cd-perf-row">
			<div class="cd-perf-item" data-strategy="desktop"><?php custom_perf_render_item_inner('desktop'); ?></div>
			<div class="cd-perf-divider"></div>
			<div class="cd-perf-item" data-strategy="mobile"><?php custom_perf_render_item_inner('mobile'); ?></div>
		</div>
		<?php $site = urlencode(home_url('/')); ?>
		<div class="cd-perf-foot">
			<button type="button" class="button cd-perf-btn">Run Speed Test</button>
			<a class="button" target="_blank" rel="noopener"
				href="<?php echo esc_url('https://pagespeed.web.dev/report?url=' . $site . '&hl=en'); ?>">Full Report</a>
			<a class="button" target="_blank" rel="noopener"
				href="<?php echo esc_url('https://www.webpagetest.org/?url=' . $site); ?>">WebPageTest</a>
		</div>
		<p class="cd-muted cd-perf-status"></p>
	</div>
	<style>
		.cd-perf-row { display: flex; gap: 14px; }
		.cd-perf-item { flex: 1; min-width: 0; }
		.cd-perf-divider { width: 1px; background: #dcdcde; }
		.cd-perf-head { display: flex; align-items: center; gap: 10px; }
		.cd-perf-stamp { margin-top: 6px; white-space: nowrap; }
		.cd-perf-ring { position: relative; width: 56px; height: 56px; flex: none; }
		.cd-perf-ring svg { width: 100%; height: 100%; transform: rotate(-90deg); }
		.cd-perf-ring circle { fill: none; stroke-width: 3.2; }
		.cd-perf-track { stroke: #e8e8e8; }
		.cd-perf-bar { stroke-linecap: round; transition: stroke-dasharray .6s ease; }
		.cd-perf-score { position: absolute; inset: 0; display: flex; align-items: center;
			justify-content: center; font-size: 17px; font-weight: bold; }
		.cd-perf-good .cd-perf-bar { stroke: var(--cd-green); }
		.cd-perf-avg  .cd-perf-bar { stroke: var(--cd-orange); }
		.cd-perf-bad  .cd-perf-bar { stroke: var(--cd-red); }
		.cd-perf-good .cd-perf-score, dd.cd-perf-good { color: var(--cd-green); }
		.cd-perf-avg  .cd-perf-score, dd.cd-perf-avg  { color: var(--cd-orange); }
		.cd-perf-bad  .cd-perf-score, dd.cd-perf-bad  { color: var(--cd-red); }
		.cd-perf-none .cd-perf-score, dd.cd-perf-none { color: var(--cd-muted); }
		.cd-perf-busy svg { animation: cd-perf-spin 1s linear infinite; }
		.cd-perf-busy .cd-perf-bar { stroke: #8da6b9; stroke-dasharray: 25 100; }
		@keyframes cd-perf-spin { to { transform: rotate(270deg); } }
		.cd-perf-error { color: var(--cd-red); font-size: 12px; }
		.cd-perf-list { display: grid; grid-template-columns: 1fr auto; gap: 2px 8px;
			margin: 10px 0 0; padding-top: 10px; border-top: 1px solid var(--cd-line); }
		.cd-perf-list dt { color: var(--cd-muted); }
		.cd-perf-list dt[title] { cursor: help; }
		.cd-perf-list dd { margin: 0; text-align: right; font-weight: bold; white-space: nowrap; }
		.cd-perf-issues { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--cd-line); }
		.cd-perf-issues > summary { font-weight: bold; }
		.cd-perf-cat { margin: 8px 0 2px; }
		.cd-perf-issue > summary { display: flex; gap: 6px; align-items: baseline; cursor: pointer; list-style: none; padding: 2px 0; }
		.cd-perf-issue > summary::-webkit-details-marker { display: none; }
		.cd-perf-issue p { margin: 2px 0 6px 14px; color: var(--cd-muted); font-size: 12px; }
		.cd-perf-dot { flex: none; width: 8px; height: 8px; border-radius: 50%; position: relative; top: -1px; }
		.cd-perf-dot.cd-perf-avg { background: var(--cd-orange); }
		.cd-perf-dot.cd-perf-bad { background: var(--cd-red); }
		.cd-perf-dot.cd-perf-none { background: var(--cd-muted); }
		.cd-perf-foot { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 16px; }
		.cd-perf-status { margin: 6px 0 0; }
		.cd-perf-status:empty { display: none; }
	</style>
	<script>
	(function () {
		var root  = document.currentScript.parentNode;
		var btn   = root.querySelector('.cd-perf-btn');
		var stat  = root.querySelector('.cd-perf-status');
		var cfg   = <?php echo wp_json_encode([
			'nonce'      => wp_create_nonce('custom_perf_run'),
			'key'        => defined('ER_PSI_KEY') ? ER_PSI_KEY : '',
			'url'        => home_url('/'),
			'categories' => array_keys(custom_perf_categories()),
			'metrics'    => array_keys(custom_perf_metrics()),
		]); ?>;
		var TIMEOUT = 120000; // ms; Google itself needs 30-60 s

		// Calls Google directly from the browser - the server never waits.
		function psi(strategy) {
			var q = new URLSearchParams({ url: cfg.url, strategy: strategy, key: cfg.key });
			cfg.categories.forEach(function (c) { q.append('category', c); });
			var ctrl = new AbortController();
			var t = setTimeout(function () { ctrl.abort(); }, TIMEOUT);
			return fetch('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' + q, { signal: ctrl.signal })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					clearTimeout(t);
					if (j.error) throw new Error('Google: ' + j.error.message);
					var lh = j.lighthouseResult;
					if (!lh || !lh.categories || !lh.categories.performance) throw new Error('Google: no score in the response');
					var pct = function (v) { return typeof v === 'number' ? Math.round(v * 100) : null; };
					var res = { scores: {}, metrics: {} };
					cfg.categories.forEach(function (c) { res.scores[c] = lh.categories[c] ? pct(lh.categories[c].score) : null; });
					cfg.metrics.forEach(function (id) {
						var a = lh.audits[id] || {};
						res.metrics[id] = { value: (a.displayValue || '–').replace(/\u00a0/g, ' '), score: pct(a.score) };
					});
					// Only what cost points in this run: performance points that slow a non-green
					// metric, other categories only points that count in their score.
					var skipMode = ['notApplicable', 'informative', 'manual', 'error'];
					var metricOf = {
						FCP: 'first-contentful-paint', LCP: 'largest-contentful-paint',
						TBT: 'total-blocking-time',    CLS: 'cumulative-layout-shift'
					};
					var weak = {};
					Object.keys(metricOf).forEach(function (k) {
						var m = lh.audits[metricOf[k]];
						if (m && typeof m.score === 'number' && m.score < 0.9) weak[k] = true;
					});
					var seen = {};
					res.issues = [];
					cfg.categories.forEach(function (c) {
						var cat = lh.categories[c];
						if (!cat) return;
						cat.auditRefs.forEach(function (ref) {
							var a = lh.audits[ref.id];
							if (!a || seen[ref.id] || ref.group === 'metrics' || ref.group === 'hidden') return;
							if (a.score === null || a.score >= 0.9 || skipMode.indexOf(a.scoreDisplayMode) >= 0) return;
							if (c === 'performance') {
								var sv = a.metricSavings || {};
								if (!Object.keys(sv).some(function (k) { return weak[k] && sv[k] > 0; })) return;
							} else if (!ref.weight) {
								return;
							}
							seen[ref.id] = true;
							var desc = a.description || '';
							// "Learn more" links become the link below the text; any other
							// inline link keeps its text.
							var learn = /\[Learn[^\]]*\]\((https?:[^)\s]+)\)/.exec(desc);
							var link  = learn ? learn[1] : ((/\]\((https?:[^)\s]+)\)/.exec(desc) || [])[1] || '');
							res.issues.push({
								cat:   c,
								title: (a.title || '').replace(/`/g, ''),
								value: (a.displayValue || '').replace(/\u00a0/g, ' '),
								score: pct(a.score),
								desc:  desc.replace(/\s*\[Learn[^\]]*\]\([^)]*\)\.?/g, '')
								           .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
								           .replace(/`/g, '').trim(),
								link:  link
							});
						});
					});
					return res;
				}, function (e) {
					clearTimeout(t);
					throw new Error(e.name === 'AbortError' ? 'Google did not answer within 2 minutes' : 'Google not reachable');
				});
		}

		// Stores the numbers on the server and returns the re-rendered column.
		function save(strategy, res) {
			var body = new FormData();
			body.append('action', 'custom_perf_save');
			body.append('strategy', strategy);
			body.append('result', JSON.stringify(res));
			body.append('_ajax_nonce', cfg.nonce);
			return fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) {
					if (!r.ok) throw new Error('Saving failed (HTTP ' + r.status + ')');
					return r.json();
				})
				.then(function (j) {
					if (!j.success) throw new Error('Saving failed: ' + (j.data || 'unknown error'));
					return j.data.html;
				});
		}

		function run(item) {
			var strategy = item.dataset.strategy;
			var ring = item.querySelector('.cd-perf-ring');
			var err  = item.querySelector('.cd-perf-error');
			var old  = ring.className;
			ring.className = 'cd-perf-ring cd-perf-busy';
			err.textContent = '';
			return psi(strategy)
				.then(function (res) { return save(strategy, res); })
				.then(function (html) { item.innerHTML = html; })
				.catch(function (e) {
					ring.className = old;
					err.textContent = e.message;
				});
		}

		btn.addEventListener('click', function () {
			if (!cfg.key) { stat.textContent = 'ER_PSI_KEY is missing in wp-config.php'; return; }
			btn.disabled = true;
			stat.textContent = 'Testing… (about 40 s)';
			Promise.all([].map.call(root.querySelectorAll('.cd-perf-item'), run))
				.then(function () { btn.disabled = false; stat.textContent = ''; });
		});
	})();
	</script>
	<?php
}

// ======================================
// 🧹 OPTIMIZE & CLEAN-UP
// ======================================

// Layout of this section:
//   1. Widget assembly      — what the dashboard box prints, top to bottom
//   2. 👆 Manual trigger    — the "InnoDB Cleanup" button
//   3. Buttons & links      — includes two external tools unrelated to the engine
//   4. Stats & health       — the row counts and their green / orange / red labels
//   5. History readout      — the "Last cleanup" line
//
// The cleanup engine and the weekly schedule live in the "Database
// Maintenance" snippet (scope: everywhere). They must stay there: wp-cron.php
// runs with is_admin() === false, so a callback registered from this
// admin-only snippet is invisible to the scheduler.

// --------------------------------------
// 1. WIDGET ASSEMBLY
// --------------------------------------

function custom_render_innodb_cleanup() {
	custom_handle_cleanup_submission();
	custom_render_action_buttons();
	custom_render_database_stats();
	custom_render_cleanup_history();
}

// --------------------------------------
// 2. 👆 MANUAL TRIGGER
// --------------------------------------

// Fires only when the "InnoDB Cleanup" button was actually submitted.
// Without a click this returns immediately and nothing is deleted.
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

// --------------------------------------
// 3. BUTTONS & EXTERNAL TOOLS
// --------------------------------------

// Only the first button runs the engine above. The others are plain links and
// have nothing to do with this cleanup. "Purge All" empties LiteSpeed only;
// the Hostinger CDN in front of it keeps its copy until flushed in hPanel
// (Performance → CDN, via the "Login" link under "Hosting & Code Repos").
function custom_render_action_buttons() {
	echo '<div class="cd-widget cd-flex" style="align-items: center;">';
	echo '<form method="post" class="cd-form">';
	wp_nonce_field('custom_cleanup_action', 'custom_cleanup_nonce');
	echo '<button type="submit" name="er_run_full_cleanup" class="button">InnoDB Cleanup</button>';
	echo '</form>';
	echo '<a href="' . esc_url(admin_url('admin.php?page=litespeed-db_optm')) . '" class="button" target="_blank">LiteSpeed DB</a>';
	$purge = admin_url('index.php?LSCWP_CTRL=purge&LSCWP_NONCE=' . wp_create_nonce('purge') . '&litespeed_type=purge_all');
	echo '<a href="' . esc_url($purge) . '" class="button">Purge All</a>';
	echo '</div>';
}

// --------------------------------------
// 4. STATS & HEALTH
// --------------------------------------

function custom_render_database_stats() {
	global $wpdb;
	$rows = [
		['Content Meta Rows', (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}"), 'meta'],
		['Term Meta Rows',    (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->termmeta}"), 'meta'],
		['User Meta Rows',    (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta}"), 'meta'],
		['Post Stats Rows',   (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_post_stats"), 'meta'],
		['Map View Rows',     (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}er_map_views"), 'map_views'],
	];
	$total = 0;

	echo '<div class="cd-gap">';
	foreach ($rows as [$label, $count, $profile]) {
		$total += $count;
		$status = custom_get_health_status($count, $profile);
		echo '<p style="margin: 5px 0;">' . esc_html($label) . ': <strong>' . number_format_i18n($count) . '</strong> ';
		echo '<span class="' . esc_attr($status[0]) . '">— ' . esc_html($status[1]) . '</span></p>';
	}
	echo '<p style="margin: 5px 0;">TOTAL Meta Rows: <strong>' . number_format_i18n($total) . '</strong></p>';
	echo '</div>';
}

// HEALTH STATUS — the only place the green / orange / red cutoffs live.
// At or below orange = green. Above orange = orange. Above red = red.
//   'meta'      → postmeta, usermeta, termmeta, er_post_stats. Orange 10k, red 50k.
//   'map_views' → er_map_views. Sits at ~11,500 rows normally (~128 visits/day
//                 over 90 days), so its cutoffs are set above that: orange 15k, red 30k.
function custom_get_health_status($count, $profile = 'meta') {
	[$orange, $red] = ($profile === 'map_views') ? [15000, 30000] : [10000, 50000];
	if ($count > $red)    return ['cd-alert',   'Consider running a cleanup.'];
	if ($count > $orange) return ['cd-warning', 'Moderate bloat detected.'];
	return                       ['cd-success', 'Healthy state.'];
}

// --------------------------------------
// 5. HISTORY READOUT
// --------------------------------------

// Written by both triggers, so this line cannot tell you which one ran.
function custom_render_cleanup_history() {
	$last_cleanup = get_option('custom_last_cleanup');
	if (!$last_cleanup) return;

	$last_result  = get_option('custom_last_cleanup_result');
	$last_success = get_option('custom_last_cleanup_success', true);
	echo '<div class="cd-section">';
	if ($last_result) {
		$result_class = $last_success ? 'cd-success' : 'cd-warning';
		echo '<p style="margin: 0 0 5px;" class="' . esc_attr($result_class) . '"><strong>' . esc_html($last_result) . '</strong></p>';
	}
	echo '<p style="margin: 0;">Last cleanup: ' . custom_format_date($last_cleanup, true) . '</p>';
	echo '</div>';
}

// ======================================
// 📰 RSS FEED
// ======================================

// One box, two feeds. Each section pages on its own.
function custom_render_rss_feeds_widget() {
	custom_render_rss_widget('My Blog', home_url('/feed/'));
	custom_render_rss_widget('My Interests', home_url('/my-interests/feed/'), 'cd-gap');
}

// Shared renderer. Prints every item; the JavaScript pages through them.
function custom_render_rss_widget($heading, $feed_url, $class = '') {
	echo '<div class="rss-widget' . ($class ? ' ' . esc_attr($class) : '') . '">';
	echo '<div class="rss-nav" style="display: flex; align-items: center; margin: 0 0 10px;">';
	echo '<span class="cd-subhead" style="margin-right: 8px;">' . esc_html($heading) . '</span><span class="rss-counter cd-muted"></span>';
	echo '</div>';
	echo '<div class="rss-items" style="display: none;">';

	$items = custom_get_rss_items($feed_url, 10);
	if (is_wp_error($items)) {
		echo '<p>Error fetching feed: ' . esc_html($items->get_error_message()) . '</p>';
	} else {
		foreach ($items as $item) {
			$desc = wp_strip_all_tags($item->get_description());
			// Feeds end on a stray trailing word from the "read more" link — drop it.
			$desc      = preg_replace('/\s+\b\w{1,10}\b$/u', '', $desc);
			$excerpt   = $desc ? wp_trim_words($desc, 30) : 'No description available';
			$timestamp = $item->get_date('U');
			$date      = $timestamp ? custom_format_date($timestamp) : '<em class="cd-date">Unknown date</em>';

			$edit_link = '';
			if (preg_match('/p=(\d+)/', $item->get_id(), $matches)) {
				$edit_link = get_edit_post_link((int) $matches[1]);
			}

			echo '<div class="rss-item" style="display: none;">';
			echo '<div><a href="' . esc_url($item->get_link()) . '" target="_blank" class="cd-link">' . esc_html($item->get_title()) . '</a> – ';
			echo '<span class="cd-muted">Published:</span> ' . $date;
			if ($edit_link) {
				echo ' – <a href="' . esc_url($edit_link) . '" target="_blank" class="cd-link">Edit</a>';
			}
			echo '</div>';
			echo '<p style="margin: 5px 0 0;">' . esc_html($excerpt) . '</p>';
			echo '</div>';
		}
	}

	echo '</div>';
	echo '</div>';
}

function custom_get_rss_items($feed_url, $max_items) {
	if (!function_exists('fetch_feed')) {
		require_once ABSPATH . WPINC . '/feed.php';
	}
	$rss = fetch_feed($feed_url);
	if (is_wp_error($rss)) return $rss;
	$rss->set_cache_duration(1800);
	return $rss->get_items(0, $max_items);
}

// ======================================
// INLINE CSS & JAVASCRIPT
// ======================================

add_action('admin_footer', 'custom_dashboard_inline_assets');
function custom_dashboard_inline_assets() {
	$screen = get_current_screen();
	if (!$screen || $screen->id !== 'dashboard') return;

	echo '<style>
		/* === Root Colour Variables === */
		#wpwrap {
			--cd-blue:   #1e73be;
			--cd-red:    #c53030;
			--cd-muted:  #808080;
			--cd-green:  #0c9d58;
			--cd-orange: #e67700;
			--cd-line:   #f0f0f1;
		}
		/* One text size for every widget body, so the boxes match each other. */
		#dashboard-widgets .inside { font-size: 14px; }
		#dashboard-widgets .inside strong { font-weight: bold; }
		#dashboard-widgets a { color: var(--cd-blue); text-decoration: none; }
		#dashboard-widgets a:hover { color: var(--cd-red); }
		/* === Buttons === */
		.cd-widget {
			--cd-btn-bg-top:    #fafbfc;
			--cd-btn-bg-bottom: #e1e8ed;
			--cd-btn-border:    #8da6b9;
			--cd-btn-hover:     #fafbfc;
			--cd-btn-shadow:    rgba(0,0,0,.08);
		}
		#dashboard-widgets .cd-widget .button,
		#dashboard-widgets .cd-widget button.button {
			background: linear-gradient(to bottom, var(--cd-btn-bg-top), var(--cd-btn-bg-bottom));
			border: 1px solid var(--cd-btn-border);
			border-radius: 4px;
			color: var(--cd-blue);
			min-height: 30px;
			padding: 0 12px;
			font-weight: normal;
			line-height: 24px;
			box-shadow: inset 0 1px 0 rgba(255,255,255,.7), 0 1px 2px var(--cd-btn-shadow);
			transition: background .15s ease, border-color .15s ease, box-shadow .15s ease;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 5px;
			cursor: pointer;
		}
		#dashboard-widgets .cd-widget .button:hover,
		#dashboard-widgets .cd-widget button.button:hover {
			color: var(--cd-red);
			background: var(--cd-btn-hover);
			border-color: var(--cd-btn-border);
			box-shadow: inset 0 1px 0 rgba(255,255,255,.8), 0 1px 3px rgba(0,0,0,.12);
		}
		#dashboard-widgets .cd-widget .button:focus:not(:focus-visible),
		#dashboard-widgets .cd-widget button.button:focus:not(:focus-visible) {
			border-color: var(--cd-btn-border);
			box-shadow: inset 0 1px 0 rgba(255,255,255,.7), 0 1px 2px var(--cd-btn-shadow);
			outline: none;
		}
		/* === Utility Classes === */
		.cd-link          { color: var(--cd-blue); text-decoration: none; font-weight: bold; }
		.cd-link:hover    { color: var(--cd-red); }
		.cd-summary       { color: var(--cd-blue); font-weight: bold; cursor: pointer; }
		.cd-summary:hover { color: var(--cd-red); }
		.cd-alert         { color: var(--cd-red); }
		.cd-success       { color: var(--cd-green); }
		.cd-warning       { color: var(--cd-orange); }
		.cd-muted         { color: var(--cd-muted); }
		.cd-bold          { font-weight: bold; }
		.cd-date          { font-size: 12px; font-style: italic; font-weight: 600; }
		.cd-subhead       { color: var(--cd-muted); font-size: 13px; font-weight: bold; }
		.cd-row-label     { min-width: 85px; align-self: center; }
		.cd-gap           { margin-top: 15px; }
		.cd-section       { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--cd-line); }
		/* Same space above and below every line: no extra margins at its edges. */
		.cd-section > :first-child { margin-top: 0 !important; }
		.cd-section > :last-child  { margin-bottom: 0 !important; }
		.rss-items        { flex-direction: column; gap: 15px; }
		/* Fold toggles and RSS buttons use the Dashicons arrows of the box headers. */
		.cd-details > summary { display: flex; align-items: center; gap: 2px; list-style: none; cursor: pointer; }
		.cd-details > summary::-webkit-details-marker { display: none; }
		.cd-details > summary::before { font: 16px/1 dashicons; content: "\f345"; }
		.cd-details[open] > summary::before { content: "\f347"; }
		.cd-arrow-prev::before, .cd-arrow-next::before { font: 16px/1 dashicons; }
		.cd-arrow-prev::before { content: "\f341"; }
		.cd-arrow-next::before { content: "\f345"; }
		.cd-flex          { display: flex; gap: 6px; flex-wrap: wrap; }
		.cd-form          { margin: 0; }
		/* === At a Glance === */
		#dashboard_right_now li a.cd-glance:before { content: none; }
		#dashboard_right_now .cd-glance .dashicons { color: #646970; margin-right: 5px; }
		/* === Widget Header === */
		#dashboard-widgets .postbox-header .hndle {
			display: flex;
			justify-content: flex-start;
			align-items: center;
			gap: 5px;
		}
	</style>';

	echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {

	// RSS Feed sections: paged navigation, 2 items at a time, wrapping at both ends.
	document.querySelectorAll('.rss-widget').forEach(widget => {
		const nav     = widget.querySelector('.rss-nav');
		const list    = widget.querySelector('.rss-items');
		const counter = widget.querySelector('.rss-counter');
		if (!nav || !list) return;

		const items = list.querySelectorAll('.rss-item');
		if (items.length === 0) {
			if (counter) counter.textContent = 'No items found';
			return;
		}

		// Build the back / forward Buttons and put them in the nav Bar.
		// Both stay visible on every Page, so neither can shift under the cursor.
		const group = document.createElement('div');
		group.className = 'cd-widget cd-flex';
		group.style.marginLeft = 'auto';
		const makeBtn = (dir, title) => {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'button cd-arrow-' + dir;
			btn.title = title;
			btn.setAttribute('aria-label', title);
			group.appendChild(btn);
			return btn;
		};
		const prevBtn = makeBtn('prev', 'Previous');
		const nextBtn = makeBtn('next', 'Next');
		nav.appendChild(group);

		const batchSize = 2;
		const lastStart = Math.floor((items.length - 1) / batchSize) * batchSize;
		let currentStart = 0;

		function renderBatch(start) {
			currentStart = start;
			items.forEach((item, i) => {
				item.style.display = (i >= start && i < start + batchSize) ? 'block' : 'none';
			});
			if (counter) {
				const endItem = Math.min(start + batchSize, items.length);
				counter.textContent = 'Showing ' + (start + 1) + '-' + endItem + ' of ' + items.length;
			}
		}

		prevBtn.addEventListener('click', () => {
			const prevStart = currentStart - batchSize;
			renderBatch(prevStart < 0 ? lastStart : prevStart);
		});
		nextBtn.addEventListener('click', () => {
			const nextStart = currentStart + batchSize;
			renderBatch(nextStart >= items.length ? 0 : nextStart);
		});

		renderBatch(0);
		list.style.display = 'flex';
	});

});

// Recent Site Activity: fold / unfold the Likes and Dislikes Lists.
// Runs outside the page-ready wrapper so a folded Row does not flash open first.
// The Widget markup sits above this Script, so the Elements already exist.
// A Row with real Votes opens by itself, but stays folded once it gots folded —
// until a different Post shows up in the List, which changes the signature.
document.querySelectorAll('.cd-toggle').forEach(trigger => {
	const target = document.getElementById(trigger.dataset.target);
	if (!target) return;
	const key = 'cdFolded_' + trigger.dataset.target;
	const signature = target.dataset.signature || '';
	if (signature === '') {
		localStorage.removeItem(key);
	} else if (localStorage.getItem(key) === signature) {
		target.style.display = 'none';
	}
	trigger.addEventListener('click', () => {
		const opening = target.style.display === 'none';
		target.style.display = opening ? 'block' : 'none';
		if (opening) {
			localStorage.removeItem(key);
		} else {
			localStorage.setItem(key, signature);
		}
	});
});
</script>
HTML;
}

// ======================================
// HOOK REGISTRATION
// ======================================

add_action('wp_dashboard_setup', function () {
	if (!current_user_can('manage_options')) return;

	// 📇 At a Glance — Rename Title
	global $wp_meta_boxes;
	if (isset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now'])) {
		$wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']['title'] = '📇 At a Glance';
	}

	// Register all Widgets
	wp_add_dashboard_widget('custom_theme_snapshot',       '🎨 Theme Snapshot',        'custom_render_theme_snapshot_widget'); // THEME RELATED
	wp_add_dashboard_widget('custom_editing_rules',        '📌 Editing Rules',         'custom_render_editing_rules_widget');
	wp_add_dashboard_widget('hosting_code_repo',           '🌀 Hosting & Code Repos',  'custom_render_hosting_repo_widget');
	wp_add_dashboard_widget('quick_links',                 '🔗 Quick Links',           'custom_render_quick_links_widget');
	wp_add_dashboard_widget('custom_activity_alerts',      '🗓️ Recent Site Activity',  'custom_render_activity_widget');
	wp_add_dashboard_widget('custom_analysis_toolkit',     '📊 Analysis Toolkit',      'custom_render_analysis_toolkit');
	wp_add_dashboard_widget('custom_performance',          '🚀 Performance',           'custom_perf_render_widget');
	wp_add_dashboard_widget('custom_optimize_and_cleanup', '🧹 Optimize & Clean-Up',   'custom_render_innodb_cleanup');
	wp_add_dashboard_widget('custom_rss_feeds_widget',     '📰 RSS Feed',              'custom_render_rss_feeds_widget');
});
