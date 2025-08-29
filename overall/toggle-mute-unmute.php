<?php
add_action('wp_footer', function () {
    ?>
    <!-- Preload for faster fetch if likely to be used -->
    <link rel="preload" href="https://unpkg.com/ttsreader-plugin/main.js" as="script">

    <style>
        #tts-toggle-btn {
            align-items: center;
            padding: 2.5px 0 0 2.5px;
        }
        #tts-toggle-btn input[type='checkbox'] { display: none; }
        #tts-toggle-btn label {
            background: #3A4F66;
            border: 1px solid #192a3d;
            border-radius: 50px;
            cursor: pointer;
            display: inline-block;
            position: relative;
            transition: all 0.3s;
            width: 50px; height: 25px;
        }
        #tts-toggle-btn label::after {
            background: #192a3d;
            border-radius: 50%;
            content: '';
            position: absolute;
            left: 1px; top: 1px;
            transition: all 0.3s;
            width: 21px; height: 21px;
        }
        #tts-toggle-btn input:checked ~ label {
            background: #0f1924; border-color: #3A4F66;
        }
        #tts-toggle-btn input:checked ~ label::after {
            background: #3A4F66; transform: translateX(25px);
        }
        #tts-toggle-btn label:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
        .tts-toggle-btn-accessibility-label {
            position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;
        }
        .TTSPlugin-Playback-buttons-bar > span { border: none; }
        .TTSPlugin-Playback-buttons-bar button:hover svg { fill: #c53030; }
        .TTSPlugin .Play-btn { border-left: 1px solid #3A4F66; border-right: 1px solid #3A4F66; }
        .TTSPlugin .Play-btn:hover { border: 1px solid #3A4F66; }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const ttsToggleSwitch = document.getElementById('tts-toggle-switch');
        const ttsScriptUrl = "https://unpkg.com/ttsreader-plugin/main.js";
        let cachedPauseBtn, cachedPlayBtn;

        const setTTSState = state => localStorage.setItem('ttsState', state);

        const loadTTSReader = () => {
            if (document.querySelector(`script[src="${ttsScriptUrl}"]`)) return;
            const script = document.createElement('script');
            script.src = ttsScriptUrl;
            script.defer = true;
            script.onload = () => {
                const interval = setInterval(() => {
                    cachedPauseBtn = cachedPauseBtn || document.querySelector('.TTSPlugin .Pause-btn');
                    cachedPlayBtn  = cachedPlayBtn  || document.querySelector('.TTSPlugin .Play-btn');
                    const state = localStorage.getItem('ttsState');
                    if (state === 'paused' && cachedPauseBtn && window.speechSynthesis.speaking) {
                        cachedPauseBtn.click();
                        clearInterval(interval);
                    }
                    if (state !== 'paused' && cachedPlayBtn) {
                        clearInterval(interval);
                    }
                }, 100);
                setTimeout(() => clearInterval(interval), 5000);
            };
            document.body.appendChild(script);
        };

        const unloadTTSReader = () => {
            document.querySelector(`script[src="${ttsScriptUrl}"]`)?.remove();
            document.querySelector('.TTSPlugin')?.remove();
            localStorage.removeItem('ttsActive');
            localStorage.removeItem('ttsState');
        };

        const toggleTTS = () => {
            if (localStorage.getItem('ttsActive')) {
                unloadTTSReader();
                ttsToggleSwitch.checked = false;
                ttsToggleSwitch.setAttribute('aria-checked', 'false');
            } else {
                localStorage.setItem('ttsActive', 'true');
                setTTSState('paused');
                loadTTSReader();
                ttsToggleSwitch.checked = true;
                ttsToggleSwitch.setAttribute('aria-checked', 'true');
            }
        };

        window.addEventListener('beforeunload', () => {
            try { speechSynthesis.cancel(); } catch {}
        });

        document.addEventListener('click', e => {
            if (e.target.closest('.TTSPlugin .Play-btn'))  setTTSState('playing');
            if (e.target.closest('.TTSPlugin .Pause-btn')) setTTSState('paused');
        });

        if (ttsToggleSwitch) {
            ttsToggleSwitch.addEventListener('change', toggleTTS);
            if (localStorage.getItem('ttsActive')) {
                ttsToggleSwitch.checked = true;
                ttsToggleSwitch.setAttribute('aria-checked', 'true');
                loadTTSReader();
            }
        }
    });
    </script>
    <?php
});
?>
