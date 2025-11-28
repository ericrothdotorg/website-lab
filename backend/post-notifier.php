<?php

defined('ABSPATH') || exit;

// Sends E-mail Notifications when Posts or Interests are published

class Post_Notifier {
    
    public function __construct() {
        // Hook into Post publish Events
        add_action('transition_post_status', [$this, 'on_post_publish'], 10, 3);
        // Configure SMTP if Constants are defined
        add_action('phpmailer_init', [$this, 'configure_smtp']);
    }
    
    /* Configure SMTP using Constants from wp-config.php */

    public function configure_smtp($phpmailer) {
        if (defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')) {
            $phpmailer->isSMTP();
            $phpmailer->Host       = SMTP_HOST;
            $phpmailer->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Username   = SMTP_USER;
            $phpmailer->Password   = SMTP_PASS;
            $phpmailer->SMTPSecure = 'tls';
            $phpmailer->From       = defined('SMTP_FROM') ? SMTP_FROM : SMTP_USER;
            $phpmailer->FromName   = defined('SMTP_FROMNAME') ? SMTP_FROMNAME : get_bloginfo('name');
        }
    }
    
    /* Trigger E-mail when Post Status changes to 'publish' */

    public function on_post_publish($new_status, $old_status, $post) {
        // Only send E-mail when transitioning TO publish (not already published posts)
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        // Check if this is a Post Type we care about
        $post_type = get_post_type($post);
        $allowed_types = ['post', 'interests'];
        if (!in_array($post_type, $allowed_types)) {
            return;
        }
        // Determine the Label
        $label_map = [
            'post'      => 'Blog',
            'interests' => 'Interests'
        ];
        $label = $label_map[$post_type];
        // Send the notification
        $this->send_notification($post, $label);
    }
    
    /* Send the E-mail Notification */

    private function send_notification($post, $label) {
        $title = get_the_title($post);
        $link = get_permalink($post);
        $excerpt = wp_strip_all_tags(get_the_excerpt($post));
        // Get featured Image
        $image_url = '';
        if (has_post_thumbnail($post)) {
            $image_url = get_the_post_thumbnail_url($post, 'large');
        }
        // Get Post Content (first 500 chars)
        $content = wp_strip_all_tags($post->post_content);
        $content = wp_trim_words($content, 100, '...');
        // Build E-mail
        $subject = "New {$label} Post Published: {$title}";
        $message = $this->build_email_html($label, $title, $link, $excerpt, $content, $image_url);
        // Email Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8'
        ];
        // Send to Admin E-mail
        $to = get_option('admin_email');
        $sent = wp_mail($to, $subject, $message, $headers);
        // Log if failed
        if (!$sent) {
            error_log("Post Notifier: Failed to send email for post ID {$post->ID}");
        }
    }
    
    /* Build HTML E-mail Body */

    private function build_email_html($label, $title, $link, $excerpt, $content, $image_url) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($title); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            
            <h2 style="margin-top: 0;">New Content Published</h2>
            
            <?php if ($image_url): ?>
            <div style="margin-bottom: 20px;">
                <a href="<?php echo esc_url($link); ?>">
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="<?php echo esc_attr($title); ?>" 
                         style="max-width: 100%; height: auto; border-radius: 5px;">
                </a>
            </div>
            <?php endif; ?>
            
            <h1 style="color: #1e73be;">
                <a href="<?php echo esc_url($link); ?>" style="color: #1e73be; text-decoration: none;">
                    <?php echo esc_html($title); ?>
                </a>
            </h1>
            
            <?php if ($excerpt): ?>
            <p style="font-size: 16px; font-style: italic; color: #666;">
                <?php echo esc_html($excerpt); ?>
            </p>
            <?php endif; ?>
            
            <div style="margin: 20px 0; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #339966;">
                <?php echo nl2br(esc_html($content)); ?>
            </div>
            
            <p style="margin: 30px 0;">
                <a href="<?php echo esc_url($link); ?>" 
                   style="display: inline-block; padding: 12px 24px; background-color: #1e73be; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold;">
                    Read More
                </a>
            </p>
            
            <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
            
            <p style="font-size: 14px; color: #666;">
                Published on <strong><?php echo esc_html($site_name); ?></strong><br>
                <a href="<?php echo esc_url($site_url); ?>" style="color: #1e73be;"><?php echo esc_url($site_url); ?></a>
            </p>
            
            <p style="font-size: 12px; color: #999; margin-top: 20px;">
                This is an automated notification for content published on the website.
            </p>
            
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
// Initialize the plugin
new Post_Notifier();
