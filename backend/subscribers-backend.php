<?php

defined('ABSPATH') || exit;

if (is_admin() && current_user_can('manage_options')) {

    /* Register Admin Menu */
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

    /* Display Subscriber List */
    function display_subscriber_list() {
        global $wpdb;
        $subscribers_table = $wpdb->prefix . "subscribers";

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
                $placeholders = implode(',', array_fill(0, count($site_ids), '%d'));
                $query = $wpdb->prepare("DELETE FROM $subscribers_table WHERE id IN ($placeholders)", ...$site_ids);
                $wpdb->query($query);
            }
            if ($user_ids) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                foreach ($user_ids as $user_id) {
                    wp_delete_user($user_id);
                }
            }
            echo '<div class="updated"><p>Selected subscribers have been deleted.</p></div>';
        }

        // Search
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
                "SELECT id, email, subscription_date, ip_address, user_agent
                FROM $subscribers_table
                WHERE email LIKE %s",
                $like
            ));
            $subscribers = $site_results;
        } else {
            $site_results = $wpdb->get_results("
                SELECT id, email, subscription_date, ip_address, user_agent
                FROM $subscribers_table
            ");
            $subscribers = $site_results;
        }

        // Sorting
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'subscription_date';
        usort($subscribers, function($a, $b) use ($order_dir, $orderby) {
            $valA = strtotime($a->subscription_date);
            $valB = strtotime($b->subscription_date);
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
            echo '<th>Email</th>';
            echo '<th><a href="' . esc_url(add_query_arg(['orderby' => 'subscription_date', 'order' => $order_toggle])) . '">Subscription Date' 
                . ($orderby === 'subscription_date' ? ($order_dir === 'ASC' ? ' ↑' : ' ↓') : '') 
                . '</a></th>';
            echo '<th>IP Address</th>';
            echo '<th>User Agent</th>';
            echo '</tr></thead><tbody>';

            foreach ($subscribers as $subscriber) {
                echo '<tr>';
                echo '<td><input type="checkbox" class="sub-check" name="subscriber_ids[]" value="' . esc_attr($subscriber->id) . '"></td>';
                echo '<td>' . esc_html($subscriber->email) . '</td>';
                echo '<td>' . esc_html(date('F j, Y H:i', strtotime($subscriber->subscription_date))) . '</td>';
                echo '<td>' . esc_html($subscriber->ip_address ?? '—') . '</td>';
                echo '<td style="max-width: 300px; word-break: break-word;">' . esc_html($subscriber->user_agent ?? '—') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            echo '<div style="margin-top: 10px;">';
            echo '<button type="submit" name="bulk_delete" class="button-primary">Delete Selected</button>';
            echo '<a href="' . admin_url('users.php') . '" class="button" style="margin-left: 10px;">Forum Users</a>';
            echo '</div>';
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

// Hide the Admin Bar for Forum Subscribers
add_action('after_setup_theme', 'hide_admin_bar_for_subscribers');
function hide_admin_bar_for_subscribers() {
    if ( current_user_can('subscriber') && !is_admin() ) {
        show_admin_bar(false);
    }
}
