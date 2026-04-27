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
		<!-- NOTE: These styles are mirrored in the EDITOR ENHANCEMENTS snippet for editor display. Update both when changing. -->
        <style>
			:root {--disc-size: 200px;}
			.counter-grid {display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; justify-content: center;}
			.counter-card {margin: 10px auto; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: var(--disc-size); height: var(--disc-size); max-width: 100%; transition: transform 1.5s ease-in-out;}
			.counter-card:hover {transform: scale(1.05);}
			.counter-card .counter-body {text-align: center; color: #3A4F66;}
			.counter-value {color: #990033; font-size: 2rem; font-weight: bold;}
			.counter-value, .counter-label {vertical-align: middle;}
			.counter-label {padding-left: 10px; font-size: 1.25rem; font-weight: normal;}
			@media (max-width: 1200px) {.counter-grid {grid-template-columns: repeat(auto-fit, minmax(var(--disc-size), 1fr));}}
        </style>
        <script>
			document.addEventListener('DOMContentLoaded', function() {
				var a = 0;
				function checkCounter() {
					var counterElement = document.getElementById('counter');
					if (counterElement) {
						var oTop = counterElement.offsetTop - window.innerHeight;
						if (a == 0 && window.scrollY >= oTop) {
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
				}
				window.addEventListener('scroll', checkCounter);
				checkCounter();
			});
        </script>
        <?php 
    }
});
