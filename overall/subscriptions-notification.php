<?php 

/* GENERATE THE FORM SHORTCODE ([subscribe_form]) */

function subscribe_form_shortcode($atts) {
    $atts = shortcode_atts([
        'layout' => ''
    ], $atts);

    if (!in_array($atts['layout'], ['vertical', 'horizontal'])) {
        return '<p style="color: red;">Error: Please specify layout="vertical" or layout="horizontal" in the shortcode.</p>';
    }
    $is_horizontal = $atts['layout'] === 'horizontal';
    $ajax_url = admin_url('admin-ajax.php');
    $confirmation_style = '
    <style>
    .subscription-form-wrapper { display: flex; width: 100%; }
    .subscription-form { display: flex; flex-direction: ' . ($is_horizontal ? 'row' : 'column') . '; align-items: flex-start; max-width: 600px; width: 100%; gap: ' . ($is_horizontal ? '10px' : '0') . '; }
    .subscription-form input[type="email"] { width: 100%; max-width: 300px; padding: 8px; }
    .subscription-form button { padding: 8px 12px; white-space: nowrap; min-width: 90px;' . ($is_horizontal ? '' : ' margin-top: 15px;') . ' }
    .subscription-message { border: none; background: none; padding: 0; margin-top: 10px; font-size: 16px; font-family: inherit; }
    .subscription-message.success { color: #2e7d32; }
    .subscription-message.error { color: #c62828; }
    @media (max-width: 600px) {
        .subscription-form { flex-direction: column !important; gap: 0 !important; }
        .subscription-form button { margin-top: 15px !important; width: auto !important; }
    }
    </style>';
    return $confirmation_style . '
    <div class="subscription-form-wrapper">
        <form method="post" id="subscription-form" class="subscription-form" novalidate>
            <label for="subscriber_email" class="screen-reader-text">Email address</label>
            <input type="email" id="subscriber_email" name="subscriber_email" required placeholder="Enter your E-mail" autocomplete="email">
            <input type="text" name="contact_time" value="" style="display:none !important;" autocomplete="off">
            <input type="hidden" name="middle_name" value="">
            <input type="hidden" name="math_check" value="7">
            <input type="hidden" name="nonce" value="' . wp_create_nonce('subscribe_form_action') . '">
            <button type="submit">Subscribe</button>
        </form>
    </div>
    <div id="subscription-message" tabindex="-1" aria-live="polite"></div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById("subscription-form");
            const messageBox = document.getElementById("subscription-message");
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                fetch("' . esc_url($ajax_url) . '", {
                    method: "POST",
                    body: new URLSearchParams({
                        action: "subscribe_user",
                        subscriber_email: formData.get("subscriber_email"),
                        contact_time: formData.get("contact_time"),
                        middle_name: formData.get("middle_name"),
                        math_check: formData.get("math_check"),
                        nonce: formData.get("nonce")
                    })
                })
                .then(res => res.json())
                .then(data => {
                    messageBox.textContent = data.data.message;
                    messageBox.className = data.success ? "success" : "error";
                    messageBox.classList.add("subscription-message");
                    messageBox.focus();
                    if (data.success) form.reset();
                })
                .catch(() => {
                    messageBox.textContent = "An unexpected error occurred.";
                    messageBox.className = "error subscription-message";
                });
            });
        });
    </script>';
}
add_shortcode('subscribe_form', 'subscribe_form_shortcode');

/* STORE IP AND USER AGENT UPON REGISTRATION */

add_action('user_register', function($user_id) {
    if (!empty($user_id)) {
        // Get IP address (check for proxy/real IP)
        $ip_address = $_SERVER['HTTP_CLIENT_IP'] ?? 
                      $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                      $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        // Store in usermeta
        update_user_meta($user_id, 'registration_ip', sanitize_text_field($ip_address));
        update_user_meta($user_id, 'registration_ua', sanitize_text_field($user_agent));
    }
});

/* AJAX HANDLER FOR SUBSCRIPTIONS */

