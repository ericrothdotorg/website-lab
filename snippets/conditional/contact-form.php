<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* =============================================================================
   1. DATABASE SETUP
   Creates wp_er_contact_messages on first Run.
   Safe to re-run. Existing installs: Adds Status Column if missing, and drops
   the old wp_er_contact_nonce_log once (see 2: no nonce anymore).
============================================================================= */

if ( is_admin() ) {
    add_action( 'init', function () {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        // Main Messages Table
        if ( ! get_option( 'er_contact_messages_table_created' ) ) {
            $table = $wpdb->prefix . 'er_contact_messages';
            $sql   = "CREATE TABLE {$table} (
                id           MEDIUMINT(9)  NOT NULL AUTO_INCREMENT,
                name         VARCHAR(100)  NOT NULL,
                email        VARCHAR(100)  NOT NULL,
                subject      VARCHAR(150)  NOT NULL,
                message      TEXT          NOT NULL,
                status       VARCHAR(20)   NOT NULL DEFAULT 'ok',
                submitted_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset};";
            dbDelta( $sql );
            update_option( 'er_contact_messages_table_created', true );
        } else {
            // Existing Installs: Add Status Column if it was created before that Column existed
            $table = $wpdb->prefix . 'er_contact_messages';
            $col   = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'status'" );
            if ( empty( $col ) ) {
                $wpdb->query( "ALTER TABLE {$table} ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'ok' AFTER message" );
            }
        }
        // Old Nonce Failure Log Table: no longer written by anything
        if ( get_option( 'er_contact_nonce_log_table_created' ) ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}er_contact_nonce_log" );
            delete_option( 'er_contact_nonce_log_table_created' );
        }
    } );
}

/* =============================================================================
   2. CONTACT FORM (Conditional: Only on designated Page IDs)
   Scripts and Styles load only on Pages where the Form is present.
   No nonce: the page and its footer are full-page cached for days, so any
   nonce printed here would be stale long before the page is (12-24 h), and the
   form would fail with "Security check failed". For a public form it adds no
   protection anyway (every logged-out visitor gets the same one). Spam is
   kept out by the honeypot, the silent math check and the per-IP rate limit.
============================================================================= */

add_action( 'wp_footer', function () {
    if ( ! is_page( [ '59078', '150449' ] ) ) return;
    ?>
    <style>
        .formsubmit-wrapper {max-width: 700px; margin: 0 auto; padding: 2em; background: var(--color-3); border-radius: 25px;}
        .formsubmit-wrapper input[type="text"],
        .formsubmit-wrapper input[type="email"],
        .formsubmit-wrapper textarea {
            width: 100%;
            padding: 10px 20px;
            margin-bottom: 2em;
            border-radius: 5px;
            font-size: var(--er-fs-sm);
			line-height: 1;
            background: var(--color-8);
            box-sizing: border-box;
        }
        .formsubmit-wrapper button[type="submit"] {
            background-color: var(--color-1);
            color: var(--color-8);
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: var(--er-fs-md);
            cursor: pointer;
            width: 125px;
            transition: background-color 0.3s ease;
        }
        .formsubmit-wrapper button[type="submit"]:hover {background-color: var(--color-2);}
        .formsubmit-wrapper .confirmation {margin-top: 1em; margin-bottom: -1em; color: var(--color-8); display: none;}
        .formsubmit-wrapper .hidden-field {position: absolute; left: -9999px; height: 1px; width: 1px; overflow: hidden;}
    </style>

    <script>
    document.addEventListener( 'DOMContentLoaded', function () {
        const form = document.getElementById( 'contact-form' );
        if ( ! form ) return; // Form not on this Page — Do nothing
        const confirmation  = document.getElementById( 'form-confirmation' );
        const submitBtn     = document.getElementById( 'submit-btn' );
        const formLoadTime  = Date.now();
        let lastSubmit      = 0;
        form.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            // Resubmit Throttle
            if ( Date.now() - lastSubmit < 5000 ) {
                showMessage( 'Please wait a few seconds before submitting again.' );
                return;
            }
            lastSubmit = Date.now();
            // Time Check
            if ( ( Date.now() - formLoadTime ) / 1000 < 3 ) return;
            // Honeypot
            const honeypot = form.querySelector( 'input[name="middle_name"]' );
            if ( honeypot && honeypot.value.trim() !== '' ) return;
            submitBtn.disabled = true;
            const formData = new FormData( form );
            fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                method: 'POST',
                body:   formData,
            } )
            .then( res => res.json() )
            .then( data => {
                if ( data.success ) {
                    form.reset();
                    showMessage( 'Thanks! Your message has been sent.' );
                } else {
                    showMessage( data.data && data.data.message ? data.data.message : 'Something went wrong. Please try again.' );
                }
            } )
            .catch( () => showMessage( 'Submission failed. Please try again.' ) )
            .finally( () => {
                submitBtn.disabled = false;
            } );
        } );
        function showMessage( msg ) {
            confirmation.textContent   = msg;
            confirmation.style.display = 'block';
        }
    } );
    </script>
    <?php
} );

