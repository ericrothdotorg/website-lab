<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// =========================================
// LANGUAGE SWITCHER (Google Translate)
// =========================================

add_action('wp_footer', function () {

	if (is_front_page()) return;

	// Single source of truth for the icon
	$er_lang_icon = '/wp-content/uploads/2026/05/globe-solid-white.svg';

	// Offered languages. Keep in sync with LANG_MAP
	// in the "Toggle - Mute | Unmute" snippet.
	$er_langs = 'ar,da,de,es,fi,fr,hi,id,it,ja,ko,no,ru,sv,th,tl,tr,vi,zh-CN';
	?>

<div id="google_translate_element" role="dialog" aria-label="Language selector" aria-hidden="true"></div>

<button type="button" id="er-lang-toggle" title="Language Switcher"
        aria-label="Open language switcher" aria-expanded="false" aria-controls="google_translate_element">
	<img src="<?php echo esc_url($er_lang_icon); ?>" width="24" height="24" alt="" aria-hidden="true">
</button>

<div id="er-lang-announcer" role="status" aria-live="polite" aria-atomic="true"></div>

<style>
	#er-lang-toggle {
		position: fixed;
		bottom: 25px;
		right: 75px;
		z-index: 999;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 40px;
		height: 40px;
		padding: 6px;
		border: none;
		border-radius: 50%;
		background: var(--color-3);
		color: var(--color-8);
		box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
		cursor: pointer;
		-webkit-appearance: none;
		appearance: none;
	}
	#er-lang-toggle:focus-visible {outline: 2px solid var(--color-3); outline-offset: 2px;}

	#google_translate_element {
		display: none;
		position: fixed;
		bottom: 80px;
		right: 75px;
		z-index: 1000;
		padding: 10px;
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
	}
	#google_translate_element.visible {display: block;}

	#er-lang-announcer {position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;}

	/* Google Translate widget cosmetics */
	.goog-te-gadget {font-family: Arial, sans-serif; font-size: 14px;}
	.goog-te-gadget-simple .goog-te-menu-value span:first-child {display: none;}
	.goog-te-gadget-simple .goog-te-menu-value span:last-child {border-left: none !important;}
	.goog-te-gadget .goog-te-gadget-simple {border: none; background: transparent;}
	.goog-te-combo {width: 200px; padding: 8px; border: 1px solid var(--color-5); border-radius: 4px; font-size: 14px;}
	.goog-te-banner-frame {display: none !important;}

	/* Only counteract the banner offset when Google actually translated */
	html.translated-ltr body, html.translated-rtl body {top: 0 !important;}
</style>

