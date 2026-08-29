<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ===========================================================================
// TOGGLE - MUTE | UNMUTE (Text-to-Speech)
// Markup for the toggle itself lives in the child theme: parts / footer.html
// ===========================================================================

// =================================
// STYLE IN HEAD
// =================================

add_action('wp_head', function () {
	?>
	<style>
	/* TTS Toggle Switch */
	#tts-toggle-btn {display: flex; align-items: center; gap: 10px;}
	#tts-toggle-btn img {opacity: 0.5;}
	#tts-toggle-btn input[type='checkbox'] {position: absolute; opacity: 0; width: 0; height: 0;}
	#tts-toggle-btn input[type='checkbox']:focus-visible + .toggle-visual {outline: 1px solid var(--color-8); outline-offset: 2px;}
	#tts-toggle-btn .toggle-visual {
		background: var(--color-3);
		border: 1px solid var(--color-4);
		border-radius: 50px;
		cursor: pointer;
		display: inline-block;
		position: relative;
		transition: all ease-in-out 0.3s;
		width: 50px;
		height: 25px;
	}
	#tts-toggle-btn .toggle-visual::after {
		background: var(--color-4);
		border-radius: 50%;
		content: '';
		cursor: pointer;
		display: inline-block;
		position: absolute;
		left: 1px;
		top: 1px;
		transition: all ease-in-out 0.3s;
		width: 21px;
		height: 21px;
	}
	#tts-toggle-btn input[type='checkbox']:checked + .toggle-visual {background: var(--color-10); border-color: var(--color-3);}
	#tts-toggle-btn input[type='checkbox']:checked + .toggle-visual::after {background: var(--color-3); transform: translateX(25px);}

	/* Screen reader only */
	#tts-status, .tts-toggle-btn-accessibility-label, .tts-cue {
		position: absolute;
		left: -9999px;
		width: 1px;
		height: 1px;
		overflow: hidden;
	}

	/* TTS Controls (Play, Pause, Stop) */
	#tts-controls {
		position: fixed;
		bottom: 27.5px;
		left: 20px;
		z-index: 9999;
		display: flex;
		align-items: center;
		gap: 10px;
		opacity: 0;
		pointer-events: none;
		transition: opacity 0.3s;
	}
	#tts-controls.show {opacity: 1; pointer-events: auto;}
	#tts-controls button {
		padding: 5px 10px;
		border: none;
		border-radius: 5px;
		background: var(--color-3);
		color: var(--color-8);
		font-size: 16px;
		line-height: 1;
		cursor: pointer;
		transition: background 0.2s;
		-webkit-appearance: none;
		appearance: none;
	}
	#tts-controls button:hover {background: var(--color-4);}
	#tts-controls button:focus-visible {outline: 2px solid var(--color-8); outline-offset: 2px;}
	</style>
	<?php
});

// =================================
// MARKUP & SCRIPT IN FOOTER
// =================================

