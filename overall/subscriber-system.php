<?php
defined( 'ABSPATH' ) || exit;

/* =============================================================================
   1. DATABASE SETUP
   Creates wp_er_subscribers on Activation. Safe to re-run.
============================================================================= */

add_action( 'init', function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    if ( ! get_option( 'er_subscribers_table_created' ) ) {
        $table = $wpdb->prefix . 'er_subscribers';
        $sql   = "CREATE TABLE {$table} (
            id         BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            email      VARCHAR(191)     NOT NULL,
            token      VARCHAR(64)      NOT NULL,
            status     ENUM('pending','active') NOT NULL DEFAULT 'pending',
            created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) {$charset};";
        dbDelta( $sql );
        update_option( 'er_subscribers_table_created', true );
    }
} );

/* =============================================================================
   2. SMTP CONFIGURATION (Self-contained)
   Guard prevents Double-Registration if Contact Form Snippet is also active.
============================================================================= */

function er_configure_smtp( $phpmailer ) {
    $phpmailer->isSMTP();
    $phpmailer->Host       = defined( 'SMTP_HOST' ) ? SMTP_HOST : '';
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = defined( 'SMTP_PORT' ) ? SMTP_PORT : 587;
    $phpmailer->Username   = defined( 'SMTP_USER' ) ? SMTP_USER : '';
    $phpmailer->Password   = defined( 'SMTP_PASS' ) ? SMTP_PASS : '';
    $phpmailer->SMTPSecure = 'tls';
    $phpmailer->From       = defined( 'SMTP_FROM' ) ? SMTP_FROM : '';
    $phpmailer->FromName   = defined( 'SMTP_FROMNAME' ) ? SMTP_FROMNAME : '';
}
if ( ! has_action( 'phpmailer_init', 'configure_smtp' ) ) {
    add_action( 'phpmailer_init', 'er_configure_smtp' );
}

/* =============================================================================
   3. SUBSCRIBER HELPERS
============================================================================= */

function er_get_subscribers( $status = 'active' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';
    return $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s", $status )
    );
}

function er_add_subscriber( $email ) {
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';
    $email = sanitize_email( $email );
    $existing = $wpdb->get_row(
        $wpdb->prepare( "SELECT id, status FROM {$table} WHERE email = %s", $email )
    );
    if ( $existing ) return 'exists';
    $token  = bin2hex( random_bytes( 32 ) );
    $result = $wpdb->insert( $table, [
        'email'  => $email,
        'token'  => $token,
        'status' => 'pending',
    ] );
    return $result ? $token : false;
}

function er_activate_subscriber( $email, $token ) {
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE email = %s AND token = %s AND status = 'pending'",
            $email, $token
        )
    );
    if ( ! $row ) return false;
    $wpdb->update( $table, [ 'status' => 'active' ], [ 'id' => $row->id ] );
    return true;
}

function er_remove_subscriber( $email, $token ) {
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';
    $email = sanitize_email( $email );
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s AND token = %s",
            $email, $token
        )
    );
    if ( ! $row ) return false;
    $wpdb->delete( $table, [ 'id' => $row->id ] );
    return true;
}

/* =============================================================================
   4. SECURITY HELPERS
============================================================================= */

function er_get_ip() {
    return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
}

function er_log_nonce_failure( $ip ) {
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'contact_nonce_log',
        [ 'ip' => $ip, 'failed_at' => current_time( 'mysql' ) ]
    );
}

/* =============================================================================
   5. SUBSCRIPTION FORM (Usage: [er_subscribe_form])
   Scripts load sitewide but only execute if the Form is present in the DOM.
============================================================================= */

add_shortcode( 'er_subscribe_form', function () {
    ob_start(); ?>
    <div class="er-subscribe-wrapper">
        <form id="er-subscribe-form" novalidate>
            <div class="er-field">
                <label for="er-email">Your E-mail Address <span aria-hidden="true">*</span></label>
                <input
                    type="email"
                    id="er-email"
                    name="er_email"
                    required
                    aria-required="true"
                    autocomplete="email"
                >
            </div>
            <!-- Honeypot: Hidden from real Users -->
            <div aria-hidden="true" style="position: absolute; left: -9999px; height: 1px; width: 1px; overflow: hidden;">
                <label for="er-website">Leave this empty</label>
                <input
                    type="text"
                    id="er-website"
                    name="middle_name"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>
            <!-- Silent Math Check -->
            <input type="hidden" name="math_check" value="7">
            <button type="submit" id="er-submit-btn">Subscribe</button>
            <div
                id="er-confirmation"
                role="status"
                aria-live="polite"
                aria-atomic="true"
                style="display: none;"
            ></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
} );

