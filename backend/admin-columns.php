<?php
defined('ABSPATH') || exit;

function initialize_custom_admin_columns() {
    if (!is_admin() || !current_user_can('manage_options')) {
    return;
    }

    // === CUSTOM ADMIN COLUMNS ===

    function define_custom_columns_clean($type) {
        $columns = [];
        $columns['cb'] = '<input type="checkbox" />';
        $columns['id'] = __('ID');
        $columns['featured_image'] = __('Featured Image');
        $columns['title'] = __('Title');

        if ($type === 'page') {
            $columns['things'] = __('Things');
            $columns['depth'] = __('Depth');
            $columns['parent'] = __('Parent');
        } elseif ($type === 'post') {
            $columns['post_categories'] = __('Categories');
			$columns['post_tags'] = __('Tags');
        } elseif ($type === 'my-interests') {
            $columns['topics'] = __('Topics');
			$columns['post_tags'] = __('Tags');
        } elseif ($type === 'my-traits') {
            $columns['types'] = __('Types');
        }

        $columns['custom_excerpt'] = __('Excerpt');
        $columns['word_count'] = __('Word Count');
        $columns['read_time'] = __('Read Time');
        $columns['date'] = __('Date');
        return $columns;
    }

    add_filter('manage_page_posts_columns', fn() => define_custom_columns_clean('page'));
    add_filter('manage_post_posts_columns', fn() => define_custom_columns_clean('post'));
    add_filter('manage_my-interests_posts_columns', fn() => define_custom_columns_clean('my-interests'));
    add_filter('manage_my-traits_posts_columns', fn() => define_custom_columns_clean('my-traits'));

    // === RENDER CONTENT ===

    function render_column_content($column, $post_id) {
        switch ($column) {
            case 'id':
                echo $post_id;
                break;
            case 'featured_image':
                echo get_the_post_thumbnail($post_id, [65, 65], ['style' => 'border-radius:4px;']);
                break;
            case 'custom_excerpt':
                echo wp_trim_words(get_the_excerpt($post_id), 35);
                break;
            case 'word_count':
                echo str_word_count(strip_tags(get_post_field('post_content', $post_id)));
                break;
            case 'read_time':
                $words = str_word_count(strip_tags(get_post_field('post_content', $post_id)));
                echo ceil($words / 200) . ' min';
                break;
            case 'things':
            case 'topics':
            case 'types':
            case 'post_categories':
			case 'post_tags':
                $taxonomy = match ($column) {
                    'things' => 'things',
                    'topics' => 'topics',
                    'types' => 'types',
                    'post_categories' => 'category',
					'post_tags' => 'post_tag',
                };
                $terms = get_the_terms($post_id, $taxonomy);
                if ($terms && !is_wp_error($terms)) {
                    $links = array_map(function ($term) use ($taxonomy) {
                        $url = admin_url("edit-tags.php?action=edit&taxonomy=$taxonomy&tag_ID={$term->term_id}");
                        return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($term->name) . '</a>';
                    }, $terms);
                    echo implode(', ', $links);
                } else {
                    echo '—';
                }
                break;
            case 'depth':
                echo count(get_post_ancestors($post_id));
                break;
            case 'parent':
                $parent = wp_get_post_parent_id($post_id);
                if ($parent) {
                    $title = get_the_title($parent);
                    $url = get_edit_post_link($parent);
                    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a>';
                } else {
                    echo __('(None)');
                }
                break;
        }
    }

    add_action('manage_page_posts_custom_column', 'render_column_content', 10, 2);
    add_action('manage_post_posts_custom_column', 'render_column_content', 10, 2);
    add_action('manage_my-interests_posts_custom_column', 'render_column_content', 10, 2);
    add_action('manage_my-traits_posts_custom_column', 'render_column_content', 10, 2);

    // === SORTABLE COLUMNS ===

    function set_sortables($columns) {
        return [
            'id' => 'ID',
            'title' => 'title',
			'post_categories' => 'post_categories',
			'topics' => 'topics',
            'date' => 'date',
        ];
    }

    add_filter('manage_edit-page_sortable_columns', 'set_sortables');
    add_filter('manage_edit-post_sortable_columns', 'set_sortables');
    add_filter('manage_edit-my-interests_sortable_columns', 'set_sortables');
    add_filter('manage_edit-my-traits_sortable_columns', 'set_sortables');

	add_filter('posts_clauses', function($clauses, $query) {
		global $wpdb;
		if (!is_admin() || !$query->is_main_query()) {
			return $clauses;
		}
		$orderby = $query->get('orderby');
		$taxonomy_map = [
			'post_categories' => 'category',
			'topics'          => 'topics',
		];
		if (!isset($taxonomy_map[$orderby])) {
			return $clauses;
		}
		$taxonomy = $taxonomy_map[$orderby];
		$order    = strtoupper($query->get('order')) === 'ASC' ? 'ASC' : 'DESC';
		$clauses['join'] .= "
			LEFT JOIN {$wpdb->term_relationships} tr ON {$wpdb->posts}.ID = tr.object_id
			LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
		";
		$clauses['where'] .= $wpdb->prepare(" AND tt.taxonomy = %s ", $taxonomy);
		$clauses['orderby'] = " t.name $order ";
		return $clauses;
	}, 10, 2);

    // === OPTIONAL STYLES ===

    add_action('admin_head', function () {
        global $typenow;
        $styles = [
            'page' => '
                .post-type-page .column-id { width: 5%; }
                .post-type-page .column-featured_image { width: 8%; }
                .post-type-page .column-title { width: 12%; }
                .post-type-page .column-things { width: 9%; }
                .post-type-page .column-depth { width: 3%; }
                .post-type-page .column-parent { width: 7%; }
                .post-type-page .column-custom_excerpt { width: 20%; }
                .post-type-page .column-word_count { width: 6%; }
                .post-type-page .column-read_time { width: 5%; }
                .post-type-page .column-date { width: 10%; }
            ',
            'post' => '
                .post-type-post .column-id { width: 5%; }
                .post-type-post .column-featured_image { width: 8%; }
                .post-type-post .column-title { width: 12%; }
                .post-type-post .column-post_categories { width: 8%; }
				.post-type-post .column-post_tags { width: 8%; }
                .post-type-post .column-custom_excerpt { width: 25%; }
                .post-type-post .column-word_count { width: 5%; }
                .post-type-post .column-read_time { width: 5%; }
                .post-type-post .column-date { width: 10%; }
            ',
            'my-interests' => '
                .post-type-my-interests .column-id { width: 5%; }
                .post-type-my-interests .column-featured_image { width: 8%; }
                .post-type-my-interests .column-title { width: 12%; }
                .post-type-my-interests .column-topics { width: 8%; }
				.post-type-my-interests .column-post_tags { width: 8%; }
                .post-type-my-interests .column-custom_excerpt { width: 25%; }
                .post-type-my-interests .column-word_count { width: 5%; }
                .post-type-my-interests .column-read_time { width: 5%; }
                .post-type-my-interests .column-date { width: 10%; }
            ',
            'my-traits' => '
                .post-type-my-traits .column-id { width: 5%; }
                .post-type-my-traits .column-featured_image { width: 8%; }
                .post-type-my-traits .column-title { width: 12%; }
                .post-type-my-traits .column-types { width: 10%; }
                .post-type-my-traits .column-custom_excerpt { width: 30%; }
                .post-type-my-traits .column-word_count { width: 6%; }
                .post-type-my-traits .column-read_time { width: 5%; }
                .post-type-my-traits .column-date { width: 10%; }
            '
        ];
        echo '<style>' . ($styles[$typenow] ?? '') . '
            .wp-list-table {
                table-layout: auto !important;
                width: 100%;
            }
        </style>';
    });

    // === MEDIA CUSTOM COLUMNS ===

    add_filter('manage_upload_columns', function ($columns) {
        $new = [];
        $new['cb'] = $columns['cb'];
        $new['id'] = __('ID');
        $new['icon'] = $columns['icon'];
        $new['title'] = $columns['title'];
        $new['uploaded_to'] = __('Uploaded To');
        $new['dimensions'] = __('Dimensions');
        $new['file_size'] = __('File Size');
        $new['available_sizes'] = __('Available Sizes');
        $new['file_format'] = __('File Format');
        $new['date'] = $columns['date'];
        return $new;
    });

    add_action('manage_media_custom_column', function ($column, $post_id) {
        switch ($column) {
            case 'id':
                echo $post_id;
                break;
            case 'uploaded_to':
                $parent_id = wp_get_post_parent_id($post_id);
                if ($parent_id) {
                    $title = _draft_or_post_title($parent_id);
                    $url = get_edit_post_link($parent_id);
                    echo '<strong><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a></strong>';
                } else {
                    echo '<em>' . __('(Unattached)') . '</em>';
                    // Native "Attach" link
                    if (current_user_can('edit_post', $post_id)) {
                        $url = admin_url('upload.php?attach=' . $post_id);
                        echo '<br><a class="hide-if-no-js" onclick="findPosts.open(\'media[]\', \'' . esc_attr($post_id) . '\');return false;" href="' . esc_url($url) . '">' . __('Attach') . '</a>';
                    }
                }
                break;
            case 'dimensions':
                $meta = wp_get_attachment_metadata($post_id);
                echo isset($meta['width']) ? "{$meta['width']}×{$meta['height']}" : '—';
                break;
            case 'file_size':
                $file = get_attached_file($post_id);
                echo file_exists($file) ? size_format(filesize($file)) : '—';
                break;
            case 'available_sizes':
                $meta = wp_get_attachment_metadata($post_id);
                echo !empty($meta['sizes']) ? implode(', ', array_keys($meta['sizes'])) : '—';
                break;
            case 'file_format':
                $file = get_attached_file($post_id);
                if (!$file || !file_exists($file)) {
                    echo '—';
                    break;
                }
                $formats = [];
                $original_ext = strtoupper(pathinfo($file, PATHINFO_EXTENSION));
                if ($original_ext) {
                    $formats[] = $original_ext;
                }
                $webp_file = $file . '.webp';
                if (file_exists($webp_file)) {
                    $formats[] = 'WEBP';
                }
                echo $formats ? implode(', ', $formats) : '—';
                break;
        }
    }, 10, 2);

    add_filter('manage_upload_sortable_columns', function ($columns) {
        $columns['id'] = 'ID';
        $columns['date'] = 'date';
        return $columns;
    });

    add_action('admin_head-upload.php', function () {
        echo '<style>
            .upload-php .column-id { width: 5%; }
            .upload-php .column-icon { width: 8%; }
            .upload-php .column-title { width: 22%; }
            .upload-php .column-uploaded_to { width: 12%; }
            .upload-php .column-dimensions { width: 10%; }
            .upload-php .column-file_size { width: 8%; }
            .upload-php .column-available_sizes { width: 15%; }
            .upload-php .column-file_format { width: 10%; }
            .upload-php .column-date { width: 10%; }
            .wp-list-table {
                table-layout: auto !important;
                width: 100%;
            }
        </style>';
    });

}

add_action('admin_init', 'initialize_custom_admin_columns');
