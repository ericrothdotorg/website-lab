<?php

// === CONDITIONAL INJECTION BASED ON PATH ===

function should_inject_lang_script() {
    $language_pairs = array(
        // English -> German
        '/about-me' => '/ueber-mich',
        '/about-me/contact' => '/ueber-mich/kontakt',
        '/professional' => '/berufswelt',
        '/professional/my-background' => '/berufswelt/mein-hintergrund',
        '/professional/my-background/my-competencies' => '/berufswelt/mein-hintergrund/meine-kompetenzen',
        '/professional/my-background/my-traits' => '/berufswelt/mein-hintergrund/meine-eigenschaften',
        '/professional/my-background/profile-summary' => '/berufswelt/mein-hintergrund/mein-kurzprofil',
        '/professional/my-compass' => '/berufswelt/mein-kompass',
        '/professional/my-publications' => '/berufswelt/meine-publikationen',
        '/professional/my-availability' => '/berufswelt/meine-verfuegbarkeit'
    );
    // Create a flat array of all valid paths (English + German)
    $all_valid_paths = array_merge(array_keys($language_pairs), array_values($language_pairs));
    // Remove trailing slashes from all paths
    $all_valid_paths = array_map(function($p) {
        return rtrim($p, '/');
    }, $all_valid_paths);
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $current_path = parse_url($request_uri, PHP_URL_PATH);
    $current_path = rtrim($current_path, '/');
    return in_array($current_path, $all_valid_paths, true);
}

// === SCRIPT + STYLE INJECTION ===

add_action('wp_footer', function() {
    if (!should_inject_lang_script()) return;
    ?>
    <script>
        (function() {
            'use strict';
            let isProcessing = false;
            // Auto-detect and redirect based on browser language
            const autoDetectLanguage = () => {
                // Check if user has already made a language choice
                const storedLanguage = localStorage.getItem('userLanguageChoice');
                if (storedLanguage) return; // Don't auto-redirect if user has made a choice
                // Get browser language (first preference)
                const browserLang = navigator.language || navigator.languages?.[0] || '';
                const langCode = browserLang.toLowerCase().split('-')[0]; // Get just the language part
                // Get current page info
                const currentPath = window.location.pathname.replace(/\/$/, ''); // Remove trailing slash
                // Define language pairs - English path maps to German path
                const languagePairs = {
                    // English -> German
                    '/about-me': '/ueber-mich',
                    '/about-me/contact': '/ueber-mich/kontakt',
                    '/professional': '/berufswelt',
                    '/professional/my-background': '/berufswelt/mein-hintergrund',
                    '/professional/my-background/my-competencies': '/berufswelt/mein-hintergrund/meine-kompetenzen',
                    '/professional/my-background/my-traits': '/berufswelt/mein-hintergrund/meine-eigenschaften',
                    '/professional/my-background/profile-summary': '/berufswelt/mein-hintergrund/mein-kurzprofil',
                    '/professional/my-compass': '/berufswelt/mein-kompass',
                    '/professional/my-publications': '/berufswelt/meine-publikationen',
                    '/professional/my-availability': '/berufswelt/meine-verfuegbarkeit'
                };
                // Create reverse mapping (German -> English)
                const reversePairs = {};
                Object.entries(languagePairs).forEach(([en, de]) => {
                    reversePairs[de] = en;
                });
                // Determine if we're on English or German page
                const isOnEnglishPage = languagePairs.hasOwnProperty(currentPath);
                const isOnGermanPage = reversePairs.hasOwnProperty(currentPath);
                // Auto-redirect logic
                if (langCode === 'de' && isOnEnglishPage) {
                    // Browser is German but on English page, redirect to German
                    const germanPath = languagePairs[currentPath];
                    if (germanPath) {
                        window.location.href = germanPath;
                        return;
                    }
                } else if (langCode === 'en' && isOnGermanPage) {
                    // Browser is English but on German page, redirect to English
                    const englishPath = reversePairs[currentPath];
                    if (englishPath) {
                        window.location.href = englishPath;
                        return;
                    }
                }
                // Fallback for unrecognized languages: default to English if on German page
                if (!['de', 'en'].includes(langCode) && isOnGermanPage) {
                    const englishPath = reversePairs[currentPath];
                    if (englishPath) {
                        window.location.href = englishPath;
                    }
                }
            };
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    // First, try auto-detection
                    autoDetectLanguage();
                    document.body.classList.add('lang-ready');
                    document.querySelectorAll('.lang-flag').forEach(function(flag) {
                        flag.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (isProcessing) return;
                            const target = flag.getAttribute('data-target');
                            if (target) {
                                isProcessing = true;
                                // Store user's manual language choice
                                try {
                                    // Determine language based on target URL
                                    const germanPaths = ['/ueber-mich', '/berufswelt'];
                                    const isGermanTarget = germanPaths.some(path => target.includes(path));
                                    const targetLang = isGermanTarget ? 'de' : 'en';
                                    localStorage.setItem('userLanguageChoice', targetLang);
                                } catch (error) {
                                    console.warn('Could not store language preference:', error);
                                }
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
