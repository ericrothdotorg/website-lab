<?php

defined('ABSPATH') || exit;

if (!is_admin()) return;

// Add admin menu
add_action('admin_menu', function() {
    if (!current_user_can('manage_options')) return;
    
    add_submenu_page(
        'tools.php',
        'Design Block Tracker',
        'Design Block Tracker',
        'manage_options',
        'design-block-tracker',
        'render_design_block_tracker'
    );
});

// Extracted functions for better code organization
function dbt_parse_blocks_recursively($blocks, $source, $title, $link, $type, &$pattern_usage = null) {
    $found = [];
    foreach ($blocks as $block) {
        $name = isset($block['blockName']) && $block['blockName'] !== null ? $block['blockName'] : '(unknown)';

        if ($name === 'core/block' && isset($block['attrs']['ref']) && $pattern_usage !== null) {
            $ref_id = $block['attrs']['ref'];
            $ref_post = get_post($ref_id);
            if ($ref_post) {
                $pattern_name = $ref_post->post_title ?: '(untitled pattern)';
                if (!isset($pattern_usage[$ref_id])) {
                    $pattern_usage[$ref_id] = ['name' => $pattern_name, 'used_in' => []];
                }
                $pattern_usage[$ref_id]['used_in'][] = [
                    'title' => $title, 'type' => $type, 'source' => $source, 'link' => $link
                ];
            }
        }

        if ($name !== '(unknown)') {
            $found[$name][] = [
                'title' => $title, 'link' => $link, 'type' => $type, 'source' => $source,
                'attrs' => isset($block['attrs']) ? $block['attrs'] : []
            ];
        }

        if (!empty($block['innerBlocks'])) {
            $inner = dbt_parse_blocks_recursively($block['innerBlocks'], $source, $title, $link, $type, $pattern_usage);
            foreach ($inner as $b => $entries) {
                if (!isset($found[$b])) $found[$b] = [];
                $found[$b] = array_merge($found[$b], $entries);
            }
        }
    }
    return $found;
}

function dbt_merge_usage(&$block_usage, $new_usage) {
    foreach ($new_usage as $block => $entries) {
        if (!isset($block_usage[$block])) $block_usage[$block] = [];
        $block_usage[$block] = array_merge($block_usage[$block], $entries);
    }
}

function dbt_scan_posts_of_type($post_type, $label, &$block_usage, &$pattern_usage) {
    try {
        // Get total count first
        $total_posts = wp_count_posts($post_type);
        $total_count = 0;
        foreach(['publish', 'draft', 'private', 'pending', 'future'] as $status) {
            $total_count += isset($total_posts->$status) ? $total_posts->$status : 0;
        }
        
        // Process in batches to avoid memory issues
        $batch_size = 50; // Process 50 posts at a time
        $processed = 0;
        
        for ($offset = 0; $offset < $total_count; $offset += $batch_size) {
            $items = get_posts([
                'post_type' => $post_type,
                'posts_per_page' => $batch_size,
                'offset' => $offset,
                'post_status' => ['publish', 'draft', 'private', 'pending', 'future', 'inherit'],
                'orderby' => 'title', 
                'order' => 'ASC',
            ]);
            
            if (empty($items)) break;
            
            foreach ($items as $item) {
                $content = $item->post_content ?? '';
                if (empty($content)) continue;
                
                $blocks = parse_blocks($content);
                if (empty($blocks)) continue;
                
                $usage = dbt_parse_blocks_recursively(
                    $blocks, $label, 
                    $item->post_title ?: '(untitled)', 
                    get_edit_post_link($item->ID, 'raw'), 
                    $post_type, 
                    $pattern_usage
                );
                dbt_merge_usage($block_usage, $usage);
                $processed++;
            }
            
            // Free memory after each batch
            unset($items);
            
            // Add a small delay to prevent overwhelming the server
            if ($offset > 0 && $offset % 200 === 0) {
                usleep(100000); // 0.1 second pause every 200 posts
            }
        }
        
        return $processed;
    } catch (Exception $e) {
        error_log('Design Block Tracker Error: ' . $e->getMessage());
        return 0;
    }
}

function dbt_get_cache_key() {
    return 'design_block_tracker_data_' . get_current_user_id();
}

function dbt_get_cached_data() {
    $cache_key = dbt_get_cache_key();
    $cached = get_transient($cache_key);
    return $cached !== false ? $cached : null;
}

function dbt_cache_data($data) {
    $cache_key = dbt_get_cache_key();
    set_transient($cache_key, $data, HOUR_IN_SECONDS); // Cache for 1 hour
}

