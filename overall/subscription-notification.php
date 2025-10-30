<?php

// ===== SHORTCODE: [SUBSCRIBE_FORM LAYOUT="VERTICAL | HORIZONTAL"] =====

function subscribe_form_shortcode($atts) {
    $atts = shortcode_atts(['layout' => ''], $atts);
    if (!in_array($atts['layout'], ['vertical', 'horizontal'])) {
        return '<p style="color: red;">Error: Please specify layout="vertical" or layout="horizontal" in the shortcode.</p>';
    }
    
    // Check if user is already logged in
    $current_user = wp_get_current_user();
    $is_logged_in = $current_user->ID > 0;
    
    if ($is_logged_in) {
        $subscription_type = get_user_meta($current_user->ID, 'subscription_type', true);
        $is_unsubscribed = get_user_meta($current_user->ID, 'email_unsubscribed', true);
        
        // If already subscribed to site-wide or both
        if (in_array($subscription_type, ['sitewide', 'both']) && !$is_unsubscribed) {
            return '<p style="color: #339966;">✓ You are already subscribed to site-wide notifications!</p>';
        }
        
        // If forum user or unsubscribed, show upgrade option
        if ($subscription_type === 'forum' || $is_unsubscribed) {
            $ajax_url = admin_url('admin-ajax.php');
            $nonce = wp_create_nonce('upgrade_subscription_action');
            
            $message = $is_unsubscribed ? 
                'You previously unsubscribed. Would you like to re-subscribe to site-wide notifications?' : 
                'You have a forum account. Subscribe to site-wide notifications too?';
            
            return '
            <div id="upgrade-subscription-wrapper">
                <p>' . esc_html($message) . '</p>
                <button id="upgrade-subscription-btn" style="padding: 8px 12px; cursor: pointer;">Subscribe to Site Updates</button>
                <div id="upgrade-message" role="alert" aria-live="assertive"></div>
            </div>
            <script>
            document.getElementById("upgrade-subscription-btn").addEventListener("click", function() {
                const btn = this;
                btn.disabled = true;
                btn.textContent = "Processing...";
                
                fetch("' . esc_url($ajax_url) . '", {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "action=upgrade_subscription&nonce=' . esc_attr($nonce) . '"
                })
                .then(res => res.json())
                .then(data => {
                    const msg = document.getElementById("upgrade-message");
                    msg.textContent = data.data.message || "Done!";
                    msg.style.color = data.success ? "#339966" : "#c53030";
                    msg.style.marginTop = "10px";
                    if (data.success) {
                        btn.style.display = "none";
                    } else {
                        btn.disabled = false;
                        btn.textContent = "Subscribe to Site Updates";
                    }
                });
            });
            </script>';
        }
    }
    
    // Default: Show subscription form for non-logged-in users or users without any subscription
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
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        update_user_meta($user_id, 'registration_ip', sanitize_text_field($ip_address));
        update_user_meta($user_id, 'registration_ua', sanitize_text_field($user_agent));
        
        // If user registered through forum, mark as forum subscriber
        if (!get_user_meta($user_id, 'subscription_type', true)) {
            update_user_meta($user_id, 'subscription_type', 'forum');
        }
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
    
    $custom_list = [
        "mailinator", "guerrillamail", "10minutemail", "temp-mail", "fakeinbox",
        "perevozka24-7", "registry.godaddy", "bonsoirmail", "aurevoirmail",
        "trashmail", "getnada", "mintemail", "dispostable", "yopmail",
        "maildrop", "moakt", "sharklasers", "spamgourmet", "anonbox",
        "throwawaymail", "mailnesia", "*.ru", "*.su"
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
        if (!$fetched) {
            $disposable_domains = $custom_list;
        } else {
            $disposable_domains = array_merge($disposable_domains, $custom_list);
        }
        $disposable_domains = array_values(array_unique($disposable_domains));
        set_transient('disposable_domains_list', $disposable_domains, 12 * HOUR_IN_SECONDS);
    }
    
    foreach ($disposable_domains as $blocked) {
        if (fnmatch($blocked, $domain) || fnmatch($blocked, $full_without_tld) || fnmatch($blocked, $basename)) {
            return true;
        }
    }
    return false;
}

// ===== EMAIL VERIFICATION FUNCTIONS =====

function verify_email_domain($email) {
    $domain = substr(strrchr($email, "@"), 1);
    if (empty($domain)) {
        return false;
    }
    $mxhosts = [];
    if (getmxrr($domain, $mxhosts)) {
        return true;
    }
    if (checkdnsrr($domain, 'A')) {
        return true;
    }
    return false;
}

