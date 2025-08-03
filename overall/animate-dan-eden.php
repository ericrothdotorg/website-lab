<?php
add_action('wp_footer', function () {
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Element is in Viewport
            function isScrolledIntoView(elem) {
                var rect = elem.getBoundingClientRect();
                var viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);
                return !(rect.bottom < 0 || rect.top - viewHeight >= 0);
            }
            // Add Animate.css Classes when in View
            function addAnimationClass(className) {
                var elements = document.querySelectorAll(className);
                elements.forEach(function(elem) {
                    if (!elem.classList.contains('animate__animated') && isScrolledIntoView(elem)) {
                        var animation = className.replace('.daneden-', '');
                        elem.classList.add('animate__animated', 'animate__' + animation);
                    }
                });
            }
            // Scroll-triggered Animations
            function onScroll() {
                var animationClasses = [
                    '.daneden-fadeIn',
                    '.daneden-fadeInUp',
                    '.daneden-fadeInDown',
                    '.daneden-zoomIn',
                    '.daneden-zoomOut',
                    '.daneden-slideInLeft',
                    '.daneden-slideInRight',
                    '.daneden-slideInUp',
                    '.daneden-slideInDown'
                ];
                animationClasses.forEach(addAnimationClass);
            }
            // Throttle Scroll Handler
            function throttle(fn, limit) {
                let waiting = false;
                return function() {
                    if (!waiting) {
                        fn();
                        waiting = true;
                        setTimeout(() => waiting = false, limit);
                    }
                };
            }
            window.addEventListener('scroll', throttle(onScroll, 150));
            onScroll(); // Initial Check
        });
    </script>
    <?php
});
?>
