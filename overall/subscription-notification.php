<?php

/* Shortcode: [subscribe_form layout="vertical | horizontal"] */
function subscribe_form_shortcode($atts) {
    $atts = shortcode_atts(['layout' => ''], $atts);
    if (!in_array($atts['layout'], ['vertical', 'horizontal'])) {
        return '<p style="color: red;">Error: Please specify layout="vertical" or layout="horizontal" in the shortcode.</p>';
    }
    $is_horizontal = $atts['layout'] === 'horizontal';
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('subscribe_form_action');

    $confirmation_style = '
    <style>
    .subscription-form-wrapper { display: flex; width: 100%; }
    .subscription-form { display: flex; flex-direction: ' . ($is_horizontal ? 'row' : 'column') . '; align-items: flex-start; max-width: 600px; width: 100%; gap: ' . ($is_horizontal ? '10px' : '0') . '; }
    .subscription-form input[type="email"] { width: 100%; max-width: 300px; padding: 8px; }
    .subscription-form button { padding: 8px 12px; white-space: nowrap; min-width: 90px;' . ($is_horizontal ? '' : ' margin-top: 15px;') . ' }
    .subscription-message { border: none; background: none; padding: 0; margin-top: 10px; font-size: 16px; font-family: inherit; }
    .subscription-message.success { color: #339966; }
    .subscription-message.error { color: #c53030; }
    @media (max-width: 600px) {
        .subscription-form { flex-direction: column !important; gap: 0 !important; }
        .subscription-form button { margin-top: 15px !important; width: auto !important; }
    }
    </style>';

    $html = $confirmation_style . '
    <div class="subscription-form-wrapper">
        <form method="post" id="subscription-form" class="subscription-form" novalidate>
            <label for="subscriber_email" class="screen-reader-text">Email address</label>
            <input type="email" id="subscriber_email" name="subscriber_email" required placeholder="Enter your E-mail" autocomplete="email">
            <input type="text" name="contact_time" value="" style="display:none !important;" autocomplete="off" aria-hidden="true" tabindex="-1">
            <input type="hidden" name="middle_name" value="">
            <input type="hidden" name="math_check" value="7">
            <input type="hidden" name="nonce" value="' . esc_attr($nonce) . '">
            <button type="submit" aria-label="Subscribe to email updates">Subscribe</button>
        </form>
    </div>
    <div id="subscription-message" role="alert" aria-live="assertive" tabindex="-1"></div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("subscription-form");
        const messageBox = document.getElementById("subscription-message");

        form.addEventListener("submit", function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const data = new URLSearchParams();
            for (const pair of formData) {
                data.append(pair[0], pair[1]);
            }
            data.append("action", "subscribe_user");

            fetch("' . esc_url($ajax_url) . '", {
                method: "POST",
                credentials: "same-origin",
                body: data
            })
            .then(res => res.json())
            .then(data => {
                messageBox.textContent = data.data.message || "No message from server";
                messageBox.className = data.success ? "subscription-message success" : "subscription-message error";
                messageBox.focus();
                if (data.success) {
                    form.reset();
                }
            })
            .catch(() => {
                messageBox.textContent = "An unexpected error occurred.";
                messageBox.className = "subscription-message error";
                messageBox.focus();
            });
        });
    });
    </script>';

    return $html;
}
add_shortcode('subscribe_form', 'subscribe_form_shortcode');

/* Track IP and User Agent on User Registration */
add_action('user_register', function($user_id) {
    if (!empty($user_id)) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        update_user_meta($user_id, 'registration_ip', sanitize_text_field($ip_address));
        update_user_meta($user_id, 'registration_ua', sanitize_text_field($user_agent));
    }
});

/* AJAX Handler for Subscriptions */
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
        // Send admin notification
        $admin_email = get_option('admin_email');
        $subject = 'New Subscriber Alert';
        $message = "A new subscriber has joined:\n\nEmail: $email\nIP: $ip_address\nUser Agent: $user_agent";
        wp_mail($admin_email, $subject, $message);

        wp_send_json_success(['message' => 'Thank you for subscribing!']);
    } else {
        wp_send_json_error(['message' => 'Subscription failed. Please try again.']);
    }
}
add_action('wp_ajax_nopriv_subscribe_user', 'ajax_subscribe_user');
add_action('wp_ajax_subscribe_user', 'ajax_subscribe_user');

/* Handle Unsubscribe via Token */
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
            wp_die('<h2>You have successfully unsubscribed.</h2><p>You will no longer receive notifications.</p>', 'Unsubscribed', ['response' => 200, 'back_link' => true]);
        } else {
            wp_die('<h2>Unsubscribe Failed</h2><p>We could not find a matching subscription. You may have already unsubscribed.</p>', 'Unsubscribe Error', ['response' => 400, 'back_link' => true]);
        }
    }
}
add_action('init', 'handle_unsubscribe');

/* Notify Subscribers when new Posts are published */
function notify_subscribers_on_new_content($post_ID) {
    global $wpdb;
    $post = get_post($post_ID);
    if (!$post || $post->post_status !== 'publish') return;
    if (strtotime($post->post_date) < strtotime('-1 day')) return;
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
        <html lang="en"><body>
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