function validate_email_before_registration($email) {
    if (is_disposable_email($email)) {
        return ['valid' => false, 'reason' => 'disposable'];
    }
    if (!is_email($email)) {
        return ['valid' => false, 'reason' => 'invalid_format'];
    }
    
    // Check if email already exists as WordPress user
    if (email_exists($email)) {
        return ['valid' => false, 'reason' => 'already_subscribed'];
    }
    
    if (!verify_email_domain($email)) {
        return ['valid' => false, 'reason' => 'domain_invalid'];
    }
    
    return ['valid' => true, 'reason' => ''];
}

// ===== REGISTRATION ERRORS FILTER =====

add_filter('registration_errors', function($errors, $sanitized_user_login, $user_email) {
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

// ===== AJAX UPGRADE SUBSCRIPTION (FOR LOGGED-IN USERS) =====

function ajax_upgrade_subscription() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in.']);
    }
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'upgrade_subscription_action')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    $user_id = get_current_user_id();
    $current_type = get_user_meta($user_id, 'subscription_type', true);
    
    // Upgrade forum users to 'both', or re-subscribe unsubscribed users
    if ($current_type === 'forum') {
        update_user_meta($user_id, 'subscription_type', 'both');
    } else {
        update_user_meta($user_id, 'subscription_type', 'sitewide');
    }
    
    // Clear unsubscribe flag
    delete_user_meta($user_id, 'email_unsubscribed');
    
    // Ensure unsubscribe token exists
    if (!get_user_meta($user_id, 'unsubscribe_token', true)) {
        $unsubscribe_token = bin2hex(random_bytes(32));
        update_user_meta($user_id, 'unsubscribe_token', $unsubscribe_token);
    }
    
    wp_send_json_success(['message' => 'Successfully subscribed to site-wide notifications!']);
}
add_action('wp_ajax_upgrade_subscription', 'ajax_upgrade_subscription');

// ===== AJAX SUBSCRIBE HANDLER (FOR NON-LOGGED-IN USERS) =====

function ajax_subscribe_user() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $cooldown_key = 'subscribe_form_last_submit_' . md5($ip);
    
    $attempt_count = get_transient($cooldown_key . '_attempts') ?: 0;
    if (get_transient($cooldown_key)) {
        $penalty_time = min(300, 60 * pow(2, $attempt_count));
        set_transient($cooldown_key . '_attempts', $attempt_count + 1, $penalty_time);
        wp_send_json_error(['message' => 'Please wait before trying again.']);
    }
    
    set_transient($cooldown_key, 1, 60);
    set_transient($cooldown_key . '_attempts', $attempt_count + 1, 300);
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'subscribe_form_action')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
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
    if (!isset($_POST['math_check']) || intval($_POST['math_check']) !== 7) {
        wp_send_json_error(['message' => 'Spam check failed.']);
    }
    
    // Check if user already exists
    $existing_user = get_user_by('email', $email);
    if ($existing_user) {
        // User exists - upgrade their subscription
        $current_type = get_user_meta($existing_user->ID, 'subscription_type', true);
        
        if ($current_type === 'forum') {
            update_user_meta($existing_user->ID, 'subscription_type', 'both');
            delete_user_meta($existing_user->ID, 'email_unsubscribed');
            delete_transient($cooldown_key . '_attempts');
            wp_send_json_success(['message' => 'Your forum account is now subscribed to site-wide notifications too!']);
        } elseif (get_user_meta($existing_user->ID, 'email_unsubscribed', true)) {
            // Re-subscribe
            delete_user_meta($existing_user->ID, 'email_unsubscribed');
            delete_transient($cooldown_key . '_attempts');
            wp_send_json_success(['message' => 'Welcome back! You have been re-subscribed.']);
        } else {
            wp_send_json_error(['message' => "You're already subscribed."]);
        }
        return;
    }
    
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
    
    // Create WordPress user
    $username = sanitize_user(current(explode('@', $email)));
    $base_username = $username;
    $counter = 1;
    
    while (username_exists($username)) {
        $username = $base_username . $counter;
        $counter++;
    }
    
    $user_id = wp_create_user(
        $username,
        wp_generate_password(20, true, true),
        $email
    );
    
    if (is_wp_error($user_id)) {
        error_log('User creation failed: ' . $user_id->get_error_message());
        wp_send_json_error(['message' => 'Subscription failed. Please try again.']);
    }
    
    // Set user meta
    $unsubscribe_token = bin2hex(random_bytes(32));
    update_user_meta($user_id, 'subscription_type', 'sitewide');
    update_user_meta($user_id, 'unsubscribe_token', $unsubscribe_token);
    update_user_meta($user_id, 'subscription_date', current_time('mysql'));
    update_user_meta($user_id, 'registration_ip', sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'Unknown'));
    update_user_meta($user_id, 'registration_ua', sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));
    
    delete_transient($cooldown_key . '_attempts');
    wp_send_json_success(['message' => 'Thank you for subscribing!']);
}
add_action('wp_ajax_nopriv_subscribe_user', 'ajax_subscribe_user');
add_action('wp_ajax_subscribe_user', 'ajax_subscribe_user');

