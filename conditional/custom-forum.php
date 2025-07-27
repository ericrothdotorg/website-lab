<?php

// FORUM SETUP & STYLES

define('FORUM_PAGE_ID', 140735);

if (!session_id()) {
    session_start();
}

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
});

// TRACK ONLINE USERS

function track_online_user() {
    if (is_admin()) return;
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawler|spider|crawl/i', $_SERVER['HTTP_USER_AGENT'])) return;
    $user_id = is_user_logged_in()
        ? get_current_user_id()
        : 'guest_' . md5($_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    $timeout = 600;
    $active_users = get_transient('forum_active_users') ?: [];
    $active_users[$user_id] = time();
    $active_users = array_filter($active_users, function($timestamp) use ($timeout) {
        return $timestamp > (time() - $timeout);
    });
    set_transient('forum_active_users', $active_users, $timeout);
}
add_action('wp', 'track_online_user');

// SWAP DELETED USERS TO BE UNKNOWN USERS

function get_forum_author_name($user_id) {
    $user = get_userdata($user_id);
    return $user ? $user->display_name : 'Unknown User';
}

// ADD CSS STYLING

function get_custom_forum_css() {
    return <<<CSS

    /* Layout & Containers */
    .forum-wrapper {margin-bottom: 3rem;}
    .forum-section {width: 100%; margin-bottom: 1rem;}
    .forum-category {margin-top: 2rem;}
    .forum-bar {display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; margin: 2rem 0; padding-top: 25px;}
    .forum-breadcrumbs {margin-top: 1rem !important; font-size: 1rem;}
    .forum-topic {background: #f4f7fa; border: 1px solid #3A4F66; padding: 1rem; border-radius: 6px; margin-bottom: 2rem;}
    .forum-reply-item {border-top: 1px dashed #3A4F66; padding-top: 0.75rem; margin-top: 1rem; background: #f2f5f7; border-radius: 4px;}

    /* Grid & Cards */
    .subforum-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 1rem;}
    .subforum-card {background: #e1e8ed; border: 1px solid #3A4F66; padding: 1rem; border-radius: 6px; height: 100%;}
    .subforum-card-flex {display: flex; gap: 0.75rem; align-items: flex-start;}
    .subforum-icon {font-size: 3rem; color: #3A4F66; padding-right: 25px; flex-shrink: 0;}
    .subforum-card-content {flex: 1;}
    .subforum-card h5 {margin: 0 0 0.5rem 0; font-size: 1.1rem; line-height: 1.3;}
    .subforum-card p {margin-bottom: 0.5rem;}
    .subforum-card small {margin-top: 0.5rem; display: block;}
    .subforum-card:hover {box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); border-color: #305473;}

    /* Typography & Text */
    .forum-topic h4 {margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.4rem;}
    .forum-topic small {display: block;}
    .forum-meta-info {margin-top: 2.5rem; display: block;}
    .moderation-links {margin-top: 1rem;}
    .moderation-links a {margin-right: 0.5rem;}
    #qt_content_toolbar .ed_button,
    div[id^="qt_reply_content_"][id$="_toolbar"] .ed_button,
    div[id^="qt_edit_"][id$="_toolbar"] .ed_button {background-color: #e1e8ed; border: none; color: #192a3d !important;}

    /* Forms & Fields */
    .forum-new-topic, .forum-reply, .forum-edit-form {background: #e1e8ed; border: 1px solid #3A4F66; padding: 1rem; border-radius: 6px; margin: 2rem 0;}
    .forum-new-topic input, .forum-edit-form input, .forum-reply textarea, .forum-new-topic select, .forum-edit-form select, .forum-edit-form textarea {width: 100%; margin-bottom: 0.5rem; padding: 0.5rem;}
    .forum-new-topic label {display: inline-flex; align-items: center; gap: 0.4rem; margin-right: 1rem;}
    .forum-new-topic input[type="checkbox"] {margin: 0;}
    .forum-new-topic select[name="parent_id"], .forum-edit-form select[name="parent_id"] {display: block; width: 100%; max-width: 350px; margin-bottom: 1rem;}
    .forum-new-topic input[name="title"], .forum-edit-form input[name="title"] {display: block; width: 100%; max-width: 700px; margin-bottom: 1rem; background-color: #ffffff; border: 1px solid #ccc;}
    .forum-new-topic select, .forum-edit-form select {background-color: #ffffff; border: 1px solid #ccc;}
    .forum-new-topic textarea, .forum-edit-form textarea, .forum-reply textarea {background-color: #ffffff !important; border: 1px solid #ccc; border-radius: 4px;}
    .forum-reply .hidden {margin-top: 1rem;}
    .forum-title-tags-row {display: flex; gap: 1rem; flex-wrap: wrap;}
    .forum-title-tags-row input[name="title"], .forum-title-tags-row input[name="tags"] {flex: 1 1 0; background-color: #ffffff; border: 1px solid #ccc; padding: 0.5rem; border-radius: 4px; margin-bottom: 1rem; height: 2.5rem; line-height: 1.2; max-width: 100%;}
    .forum-title-tags-row input[name="title"]:focus, .forum-title-tags-row input[name="tags"]:focus {background-color: #ffffff; border: 1px solid #ccc;}

    /* Controls & Buttons */
    .forum-actions-row {display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; align-items: center; justify-content: flex-end;}
    .forum-checkbox {display: flex; align-items: center;}
    .forum-checkbox label {display: flex; align-items: center; gap: 0.4rem; margin: 0; white-space: nowrap;}
    .forum-actions {display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;}
    .forum-actions label {display: flex; align-items: center; gap: 0.4rem; margin-left: auto; justify-content: flex-start; width: auto; white-space: nowrap;}
    .forum-button, .forum-search .forum-button {background-color: #1e73be; border: none; color: #fff; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; transition: background 0.2s ease; width: 100%; max-width: 125px; margin-bottom: 0.75rem;}
    .forum-button:hover {background-color: #c53030;}
    .forum-button-row {display: flex; gap: 1rem; margin-top: 1rem;}
    .forum-edit-form button {margin-top: 1rem;}
    .forum-reply button {margin-top: 1.5rem;}
    .forum-button.open-reply {margin-top: 1.5rem;}

    /* Search */
    .forum-search {margin: 1rem 0; display: flex; flex-direction: column; gap: 0.75rem; flex: 1; min-width: 260px;}
    .forum-search input[type="text"], .forum-search input[name="search"] {display: block; width: 100%; max-width: 350px; padding: 0.4rem;}

    /* Statistics */
    .forum-stats {flex: 2; min-width: 300px; max-width: 750px; background: #f2f5f7; padding: 1rem; border-radius: 6px; border: 1px solid #3A4F66;}
    .forum-stats h5 {margin-bottom: 1rem; font-size: 1.25rem;}
    .forum-stats ul {display: grid; grid-template-columns: repeat(5, auto); gap: 1rem 1.5rem; padding: 0; margin: 0; list-style: none; align-items: center; justify-content: flex-start;}
    .forum-stats li {font-size: 1rem; white-space: nowrap; margin: 0;}

    /* Pagination */
    .forum-pagination {display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 2rem;}
    .forum-page-link {padding: 0.4rem 0.75rem; border: 1px solid #3A4F66; border-radius: 4px;}
    .forum-page-link:hover {background: none;}
    .forum-page-link.active {font-weight: bold;}

    /* Private Messaging */
    .private-messaging summary {font-size: 1.1rem;}
    .forum-recipient-select {display: block; width: 100%; max-width: 350px; background-color: #ffffff; border: 1px solid #ccc; padding: 0.5rem; border-radius: 4px;}
    .private-message-form textarea {max-width: 750px; width: 100%; box-sizing: border-box; background-color: #ffffff; border: 1px solid #ccc; padding: 0.5rem; border-radius: 4px;}
    .private-message-form textarea:focus {background-color: #ffffff; border: 1px solid #ccc;}


    /* Honeypot */
    .bot-trap {position: absolute; left: -9999px; height: 0; width: 0; opacity: 0; pointer-events: none;}

    /* Responsive Rules */
    @media screen and (max-width: 768px) {
    .subforum-icon {font-size: 1.5rem; padding-right: 5px;}
    .forum-new-topic input, .forum-edit-form input, .forum-reply textarea, .forum-new-topic select, .forum-edit-form select, .forum-edit-form textarea, .forum-search input[type="text"], .forum-search input[name="search"], .forum-recipient-select {width: 100%; max-width: none; margin-bottom: 0.75rem;}
    .forum-title-tags-row {flex-direction: column;}
    .forum-title-tags-row input[name="title"], .forum-title-tags-row input[name="tags"] {margin-bottom: 0.5rem;}
    .forum-search {display: block;}
    .forum-pagination a {display: inline-block; margin: 0.3rem 0.3rem 0 0;}
    .moderation-links a {display: inline-block; margin-top: 0.3rem;}
    .forum-actions-row {flex-direction: column; align-items: flex-end; justify-content: flex-end;}
    .forum-actions {flex-direction: column; gap: 0.75rem;}
    }
    @media screen and (min-width: 768px) {
    .forum-new-topic select[name="parent_id"], .forum-edit-form select[name="parent_id"] {max-width: 350px;}
    .forum-new-topic input[name="title"], .forum-edit-form input[name="title"] {max-width: 700px;}
    .forum-search input[name="search"], .forum-search input[type="text"] {max-width: 350px;}
    }
    @media screen and (max-width: 1200px) {
    .forum-stats ul {grid-template-columns: repeat(2, minmax(0, 1fr));}
    }

CSS;
}

function enqueue_inline_forum_styles() {
    if (!is_page(FORUM_PAGE_ID)) return;
    wp_register_style('custom-forum-inline-style', false);
    wp_enqueue_style('custom-forum-inline-style');
    wp_add_inline_style('custom-forum-inline-style', get_custom_forum_css());
}
add_action('wp_enqueue_scripts', 'enqueue_inline_forum_styles');

// SUBMIT & REPLY (incl. NOTIFICATIONS)

function handle_submit_forum_reply() {
    check_ajax_referer('submit_reply_nonce');
    if (get_current_user_id() === 0) {
        wp_send_json_error('Please log in to reply.');
    }
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    $content   = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
    if (!$parent_id || empty($content)) {
        wp_send_json_error('Missing reply content or target.');
    }
    global $wpdb;
    $wpdb->insert('wp_custom_forum', [
        'post_type'  => 'reply',
        'user_id'    => get_current_user_id(),
        'content'    => $content,
        'parent_id'  => $parent_id,
        'created_at' => current_time('mysql', 1)
    ]);
    $forum_info = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_custom_forum WHERE id = %d", $parent_id));
    if ($forum_info && $forum_info->user_id != get_current_user_id()) {
        $author = get_userdata($forum_info->user_id);
        if ($author && !empty($author->user_email)) {
            $replier = wp_get_current_user();
            $subject = 'New reply to your topic: ' . $forum_info->title;
            $message = sprintf(
                "Hi %s,\n\n%s has replied to your topic \"%s\".\n\nView it here: %s#topic_%d\n\nReply:\n%s",
                $author->display_name,
                $replier->display_name,
                $forum_info->title,
                get_permalink(FORUM_PAGE_ID),
                $forum_info->id,
                $content
            );
            wp_mail($author->user_email, $subject, $message);
        }
    }
    $subscribers = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id FROM wp_custom_forum_subscriptions WHERE topic_id = %d",
        $forum_info->id
    ));
    foreach ($subscribers as $sub) {
        $subscriber_id = intval($sub->user_id);
        if ($subscriber_id === get_current_user_id() || $subscriber_id === intval($forum_info->user_id)) {
            continue; // Skips the replier and author to avoid duplicates
        }
        $subscriber = get_userdata($subscriber_id);
        if ($subscriber && !empty($subscriber->user_email)) {
            $replier = wp_get_current_user();
            $subject = 'New reply to a topic you subscribed to: ' . $forum_info->title;
            $message = sprintf(
                "Hi %s,\n\n%s has replied to the topic \"%s\" that you subscribed to.\n\nView it here: %s#topic_%d\n\nReply:\n%s",
                $subscriber->display_name,
                $replier->display_name,
                $forum_info->title,
                get_permalink(FORUM_PAGE_ID),
                $forum_info->id,
                $content
            );
            wp_mail($subscriber->user_email, $subject, $message);
        }
    }
    wp_send_json_success('Reply posted.');
}
add_action('wp_ajax_submit_forum_reply', 'handle_submit_forum_reply');
add_action('wp_ajax_nopriv_submit_forum_reply', 'handle_submit_forum_reply');

// RENDER CUSTOM FORUM

function render_custom_forum() {

    if (!is_page(FORUM_PAGE_ID)) {
        return '';
    }
    ob_start();

    if (!session_id()) session_start();
    $_SESSION['honeypot_fields'] = [
        'topic' => [
            'name' => 'trap_' . wp_rand(1000, 9999),
            'start' => time()
        ],
        'reply' => [
            'name' => 'trap_' . wp_rand(1000, 9999),
            'start' => time()
        ],
        'pm' => [
            'name' => 'trap_' . wp_rand(1000, 9999),
            'start' => time()
        ]
    ];

    echo '<section id="custom-forum-wrapper">';

    global $wpdb, $post, $current_user;
    $active_slug = isset($_GET['cat']) ? sanitize_title($_GET['cat']) : '';
    if ($active_slug) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM wp_custom_forum_structure WHERE slug = %s",
            $active_slug
        ));
        if (!$exists) {
            wp_die(esc_html('Invalid forum category.'));
        }
    }
    $search      = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $edit_id     = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $structure = $wpdb->get_results("SELECT * FROM wp_custom_forum_structure ORDER BY name ASC");
    $forums = [];
    foreach ($structure as $item) {
        $forums[$item->parent_id][] = $item;
    }
    $active_structure = $active_slug
        ? $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM wp_custom_forum_structure WHERE slug = %s",
            $active_slug
        ))
        : null;
    $active_tag = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';
    $matching_subforums = [];
    if ($active_tag) {
        $tagged_topics = $wpdb->get_results($wpdb->prepare(
            "SELECT parent_id FROM wp_custom_forum WHERE post_type = 'topic' AND tags LIKE %s",
            '%' . $wpdb->esc_like($active_tag) . '%'
        ));
        foreach ($tagged_topics as $t) {
            if (!empty($t->parent_id)) {
                $matching_subforums[$t->parent_id] = true;
            }
        }
    }

    // Forum Intro

    echo '<p>Select a subforum below to choose where your topic will appear. Each subforum covers a different area of discussion.';
    if (get_current_user_id() === 0) {
        echo '<strong><i class="fas fa-sign-in-alt" style="margin-left: 6px; margin-right: 8px;"></i>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> or <a href="' . site_url('wp-login.php?action=register') . '">register</a> to post or reply.</strong>';
    }
    echo '</p>';

    // Sticky Logic
    if (isset($_GET['sticky'])) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, only the forum admin can pin topics.', 'your-text-domain'));
        }
        $sticky_id = intval($_GET['sticky']);
        $nonce     = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sticky_post_' . $sticky_id)) {
            exit;
        }
        $wpdb->update('wp_custom_forum', ['is_sticky' => 1], ['id' => $sticky_id]);
        wp_redirect(remove_query_arg('sticky'));
        exit;
    }

    // Topic Creation Form
        if (get_current_user_id() > 0) {
        if ($error = get_transient('forum_topic_error')) {
        echo '<p style="color: red;">' . esc_html($error) . '</p>';
        delete_transient('forum_topic_error');
        }
        echo '<form method="POST" class="forum-new-topic">';
        echo '<input type="hidden" name="forum_action" value="new_topic">';
        echo wp_nonce_field('new_topic_action', 'forum_nonce');
        echo '<input type="text" name="' . esc_attr($_SESSION['honeypot_fields']['topic']['name']) . '" class="bot-trap" autocomplete="off">';
        echo '<select name="parent_id">';
        echo '<option value="" disabled selected>Select a Subforum</option>';
        foreach ($forums[NULL] as $forum) {
            echo '<optgroup label="' . esc_html($forum->name) . '">';
            foreach ($forums[$forum->id] ?? [] as $sub) {
                $selected = ($sub->slug === $active_slug) ? 'selected' : '';
                echo '<option value="' . $sub->id . '" ' . $selected . '>' . esc_html($sub->name) . '</option>';
            }
            echo '</optgroup>';
        }
        echo '</select>';
        echo '<div class="forum-title-tags-row">';
        echo '<input type="text" name="title" placeholder="Topic title" required>';
        echo '<input type="text" name="tags" placeholder="Tags (comma-separated)">';
        echo '</div>';
        ob_start();
        wp_editor('', 'content', [
            'textarea_name' => 'content',
            'media_buttons' => false,
            'textarea_rows' => 10,
            'teeny' => true,
            'quicktags' => true
        ]);
        echo ob_get_clean();
        echo '<div class="forum-actions">';
        echo '<div class="forum-actions-row">';
        echo '<div class="forum-checkbox"><label><input type="checkbox" name="is_private"> Make this topic private</label></div>';
        echo '<div class="forum-checkbox"><label><input type="checkbox" name="subscribe_topic"> Subscribe to this topic</label></div>';
        echo '</div>';
        echo '<button type="submit" class="forum-button" aria-label="' . esc_attr('Post new topic') . '">Post Topic</button>';
        echo '</div>';
        echo '</form>';
    }

    // Create Topic
    $forum_action = isset($_POST['forum_action']) ? sanitize_text_field($_POST['forum_action']) : '';
    if ($forum_action === 'new_topic' && get_current_user_id() > 0) {
        $forum_nonce = isset($_POST['forum_nonce']) ? sanitize_text_field($_POST['forum_nonce']) : '';
        $trap = $_SESSION['honeypot_fields']['topic'] ?? [];
        if (!empty($trap['name']) && !empty($_POST[$trap['name']])) {
            exit; // Bot detected: honeypot filled
        }
        if (!empty($trap['start']) && (time() - $trap['start'] < 5)) {
            exit; // Bot detected: submitted too fast
        }
        if (!wp_verify_nonce($forum_nonce, 'new_topic_action')) {
            return;
        }
        $title   = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        if (empty($title) || empty($content)) {
            set_transient('forum_topic_error', 'Title and content are required.', 30);
            $inserted_id = $wpdb->insert_id;
            unset($_SESSION['honeypot_fields']['topic']);
            wp_redirect(add_query_arg(null, null) . '#topic_' . $inserted_id);
            exit;
        }
        $parent_id  = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $tags       = sanitize_text_field($_POST['tags'] ?? '');
        $is_private = isset($_POST['is_private']) ? 1 : 0;
        $wpdb->insert('wp_custom_forum', [
            'post_type'   => 'topic',
            'user_id'     => get_current_user_id(),
            'title'       => $title,
            'content'     => $content,
            'parent_id'   => $parent_id,
            'tags'        => $tags,
            'is_private'  => $is_private
        ]);
        $inserted_id = $wpdb->insert_id;
        if (isset($_POST['subscribe_topic'])) {
            $wpdb->insert('wp_custom_forum_subscriptions', [
                'user_id'  => get_current_user_id(),
                'topic_id' => $inserted_id
            ]);
        }
        wp_redirect(add_query_arg(null, null) . '#topic_' . $inserted_id);
        exit;
    }

    // Create Reply
    $forum_action = isset($_POST['forum_action']) ? sanitize_text_field($_POST['forum_action']) : '';
    if ($forum_action === 'new_reply' && get_current_user_id() > 0) {
        $forum_nonce = isset($_POST['forum_nonce']) ? sanitize_text_field($_POST['forum_nonce']) : '';
        $trap = $_SESSION['honeypot_fields']['reply'] ?? [];
        if (!empty($trap['name']) && !empty($_POST[$trap['name']])) {
            exit; // Bot detected: honeypot filled
        }
        if (!empty($trap['start']) && (time() - $trap['start'] < 5)) {
            exit; // Bot detected: submitted too fast
        }
        if (!wp_verify_nonce($forum_nonce, 'new_reply_action')) {
            return;
        }
        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $content_key = 'content_' . $topic->id;
        $reply_content = isset($_POST[$content_key]) ? wp_kses_post(wp_unslash($_POST[$content_key])) : '';
        if (empty($reply_content)) {
            set_transient('forum_reply_error_' . $topic->id, 'Reply content cannot be empty.', 30);
            unset($_SESSION['honeypot_fields']['reply']);
            wp_redirect(add_query_arg(null, null) . '#topic_' . $parent_id);
            exit;
        }
        $wpdb->insert('wp_custom_forum', [
            'post_type' => 'reply',
            'user_id'   => get_current_user_id(),
            'content'   => $reply_content,
            'parent_id' => $parent_id
        ]);
        unset($_SESSION['honeypot_name']);
        $inserted_id = $wpdb->insert_id;
        wp_redirect(add_query_arg(null, null) . '#topic_' . $parent_id);
        exit;
    }

    // Delete Topic
    if (isset($_GET['delete']) && get_current_user_id() > 0) {
        $target_id = intval($_GET['delete']);
        $nonce     = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'delete_post_' . $target_id)) {
            exit;
        }
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM wp_custom_forum WHERE id = %d",
            $target_id
        ));
        if ($post && ($post->user_id == get_current_user_id() || current_user_can('manage_options'))) {
            if ($post->post_type === 'topic') {
                $wpdb->delete('wp_custom_forum', [
                    'parent_id' => $target_id,
                    'post_type' => 'reply'
                ]);
            } elseif ($post->post_type === 'reply') {
                do_action('forum_reply_deleted', $target_id);
            }
            $wpdb->delete('wp_custom_forum', ['id' => $target_id]);
            wp_redirect(remove_query_arg('delete'));
            exit;
        }
    }

    // Update Topic
    $forum_action = isset($_POST['forum_action']) ? sanitize_text_field($_POST['forum_action']) : '';
    if ($forum_action === 'update_post' && get_current_user_id() > 0) {
        $forum_nonce = isset($_POST['forum_nonce']) ? sanitize_text_field($_POST['forum_nonce']) : '';
        if (!wp_verify_nonce($forum_nonce, 'update_post_action')) {
            return;
        }
        $update_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $title   = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
        if (empty($title) || empty($content)) {
            set_transient('forum_update_error_' . $update_id, 'Title and content are required.', 30);
            wp_redirect(add_query_arg(['edit' => $update_id], get_permalink()));
            exit;
        }
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_custom_forum WHERE id = %d", $update_id));
        if ($existing && ($existing->user_id == get_current_user_id() || current_user_can('manage_options'))) {
            $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
            $allowed_tags = wp_kses_allowed_html('post');
            $safe_content = wp_kses($content, $allowed_tags);
            $wpdb->update('wp_custom_forum', [
                'title'     => $title,
                'content'   => $safe_content,
                'parent_id' => $parent_id
            ], ['id' => $update_id]);
            wp_redirect(remove_query_arg('edit') . '#topic_' . $update_id);
            exit;
        }
    }

    // Solve Topic
    if (isset($_GET['solve']) && get_current_user_id() > 0) {
        $solve_id = intval($_GET['solve']);
        $nonce    = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'solve_post_' . $solve_id)) {
            exit;
        }
        $post = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM wp_custom_forum WHERE id = %d",
            $solve_id
        ));
        if ($post && ($post->user_id == get_current_user_id() || current_user_can('manage_options'))) {
            $wpdb->update('wp_custom_forum', ['is_solved' => 1], ['id' => $solve_id]);
            wp_redirect(remove_query_arg('solve'));
            exit;
        }
    }

    // Forum Bar: Search Form and Statistics Block
    echo '<div class="forum-bar">';

    // Search Form
    echo '<form method="GET" class="forum-search">';
    echo '<input type="text" name="search" placeholder="Search topics..." value="' . esc_attr($search) . '" />';
    if ($active_slug) echo '<input type="hidden" name="cat" value="' . esc_attr($active_slug) . '" />';
    echo '<button type="submit" class="forum-button" aria-label="' . esc_attr('Search topics') . '">Search</button>';
    echo '</form>';

    // Statistics Block
    $stats = get_transient('forum_stats_block');
    if (!$stats) {
        $active_users = get_transient('forum_active_users') ?: [];
        $current_online = count($active_users);
        $sticky_topic = $wpdb->get_row("
            SELECT * FROM wp_custom_forum 
            WHERE is_sticky = 1 AND post_type = 'topic' 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stats = [
            'subforums'       => $wpdb->get_var("SELECT COUNT(*) FROM wp_custom_forum_structure WHERE parent_id IS NOT NULL AND parent_id != 0"),
            'topics'          => $wpdb->get_var("SELECT COUNT(*) FROM wp_custom_forum WHERE post_type = 'topic'"),
            'replies'         => $wpdb->get_var("SELECT COUNT(*) FROM wp_custom_forum WHERE post_type = 'reply'"),
            'views'           => $wpdb->get_var("SELECT SUM(views) FROM wp_custom_forum WHERE post_type = 'topic'"),
            'active_users'    => $active_users,
            'current_online'  => $current_online,
            'sticky_topic'    => $sticky_topic,
        ];
        set_transient('forum_stats_block', $stats, 60); // Cache for 1 minute
    }
    $total_subforums  = $stats['subforums'];
    $total_topics     = $stats['topics'];
    $total_replies    = $stats['replies'];
    $total_views      = $stats['views'];
    $total_posts      = $total_topics + $total_replies;
    $active_users     = $stats['active_users'];
    $current_online   = $stats['current_online'];
    $sticky_topic     = $stats['sticky_topic'];
    echo '<div class="forum-stats">';
    echo '<h4>Forum Information</h4>';
    echo '<ul>';
    if ($sticky_topic) {
        $structure = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM wp_custom_forum_structure WHERE id = %d",
            $sticky_topic->parent_id
        ));
        $sticky_url = add_query_arg(['cat' => $structure->slug], get_permalink()) . '#topic_' . $sticky_topic->id;
        echo '<li>📌 <a href="' . esc_url($sticky_url) . '">' . esc_html($sticky_topic->title) . '</a></li>';
    } else {
        echo '<li>📌 Sticky Topic: <em>None pinned</em></li>';
    }
    echo '<li>🗂️ Subforums: <strong>' . intval($total_subforums) . '</strong></li>';
    echo '<li>📂 Topics: <strong>' . intval($total_topics) . '</strong></li>';
    echo '<li>💬 Replies: <strong>' . intval($total_replies) . '</strong></li>';
    echo '<li>📝 Posts: <strong>' . intval($total_posts) . '</strong></li>';
    echo '<li>👁️ Views: <strong>' . intval($total_views) . '</strong></li>';
    echo '<li>🟢 Currently Online: <strong>' . $current_online . '</strong></li>';
    echo '</ul>';
    echo '</div>';

    echo '</div>'; // End .forum-bar

    // Forum Directory
    if (!$active_structure || $active_tag) {
        echo '<h4>Forum Directory</h4>';
        foreach ($forums[NULL] as $forum) {
            $subforums = $forums[$forum->id] ?? [];
            $filtered_subs = [];
            foreach ($subforums as $sub) {
                if (!$active_tag || isset($matching_subforums[$sub->id])) {
                    $filtered_subs[] = $sub;
                }
            }
            if (empty($filtered_subs)) continue;
            echo '<div class="forum-section">';
            echo '<h5>' . esc_html($forum->name) . '</h5>';
            if (!empty($forum->description)) {
                echo '<p class="forum-description">' . esc_html(wp_unslash($forum->description)) . '</p>';
            }
            echo '<div class="subforum-grid">';
            $first_card = true;
            foreach ($filtered_subs as $sub) {
                // Cache Topic Count
                $topic_cache_key = 'forum_topic_count_' . $sub->id;
                $topic_count = get_transient($topic_cache_key);
                if ($topic_count === false) {
                    $topic_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM wp_custom_forum WHERE post_type = 'topic' AND parent_id = %d",
                        $sub->id
                    ));
                    set_transient($topic_cache_key, $topic_count, 300); // 5 minutes
                }
                // Cache Reply Count
                $reply_cache_key = 'forum_reply_count_' . $sub->id;
                $reply_count = get_transient($reply_cache_key);
                if ($reply_count === false) {
                    $reply_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM wp_custom_forum WHERE post_type = 'reply' AND parent_id IN (
                            SELECT id FROM wp_custom_forum WHERE post_type = 'topic' AND parent_id = %d
                        )",
                        $sub->id
                    ));
                    set_transient($reply_cache_key, $reply_count, 300); // 5 minutes
                }
                echo '<div class="subforum-card"' . ($first_card ? ' id="forum-directory"' : '') . '>';
                $first_card = false;
                echo '<div class="subforum-card-flex">';
                echo '<i class="' . esc_attr($sub->icon_class ?: 'fas fa-comments') . ' subforum-icon"></i>';
                echo '<div class="subforum-card-content">';
                echo '<h5><a href="?cat=' . esc_attr($sub->slug) . '">' . esc_html($sub->name) . '</a></h5>';
                if (!empty($sub->description)) {
                    echo '<p>' . esc_html(wp_unslash($sub->description)) . '</p>';
                }
                echo '<small><strong>' . intval($topic_count) . '</strong> Topics • <strong>' . intval($reply_count) . '</strong> Replies</small>';
                echo '</div>'; // .subforum-card-content
                echo '</div>'; // .subforum-card-flex
                echo '</div>'; // .subforum-card
            }
            echo '</div>'; // .subforum-grid
            echo '</div>'; // .forum-section
        }
        // Private Messaging (Always last Subforum Card)
        echo render_private_messaging_card();
        echo '<div style="margin-bottom: 3.5rem;"></div>';
    }

    // Topic Listing Loop
    if ($active_structure || $active_tag) {
        $structure_id = intval($active_structure->id);
        $per_page     = 10;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $per_page;
        $search_sql = $search ? '%' . $wpdb->esc_like($search) . '%' : '';
        if ($search || $active_tag) {
            $conditions = ['post_type = %s', 'parent_id = %d'];
            $params = ['topic', $structure_id];
            if ($search) {
                $conditions[] = 'title LIKE %s';
                $params[] = '%' . $wpdb->esc_like($search) . '%';
            }
            if ($active_tag) {
                $conditions[] = 'tags LIKE %s';
                $params[] = '%' . $wpdb->esc_like($active_tag) . '%';
            }
            $where_sql = implode(' AND ', $conditions);
            $params_with_limits = array_merge($params, [$per_page, $offset]);
            $sql = "SELECT * FROM wp_custom_forum WHERE $where_sql ORDER BY is_sticky DESC, created_at DESC LIMIT %d OFFSET %d";
            $topics = $wpdb->get_results(call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $params_with_limits)));
            $matching_subforums = [];
            if (!empty($topics)) {
                foreach ($topics as $topic) {
                    if (!empty($topic->parent_id)) {
                        $matching_subforums[$topic->parent_id] = true;
                    }
                }
            }
            $count_sql = "SELECT COUNT(*) FROM wp_custom_forum WHERE $where_sql";
            $total = $wpdb->get_var(call_user_func_array([$wpdb, 'prepare'], array_merge([$count_sql], $params)));
        } else {
            $topics = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM wp_custom_forum 
                WHERE post_type = 'topic' AND parent_id = %d 
                ORDER BY is_sticky DESC, created_at DESC 
                LIMIT %d OFFSET %d",
                $structure_id, $per_page, $offset
            ));
            $total = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM wp_custom_forum 
                WHERE post_type = 'topic' AND parent_id = %d",
                $structure_id
            ));
        }
        $matching_subforums = [];
        if (!empty($topics)) {
            foreach ($topics as $topic) {
                if (!empty($topic->parent_id)) {
                    $matching_subforums[$topic->parent_id] = true;
                }
            }
        }
        if ($edit_id && !array_filter($topics, fn($t) => intval($t->id) === $edit_id)) {
            $edited_topic = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_custom_forum WHERE id = %d AND post_type = 'topic'", $edit_id));
            if ($edited_topic) {
                $topics[] = $edited_topic;
            }
        }
        echo '<div class="forum-category">';
        if ($active_structure) {
            echo '<h4 id="forum-category-header">' . esc_html($active_structure->name) . ' <span>(' . intval($total) . ' topics)</span></h4>';
            if (!empty($active_structure->description)) {
                echo '<p class="forum-description">' . esc_html(wp_unslash($active_structure->description)) . '</p>';
            }
            if (empty($topics)) {
                echo '<p style="font-style: italic;">No topics found for this subforum or search.';
                if (is_user_logged_in()) {
                    echo ' You can be the first to post in this subforum!';
                }
                echo '</p>';
            }
        }
        foreach ($topics as $topic) {
            if ($topic->is_private && get_current_user_id() !== intval($topic->user_id)) continue;
            $view_key = 'forum_view_' . $topic->id . '_' . get_current_user_id();
            if (!get_transient($view_key)) {
                $wpdb->query($wpdb->prepare("UPDATE wp_custom_forum SET views = views + 1 WHERE id = %d", $topic->id));
                set_transient($view_key, true, 300); // 5 minutes
            }
            $author = get_userdata($topic->user_id);
            $reply_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM wp_custom_forum WHERE post_type = 'reply' AND parent_id = %d", $topic->id));
            $last_reply = $wpdb->get_var($wpdb->prepare("SELECT MAX(created_at) FROM wp_custom_forum WHERE post_type = 'reply' AND parent_id = %d", $topic->id));
            echo '<div class="forum-topic" id="topic_' . $topic->id . '">';
            $is_subscribed = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM wp_custom_forum_subscriptions WHERE user_id = %d AND topic_id = %d",
                get_current_user_id(), $topic->id
            ));
            echo '<h4>';
            if ($topic->is_sticky) echo '📌 ';
            echo esc_html(wp_unslash($topic->title));
            if ($is_subscribed) echo ' 🔔';
            if ($topic->is_solved) echo ' ✅';
            echo '</h4>';
            echo '<div class="forum-full-content">' . wp_kses_post(wp_unslash($topic->content)) . '</div>';
            $author_name = $author ? $author->display_name : 'Unknown User';
            echo '<small class="forum-meta-info">' . esc_html($author_name) . ' · ' . date('F j, Y', strtotime($topic->created_at)) . ' · ' . intval($reply_count) . ' replies</small>';
            if (!empty($topic->tags)) {
                $tag_list = explode(',', $topic->tags);
                echo '<small class="forum-tags">Tags: ';
                foreach ($tag_list as $tag) {
                    $tag = trim($tag);
                    echo '<a href="' . add_query_arg(['tag' => urlencode($tag)], get_permalink()) . '" style="margin-right: 0.5rem;">' . esc_html($tag) . '</a>';
                }
                echo '</small>';
            }

            // Moderation and Solve Buttons
            if (get_current_user_id() === intval($topic->user_id) || current_user_can('manage_options') || is_user_logged_in()) {
                echo '<div class="moderation-links">';
                if (get_current_user_id() === intval($topic->user_id) || current_user_can('manage_options')) {
                    $edit_url = wp_nonce_url(add_query_arg(['edit' => $topic->id], get_permalink()), 'edit_post_' . $topic->id);
                    echo '<a href="' . esc_url($edit_url) . '#topic_' . esc_attr($topic->id) . '">✏️ Edit</a>';
                    $delete_url = wp_nonce_url(add_query_arg('delete', $topic->id, get_permalink()), 'delete_post_' . $topic->id);
                    echo '<a href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js('Delete this topic?') . '\')">🗑️ Delete</a>';
                    $solve_url = wp_nonce_url(add_query_arg(['solve' => $topic->id], get_permalink()), 'solve_post_' . $topic->id);
                    echo '<a href="' . esc_url($solve_url) . '">✅ Mark as Solved</a>';
                }
                if (current_user_can('manage_options')) {
                    $sticky_url = wp_nonce_url(add_query_arg(['sticky' => $topic->id], get_permalink()), 'sticky_post_' . $topic->id);
                    echo '<a href="' . esc_url($sticky_url) . '">📌 Pin Topic</a>';
                }
                if (is_user_logged_in()) {
                    $is_subscribed = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM wp_custom_forum_subscriptions WHERE user_id = %d AND topic_id = %d",
                        get_current_user_id(), $topic->id
                    ));
                    $subscribe_url = wp_nonce_url(add_query_arg(['subscribe' => $topic->id], get_permalink()), 'subscribe_topic_' . $topic->id);
                    $unsubscribe_url = wp_nonce_url(add_query_arg(['unsubscribe' => $topic->id], get_permalink()), 'unsubscribe_topic_' . $topic->id);
                    if ($is_subscribed) {
                        echo '<a href="' . esc_url($unsubscribe_url) . '">🔕 Unsubscribe</a>';
                    } else {
                        echo '<a href="' . esc_url($subscribe_url) . '">🔔 Subscribe</a>';
                    }
                }
                echo '</div>';
            }

            // Inline Edit Form (if editing this topic)
            if ($edit_id === intval($topic->id)) {
                echo '<form method="POST" class="forum-edit-form">';
                echo '<input type="hidden" name="forum_action" value="update_post">';
                echo wp_nonce_field('update_post_action', 'forum_nonce');
                echo '<input type="hidden" name="post_id" value="' . $topic->id . '">';
                echo '<label for="title_' . $topic->id . '"><strong>Title:</strong></label>';
                echo '<input id="title_' . $topic->id . '" type="text" name="title" value="' . esc_attr($topic->title) . '" required>';
                echo '<label><strong>Content:</strong></label>';
                ob_start();
                wp_editor($topic->content, 'edit_' . $topic->id, [
                    'textarea_name' => 'content',
                    'media_buttons' => false,
                    'textarea_rows' => 10,
                    'teeny' => true,
                    'quicktags' => true
                ]);
                echo ob_get_clean();
                echo '<label><strong>Subforum:</strong></label>';
                echo '<select name="parent_id">';
                foreach ($forums[NULL] as $forum) {
                    echo '<optgroup label="' . esc_html($forum->name) . '">';
                    foreach ($forums[$forum->id] ?? [] as $sub) {
                        $selected = ($sub->id === $topic->parent_id) ? 'selected' : '';
                        echo '<option value="' . $sub->id . '" ' . $selected . '>' . esc_html($sub->name) . '</option>';
                    }
                    echo '</optgroup>';
                }
                echo '</select>';
                echo '<button type="submit" class="forum-button" aria-label="' . esc_attr('Update topic') . '">Update</button>';
                echo '</form>';
            }

            // Reply Form
            echo '<button class="forum-button open-reply" data-topic="' . $topic->id . '" aria-label="' . esc_attr('Toggle reply form for topic') . '">Reply</button>';
            echo '<div id="reply_form_' . $topic->id . '" class="forum-reply hidden">';
            if (is_user_logged_in()) {
                if (!session_id()) {
                    session_start();
                }
                echo '<form method="POST">';
                echo '<input type="hidden" name="forum_action" value="new_reply">';
                echo wp_nonce_field('new_reply_action', 'forum_nonce');
                echo '<input type="text" name="' . esc_attr($_SESSION['honeypot_fields']['reply']['name']) . '" class="bot-trap" autocomplete="off">';
                echo '<input type="hidden" name="parent_id" value="' . $topic->id . '">';
                ob_start();
                wp_editor('', 'reply_content_' . $topic->id, [
                    'textarea_name' => 'content_' . $topic->id,
                    'media_buttons' => false,
                    'textarea_rows' => 10,
                    'teeny' => false,
                    'quicktags' => true
                ]);
                echo ob_get_clean();
                echo '<div class="forum-button-row">';
                echo '<button type="submit" class="forum-button" aria-label="' . esc_attr('Submit reply to topic') . '">Reply</button>';
                echo '<button type="button" class="forum-button cancel-reply" onclick="document.getElementById(\'reply_form_' . $topic->id . '\').classList.add(\'hidden\')" aria-label="' . esc_attr('Cancel reply to topic') . '">Cancel</button>';
                echo '</div>';
                echo '</form>';
            }
            echo '</div>';

            // Replies
            if (!$topic->is_private || get_current_user_id() === intval($topic->user_id) || current_user_can('manage_options')) {
                $replies_per_page = 10;
                $reply_page_key = 'reply_page_' . $topic->id;
                $reply_page = isset($_GET[$reply_page_key]) ? max(1, intval($_GET[$reply_page_key])) : 1;
                $reply_offset = ($reply_page - 1) * $replies_per_page;
                $replies = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM wp_custom_forum WHERE post_type = 'reply' AND parent_id = %d ORDER BY created_at ASC LIMIT %d OFFSET %d",
                    $topic->id, $replies_per_page, $reply_offset
                ));
                $total_replies = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM wp_custom_forum WHERE post_type = 'reply' AND parent_id = %d",
                    $topic->id
                ));
                $total_reply_pages = ceil($total_replies / $replies_per_page);
                foreach ($replies as $reply) {
                    $user = get_userdata($reply->user_id);
                    $avatar = $user ? get_avatar($user->ID, 40) : get_avatar(0, 40);
                    $author_name = $user ? $user->display_name : 'Unknown User';
                    echo '<div class="forum-reply-item">';
                    echo '<div style="display: flex; gap: 0.8rem;">';
                    echo '<div>' . $avatar . '</div>';
                    echo '<div>';
                    echo '<p>' . wp_kses_post($reply->content) . '</p>';
                    echo '<small><strong>' . esc_html($author_name) . '</strong> replied on ' . date('F j, Y', strtotime($reply->created_at)) . '</small>';
                    if ($reply->user_id == get_current_user_id() || current_user_can('manage_options')) {
                        echo '<div class="moderation-links">';
                        echo '<a href="' . wp_nonce_url('?delete=' . $reply->id, 'delete_post_' . $reply->id) . '" onclick="return confirm(\'Delete this reply?\')">Delete</a>';
                        echo '</div>';
                    }
                    echo '</div></div></div>';
                }
                if ($total_reply_pages > 1) {
                    echo '<nav class="forum-pagination" aria-label="Reply pagination">';
                    for ($i = 1; $i <= $total_reply_pages; $i++) {
                        $is_active = ($reply_page === $i);
                        $url = add_query_arg('reply_page_' . $topic->id, $i, get_permalink());
                        echo '<a href="' . esc_url($url . '#topic_' . $topic->id) . '" class="forum-page-link' . ($is_active ? ' active' : '') . '"' . ($is_active ? ' aria-current="page"' : '') . '>' . $i . '</a> ';
                    }
                    echo '</nav>';
                }
            } else {
                echo '<p style="font-style: italic;">Replies are private and only visible to the topic author.</p>';
            }
                if ($last_reply) {
                    echo '<small>Last reply: ' . date('F j, Y', strtotime($last_reply)) . '</small>';
                }
            echo '</div>'; // Close forum-topic
        }
        echo '</div>'; // Close forum-category

        // Pagination
        $total_pages = ceil($total / $per_page);
        if ($total_pages > 1) {
            echo '<nav class="forum-pagination" aria-label="Forum pagination">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $is_active = ($page === $i);
                $url = add_query_arg(['page' => $i], get_permalink());
                if ($active_slug) $url = add_query_arg(['cat' => $active_slug], $url);
                if ($search) $url = add_query_arg(['search' => $search], $url);
                echo '<a href="' . esc_url($url) . '" class="forum-page-link' . ($is_active ? ' active' : '') . '"' . ($is_active ? ' aria-current="page"' : '') . '>' . $i . '</a> ';
            }
            echo '</nav>';
        }
        // Private Messaging (Always last Subforum Card)
        echo render_private_messaging_card();
    }

    // AJAX Handler for Replies
    $ajax_url = esc_url(admin_url('admin-ajax.php'));
    $reply_nonce = wp_create_nonce('submit_reply_nonce');

    echo '<script>
        /* ----------------------------------------
            ForumAjax: Global Config for AJAX
        ---------------------------------------- */
        var ForumAjax = {
            ajaxurl: "' . $ajax_url . '",
            nonce: "' . $reply_nonce . '"
        };
        /* ----------------------------------------
            Scroll to Hash Target on Page Load
        ---------------------------------------- */
        function scrollToHashTarget() {
            const urlParams = new URLSearchParams(window.location.search);
            const hasTag = urlParams.has("tag");
            const hasCat = urlParams.has("cat");
            let targetSelector = null;
            let offset = 100;
            if (hasTag) {
                targetSelector = "#forum-directory";
                offset = 160;
            } else if (hasCat) {
                targetSelector = "#forum-category-header";
                offset = 100;
            } else if (window.location.hash) {
                targetSelector = window.location.hash;
                offset = 100;
            }
            if (targetSelector) {
                const target = document.querySelector(targetSelector);
                if (target) {
                    const topPos = target.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top: topPos, behavior: "smooth" });
                }
            }
        }
        /* ----------------------------------------
            Toggle Reply Form Visibility
        ---------------------------------------- */
        function setupReplyToggle() {
            document.querySelectorAll(".open-reply").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    const topicId = btn.dataset.topic;
                    document.querySelectorAll(".forum-reply").forEach(function (el) {
                        el.classList.add("hidden");
                    });
                    const formDiv = document.getElementById("reply_form_" + topicId);
                    if (formDiv) {
                        formDiv.classList.remove("hidden");
                        if (!formDiv.querySelector(".cancel-reply")) {
                            const cancelBtn = document.createElement("button");
                            cancelBtn.textContent = "Cancel";
                            cancelBtn.className = "forum-button cancel-reply";
                            cancelBtn.style.marginTop = "1rem";
                            cancelBtn.addEventListener("click", function () {
                                formDiv.classList.add("hidden");
                            });
                            formDiv.appendChild(cancelBtn);
                        }
                    }
                });
            });
        }
        /* ----------------------------------------
            Handle AJAX Reply Submission
        ---------------------------------------- */
        function setupAjaxReplySubmission() {
            document.querySelectorAll(".forum-reply form").forEach(function (form) {
                form.addEventListener("submit", function (e) {
                    e.preventDefault();
                    const parentField = form.querySelector(\'[name="parent_id"]\');
                    const parentId = parentField ? parentField.value : "";
                    if (!parentId) {
                        alert("Missing topic ID.");
                        return;
                    }
                    const textarea = form.querySelector("textarea");
                    const editorId = textarea ? textarea.name : "";
                    const editor = tinymce.get(editorId);
                    const content = editor ? editor.getContent() : "";
                    if (!content.trim()) {
                        alert("Reply content is empty.");
                        return;
                    }
                    const payload = {
                        action: "submit_forum_reply",
                        _ajax_nonce: ForumAjax.nonce,
                        parent_id: parentId,
                        content: content
                    };
                    fetch(ForumAjax.ajaxurl, {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: new URLSearchParams(payload).toString()
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            location.href = window.location.pathname + "#topic_" + parentId;
                        } else {
                            alert(result.data || "Reply failed.");
                        }
                    })
                    .catch(error => {
                        console.error("AJAX error:", error);
                        alert("An error occurred while submitting your reply.");
                    });
                });
            });
        }
        /* ----------------------------------------
            Toggle Private Messaging Visibility
        ---------------------------------------- */
        function togglePrivateMessaging() {
            const container = document.getElementById("private-messaging-container");
            container.style.display = (container.style.display === "none") ? "block" : "none";
        }
        /* ----------------------------------------
            Initialize all Forum Scripts
        ---------------------------------------- */
        document.addEventListener("DOMContentLoaded", function () {
            scrollToHashTarget();
            setupReplyToggle();
            setupAjaxReplySubmission();
        });
    </script>';

    echo '</section>';

        // Breadcrumbs
        if ($active_structure || $active_tag) {
            echo '<nav class="forum-breadcrumbs" aria-label="Forum breadcrumbs">';
            echo '<a href="' . esc_url(get_permalink()) . '">Site Forum</a>';
            if ($active_structure) {
                echo ' &raquo; <a href="' . esc_url(add_query_arg(['cat' => $active_structure->slug], get_permalink())) . '">' . esc_html($active_structure->name) . '</a>';
            } elseif ($active_tag) {
                echo ' &raquo; <a href="' . esc_url(add_query_arg(['tag' => urlencode($active_tag)], get_permalink())) . '">Tag: ' . esc_html($active_tag) . '</a>';
            }
            echo '</nav>';
        }

    return ob_get_clean();
}
add_shortcode('custom_forum', 'render_custom_forum');

