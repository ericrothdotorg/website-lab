<?php 
add_action('wp_footer', function () {
    ?>
    <style>
        .video-placeholder {position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer;}
        .video-placeholder img {width: 100%; height: 100%; object-fit: cover;}
        .play-button-youtube,
        .play-button-vimeo {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 65px; height: 65px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            transition: transform 0.25s ease;
            z-index: 2;
        }
        .play-button-youtube:hover,
        .play-button-vimeo:hover {transform: translate(-50%, -50%) scale(1.25);}
        .play-button-youtube {background-image: url('https://ericroth.org/wp-content/uploads/2025/01/YT-Play-Button.png');}
        .play-button-vimeo {background-image: url('https://ericroth.org/wp-content/uploads/2025/05/Vimeo-Play-Button.png');}
        .video-placeholder:hover::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.25);
            z-index: 1;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function createPlaceholder(iframe, videoId, platform) {
                var placeholder = document.createElement('div');
                placeholder.className = 'video-placeholder';
                placeholder.setAttribute('data-video-id', videoId);
                var img = document.createElement('img');
                img.src = platform === 'youtube' 
                    ? 'https://img.youtube.com/vi/' + videoId + '/hqdefault.jpg'
                    : 'https://vumbnail.com/' + videoId + '.jpg';
                img.alt = 'Video Placeholder';
                var playButton = document.createElement('div');
                playButton.className = platform === 'youtube' ? 'play-button-youtube' : 'play-button-vimeo';
                placeholder.appendChild(img);
                placeholder.appendChild(playButton);
                iframe.parentNode.insertBefore(placeholder, iframe);
                iframe.style.display = 'none';
                placeholder.addEventListener('click', function() {
                    var newIframe = document.createElement('iframe');
                    newIframe.setAttribute('src', platform === 'youtube' 
                        ? 'https://www.youtube.com/embed/' + videoId + '?autoplay=1' 
                        : 'https://player.vimeo.com/video/' + videoId + '?autoplay=1');
                    newIframe.setAttribute('frameborder', '0');
                    newIframe.setAttribute('allow', platform === 'youtube' 
                        ? 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
                        : 'autoplay; fullscreen; picture-in-picture');
                    newIframe.setAttribute('allowfullscreen', 'true');
                    this.parentNode.replaceChild(newIframe, this);
                });
            }
            var youtubeIframes = document.querySelectorAll('iframe[src*="youtube.com/embed"]');
            youtubeIframes.forEach(iframe => {
                var videoId = iframe.src.split('/embed/')[1].split('?')[0];
                createPlaceholder(iframe, videoId, 'youtube');
            });
            var vimeoIframes = document.querySelectorAll('iframe[src*="player.vimeo.com/video"]');
            vimeoIframes.forEach(iframe => {
                var videoId = iframe.src.split('/video/')[1].split('?')[0];
                createPlaceholder(iframe, videoId, 'vimeo');
            });
        });
    </script>
    <?php
});
