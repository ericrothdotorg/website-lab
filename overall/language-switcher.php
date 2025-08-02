<?php

add_action('wp_footer', function () {
?>
<div id="google_translate_element_wrapper" class="closed" role="region" aria-label="Language Switcher" aria-hidden="true">
    <div id="language-flags">
        <span id="reset-language" title="Reset Language" role="button" aria-label="Reset Language" tabindex="0"
            onclick="resetTranslation()" 
            onkeydown="if(event.key === 'Enter' || event.key === ' ') resetTranslation();" 
            style="margin-right: 8px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="#FFFFFF" viewBox="0 0 24 24" style="cursor: pointer;">
                <title>Reset Language</title>
                <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6a6.003 6.003 0 0 1-5.65-4H4.26a8.003 8.003 0 0 0 15.47-2c0-4.42-3.58-8-8-8z"/>
            </svg>
        </span>
        <img src="https://flagcdn.com/w40/sa.png" alt="العربية" title="العربية" role="button" tabindex="0" lang="ar" onclick="handleFlagClick('ar')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('ar');">
        <img src="https://flagcdn.com/w40/id.png" alt="Bahasa" title="Bahasa" role="button" tabindex="0" lang="id" onclick="handleFlagClick('id')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('id');">
        <img src="https://flagcdn.com/w40/cn.png" alt="中文" title="中文" role="button" tabindex="0" lang="zh-CN" onclick="handleFlagClick('zh-CN')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('zh-CN');">
        <img src="https://flagcdn.com/w40/dk.png" alt="Dansk" title="Danish" role="button" tabindex="0" lang="da" onclick="handleFlagClick('da')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('da');">
        <img src="https://flagcdn.com/w40/de.png" alt="Deutsch" title="Deutsch" role="button" tabindex="0" lang="de" onclick="handleFlagClick('de')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('de');">
        <img src="https://flagcdn.com/w40/es.png" alt="Español" title="Español" role="button" tabindex="0" lang="es" onclick="handleFlagClick('es')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('es');">
        <img src="https://flagcdn.com/w40/fr.png" alt="Français" title="Français" role="button" tabindex="0" lang="fr" onclick="handleFlagClick('fr')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('fr');">
        <img src="https://flagcdn.com/w40/in.png" alt="हिन्दी" title="हिन्दी" role="button" tabindex="0" lang="hi" onclick="handleFlagClick('hi')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('hi');">
        <img src="https://flagcdn.com/w40/it.png" alt="Italiano" title="Italiano" role="button" tabindex="0" lang="it" onclick="handleFlagClick('it')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('it');">
        <img src="https://flagcdn.com/w40/jp.png" alt="日本語" title="日本語" role="button" tabindex="0" lang="ja" onclick="handleFlagClick('ja')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('ja');">
        <img src="https://flagcdn.com/w40/kr.png" alt="한국어" title="한국어" role="button" tabindex="0" lang="ko" onclick="handleFlagClick('ko')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('ko');">
        <img src="https://flagcdn.com/w40/no.png" alt="Norsk" title="Norwegian" role="button" tabindex="0" lang="no" onclick="handleFlagClick('no')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('no');">
        <img src="https://flagcdn.com/w40/ru.png" alt="Русский" title="Russian" role="button" tabindex="0" lang="ru" onclick="handleFlagClick('ru')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('ru');">
        <img src="https://flagcdn.com/w40/fi.png" alt="Suomi" title="Finnish" role="button" tabindex="0" lang="fi" onclick="handleFlagClick('fi')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('fi');">
        <img src="https://flagcdn.com/w40/se.png" alt="Svenska" title="Swedish" role="button" tabindex="0" lang="sv" onclick="handleFlagClick('sv')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('sv');">
        <img src="https://flagcdn.com/w40/ph.png" alt="Tagalog" title="Tagalog" role="button" tabindex="0" lang="tl" onclick="handleFlagClick('tl')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('tl');">
        <img src="https://flagcdn.com/w40/th.png" alt="ไทย" title="ไทย" role="button" tabindex="0" lang="th" onclick="handleFlagClick('th')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('th');">
        <img src="https://flagcdn.com/w40/tr.png" alt="Türkçe" title="Türkçe" role="button" tabindex="0" lang="tr" onclick="handleFlagClick('tr')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('tr');">
        <img src="https://flagcdn.com/w40/vn.png" alt="Tiếng Việt" title="Vietnamese" role="button" tabindex="0" lang="vi" onclick="handleFlagClick('vi')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('vi');">
    </div>
    <div id="language-toggle" role="button" aria-label="Open Language Switcher" tabindex="0"
        onclick="toggleLanguageFlags()"
        onkeydown="if(event.key === 'Enter' || event.key === ' ') toggleLanguageFlags();">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#FFFFFF"
            stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
            stroke-linejoin="round" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/>
            <line x1="2" y1="12" x2="22" y2="12"/>
            <path d="M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"/>
        </svg>
    </div>
</div>

<style>
/* === Wrapper === */
#google_translate_element_wrapper { position: fixed; bottom: 25px; right: 75px; z-index: 9999; background: #3A4F66; border-radius: 50%; box-shadow: 0 0 10px rgba(0, 0, 0, 0.2); display: flex; align-items: center; justify-content: flex-end; overflow: hidden; }
/* === Wrapper States === */
#google_translate_element_wrapper.closed { width: 40px; height: 40px; padding: 0; border-radius: 50%; justify-content: center; }
#google_translate_element_wrapper.open { border-radius: 30px; padding: 3px 10px 3px 10px; width: auto; height: auto; }
#google_translate_element_wrapper.closed #language-flags { max-width: 0; opacity: 0; pointer-events: none; }
#google_translate_element_wrapper.open #language-flags { max-width: 999px; padding-right: 10px; opacity: 1; pointer-events: auto; }
/* === Toggle Button === */
#language-toggle { background: transparent; border: none; cursor: pointer; padding: 6px; }
#language-toggle svg { display: block; }
/* === Language Flags === */
#language-flags { display: flex; flex-wrap: wrap; flex-direction: row; align-items: center; gap: 6px; overflow: hidden; }
#language-flags img { width: 24px; height: auto; cursor: pointer; }
#language-flags img:hover { transform: scale(1.15); }
#language-flags span { display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; }
/* === Reset Language === */
#reset-language:hover { transform: scale(1.15); }
/* === Media Query (Responsive) === */
@media (max-width: 600px) { #google_translate_element_wrapper.open { padding: 6px 10px 10px 20px !important; } }
#google_translate_element_wrapper [tabindex="0"]:focus-visible {outline: 2px solid #FFD700; outline-offset: 2px; border-radius: 4px;}
</style>

<script type="text/javascript">
    // Toggle open/close state of language switcher
    function toggleLanguageFlags() {
        const wrapper = document.getElementById('google_translate_element_wrapper');
        const isOpen = !wrapper.classList.contains('open');
        wrapper.classList.toggle('open');
        wrapper.classList.toggle('closed');
        wrapper.setAttribute('aria-hidden', !isOpen); // Set for screen readers
        // Lazy-load Translate
        if (!translateScriptLoaded) {
            loadGoogleTranslate();
            translateScriptLoaded = true;
        }
    }
    // Handle click on a flag, trigger translation and close switcher
    function handleFlagClick(lang) {
        translatePage(lang);
        toggleLanguageFlags();
    }
    // Translate the page using Google Translate combo box
    function translatePage(lang) {
        const frame = document.querySelector('iframe.goog-te-banner-frame');
        if (frame) frame.remove();
        const gtFrame = document.querySelector('iframe.goog-te-menu-frame');
        if (gtFrame) gtFrame.remove();
        const select = document.querySelector('.goog-te-combo');
        if (select) {
            select.value = lang;
            select.dispatchEvent(new Event('change'));
            localStorage.setItem('preferredLang', lang);
        }
    }
    // Inject hidden Google Translate dropdown
    function injectGoogleTranslate() {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            autoDisplay: false
        }, 'google_translate_element');
    }
    // Restore preferred language from localStorage on load
    function restoreLanguage() {
        const lang = localStorage.getItem('preferredLang');
        if (lang) {
            const interval = setInterval(() => {
                const select = document.querySelector('.goog-te-combo');
                if (select) {
                    select.value = lang;
                    select.dispatchEvent(new Event('change'));
                    clearInterval(interval);
                }
            }, 500);
        }
    }
    // Lazy load Google Translate when needed
    let translateScriptLoaded = false;
    function loadGoogleTranslate() {
        const gtDiv = document.createElement('div');
        gtDiv.id = 'google_translate_element';
        gtDiv.style.display = 'none';
        gtDiv.setAttribute('aria-hidden', 'true');
        gtDiv.setAttribute('tabindex', '-1');
        document.body.appendChild(gtDiv);
        const gtScript = document.createElement('script');
        gtScript.src = "//translate.google.com/translate_a/element.js?cb=injectGoogleTranslate";
        document.body.appendChild(gtScript);
        restoreLanguage();
    }
    // Reset language preference and clear all translation effects
    function resetTranslation() {
        localStorage.removeItem('preferredLang');
        document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + location.hostname + ';';
        document.querySelectorAll('iframe.goog-te-banner-frame, iframe.goog-te-menu-frame').forEach(el => el.remove());
        document.querySelectorAll('[class^="goog-te"]').forEach(el => el.remove());
        document.querySelectorAll('style').forEach(style => {
            if (style.innerText.includes('.goog-te')) {
                style.remove();
            }
        });
        sessionStorage.setItem('scrollToTopAfterReload', 'true');
        location.reload();
    }
    // Scroll to top after reset, if triggered
    if (sessionStorage.getItem('scrollToTopAfterReload')) {
        window.scrollTo(0, 0);
        sessionStorage.removeItem('scrollToTopAfterReload');
    }
    // Close language switcher when clicking outside of it
    document.addEventListener('click', function (event) {
        const wrapper = document.getElementById('google_translate_element_wrapper');
        if (!wrapper.contains(event.target)) {
            wrapper.classList.remove('open');
            wrapper.classList.add('closed');
        }
    });
</script>

<?php
});
