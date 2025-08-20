<?php
add_action('wp_footer', function () {
  if (is_page(array('59078')) || is_single(array(''))) {
    ?>
    <style>
      .formsubmit-wrapper {max-width: 700px; margin: 0 auto; padding: 2em; background: #3A4F66; border-radius: 25px;}
      .formsubmit-wrapper input[type="text"],
      .formsubmit-wrapper input[type="email"],
      .formsubmit-wrapper textarea {width: 100%; padding: 20px; margin-bottom: 2em; border-radius: 5px; font-size: 16px; background: #FFFFFF; box-sizing: border-box;}
      .formsubmit-wrapper button[type="submit"] {
        background-color: #1e73be;
        color: #fff;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        font-size: 18px;
        cursor: pointer;
        width: 100px;
        transition: background-color 0.3s ease;
      }
      .formsubmit-wrapper button[type="submit"]:hover {background-color: #c53030;}
      .formsubmit-wrapper .confirmation {margin-top: 1em; margin-bottom: -1em; color: #FFFFFF;}
      .formsubmit-wrapper .hidden-field {position: absolute; left: -9999px; height: 1px; width: 1px; overflow: hidden;}
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
      const form = document.getElementById("contact-form");
      if (!form) return;
      const confirmation = document.getElementById("form-confirmation");
      const submitBtn = document.getElementById("submit-btn");
      const formLoadTime = Date.now();
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        const timeElapsed = (Date.now() - formLoadTime) / 1000;
        if (timeElapsed < 3) return;
        const honeypot = form.querySelector('input[name="middle_name"]');
        if (honeypot && honeypot.value.trim() !== "") return;
        submitBtn.disabled = true;
        const formData = new FormData(form);
        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
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
            confirmation.textContent = "Something went wrong. Please try again.";
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

/* HANDLE FORM SUBMISSIONS */

add_action('template_redirect', function () {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['message'])) {
    $honeypot = trim($_POST['middle_name'] ?? '');
    $math_check = trim($_POST['math_check'] ?? '');
    if ($honeypot !== '' || $math_check !== '7') return;
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $subject = sanitize_text_field($_POST['subject'] ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');
    if (!$email || !$message) return;
    global $wpdb;
    $wpdb->insert(
      $wpdb->prefix . 'contact_messages',
      [
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message
      ],
      ['%s', '%s', '%s', '%s']
    );
  $admin_email = get_option('admin_email');
  $subject_line = 'New Contact Form Submission';
  $email_body = "Name: $name\n";
  $email_body .= "Email: $email\n";
  if (!empty($subject)) {
    $email_body .= "Subject: $subject\n";
  }
  $email_body .= "Message:\n$message";
  wp_mail($admin_email, $subject_line, $email_body);
    wp_redirect(add_query_arg('contact', 'success', wp_get_referer()));
    exit;
  }
});

/* AJAX HANDLER (for Confirmation Message) */

add_action('wp_ajax_submit_contact_form_ajax', 'handle_contact_form_ajax');
add_action('wp_ajax_nopriv_submit_contact_form_ajax', 'handle_contact_form_ajax');
function handle_contact_form_ajax() {
  $honeypot = trim($_POST['middle_name'] ?? '');
  $math_check = trim($_POST['math_check'] ?? '');
  if ($honeypot !== '' || $math_check !== '7') {
    wp_send_json_error();
  }
  $name = sanitize_text_field($_POST['name'] ?? '');
  $email = sanitize_email($_POST['email'] ?? '');
  $subject = sanitize_text_field($_POST['subject'] ?? '');
  $message = sanitize_textarea_field($_POST['message'] ?? '');
  if (!$email || !$message) {
    wp_send_json_error();
  }
  global $wpdb;
  $wpdb->insert(
    $wpdb->prefix . 'contact_messages',
    [
      'name' => $name,
      'email' => $email,
      'subject' => $subject,
      'message' => $message
    ],
    ['%s', '%s', '%s', '%s']
  );
  $admin_email = get_option('admin_email');
  $subject_line = 'New Contact Form Submission';
  $email_body = "Name: $name\n";
  $email_body .= "Email: $email\n";
  if (!empty($subject)) {
    $email_body .= "Subject: $subject\n";
  }
  $email_body .= "Message:\n$message";
  wp_mail($admin_email, $subject_line, $email_body);
  wp_send_json_success();
}

/* CREATE DATABASE TABLE TO STORE STUFF */

add_action('init', function () {
  if (!get_option('contact_messages_table_created')) {
    global $wpdb;
    $table = $wpdb->prefix . 'contact_messages';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
      id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
      name VARCHAR(100) NOT NULL,
      email VARCHAR(100) NOT NULL,
      subject VARCHAR(150) NOT NULL,
      message TEXT NOT NULL,
      submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) $charset;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    update_option('contact_messages_table_created', true);
  }
});

/* CREATE ADMIN MENU TO MANAGE MESSAGES */

add_action('admin_menu', function () {
  add_menu_page(
    'Contact Form',
    'Contact Form',
    'manage_options',
    'contact-form',
    'display_contact_messages',
    'dashicons-email',
    21
  );
});
add_action('admin_enqueue_scripts', function ($hook) {
  if ($hook === 'toplevel_page_contact-form') {
    add_thickbox();
  }
});
function display_contact_messages() {
  global $wpdb;
  $table = $wpdb->prefix . 'contact_messages';
  if (isset($_POST['bulk_delete'], $_POST['message_ids'], $_POST['_wpnonce']) && check_admin_referer('contact_messages_action', '_wpnonce')) {
    $ids = implode(',', array_map('intval', $_POST['message_ids']));
    $wpdb->query("DELETE FROM $table WHERE id IN ($ids)");
    echo '<div class="updated"><p>Selected messages have been deleted.</p></div>';
  }
  $search = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
  $per_page = 20;
  $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
  $offset = ($page - 1) * $per_page;

  if ($search) {
    $query = $wpdb->prepare("SELECT * FROM $table WHERE name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
      "%$search%", "%$search%", "%$search%", "%$search%", $per_page, $offset);
    $messages = $wpdb->get_results($query);
    $total = count($messages);
  } else {
    $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY submitted_at DESC LIMIT %d OFFSET %d", $per_page, $offset));
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
  }
  echo '<div class="wrap"><h2>Contact Messages</h2>';
  echo '<form method="post">';
  echo '<input type="text" name="search_term" placeholder="Search messages..." value="' . esc_attr($search) . '">';
  echo '<button type="submit" class="button-primary">Search</button>';
  echo '</form><br>';
  if ($messages) {
    echo '<form method="post">';
    wp_nonce_field('contact_messages_action');
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th></th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Date</th></tr></thead><tbody>';
    foreach ($messages as $msg) {
      echo '<tr>';
      echo '<td><input type="checkbox" name="message_ids[]" value="' . esc_attr($msg->id) . '"></td>';
      echo '<td>' . esc_html($msg->name) . '</td>';
      echo '<td>' . esc_html($msg->email) . '</td>';
      echo '<td>' . esc_html($msg->subject) . '</td>';
      echo '<td>';
      echo esc_html(wp_trim_words($msg->message, 20)) . '<br>';
      echo '<a href="#TB_inline?width=600&height=400&inlineId=msg-' . esc_attr($msg->id) . '" class="thickbox">Preview</a>';
      echo '<div id="msg-' . esc_attr($msg->id) . '" style="display:none;">';
      echo '<h3>' . esc_html($msg->subject) . '</h3>';
      echo '<p><strong>From:</strong> ' . esc_html($msg->name) . ' &lt;' . esc_html($msg->email) . '&gt;</p>';
      echo '<p>' . nl2br(esc_html($msg->message)) . '</p>';
      echo '</div>';
      echo '</td>';
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
  } else {
    echo '<p>No messages found.</p>';
  }
  echo '</div>';
}
