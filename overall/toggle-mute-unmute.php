<?php

add_action('wp_head', function() {
  ?>
  <style>
	/* Toggle Button */
	#tts-toggle-btn {display: flex; align-items: center; gap: 10px; padding-top: 15px;}
	#tts-toggle-btn input[type='checkbox'] {display: none;}
	#tts-toggle-btn .toggle-visual {
	  background: #3A4F66;
	  border: 1px solid #192a3d;
	  border-radius: 50px;
	  cursor: pointer;
	  display: inline-block;
	  position: relative;
	  transition: all ease-in-out 0.3s;
	  width: 50px;
	  height: 25px;
	}
	#tts-toggle-btn .toggle-visual::after {
	  background: #192a3d;
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
	#tts-toggle-btn input[type='checkbox']:checked + .toggle-visual {background: #0f1924; border-color: #3A4F66;}
	#tts-toggle-btn input[type='checkbox']:checked + .toggle-visual::after {background: #3A4F66; transform: translateX(25px);}

	/* Accessibility Labels */
	#tts-status, .tts-toggle-btn-accessibility-label {
	  position: absolute;
	  left: -9999px;
	  width: 1px;
	  height: 1px;
	  overflow: hidden;
	}
	
	/* Controls Button */
	#tts-controls {
	  display: flex;
	  align-items: center;
	  gap: 10px;
	  position: fixed;
	  bottom: 27.5px;
	  left: 20px;
	  opacity: 0;
	  pointer-events: none;
	  transition: opacity 0.3s;
	  z-index: 9999;
	}
	#tts-controls.show {
	  opacity: 1;
	  pointer-events: auto;
	}
	#tts-controls button {
	  padding: 5px 10px;
	  border: none;
	  border-radius: 5px;
	  background: #3A4F66;
	  color: white;
	  cursor: pointer;
	  transition: background 0.2s;
	}
	#tts-controls button:hover,
	#tts-controls button:focus {
	  background: #192a3d;
	  outline: none;
	}
  </style>
  <?php
});

