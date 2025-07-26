<?php

add_action('admin_menu', function() {
    add_menu_page('Forum Structure', 'Forum Structure', 'manage_options', 'forum-structure', 'render_forum_structure', 'dashicons-format-chat');
});

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
});

function build_forum_tree($entries) {
    $tree = [];
    $lookup = [];

    foreach ($entries as $entry) {
        $entry->children = [];
        $lookup[$entry->id] = $entry;
    }

    foreach ($lookup as $entry) {
        if ($entry->parent_id && isset($lookup[$entry->parent_id])) {
            $lookup[$entry->parent_id]->children[] = $entry;
        } else {
            $tree[] = $entry;
        }
    }

    return $tree;
}

function render_forum_structure() {
    global $wpdb;

    // Inject CSS for Column Widths
    echo '<style>
        table.forum-structure {table-layout: fixed; width: 100%;}
        .forum-structure th:nth-child(1),
        .forum-structure td:nth-child(1) { width: 12%; }
        .forum-structure th:nth-child(2),
        .forum-structure td:nth-child(2) { width: 12%; }
        .forum-structure th:nth-child(3),
        .forum-structure td:nth-child(3) { width: 27%; }
        .forum-structure th:nth-child(4),
        .forum-structure td:nth-child(4) { width: 13%; }
        .forum-structure th:nth-child(5),
        .forum-structure td:nth-child(5) { width: 5%; }
        .forum-structure th:nth-child(6),
        .forum-structure td:nth-child(6) { width: 10%; }
        .forum-structure th:nth-child(7),
        .forum-structure td:nth-child(7) { width: 10%; }
    </style>';

    // Fetch all Entries ordered by Hierarchy and sort_order
    $all_entries = $wpdb->get_results("SELECT * FROM wp_custom_forum_structure ORDER BY parent_id ASC, sort_order ASC");

    // Add Structure Entry
    if (!empty($_POST['new_structure'])) {
        $wpdb->insert('wp_custom_forum_structure', [
            'name'        => sanitize_text_field($_POST['name']),
            'slug'        => sanitize_title($_POST['slug']),
            'description' => sanitize_textarea_field($_POST['description']),
            'parent_id'   => $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null,
            'icon_class'  => trim($_POST['icon_class']),
            'sort_order'  => intval($_POST['sort_order'])
        ]);
        wp_redirect(admin_url('admin.php?page=forum-structure'));
        exit;
    }

    // Update Structure Entry
    if (!empty($_POST['update_structure'])) {
        $id = intval($_POST['struct_id']);
        $wpdb->update('wp_custom_forum_structure', [
            'name'        => sanitize_text_field($_POST['name']),
            'slug'        => sanitize_title($_POST['slug']),
            'description' => sanitize_textarea_field($_POST['description']),
            'parent_id'   => $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null,
            'icon_class'  => trim($_POST['icon_class']),
            'sort_order'  => intval($_POST['sort_order'])
        ], ['id' => $id]);
        wp_redirect(admin_url('admin.php?page=forum-structure'));
        exit;
    }

    // Delete Structure Entry
    if (!empty($_POST['delete_structure'])) {
        $id = intval($_POST['struct_id']);
        $wpdb->delete('wp_custom_forum_structure', ['id' => $id]);
        wp_redirect(admin_url('admin.php?page=forum-structure'));
        exit;
    }

    // Add Title
    echo '<div class="wrap"><h1>🧱 Forum Structure Manager</h1>';

    // Add Form
    echo '<h2>Add New Entry</h2><form method="POST">';
    echo '<input type="hidden" name="new_structure" value="1">';
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row"><label for="name">Name</label></th><td><input type="text" name="name" required /></td></tr>';
    echo '<tr><th scope="row"><label for="slug">Slug</label></th><td><input type="text" name="slug" required /></td></tr>';
    echo '<tr><th scope="row"><label for="description">Description</label></th><td><textarea name="description" rows="3" style="width:100%; max-width:600px;"></textarea></td></tr>';
    echo '<tr><th scope="row"><label for="parent_id">Parent Forum</label></th><td>';
    echo '<select name="parent_id"><option value="">(No parent)</option>';
    foreach ($all_entries as $entry) {
        if ($entry->parent_id !== null) continue;
        echo '<option value="' . $entry->id . '">' . esc_html($entry->name) . '</option>';
    }
    echo '</select></td></tr>';
    echo '<tr><th scope="row"><label for="icon_class">FontAwesome Icon Class</label></th>';
    echo '<td><input type="text" name="icon_class" placeholder="e.g. fas fa-comments" /></td></tr>';
    echo '<tr><th scope="row"><label for="sort_order">Sort Order</label></th><td><input type="number" name="sort_order" value="0" style="width:80px;" /></td></tr>';
    echo '</tbody></table><p><input type="submit" class="button button-primary" value="Add Entry"></p></form>';

    // Render Table with Hierarchy
    $tree = build_forum_tree($all_entries);
    echo '<h2 style="padding-top: 25px;">Existing Structure</h2>';
    echo '<table class="widefat fixed striped forum-structure">';
    echo '<thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Parent</th><th>Sort</th><th>Icon</th><th>Actions</th></tr></thead>';
    function render_forum_rows($nodes, $all_entries, $depth = 0) {
        foreach ($nodes as $entry) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
            echo '<form method="POST"><tr>';
            echo '<input type="hidden" name="struct_id" value="' . intval($entry->id) . '" />';
            echo '<td>' . $indent . '<input type="text" name="name" value="' . esc_attr($entry->name) . '" /></td>';
            echo '<td><input type="text" name="slug" value="' . esc_attr($entry->slug) . '" /></td>';
            echo '<td><textarea name="description" rows="2" style="width:100%; max-width:1000px; white-space:pre-wrap;">' . esc_textarea(stripslashes($entry->description)) . '</textarea></td>';
            echo '<td><select name="parent_id"><option value="">(No parent)</option>';
            foreach ($all_entries as $opt) {
                if ($opt->id === $entry->id) continue;
                $label = $opt->parent_id ? '↳ ' . $opt->name : $opt->name;
                $selected = $opt->id == $entry->parent_id ? 'selected' : '';
                echo '<option value="' . $opt->id . '" ' . $selected . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td>';
            echo '<td><input type="number" name="sort_order" value="' . intval($entry->sort_order) . '" style="width:80px;" /></td>';
            echo '<td class="icon-cell"><input type="text" name="icon_class" value="' . esc_attr($entry->icon_class) . '" placeholder="e.g. fas fa-comments" /></td>';
            echo '<td>';
            echo '<input type="submit" name="update_structure" value="Update" class="button button-primary" style="margin-right:6px;" />';
            echo '<input type="submit" name="delete_structure" value="Delete" class="button button-secondary" onclick="return confirm(\'Are you sure?\')" />';
            echo '</td>';
            echo '</tr></form>';

            if (!empty($entry->children)) {
                render_forum_rows($entry->children, $all_entries, $depth + 1);
            }
        }
    }
    render_forum_rows($tree, $all_entries);
    echo '</tbody></table></div>';
}
