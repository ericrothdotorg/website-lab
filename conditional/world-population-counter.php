<?php
defined('ABSPATH') || exit;

add_action('wp_footer', function() {
    if (is_page(array('')) || is_single(array('131123'))) { ?>
        <style>
            .world-population-design {text-align: center; color: #990033; font-size: 2.5rem; font-weight: bold;}
        </style>
        <script>
            (() => {
                // Base population estimate and growth rate
				const BASE_POP  = 8_300_678_395; // UN 2024 mid-year 2026 estimate
				const BASE_DATE = new Date('2026-07-01T00:00:00Z');
				const GROWTH_PER_SEC = 2.19; // ~69M net / year ÷ 31,557,600 sec
                function updatePopulation() {
                    const elapsed = (Date.now() - BASE_DATE.getTime()) / 1000;
                    const population = Math.round(BASE_POP + elapsed * GROWTH_PER_SEC);
                    document.getElementById('worldpopulation').textContent =
                        population.toLocaleString();
                    requestAnimationFrame(updatePopulation);
                }
                updatePopulation();
            })();
        </script>
    <?php }
});
