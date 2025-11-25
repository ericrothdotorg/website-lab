<?php

add_action('wp_footer', function () {
?>
<div id="google_translate_element" role="dialog" aria-label="Language Selector" aria-hidden="true"></div>

<div id="language-toggle" role="button" aria-label="Open Language Switcher" 
    aria-expanded="false" aria-controls="google_translate_element" tabindex="0"
    title="Language Switcher" onclick="toggleTranslate()"
    onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggleTranslate(); }">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#FFFFFF"
        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
        stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/>
        <line x1="2" y1="12" x2="22" y2="12"/>
        <path d="M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"/>
    </svg>
</div>

<!-- Screen reader announcements -->
<div id="translate-announcer" role="status" aria-live="polite" aria-atomic="true" style="position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;"></div>

<style>
    #language-toggle {
        position: fixed;
        bottom: 25px;
        right: 75px;
        z-index: 999;
        background: #3A4F66;
        border-radius: 50%;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 6px;
        border: none;
    }
    
    #language-toggle:focus-visible {
		   outline: 2px solid #3A4F66;
		   outline-offset: 2px;
    }
    
    #language-toggle svg {
		   display: block;
    }
    
    /* Hide Google Translate element by default */
    #google_translate_element {
        display: none;
        position: fixed;
        bottom: 80px;
        right: 75px;
        z-index: 1000;
        background: white;
        padding: 10px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    #google_translate_element.visible {
        display: block;
    }
    
    #google_translate_element:focus {
        outline: 2px solid #3A4F66;
        outline-offset: 2px;
    }
    
    /* Style the Google Translate dropdown */
    .goog-te-gadget {
        font-family: Arial, sans-serif;
        font-size: 14px;
    }
    
    /* Hide the ugly ">" arrow and powered by text */
    .goog-te-gadget-simple .goog-te-menu-value span:first-child {
        display: none;
    }
    
    .goog-te-gadget-simple .goog-te-menu-value span:last-child {
        border-left: none !important;
    }
    
    .goog-te-gadget .goog-te-gadget-simple {
        border: none;
        background: transparent;
    }
    
    /* Clean up the select appearance */
    .goog-te-combo {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        width: 200px;
    }
    
    /* Hide the Google Translate banner */
    .goog-te-banner-frame {
        display: none !important;
    }
    
    body {
        top: 0 !important;
    }
</style>

