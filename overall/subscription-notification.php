<?php

// ===== CREATE SUBSCRIBERS TABLE TO STORE STUFF =====

add_action('init', function () {
    if (!get_option('subscribers_table_created')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscribers';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            unsubscribe_token varchar(64) NOT NULL,
            subscription_date datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(100),
            user_agent text,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY subscription_date (subscription_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('subscribers_table_created', true);
    }
});

// ===== SHORTCODE: [SUBSCRIBE_FORM LAYOUT="VERTICAL | HORIZONTAL"] =====

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
            <label for="subscriber_email" class="screen-reader-text">Enter your E-mail</label>
            <input type="email" id="subscriber_email" name="subscriber_email" required placeholder="Enter your E-mail" autocomplete="email" aria-label="E-mail Address">
            <input type="hidden" name="contact_time" value="" autocomplete="off">
            <input type="hidden" name="middle_name" value="">
            <input type="hidden" name="math_check" value="">
            <input type="hidden" name="nonce" value="' . esc_attr($nonce) . '">
            <input type="hidden" name="nonce_time" value="' . esc_attr(time()) . '">
            <button type="submit" aria-label="Subscribe to email updates">Subscribe</button>
        </form>
    </div>
    <div id="subscription-message" role="alert" aria-live="assertive" tabindex="-1"></div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("subscription-form");
        const messageBox = document.getElementById("subscription-message");
        const mathField = form.querySelector("[name=math_check]");
        
        // Generate dynamic math challenge: simple addition that equals 7
        const num1 = Math.floor(Math.random() * 4) + 1;
        const num2 = 7 - num1;
        mathField.value = num1 + num2;

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
                    mathField.value = num1 + num2;
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

// ===== TRACK IP AND USER AGENT ON USER REGISTRATION =====

add_action('user_register', function($user_id) {
    if (!empty($user_id)) {
        // Use only REMOTE_ADDR to prevent IP spoofing
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        update_user_meta($user_id, 'registration_ip', sanitize_text_field($ip_address));
        update_user_meta($user_id, 'registration_ua', sanitize_text_field($user_agent));
    }
});

// ===== REUSABLE FUNCTION TO CHECK IF AN E-MAIL IS DISPOSABLE =====

function is_disposable_email($email) {
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    $domain = trim(str_replace(['。', '․', '﹒', '｡'], '.', $domain));
    $parts = explode('.', $domain);
    $tld = array_pop($parts);
    $sld = array_pop($parts);
    $basename = $sld;
    $full_without_tld = implode('.', array_merge($parts, [$sld]));
    // Always enforced Custom List
    $custom_list = [
        "mailinator",
        "guerrillamail",
        "10minutemail",
        "temp-mail",
        "fakeinbox",
        "perevozka24-7",
        "registry.godaddy",
        "bonsoirmail",
        "aurevoirmail",
        "trashmail",
        "getnada",
        "mintemail",
        "dispostable",
        "yopmail",
        "maildrop",
        "moakt",
        "sharklasers",
        "spamgourmet",
        "anonbox",
        "throwawaymail",
        "mailnesia",
        "*.ru",
        "*.su"
    ];
    $disposable_domains = get_transient('disposable_domains_list');
    if ($disposable_domains === false) {
        $remote_sources = [
            'https://raw.githubusercontent.com/disposable/disposable-email-domains/master/domains.txt',
            'https://raw.githubusercontent.com/ivolo/disposable-email-domains/master/index.json',
            'https://raw.githubusercontent.com/FGRibreau/mailchecker/master/list.txt'
        ];
        $fetched = false;
        foreach ($remote_sources as $source) {
            $response = wp_remote_get($source, ['timeout' => 10]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                if (stripos($source, '.json') !== false) {
                    $list = json_decode($body, true);
                    if (is_array($list)) {
                        $disposable_domains = array_map('strtolower', array_map('trim', $list));
                        $fetched = true;
                        break;
                    }
                } else {
                    $disposable_domains = array_filter(array_map('trim', explode("\n", strtolower($body))));
                    $fetched = true;
                    break;
                }
            }
        }
        // If no Remote worked, use only Custom List
        if (!$fetched) {
            $disposable_domains = $custom_list;
        } else {
            // Merge Custom List with Remote List if fetched
            $disposable_domains = array_merge($disposable_domains, $custom_list);
        }
        $disposable_domains = array_values(array_unique($disposable_domains));
        set_transient('disposable_domains_list', $disposable_domains, 12 * HOUR_IN_SECONDS);
    }
    foreach ($disposable_domains as $blocked) {
        if (
            fnmatch($blocked, $domain) ||
            fnmatch($blocked, $full_without_tld) ||
            fnmatch($blocked, $basename)
        ) {
            return true;
        }
    }
    return false;
}

// ===== EMAIL VERIFICATION FUNCTIONS =====

// Basic email domain verification: checks if domain has valid MX records
function verify_email_domain($email) {
    $domain = substr(strrchr($email, "@"), 1);
    if (empty($domain)) {
        return false;
    }
    // Check if domain has MX records
    $mxhosts = [];
    if (getmxrr($domain, $mxhosts)) {
        return true;
    }
    // Fallback: check if domain has A record
    if (checkdnsrr($domain, 'A')) {
        return true;
    }
    return false;
}

// Combined Validation Function: This checks disposable emails + domain verification
function validate_email_before_registration($email) {
    // Check 1: Is it a disposable email service?
    if (is_disposable_email($email)) {
        return ['valid' => false, 'reason' => 'disposable'];
    }
    // Check 2: Is the email format valid?
    if (!is_email($email)) {
        return ['valid' => false, 'reason' => 'invalid_format'];
    }
    // Check 3: Is this email already in your subscribers table?
    global $wpdb;
    $table_name = $wpdb->prefix . "subscribers";
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
    if ($exists) {
        return ['valid' => false, 'reason' => 'already_subscribed'];
    }
    // Check 4: Does the domain have valid MX or A records?
    if (!verify_email_domain($email)) {
        return ['valid' => false, 'reason' => 'domain_invalid'];
    }
    // All checks passed!
    return ['valid' => true, 'reason' => ''];
}

// ===== REGISTRATION ERRORS FILTER (SINGLE HOOK - NO DUPLICATION) =====

add_filter('registration_errors', function($errors, $sanitized_user_login, $user_email) {
    // NEW: Add the combined email validation (this replaces the old disposable-only check)
    $validation = validate_email_before_registration($user_email);
    if (!$validation['valid']) {
        $messages = [
            'disposable' => __('Disposable E-mail addresses are not allowed.'),
            'invalid_format' => __('Invalid E-mail address format.'),
            'already_subscribed' => __("You're already subscribed with this email."),
            'domain_invalid' => __('Email domain could not be verified. Please check the address and try again.')
        ];
        $errors->add('email_validation', $messages[$validation['reason']] ?? 'Email validation failed.');
    }
    return $errors;
}, 10, 3);

// ===== AJAX SUBSCRIBE HANDLER =====

function ajax_subscribe_user() {
    // Use only REMOTE_ADDR to prevent IP spoofing
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $cooldown_key = 'subscribe_form_last_submit_' . md5($ip);
    
    // Check for rate limiting with escalating penalties
    $attempt_count = get_transient($cooldown_key . '_attempts') ?: 0;
    if (get_transient($cooldown_key)) {
        // Increase penalty time with each attempt
        $penalty_time = min(300, 60 * pow(2, $attempt_count)); // Max 5 minutes
        set_transient($cooldown_key . '_attempts', $attempt_count + 1, $penalty_time);
        wp_send_json_error(['message' => 'Please wait before trying again.']);
    }
    
    set_transient($cooldown_key, 1, 60);
    set_transient($cooldown_key . '_attempts', $attempt_count + 1, 300);
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'subscribe_form_action')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    // Verify nonce age (max 12 hours old)
    $nonce_time = isset($_POST['nonce_time']) ? absint($_POST['nonce_time']) : 0;
    if ($nonce_time === 0 || (time() - $nonce_time) > 12 * HOUR_IN_SECONDS) {
        wp_send_json_error(['message' => 'Security token expired. Please refresh the page.']);
    }
    
    $email = isset($_POST['subscriber_email']) ? sanitize_email($_POST['subscriber_email']) : '';
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(['message' => 'Invalid E-mail address.']);
    }
    if (!empty($_POST['contact_time'])) {
        wp_send_json_error(['message' => 'Bot activity detected.']);
    }
    // Verify math check equals 7
    if (!isset($_POST['math_check']) || intval($_POST['math_check']) !== 7) {
        wp_send_json_error(['message' => 'Spam check failed.']);
    }
    // Run the email validation
    $validation = validate_email_before_registration($email);
    if (!$validation['valid']) {
        $messages = [
            'disposable' => 'Disposable E-mail addresses are not allowed.',
            'invalid_format' => 'Invalid E-mail address.',
            'already_subscribed' => "You're already subscribed.",
            'domain_invalid' => 'Email domain could not be verified. Please check the address and try again.'
        ];
        wp_send_json_error(['message' => $messages[$validation['reason']] ?? 'Email validation failed.']);
    }
    global $wpdb;
    $table_name = $wpdb->prefix . "subscribers";
    // Use cryptographically secure random token
    $unsubscribe_token = bin2hex(random_bytes(32));
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $inserted = $wpdb->insert($table_name, [
        'email' => $email,
        'unsubscribe_token' => $unsubscribe_token,
        'ip_address' => sanitize_text_field($ip_address),
        'user_agent' => sanitize_text_field($user_agent)
    ]);
    if ($inserted) {
        // Clear rate limit attempts on success
        delete_transient($cooldown_key . '_attempts');
        wp_send_json_success(['message' => 'Thank you for subscribing!']);
    } else {
        // Handle duplicate email error gracefully (race condition protection)
        if ($wpdb->last_error && strpos($wpdb->last_error, 'Duplicate entry') !== false) {
            wp_send_json_error(['message' => "You're already subscribed."]);
        }
        error_log('Subscription insert failed: ' . $wpdb->last_error);
        wp_send_json_error(['message' => 'Subscription failed. Please try again.']);
    }
}
add_action('wp_ajax_nopriv_subscribe_user', 'ajax_subscribe_user');
add_action('wp_ajax_subscribe_user', 'ajax_subscribe_user');