add_action( 'wp_footer', function () {
    $nonce = wp_create_nonce( 'er_subscribe_nonce' );
    ?>
    <style>
        .er-subscribe-wrapper {max-width: 350px; overflow: visible;}
        .er-subscribe-wrapper .er-field {margin-bottom: 1em;}
        .er-subscribe-wrapper label {display: block; margin-bottom: 0.5em;}
        .er-subscribe-wrapper input[type="email"] {
            display: block;
            width: 100%;
            padding: 10px;
            font-size: 16px;
            box-sizing: border-box;
            border-radius: 5px;
        }
        .er-subscribe-wrapper button[type="submit"] {
            display: block;
            width: auto;
            padding: 10px 25px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            border: none;
            background: #1e73be;
            color: #ffffff;
        }
        .er-subscribe-wrapper button[type="submit"]:hover,
        .er-subscribe-wrapper button[type="submit"]:focus {background: #c53030;}
        .er-subscribe-wrapper button[type="submit"]:focus {outline: 1px solid #1e73be; outline-offset: 2px;}
        .er-subscribe-wrapper input[type="email"]:focus {outline: 1px solid #1e73be; outline-offset: 2px;}
        .er-subscribe-wrapper #er-confirmation {margin-top: 1em; width: 100%; word-wrap: break-word; overflow-wrap: break-word;}
    </style>

    <script>
    document.addEventListener( 'DOMContentLoaded', function () {
        const form = document.getElementById( 'er-subscribe-form' );
        if ( ! form ) return; // Form not on this Page — Do nothing
        const confirmation = document.getElementById( 'er-confirmation' );
        const submitBtn    = document.getElementById( 'er-submit-btn' );
        const formLoadTime = Date.now();
        let lastSubmit     = 0;
        form.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            // Time Check
            if ( ( Date.now() - formLoadTime ) / 1000 < 3 ) return;
            // Resubmit Throttle
            if ( Date.now() - lastSubmit < 5000 ) {
                showMessage( 'Please wait a few seconds before submitting again.' );
                return;
            }
            lastSubmit = Date.now();
            // Honeypot
            const honeypot = form.querySelector( 'input[name="middle_name"]' );
            if ( honeypot && honeypot.value.trim() !== '' ) return;
            submitBtn.disabled    = true;
            submitBtn.textContent = 'Sending\u2026';
            const formData = new FormData( form );
            formData.append( 'action',   'er_subscribe_ajax' );
            formData.append( '_wpnonce', '<?php echo esc_js( $nonce ); ?>' );
            fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                method: 'POST',
                body:   formData,
            } )
            .then( res => res.json() )
            .then( data => {
                if ( data.success ) {
                    form.reset();
                    showMessage( data.data.message );
                } else {
                    showMessage( data.data && data.data.message ? data.data.message : 'Something went wrong. Please try again.' );
                }
            } )
            .catch( () => showMessage( 'Submission failed. Please try again.' ) )
            .finally( () => {
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Subscribe';
            } );
        } );
        function showMessage( msg ) {
            confirmation.textContent   = msg;
            confirmation.style.display = 'block';
            confirmation.focus();
        }
    } );
    </script>
    <?php
} );

/* =============================================================================
   6. FORM HANDLER (AJAX)
============================================================================= */

add_action( 'wp_ajax_er_subscribe_ajax',        'er_handle_subscribe_ajax' );
add_action( 'wp_ajax_nopriv_er_subscribe_ajax', 'er_handle_subscribe_ajax' );

function er_handle_subscribe_ajax() {
    $ip = er_get_ip();
    // Nonce
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'er_subscribe_nonce' ) ) {
        er_log_nonce_failure( $ip );
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }
    // Transient Rate Limit
    if ( get_transient( 'er_subscribe_ip_' . md5( $ip ) ) ) {
        wp_send_json_error( [ 'message' => 'Please wait before submitting again.' ] );
    }
    // Honeypot
    if ( trim( $_POST['middle_name'] ?? '' ) !== '' ) {
        wp_send_json_error();
    }
    // Silent Math Check
    if ( trim( $_POST['math_check'] ?? '' ) !== '7' ) {
        wp_send_json_error();
    }
    $email = sanitize_email( $_POST['er_email'] ?? '' );
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
    }
    set_transient( 'er_subscribe_ip_' . md5( $ip ), time(), 60 );
    $token = er_add_subscriber( $email );
    if ( $token === 'exists' ) {
        wp_send_json_error( [ 'message' => 'This email address is already registered.' ] );
    }
    if ( ! $token ) {
        wp_send_json_error( [ 'message' => 'Something went wrong. Please try again.' ] );
    }
    $confirm_url = add_query_arg( [
        'er_confirm' => 1,
        'email'      => urlencode( $email ),
        'token'      => $token,
    ], home_url() );
    $subject = 'Please confirm your subscription - ericroth.org';
    $body    = "Hi,\n\n"
             . "Please confirm your subscription by clicking the link below:\n\n"
             . $confirm_url . "\n\n"
             . "If you did not request this, simply ignore this email.\n\n"
             . get_bloginfo( 'name' );
    wp_mail( $email, $subject, $body );
    wp_send_json_success( [ 'message' => 'Almost there → Please check your inbox.' ] );
}

