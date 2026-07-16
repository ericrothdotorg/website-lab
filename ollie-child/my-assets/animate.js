/**
 * Animate (in viewport) — Dan Eden / animate.css scroll trigger.
 *
 * Standalone: adds animate.css classes to any .daneden-* element when it
 * scrolls into view. No dependency on Eric Slider. Enqueued by the
 * "Eric Slider & Animate" snippet, gated on the same $needs_animate check.
 */
 
(function() {
    function initAnimateCSS() {
        document.querySelectorAll('.wp-block-pullquote blockquote').forEach(function (bq) {
            bq.classList.add('daneden-zoomIn');
        });
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
        // Use IntersectionObserver for better Performance
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var elem = entry.target;
                        animationClasses.forEach(function(className) {
                            if (elem.classList.contains(className.replace('.', ''))) {
                                var animation = className.replace('.daneden-', '');
                                elem.classList.add('animate__animated', 'animate__' + animation);
                            }
                        });
                        observer.unobserve(elem);
                    }
                });
            }, {
                rootMargin: '0px 0px -10% 0px'
            });
            animationClasses.forEach(function(className) {
                document.querySelectorAll(className).forEach(function(elem) {
                    observer.observe(elem);
                });
            });
        } else {
            // Fallback for older Browsers
            function isScrolledIntoView(elem) {
                var rect = elem.getBoundingClientRect();
                var viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);
                return !(rect.bottom < 0 || rect.top - viewHeight >= 0);
            }
            function addAnimationClass(className) {
                document.querySelectorAll(className).forEach(function(elem) {
                    if (!elem.classList.contains('animate__animated') && isScrolledIntoView(elem)) {
                        var animation = className.replace('.daneden-', '');
                        elem.classList.add('animate__animated', 'animate__' + animation);
                    }
                });
            }
            function onScroll() {
                animationClasses.forEach(addAnimationClass);
            }
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
            onScroll();
        }
    }

    // Defer via requestIdleCallback as it is non-critical
    function scheduleAnimateCSS() {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(initAnimateCSS, { timeout: 500 });
        } else {
            setTimeout(initAnimateCSS, 200);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleAnimateCSS);
    } else {
        scheduleAnimateCSS();
    }
})();