// HANDLE SUBSCRIPTIONS

function handle_forum_subscriptions() {
    if (!is_user_logged_in()) return;
    global $wpdb;
    $user_id = get_current_user_id();
    // Handle POST subscribe
    $subscribe_topic = isset($_POST['subscribe_topic']) ? intval($_POST['subscribe_topic']) : 0;
    $subscribe_nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
    if ($subscribe_topic && wp_verify_nonce($subscribe_nonce, 'subscribe_topic_' . $subscribe_topic)) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM wp_custom_forum_subscriptions WHERE user_id = %d AND topic_id = %d",
            $user_id, $subscribe_topic
        ));
        if (!$exists) {
            $wpdb->insert('wp_custom_forum_subscriptions', [
                'user_id'  => $user_id,
                'topic_id' => $subscribe_topic
            ]);
            // Notify admin
            $admin_email = get_option('admin_email');
            $user = get_userdata($user_id);
            $topic = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM wp_custom_forum WHERE id = %d",
                $subscribe_topic
            ));
            if ($user && $topic && !empty($admin_email)) {
                $subject = '📌 New Forum Topic Subscription';
                // Add diagnostic metadata
                $timestamp = date('Y-m-d H:i:s');
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                // Build detailed message
                $message = sprintf(
                    "A new topic subscription has been recorded.\n\nUser: %s\nEmail: %s\nTime: %s\nIP Address: %s\nUser Agent: %s\n\nTopic: \"%s\"\nView topic:\n%s#topic_%d",
                    $user->display_name,
                    $user->user_email,
                    $timestamp,
                    $ip_address,
                    $user_agent,
                    $topic->title,
                    get_permalink(FORUM_PAGE_ID),
                    $topic->id
                );
                // Email headers
                $headers = [
                    'From: Eric Roth <' . $admin_email . '>',
                    'Reply-To: ' . $admin_email
                ];
                // Send notification
                wp_mail($admin_email, $subject, $message, $headers);
            }
        }
        wp_safe_redirect(add_query_arg(null, null) . '#topic_' . $subscribe_topic);
        exit;
    }
    // Handle POST unsubscribe
    $unsubscribe_topic = isset($_POST['unsubscribe_topic']) ? intval($_POST['unsubscribe_topic']) : 0;
    $unsubscribe_nonce = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';
    if ($unsubscribe_topic && wp_verify_nonce($unsubscribe_nonce, 'unsubscribe_topic_' . $unsubscribe_topic)) {
        $wpdb->delete('wp_custom_forum_subscriptions', [
            'user_id'  => $user_id,
            'topic_id' => $unsubscribe_topic
        ]);
        wp_safe_redirect(add_query_arg(null, null) . '#topic_' . $unsubscribe_topic);
        exit;
    }
    // Handle GET subscribe
    $subscribe_get       = isset($_GET['subscribe']) ? intval($_GET['subscribe']) : 0;
    $subscribe_get_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
    // Bot protection: check user agent and referer
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer    = $_SERVER['HTTP_REFERER'] ?? '';
    if (preg_match('/bot|crawl|spider|slurp|crawler/i', $user_agent)) return;
    if (empty($referer) || strpos($referer, home_url()) === false) return;
    if ($subscribe_get && wp_verify_nonce($subscribe_nonce, 'subscribe_topic_' . $subscribe_get)) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM wp_custom_forum_subscriptions WHERE user_id = %d AND topic_id = %d",
            $user_id, $subscribe_get
        ));
        if (!$exists) {
            $wpdb->insert('wp_custom_forum_subscriptions', [
                'user_id'  => $user_id,
                'topic_id' => $subscribe_get
            ]);
            // Notify admin
            $admin_email = get_option('admin_email');
            $user = get_userdata($user_id);
            $topic = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM wp_custom_forum WHERE id = %d",
                $subscribe_get
            ));
            if ($user && $topic && !empty($admin_email)) {
                $subject = '📌 New Forum Topic Subscription';
                // Add diagnostic metadata
                $timestamp = date('Y-m-d H:i:s');
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                // Build detailed message
                $message = sprintf(
                    "A new topic subscription has been recorded.\n\nUser: %s\nEmail: %s\nTime: %s\nIP Address: %s\nUser Agent: %s\n\nTopic: \"%s\"\nView topic:\n%s#topic_%d",
                    $user->display_name,
                    $user->user_email,
                    $timestamp,
                    $ip_address,
                    $user_agent,
                    $topic->title,
                    get_permalink(FORUM_PAGE_ID),
                    $topic->id
                );
                // Email headers
                $headers = [
                    'From: Eric Roth <' . $admin_email . '>',
                    'Reply-To: ' . $admin_email
                ];
                // Send notification
                wp_mail($admin_email, $subject, $message, $headers);
            }
        }
        wp_safe_redirect(remove_query_arg(['subscribe', '_wpnonce']) . '#topic_' . $subscribe_get);
        exit;
    }
    // Handle GET unsubscribe
    $unsubscribe_get       = isset($_GET['unsubscribe']) ? intval($_GET['unsubscribe']) : 0;
    $unsubscribe_get_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
    if ($unsubscribe_get && wp_verify_nonce($unsubscribe_get_nonce, 'unsubscribe_topic_' . $unsubscribe_get)) {
        $wpdb->delete('wp_custom_forum_subscriptions', [
            'user_id'  => $user_id,
            'topic_id' => $unsubscribe_get
        ]);
        wp_safe_redirect(remove_query_arg(['unsubscribe', '_wpnonce']));
        exit;
    }
}
add_action('wp', 'handle_forum_subscriptions');