<script>
(function () {
	'use strict';

	var LANGS   = '<?php echo esc_js($er_langs); ?>';
	var COOKIE  = 'googtrans';

	var panel, toggle, announcer;
	var loading = false, loaded = false, retries = 0;
	var observer = null, observerTimer = null;

	// --- Helpers -------------------------------------------------

	function announce(msg) {
		if (!announcer) return;
		announcer.textContent = msg;
		setTimeout(function () { announcer.textContent = ''; }, 1000);
	}

	function getCookie() {
		var row = document.cookie.split('; ').find(function (c) { return c.indexOf(COOKIE + '=') === 0; });
		return row ? row.slice(COOKIE.length + 1) : null;
	}

	function isTranslated() {
		var v = getCookie();
		return !!v && v !== '/en/en';
	}

	// Google may set the cookie host-only, on the host, or dot-prefixed.
	// Clear every variant, otherwise the reset silently fails.
	function clearCookie() {
		var host    = location.hostname;
		var domains = ['', '; domain=' + host, '; domain=.' + host];
		if (host.indexOf('www.') === 0) {
			domains.push('; domain=' + host.slice(4), '; domain=.' + host.slice(4));
		}
		domains.forEach(function (d) {
			document.cookie = COOKIE + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT' + d;
		});
		try { localStorage.removeItem('preferredLang'); } catch (e) {}
	}

	// --- Widget loading ------------------------------------------

	function loadWidget() {
		if (loaded || loading) return;
		if (document.querySelector('script[src*="translate.google.com/translate_a/element.js"]')) {
			loaded = true;
			return;
		}
		loading = true;
		var s = document.createElement('script');
		s.src = 'https://translate.google.com/translate_a/element.js?cb=erTranslateInit';
		s.onload  = function () { loading = false; loaded = true; };
		s.onerror = function () {
			loading = false;
			s.remove();
			if (retries++ < 3) { setTimeout(loadWidget, 1000); }
			else { announce('Language switcher currently unavailable'); }
		};
		document.body.appendChild(s);
	}

	// Callback name referenced in the script URL above — must be global.
	window.erTranslateInit = function () {
		if (!window.google || !google.translate) return;
		new google.translate.TranslateElement({
			pageLanguage: 'en',
			includedLanguages: LANGS,
			autoDisplay: false
		}, 'google_translate_element');
		watchForSelect();
	};

	function stopWatching() {
		if (observer) { observer.disconnect(); observer = null; }
		if (observerTimer) { clearTimeout(observerTimer); observerTimer = null; }
	}

	function watchForSelect() {
		if (bindSelect()) return;
		if (observer) return;
		observer = new MutationObserver(function () { bindSelect(); });
		observer.observe(panel, { childList: true, subtree: true });
		observerTimer = setTimeout(stopWatching, 10000); // give up instead of observing forever
	}

	function bindSelect() {
		var select = panel.querySelector('.goog-te-combo');
		if (!select) return false;
		if (select.dataset.erBound) { stopWatching(); return true; }
		select.dataset.erBound = '1';
		stopWatching();

		select.setAttribute('aria-label', 'Select language');
		select.addEventListener('change', function () {
			var lang = this.value;

			// Empty value = back to the original language
			if (!lang) {
				clearCookie();
				announce('Resetting to original language');
				setTimeout(function () { location.reload(); }, 100);
				return;
			}

			// Handed over to the TTS snippet so it picks a matching voice
			try { localStorage.setItem('preferredLang', lang); } catch (e) {}

			announce('Language changed to ' + this.options[this.selectedIndex].text);
			closePanel();
		});
		return true;
	}

	// --- Panel ---------------------------------------------------

	function openPanel() {
		panel.classList.add('visible');
		panel.setAttribute('aria-hidden', 'false');
		toggle.setAttribute('aria-expanded', 'true');
		toggle.setAttribute('aria-label', 'Close language switcher');
		loadWidget();
		announce('Language selector opened');
		setTimeout(function () {
			var select = panel.querySelector('.goog-te-combo');
			if (select) select.focus();
		}, 500);
	}

	function closePanel() {
		panel.classList.remove('visible');
		panel.setAttribute('aria-hidden', 'true');
		toggle.setAttribute('aria-expanded', 'false');
		toggle.setAttribute('aria-label', 'Open language switcher');
		toggle.focus();
		announce('Language selector closed');
	}

	function togglePanel() {
		if (panel.classList.contains('visible')) { closePanel(); }
		else { openPanel(); }
	}

	// --- Init ----------------------------------------------------

	function init() {
		panel     = document.getElementById('google_translate_element');
		toggle    = document.getElementById('er-lang-toggle');
		announcer = document.getElementById('er-lang-announcer');
		if (!panel || !toggle) return;

		toggle.addEventListener('click', togglePanel);

		document.addEventListener('click', function (e) {
			if (!panel.classList.contains('visible')) return;
			if (panel.contains(e.target) || toggle.contains(e.target)) return;
			closePanel();
		});

		document.addEventListener('keydown', function (e) {
			if (!panel.classList.contains('visible')) return;

			if (e.key === 'Escape') { closePanel(); return; }

			if (e.key === 'Tab') {
				var focusable = panel.querySelectorAll('select, button, a[href], [tabindex]:not([tabindex="-1"])');
				if (!focusable.length) return;
				var first = focusable[0];
				var last  = focusable[focusable.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); toggle.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); toggle.focus(); }
			}
		});

		// Re-apply an existing translation on page load
		if (isTranslated()) loadWidget();
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
	else { init(); }
})();
</script>

	<?php
});
