<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ======================================
// DEFINE OUTPUT PAGES
// ======================================

$counter_pages = [
    '100674' => 'My Competencies',
    '150120' => 'Meine Kompetenzen',
    '179'    => 'ericroth.org',
    '51969'  => 'Site Overview',
    '104510' => 'Site Composition',
    '26874'  => 'Site Updates',
    '87873'  => 'About Me',
    '150455' => 'Über Mich'
];

// ===========================================
// STYLES → Head (critical, prevents CLS)
// ===========================================

add_action('wp_head', function() use ($counter_pages) {
    if (!is_page($counter_pages)) return;
    ?>
    <style>
		:root {--disc-size: 185px;}
        .counter-grid {display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; justify-content: center;}
        .counter-card {margin: 10px auto; border-radius: 50%; display: flex; align-items: center; justify-content: center; width: var(--disc-size); max-width: 100%; aspect-ratio: 1 / 1; transition: transform 1.5s ease-in-out;}
        .counter-card:hover {transform: scale(1.05); background: var(--color-7);}
        .counter-card .counter-body {text-align: center; color: var(--color-3); transform: translateY(-5px);}
        .counter-body img {margin-bottom: 0.25em;}
        .counter-value {color: #990033; font-size: var(--er-fs-xl); font-weight: var(--er-fw-bold);}
        .counter-value, .counter-label {vertical-align: middle;}
        .counter-label {padding-left: 10px; font-size: var(--er-fs-md); font-weight: var(--er-fw-normal);}

        @media (max-width: 1200px) {.counter-grid {grid-template-columns: repeat(auto-fit, minmax(var(--disc-size), 1fr));}}
        @media (max-width: 600px) {:root {--disc-size: 160px;} .counter-grid {grid-template-columns: repeat(2, 1fr);} .counter-value {font-size: var(--er-fs-lg);} .counter-label {font-size: 1rem;}}

        body.dark-mode .counter-card {border: 1px solid var(--color-4); background: var(--color-10);}
        body.dark-mode .counter-card:hover {background: var(--color-6);}
        body.dark-mode .counter-body {color: var(--color-5);}

        /* Page Specific */
        .page-id-179 .counter-card {background: rgba(0, 0, 0, 0.65) !important; border: none !important;}
        .page-id-179 .counter-card:hover {background: var(--color-6) !important;}
        .page-id-179 .counter-body {color: var(--color-5);}

        /* White Icons: Dark Mode + Frontpage */
        body.dark-mode .counter-body img,
        .page-id-179 .counter-body img {filter: brightness(0) invert(1) brightness(0.95) !important;}
    </style>
    <?php
}, 5);

// ===========================================
// SCRIPT → Footer (fine here, no CLS impact)
// ===========================================
						
add_action('wp_footer', function() use ($counter_pages) {
    if (!is_page($counter_pages)) return;
    ?>
	<script>
	function init() {
		var counters = document.querySelectorAll('.counter-value');
		if (!counters.length) return;

		var observer = new IntersectionObserver(function(entries, obs) {
			entries.forEach(function(entry) {
				if (!entry.isIntersecting) return;
				var counter = entry.target;
				obs.unobserve(counter);

				var countTo = parseFloat(counter.getAttribute('data-count'));
				var countNum = 0;
				var step = countTo / (7500 / 16);

				function update() {
					countNum += step;
					if (countNum < countTo) {
						counter.textContent = Math.floor(countNum);
						requestAnimationFrame(update);
					} else {
						counter.textContent = countTo;
					}
				}
				requestAnimationFrame(update);
			});
		}, { threshold: 0.3 });

		counters.forEach(function(c) { observer.observe(c); });
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	</script>
    <?php
});