// PRIVATE MESSAGING

function handle_forum_message_submission() {
    if (!is_user_logged_in()) return;
    $nonce = isset($_POST['forum_message_nonce']) ? sanitize_text_field($_POST['forum_message_nonce']) : '';
    $trap = $_SESSION['honeypot_fields']['pm'] ?? [];
    if (!empty($trap['name']) && !empty($_POST[$trap['name']])) {
        return; // Bot detected: honeypot filled
    }
    if (!empty($trap['start']) && (time() - $trap['start'] < 5)) {
        return; // Bot detected: submitted too fast
    }
    if (!$nonce || !wp_verify_nonce($nonce, 'send_forum_message')) return;
    global $wpdb;
    $sender_id    = get_current_user_id();
    $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
    $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
    if ($recipient_id && !empty($message)) {
        $wpdb->insert($wpdb->prefix . 'forum_messages', [
            'sender_id'    => $sender_id,
            'recipient_id' => $recipient_id,
            'message'      => $message
        ]);
        unset($_SESSION['pm_honeypot_name']);
    }
}
add_action('wp', 'handle_forum_message_submission');

function render_private_messaging_card() {
    if (!is_user_logged_in()) {
        return '<div class="forum-section"><p>Please log in to view your messages.</p></div>';
    }
    global $wpdb;
    $current_user_id = get_current_user_id();
    $forum_sub_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}custom_forum_subscriptions");
    $site_sub_emails = $wpdb->get_col("SELECT DISTINCT email FROM {$wpdb->prefix}subscribers");
    $site_sub_ids = [];
    foreach ($site_sub_emails as $email) {
        $email = sanitize_email(strtolower($email));
        $user = get_user_by('email', $email);
        if ($user && strtolower($user->user_email) === $email) {
            $site_sub_ids[] = intval($user->ID);
        }
    }
    $valid_user_ids = array_unique(array_merge($forum_sub_ids, $site_sub_ids));
    $valid_user_ids = array_diff($valid_user_ids, [$current_user_id]);
    $users = get_users([
        'include' => $valid_user_ids
    ]);
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}forum_messages WHERE recipient_id = %d ORDER BY sent_at DESC",
        $current_user_id
    ));
    ob_start();
    echo '<div class="forum-section">';
    echo '<h5>Private Messaging</h5>';
    echo '<div class="subforum-grid">';
    echo '<div class="subforum-card">';
    echo '<div class="subforum-card-flex">';
    echo '<i class="fas fa-envelope subforum-icon"></i>';
    echo '<div class="subforum-card-content">';
    echo '<h5><a href="javascript:void(0);" onclick="togglePrivateMessaging()">Private Messaging</a></h5>';
    echo '<p>Welcome to the private messaging hub! If you would like to connect with other forum members, check your inbox or send a message, this is the place to do it. Please log in first to access and use these features.</p>';
    echo '<small>Login Required</small>';
    echo '<div id="private-messaging-container" style="display:none; margin-top:1rem;">';
    echo '<h6>Your Inbox</h6>';
    if ($messages) {
        foreach ($messages as $msg) {
            $sender = get_userdata($msg->sender_id);
            echo '<div style="border: 1px solid #ccc; padding: 1rem; margin-bottom: 1rem;">';
            echo '<strong>From:</strong> ' . esc_html($sender->display_name) . '<br>';
            echo '<strong>Sent:</strong> ' . esc_html($msg->sent_at) . '<br>';
            echo '<p>' . esc_html($msg->message) . '</p>';
            echo '</div>';
        }
    } else {
        echo '<p>No messages yet.</p>';
    }
    echo '<h6>Send a Message</h6>';
    echo '<form method="POST" class="private-message-form">';
    echo wp_nonce_field('send_forum_message', 'forum_message_nonce');
    echo '<input type="text" name="' . esc_attr($_SESSION['honeypot_fields']['pm']['name']) . '" class="bot-trap" autocomplete="off">';
    echo '<select name="recipient_id" class="forum-recipient-select" required>';
    echo '<option value="">Select recipient</option>';
    foreach ($users as $user) {
        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
    }
    echo '</select><br>';
    echo '<textarea name="message" rows="5" cols="50" placeholder="Your message..." required></textarea><br>';
    echo '<button type="submit">Send</button>';
    echo '</form>';
    echo '</div>'; // #private-messaging-container
    echo '</div>'; // .subforum-card-content
    echo '</div>'; // .subforum-card-flex
    echo '</div>'; // .subforum-card
    echo '</div>'; // .subforum-grid
    echo '</div>'; // .forum-section
    unset($_SESSION['honeypot_fields']['pm']);
    return ob_get_clean();
}

// UNSUBSCRIBE LOGIC

add_action('wp_footer', function() {
    if (isset($_GET['unsubscribed'])) {
        $message = '';
        $type = '';
        if ($_GET['unsubscribed'] === '1') {
            $message = 'You have successfully unsubscribed from the forum topic.';
            $type = 'success';
        } elseif ($_GET['unsubscribed'] === 'not_logged_in') {
            $message = 'You must be logged in to unsubscribe from a forum topic.';
            $type = 'error';
        }
        if ($message) {
            $bg = ($type === 'success') ? '#dff0d8' : '#f2dede';
            $color = ($type === 'success') ? '#3c763d' : '#a94442';
            echo '<div class="notice ' . esc_attr($type) . '" style="text-align:center; padding:10px; background:' . esc_attr($bg) . '; color:' . esc_attr($color) . ';">
                    ' . esc_html($message) . '
                  </div>';
        }
    }
});