/* =============================================================================
   7. OPT-IN HANDLER
   Activates Subscriber, sends welcome E-mail, redirects after 2 Seconds.
============================================================================= */

add_action( 'init', function () {
    if ( empty( $_GET['er_confirm'] ) ) return;
    $email = sanitize_email( urldecode( $_GET['email'] ?? '' ) );
    $token = sanitize_text_field( $_GET['token'] ?? '' );
    if ( er_activate_subscriber( $email, $token ) ) {
        $subject = 'You\'re now subscribed - ericroth.org';
        $body    = "Hi,\n\n"
                 . "Your subscription to ericroth.org is confirmed.\n"
                 . "You'll receive a notification whenever new content is published.\n\n"
                 . get_bloginfo( 'name' );
        wp_mail( $email, $subject, $body );
        $home = esc_url( home_url() );
        wp_die(
            '<p>Your subscription is confirmed. Welcome!</p>'
            . '<p>You will be redirected to ericroth.org in 2 seconds.</p>'
            . '<p><a href="' . $home . '">Click here if you are not redirected automatically.</a></p>'
            . '<script>setTimeout(function(){ window.location.href="' . $home . '"; }, 2000);</script>',
            'Confirmed',
            [ 'response' => 200 ]
        );
    } else {
        wp_die(
            '<p>This confirmation link is invalid or has already been used.</p>',
            'Error',
            [ 'response' => 400 ]
        );
    }
} );

/* =============================================================================
   8. UNSUBSCRIBE HANDLER
   Removes Subscriber, redirects after 2 Seconds.
============================================================================= */

add_action( 'init', function () {
    if ( empty( $_GET['er_unsub'] ) ) return;
    $email = sanitize_email( urldecode( $_GET['email'] ?? '' ) );
    $token = sanitize_text_field( $_GET['token'] ?? '' );
    $home = esc_url( home_url() );
    if ( er_remove_subscriber( $email, $token ) ) {
        wp_die(
            '<p>You have been unsubscribed successfully.</p>'
            . '<p>You will be redirected to ericroth.org in 2 seconds.</p>'
            . '<p><a href="' . $home . '">Click here if you are not redirected automatically.</a></p>'
            . '<script>setTimeout(function(){ window.location.href="' . $home . '"; }, 2000);</script>',
            'Unsubscribed',
            [ 'response' => 200 ]
        );
    } else {
        wp_die(
            '<p>This unsubscribe link is invalid.</p>',
            'Error',
            [ 'response' => 400 ]
        );
    }
} );

/* =============================================================================
   9. PUBLISH TRIGGER
   Fires when any watched Post Type transitions to 'publish'.
============================================================================= */

add_action( 'transition_post_status', function ( $new, $old, $post ) {
    if ( $new !== 'publish' || $old === 'publish' ) return;
    $watched = [ 'post', 'my-interests', 'my-quotes' ];
    if ( ! in_array( $post->post_type, $watched, true ) ) return;
    $subscribers = er_get_subscribers( 'active' );
    if ( empty( $subscribers ) ) return;
    $title    = get_the_title( $post );
    $url      = get_permalink( $post );
    $excerpt  = has_excerpt( $post )
        ? get_the_excerpt( $post )
        : wp_trim_words( strip_tags( $post->post_content ), 30 );
    $sitename = get_bloginfo( 'name' );
    foreach ( $subscribers as $sub ) {
        $unsub_url = add_query_arg( [
            'er_unsub' => 1,
            'email'    => urlencode( $sub->email ),
            'token'    => $sub->token,
        ], home_url() );
        $subject = 'New post: ' . $title . ' — ' . $sitename;
        $body    = "Hi,\n\n"
                 . "A new post has just been published on {$sitename}:\n\n"
                 . "{$title}\n\n"
                 . "{$excerpt}\n\n"
                 . "Read it here:\n"
                 . "{$url}\n\n"
                 . "---\n"
                 . "To unsubscribe: {$unsub_url}";
        wp_mail( $sub->email, $subject, $body );
    }
}, 10, 3 );