// ===== UNSUBSCRIBE HANDLER =====

function handle_unsubscribe() {
    if (isset($_GET['unsubscribe'])) {
        $token = sanitize_text_field($_GET['unsubscribe']);
        
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            wp_safe_redirect(home_url());
            return;
        }
        
        // Find user by unsubscribe token
        $users = get_users([
            'meta_key' => 'unsubscribe_token',
            'meta_value' => $token,
            'number' => 1
        ]);
        
        if (!empty($users)) {
            $user = $users[0];
            $subscription_type = get_user_meta($user->ID, 'subscription_type', true);
            
            // Mark as unsubscribed but keep account (for forum access)
            update_user_meta($user->ID, 'email_unsubscribed', true);
            
            // Optional: Downgrade 'both' to 'forum' only
            if ($subscription_type === 'both') {
                update_user_meta($user->ID, 'subscription_type', 'forum');
            }
            
            wp_die('<h2>You have successfully unsubscribed.</h2><p>You will no longer receive site-wide notifications.</p>' . 
                   ($subscription_type === 'both' ? '<p>Your forum account remains active.</p>' : ''), 
                   'Unsubscribed', ['response' => 200, 'back_link' => true]);
        } else {
            wp_die('<h2>Unsubscribe Failed</h2><p>We could not find a matching subscription. You may have already unsubscribed.</p>', 'Unsubscribe Error', ['response' => 400, 'back_link' => true]);
        }
    }
}
add_action('init', 'handle_unsubscribe');

// ===== NOTIFY ALL USERS WHEN NEW POSTS ARE PUBLISHED =====

function notify_subscribers_on_new_content($post_ID) {
    $post = get_post($post_ID);
    if (!$post || $post->post_status !== 'publish') return;
    if (strtotime($post->post_date) < strtotime('-1 day')) return;
    if (get_post_meta($post_ID, 'notification_sent', true)) return;
    update_post_meta($post_ID, 'notification_sent', true);
    
    // Get ALL subscribed WP users (site-wide and both -> incl. forum)
    $subscribers = get_users([
        'fields' => ['ID', 'user_email'],
        'meta_query' => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key' => 'subscription_type',
                    'value' => 'sitewide',
                    'compare' => '='
                ],
                [
                    'key' => 'subscription_type',
                    'value' => 'both',
                    'compare' => '='
                ]
            ],
            [
                'relation' => 'OR',
                [
                    'key' => 'email_unsubscribed',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'email_unsubscribed',
                    'value' => '1',
                    'compare' => '!='
                ]
            ]
        ]
    ]);
    
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
        $subscriber_email = sanitize_email($subscriber->user_email);
        if (!is_email($subscriber_email)) {
            error_log("Invalid subscriber email skipped: " . $subscriber->user_email);
            continue;
        }
        
        // Get unsubscribe token
        $unsubscribe_token = get_user_meta($subscriber->ID, 'unsubscribe_token', true);
        if (empty($unsubscribe_token)) {
            // Generate token if it doesn't exist (for old users)
            $unsubscribe_token = bin2hex(random_bytes(32));
            update_user_meta($subscriber->ID, 'unsubscribe_token', $unsubscribe_token);
        }
        
        $unsubscribe_url = esc_url(add_query_arg('unsubscribe', $unsubscribe_token, home_url()));
        
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
        <p>Thank you for subscribing to <a href="<?php echo $site_url; ?>"><?php echo esc_html($site_name); ?></a> - <a href="https://ericroth.org/">ericroth.org</a></p>
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

// ===== HIDE ADMIN BAR FOR FORUM SUBSCRIBERS =====

add_action('after_setup_theme', function() {
    if (current_user_can('subscriber') && !is_admin()) {
        show_admin_bar(false);
    }
});

?>
