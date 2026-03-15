<?php
defined('ABSPATH') || exit;

// ======================================
// CREATE / UPDATE DATABASE TABLES
// ======================================

add_action('init', function () {
  global $wpdb;
  $charset = $wpdb->get_charset_collate();
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  // Main Messages Table — dbDelta adds the Status Column if it doesn't exist yet
  if (!get_option('contact_messages_table_created')) {
    $table = $wpdb->prefix . 'contact_messages';
    $sql = "CREATE TABLE $table (
      id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
      name VARCHAR(100) NOT NULL,
      email VARCHAR(100) NOT NULL,
      subject VARCHAR(150) NOT NULL,
      message TEXT NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'ok',
      submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) $charset;";
    dbDelta($sql);
    update_option('contact_messages_table_created', true);
  } else {
    // Existing Installs: Add Status Column if missing
    $table = $wpdb->prefix . 'contact_messages';
    $col = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'status'");
    if (empty($col)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'ok' AFTER message");
    }
  }
  // Nonce Failure Log Table
  if (!get_option('contact_nonce_log_table_created')) {
    $log_table = $wpdb->prefix . 'contact_nonce_log';
    $sql = "CREATE TABLE $log_table (
      id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
      ip VARCHAR(45) NOT NULL,
      failed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) $charset;";
    dbDelta($sql);
    update_option('contact_nonce_log_table_created', true);
  }
});

// ======================================
// SMTP CONFIGURATION
// ======================================

add_action('phpmailer_init', 'configure_smtp');
function configure_smtp($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host       = defined('SMTP_HOST') ? SMTP_HOST : '';
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $phpmailer->Username   = defined('SMTP_USER') ? SMTP_USER : '';
    $phpmailer->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->From       = defined('SMTP_FROM') ? SMTP_FROM : '';
    $phpmailer->FromName   = defined('SMTP_FROMNAME') ? SMTP_FROMNAME : '';
}

// ======================================
// CONTACT FORM DISPLAY & SCRIPTS
// ======================================

add_action('wp_footer', function () {
  if (is_page(array('59078','150449'))) {
    $nonce = wp_create_nonce('contact_form_nonce');
    ?>

    <style>
      .formsubmit-wrapper {max-width: 700px; margin: 0 auto; padding: 2em; background: #3A4F66; border-radius: 25px;}
      .formsubmit-wrapper input[type="text"],
      .formsubmit-wrapper input[type="email"],
      .formsubmit-wrapper textarea {width: 100%; padding: 20px; margin-bottom: 2em; border-radius: 5px; font-size: 16px; background: #FFFFFF; box-sizing: border-box;}
      .formsubmit-wrapper button[type="submit"] {
        background-color: #1e73be;
        color: #FFFFFF;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        font-size: 18px;
        cursor: pointer;
        width: 125px;
        transition: background-color 0.3s ease;
      }
      .formsubmit-wrapper button[type="submit"]:hover {background-color: #c53030;}
      .formsubmit-wrapper .confirmation {margin-top: 1em; margin-bottom: -1em; color: #FFFFFF; display: none;}
      .formsubmit-wrapper .hidden-field {position: absolute; left: -9999px; height: 1px; width: 1px; overflow: hidden;}
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
      const form = document.getElementById("contact-form");
      if (!form) return;
      document.getElementById("contact-nonce").value = "<?php echo $nonce; ?>";
      const confirmation = document.getElementById("form-confirmation");
      const submitBtn = document.getElementById("submit-btn");
      const formLoadTime = Date.now();
      let lastSubmissionTime = 0;
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        const now = Date.now();
        if (now - lastSubmissionTime < 5000) {
          confirmation.textContent = "Please wait a few seconds before submitting again.";
          confirmation.style.display = "block";
          return;
        }
        lastSubmissionTime = now;
        const timeElapsed = (Date.now() - formLoadTime) / 1000;
        if (timeElapsed < 3) return;
        const honeypot = form.querySelector('input[name="middle_name"]');
        if (honeypot && honeypot.value.trim() !== "") return;
        submitBtn.disabled = true;
        const formData = new FormData(form);
        fetch("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", {
          method: "POST",
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            form.reset();
            confirmation.textContent = "Thanks! Your message has been sent.";
            confirmation.style.display = "block";
          } else {
            confirmation.textContent = data.data && data.data.message ? data.data.message : "Something went wrong. Please try again.";
            confirmation.style.display = "block";
          }
        })
        .catch(() => {
          confirmation.textContent = "Submission failed. Please try again.";
          confirmation.style.display = "block";
        })
        .finally(() => {
          submitBtn.disabled = false;
        });
      });
    });
    </script>
    <?php
  }
});