function ajax_subscribe_user() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'subscribe_form_action')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    $email = isset($_POST['subscriber_email']) ? sanitize_email($_POST['subscriber_email']) : '';
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(['message' => 'Invalid E-mail address.']);
    }
    if (!empty($_POST['contact_time'])) {
        wp_send_json_error(['message' => 'Bot activity detected.']);
    }
    if (!isset($_POST['math_check']) || $_POST['math_check'] !== '7') {
        wp_send_json_error(['message' => 'Spam check failed.']);
    }
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    $disposable_domains = ["mailinator.com", "guerrillamail.com", "10minutemail.com", "temp-mail.org", "fakeinbox.com"];
    if (in_array($domain, $disposable_domains)) {
        wp_send_json_error(['message' => 'Disposable E-mail addresses are not allowed.']);
    }
    global $wpdb;
    $table_name = $wpdb->prefix . "subscribers";
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
    if ($exists) {
        wp_send_json_error(['message' => "You're already subscribed."]);
    }
    $unsubscribe_token = md5(uniqid(rand(), true));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $inserted = $wpdb->insert($table_name, [
        'email' => $email,
        'unsubscribe_token' => $unsubscribe_token,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent
    ]);
    if ($inserted) {
        // Notify admin with full details
        $admin_email = get_option('admin_email');
        $timestamp = current_time('mysql');
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $subject = '📬 New Site Subscription Received';
        $message = sprintf(
            "A new site-wide subscription has been recorded.\n\nEmail: %s\nTime: %s\nIP Address: %s\nUser Agent: %s\n\nView subscriber list:\n%s/wp-admin/admin.php?page=subscriber-list",
            $email,
            $timestamp,
            $ip_address,
            $user_agent,
            site_url()
        );
        $headers = [
            'From: Eric Roth <' . $admin_email . '>',
            'Reply-To: ' . $admin_email
        ];
        wp_mail($admin_email, $subject, $message, $headers);
        wp_send_json_success(['message' => 'Thank you for subscribing!']);
    } else {
        wp_send_json_error(['message' => 'Subscription failed. Please try again.']);
    }
}
add_action('wp_ajax_nopriv_subscribe_user', 'ajax_subscribe_user');
add_action('wp_ajax_subscribe_user', 'ajax_subscribe_user');

/* NOTIFY SUBSCRIBERS UPON NEW POSTS */

if (!function_exists('notify_subscribers_on_new_content')) {
    function notify_subscribers_on_new_content($post_ID) {
        global $wpdb;
        $post = get_post($post_ID);
        if (!$post || $post->post_status !== 'publish') return;
        // Only notify if published within the last 24 hours
        if (strtotime($post->post_date) < strtotime('-1 day')) return;
        // Avoid duplicate notifications
        if (get_post_meta($post_ID, 'notification_sent', true)) return;
        update_post_meta($post_ID, 'notification_sent', true);
        $table_name = $wpdb->prefix . "subscribers";
        $subscribers = $wpdb->get_results("SELECT email, unsubscribe_token FROM $table_name");
        if (empty($subscribers)) return;
        $post_type = get_post_type($post_ID);
        $post_type_name = ($post_type === 'post') ? 'New Blog Post' : 'New "My Interests" Post';
        $post_title = get_the_title($post_ID);
        $post_excerpt = get_the_excerpt($post_ID);
        $post_url = get_permalink($post_ID);
        $post_thumbnail_url = get_the_post_thumbnail_url($post_ID, 'medium');
        $subject = sanitize_text_field("$post_type_name: $post_title");
        foreach ($subscribers as $subscriber) {
            $unsubscribe_url = esc_url(site_url("/?unsubscribe=" . $subscriber->unsubscribe_token));
            ob_start();
            ?>
            <html><body>
            <p>Dear Subscriber,</p>
            <p>New content has just been published:</p>
            <?php if ($post_thumbnail_url): ?>
                <a href="<?php echo esc_url($post_url); ?>">
                    <img src="<?php echo esc_url($post_thumbnail_url); ?>" alt="<?php echo esc_attr($post_title); ?>" style="width: 100%; max-width: 300px; height: auto;">
                </a>
            <?php endif; ?>
            <h3><?php echo esc_html($post_title); ?></h3>
            <p><?php echo esc_html($post_excerpt); ?></p>
            <p>Read the full post: <a href="<?php echo esc_url($post_url); ?>"><strong><?php echo esc_html($post_title); ?></strong></a></p>
            <p>Thank you for subscribing to <a href="https://ericroth.org">ericroth.org</a></p>
            <p><img src="https://ericroth.org/wp-content/uploads/2024/03/Site-Logo.png" alt="ericroth.org Logo" style="width: 100px; height: auto;"></p>
            <hr>
            <p><a href="<?php echo $unsubscribe_url; ?>">Unsubscribe from notifications</a></p>
            <p style="font-size: 12px; color: #666;">Please do not reply to this email.</p>
            </body></html>
            <?php
            $message = ob_get_clean();
            $headers = [
                'From: Eric Roth <info@ericroth.org>',
                'Reply-To: info@ericroth.org',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8'
            ];
            $sent = wp_mail($subscriber->email, $subject, $message, $headers);
            if (!$sent) {
                error_log("Failed to send notification to: " . $subscriber->email);
            }
        }
    }
    add_action('publish_post', 'notify_subscribers_on_new_content');
    add_action('publish_my-interests', 'notify_subscribers_on_new_content');
}

