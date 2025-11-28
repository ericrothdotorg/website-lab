<?php

add_action('wp_footer', function() {
    if (is_page(array('')) || is_single(array('131123'))) {
    ?>
        <style>
            .world-population-design {text-align: center; color: #990033; font-size: 250%; font-weight: bold;}
        </style>

        <script>
            function worldpopulationcounter() {
                var startdate = new Date();
                updatePopulation(startdate);
            }
            function ChangeValue(number, pv) {
                var numberstring = "";
                var j = 0;
                while (number >= 1) {
                    numberstring = (Math.round(number - 0.5) % 10) + numberstring;
                    number = Math.floor(number / 10);
                    j++;
                    if (number >= 1 && j == 3) {
                        numberstring = "," + numberstring;
                        j = 0;
                    }
                }
                if (pv == 1) {
                    document.getElementById("worldpopulation").innerHTML = numberstring;
                }
            }
            function updatePopulation(startdatum) {
                var now = 5600000000.0;
                var now2 = 5690000000.0;
                var groeipercentage = (now2 - now) / now * 100;
                var groeiperseconde = (now * (groeipercentage / 100)) / 365.0 / 24.0 / 60.0 / 60.0;
                var nu = new Date();
                var schuldstartdatum = new Date(96, 1, 1);
                var secondenoppagina = (nu.getTime() - startdatum.getTime()) / 1000;
                var totaleschuld = (nu.getTime() - schuldstartdatum.getTime()) / 1000 * groeiperseconde + now;
                ChangeValue(totaleschuld, 1);
                setTimeout(function() {
                    updatePopulation(startdatum);
                }, 200);
            }
            window.onload = worldpopulationcounter;
        </script>
    <?php
    }
});
