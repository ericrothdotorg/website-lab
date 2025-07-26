<?php 
add_action('wp_footer', function () {
    ?>
    <style>
        /* Mute-Unmute Mode Switch */
        #tts-toggle-btn {align-items: center; padding-top: 2.5px; padding-left: 2.5px;}
        #tts-toggle-btn input[type='checkbox'] {display: none;}
        #tts-toggle-btn label {background: #3A4F66; border: 1px solid #192a3d; border-radius: 50px; cursor: pointer; display: inline-block; position: relative; -webkit-transition: all ease-in-out 0.3s; transition: all ease-in-out 0.3s; width: 50px; height: 25px;}
        #tts-toggle-btn label::after {background: #192a3d; border-radius: 50%; content: ''; cursor: pointer; display: inline-block; position: absolute; left: 1px; top: 1px; -webkit-transition: all ease-in-out 0.3s; transition: all ease-in-out 0.3s; width: 21px; height: 21px;}
        #tts-toggle-btn input[type='checkbox']:checked ~ label {background: #0f1924; border-color: #3A4F66;}
        #tts-toggle-btn input[type='checkbox']:checked ~ label::after {background: #3A4F66; -webkit-transform: translateX(25px); transform: translateX(25px);}
        .tts-toggle-btn-accessibility-label {position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; display: none;}
        /* TTS Reader UI Element */
        .TTSPlugin-Playback-buttons-bar>span {border: none;}
        .TTSPlugin-Playback-buttons-bar button:hover svg {fill: #c53030;}
        .TTSPlugin .Play-btn {border-left: 1px solid #3A4F66; border-right: 1px solid #3A4F66;}
        .TTSPlugin .Play-btn:hover {border: 1px solid #3A4F66;}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ttsToggleSwitch = document.getElementById('tts-toggle-switch');
            const ttsScriptUrl = "https://unpkg.com/ttsreader-plugin/main.js";
            function toggleTTS() {
                let existingScript = document.querySelector(`script[src="${ttsScriptUrl}"]`);
                let existingTTSUI = document.querySelector('.TTSPlugin');
                if (existingScript) {
                    existingScript.remove();
                    localStorage.removeItem('ttsActive');
                    ttsToggleSwitch.checked = false;
                    if (existingTTSUI) existingTTSUI.remove();
                } else {
                    let script = document.createElement('script');
                    script.src = ttsScriptUrl;
                    script.defer = true;
                    document.body.appendChild(script);
                    localStorage.setItem('ttsActive', 'true');
                    ttsToggleSwitch.checked = true;
                }
            }
            if (ttsToggleSwitch) {
                ttsToggleSwitch.addEventListener('change', toggleTTS);
                if (localStorage.getItem('ttsActive')) toggleTTS();
            }
        });
    </script>
    <?php
});
?>
