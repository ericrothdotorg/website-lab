<?php

add_action('wp_head', function() {
  ?>
  <style>
	/* TTS Toggle Button */
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

	/* TTS Accessibility Labels */
	#tts-status, .tts-toggle-btn-accessibility-label {
	  position: absolute;
	  left: -9999px;
	  width: 1px;
	  height: 1px;
	  overflow: hidden;
	}
	
	/* TTS Controls Button (Play, Pause, Stop) */
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
        const ttsToggle = document.getElementById('tts-toggle-btn');
        if (ttsToggle) ttsToggle.style.setProperty('display', 'none');
        return;
      }

      // Safe localStorage wrapper
      const safeStorage = {
        get: (key) => {
          try {
            return localStorage.getItem(key);
          } catch(e) {
            console.warn('localStorage get failed:', e);
            return null;
          }
        },
        set: (key, val) => {
          try {
            localStorage.setItem(key, val);
            return true;
          } catch(e) {
            console.warn('localStorage set failed:', e);
            return false;
          }
        },
        remove: (key) => {
          try {
            localStorage.removeItem(key);
            return true;
          } catch(e) {
            console.warn('localStorage remove failed:', e);
            return false;
          }
        }
      };

      // TTS Manager Class
      class TTSManager {
        constructor() {
          this.initialized = false;
          this.ttsTimeout = null;
          this.toggleDebounceTimer = null;
          this.contentObserver = null;
          this.voicesChangedHandled = false;
          this.isPaused = false;
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
          this.initialized = true;
          
          // === TTS Content Extraction ===
          this.getReadableText = function() {
            const main = document.querySelector('main') || document.body;
            if (!main) return '';
            const clone = main.cloneNode(true);
            clone.querySelectorAll('nav,aside,footer,header,style,script,select,code,img,svg,iframe,.social-icons,.share-buttons,.tag-cloud,.like-dislike-container,.slick-arrow,.slick-dots,.slick-cloned,.tts-cue').forEach(el => el.remove());
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
              const rawLang = safeStorage.get('preferredLang') || 'en';
              const targetLang = langMap[rawLang] || 'en-US';
              const utterance = new SpeechSynthesisUtterance(text);
              utterance.lang = targetLang;
              const speak = () => {
                if (self.voicesChangedHandled) return;
                self.voicesChangedHandled = true;
                
                const voices = speechSynthesis.getVoices();
                const matchedVoice = voices.find(v => v.lang === targetLang);
                if (!matchedVoice) {
                  statusEl.textContent = 'No matching voice found for ' + targetLang;
                  self.voicesChangedHandled = false;
                  return;
                }
                utterance.voice = matchedVoice;
                utterance.onstart = () => { 
                  statusEl.textContent = 'Text-to-speech started.';
                  self.isPaused = false;
                };
                utterance.onend = () => { 
                  statusEl.textContent = 'Text-to-speech finished.';
                  self.voicesChangedHandled = false;
                  safeStorage.remove('ttsPlaying');
                };
                utterance.onerror = () => { 
                  statusEl.textContent = 'Error occurred during speech.';
                  self.voicesChangedHandled = false;
                };
                speechSynthesis.cancel();
                speechSynthesis.speak(utterance);
                safeStorage.set('ttsPlaying', 'true');
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
            const controls = document.getElementById('tts-controls');
            if (controls) controls.classList.remove('show');
            safeStorage.remove('ttsEnabled');
            safeStorage.remove('ttsPlaying');
            self.isPaused = false;
            self.cleanupCues();
            if (self.contentObserver) {
              self.contentObserver.disconnect();
              self.contentObserver = null;
            }
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

          // === Cleanup all TTS cues ===
          this.cleanupCues = function() {
            document.querySelectorAll('.tts-cue').forEach(cue => cue.remove());
          };

          // === Initialize all Modules ===
          this.insertDisplayPostsCue();
          this.insertSlideshowCue();
          this.insertYouTubeCue();

          // === Observe dynamically added Content safely ===
          this.contentObserver = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
              mutation.addedNodes.forEach(node => {
                if (!(node instanceof HTMLElement)) return;
                if (node.matches('.slideshow-single-item') || node.querySelector('.slideshow-single-item')) this.insertSlideshowCue();
                if (node.matches('.display-posts-listing') || node.querySelector('.display-posts-listing')) this.insertDisplayPostsCue();
                if (node.matches('.wp-block-embed-youtube') || node.querySelector('.wp-block-embed-youtube')) this.insertYouTubeCue();
              });
            });
          });
          this.contentObserver.observe(document.body, { childList: true, subtree: true });

          // === TTS Play / Pause / Stop Controls Button Handlers ===
          const btnPlay = document.getElementById('tts-play-icon');
          const btnPause = document.getElementById('tts-pause-icon');
          const btnStop = document.getElementById('tts-stop-icon');
          if (btnPlay) {
            btnPlay.addEventListener('click', () => {
              if (speechSynthesis.paused) {
                speechSynthesis.resume();
                self.isPaused = false;
                safeStorage.set('ttsPlaying', 'true');
              } else if (window.speakContent) {
                window.speakContent();
              }
            });
          }
          if (btnPause) {
            btnPause.addEventListener('click', () => {
              if (!speechSynthesis.paused && speechSynthesis.speaking) {
                speechSynthesis.pause();
                self.isPaused = true;
                safeStorage.set('ttsPaused', 'true');
              }
            });
          }
          if (btnStop) {
            btnStop.addEventListener('click', () => {
              if (window.stopSpeaking) window.stopSpeaking();
            });
          }

          // === Keyboard Accessibility for TTS Controls Button ===
          document.querySelectorAll('#tts-controls button').forEach(btn => {
            btn.addEventListener('keydown', e => {
              if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
            });
          });
        }
        setupNavCleanup() {
          window.addEventListener('beforeunload', function() {
            try {
              if (speechSynthesis.speaking && !speechSynthesis.paused) {
                // Only cancel if we're the ones using it
                speechSynthesis.cancel();
              }
            } catch(e) {}
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
              clearTimeout(self.toggleDebounceTimer);
              self.toggleDebounceTimer = setTimeout(() => {
                if (toggleSwitch.checked) {
                  if (!self.initialized) { self.initTTS(); }
                  if (controls) controls.classList.add('show');
                  safeStorage.set('ttsEnabled','true');
                  window.scrollTo({top:0, behavior:'smooth'});
                  setTimeout(() => {
                    if (window.speakContent) window.speakContent();
                  }, 500);
                } else {
                  if (controls) controls.classList.remove('show');
                  if (window.stopSpeaking) window.stopSpeaking();
                  safeStorage.remove('ttsEnabled');
                }
              }, 200);
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
          if (safeStorage.get('ttsEnabled')) {
            if (!self.initialized) { self.initTTS(); }
            setTimeout(() => {
              if (controls) controls.classList.add('show');
              document.querySelectorAll('#tts-toggle-switch').forEach(toggle => toggle.checked = true);
            }, 100);
          }

          // === Auto-resume TTS if it was playing before (not paused) ===
          if (safeStorage.get('ttsEnabled') && safeStorage.get('ttsPlaying') && !safeStorage.get('ttsPaused')) {
            setTimeout(() => {
              if (!self.initialized) { self.initTTS(); }
              if (controls) controls.classList.add('show');
              document.querySelectorAll('#tts-toggle-switch').forEach(toggle => toggle.checked = true);
              setTimeout(() => { if (window.speakContent) window.speakContent(); }, 500);
            }, 200);
          } else if (safeStorage.get('ttsPaused')) {
            // Was paused, don't auto-resume but keep controls visible
            safeStorage.remove('ttsPaused');
          }
        }
      }

      // Initialize TTS Manager
      window.ttsManager = new TTSManager();
    });
  </script>
  <?php
});