add_action('wp_footer', function() {
  ?>
  <!-- TTS Play / Pause / Stop Controls Button -->
  <div id="tts-controls" style="display: inline-block; padding: 0;">
    <button id="tts-play" aria-label="Text-to-speech controls" style="display: flex; gap: 10px; padding: 5px 10px; background: #3A4F66; color: white; border: none; border-radius: 5px; cursor: pointer;">
      <span id="tts-play-icon" aria-label="Play text-to-speech" title="Play" style="cursor: pointer;">▶</span>
      <span id="tts-pause-icon" aria-label="Pause text-to-speech" title="Pause" style="cursor: pointer;">⏸</span>
      <span id="tts-stop-icon" aria-label="Stop text-to-speech" title="Stop" style="cursor: pointer;">⏹</span>
    </button>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {

      if (!('speechSynthesis' in window)) {
        console.warn('Speech synthesis not supported in this browser');
        document.getElementById('tts-toggle-btn')?.style.setProperty('display', 'none');
        return;
      }

      try { speechSynthesis.cancel(); } catch(e) {}

      // TTS Manager Class
      class TTSManager {
        constructor() {
          this.initialized = false;
          this.ttsTimeout = null;
          this.init();
        }
        init() {
          this.setupTTSOnToggle();
          this.setupNavCleanup();
          this.restorePreviousState();
        }

        // === Lazy-load TTS on first toggle Click ===
        initTTS() {
          if (this.initialized) return;
          
          // === TTS Content Extraction ===
          this.getReadableText = function() {
            const main = document.querySelector('main') || document.body;
            if (!main) return '';
            const clone = main.cloneNode(true);
            clone.querySelectorAll('nav,aside,footer,header,style,script,select,code,img,svg,iframe,.social-icons,.share-buttons,.tag-cloud,.like-dislike-container,.slick-arrow,.slick-dots,.slick-cloned').forEach(el => el.remove());
            clone.querySelectorAll('a,button').forEach(el => {
              const text = document.createTextNode(el.textContent);
              el.replaceWith(text);
            });
            return clone.innerText.trim();
          };

          // === TTS Speak & Stop ===
          const self = this;
          window.speakContent = function() {
            clearTimeout(self.ttsTimeout);
            self.ttsTimeout = setTimeout(() => {
              const text = self.getReadableText();
              const statusEl = document.getElementById('tts-status');
              if (!statusEl) return;
              if (text.length < 10) {
                statusEl.textContent = 'No readable content found.';
                return;
              }
              const langMap = {
                'ar':'ar-SA','id':'id-ID','zh-CN':'zh-CN','da':'da-DK',
                'de':'de-DE','es':'es-ES','fr':'fr-FR','hi':'hi-IN',
                'it':'it-IT','ja':'ja-JP','ko':'ko-KR','no':'no-NO',
                'ru':'ru-RU','fi':'fi-FI','sv':'sv-SE','tl':'tl-PH',
                'th':'th-TH','tr':'tr-TR','vi':'vi-VN','en':'en-US'
              };
              const rawLang = localStorage.getItem('preferredLang') || 'en';
              const targetLang = langMap[rawLang] || 'en-US';
              const utterance = new SpeechSynthesisUtterance(text);
              utterance.lang = targetLang;
              const speak = () => {
                const voices = speechSynthesis.getVoices();
                const matchedVoice = voices.find(v => v.lang === targetLang);
                if (!matchedVoice) {
                  statusEl.textContent = 'No matching voice found for ' + targetLang;
                  return;
                }
                utterance.voice = matchedVoice;
                utterance.onstart = () => { statusEl.textContent = 'Text-to-speech started.'; };
                utterance.onend = () => { statusEl.textContent = 'Text-to-speech finished.'; };
                utterance.onerror = () => { statusEl.textContent = 'Error occurred during speech.'; };
                speechSynthesis.cancel();
                speechSynthesis.speak(utterance);
                localStorage.setItem('ttsPlaying', 'true');
              };
              if (speechSynthesis.getVoices().length === 0) {
                speechSynthesis.onvoiceschanged = speak;
              } else {
                speak();
              }
            }, 300);
          };
          window.stopSpeaking = function() {
            speechSynthesis.cancel();
            const statusEl = document.getElementById('tts-status');
            if (statusEl) statusEl.textContent = 'Text-to-speech stopped.';
            document.querySelectorAll('#tts-toggle-switch').forEach(toggle => toggle.checked = false);
            document.getElementById('tts-controls')?.classList.remove('show');
            localStorage.removeItem('ttsEnabled');
            localStorage.removeItem('ttsPlaying');
          };

          // === Idempotent TTS Cue for Display Posts ===
          this.insertDisplayPostsCue = function() {
            const blocks = document.querySelectorAll('.display-posts-listing');
            blocks.forEach(block => {
              if (!block.previousElementSibling || !block.previousElementSibling.classList.contains('tts-cue')) {
                const cue = document.createElement('div');
                cue.textContent = 'Queried content coming up: Click to go to that post. ';
                cue.setAttribute('aria-live','polite');
                cue.setAttribute('role','status');
                cue.classList.add('tts-cue');
                cue.style.position = 'absolute';
                cue.style.left = '-9999px';
                block.parentNode.insertBefore(cue, block);
              }
            });
          };

          // === Idempotent TTS Cue for Single Item Slideshows ===
          this.insertSlideshowCue = function() {
            const slideshows = document.querySelectorAll('.slideshow-single-item');
            slideshows.forEach(slideshow => {
              if (!slideshow.previousElementSibling || !slideshow.previousElementSibling.classList.contains('tts-cue')) {
                const cue = document.createElement('div');
                cue.textContent = 'Slideshow content ahead. You may also swipe to view items. ';
                cue.setAttribute('aria-live','polite');
                cue.classList.add('tts-cue');
                cue.style.position = 'absolute';
                cue.style.left = '-9999px';
                slideshow.parentNode.insertBefore(cue, slideshow);
              }
            });
          };

          // === Idempotent TTS Cue for YouTube Embeds ===
          this.insertYouTubeCue = function() {
            const embeds = document.querySelectorAll('.wp-block-embed-youtube');
            embeds.forEach(embed => {
              if (!embed.previousElementSibling || !embed.previousElementSibling.classList.contains('tts-cue')) {
                const cue = document.createElement('div');
                cue.textContent = 'Video content ahead. Click to play it. ';
                cue.setAttribute('aria-live','polite');
                cue.setAttribute('role','status');
                cue.classList.add('tts-cue');
                cue.style.position = 'absolute';
                cue.style.left = '-9999px';
                embed.parentNode.insertBefore(cue, embed);
              }
            });
          };

          // === Initialize all Modules ===
          this.insertDisplayPostsCue();
          this.insertSlideshowCue();
          this.insertYouTubeCue();

          // === Observe dynamically added Content safely ===
          const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
              mutation.addedNodes.forEach(node => {
                if (!(node instanceof HTMLElement)) return;
                if (node.matches('.slideshow-single-item') || node.querySelector('.slideshow-single-item')) this.insertSlideshowCue();
                if (node.matches('.display-posts-listing') || node.querySelector('.display-posts-listing')) this.insertDisplayPostsCue();
                if (node.matches('.wp-block-embed-youtube') || node.querySelector('.wp-block-embed-youtube')) this.insertYouTubeCue();
              });
            });
          });
          observer.observe(document.body, { childList: true, subtree: true });

          // === TTS Play / Pause / Stop Controls Button Handlers ===
          const btnPlay = document.getElementById('tts-play-icon');
          const btnPause = document.getElementById('tts-pause-icon');
          const btnStop = document.getElementById('tts-stop-icon');
          btnPlay?.addEventListener('click', () => {
            if (speechSynthesis.paused) speechSynthesis.resume();
            else speakContent();
          });
          btnPause?.addEventListener('click', () => {
            if (!speechSynthesis.paused) speechSynthesis.pause();
            localStorage.removeItem('ttsPlaying');
          });
          btnStop?.addEventListener('click', () => {
            stopSpeaking();
          });

          // === Keyboard Accessibility for TTS Controls Button ===
          document.querySelectorAll('#tts-controls button').forEach(btn => {
            btn.addEventListener('keydown', e => {
              if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
            });
          });
          this.initialized = true;
        }
        setupNavCleanup() {
          window.addEventListener('beforeunload', function() {
            speechSynthesis.cancel();
          });
        }
        setupTTSOnToggle() {
          const self = this;
          const controls = document.getElementById('tts-controls');

          // Function to setup individual Toggle
          const setupToggle = (toggleSwitch) => {
            const visualToggle = toggleSwitch.parentNode.querySelector('.toggle-visual');
            if (!toggleSwitch || !visualToggle) return;
            
            visualToggle.addEventListener('click', () => {
              toggleSwitch.checked = !toggleSwitch.checked;
              toggleSwitch.dispatchEvent(new Event('change'));
            });
            
            toggleSwitch.addEventListener('change', () => {
              if (toggleSwitch.checked) {
                if (!self.initialized) { self.initTTS(); }
                controls.classList.add('show');
                localStorage.setItem('ttsEnabled','true');
                window.scrollTo({top:0, behavior:'smooth'});
                setTimeout(() => {
                  if (window.speakContent) window.speakContent();
                }, 500);
              } else {
                controls.classList.remove('show');
                if (window.stopSpeaking) window.stopSpeaking();
                localStorage.removeItem('ttsEnabled');
              }
            });
          };

          // Setup existing Toggles
          document.querySelectorAll('#tts-toggle-switch').forEach(setupToggle);

          // Watch for new Toggles added dynamically
          const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
              mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) { // Element node
                  if (node.id === 'tts-toggle-switch') setupToggle(node);
                  const newToggles = node.querySelectorAll && node.querySelectorAll('#tts-toggle-switch');
                  if (newToggles) newToggles.forEach(setupToggle);
                }
              });
            });
          });
          observer.observe(document.body, { childList: true, subtree: true });
        }
        restorePreviousState() {
          const self = this;
          const controls = document.getElementById('tts-controls');

          // === Show TTS Controls immediately if previously enabled ===
          if (localStorage.getItem('ttsEnabled')) {
            if (!self.initialized) { self.initTTS(); }
            setTimeout(() => {
              controls?.classList.add('show');
              document.querySelectorAll('#tts-toggle-switch').forEach(toggle => toggle.checked = true);
            }, 100);
          }

          // === Auto-resume TTS if it was playing before ===
          if (localStorage.getItem('ttsEnabled') && localStorage.getItem('ttsPlaying')) {
            setTimeout(() => {
              if (!self.initialized) { self.initTTS(); }
              controls?.classList.add('show');
              document.querySelectorAll('#tts-toggle-switch').forEach(toggle => toggle.checked = true);
              setTimeout(() => { if (window.speakContent) window.speakContent(); }, 500);
            }, 200);
          }
        }
      }

      // Initialize TTS Manager
      window.ttsManager = new TTSManager();
    });
  </script>
  <?php
});
