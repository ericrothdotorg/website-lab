<?php

// === CONFIGURATION ===
function get_language_pairs() {
    $cache_key = 'cached_language_pairs';
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;
    $pairs = [
        'professional' => 'beruflich',
        'professional/my-background' => 'beruflich/mein-hintergrund',
        'professional/my-compass' => 'beruflich/mein-kompass',
    ];
    set_transient($cache_key, $pairs, 12 * HOUR_IN_SECONDS);
    return $pairs;
}
function get_flag_meta($lang_code) {
    return [
        'de' => ['label' => 'Deutsch', 'flag' => 'https://flagcdn.com/w40/de.webp'],
        'en' => ['label' => 'English', 'flag' => 'https://flagcdn.com/w40/gb.webp'],
    ][$lang_code] ?? null;
}

// === SLUG RESOLUTION ===
function get_full_slug($post_id) {
    $post = get_post($post_id);
    if (!$post) return '';
    $slug = $post->post_name;
    $ancestors = get_post_ancestors($post_id);
    if ($ancestors) {
        $ancestor_slugs = array_map(function($id) {
            return get_post($id)->post_name;
        }, array_reverse($ancestors));
        $slug = implode('/', $ancestor_slugs) . '/' . $slug;
    }
    return $slug;
}

// === SHORTCODE ===
function professional_language_switcher() {
    $current_slug = get_full_slug(get_queried_object_id());
    if (!$current_slug) return '';
    $pairs = get_language_pairs();
    $target_slug = null;
    $lang_code = '';
    if (isset($pairs[$current_slug])) {
        $target_slug = $pairs[$current_slug];
        $lang_code = 'de';
    } else {
        foreach ($pairs as $en => $de) {
            if ($de === $current_slug) {
                $target_slug = $en;
                $lang_code = 'en';
                break;
            }
        }
    }
    if (!$target_slug || !$lang_code) return '';
    $target_page = get_page_by_path($target_slug);
    if (!$target_page || $target_page->post_status !== 'publish') return '';
    $meta = get_flag_meta($lang_code);
    if (!$meta) return '';
    $target_url = home_url('/' . $target_slug . '/');
    return sprintf(
        '<div class="lang-flag-wrapper"><img src="%s" alt="%s" title="%s" aria-label="Switch to %s version" loading="lazy" role="button" tabindex="0" lang="%s" data-target="%s" class="lang-flag"></div>',
        esc_url($meta['flag']),
        esc_attr($meta['label']),
        esc_attr($meta['label']),
        esc_attr($meta['label']),
        esc_attr($lang_code),
        esc_url_raw($target_url)
    );
}
add_shortcode('prof_lang_switch', 'professional_language_switcher');

// === SEO HREF LANG TAGS ===
add_action('wp_head', function() {
    $slug = get_full_slug(get_queried_object_id());
    $pairs = get_language_pairs();
    if (isset($pairs[$slug])) {
        echo '<link rel="alternate" hreflang="de" href="' . esc_url(home_url('/' . $pairs[$slug] . '/')) . '" />';
    } elseif (in_array($slug, $pairs)) {
        $english = array_search($slug, $pairs);
        echo '<link rel="alternate" hreflang="en" href="' . esc_url(home_url('/' . $english . '/')) . '" />';
    }

    // === EARLY REDIRECT TO TARGET LANGUAGE ===
    $current_slug = get_full_slug(get_queried_object_id());
    ?>
    <script>
    (function() {
        const currentSlug = <?php echo wp_json_encode($current_slug); ?>;
        const langPairs = <?php echo wp_json_encode(get_language_pairs()); ?>;
        const preferredLang = navigator.language || navigator.userLanguage || '';
        const isGerman = preferredLang.toLowerCase().startsWith('de');
        const hasSwitched = sessionStorage.getItem('langSwitched') === 'true';
        if (!hasSwitched && langPairs[currentSlug] && isGerman) {
            sessionStorage.setItem('langSwitched', 'true');
            window.location.replace('<?php echo home_url(); ?>/' + langPairs[currentSlug] + '/');
        }
    })();
    </script>
    <?php
});

// === SCRIPT + STYLE INJECTION ===
add_action('wp_footer', function() {
    $post = get_post(get_queried_object_id());
    if (!$post || !has_shortcode($post->post_content ?? '', 'prof_lang_switch')) return;
    $current_slug = get_full_slug(get_queried_object_id());
    ?>
    <script>
    (function() {
        'use strict';
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('lang-ready');
            const currentSlug = <?php echo wp_json_encode($current_slug); ?>;
            const langPairs = <?php echo wp_json_encode(get_language_pairs()); ?>;
            const preferredLang = navigator.language || navigator.userLanguage || '';
            const isGerman = preferredLang.toLowerCase().startsWith('de');
            const hasSwitched = sessionStorage.getItem('langSwitched') === 'true';
            if (!hasSwitched && langPairs[currentSlug] && isGerman) {
                sessionStorage.setItem('langSwitched', 'true');
                window.location.href = '<?php echo home_url(); ?>/' + langPairs[currentSlug] + '/';
                return;
            }
            document.querySelectorAll('.lang-flag').forEach(function(flag) {
                flag.addEventListener('click', function() {
                    const target = flag.getAttribute('data-target');
                    if (target) {
                        sessionStorage.setItem('langSwitched', 'true');
                        window.location.href = target;
                    }
                });
                flag.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        flag.click();
                    }
                });
            });
        });
    })();
    </script>
    <style>
        .ct-container-full .entry-content {position: relative;}
        .lang-flag-wrapper {position: absolute; top: 10px; right: 0px; display: flex; justify-content: flex-end; padding-right: 5px; visibility: hidden;}
        body.lang-ready .lang-flag-wrapper {visibility: visible;}
        .lang-flag {cursor: pointer; width: 36px; height: 20px; object-fit: cover; border: none; outline: none; transition: transform 0.2s ease;}
        .lang-flag:focus {outline: 1px solid #1e73be; outline-offset: 1px; box-shadow: 0 0 0 0.05rem rgba(0, 123, 255, 0.25);}
        .lang-flag:hover {transform: scale(1.05);}
        .lang-flag:active {transform: scale(0.95);}
    </style>
    <?php
});
