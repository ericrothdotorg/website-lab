<?php
add_action('wp_footer', function () {
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if element is in viewport
            function isScrolledIntoView(elem) {
                var rect = elem.getBoundingClientRect();
                var viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);
                return !(rect.bottom < 0 || rect.top - viewHeight >= 0);
            }
            // Add Animate.css classes when in view
            function addAnimationClass(className) {
                var elements = document.querySelectorAll(className);
                elements.forEach(function(elem) {
                    if (!elem.classList.contains('animate__animated') && isScrolledIntoView(elem)) {
                        var animation = className.replace('.daneden-', '');
                        elem.classList.add('animate__animated', 'animate__' + animation);
                    }
                });
            }
            // Scroll-triggered animations
            function onScroll() {
                addAnimationClass('.daneden-fadeIn');
                addAnimationClass('.daneden-fadeInUp');
                addAnimationClass('.daneden-fadeInDown');
                addAnimationClass('.daneden-zoomIn');
                addAnimationClass('.daneden-zoomOut');
                addAnimationClass('.daneden-slideInLeft');
                addAnimationClass('.daneden-slideInRight');
                addAnimationClass('.daneden-slideInUp');
                addAnimationClass('.daneden-slideInDown');
            }
            window.addEventListener('scroll', onScroll);
            onScroll(); // Initial check
        });
    </script>
    <?php
});
?>
