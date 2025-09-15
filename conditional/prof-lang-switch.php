<?php

// === CONDITIONAL INJECTION BASED ON PATH ===
function should_inject_lang_script() {
    $pairs = array(
        // English
        '/about-me',
        '/about-me/contact',
        '/professional',
        '/professional/my-background',
        '/professional/my-background/my-competencies',
        '/professional/my-background/my-traits',
        '/professional/my-compass',
        '/professional/my-publications',
        '/professional/my-availability',
        // Deutsch
        '/ueber-mich',
        '/ueber-mich/kontakt',
        '/berufswelt',
        '/berufswelt/mein-hintergrund',
        '/berufswelt/mein-hintergrund/meine-kompetenzen',
        '/berufswelt/mein-hintergrund/meine-eigenschaften',
        '/berufswelt/mein-kompass',
        '/berufswelt/meine-publikationen',
        '/berufswelt/meine-verfuegbarkeit'
    );
    $pairs = array_map(function($p){
        return rtrim($p, '/');
    }, $pairs);
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $current_path = parse_url($request_uri, PHP_URL_PATH);
    $current_path = rtrim($current_path, '/');
    return in_array($current_path, $pairs, true);
}

// === SCRIPT + STYLE INJECTION ===
add_action('wp_footer', function() {
    if (!should_inject_lang_script()) return;
    ?>
    <script>
        (function() {
            'use strict';
            let isProcessing = false;
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    document.body.classList.add('lang-ready');
                    document.querySelectorAll('.lang-flag').forEach(function(flag) {
                        flag.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (isProcessing) return;
                            const target = flag.getAttribute('data-target');
                            if (target) {
                                isProcessing = true;
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
                } catch (error) {
                    console.warn('Language switcher initialization failed:', error);
                }
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
?>