// Main render function
function render_design_block_tracker() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Check for refresh request
    $force_refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
    
    // Try to get cached data first
    $cached_data = null;
    if (!$force_refresh) {
        $cached_data = dbt_get_cached_data();
    }
    
    echo '<div class="wrap"><h1>Design Block Tracker</h1>';
    echo '<p><a href="'.esc_url(add_query_arg('refresh', '1')).'" class="button button-primary">Refresh Data</a>';
    if ($cached_data) {
        echo ' <span style="color:#666;margin-left:10px;">(Using cached data)</span>';
    }
    echo '</p>';

    if ($cached_data) {
        // Use cached data
        extract($cached_data);
    } else {
        // Generate fresh data
        $block_usage = [];
        $pattern_usage = [];
        $catalog = ['my_patterns' => [], 'reusable_blocks' => []];
        $my_blocksy_blocks = [];
        $block_stats = ['total_blocks' => 0, 'unique_blocks' => 0, 'most_used' => '', 'most_used_count' => 0];

        // Section A: My Patterns (wp_block) AND wp_pattern - also use batching
        try {
            // Process wp_block in batches
            $total_blocks = wp_count_posts('wp_block');
            $total_wp_blocks = 0;
            foreach(['publish', 'draft', 'private', 'pending', 'future'] as $status) {
                $total_wp_blocks += isset($total_blocks->$status) ? $total_blocks->$status : 0;
            }
            
            $batch_size = 50;
            for ($offset = 0; $offset < $total_wp_blocks; $offset += $batch_size) {
                $wp_blocks = get_posts([
                    'post_type' => 'wp_block',
                    'posts_per_page' => $batch_size,
                    'offset' => $offset,
                    'post_status' => ['publish', 'draft', 'private', 'pending', 'future', 'inherit'],
                    'orderby' => 'title', 
                    'order' => 'ASC'
                ]);
                
                if (empty($wp_blocks)) break;
                
                foreach($wp_blocks as $b) {
                    $title = $b->post_title ?: '(untitled)';
                    $edit = get_edit_post_link($b->ID, 'raw');
                    $is_pattern = false;
                    
                    $terms = wp_get_post_terms($b->ID, 'wp_pattern_category', ['fields' => 'ids']);
                    if (!is_wp_error($terms) && !empty($terms)) $is_pattern = true;
                    
                    $sync = get_post_meta($b->ID, 'wp_pattern_sync_status', true);
                    if (!empty($sync)) $is_pattern = true;

                    // Add to catalog
                    if ($is_pattern) {
                        $catalog['my_patterns'][] = ['id' => $b->ID, 'title' => $title, 'link' => $edit, 'note' => 'wp_block (pattern)', 'used_in' => []];
                    } else {
                        $catalog['reusable_blocks'][] = ['id' => $b->ID, 'title' => $title, 'link' => $edit, 'note' => 'wp_block', 'used_in' => []];
                    }

                    // Parse content
                    if (!empty($b->post_content)) {
                        $usage = dbt_parse_blocks_recursively(
                            parse_blocks($b->post_content),
                            $is_pattern ? 'My Pattern' : 'Reusable Block',
                            $title, $edit, 'wp_block', $pattern_usage
                        );
                        dbt_merge_usage($block_usage, $usage);
                    }
                }
                unset($wp_blocks); // Free memory
            }

            // Include wp_pattern posts - also batched
            if (post_type_exists('wp_pattern')) {
                $total_patterns = wp_count_posts('wp_pattern');
                $total_wp_patterns = 0;
                foreach(['publish', 'draft', 'private', 'pending', 'future'] as $status) {
                    $total_wp_patterns += isset($total_patterns->$status) ? $total_patterns->$status : 0;
                }
                
                for ($offset = 0; $offset < $total_wp_patterns; $offset += $batch_size) {
                    $patterns = get_posts([
                        'post_type' => 'wp_pattern',
                        'posts_per_page' => $batch_size,
                        'offset' => $offset,
                        'post_status' => ['publish', 'draft', 'private', 'pending', 'future', 'inherit'],
                        'orderby' => 'title', 
                        'order' => 'ASC'
                    ]);
                    
                    if (empty($patterns)) break;
                    
                    foreach($patterns as $p) {
                        $title = $p->post_title ?: '(untitled)';
                        $edit = get_edit_post_link($p->ID, 'raw');
                        $catalog['my_patterns'][] = ['id' => $p->ID, 'title' => $title, 'link' => $edit, 'note' => 'wp_pattern', 'used_in' => []];
                        
                        if (!empty($p->post_content)) {
                            $usage = dbt_parse_blocks_recursively(
                                parse_blocks($p->post_content),
                                'My Pattern', $title, $edit, 'wp_pattern', $pattern_usage
                            );
                            dbt_merge_usage($block_usage, $usage);
                        }
                    }
                    unset($patterns); // Free memory
                }
            }
        } catch (Exception $e) {
            error_log('Design Block Tracker Error (Patterns): ' . $e->getMessage());
        }

        // Scan content for usage data
        dbt_scan_posts_of_type('page', 'Page', $block_usage, $pattern_usage);
        dbt_scan_posts_of_type('post', 'Post', $block_usage, $pattern_usage);
        dbt_scan_posts_of_type('wp_template', 'Template', $block_usage, $pattern_usage);
        dbt_scan_posts_of_type('wp_template_part', 'Template Part', $block_usage, $pattern_usage);
        
        // Scan custom post types
        $custom_post_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
        foreach ($custom_post_types as $cpt) {
            if ($cpt->name !== 'wp_block' && $cpt->name !== 'ct_content_block') {
                dbt_scan_posts_of_type($cpt->name, $cpt->label, $block_usage, $pattern_usage);
            }
        }

        // Update used_in arrays
        foreach ($catalog['my_patterns'] as &$cp) {
            if (!empty($cp['id']) && isset($pattern_usage[$cp['id']]['used_in'])) {
                $cp['used_in'] = $pattern_usage[$cp['id']]['used_in'];
            }
        }
        unset($cp);
        
        foreach ($catalog['reusable_blocks'] as &$rb) {
            if (!empty($rb['id']) && isset($pattern_usage[$rb['id']]['used_in'])) {
                $rb['used_in'] = $pattern_usage[$rb['id']]['used_in'];
            }
        }
        unset($rb);

        // Calculate stats ONLY from Section A patterns / blocks (not from content scans)
        $section_a_blocks = [];
        $section_a_total = 0;
        
        // Count blocks found in patterns and reusable blocks only
        foreach($block_usage as $block => $entries) {
            $section_a_count = 0;
            foreach($entries as $entry) {
                if($entry['source'] === 'My Pattern' || $entry['source'] === 'Reusable Block') {
                    $section_a_count++;
                }
            }
            if($section_a_count > 0) {
                $section_a_blocks[$block] = $section_a_count;
                $section_a_total += $section_a_count;
            }
        }
        
        $block_stats['unique_blocks'] = count($section_a_blocks);
        $block_stats['total_blocks'] = $section_a_total;
        $block_stats['pattern_count'] = count($catalog['my_patterns']) + count($catalog['reusable_blocks']);

        // Cache the results
        dbt_cache_data(compact('block_usage', 'pattern_usage', 'catalog', 'block_stats'));
    }

    // === Styles ===
    echo '<style>
        .dbt-stats {background: #f0f4f8; padding: 20px; border-radius: 8px; margin: 20px 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;}
        .dbt-stat {text-align: center;}
        .dbt-stat-value {font-size: 2em; font-weight: bold; color: #2271b1;}
        .dbt-stat-label {color: #666; margin-top: 5px;}
        .patterns-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 12px;}
        details {background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 6px; transition: box-shadow 0.2s;}
        details:hover {box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);}
        details summary {font-weight: bold; cursor: pointer; margin-bottom: 6px; user-select: none;}
        details summary:hover {color: #2271b1;}
        details[open] summary {margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #eee;}
        ul {margin: 8px 0 0 1em; padding: 0; list-style: disc;}
        ul li {margin: 4px 0; line-height: 1.6;}
        .section {margin-bottom: 28px;}
        .tag {display: inline-block; background: #eef2f7; color: #334; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-left: 8px;}
        .tag-post {background: #e7f5e7;}
        .tag-page {background: #fff4e6;}
        .tag-template {background: #f0e6ff;}
        .tag-wp_block {background: #ffe6e6;}
        .tag-wp_template {background: #f0e6ff;}
        .tag-wp_template_part {background: #f0e6ff;}
        .search-box {margin: 20px 0;}
        .search-box input {padding: 8px 12px; width: 300px; border: 1px solid #ddd; border-radius: 4px;}
        .empty-state {padding: 40px; text-align: center; background: #f9f9f9; border-radius: 8px; color: #666;}
        .pattern-item {background: #f8f9fa; padding: 12px; border-left: 3px solid #2271b1; margin-bottom: 12px; border-radius: 4px;}
        .pattern-title {font-weight: bold; margin-bottom: 8px;}
        .usage-list {margin-top: 8px; padding-left: 20px;}
        .usage-item {color: #666; font-size: 14px;}
        .no-usage {color: #999; font-style: italic;}
        .pattern-edit-link {float: right; font-size: 12px; color: #2271b1; text-decoration: none;}
        .pattern-edit-link:hover {text-decoration: underline;}
        .loading {opacity: 0.6; pointer-events: none;}
        .success-message {background: #d4edda; color: #155724; padding: 8px 12px; border-radius: 4px; margin: 10px 0;}
    </style>';

    // === JS ===
    echo '<script>
    function filterPatterns() {
        const search = document.getElementById("pattern-search").value.toLowerCase();
        document.querySelectorAll("#patterns-section .pattern-item").forEach(function(item){
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(search)?"block":"none";
        });
    }
    </script>';

    // === Stats Dashboard ===
    echo '<div class="dbt-stats">';
    echo '<div class="dbt-stat"><div class="dbt-stat-value">'.number_format($block_stats['pattern_count']).'</div><div class="dbt-stat-label">Total Patterns & Blocks</div></div>';
    echo '<div class="dbt-stat"><div class="dbt-stat-value">'.number_format($block_stats['unique_blocks']).'</div><div class="dbt-stat-label">Unique Block Types Used</div></div>';
    echo '<div class="dbt-stat"><div class="dbt-stat-value">'.number_format($block_stats['total_blocks']).'</div><div class="dbt-stat-label">Block Instances in Patterns</div></div>';
    echo '</div>';

    // === Section A: My Patterns ===
    echo '<div class="section" id="patterns-section"><h2>My Patterns (in Appearance → Design)</h2>';
    echo '<div class="search-box"><input type="text" id="pattern-search" placeholder="Search patterns..." onkeyup="filterPatterns()"></div>';
    
    $all_patterns = array_merge($catalog['my_patterns'], $catalog['reusable_blocks']);
    if(empty($all_patterns)){ 
        echo '<div class="empty-state">No patterns or reusable blocks found.</div>'; 
    } else {
        echo '<div class="patterns-grid">';
        usort($all_patterns, fn($a,$b) => strcasecmp($a['title'],$b['title']));
        foreach($all_patterns as $item){
            echo '<details class="pattern-item">';
            echo '<summary class="pattern-title">'.esc_html($item['title']);
            if(!empty($item['link'])) echo '<a href="'.esc_url($item['link']).'" target="_blank" class="pattern-edit-link">Edit →</a>';
            echo '</summary>';
            echo '<div><span class="tag">'.esc_html($item['note']).'</span></div>';
            if(!empty($item['used_in'])){
                echo '<div class="usage-list"><strong>Used in:</strong><ul>';
                foreach($item['used_in'] as $usage){
                    $usage_link = !empty($usage['link']) ? '<a href="'.esc_url($usage['link']).'" target="_blank">'.esc_html($usage['title']).'</a>' : esc_html($usage['title']);
                    echo '<li class="usage-item">'.$usage_link.' <span class="tag tag-'.esc_attr($usage['type']).'">'.esc_html($usage['source']).'</span></li>';
                }
                echo '</ul></div>';
            } else { 
                echo '<div class="no-usage">Not currently used in any content</div>'; 
            }
            echo '</details>';
        }
        echo '</div>';
    }
    echo '</div>';

    // === Export Data ===
    echo '<div class="section"><h2>Export Data</h2><p><button class="button" onclick="exportBlockData()">Export as JSON</button></p>';
    echo '<script>
    function exportBlockData(){
        const data='.json_encode(['block_usage'=>$block_usage,'pattern_usage'=>$pattern_usage,'catalog'=>$catalog,'stats'=>$block_stats],JSON_PRETTY_PRINT).';
        const blob=new Blob([JSON.stringify(data,null,2)],{type:"application/json"});
        const url=URL.createObjectURL(blob);
        const a=document.createElement("a");
        a.href=url;
        a.download="block-tracker-export-"+new Date().toISOString().split("T")[0]+".json";
        a.click();
        URL.revokeObjectURL(url);
    }
    </script></div>';

    echo '<p style="text-align:center;color:#666;margin-top:40px;">Block data last refreshed: '.current_time('mysql').'</p>';
    echo '</div>'; // .wrap
}

// AJAX endpoint
add_action('wp_ajax_refresh_block_tracker', function(){
    if(!current_user_can('manage_options')) wp_die('Unauthorized');
    
    // Clear cache
    $cache_key = dbt_get_cache_key();
    delete_transient($cache_key);
    
    wp_send_json_success(['message'=>'Data refreshed and cache cleared']);
});
