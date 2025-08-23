<?php

add_action('wp', function () {
    if (!is_page(140735)) return;

    // Inject Custom Styles for Asgaros Forum
    add_action('wp_head', function () {
        echo '<style>
            #af-wrapper a {color: #1e73be;}
            #af-wrapper a:hover {color: #c53030; text-decoration: none;}
            #af-wrapper #forum-header {background: #3A4F66;}
            #af-wrapper #forum-navigation a,
            #af-wrapper #forum-navigation-mobile a {border-left: none; font-weight: bold;}
            #af-wrapper #forum-navigation .home-link:hover,
            #af-wrapper #forum-navigation .profile-link:hover,
            #af-wrapper #forum-navigation .members-link:hover,
            #af-wrapper #forum-navigation .subscriptions-link:hover,
            #af-wrapper #forum-navigation .activity-link:hover,
            #af-wrapper #forum-navigation .logout-link:hover {background: #192a3d;}
            #af-wrapper #forum-breadcrumbs {color: #3A4F66 !important; padding-top: 10px;}
            #af-wrapper #forum-breadcrumbs a {color: #1e73be !important;}
            #af-wrapper #forum-breadcrumbs a:hover {color: #c53030 !important;}
            #af-wrapper .title-element {background: #3A4F66; border-bottom: none; border-radius: 0;}
            #af-wrapper .read {color: #3A4F66;}
            #af-wrapper .forum-title {font-weight: bold !important;}
            #af-wrapper .forum-stats {background: none !important; border: none !important;}
            #af-wrapper .forum-menu .button-red:hover,
            #af-wrapper .forum-menu .button-neutral:hover,
            #af-wrapper .forum-menu .topic-button-sticky:hover,
            #af-wrapper .forum-menu .forum-editor-button:hover {background: #c53030; color: #FFFFFF;}
            #af-wrapper .button-red {color: #FFFFFF; background: #c53030;}
            #af-wrapper .button-red:hover {color: #FFFFFF;}
            #af-wrapper .button-normal {color: #FFFFFF; background: #1e73be;}
            #af-wrapper .button-normal:hover {background: #c53030;}
        </style>';
    });

    // Restrict Uploads to Asgaros Forum Context
    add_filter('upload_dir', function ($dirs) {
        if (!is_asgaros_forum_upload()) return $dirs;

        $dirs['subdir'] = '/asgaros' . $dirs['subdir'];
        $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
        return $dirs;
    });

    add_action('add_attachment', function ($attachment_id) {
        if (is_asgaros_forum_upload()) {
            wp_set_object_terms($attachment_id, 63, 'rml-folder'); // Media Folder Number 63
        }
    });

    function is_asgaros_forum_upload(): bool {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $request = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($referer, 'asgarosforum') !== false ||
               strpos($request, 'asgarosforum') !== false ||
               (defined('DOING_AJAX') && DOING_AJAX && !empty($_POST['action']) && strpos($_POST['action'], 'asgaros') !== false);
    }

    // Load Editor Scripts for Everyone
    add_action('wp_enqueue_scripts', function () {
        wp_enqueue_script('jquery');
        wp_enqueue_script('editor');
        wp_enqueue_style('editor-buttons');
        wp_enqueue_script('quicktags');
    });

    // Enable TinyMCE and Quicktags for All Users
    add_filter('wp_editor_settings', function ($settings, $editor_id) {
        $settings['tinymce']   = true;
        $settings['quicktags'] = true;
        return $settings;
    }, 10, 2);

    // Inject Editor into Asgaros Forum Post Form
    add_filter('asgarosforum_custom_editor', function ($content) {
        ob_start();
        wp_editor('', 'asgaros_editor', [
            'textarea_name' => 'asgaros_content',
            'media_buttons' => false,
            'tinymce'       => true,
            'quicktags'     => true,
        ]);
        return ob_get_clean();
    });

    // Role-Based UI Adjustments
    add_action('wp_footer', function () {
        if (!is_page(140735)) return;

        if (current_user_can('administrator')) {
            // Admins: Keep both Tabs, show Toolbar dynamically
            echo '<script>
                document.addEventListener("DOMContentLoaded", function () {
                    const wrapper = document.querySelector("#af-wrapper");
                    if (!wrapper) return;
                    const toolbar = wrapper.querySelector(".quicktags-toolbar");
                    const codeTab = wrapper.querySelector(".wp-editor-tabs .switch-html");
                    const tabs = wrapper.querySelectorAll(".wp-editor-tabs button");
                    function updateToolbar() {
                        if (toolbar && codeTab) {
                            toolbar.style.display = codeTab.classList.contains("active") ? "block" : "none";
                        }
                    }
                    tabs.forEach(btn => btn.addEventListener("click", () => setTimeout(updateToolbar, 50)));
                    updateToolbar();
                });
            </script>';
        } else {
            // Non-admins: Remove "Code" Tab, keep Toolbar
            echo '<script>
                document.addEventListener("DOMContentLoaded", function () {
                    const codeTab = document.querySelector("#af-wrapper .wp-editor-tabs .switch-html");
                    if (codeTab) codeTab.remove();
                });
            </script>';
        }
    });
});
