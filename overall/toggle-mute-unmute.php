<?php
function tts_toggle_button_shortcode() {
  ob_start();
  ?>
  <style>
    #tts-toggle-btn {
      align-items: center;
      padding: 2.5px 0 0 2.5px;
    }
    #tts-toggle-btn input[type='checkbox'] {
      display: none;
    }
    #tts-toggle-btn label {
      background: #3A4F66;
      border: 1px solid #192a3d;
      border-radius: 50px;
      cursor: pointer;
      display: inline-block;
      position: relative;
      transition: all 0.3s;
      width: 50px;
      height: 25px;
    }
    #tts-toggle-btn label::after {
      background: #192a3d;
      border-radius: 50%;
      content: '';
      position: absolute;
      left: 1px;
      top: 1px;
      transition: all 0.3s;
      width: 21px;
      height: 21px;
    }
    #tts-toggle-btn input:checked ~ label {
      background: #0f1924;
      border-color: #3A4F66;
    }
    #tts-toggle-btn input:checked ~ label::after {
      background: #3A4F66;
      transform: translateX(25px);
    }
    #tts-toggle-btn label:focus-visible {
      outline: 2px solid #fff;
      outline-offset: 2px;
    }
    .tts-toggle-btn-accessibility-label {
      position: absolute;
      left: -9999px;
      width: 1px;
      height: 1px;
      overflow: hidden;
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      let isSpeaking = false;
      const toggleSwitch = document.getElementById('tts-toggle-switch');
      const statusEl = document.getElementById('tts-status');
      const labelEl = document.querySelector("label[for='tts-toggle-switch']");

      labelEl.setAttribute('role', 'switch');
      statusEl.setAttribute('aria-live', 'polite');

      function getReadableText() {
        const main = document.querySelector('main');
        if (!main) return '';
        const clone = main.cloneNode(true);
        clone.querySelectorAll('nav, aside, footer, header, style, script, select, code, svg, img, iframe, .social-icons, .share-buttons, .tag-cloud, .like-dislike-container, .slick-arrow, .slick-cloned').forEach(el => el.remove());
        clone.querySelectorAll('a, button').forEach(el => {
          const text = document.createTextNode(el.textContent);
          el.replaceWith(text);
        });
        return clone.innerText.trim();
      }

      function speakContent() {
        const text = getReadableText();
        if (text.length < 10) {
          statusEl.textContent = 'No readable content found.';
          return;
        }
        const utterance = new SpeechSynthesisUtterance(text);
        speechSynthesis.speak(utterance);
        isSpeaking = true;
        toggleSwitch.checked = true;
        labelEl.setAttribute('aria-checked', 'true');
        statusEl.textContent = 'Text-to-speech started.';
      }

      function stopSpeaking() {
        speechSynthesis.cancel();
        isSpeaking = false;
        toggleSwitch.checked = false;
        labelEl.setAttribute('aria-checked', 'false');
        statusEl.textContent = 'Text-to-speech stopped.';
      }

      toggleSwitch.addEventListener('change', () => {
        if (toggleSwitch.checked) {
          localStorage.setItem('ttsEnabled', 'true');
          window.scrollTo({ top: 0, behavior: 'smooth' });
          setTimeout(() => {
            speakContent();
          }, 500);
        } else {
          localStorage.removeItem('ttsEnabled');
          stopSpeaking();
        }
      });

      window.addEventListener('beforeunload', () => {
        stopSpeaking();
      });

      if (localStorage.getItem('ttsEnabled') === 'true') {
        toggleSwitch.checked = true;
        labelEl.setAttribute('aria-checked', 'true');
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
          speakContent();
        }, 500);
      }
    });
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode('tts_toggle', 'tts_toggle_button_shortcode');
?>
