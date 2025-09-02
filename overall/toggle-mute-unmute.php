<?php
add_action('wp_head', function() {
  ?>
  <style>
    /* TTS Switch */
    #tts-toggle-btn{display: flex; align-items: center; gap:10px; padding-top: 15px;}
    #tts-toggle-btn input[type='checkbox']{display: none;}
    #tts-toggle-btn .toggle-visual{background: #3A4F66; border: 1px solid #192a3d; border-radius: 50px; cursor: pointer; display: inline-block; position: relative; transition: all ease-in-out 0.3s; width: 50px; height: 25px;}
    #tts-toggle-btn .toggle-visual::after{background: #192a3d; border-radius: 50%; content: ''; cursor: pointer; display: inline-block; position: absolute; left:1px; top: 1px; transition: all ease-in-out 0.3s; width: 21px; height: 21px;}
    #tts-toggle-btn input[type='checkbox']:checked + .toggle-visual{background: #0f1924; border-color: #3A4F66;}
    #tts-toggle-btn input[type='checkbox']:checked + .toggle-visual::after{background: #3A4F66; transform: translateX(25px);}
  </style>
  <?php
});

add_action('wp_footer', function() {
  ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {

      // === TTS Toggle Setup ===
      function setupTTSToggle() {
        const toggleSwitch = document.getElementById('tts-toggle-switch');
        const statusEl = document.getElementById('tts-status');
        const labelEl = document.getElementById('tts-toggle-label');
        const visualToggle = document.querySelector('.toggle-visual');
        if (!toggleSwitch || !statusEl || !labelEl || !visualToggle) return;
        labelEl.setAttribute('role', 'switch');
        statusEl.setAttribute('aria-live', 'polite');
        visualToggle.addEventListener('click', () => {
          toggleSwitch.checked = !toggleSwitch.checked;
          toggleSwitch.dispatchEvent(new Event('change'));
        });
        toggleSwitch.addEventListener('change', () => {
          if (toggleSwitch.checked) {
            localStorage.setItem('ttsEnabled', 'true');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setTimeout(speakContent, 500);
          } else {
            localStorage.removeItem('ttsEnabled');
            stopSpeaking();
          }
        });
        if (localStorage.getItem('ttsEnabled') === 'true') {
          toggleSwitch.checked = true;
          window.scrollTo({ top: 0, behavior: 'smooth' });
          setTimeout(speakContent, 500);
        }
        window.addEventListener('beforeunload', stopSpeaking);
      }

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
          if (voices.length) {
            callback(voices);
          } else {
            speechSynthesis.onvoiceschanged = () => {
              const loadedVoices = speechSynthesis.getVoices();
              if (loadedVoices.length) {
                callback(loadedVoices);
              }
            };
          }
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
            const speak = () => {
              speechSynthesis.cancel();
              const utterance = new SpeechSynthesisUtterance(text);
              const langMap = {
                'ar': 'ar-SA', 'id': 'id-ID', 'zh-CN': 'zh-CN', 'da': 'da-DK',
                'de': 'de-DE', 'es': 'es-ES', 'fr': 'fr-FR', 'hi': 'hi-IN',
                'it': 'it-IT', 'ja': 'ja-JP', 'ko': 'ko-KR', 'no': 'no-NO',
                'ru': 'ru-RU', 'fi': 'fi-FI', 'sv': 'sv-SE', 'tl': 'tl-PH',
                'th': 'th-TH', 'tr': 'tr-TR', 'vi': 'vi-VN', 'en': 'en-US'
              };
              const rawLang = localStorage.getItem('preferredLang') || 'en';
              const langCode = langMap[rawLang] || 'en-US';
              utterance.lang = langCode;
              waitForVoices(voices => {
                const matchedVoice = voices.find(v => v.lang === langCode);
                if (matchedVoice) utterance.voice = matchedVoice;
                utterance.onstart = () => { statusEl.textContent = 'Text-to-speech started.'; };
                utterance.onend = () => { statusEl.textContent = 'Text-to-speech finished.'; };
                utterance.onerror = () => { statusEl.textContent = 'Error occurred during speech.'; };
                speechSynthesis.speak(utterance);
              });
            };
            if (!voicesLoaded && speechSynthesis.getVoices().length === 0) {
              speechSynthesis.onvoiceschanged = () => {
                if (!voicesLoaded && speechSynthesis.getVoices().length > 0) {
                  voicesLoaded = true;
                  speak();
                }
              };
            } else {
              voicesLoaded = true;
              speak();
            }
          }, 300);
        };
        window.stopSpeaking = function() {
          speechSynthesis.cancel();
          const statusEl = document.getElementById('tts-status');
          if (statusEl) statusEl.textContent = 'Text-to-speech stopped.';
        };
      })();

      // === Idempotent TTS Cue for Display Posts ===
      function insertDisplayPostsCue() {
        const blocks = document.querySelectorAll('.display-posts-listing');
        blocks.forEach(block => {
          if (!block.previousElementSibling || !block.previousElementSibling.classList.contains('tts-cue')) {
            const cue = document.createElement('div');
            cue.textContent = 'Queried content coming up: Click to go to that post. ';
            cue.setAttribute('aria-live', 'polite');
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
            cue.setAttribute('aria-live', 'polite');
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
            cue.setAttribute('aria-live', 'polite');
            cue.setAttribute('role', 'status');
            cue.classList.add('tts-cue');
            cue.style.position = 'absolute';
            cue.style.left = '-9999px';
            embed.parentNode.insertBefore(cue, embed);
          }
        });
      }

      // === Initialize All Modules ===
      setupTTSToggle();
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

    });
  </script>

  <?php
});
?>
