<?php

add_action('wp_footer', function () {
?>
<div id="google_translate_element_wrapper" class="closed" role="region" aria-label="Language Switcher">
    <div id="flags-container">
        <div class="reset-and-flags">
            <span class="reset" title="Reset Language" role="button" aria-label="Reset Language" tabindex="0"
                onclick="resetTranslation()" 
                onkeydown="if(event.key === 'Enter' || event.key === ' ') resetTranslation();" >Reset
                <svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="#FFFFFF" viewBox="0 0 24 24" style="cursor: pointer;">
                    <title>Reset Language</title>
                    <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6a6.003 6.003 0 0 1-5.65-4H4.26a8.003 8.003 0 0 0 15.47-2c0-4.42-3.58-8-8-8z"/>
                </svg>
            </span>
            <span class="flags">
                <img src="https://flagcdn.com/w40/sa.webp" alt="العربية" title="العربية" width="40" height="30" role="button" tabindex="0" lang="ar" onclick="handleFlagClick('ar')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('ar');">
                <img src="https://flagcdn.com/w40/id.webp" alt="Bahasa" title="Bahasa" width="40" height="30" role="button" tabindex="0" lang="id" onclick="handleFlagClick('id')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('id');">
                <img src="https://flagcdn.com/w40/cn.webp" alt="中文" title="中文" width="40" height="30" role="button" tabindex="0" lang="zh-CN" onclick="handleFlagClick('zh-CN')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('zh-CN');">
                <img src="https://flagcdn.com/w40/dk.webp" alt="Dansk" title="Danish" width="40" height="30" role="button" tabindex="0" lang="da" onclick="handleFlagClick('da')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('da');">
                <img src="https://flagcdn.com/w40/de.webp" alt="Deutsch" title="Deutsch" width="40" height="30" role="button" tabindex="0" lang="de" onclick="handleFlagClick('de')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('de');">
                <img src="https://flagcdn.com/w40/es.webp" alt="Español" title="Español" width="40" height="30" role="button" tabindex="0" lang="es" onclick="handleFlagClick('es')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('es');">
                <img src="https://flagcdn.com/w40/fr.webp" alt="Français" title="Français" width="40" height="30" role="button" tabindex="0" lang="fr" onclick="handleFlagClick('fr')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('fr');">
                <img src="https://flagcdn.com/w40/in.webp" alt="हिन्दी" title="हिन्दी" width="40" height="30" role="button" tabindex="0" lang="hi" onclick="handleFlagClick('hi')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('hi');">
                <img src="https://flagcdn.com/w40/it.webp" alt="Italiano" title="Italiano" width="40" height="30" role="button" tabindex="0" lang="it" onclick="handleFlagClick('it')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('it');">
                <img src="https://flagcdn.com/w40/jp.webp" alt="日本語" title="日本語" width="40" height="30" role="button" tabindex="0" lang="ja" onclick="handleFlagClick('ja')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('ja');">
                <img src="https://flagcdn.com/w40/kr.webp" alt="한국어" title="한국어" width="40" height="30" role="button" tabindex="0" lang="ko" onclick="handleFlagClick('ko')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('ko');">
                <img src="https://flagcdn.com/w40/no.webp" alt="Norsk" title="Norwegian" width="40" height="30" role="button" tabindex="0" lang="no" onclick="handleFlagClick('no')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('no');">
                <img src="https://flagcdn.com/w40/ru.webp" alt="Русский" title="Russian" width="40" height="30" role="button" tabindex="0" lang="ru" onclick="handleFlagClick('ru')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('ru');">
                <img src="https://flagcdn.com/w40/fi.webp" alt="Suomi" title="Finnish" width="40" height="30" role="button" tabindex="0" lang="fi" onclick="handleFlagClick('fi')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('fi');">
                <img src="https://flagcdn.com/w40/se.webp" alt="Svenska" title="Swedish" width="40" height="30" role="button" tabindex="0" lang="sv" onclick="handleFlagClick('sv')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('sv');">
                <img src="https://flagcdn.com/w40/ph.webp" alt="Tagalog" title="Tagalog" width="40" height="30" role="button" tabindex="0" lang="tl" onclick="handleFlagClick('tl')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('tl');">
                <img src="https://flagcdn.com/w40/th.webp" alt="ไทย" title="ไทย" width="40" height="30" role="button" tabindex="0" lang="th" onclick="handleFlagClick('th')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('th');">
                <img src="https://flagcdn.com/w40/tr.webp" alt="Türkçe" title="Türkçe" width="40" height="30" role="button" tabindex="0" lang="tr" onclick="handleFlagClick('tr')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('tr');">
                <img src="https://flagcdn.com/w40/vn.webp" alt="Tiếng Việt" title="Vietnamese" width="40" height="30" role="button" tabindex="0" lang="vi" onclick="handleFlagClick('vi')" onkeydown="if(event.key === 'Enter' || event.key === ' ') handleFlagClick('vi');">
            </span>
        </div>
    </div>
    <div id="language-toggle" role="button" aria-label="Open Language Switcher" tabindex="0"
        title="Language Switcher" onclick="toggleLanguageFlags()"
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
#google_translate_element_wrapper {position: fixed; bottom: 25px; right: 75px; z-index: 999; background: #3A4F66; border-radius: 50%; box-shadow: 0 0 10px rgba(0, 0, 0, 0.2); display: flex; align-items: center; justify-content: flex-end; overflow: hidden;}
#google_translate_element_wrapper [tabindex="0"]:focus-visible {outline: 2px solid #FFD700; outline-offset: 2px; border-radius: 4px;}
#google_translate_element_wrapper.closed {width: 40px; height: 40px; padding: 0; border-radius: 50%; justify-content: center;}
#google_translate_element_wrapper.open {border-radius: 30px; padding: 3px 10px 3px 10px; width: auto; height: auto;}
#google_translate_element_wrapper.closed #flags-container {max-width: 0; opacity: 0; pointer-events: none;}
#google_translate_element_wrapper.open #flags-container {max-width: 999px; padding-right: 10px; opacity: 1; pointer-events: auto;}
#language-toggle {background: transparent; border: none; cursor: pointer; padding: 6px;}
#language-toggle svg {display: block;}
#flags-container {display: flex; align-items: center; flex-wrap: wrap;}
#flags-container .reset-and-flags {display: flex; align-items: center; flex-wrap: nowrap;}
#flags-container .reset {display: flex; align-items: center; flex-wrap: nowrap; gap: 4px; margin: 0 10px; color: white; font-size: 14px; cursor: pointer;}
#flags-container .flags {display: flex; align-items: center; flex-wrap: wrap; gap: 6px;}
#flags-container .flags img {width: 24px; height: auto; cursor: pointer;}
#flags-container .flags img:hover {transform: scale(1.15);}
@media (max-width: 600px) {#google_translate_element_wrapper.open {padding: 6px 10px 10px 20px !important;} #flags-container .reset {margin-right: 20px;}}
</style>

<script type="text/javascript">
    function toggleLanguageFlags() {
        const wrapper = document.getElementById('google_translate_element_wrapper');
        const toggleBtn = document.getElementById('language-toggle');
        const isOpen = !wrapper.classList.contains('open');
        wrapper.classList.toggle('open');
        wrapper.classList.toggle('closed');
        wrapper.setAttribute('aria-hidden', !isOpen);
        const focusables = wrapper.querySelectorAll('[tabindex]:not(#language-toggle)');
        focusables.forEach(el => {
            el.setAttribute('tabindex', isOpen ? '0' : '-1');
        });
        toggleBtn.setAttribute('aria-label', isOpen ? 'Close Language Switcher' : 'Open Language Switcher');
        if (!translateScriptLoaded) {
            loadGoogleTranslate();
            translateScriptLoaded = true;
        }
    }

    function handleFlagClick(lang) {
        translatePage(lang);
        toggleLanguageFlags();
        window.scrollTo(0, 0);
    }

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

    function injectGoogleTranslate() {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            autoDisplay: false
        }, 'google_translate_element');
    }

    function restoreLanguage() {
        const lang = localStorage.getItem('preferredLang');
        if (lang) {
            let attempts = 0;
            const maxAttempts = 20;
            const interval = setInterval(() => {
                const select = document.querySelector('.goog-te-combo');
                if (select) {
                    select.value = lang;
                    select.dispatchEvent(new Event('change'));
                    clearInterval(interval);
                }
                attempts++;
                if (attempts >= maxAttempts) {
                    clearInterval(interval);
                }
            }, 500);
        }
    }
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

    if (sessionStorage.getItem('scrollToTopAfterReload')) {
        window.scrollTo(0, 0);
        sessionStorage.removeItem('scrollToTopAfterReload');
    }

    document.addEventListener('click', function (event) {
        const wrapper = document.getElementById('google_translate_element_wrapper');
        if (!wrapper.contains(event.target)) {
            wrapper.classList.remove('open');
            wrapper.classList.add('closed');
            wrapper.setAttribute('aria-hidden', 'true');
            const focusables = wrapper.querySelectorAll('[tabindex]:not(#language-toggle)');
            focusables.forEach(el => el.setAttribute('tabindex', '-1'));
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        const wrapper = document.getElementById('google_translate_element_wrapper');
        if (wrapper) {
            wrapper.classList.add('closed');
            wrapper.classList.remove('open');
            wrapper.setAttribute('aria-hidden', 'true');
            const focusables = wrapper.querySelectorAll('[tabindex]:not(#language-toggle)');
            focusables.forEach(el => el.setAttribute('tabindex', '-1'));
        }

        if (localStorage.getItem('preferredLang')) {
            loadGoogleTranslate();
            translateScriptLoaded = true;
        }
    });
</script>

<?php
});