// ======================================
// AJAX HANDLER
// ======================================

add_action('wp_ajax_submit_contact_form_ajax', 'handle_contact_form_ajax');
add_action('wp_ajax_nopriv_submit_contact_form_ajax', 'handle_contact_form_ajax');

function handle_contact_form_ajax() {
  if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'contact_form_nonce')) {
    global $wpdb;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $wpdb->insert($wpdb->prefix . 'contact_nonce_log', ['ip' => $ip], ['%s']);
    wp_send_json_error(array('message' => 'Security check failed'));
  }
  $ip = $_SERVER['REMOTE_ADDR'];
  $last_submission = get_transient('contact_form_ip_' . md5($ip));
  if ($last_submission) {
    wp_send_json_error(array('message' => 'Please wait before submitting again.'));
  }
  $honeypot   = trim($_POST['middle_name'] ?? '');
  $math_check = trim($_POST['math_check']  ?? '');
  if ($honeypot !== '' || $math_check !== '7') {
    wp_send_json_error();
  }
  $name    = sanitize_text_field($_POST['name']    ?? '');
  $email   = sanitize_email($_POST['email']        ?? '');
  $subject = sanitize_text_field($_POST['subject'] ?? '');
  $message = sanitize_textarea_field($_POST['message'] ?? '');
  if (!is_email($email)) {
    wp_send_json_error(array('message' => 'Invalid email address'));
  }
  if (!$email || !$message) {
    wp_send_json_error(array('message' => 'Email and message are required'));
  }
  set_transient('contact_form_ip_' . md5($ip), time(), 60);
  global $wpdb;
  $result = $wpdb->insert(
    $wpdb->prefix . 'contact_messages',
    ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message, 'status' => 'ok'],
    ['%s', '%s', '%s', '%s', '%s']
  );
  if ($result === false) {
    $err = $wpdb->last_error;
    error_log('Contact form DB insert failed: ' . $err);
    wp_mail(
      get_option('admin_email'),
      '[Contact Form] DB insert failed',
      "A message could not be saved to the database.\n\nFrom: $name ($email)\nSubject: $subject\nError: $err\nTime: " . current_time('mysql')
    );
    wp_send_json_error(array('message' => 'Failed to save message'));
  }
  $inserted_id  = $wpdb->insert_id;
  $admin_email  = get_option('admin_email');
  $email_body   = "Name: $name\nEmail: $email\n";
  $email_body  .= $subject ? "Subject: $subject\n" : '';
  $email_body  .= "Message:\n$message";
  $mail_sent = wp_mail($admin_email, 'New Contact Form Submission', $email_body);
  if (!$mail_sent) {
    error_log('Contact form email failed to send');
    $wpdb->update(
      $wpdb->prefix . 'contact_messages',
      ['status' => 'mail_failed'],
      ['id' => $inserted_id],
      ['%s'],
      ['%d']
    );
  }
  wp_send_json_success();
}

// ======================================
// ADMIN MENU
// ======================================

add_action('admin_menu', function () {
  add_menu_page('Contact Form', 'Contact Form', 'manage_options', 'contact-form', 'display_contact_messages', 'dashicons-email', 21);
});
add_action('admin_enqueue_scripts', function ($hook) {
  if ($hook === 'toplevel_page_contact-form') add_thickbox();
});