/* =============================================================================
   3. FORM HANDLER (AJAX)
   Validates, rate-limits, saves to DB and e-mails the Admin.
   Sets Status to 'mail_failed' if the Notification E-mail cannot be sent.
============================================================================= */

add_action( 'wp_ajax_submit_contact_form_ajax',        'er_contact_handle_ajax' );
add_action( 'wp_ajax_nopriv_submit_contact_form_ajax', 'er_contact_handle_ajax' );

function er_contact_handle_ajax() {
    global $wpdb;
    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
        wp_send_json_error();
    }
    // Transient Rate Limit
    $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
    if ( get_transient( 'contact_form_ip_' . md5( $ip ) ) ) {
        wp_send_json_error( [ 'message' => 'Please wait before submitting again.' ] );
    }
    // Honeypot + Silent Math Check
    $honeypot   = trim( $_POST['middle_name'] ?? '' );
    $math_check = trim( $_POST['math_check']  ?? '' );
    if ( $honeypot !== '' || $math_check !== '7' ) {
        wp_send_json_error();
    }
    // Sanitize Fields
    $name    = sanitize_text_field(     $_POST['name']    ?? '' );
    $email   = sanitize_email(          $_POST['email']   ?? '' );
    $subject = sanitize_text_field(     $_POST['subject'] ?? '' );
    $message = sanitize_textarea_field( $_POST['message'] ?? '' );
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Invalid email address.' ] );
    }
    if ( ! $email || ! $message ) {
        wp_send_json_error( [ 'message' => 'Email and message are required.' ] );
    }
    // Set Transient BEFORE DB insert to block any duplicate requests
    set_transient( 'contact_form_ip_' . md5( $ip ), time(), 60 );
    // Save to Database
    $result = $wpdb->insert(
        $wpdb->prefix . 'er_contact_messages',
        [ 'name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message, 'status' => 'ok' ],
        [ '%s', '%s', '%s', '%s', '%s' ]
    );
    if ( $result === false ) {
        $err = $wpdb->last_error;
        error_log( 'Contact form DB insert failed: ' . $err );
        wp_mail(
            get_option( 'admin_email' ),
            '[Contact Form] DB insert failed',
            "A message could not be saved to the database.\n\nFrom: {$name} ({$email})\nSubject: {$subject}\nError: {$err}\nTime: " . current_time( 'mysql' )
        );
        wp_send_json_error( [ 'message' => 'Failed to save message.' ] );
    }
    $inserted_id = $wpdb->insert_id;
    // Notify Admin
    $email_body  = "Name: {$name}\nEmail: {$email}\n";
    $email_body .= $subject ? "Subject: {$subject}\n" : '';
    $email_body .= "Message:\n{$message}";
    $mail_sent   = wp_mail( get_option( 'admin_email' ), 'New Contact Form Submission', $email_body );
    // Log Mail Failure against the saved Row so it's visible in the Admin Page
    if ( ! $mail_sent ) {
        error_log( 'Contact form email failed to send.' );
        $wpdb->update(
            $wpdb->prefix . 'er_contact_messages',
            [ 'status' => 'mail_failed' ],
            [ 'id'     => $inserted_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }
    wp_send_json_success();
}

/* =============================================================================
   4. ADMIN PAGE
   Top-level Menu → View, search, filter by Status Dot, bulk delete,
   full-message Thickbox Preview, Pagination.
   Thickbox is enqueued only when this Admin Page is active.
============================================================================= */

if ( is_admin() ) {
    add_action( 'admin_menu', function () {
        add_menu_page(
            'Contact Form',
            'Contact Form',
            'manage_options',
            'contact-form',
            'er_contact_admin_page',
            'dashicons-email',
            21
        );
    } );

    // Enqueue Thickbox only on this Admin Page (used for full-message Preview Overlay)
    add_action( 'admin_enqueue_scripts', function ( $hook ) {
        if ( $hook === 'toplevel_page_contact-form' ) add_thickbox();
    } );

    function er_contact_admin_page() {
        global $wpdb;
        $table     = $wpdb->prefix . 'er_contact_messages';
        // Handle Bulk Delete
        if (
            isset( $_POST['bulk_delete'], $_POST['message_ids'], $_POST['_wpnonce'] ) &&
            check_admin_referer( 'er_contact_messages_action', '_wpnonce' )
        ) {
            $ids          = array_map( 'intval', $_POST['message_ids'] );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids ) );
            echo '<div class="updated"><p>Selected messages have been deleted.</p></div>';
        }
        // Search (POST — Submitted from the same Page)
        $search   = isset( $_POST['search_term'] ) ? sanitize_text_field( $_POST['search_term'] ) : '';
        $per_page = 20;
        $page     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $offset   = ( $page - 1 ) * $per_page;
        if ( $search ) {
            $like     = "%{$search}%";
            $messages = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
                $like, $like, $like, $like, $per_page, $offset
            ) );
            $total = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE name LIKE %s OR email LIKE %s OR subject LIKE %s OR message LIKE %s",
                $like, $like, $like, $like
            ) );
        } else {
            $messages = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            ) );
            $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        }
        ?>
        <div class="wrap">
            <h2>Contact Messages</h2>

            <!-- Search Form (POST keeps Search Term visible after Submit) -->
            <form method="post">
                <input type="text" name="search_term" placeholder="Search messages..." value="<?php echo esc_attr( $search ); ?>">
                <button type="submit" class="button-primary">Search</button>
            </form>
            <br>

            <?php if ( empty( $messages ) ) : ?>
                <p>No messages found.</p>
            <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field( 'er_contact_messages_action' ); ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th></th>
                                <!-- Status Dot: Green = ok, Red = mail_failed (Notification E-mail did not send) -->
                                <th>Status</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $messages as $msg ) :
                                // Status Dot: Red = Admin Notification failed; Green = all good
                                $status_badge = $msg->status === 'mail_failed'
                                    ? '<span title="Message saved but notification email failed to send" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: red;"></span>'
                                    : '<span title="OK" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: green;"></span>';
                            ?>
                                <tr>
                                    <td><input type="checkbox" name="message_ids[]" value="<?php echo esc_attr( $msg->id ); ?>"></td>
                                    <td style="text-align:center;"><?php echo $status_badge; ?></td>
                                    <td><?php echo esc_html( $msg->name ); ?></td>
                                    <td><?php echo esc_html( $msg->email ); ?></td>
                                    <td><?php echo esc_html( $msg->subject ); ?></td>
                                    <td>
                                        <?php echo esc_html( wp_trim_words( $msg->message, 20 ) ); ?><br>
                                        <!-- Thickbox: opens full message in an overlay without leaving the page -->
                                        <a href="#TB_inline?width=600&height=400&inlineId=msg-<?php echo esc_attr( $msg->id ); ?>" class="thickbox">Preview</a>
                                        <div id="msg-<?php echo esc_attr( $msg->id ); ?>" style="display: none;">
                                            <h3><?php echo esc_html( $msg->subject ); ?></h3>
                                            <p><strong>From:</strong> <?php echo esc_html( $msg->name ); ?> &lt;<?php echo esc_html( $msg->email ); ?>&gt;</p>
                                            <p><?php echo nl2br( esc_html( $msg->message ) ); ?></p>
                                        </div>
                                    </td>
                                    <!-- Stored by MySQL in UTC, shown in site time -->
                                    <td><?php echo esc_html( wp_date( 'F j, Y H:i', strtotime( $msg->submitted_at . ' UTC' ) ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <br>
                    <button type="submit" name="bulk_delete" class="button-primary">Delete Selected</button>
                </form>

                <!-- Pagination -->
                <?php
                $total_pages = ceil( $total / $per_page );
                if ( $total_pages > 1 ) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
                                $class = ( $page == $i ) ? 'button button-primary' : 'button'; ?>
                                <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php
    }
}
