<?php
add_action('wp_head', function() {
  ?>
  <style>
    /* TTS Switch */
    #tts-toggle-btn {display: flex; align-items: center; gap:10px; padding-top: 15px;}
    #tts-toggle-btn input[type='checkbox'] {display: none;}
    #tts-toggle-btn .toggle-visual {background: #3A4F66; border: 1px solid #192a3d; border-radius: 50px; cursor: pointer; display: inline-block; position: relative; transition: all ease-in-out 0.3s; width: 50px; height: 25px;}
    #tts-toggle-btn .toggle-visual::after {background: #192a3d; border-radius: 50%; content: ''; cursor: pointer; display: inline-block; position: absolute; left:1px; top: 1px; transition: all ease-in-out 0.3s; width: 21px; height: 21px;}
    #tts-toggle-btn input[type='checkbox']:checked + .toggle-visual {background: #0f1924; border-color: #3A4F66;}
    #tts-toggle-btn input[type='checkbox']:checked + .toggle-visual::after {background: #3A4F66; transform: translateX(25px);}

    /* TTS Controls Button */
    #tts-controls {display: flex; align-items: center; gap: 10px; position: fixed; bottom: 27.5px; left: 20px; opacity: 0; pointer-events: none; transition: opacity 0.3s; z-index: 9999;}
    #tts-controls.show {opacity: 1; pointer-events: auto;}
    #tts-controls button {padding: 5px 10px; border: none; border-radius: 5px; background: #3A4F66; color: white; cursor: pointer; transition: background 0.2s;}
    #tts-controls button:hover, #tts-controls button:focus {background: #192a3d; outline: none;}
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
      try { speechSynthesis.cancel(); } catch(e) {}

      // === Lazy-load TTS on first toggle Click ===
      let ttsInitialized = false;
      function initTTS() {

        // === TTS Content Extraction ===
        function getReadableText() {
          const main = document.querySelector('main') || document.body;
          if (!main) return '';
          const clone = main.cloneNode(true);
          clone.querySelectorAll('nav,aside,footer,header,style,script,select,code,img,svg,iframe,.social-icons,.share-buttons,.tag-cloud,.like-dislike-container,.slick-arrow,.slick-dots,.slick-cloned').forEach(el => el.remove());
          clone.querySelectorAll('a,button').forEach(el => {
            const text = document.createTextNode(el.textContent);
            el.replaceWith(text);
          });
          return clone.innerText.trim();
        }

        // === TTS Speak & Stop ===
        (function() {
          let voicesLoaded = false;
          let ttsTimeout;
          function waitForVoices(callback) {
            const voices = speechSynthesis.getVoices();
            if (voices.length) callback(voices);
            else speechSynthesis.onvoiceschanged = () => {
              const loadedVoices = speechSynthesis.getVoices();
              if (loadedVoices.length) callback(loadedVoices);
            };
          }
          window.speakContent = function() {
            clearTimeout(ttsTimeout);
            ttsTimeout = setTimeout(() => {
              const text = getReadableText();
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
        })();

        // === Idempotent TTS Cue for Display Posts ===
        function insertDisplayPostsCue() {
          const blocks = document.querySelectorAll('.display-posts-listing');
          blocks.forEach(block => {
            if (!block.previousElementSibling || !block.previousElementSibling.classList.contains('tts-cue')) {
              const cue = document.createElement('div');
              cue.textContent = 'Queried content coming up: Click to go to that post. ';
              cue.setAttribute('aria-live','polite');
              cue.setAttribute('role','status');
              cue.classList.add('tts-cue');
              cue.style.position = 'absolute';
              cue.style.left = '-9999px';
              block.parentNode.insertBefore(cue, block);
            }
          });
        }

        // === Idempotent TTS Cue for Single Item Slideshows ===
        function insertSlideshowCue() {
          const slideshows = document.querySelectorAll('.slideshow-single-item');
          slideshows.forEach(slideshow => {
            if (!slideshow.previousElementSibling || !slideshow.previousElementSibling.classList.contains('tts-cue')) {
              const cue = document.createElement('div');
              cue.textContent = 'Slideshow content ahead. You may also swipe to view items. ';
              cue.setAttribute('aria-live','polite');
              cue.classList.add('tts-cue');
              cue.style.position = 'absolute';
              cue.style.left = '-9999px';
              slideshow.parentNode.insertBefore(cue, slideshow);
            }
          });
        }

        // === Idempotent TTS Cue for YouTube Embeds ===
        function insertYouTubeCue() {
          const embeds = document.querySelectorAll('.wp-block-embed-youtube');
          embeds.forEach(embed => {
            if (!embed.previousElementSibling || !embed.previousElementSibling.classList.contains('tts-cue')) {
              const cue = document.createElement('div');
              cue.textContent = 'Video content ahead. Click to play it. ';
              cue.setAttribute('aria-live','polite');
              cue.setAttribute('role','status');
              cue.classList.add('tts-cue');
              cue.style.position = 'absolute';
              cue.style.left = '-9999px';
              embed.parentNode.insertBefore(cue, embed);
            }
          });
        }

        // === Initialize All Modules ===
        insertDisplayPostsCue();
        insertSlideshowCue();
        insertYouTubeCue();

        // === Observe dynamically added Content safely ===
        const observer = new MutationObserver(mutations => {
          mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
              if (!(node instanceof HTMLElement)) return;
              if (node.matches('.slideshow-single-item') || node.querySelector('.slideshow-single-item')) insertSlideshowCue();
              if (node.matches('.display-posts-listing') || node.querySelector('.display-posts-listing')) insertDisplayPostsCue();
              if (node.matches('.wp-block-embed-youtube') || node.querySelector('.wp-block-embed-youtube')) insertYouTubeCue();
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

      } // End initTTS

      // === Stop TTS on Navigation ===
      window.addEventListener('beforeunload', function() {
        speechSynthesis.cancel();
      });

      // === TTS Toggle Setup with Lazy-load ===
      const toggles = document.querySelectorAll('#tts-toggle-switch');
      const controls = document.getElementById('tts-controls');

      // === Show TTS Controls immediately if previously enabled ===
      if (localStorage.getItem('ttsEnabled')) {
        if (!ttsInitialized) { initTTS(); ttsInitialized = true; }
        setTimeout(() => {
          controls?.classList.add('show');
          document.querySelectorAll('#tts-toggle-switch').forEach(toggle => toggle.checked = true);
        }, 100);
      }

      // === Auto-resume TTS if it was playing before ===
      if (localStorage.getItem('ttsEnabled') && localStorage.getItem('ttsPlaying')) {
        setTimeout(() => {
          if (!ttsInitialized) {
            initTTS();
            ttsInitialized = true;
          }
          controls?.classList.add('show');
          document.querySelectorAll('#tts-toggle-switch').forEach(toggle => toggle.checked = true);
          setTimeout(speakContent, 300);
        }, 200);
      }

      toggles.forEach(toggleSwitch => {
        const visualToggle = toggleSwitch.parentNode.querySelector('.toggle-visual');
        if (!toggleSwitch || !visualToggle) return;
        visualToggle.addEventListener('click', () => {
          toggleSwitch.checked = !toggleSwitch.checked;
          toggleSwitch.dispatchEvent(new Event('change'));
        });
        toggleSwitch.addEventListener('change', () => {
          if (toggleSwitch.checked) {
            if (!ttsInitialized) { initTTS(); ttsInitialized = true; }
            controls.classList.add('show');
            localStorage.setItem('ttsEnabled','true');
            window.scrollTo({top:0, behavior:'smooth'});
            setTimeout(speakContent,500);
          } else {
            controls.classList.remove('show');
            stopSpeaking?.();
            localStorage.removeItem('ttsEnabled');
          }
        });
      });

    });
  </script>
  <?php
});
?>
