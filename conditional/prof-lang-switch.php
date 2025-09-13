<?php

// === CONDITIONAL INJECTION BASED ON PATH ===
function should_inject_lang_script() {
    $pairs = array(
        // English
        '/professional',
        '/professional/my-background',
        '/professional/my-background/my-competencies',
        '/professional/my-background/my-traits',
        '/professional/my-compass',
        '/professional/my-publications',
        '/professional/my-availability',
        // Deutsch
        '/beruflich',
        '/beruflich/mein-hintergrund',
        '/beruflich/mein-hintergrund/meine-kompetenzen',
        '/beruflich/mein-hintergrund/meine-eigenschaften',
        '/beruflich/mein-kompass',
        '/beruflich/meine-publikationen',
        '/beruflich/meine-verfuegbarkeit'
    );
    $current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $current_path = rtrim($current_path, '/');
    return in_array($current_path, $pairs);
}

// === LANGUAGE REDIRECT BASED ON BROWSER SETTINGS ===
add_action('wp_head', function() {
    if (!should_inject_lang_script()) return;
    ?>
    <script>
    (function() {
        const preferredLang = navigator.language || navigator.userLanguage || '';
        const isGerman = preferredLang.toLowerCase().startsWith('de');
        const hasSwitched = sessionStorage.getItem('langSwitched') === 'true';
        const path = window.location.pathname.replace(/\/$/, '');
        const pairs = {
            // English → Deutsch
            '/professional':                                    {target: '/beruflich', lang: 'de'},
            '/professional/my-background':                      {target: '/beruflich/mein-hintergrund', lang: 'de'},
            '/professional/my-background/my-competencies':      {target: '/beruflich/mein-hintergrund/meine-kompetenzen', lang: 'de'},
            '/professional/my-background/my-traits':            {target: '/beruflich/mein-hintergrund/meine-eigenschaften', lang: 'de'},
            '/professional/my-compass':                         {target: '/beruflich/mein-kompass', lang: 'de'},
            '/professional/my-publications':                    {target: '/beruflich/meine-publikationen', lang: 'de'},
            '/professional/my-availability':                    {target: '/beruflich/meine-verfuegbarkeit', lang: 'de'},
            // Deutsch → English
            '/beruflich':                                       {target: '/professional', lang: 'en'},
            '/beruflich/mein-hintergrund':                      {target: '/professional/my-background', lang: 'en'},
            '/beruflich/mein-hintergrund/meine-kompetenzen':    {target: '/professional/my-background/my-competencies', lang: 'en'},
            '/beruflich/mein-hintergrund/meine-eigenschaften':  {target: '/professional/my-background/my-traits', lang: 'en'},
            '/beruflich/mein-kompass':                          {target: '/professional/my-compass', lang: 'en'},
            '/beruflich/meine-publikationen':                   {target: '/professional/my-publications', lang: 'en'},
            '/beruflich/meine-verfuegbarkeit':                  {target: '/professional/my-availability', lang: 'en'}
        };
        if (hasSwitched || !pairs[path]) return;
        const target = pairs[path].target;
        const lang = pairs[path].lang;
        if ((isGerman && lang === 'de') || (!isGerman && lang === 'en')) {
            sessionStorage.setItem('langSwitched', 'true');
            window.location.replace(target);
        }
    })();
    </script>
    <?php
});

// === SCRIPT + STYLE INJECTION ===
add_action('wp_footer', function() {
    if (!should_inject_lang_script()) return;
    ?>
    <script>
    (function() {
        'use strict';
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('lang-ready');
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