/* =============================================================================
   10. ADMIN PAGE
   Top-level Menu → View, filter by Status, delete Subscribers.
============================================================================= */

add_action( 'admin_menu', function () {
    add_menu_page(
        'Subscribers',
        'Subscribers',
        'manage_options',
        'er-subscribers',
        'er_render_admin_page',
        'dashicons-groups',
        30
    );
} );

function er_render_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'er_subscribers';
    // Handle Single Delete
    if (
        isset( $_GET['er_delete'], $_GET['_wpnonce'] ) &&
        wp_verify_nonce( $_GET['_wpnonce'], 'er_delete_subscriber' )
    ) {
        $wpdb->delete( $table, [ 'id' => intval( $_GET['er_delete'] ) ] );
        echo '<div class="notice notice-success is-dismissible"><p>Subscriber deleted.</p></div>';
    }
    // Handle Bulk Delete
    if (
        isset( $_POST['bulk_delete'], $_POST['subscriber_ids'], $_POST['_wpnonce'] ) &&
        check_admin_referer( 'er_subscribers_action', '_wpnonce' )
    ) {
        $ids          = array_map( 'intval', $_POST['subscriber_ids'] );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids ) );
        echo '<div class="notice notice-success is-dismissible"><p>Selected subscribers deleted.</p></div>';
    }
    // Filter
    $status_filter = sanitize_text_field( $_GET['status'] ?? 'all' );
    $where         = $status_filter !== 'all'
        ? $wpdb->prepare( 'WHERE status = %s', $status_filter )
        : '';
    $subscribers = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC" );
    $total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $active      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
    $pending     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );
    $current_url = admin_url( 'admin.php?page=er-subscribers' );
    ?>
    <div class="wrap">
        <h1>Subscribers</h1>

        <ul class="subsubsub" role="navigation" aria-label="Filter subscribers by status">
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'all',     $current_url ) ); ?>" <?php echo $status_filter === 'all'     ? 'aria-current="page"' : ''; ?>>All <span class="count">(<?php echo $total; ?>)</span></a></li> |
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'active',  $current_url ) ); ?>" <?php echo $status_filter === 'active'  ? 'aria-current="page"' : ''; ?>>Active <span class="count">(<?php echo $active; ?>)</span></a></li> |
            <li><a href="<?php echo esc_url( add_query_arg( 'status', 'pending', $current_url ) ); ?>" <?php echo $status_filter === 'pending' ? 'aria-current="page"' : ''; ?>>Pending <span class="count">(<?php echo $pending; ?>)</span></a></li>
        </ul>

        <?php if ( empty( $subscribers ) ) : ?>
            <p>No subscribers found.</p>
        <?php else : ?>
            <form method="post">
                <?php wp_nonce_field( 'er_subscribers_action', '_wpnonce' ); ?>
                <table class="wp-list-table widefat fixed striped" role="table">
                    <thead>
                        <tr>
                            <th scope="col"><input type="checkbox" id="er-select-all" aria-label="Select all subscribers"></th>
                            <th scope="col">Email</th>
                            <th scope="col">Status</th>
                            <th scope="col">Subscribed</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $subscribers as $sub ) :
                            $delete_url = wp_nonce_url(
                                add_query_arg( [ 'page' => 'er-subscribers', 'er_delete' => $sub->id ], admin_url( 'admin.php' ) ),
                                'er_delete_subscriber'
                            ); ?>
                            <tr>
                                <td><input type="checkbox" name="subscriber_ids[]" value="<?php echo esc_attr( $sub->id ); ?>" aria-label="Select <?php echo esc_attr( $sub->email ); ?>"></td>
                                <td><?php echo esc_html( $sub->email ); ?></td>
                                <td><?php echo esc_html( ucfirst( $sub->status ) ); ?></td>
                                <td><?php echo esc_html( date( 'F j, Y H:i', strtotime( $sub->created_at ) ) ); ?></td>
                                <td><a href="<?php echo esc_url( $delete_url ); ?>" aria-label="Delete <?php echo esc_attr( $sub->email ); ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <br>
                <button type="submit" name="bulk_delete" class="button button-primary">Delete Selected</button>
            </form>

            <script>
            document.getElementById( 'er-select-all' ).addEventListener( 'change', function () {
                document.querySelectorAll( 'input[name="subscriber_ids[]"]' ).forEach( cb => cb.checked = this.checked );
            } );
            </script>
        <?php endif; ?>
    </div>
    <?php
}
