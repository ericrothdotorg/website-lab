<?php
defined('ABSPATH') || exit;

// ================================================
// CONDITIONAL FLAG INJECTION BASED ON PATH
// ================================================

function should_inject_lang_script() {
    $language_pairs = array(
        '/about-me'                                         => '/ueber-mich',
        '/about-me/contact'                                 => '/ueber-mich/kontakt',
        '/professional'                                     => '/berufswelt',
        '/professional/my-background'                       => '/berufswelt/mein-hintergrund',
        '/professional/my-background/my-competencies'       => '/berufswelt/mein-hintergrund/meine-kompetenzen',
        '/professional/my-background/my-traits'             => '/berufswelt/mein-hintergrund/meine-eigenschaften',
        '/professional/my-compass'                          => '/berufswelt/mein-kompass',
        '/professional/my-publications'                     => '/berufswelt/meine-publikationen',
        '/professional/my-availability'                     => '/berufswelt/meine-verfuegbarkeit'
    );
    $all_valid_paths = array_merge(array_keys($language_pairs), array_values($language_pairs));
    $all_valid_paths = array_map(function($p) {
        return rtrim($p, '/');
    }, $all_valid_paths);
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $current_path = parse_url($request_uri, PHP_URL_PATH);
    $current_path = rtrim($current_path, '/');
    return in_array($current_path, $all_valid_paths, true);
}

// ================================================
// EARLY AUTO-DETECTION (runs before Page renders)
// ================================================

add_action('wp_head', function() {
    if (!should_inject_lang_script()) {
        return;
    }
    ?>
    <script>
        (function() {
            'use strict';
            const storedLanguage = localStorage.getItem('userLanguageChoice');
            if (storedLanguage) return;
            const browserLang = navigator.language || navigator.languages?.[0] || '';
            const langCode = browserLang.toLowerCase().split('-')[0];
            const currentPath = window.location.pathname.replace(/\/$/, '');
            const languagePairs = {
                '/about-me':                                        '/ueber-mich',
                '/about-me/contact':                                '/ueber-mich/kontakt',
                '/professional':                                    '/berufswelt',
                '/professional/my-background':                      '/berufswelt/mein-hintergrund',
                '/professional/my-background/my-competencies':      '/berufswelt/mein-hintergrund/meine-kompetenzen',
                '/professional/my-background/my-traits':            '/berufswelt/mein-hintergrund/meine-eigenschaften',
                '/professional/my-compass':                         '/berufswelt/mein-kompass',
                '/professional/my-publications':                    '/berufswelt/meine-publikationen',
                '/professional/my-availability':                    '/berufswelt/meine-verfuegbarkeit'
            };
            const reversePairs = {};
            Object.entries(languagePairs).forEach(([en, de]) => {
                reversePairs[de] = en;
            });
            const isOnEnglishPage = languagePairs.hasOwnProperty(currentPath);
            const isOnGermanPage  = reversePairs.hasOwnProperty(currentPath);
            if (langCode === 'de' && isOnEnglishPage) {
                const germanPath = languagePairs[currentPath];
                if (germanPath) { window.location.replace(germanPath); return; }
            } else if (langCode === 'en' && isOnGermanPage) {
                const englishPath = reversePairs[currentPath];
                if (englishPath) { window.location.replace(englishPath); return; }
            }
            if (!['de', 'en'].includes(langCode) && isOnGermanPage) {
                const englishPath = reversePairs[currentPath];
                if (englishPath) { window.location.replace(englishPath); }
            }
        })();
    </script>
    <?php
}, 1);

// ================================================
// FOOTER SCRIPTS & STYLES (CONDITIONAL)
// ================================================