/* HANDLE UNSUBSCRIBE */

if (!function_exists('handle_unsubscribe')) {
    function handle_unsubscribe() {
        if (isset($_GET['unsubscribe'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . "subscribers";
            $token = sanitize_text_field($_GET['unsubscribe']);
            if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
                wp_safe_redirect(home_url());
                return;
            }
            $deleted = $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE unsubscribe_token = %s", $token));
            if ($deleted) {
                wp_die(
                    '<h2>You have successfully unsubscribed.</h2><p>You will no longer receive notifications.</p>',
                    'Unsubscribed',
                    ['response' => 200, 'back_link' => true]
                );
            } else {
                wp_die(
                    '<h2>Unsubscribe Failed</h2><p>We could not find a matching subscription. You may have already unsubscribed.</p>',
                    'Unsubscribe Error',
                    ['response' => 400, 'back_link' => true]
                );
            }
        }
    }
    add_action('init', 'handle_unsubscribe');
}

/* CREATE ADMIN MENU TO MANAGE SUBSCRIBERS */

if (!function_exists('add_subscriber_admin_page')) {
    function add_subscriber_admin_page() {
        add_menu_page(
            'Subscribers',
            'Subscribers',
            'manage_options',
            'subscriber-list',
            'display_subscriber_list',
            'dashicons-megaphone',
            20
        );
    }
    add_action('admin_menu', 'add_subscriber_admin_page');
}
if (!function_exists('display_subscriber_list')) {
    function display_subscriber_list() {
        global $wpdb;
        $subscribers_table = $wpdb->prefix . "subscribers";
        $users_table = $wpdb->prefix . "users";
        $usermeta_table = $wpdb->prefix . "usermeta";
        // Bulk delete
        if (isset($_POST['bulk_delete'], $_POST['subscriber_ids'], $_POST['_wpnonce']) && check_admin_referer('subscriber_admin_action', '_wpnonce')) {
            $ids = array_map('sanitize_text_field', $_POST['subscriber_ids']);
            $site_ids = $user_ids = [];
            foreach ($ids as $id) {
                if (strpos($id, 'u_') === 0) {
                    $user_ids[] = intval(substr($id, 2));
                } else {
                    $site_ids[] = intval($id);
                }
            }
            if ($site_ids) {
                $wpdb->query("DELETE FROM $subscribers_table WHERE id IN (" . implode(',', $site_ids) . ")");
            }
            if ($user_ids) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                foreach ($user_ids as $user_id) {
                    wp_delete_user($user_id);
                }
            }
            echo '<div class="updated"><p>Selected subscribers have been deleted.</p></div>';
        }
        // Search & Pagination
        $search_query = isset($_POST['search_email']) ? sanitize_text_field($_POST['search_email']) : '';
        $per_page = 100;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        $order_dir = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
        $order_toggle = ($order_dir === 'ASC') ? 'desc' : 'asc';
        $subscribers = [];
        if (!empty($search_query)) {
            $like = '%' . $wpdb->esc_like($search_query) . '%';
            $site_results = $wpdb->get_results($wpdb->prepare(
                "SELECT id, email, subscription_date, ip_address, user_agent, 'Site' AS source, NULL AS username
                 FROM $subscribers_table
                 WHERE email LIKE %s",
                $like
            ));
            $user_results = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    u.ID AS id,
                    u.user_email AS email,
                    u.user_registered AS subscription_date,
                    ip.meta_value AS ip_address,
                    ua.meta_value AS user_agent,
                    'Forum' AS source,
                    u.user_login AS username
                FROM $users_table u
                LEFT JOIN $usermeta_table ip ON ip.user_id = u.ID AND ip.meta_key = 'registration_ip'
                LEFT JOIN $usermeta_table ua ON ua.user_id = u.ID AND ua.meta_key = 'registration_ua'
                WHERE u.user_email LIKE %s",
                $like
            ));
            $subscribers = array_merge($site_results, $user_results);
        } else {
            $site_results = $wpdb->get_results("
                SELECT id, email, subscription_date, ip_address, user_agent, 'Site' AS source, NULL AS username 
                FROM $subscribers_table
            ");
            $user_results = $wpdb->get_results("
                SELECT 
                    u.ID AS id,
                    u.user_email AS email,
                    u.user_registered AS subscription_date,
                    ip.meta_value AS ip_address,
                    ua.meta_value AS user_agent,
                    'Forum' AS source,
                    u.user_login AS username
                FROM $users_table u
                LEFT JOIN $usermeta_table ip ON ip.user_id = u.ID AND ip.meta_key = 'registration_ip'
                LEFT JOIN $usermeta_table ua ON ua.user_id = u.ID AND ua.meta_key = 'registration_ua'
            ");
            $subscribers = array_merge($site_results, $user_results);
        }
        // Sorting
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'subscription_date';
        usort($subscribers, function($a, $b) use ($order_dir, $orderby) {
            switch ($orderby) {
                case 'username':
                    $valA = strtolower($a->username ?? '');
                    $valB = strtolower($b->username ?? '');
                    break;
                case 'source':
                    $valA = strtolower($a->source ?? '');
                    $valB = strtolower($b->source ?? '');
                    break;
                default:
                    // fallback: sort by subscription date
                    $valA = strtotime($a->subscription_date);
                    $valB = strtotime($b->subscription_date);
            }
            return ($order_dir === 'ASC') ? $valA <=> $valB : $valB <=> $valA;
        });
        $total_subscribers = count($subscribers);
        $subscribers = array_slice($subscribers, $offset, $per_page);
        // Display
        echo '<div class="wrap"><h2>Subscribers</h2>';
        echo '<form method="post">';
        echo '<input type="text" name="search_email" placeholder="Search by email" value="' . esc_attr($search_query) . '"> ';
        echo '<button type="submit" class="button-primary">Search</button>';
        echo '</form><br>';
        if ($subscribers) {
            echo '<form method="post">';
            wp_nonce_field('subscriber_admin_action');
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th><input type="checkbox" onclick="jQuery(\'.sub-check\').prop(\'checked\', this.checked);"></th>';
            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'username', 'order' => $order_toggle])) . '">Username' 
            . ($orderby === 'username' ? ($order_dir === 'ASC' ? ' ↑' : ' ↓') : '') 
            . '</a></th>';
            echo '<th>Email</th>';
            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'source', 'order' => $order_toggle])) . '">Source' 
            . ($orderby === 'source' ? ($order_dir === 'ASC' ? ' ↑' : ' ↓') : '') 
            . '</a></th>';
            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'subscription_date', 'order' => $order_toggle])) . '">Subscription Date' 
            . ($orderby === 'subscription_date' ? ($order_dir === 'ASC' ? ' ↑' : ' ↓') : '') 
            . '</a></th>';
            echo '<th>IP Address</th>';
            echo '<th>User Agent</th>';
            echo '</tr></thead><tbody>';
            foreach ($subscribers as $subscriber) {
                $prefix = ($subscriber->source === 'Forum') ? 'u_' : '';
                echo '<tr>';
                echo '<td><input type="checkbox" class="sub-check" name="subscriber_ids[]" value="' . esc_attr($prefix . $subscriber->id) . '"></td>';
                echo '<td>' . esc_html($subscriber->username ?? '—') . '</td>';
                echo '<td>' . esc_html($subscriber->email) . '</td>';
                echo '<td>' . esc_html($subscriber->source) . '</td>';
                echo '<td>' . esc_html(date('F j, Y H:i', strtotime($subscriber->subscription_date))) . '</td>';
                echo '<td>' . esc_html($subscriber->ip_address ?? '—') . '</td>';
                echo '<td style="max-width: 300px; word-break: break-word;">' . esc_html($subscriber->user_agent ?? '—') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '<button type="submit" name="bulk_delete" class="button-primary">Delete Selected</button>';
            echo '</form>';
            // Pagination
            $total_pages = ceil($total_subscribers / $per_page);
            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                for ($i = 1; $i <= $total_pages; $i++) {
                    $class = ($current_page == $i) ? 'button button-primary' : 'button';
                    echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '" class="' . esc_attr($class) . '">' . $i . '</a> ';
                }
                echo '</div></div>';
            }
        } else {
            echo '<p>No subscribers found.</p>';
        }
        echo '</div>';
    }
}
