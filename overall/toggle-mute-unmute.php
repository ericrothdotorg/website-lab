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
      let isSpeaking = false;
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

      function getReadableText() {
        const main = document.querySelector('main');
        if (!main) return '';
        const clone = main.cloneNode(true);
        clone.querySelectorAll('nav,aside,footer,header,style,script,select,code,svg,img,iframe,.social-icons,.share-buttons,.tag-cloud,.like-dislike-container,.slick-arrow,.slick-cloned').forEach(el => el.remove());
        clone.querySelectorAll('a,button').forEach(el => {
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
        statusEl.textContent = 'Text-to-speech started.';
      }

      function stopSpeaking() {
        speechSynthesis.cancel();
        isSpeaking = false;
        toggleSwitch.checked = false;
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
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
          speakContent();
        }, 500);
      }
    });
  </script>
  <?php
});
?>
