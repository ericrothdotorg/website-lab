<?php

add_action('wp_footer', function () {
?>
<div id="google_translate_element_wrapper" class="closed">
    <div id="language-flags">
        <span title="Reset Language" style="margin-right: 10px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="#FFFFFF" viewBox="0 0 24 24" onclick="resetTranslation()" style="cursor: pointer;">
            <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6a6.003 6.003 0 0 1-5.65-4H4.26a8.003 8.003 0 0 0 15.47-2c0-4.42-3.58-8-8-8z"/>
        </svg>
        </span>
        <img src="https://flagcdn.com/w40/sa.png" alt="العربية" title="العربية" onclick="handleFlagClick('ar')">
        <img src="https://flagcdn.com/w40/id.png" alt="Bahasa" title="Bahasa" onclick="handleFlagClick('id')">
        <img src="https://flagcdn.com/w40/cn.png" alt="中文" title="中文" onclick="handleFlagClick('zh-CN')">
        <img src="https://flagcdn.com/w40/de.png" alt="Deutsch" title="Deutsch" onclick="handleFlagClick('de')">
        <img src="https://flagcdn.com/w40/us.png" alt="English" title="English" onclick="handleFlagClick('en')">
        <img src="https://flagcdn.com/w40/es.png" alt="Español" title="Español" onclick="handleFlagClick('es')">
        <img src="https://flagcdn.com/w40/fr.png" alt="Français" title="Français" onclick="handleFlagClick('fr')">
        <img src="https://flagcdn.com/w40/in.png" alt="हिन्दी" title="हिन्दी" onclick="handleFlagClick('hi')">
        <img src="https://flagcdn.com/w40/it.png" alt="Italiano" title="Italiano" onclick="handleFlagClick('it')">
        <img src="https://flagcdn.com/w40/jp.png" alt="日本語" title="日本語" onclick="handleFlagClick('ja')">
        <img src="https://flagcdn.com/w40/kr.png" alt="한국어" title="한국어" onclick="handleFlagClick('ko')">
        <img src="https://flagcdn.com/w40/ph.png" alt="Tagalog" title="Tagalog" onclick="handleFlagClick('tl')">
        <img src="https://flagcdn.com/w40/th.png" alt="ไทย" title="ไทย" onclick="handleFlagClick('th')">
        <img src="https://flagcdn.com/w40/tr.png" alt="Türkçe" title="Türkçe" onclick="handleFlagClick('tr')">
    </div>
    <div id="language-toggle" onclick="toggleLanguageFlags()">
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
#google_translate_element_wrapper.open { border-radius: 30px; padding: 6px 10px 6px 10px; width: auto; height: auto; }
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
/* === Media Query (Responsive) === */
@media (max-width: 600px) {
  #google_translate_element_wrapper.open { padding: 6px 10px 10px 20px !important; }
}
</style>

<script type="text/javascript">
    // Toggle open/close state of language switcher
    function toggleLanguageFlags() {
        const wrapper = document.getElementById('google_translate_element_wrapper');
        wrapper.classList.toggle('open');
        wrapper.classList.toggle('closed');
        // Lazy-load Google Translate on first open
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
