<?php
defined('ABSPATH') || exit;

// =====================================
// REGISTER TERM META
// =====================================

add_action('init', function() {
    register_term_meta('term', 'er_term_image_id', [
        'type'              => 'integer',
        'single'            => true,
        'sanitize_callback' => 'absint',
    ]);
});

// =====================================
// ADD AND EDIT FIELD
// =====================================

function er_term_image_add_field() {
    ?>
    <div class="form-field er-term-image-wrap">
        <label>Term Image</label>
        <div class="er-term-image-preview"></div>
        <input type="hidden" name="er_term_image_id" class="er-term-image-id" value="">
        <button type="button" class="button er-term-image-btn">Select Image</button>
        <button type="button" class="button er-term-image-remove" style="display:none;">Remove</button>
    </div>
    <?php
}

function er_term_image_edit_field($term) {
    $img_id = (int) get_term_meta($term->term_id, 'er_term_image_id', true);
    // Fallback to direct Query if Cache fails
    if (!$img_id) {
        global $wpdb;
        $img_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->termmeta} 
             WHERE term_id = %d AND meta_key = 'er_term_image_id'",
            $term->term_id
        ));
    }
    $img = $img_id ? wp_get_attachment_image($img_id, 'medium') : '';
    ?>
    <tr class="form-field er-term-image-wrap">
        <th><label>Term Image</label></th>
        <td>
            <div class="er-term-image-preview"><?php echo $img; ?></div>
            <input type="hidden" name="er_term_image_id" class="er-term-image-id" value="<?php echo esc_attr($img_id); ?>">
            <button type="button" class="button er-term-image-btn">Select Image</button>
            <button type="button" class="button er-term-image-remove" <?php if (!$img_id) echo 'style="display:none;"'; ?>>Remove</button>
        </td>
    </tr>
    <?php
}

// =====================================
// SAVE
// =====================================

function er_term_image_save($term_id) {
    if (!isset($_POST['er_term_image_id'])) return;
    $img_id = absint($_POST['er_term_image_id']);
    if ($img_id) {
        update_term_meta($term_id, 'er_term_image_id', $img_id);
    } else {
        delete_term_meta($term_id, 'er_term_image_id');
    }
}

// =====================================
// HOOKS
// =====================================

add_action('init', function() {
    $taxonomies = get_taxonomies([], 'names');
    foreach ($taxonomies as $tax) {
        add_action("{$tax}_add_form_fields",  'er_term_image_add_field');
        add_action("{$tax}_edit_form_fields", 'er_term_image_edit_field');
        add_action("created_{$tax}",          'er_term_image_save', 10, 2);
        add_action("edited_{$tax}",           'er_term_image_save', 10, 2);
    }
}, 99);

// =====================================
// ADMIN JS
// =====================================

add_action('admin_enqueue_scripts', function() {
    if (empty($_GET['taxonomy'])) return;
    wp_enqueue_media();
    wp_register_script('er-term-image-admin', '');
    wp_enqueue_script('er-term-image-admin');
    wp_add_inline_script('er-term-image-admin', "
        (function($){
            function erInit(c){
                var btn   = c.find('.er-term-image-btn');
                var rem   = c.find('.er-term-image-remove');
                var prev  = c.find('.er-term-image-preview');
                var input = c.find('.er-term-image-id');
                if (!btn.length) return;
                btn.on('click', function(e){
                    e.preventDefault();
                    var frame = wp.media({
                        title: 'Select Image',
                        button: { text: 'Use this image' },
                        multiple: false
                    });
                    frame.on('select', function(){
                        var att = frame.state().get('selection').first().toJSON();
                        var url = (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ? att.sizes.thumbnail.url : att.url;
                        input.val(att.id);
                        prev.html('<img src=\"' + url + '\" style=\"max-width:100px;height:auto;\" />');
                        rem.show();
                    });
                    frame.open();
                });
                rem.on('click', function(e){
                    e.preventDefault();
                    input.val('');
                    prev.empty();
                    rem.hide();
                });
            }
            $(function(){
                $('.er-term-image-wrap').each(function(){
                    erInit($(this));
                });
            });
        })(jQuery);
    ");
});

// =====================================
// BLOCKSY HERO SUPPORT
// =====================================

add_filter('blocksy:hero:type-2:image:attachment_id', function ($attachment_id) {
    if (!is_tax() && !is_category() && !is_tag()) return $attachment_id;
    $term = get_queried_object();
    if (!$term instanceof WP_Term) return $attachment_id;
    $custom = (int) get_term_meta($term->term_id, 'er_term_image_id', true);
    return $custom ?: $attachment_id;
});
