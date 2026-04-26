<?php
defined('ABSPATH') || exit;

add_action('wp_footer', function() {
    if (is_page(array(
        '100674', // My Competencies
        '150120', // Meine Kompetenzen
        '157323', // Terra Gate
        '51969',  // Site Overview
        '104510', // Site Composition
        '26874',  // Site Updates
        '87873',  // About Me
        '150455'  // Über Mich
    ))) {
        ?>
        <style>
			.counter-grid {display: flex; justify-content: space-around; flex-flow: row wrap;}
			.counter-card {float: left; width: 18%; margin: 10px 5px; border-radius: 25px; overflow: hidden;}
			.counter-card .counter-body {padding: 15px 0; text-align: center; color: #3A4F66;}
			.counter-value {color: #990033; font-size: 1.75rem; font-weight: bold;}
			.counter-value, .counter-label {vertical-align: middle;}
			.counter-label {padding-left: 10px; font-weight: normal;}
			@media (max-width: 600px) {.counter-card { width: 45%; }}
			@media (min-width: 600px) and (max-width: 992px) {.counter-card { width: 30%; }}
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var a = 0;
                window.addEventListener('scroll', function() {
                    var counterElement = document.getElementById('counter');
                    if (counterElement) {
                        var oTop = counterElement.offsetTop - window.innerHeight;
                        if (a == 0 && window.scrollY > oTop) {
                            document.querySelectorAll('.counter-value').forEach(function(counter) {
                                var countTo = counter.getAttribute('data-count');
                                var countNum = 0;
                                var duration = 7500;
                                var step = countTo / (duration / 16);
                                function updateCounter() {
                                    countNum += step;
                                    if (countNum < countTo) {
                                        counter.textContent = Math.floor(countNum);
                                        requestAnimationFrame(updateCounter);
                                    } else {
                                        counter.textContent = countTo;
                                    }
                                }
                                requestAnimationFrame(updateCounter);
                            });
                            a = 1;
                        }
                    }
                });
            });
        </script>
        <?php 
    }
});