// ===== UNSUBSCRIBE HANDLER =====

function handle_unsubscribe() {
    if (isset($_GET['unsubscribe'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . "subscribers";
        $token = sanitize_text_field($_GET['unsubscribe']);
        // Validate token format (now 64 hex characters for secure random token)
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
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

// ===== NOTIFY SUBSCRIBERS WHEN NEW POSTS ARE PUBLISHED =====

function notify_subscribers_on_new_content($post_ID) {
    global $wpdb;
    $post = get_post($post_ID);
    if (!$post || $post->post_status !== 'publish') return;
    if (strtotime($post->post_date) < strtotime('-1 day')) return;
    if (get_post_meta($post_ID, 'notification_sent', true)) return;
    update_post_meta($post_ID, 'notification_sent', true);
    $table_name = $wpdb->prefix . "subscribers";
    $subscribers = $wpdb->get_results($wpdb->prepare("SELECT email, unsubscribe_token FROM %i", $table_name));
    if (empty($subscribers)) return;
    $post_type = get_post_type($post_ID);
    $post_type_name = ($post_type === 'post') ? 'New Blog Post' : 'New "My Interests" Post';
    $post_title = get_the_title($post_ID);
    $post_excerpt = get_the_excerpt($post_ID);
    $post_url = get_permalink($post_ID);
    $post_thumbnail_url = get_the_post_thumbnail_url($post_ID, 'medium');
    $subject = sanitize_text_field("$post_type_name: $post_title");
    $from_email = defined('SMTP_FROM') ? SMTP_FROM : '';
    $from_name  = defined('SMTP_FROMNAME') ? SMTP_FROMNAME : '';
    if (empty($from_email) || empty($from_name)) return;
    $headers = [
        "From: $from_name <$from_email>",
        "Reply-To: $from_email",
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];
    $site_url = esc_url(home_url());
    $site_name = get_bloginfo('name');
    
    foreach ($subscribers as $subscriber) {
        // Sanitize email before sending
        $subscriber_email = sanitize_email($subscriber->email);
        if (!is_email($subscriber_email)) {
            error_log("Invalid subscriber email skipped: " . $subscriber->email);
            continue;
        }
        
        $unsubscribe_url = esc_url(add_query_arg('unsubscribe', $subscriber->unsubscribe_token, home_url()));
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
        <p>Thank you for subscribing to <a href="<?php echo $site_url; ?>"><?php echo esc_html($site_name); ?></a></p>
        <p><img src="https://ericroth.org/wp-content/uploads/2024/03/Site-Logo.png" alt="<?php echo esc_attr($site_name); ?> Logo" style="width: 100px; height: auto;"></p>
        <hr>
        <p><a href="<?php echo $unsubscribe_url; ?>">Unsubscribe from notifications</a></p>
        <p style="font-size: 12px; color: #666;">Please do not reply to this email.</p>
        </body></html>
        <?php
        $message = ob_get_clean();
        $sent = wp_mail($subscriber_email, $subject, $message, $headers);
        if (!$sent) {
            error_log("Failed to send notification to: " . $subscriber_email);
        }
    }
}
add_action('publish_post', 'notify_subscribers_on_new_content');
add_action('publish_my-interests', 'notify_subscribers_on_new_content');

?>
