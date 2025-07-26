<?php 
add_action('wp_footer', function() {
    if (is_page(array('8977', '100674', '55867')) || is_single(array(''))) {
        ?>
        <style>
            /* Summaries ALL */
            .card-counter {padding: 15px 0; text-align: center; color: #192a3d;}
            .text-box.number-counter {padding: 0; margin-bottom: 0;}
            .number-counter-all {display: flex; justify-content: space-around; flex-flow: row wrap;}
            .counter-value-all {color: #990033; font-weight: bold;}
            /* Summaries COMPOSITION */
            .column-counter-composition {float: left; width: 18%; margin: 10px 5px;}
            @media (max-width: 600px) {.column-counter-composition {width: 45%;}}
            @media (min-width: 600px) and (max-width: 992px) {.column-counter-composition {width: 30%;}}
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var a = 0;
                window.addEventListener('scroll', function() {
                    var counterElement = document.getElementById('counter');
                    if (counterElement) {
                        var oTop = counterElement.offsetTop - window.innerHeight;
                        if (a == 0 && window.scrollY > oTop) {
                            document.querySelectorAll('.counter-value-all').forEach(function(counter) {
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
?>
