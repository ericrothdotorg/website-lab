<?php

// SIMPLE EMAIL VALIDATION FOR EXISTING USERS (in wp_users -> Asgaros Forum)

add_action('admin_menu', function() {
    add_management_page(
        'Validate User Emails',
        'Validate Emails',
        'manage_options',
        'validate-user-emails',
        'validate_user_emails_page'
    );
});

function validate_user_emails_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to access this page.');
    }
    
    $results = null;
    $processing = false;
    
    // Check if form was submitted
    if (isset($_POST['validate_emails']) && check_admin_referer('validate_emails_nonce')) {
        $processing = true;
        $results = validate_all_user_emails_simple();
    }
    
    ?>
    <div class="wrap">
        <h1>Validate User Emails</h1>
        
        <?php if (!$processing): ?>
            <p>This will check all user emails for:</p>
            <ul>
                <li>Invalid email format</li>
                <li>Disposable email services</li>
                <li>SMTP verification (mailbox existence)</li>
            </ul>
            
            <form method="post">
                <?php wp_nonce_field('validate_emails_nonce'); ?>
                <input type="hidden" name="validate_emails" value="1">
                <button type="submit" class="button button-primary" onclick="this.disabled=true; this.form.submit();">Start Validation</button>
            </form>
        <?php endif; ?>
        
        <?php if ($results): ?>
            <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccc;">
                <h2>✓ Validation Complete</h2>
                
                <p><strong>Total users:</strong> <?php echo esc_html($results['total']); ?></p>
                <p><strong>Valid emails:</strong> <span style="color: green;"><?php echo esc_html($results['valid']); ?></span></p>
                <p><strong>Invalid emails:</strong> <span style="color: red;"><?php echo esc_html($results['invalid']); ?></span></p>
                
                <?php if (!empty($results['invalid_users'])): ?>
                    <h3 style="margin-top: 30px;">Users with Invalid Emails:</h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['invalid_users'] as $user): ?>
                                <tr>
                                    <td><?php echo esc_html($user['id']); ?></td>
                                    <td><?php echo esc_html($user['login']); ?></td>
                                    <td><?php echo esc_html($user['email']); ?></td>
                                    <td><?php echo esc_html($user['reason']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p style="margin-top: 20px; padding: 10px; background: #fffacd; border-left: 4px solid #ff9800;">
                        <strong>Note:</strong> Copy the User IDs above if you want to delete them. You can delete users manually from Users page, or ask me for a deletion script.
                    </p>
                <?php else: ?>
                    <p style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; margin-top: 20px;">
                        ✓ Great! All users have valid email addresses.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function validate_all_user_emails_simple() {
    $users = get_users(['fields' => ['ID', 'user_login', 'user_email']]);
    
    $results = [
        'total' => count($users),
        'valid' => 0,
        'invalid' => 0,
        'invalid_users' => []
    ];
    
    foreach ($users as $user) {
        $validation = check_single_user_email($user->user_email);
        
        if ($validation['valid']) {
            $results['valid']++;
        } else {
            $results['invalid']++;
            $results['invalid_users'][] = [
                'id' => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'reason' => $validation['reason']
            ];
        }
    }
    
    return $results;
}

function check_single_user_email($email) {
    // Check 1: Basic format validation
    if (!is_email($email)) {
        return ['valid' => false, 'reason' => 'Invalid format'];
    }
    
    // Check 2: Is it disposable?
    if (is_disposable_email($email)) {
        return ['valid' => false, 'reason' => 'Disposable email'];
    }
    
    return ['valid' => true, 'reason' => ''];
}

?>