add_action('wp_footer', function() {

    // === SCRIPT + STYLE INJECTION (for flag switcher) ===

    if (should_inject_lang_script()) {
        ?>
        <script>
            (function() {
                'use strict';
                let isProcessing = false;

                function insertFlagBeforeHeading() {
                    const flagWrapper = document.querySelector('.lang-flag-wrapper');
                    const heading = document.querySelector('main h3, .content-area h3, article h3');
                    if (!flagWrapper || !heading) return false;

                    const row = document.createElement('div');
                    row.className = 'lang-heading-row';
                    heading.parentNode.insertBefore(row, heading);
                    row.appendChild(flagWrapper);
                    row.appendChild(heading);
                    return true;
                }

                function tryInsertFlag() {
                    if (!insertFlagBeforeHeading()) {
                        setTimeout(tryInsertFlag, 100);
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    try {
                        document.body.classList.add('lang-ready');
                        tryInsertFlag();

                        document.querySelectorAll('.lang-flag').forEach(function(flag) {
                            flag.addEventListener('click', function(e) {
                                e.preventDefault();
                                if (isProcessing) return;
                                const target = flag.getAttribute('data-target');
                                if (target) {
                                    isProcessing = true;
                                    try {
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
            .lang-heading-row {
                display: flex;
                align-items: center;
                gap: 35px;
            }
            .lang-heading-row h3 {
                margin: 0;
            }
            .lang-flag-wrapper {
                position: static;
                display: flex;
                flex-shrink: 0;
            }
            .lang-flag {
                cursor: pointer;
                width: 36px;
                height: 20px;
                object-fit: cover;
                border: none;
                outline: none;
                transition: transform 0.2s ease;
            }
			.lang-flag:hover,
			.lang-flag:focus-visible {
				outline: var(--a11y-focus-width) solid var(--a11y-focus-color);
				outline-offset: var(--a11y-focus-offset);
			}
        </style>
        <?php
    }

    // === NINJA CHARACTER INJECTION ===

    if (
        is_page(array(
            // English Pages
            '179','87873','59078','55867','138768','113713','123635','65752','8977','100674','83147','55706','65756','87873','151412','51969',
            // German Pages
            '150455','150449','149904','149998','150034','150120','150200','150223','150233','151417')) ||
        is_single(array(
            // English Singles
            '140909','127567','127484','121987','121984','141168','121985','135495','121986','121983','150592',
            // German Singles
            '150592')) ||
        is_tax('topics', 124) ||
        is_tax('things', array(137, 258))
    ) {
        ?>
        <script>
            (function() {
                const initNinja = function() {
                    const targetContainer = document.querySelector(".hero-section[data-type='type-2'] > .entry-header.ct-container");
                    if (targetContainer) {
                        const wrapper = document.createElement("div");
                        wrapper.className = "custom-ninja-wrapper";
                        wrapper.setAttribute("role", "img");
                        wrapper.setAttribute("aria-label", "Illustration of a Ninja Character");

                        const link = document.createElement("a");
                        link.href  = "https://ericroth.org/services/";
                        link.style.pointerEvents = "auto";
                        link.title = "Ninja Services";
                        link.setAttribute("aria-label", "Visit Ninja Services Page");
                        link.setAttribute("role", "link");
                        link.setAttribute("tabindex", "0");

                        const img = document.createElement("img");
                        img.src     = "https://ericroth.org/wp-content/uploads/2025/08/Ninja-Character-Stroke-1px.png";
                        img.alt     = "Ninja Character Illustration";
                        img.className = "custom-ninja-image daneden-slideInRight";
                        img.width   = 100;
                        img.height  = 100;
                        img.loading = "lazy";
                        img.decoding = "async";
                        img.setAttribute("fetchpriority", "low");

                        link.appendChild(img);
                        wrapper.appendChild(link);
                        targetContainer.appendChild(wrapper);
                    }
                };
                if ('requestIdleCallback' in window) {
                    requestIdleCallback(initNinja);
                } else {
                    window.addEventListener("load", initNinja);
                }
            })();
        </script>
        <style>
            .custom-ninja-wrapper {
                position: absolute;
                top: calc(50% - 25px);
                right: 25px;
                transform: translateY(-50%);
                z-index: 99;
                width: clamp(60px, calc(8vw + 4px), 100px);
                height: clamp(60px, calc(8vw + 4px), 100px);
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .custom-ninja-image {
                max-width: 100%;
                height: auto;
                border: 0;
            }
            .entry-header.ct-container .page-description p { /* THEME RELATED */
                margin-right: clamp(120px, calc(10vw + 20px), 150px);
            }
        </style>
        <?php
    }
});