function display_contact_messages() {
  global $wpdb;
  $table     = $wpdb->prefix . 'contact_messages';
  $log_table = $wpdb->prefix . 'contact_nonce_log';
  // Purge Nonce Log Entries older than 24 Hours
  $wpdb->query("DELETE FROM $log_table WHERE failed_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
  // Bulk delete
  if (isset($_POST['bulk_delete'], $_POST['message_ids'], $_POST['_wpnonce']) && check_admin_referer('contact_messages_action', '_wpnonce')) {
    $ids = array_map('intval', $_POST['message_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", ...$ids));
    echo '<div class="updated"><p>Selected messages have been deleted.</p></div>';
  }
  // Nonce Failure Warning Banner
  $one_hour_ago   = date('Y-m-d H:i:s', strtotime('-1 hour'));
  $nonce_failures = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $log_table WHERE failed_at >= %s", $one_hour_ago));
  if ($nonce_failures >= 5) {
    echo '<div class="notice notice-warning"><p><strong>⚠ Contact Form Warning:</strong> ' . $nonce_failures . ' nonce verification failures in the last hour — your form may be broken.</p></div>';
  }
  $search   = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
  $per_page = 20;
  $page     = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
  $offset   = ($page - 1) * $per_page;
  if ($search) {
    $like     = "%$search%";
    $messages = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $table WHERE name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
      $like, $like, $like, $like, $per_page, $offset
    ));
    $total = $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $table WHERE name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s",
      $like, $like, $like, $like
    ));
  } else {
    $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY submitted_at DESC LIMIT %d OFFSET %d", $per_page, $offset));
    $total    = $wpdb->get_var("SELECT COUNT(*) FROM $table");
  }
  echo '<div class="wrap"><h2>Contact Messages</h2>';
  echo '<form method="post"><input type="text" name="search_term" placeholder="Search messages..." value="' . esc_attr($search) . '"> ';
  echo '<button type="submit" class="button-primary">Search</button></form><br>';
  if (!$messages) { echo '<p>No messages found.</p></div>'; return; }
  echo '<form method="post">';
  wp_nonce_field('contact_messages_action');
  echo '<table class="wp-list-table widefat fixed striped">';
  echo '<thead><tr><th></th><th>Status</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Date</th></tr></thead><tbody>';
  foreach ($messages as $msg) {
    if ($msg->status === 'mail_failed') {
      $status_badge = '<span title="Message saved but notification email failed to send" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #c53030;"></span>';
    } else {
      $status_badge = '<span title="OK" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: green;"></span>';
    }
    echo '<tr>';
    echo '<td><input type="checkbox" name="message_ids[]" value="' . esc_attr($msg->id) . '"></td>';
    echo '<td style="text-align:center;">' . $status_badge . '</td>';
    echo '<td>' . esc_html($msg->name) . '</td>';
    echo '<td>' . esc_html($msg->email) . '</td>';
    echo '<td>' . esc_html($msg->subject) . '</td>';
    echo '<td>';
    echo esc_html(wp_trim_words($msg->message, 20)) . '<br>';
    echo '<a href="#TB_inline?width=600&height=400&inlineId=msg-' . esc_attr($msg->id) . '" class="thickbox">Preview</a>';
    echo '<div id="msg-' . esc_attr($msg->id) . '" style="display: none;">';
    echo '<h3>' . esc_html($msg->subject) . '</h3>';
    echo '<p><strong>From:</strong> ' . esc_html($msg->name) . ' &lt;' . esc_html($msg->email) . '&gt;</p>';
    echo '<p>' . nl2br(esc_html($msg->message)) . '</p>';
    echo '</div></td>';
    echo '<td>' . esc_html(date('F j, Y H:i', strtotime($msg->submitted_at))) . '</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo '<button type="submit" name="bulk_delete" class="button-primary">Delete Selected</button>';
  echo '</form>';
  $total_pages = ceil($total / $per_page);
  if ($total_pages > 1) {
    echo '<div class="tablenav"><div class="tablenav-pages">';
    for ($i = 1; $i <= $total_pages; $i++) {
      $class = ($page == $i) ? 'button button-primary' : 'button';
      echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '" class="' . esc_attr($class) . '">' . $i . '</a> ';
    }
    echo '</div></div>';
  }
  echo '</div>';
}