<script type="text/javascript">
    let translateLoaded = false;
    
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            autoDisplay: false
        }, 'google_translate_element');
        translateLoaded = true;
        
        // Monitor for language changes and save to localStorage
        setTimeout(() => {
            const select = document.querySelector('.goog-te-combo');
            if (select) {
                // Add accessible label
                select.setAttribute('aria-label', 'Select language');
                
                select.addEventListener('change', function() {
                    const selectedLang = this.value;
                    const selectedText = this.options[this.selectedIndex].text;
                    
                    if (selectedLang && selectedLang !== '') {
                        localStorage.setItem('googtrans', '/en/' + selectedLang);
                        
                        // Also set the cookie directly
                        document.cookie = 'googtrans=/en/' + selectedLang + '; path=/';
                        
                        // Announce language change to screen readers
                        announceToScreenReader('Language changed to ' + selectedText);
                        
                        // Close the popup after selection
                        closeTranslatePanel();
                    }
                });
                
                // Restore saved language if exists
                const savedTrans = localStorage.getItem('googtrans');
                if (savedTrans && savedTrans !== '/en/en') {
                    const lang = savedTrans.split('/')[2];
                    if (lang) {
                        select.value = lang;
                        select.dispatchEvent(new Event('change'));
                    }
                }
            }
        }, 500);
    }
    
    function announceToScreenReader(message) {
        const announcer = document.getElementById('translate-announcer');
        if (announcer) {
            announcer.textContent = message;
            setTimeout(() => {
                announcer.textContent = '';
            }, 1000);
        }
    }
    
    function closeTranslatePanel() {
        const element = document.getElementById('google_translate_element');
        const toggle = document.getElementById('language-toggle');
        
        element.classList.remove('visible');
        element.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-label', 'Open Language Switcher');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
        
        announceToScreenReader('Language selector closed');
    }
    
    function toggleTranslate() {
        const element = document.getElementById('google_translate_element');
        const toggle = document.getElementById('language-toggle');
        const isCurrentlyVisible = element.classList.contains('visible');
        
        // Check if Google Translate is already active (has translated content)
        const isTranslated = document.cookie.includes('googtrans=') && 
                            !document.cookie.includes('googtrans=/en/en');
        
        // If closing OR if already translated (meaning user wants to reset)
        if (isCurrentlyVisible || isTranslated) {
            localStorage.removeItem('googtrans');
            document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + location.hostname + ';';
            announceToScreenReader('Resetting to original language');
            setTimeout(() => {
                location.reload();
            }, 100);
            return;
        }
        
        // If opening, load script if needed
        if (!translateLoaded) {
            const script = document.createElement('script');
            script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            document.body.appendChild(script);
        }
        
        // Show the element
        element.classList.add('visible');
        element.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-label', 'Close Language Switcher');
        toggle.setAttribute('aria-expanded', 'true');
        
        announceToScreenReader('Language selector opened');
        
        // Move focus to the dropdown when it loads
        setTimeout(() => {
            const select = document.querySelector('.goog-te-combo');
            if (select) {
                select.focus();
            }
        }, 600);
    }
    
    // Close when clicking outside
    document.addEventListener('click', function(event) {
        const element = document.getElementById('google_translate_element');
        const toggle = document.getElementById('language-toggle');
        
        if (element.classList.contains('visible') && 
            !element.contains(event.target) && 
            !toggle.contains(event.target)) {
            closeTranslatePanel();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(event) {
        const element = document.getElementById('google_translate_element');
        
        if (event.key === 'Escape' && element.classList.contains('visible')) {
            closeTranslatePanel();
        }
    });
    
    // Trap focus within the translate panel when open
    document.addEventListener('keydown', function(event) {
        const element = document.getElementById('google_translate_element');
        const toggle = document.getElementById('language-toggle');
        
        if (!element.classList.contains('visible')) return;
        
        if (event.key === 'Tab') {
            const focusableElements = element.querySelectorAll('select, button, [tabindex="0"]');
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                toggle.focus();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
                event.preventDefault();
                toggle.focus();
            }
        }
    });
    
    // Auto-load Google Translate if a language preference exists
    document.addEventListener('DOMContentLoaded', function() {
        const savedTrans = localStorage.getItem('googtrans');
        const cookieTrans = document.cookie.split('; ').find(row => row.startsWith('googtrans='));
        
        if ((savedTrans && savedTrans !== '/en/en') || (cookieTrans && !cookieTrans.includes('/en/en'))) {
            const script = document.createElement('script');
            script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            document.body.appendChild(script);
            translateLoaded = true;
            
            // Ensure cookie is set from localStorage if it's missing
            if (savedTrans && !cookieTrans) {
                document.cookie = 'googtrans=' + savedTrans + '; path=/';
            }
        }
        
        // Monitor Google Translate's close button
        const observer = new MutationObserver(function(mutations) {
            // Check if the translation bar was closed
            const cookieValue = document.cookie.split('; ').find(row => row.startsWith('googtrans='));
            const isTranslated = cookieValue && !cookieValue.includes('/en/en');
            
            // If we detect translation was reset (user clicked X on Google's bar)
            if (!isTranslated && localStorage.getItem('googtrans')) {
                localStorage.removeItem('googtrans');
                document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                const element = document.getElementById('google_translate_element');
                const toggle = document.getElementById('language-toggle');
                if (element) {
                    element.classList.remove('visible');
                    element.setAttribute('aria-hidden', 'true');
                }
                if (toggle) {
                    toggle.setAttribute('aria-label', 'Open Language Switcher');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
        
        observer.observe(document.body, { 
            childList: true, 
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
        
        // Watch for cookie changes (less aggressive polling)
        setInterval(() => {
            const cookieValue = document.cookie.split('; ').find(row => row.startsWith('googtrans='));
            const isTranslated = cookieValue && !cookieValue.includes('/en/en');
            const hasStoredPref = localStorage.getItem('googtrans');
            
            // If cookie says not translated but we have stored preference, user clicked X
            if (!isTranslated && hasStoredPref) {
                localStorage.removeItem('googtrans');
                document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            }
            
            // If we have stored preference but cookie is missing, restore it
            if (hasStoredPref && !cookieValue) {
                document.cookie = 'googtrans=' + hasStoredPref + '; path=/';
            }
        }, 1000);
    });
</script>

<?php
});