add_action('wp_footer', function () {
	?>

<div id="tts-controls" role="group" aria-label="Text-to-speech controls">
	<button type="button" id="tts-play"  aria-label="Play text-to-speech"  title="Play">&#9654;</button>
	<button type="button" id="tts-pause" aria-label="Pause text-to-speech" title="Pause">&#9208;</button>
	<button type="button" id="tts-stop"  aria-label="Stop text-to-speech"  title="Stop">&#9209;</button>
</div>

<script>
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn); }
		else { fn(); }
	}

	ready(function () {

		var els = {
			toggleBtn: document.getElementById('tts-toggle-btn'),
			toggle:    document.getElementById('tts-toggle-switch'),
			visual:    document.querySelector('#tts-toggle-btn .toggle-visual'),
			status:    document.getElementById('tts-status'),
			controls:  document.getElementById('tts-controls'),
			play:      document.getElementById('tts-play'),
			pause:     document.getElementById('tts-pause'),
			stop:      document.getElementById('tts-stop')
		};

		// No speech synthesis: hide the UI entirely, do nothing else.
		if (!('speechSynthesis' in window)) {
			if (els.toggleBtn) els.toggleBtn.style.display = 'none';
			if (els.controls)  els.controls.style.display  = 'none';
			return;
		}
		if (!els.toggle) return;

		var synth = window.speechSynthesis;

		// Keep in sync with $er_langs in the "Google Translate" snippet.
		var LANG_MAP = {
			'ar': 'ar-SA', 'da': 'da-DK', 'de': 'de-DE', 'es': 'es-ES',
			'fi': 'fi-FI', 'fr': 'fr-FR', 'hi': 'hi-IN', 'id': 'id-ID',
			'it': 'it-IT', 'ja': 'ja-JP', 'ko': 'ko-KR', 'no': 'no-NO',
			'ru': 'ru-RU', 'sv': 'sv-SE', 'th': 'th-TH', 'tl': 'tl-PH',
			'tr': 'tr-TR', 'vi': 'vi-VN', 'zh-CN': 'zh-CN', 'en': 'en-US'
		};

		var MAX_CHUNK = 180; // Chromium drops utterances after ~15s, so speak in small pieces

		var state = {
			cuesInserted: false,
			session: 0,        // invalidates callbacks of a cancelled run
			queue: [],
			index: 0,
			paused: false,
			debounce: null,
			watchdog: null,
			autoTimer: null,
			marks: [],         // where each slideshow slide starts in the text
			lastSync: null     // slide currently shown, to avoid redundant clicks
		};

		// --- Safe localStorage ---------------------------------------

		var store = {
			get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
			set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
			remove: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
		};

		function status(msg) { if (els.status) els.status.textContent = msg; }

		// --- Voices ---------------------------------------------------

		function withVoices(cb) {
			if (synth.getVoices().length) { cb(); return; }
			var done = false;
			var go = function () {
				if (done) return;
				done = true;
				synth.removeEventListener('voiceschanged', go);
				cb();
			};
			synth.addEventListener('voiceschanged', go);
			setTimeout(go, 1000); // proceed anyway; the browser still has a default voice
		}

		function normLang(l) { return (l || '').replace('_', '-').toLowerCase(); }

		// Exact match first, then base language. null = let the browser decide via utterance.lang
		function pickVoice(target) {
			var voices = synth.getVoices() || [];
			var t = normLang(target);
			var base = t.split('-')[0];

			// Modern neural / network voices sound far better than the
			// legacy system voices (Windows SAPI in particular).
			var GOOD = /(google|natural|neural|enhanced|premium|siri|online|eloquence)/i;
			var POOR = /(david|zira|mark|hazel|sabina|helena|compact|espeak|festival|pico)/i;

			var candidates = voices.filter(function (v) { return normLang(v.lang) === t; });
			if (!candidates.length) {
				candidates = voices.filter(function (v) { return normLang(v.lang).split('-')[0] === base; });
			}
			if (!candidates.length) return null; // browser picks its own via utterance.lang

			function score(v) {
				var s = 0;
				if (GOOD.test(v.name)) s += 4;
				if (POOR.test(v.name)) s -= 4;
				if (v.localService === false) s += 2; // server-side voices are usually the good ones
				if (v.default) s += 1;
				return s;
			}

			candidates.sort(function (a, b) { return score(b) - score(a); });
			return candidates[0];
		}

		function targetLang() {
			var raw = store.get('preferredLang') || 'en';
			return LANG_MAP[raw] || LANG_MAP[raw.split('-')[0]] || 'en-US';
		}

		// --- Spoken cues for non-text content -------------------------

		// Rule 1: if the target sits inside a column, announce once in front of
		// the whole columns block instead of once per column.
		function cueAnchor(el) {
			if (!el.closest) return el;
			var col = el.closest('.wp-block-column');
			if (col) {
				var row = col.closest('.wp-block-columns');
				if (row && row.parentNode) return row;
			}
			return el;
		}

		function hasCueBefore(node, key) {
			var prev = node.previousElementSibling;
			while (prev && prev.classList.contains('tts-cue')) {
				if (prev.getAttribute('data-cue-key') === key) return true;
				prev = prev.previousElementSibling;
			}
			return false;
		}

		function addCue(selector, key, text) {
			document.querySelectorAll(selector).forEach(function (el) {
				var anchor = cueAnchor(el);
				if (!anchor.parentNode) return;
				if (hasCueBefore(anchor, key)) return;
				var cue = document.createElement('div');
				cue.className = 'tts-cue';
				cue.setAttribute('data-cue-key', key);
				cue.textContent = text;
				anchor.parentNode.insertBefore(cue, anchor);
			});
		}

		// All slideshow variants (see eric-slider-init), split by behaviour.
		// Single-item ones cycle one thing at a time and cannot be followed, so
		// they are announced but not read.
		var SLIDESHOW_SINGLE =
			'.slideshow-single-item,.slideshow-single-item-fade,' +
			'.slideshow-single-item-no-dots,.slideshow-single-item-chromeless,' +
			'.slideshow-quotes';
		var SLIDESHOW_MULTI =
			'.slideshow-multiple-items,.slideshow-multiple-items-3,' +
			'.slideshow-multiple-items-4,.slideshow-multiple-items-center-mode,' +
			'.slideshow-multiple-items-vertical';
		// Related posts get their own wording, because they are a slideshow above
		// four entries and a plain grid below. Excluded here so they never collect
		// both announcements at once.
		var SLIDESHOW_SEL = (SLIDESHOW_SINGLE + ',' + SLIDESHOW_MULTI)
			.split(',')
			.map(function (sel) { return sel + ':not(.er-related-posts)'; })
			.join(',');

		// Idempotent, so re-running catches dynamically added content
		// without needing a permanent MutationObserver on <body>.
		// Delete a line here to drop that announcement entirely.
		function insertCues() {
			addCue(SLIDESHOW_SEL, 'slideshow', 'A slideshow follows here.');
			addCue('.er-related-posts', 'related', 'Related posts follow here.');
			addCue('.wp-block-embed-youtube', 'video', 'A video follows here.');
			state.cuesInserted = true;
		}

		function cleanupCues() {
			document.querySelectorAll('.tts-cue').forEach(function (cue) { cue.remove(); });
			state.cuesInserted = false;
		}

		// --- Text extraction ------------------------------------------

		// Text-carrying elements. The last few are div/span based containers used
		// by Display Posts and the Ollie templates — without them, card titles
		// are silently skipped.
		var BLOCK_SEL = 'h1,h2,h3,h4,h5,h6,p,li,blockquote,figcaption,dt,dd,td,th,summary,' +
			'.tts-cue,.listing-item,.title,.excerpt,.page-description';

		function pruneClone(clone) {
			// Structural noise, navigation, and meta data — nothing to listen to.
			// 'tts-skip' is the manual opt-out: put that class on any block,
			// slideshow or not, and it is skipped entirely.
			clone.querySelectorAll(
				'nav,aside,footer,header,style,script,select,code,pre,img,svg,iframe,' +
				'.social-icons,.share-buttons,.tag-cloud,.tag-cloud-footer,' +
				'.like-dislike-container,.er-social-icon-row,.er-author-links,' +
				'.entry-meta,.er-reading-time,.post-stats,.er-breadcrumbs,.er-postnav,' +
				'.er-related-posts,.tts-skip,' +
				'.eric-slider-controls,.eric-slider-dots,.eric-slider-cloned'
			).forEach(function (el) { el.remove(); });

			// Single-item slideshows: the cue announces them, the slides say nothing.
			clone.querySelectorAll(SLIDESHOW_SINGLE).forEach(function (slider) {
				slider.querySelectorAll('.eric-slider-slide').forEach(function (s) { s.remove(); });
			});

			// Multi-item slideshows keep every slide and are read in document order;
			// their loop duplicates were dropped above via .eric-slider-cloned.

			// Collapsed accordions: read the heading, skip the hidden content
			clone.querySelectorAll('details:not([open])').forEach(function (d) {
				Array.prototype.slice.call(d.children).forEach(function (child) {
					if (child.tagName !== 'SUMMARY') child.remove();
				});
			});
			return clone;
		}

		function collectText(clone) {
			// Read block by block so sentences do not run into each other
			var parts = [];
			var marks = [];      // { offset, slider, index } for slideshow sync
			var spoken = 0;      // characters queued so far

			clone.querySelectorAll(BLOCK_SEL).forEach(function (el) {
				if (el.querySelector(BLOCK_SEL)) return; // container, its children are handled

				var t = (el.textContent || '').replace(/\s+/g, ' ').trim();
				if (!t) return;

				// Note where each slide's text starts, so playback can move the
				// live slideshow along with it
				var slide = el.closest ? el.closest('.eric-slider-slide') : null;
				if (slide) {
					var host = slide.closest('[data-tts-slider]');
					if (host) {
						marks.push({
							offset: spoken,
							slider: host.getAttribute('data-tts-slider'),
							index: parseInt(slide.getAttribute('data-index'), 10) || 0
						});
					}
				}

				var sentence = /[.!?\u2026:;]$/.test(t) ? t : t + '.';
				parts.push(sentence);
				spoken += sentence.length + 1;
			});

			var text = parts.join(' ');
			if (text.length < 10) {
				text = (clone.textContent || '').replace(/\s+/g, ' ').trim();
			}
			return { text: text, marks: marks };
		}

		function getReadableText() {
			var main = document.querySelector('main') || document.body;
			if (!main) return { text: '', marks: [] };

			// Tag the live sliders so the clone can point back at them
			document.querySelectorAll(SLIDESHOW_MULTI).forEach(function (s, i) {
				s.setAttribute('data-tts-slider', String(i));
			});

			return collectText(pruneClone(main.cloneNode(true)));
		}

		// Text of one accordion, without its heading (that was read already)
		function getDetailsText(details) {
			var clone = details.cloneNode(true);
			var summary = clone.querySelector('summary');
			if (summary) summary.remove();
			return collectText(pruneClone(clone)).text;
		}

		// Returns { text, offset } per chunk. The offset says where the chunk
		// started in the full text, which is how playback finds the matching slide.
		function chunkText(text, max) {
			var sentences = text.match(/[^.!?\u2026]+[.!?\u2026]*\s*/g) || [text];
			var chunks = [];
			var cur = '';
			var curStart = 0;
			var pos = 0;

			sentences.forEach(function (s) {
				var sStart = pos;
				pos += s.length;

				if (cur && (cur + s).length > max) {
					chunks.push({ text: cur.trim(), offset: curStart });
					cur = s;
					curStart = sStart;
				} else {
					if (!cur) curStart = sStart;
					cur += s;
				}

				while (cur.length > max) {
					var cut = cur.lastIndexOf(' ', max);
					if (cut < max * 0.5) cut = max;
					chunks.push({ text: cur.slice(0, cut).trim(), offset: curStart });
					curStart += cut;
					cur = cur.slice(cut);
				}
			});

			if (cur.trim()) chunks.push({ text: cur.trim(), offset: curStart });
			return chunks.filter(function (c) { return c.text.length > 0; });
		}

		// Build the playback queue. Chunks never span a slide boundary, otherwise
		// several slide titles end up in one utterance and the slideshow can only
		// be moved once for all of them.
		function buildQueue(text, marks, max) {
			var bounds = [0];
			marks.forEach(function (m) {
				if (m.offset > 0 && bounds[bounds.length - 1] !== m.offset) bounds.push(m.offset);
			});
			bounds.push(text.length);

			var queue = [];
			for (var i = 0; i < bounds.length - 1; i++) {
				var from = bounds[i];
				var segment = text.slice(from, bounds[i + 1]);
				if (!segment.trim()) continue;
				chunkText(segment, max).forEach(function (c) {
					queue.push({ text: c.text, offset: from + c.offset });
				});
			}
			return queue;
		}

		// --- Slideshow sync ---------------------------------------------

		// Move the slideshow to whatever is being spoken right now
		// Each click also resets the slider's own 2 second timer, which is what
		// keeps it from wandering off on its own while a title is being read.
		// Once reading moves on, that timer simply runs out and the slideshow
		// carries on looping by itself. Nothing else is touched — in particular
		// the pause button is left alone, so its icon never changes.
		function syncSliders(offset) {
			if (offset === null || offset === undefined || !state.marks.length) return;

			var mark = null;
			for (var i = 0; i < state.marks.length; i++) {
				if (state.marks[i].offset <= offset) mark = state.marks[i];
				else break;
			}
			if (!mark) return;

			var key = mark.slider + ':' + mark.index;
			if (state.lastSync === key) return;
			state.lastSync = key;

			var host = document.querySelector('[data-tts-slider="' + mark.slider + '"]');
			if (!host) return;
			var dots = host.querySelectorAll('.eric-slider-dots button');
			if (!dots.length) return; // no dots, no way to steer it — leave it alone
			var target = dots[Math.min(mark.index, dots.length - 1)];
			if (target) target.click();
		}

		// --- Playback --------------------------------------------------

		function startWatchdog() {
			clearInterval(state.watchdog);
			// Chromium occasionally stalls mid-queue; resume() is a no-op when healthy.
			state.watchdog = setInterval(function () {
				if (!synth.speaking) { clearInterval(state.watchdog); return; }
				if (!state.paused && synth.paused) synth.resume();
			}, 8000);
		}

		function speakNext(session, voice, lang) {
			if (session !== state.session) return;

			if (state.index >= state.queue.length) {
				status('Text-to-speech finished.');
				store.remove('ttsPlaying');
				clearInterval(state.watchdog);
				return;
			}

			var item = state.queue[state.index++];
			var u = new SpeechSynthesisUtterance(item.text);
			u.lang = lang;
			if (voice) u.voice = voice;

			u.onstart = function () {
				if (session !== state.session) return;
				state.paused = false;
				if (state.index === 1) status('Text-to-speech started.');
				syncSliders(item.offset);
			};
			u.onend = function () { speakNext(session, voice, lang); };
			u.onerror = function (e) {
				if (session !== state.session) return;
				var err = e && e.error;
				if (err === 'interrupted' || err === 'canceled') return;
				status('Error occurred during speech.');
				store.remove('ttsPlaying');
			};

			synth.speak(u);
		}

		function start(isAutoResume) {
			var page = getReadableText();
			if (page.text.length < 10) { status('No readable content found.'); return; }

			var lang = targetLang();

			withVoices(function () {
				var voice = pickVoice(lang);

				state.session++;
				state.queue = buildQueue(page.text, page.marks, MAX_CHUNK);
				state.marks = page.marks;
				state.index = 0;
				state.paused = false;
				state.lastSync = null;

				synth.cancel();
				speakNext(state.session, voice, lang);
				store.set('ttsPlaying', 'true');
				startWatchdog();

				// Browsers block speech without a user gesture (Safari/iOS in particular).
				// If nothing started, tell the user instead of pretending it plays.
				if (isAutoResume) {
					clearTimeout(state.autoTimer);
					state.autoTimer = setTimeout(function () {
						if (!synth.speaking && !synth.pending) {
							store.remove('ttsPlaying');
							status('Press play to continue reading.');
						}
					}, 1500);
				}
			});
		}

		function pause() {
			if (synth.speaking && !synth.paused) {
				synth.pause();
				state.paused = true;
				store.remove('ttsPlaying'); // do not auto-resume on the next page
				status('Text-to-speech paused.');
			}
		}

		function resume() {
			if (synth.paused) {
				synth.resume();
				state.paused = false;
				store.set('ttsPlaying', 'true');
				startWatchdog();
				status('Text-to-speech resumed.');
			}
		}

		// Full reset — everything that could keep stale state must be cleared here.
		function stop() {
			state.session++;
			state.queue = [];
			state.index = 0;
			state.paused = false;
			clearInterval(state.watchdog);
			clearTimeout(state.autoTimer);
			synth.cancel();
			cleanupCues();
			state.lastSync = null;
			store.remove('ttsPlaying');
		}

		// --- Enable / disable ------------------------------------------

		function setEnabled(on) {
			els.toggle.checked = on;
			els.toggle.setAttribute('aria-checked', on ? 'true' : 'false');
			if (els.controls) els.controls.classList.toggle('show', on);

			if (on) {
				store.set('ttsEnabled', 'true');
				if (!state.cuesInserted) insertCues();
				status('Text-to-speech enabled.');
			} else {
				store.remove('ttsEnabled');
				stop();
				status('Text-to-speech disabled.');
			}
		}

		// --- Wiring -----------------------------------------------------

		els.toggle.setAttribute('role', 'switch');
		els.toggle.setAttribute('aria-checked', els.toggle.checked ? 'true' : 'false');
		if (els.visual) els.visual.setAttribute('aria-hidden', 'true');

		// The visual span is decorative; clicking it drives the real checkbox
		if (els.visual) {
			els.visual.addEventListener('click', function () {
				els.toggle.checked = !els.toggle.checked;
				els.toggle.dispatchEvent(new Event('change'));
			});
		}

		els.toggle.addEventListener('change', function () {
			var on = els.toggle.checked;
			clearTimeout(state.debounce);
			state.debounce = setTimeout(function () {
				setEnabled(on);
				if (!on) return;
				var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
				window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
				setTimeout(function () { start(false); }, 400);
			}, 150);
		});

		if (els.play) {
			els.play.addEventListener('click', function () {
				if (synth.paused) { resume(); }
				else if (!synth.speaking) {
					if (!state.cuesInserted) insertCues();
					start(false);
				}
			});
		}
		if (els.pause) els.pause.addEventListener('click', pause);
		if (els.stop) {
			els.stop.addEventListener('click', function () {
				setEnabled(false); // calls stop() internally
			});
		}

		// An accordion opened mid-read: slot its content in right after the
		// chunk that is currently being spoken, then carry on as before.
		// 'toggle' does not bubble, so this listens in the capture phase.
		document.addEventListener('toggle', function (e) {
			var d = e.target;
			if (!d || d.tagName !== 'DETAILS' || !d.open) return;
			if (!synth.speaking && !synth.paused) return; // nothing running, nothing to do

			var text = getDetailsText(d);
			if (!text) return;

			// offset null: this text is not part of the page-wide positions,
			// so it must not move any slideshow
			var chunks = chunkText(text, MAX_CHUNK).map(function (ch) {
				return { text: ch.text, offset: null };
			});
			if (!chunks.length) return;

			Array.prototype.splice.apply(state.queue, [state.index, 0].concat(chunks));
			status('Reading the section you just opened.');
		}, true);

		// pagehide instead of beforeunload: does not break the bfcache
		window.addEventListener('pagehide', function () {
			try { synth.cancel(); } catch (e) {}
		});

		// --- Restore previous state --------------------------------------

		if (store.get('ttsEnabled')) {
			els.toggle.checked = true;
			els.toggle.setAttribute('aria-checked', 'true');
			if (els.controls) els.controls.classList.add('show');
			insertCues();

			if (store.get('ttsPlaying')) {
				setTimeout(function () { start(true); }, 300);
			}
		}
	});
})();
</script>

	<?php
});
