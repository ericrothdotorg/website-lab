/**
 * EricSlider — Vanilla JS, zero Dependencies
 */

(function (root, factory) {
    'use strict';
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.EricSlider = factory();
    }
}(typeof window !== 'undefined' ? window : this, function () {
    'use strict';

    /* =========================================================
       DEFAULTS
    ========================================================= */
    
    var DEFAULTS = {
        dots:           false,
        infinite:       true,
        autoplay:       false,
        autoplaySpeed:  3000,
        speed:          400,
        slidesToShow:   1,
        slidesToScroll: 1,
        adaptiveHeight: false,
        fade:           false,
        centerMode:     false,
        centerPadding:  '50px',
        vertical:       false,
        draggable:      true,
        pauseOnHover:   true,
        pauseOnFocus:   true,
        accessibility:  true,
        responsive:     null,
        label:          'Slideshow',
        controls:       false
    };

    /* =========================================================
       HELPERS
    ========================================================= */
    
    function extend() {
        var out = {};
        for (var i = 0; i < arguments.length; i++) {
            if (!arguments[i]) continue;
            for (var k in arguments[i]) {
                if (Object.prototype.hasOwnProperty.call(arguments[i], k)) {
                    out[k] = arguments[i][k];
                }
            }
        }
        return out;
    }

    function css(el, props) {
        for (var p in props) {
            if (Object.prototype.hasOwnProperty.call(props, p)) {
                el.style[p] = props[p];
            }
        }
    }

    /* =========================================================
       INSTANCE COUNTER — for unique slide IDs (aria-controls)
    ========================================================= */

    var _instanceCount = 0;

    /* =========================================================
       CONSTRUCTOR
    ========================================================= */
    
    function EricSlider(element, settings) {
        if (!(this instanceof EricSlider)) return new EricSlider(element, settings);
        this.el           = typeof element === 'string' ? document.querySelector(element) : element;
        this._baseOptions = extend(DEFAULTS, settings);
        this.options      = this._resolveResponsive(extend(this._baseOptions));
        this.current      = 0;
        this._animating    = false;
        this._paused       = false;
        this._manualPause  = false; // true when user clicked pause; hover/focus cannot override
        this._timer        = null;
        this._resizeTimer = null;
        this._uid         = 'eric-slider-' + (++_instanceCount); // unique ID prefix for aria-controls
        this._reducedMotion = (typeof window !== 'undefined' && window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches); // Fix: respect prefers-reduced-motion
        this._init();
    }

    /* =========================================================
       RESPONSIVE
    ========================================================= */
    
    EricSlider.prototype._resolveResponsive = function (base) {
        var r = base.responsive;
        if (!r || !r.length) return base;
        var w = window.innerWidth;
        // Sort ascending, then find the smallest breakpoint that is still larger
        // than the current viewport width — that is the active breakpoint.
        var sorted = r.slice().sort(function (a, b) { return a.breakpoint - b.breakpoint; });
        var match  = null;
        for (var i = 0; i < sorted.length; i++) {
            if (w < sorted[i].breakpoint) { match = sorted[i].settings; break; }
        }
        var out = match ? extend(base, match) : base;
        out.responsive = r;
        return out;
    };

    /* =========================================================
       INIT
    ========================================================= */
    
    EricSlider.prototype._init = function () {
        var el   = this.el;
        var opts = this.options;
        var self = this;

        this._rawSlides = Array.prototype.slice.call(el.children);
        this._count     = this._rawSlides.length;
        if (!this._count) return;

        this.current = Math.max(0, Math.min(this.current, this._count - 1));

        el.classList.add('eric-slider');
        if (opts.fade)       el.classList.add('eric-slider-fade');
        if (opts.vertical)   el.classList.add('eric-slider-vertical');
        if (opts.centerMode) el.classList.add('eric-slider-center');

        el.setAttribute('role', 'region');
        el.setAttribute('aria-roledescription', 'slideshow');
        el.setAttribute('aria-label', opts.label);

        this._buildDOM();
        this._setDimensions();
        this._setInitialPosition();

        // FIX 4: for fade+adaptiveHeight, images may not be loaded yet.
        // Set initial height, then re-measure once each image loads.
        if (opts.adaptiveHeight) this._adaptHeight(false);
        if (opts.fade || opts.adaptiveHeight) this._bindImageLoad();

        this._bindEvents();
        if (opts.autoplay) this._startAutoplay();

        this._updateAria();
        if (opts.dots) this._updateDots();

        el.classList.add('eric-slider-initialized');

        // Safety net: remeasure after visibility is restored (handles cached images
        // that never fire load, and ensures measurement is post-paint).
        if (opts.adaptiveHeight) {
            requestAnimationFrame(function () {
                if (opts.fade) self._applyFade(self.current, false);
                self._adaptHeight(false);
            });
        }
    };

    /* =========================================================
       BUILD DOM
    ========================================================= */
    
    EricSlider.prototype._buildDOM = function () {
        var el   = this.el;
        var opts = this.options;
        var self = this;

        this._list  = document.createElement('div');
        this._track = document.createElement('div');
        this._list.className  = 'eric-slider-list';
        this._track.className = 'eric-slider-track';

        // Wrap each original child in a slide div
        this._slideEls = [];
        this._rawSlides.forEach(function (child, i) {
            var slide = document.createElement('div');
            slide.className = 'eric-slider-slide';
            slide.setAttribute('id', self._uid + '-slide-' + i);
            slide.setAttribute('data-index', i);
            slide.setAttribute('aria-hidden', 'true');
            slide.setAttribute('role', 'group');
            slide.setAttribute('aria-roledescription', 'slide');
            child.parentNode.removeChild(child);
            slide.appendChild(child);
            self._track.appendChild(slide);
            self._slideEls.push(slide);
        });

        this._list.appendChild(this._track);
        el.appendChild(this._list);

        // Clones for infinite (non-fade only)
        if (opts.infinite && !opts.fade) this._buildClones();

        // Dots
        if (opts.dots) {
            this._dotsEl = document.createElement('ul');
            this._dotsEl.className = 'eric-slider-dots';
            this._dotsEl.setAttribute('role', 'tablist');
            for (var i = 0; i < this._count; i++) {
                var li  = document.createElement('li');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('role', 'tab');
                btn.setAttribute('aria-label', 'Slide ' + (i + 1));
                btn.setAttribute('aria-controls', self._uid + '-slide-' + i);
                btn.setAttribute('aria-selected', i === self.current ? 'true' : 'false');
                btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><circle cx="6" cy="6" r="6"/></svg>';
                li.appendChild(btn);
                this._dotsEl.appendChild(li);
            }
            el.appendChild(this._dotsEl);
        }

        // Controls (prev / pause / next)
        if (opts.controls) {
            this._controlsEl = document.createElement('div');
            this._controlsEl.className = 'eric-slider-controls';
            this._controlsEl.setAttribute('role', 'group');
            this._controlsEl.setAttribute('aria-label', 'Slideshow controls');

            this._ctrlPrev = document.createElement('button');
            this._ctrlPrev.type = 'button';
            this._ctrlPrev.className = 'eric-slider-ctrl-prev';
            this._ctrlPrev.setAttribute('aria-label', 'Previous slide');
            this._ctrlPrev.innerHTML  = '<svg width="10" height="12" viewBox="0 0 10 12" fill="currentColor" aria-hidden="true"><polygon points="10,0 0,6 10,12"/></svg>';

            this._ctrlPause = document.createElement('button');
            this._ctrlPause.type = 'button';
            this._ctrlPause.className = 'eric-slider-ctrl-pause';
            this._ctrlPause.setAttribute('aria-label', 'Pause slideshow');
            this._ctrlPause.innerHTML = '<svg width="10" height="12" viewBox="0 0 10 12" fill="currentColor" aria-hidden="true"><rect x="0" y="0" width="3.5" height="12"/><rect x="6.5" y="0" width="3.5" height="12"/></svg>';

            this._ctrlNext = document.createElement('button');
            this._ctrlNext.type = 'button';
            this._ctrlNext.className = 'eric-slider-ctrl-next';
            this._ctrlNext.setAttribute('aria-label', 'Next slide');
            this._ctrlNext.innerHTML = '<svg width="10" height="12" viewBox="0 0 10 12" fill="currentColor" aria-hidden="true"><polygon points="0,0 10,6 0,12"/></svg>';

            this._controlsEl.appendChild(this._ctrlPrev);
            this._controlsEl.appendChild(this._ctrlPause);
            this._controlsEl.appendChild(this._ctrlNext);

            if (opts.centerMode && el.parentNode) {
                // Wrap el in a dedicated div so controls can live outside el.
                // overflow: hidden on .eric-slider-center clips position: absolute; top: -40px
                // inside el. The wrapper is purpose-built and never shared with other sliders.
                this._centerWrap = document.createElement('div');
                this._centerWrap.className = 'eric-slider-center-wrap';
                el.parentNode.insertBefore(this._centerWrap, el);
                this._centerWrap.appendChild(this._controlsEl);
                this._centerWrap.appendChild(el);
            } else {
                el.insertBefore(this._controlsEl, this._list);
            }
        }
    };

    /* =========================================================
       CLONES
    ========================================================= */
    
    EricSlider.prototype._buildClones = function () {
        var opts  = this.options;
        var n     = opts.slidesToShow;
        var total = this._count;

        // Remove existing clones
        var old = this._track.querySelectorAll('.eric-slider-cloned');
        Array.prototype.forEach.call(old, function (c) { c.parentNode.removeChild(c); });

        // Prepend clones of last n slides IN REVERSE so track order is correct.
        // i=n-1 first → clone of (total-1), inserted at front
        // i=0   last  → clone of (total-n), inserted at front
        // Result: front of track = [...clone(total-n), ..., clone(total-1)]
        for (var i = n - 1; i >= 0; i--) {
            var srcIdx = (total - n + i + total) % total;
            var clone  = this._slideEls[srcIdx].cloneNode(true);
            clone.classList.add('eric-slider-cloned');
            clone.setAttribute('aria-hidden', 'true');
            clone.removeAttribute('data-index');
            this._track.insertBefore(clone, this._track.firstChild);
        }

        // Append clones of first n slides in forward order
        for (var j = 0; j < n; j++) {
            var clone2 = this._slideEls[j % total].cloneNode(true);
            clone2.classList.add('eric-slider-cloned');
            clone2.setAttribute('aria-hidden', 'true');
            clone2.removeAttribute('data-index');
            this._track.appendChild(clone2);
        }
    };

    /* =========================================================
       DIMENSIONS
    ========================================================= */
    
    EricSlider.prototype._setDimensions = function () {
        var opts = this.options;

        // Use root element width — reliable regardless of negative margins
        // that PHP CSS applies to .eric-slider-list for gutters.
        var lw = this.el.offsetWidth;
        this._listW = lw;
        this._listH = this._list.offsetHeight;

        if (opts.fade) {
            this._slideW = lw;
            return;
        }

        if (opts.vertical) {
            this._slideH = this._slideEls[0] ? (this._slideEls[0].offsetHeight || 120) : 120;
            var allV = this._track.children;
            css(this._track, { width: lw + 'px', height: (allV.length * this._slideH) + 'px' });
            Array.prototype.forEach.call(allV, function (s) {
                css(s, { width: lw + 'px', height: this._slideH + 'px' });
            }, this);
            return;
        }

        var slideW;
        if (opts.centerMode) {
            var pad = parseInt(opts.centerPadding, 10) || 0;
            slideW = lw - pad * 2;
            css(this._list, {
                width:    slideW + 'px',
                margin:   '0 ' + pad + 'px',
                overflow: 'visible',
                padding:  '0'
            });
        } else {
            // slideW = full slot width per slide including its CSS margins.
            // Slide element's actual width = slideW minus those margins.
            slideW = Math.floor(lw / opts.slidesToShow);
            css(this._list, { width: lw + 'px' });
        }
        this._slideW = slideW;

        // Read computed margin from first real slide (class is in DOM, so CSS applies).
        var slideMargin = 0;
        if (this._slideEls[0]) {
            var sc = window.getComputedStyle(this._slideEls[0]);
            slideMargin = (parseFloat(sc.marginLeft) || 0) + (parseFloat(sc.marginRight) || 0);
        }
        var innerW = Math.max(1, slideW - slideMargin);

        var allH = this._track.children;
        css(this._track, { width: (slideW * allH.length) + 'px' });
        if (opts.centerMode) {
            Array.prototype.forEach.call(allH, function (s) {
                css(s, { width: slideW + 'px', padding: '0' });
            });
        } else {
            Array.prototype.forEach.call(allH, function (s) {
                css(s, { width: innerW + 'px' });
            });
        }
    };

    /* =========================================================
       INITIAL POSITION (no animation)
    ========================================================= */
    
    EricSlider.prototype._setInitialPosition = function () {
        var opts = this.options;
        if (opts.fade) {
            this._applyFade(this.current, false);
        } else if (opts.vertical) {
            this._positionVertical(false);
        } else {
            this._positionTrack(false);
        }
    };

    /* =========================================================
       POSITION TRACK
    ========================================================= */
    
    EricSlider.prototype._positionTrack = function (animate) {
        if (this.options.fade || this.options.vertical) return;
        var off = this._getOffset(this.current);
        var dur = (animate && !this._reducedMotion) ? this.options.speed + 'ms' : '0ms';
        css(this._track, {
            transition: animate ? ('transform ' + dur + ' ease') : 'none',
            transform:  'translate3d(' + off + 'px,0,0)'
        });
    };
    EricSlider.prototype._getOffset = function (idx) {
        var opts   = this.options;
        var clones = opts.infinite ? opts.slidesToShow : 0;
        return -((idx + clones) * this._slideW);
    };

    EricSlider.prototype._positionVertical = function (animate) {
        var clones = this.options.infinite ? this.options.slidesToShow : 0;
        var off    = -((this.current + clones) * this._slideH);
        var dur    = (animate && !this._reducedMotion) ? this.options.speed + 'ms' : '0ms';
        css(this._track, {
            transition: animate ? ('transform ' + dur + ' ease') : 'none',
            transform:  'translate3d(0,' + off + 'px,0)'
        });
    };

    /* =========================================================
       FADE
    ========================================================= */
    
    EricSlider.prototype._applyFade = function (to, animate) {
        var opts = this.options;
        var dur  = (animate && !this._reducedMotion) ? opts.speed + 'ms' : '0ms';
        var h    = 0;

        this._slideEls.forEach(function (s, i) {
            var active = i === to;
            css(s, {
                transition: animate ? ('opacity ' + dur + ' ease') : 'none',
                opacity:    active ? '1' : '0',
                zIndex:     active ? '1' : '0',
                position:   active ? 'relative' : 'absolute',
                top:        '0',
                left:       '0',
                width:      '100%'
            });
            if (active) {
                void s.offsetHeight; // force reflow so position:relative is painted before measuring
                h = s.offsetHeight || h;
            }
        });

        css(this._track, { position: 'relative' });
        if (h) css(this._track, { height: h + 'px' });
    };

    /* =========================================================
       ADAPTIVE HEIGHT
    ========================================================= */
    
    EricSlider.prototype._adaptHeight = function (animate) {
        var s = this._slideEls[this.current];
        if (!s) return;
        // In fade mode, _applyFade has already set the active slide to position:relative,
        // so offsetHeight is accurate. scrollHeight is unreliable if there's padding/overflow.
        var h = s.offsetHeight;
        if (!h) return; // not measured yet — _bindImageLoad will retry
        var dur = (animate && !this._reducedMotion) ? this.options.speed + 'ms' : '0ms';
        css(this._list, {
            transition: 'height ' + dur + ' ease',
            height:     h + 'px'
        });
    };

    /* =========================================================
       IMAGE LOAD — re-measure height once images arrive
    ========================================================= */

    EricSlider.prototype._bindImageLoad = function () {
        var self = this;
        var imgs = this.el.querySelectorAll('img');

        // Standard load events — for non-lazy images
        Array.prototype.forEach.call(imgs, function (img) {
            if (!img.complete) {
                img.addEventListener('load', function onLoad() {
                    img.removeEventListener('load', onLoad);
                    requestAnimationFrame(function () {
                        if (self.options.fade) self._applyFade(self.current, false);
                        if (self.options.adaptiveHeight) self._adaptHeight(false);
                    });
                });
                img.addEventListener('error', function onErr() {
                    img.removeEventListener('error', onErr);
                });
            }
        });

        // IntersectionObserver — for lazy images whose load events are deferred
        // by the browser or LiteSpeed until the element enters the viewport.
        // Remeasures once on intersection, then disconnects.
        if ('IntersectionObserver' in window && self.options.adaptiveHeight) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    io.disconnect();
                    // Give the browser a moment to decode and paint lazy images
                    setTimeout(function () {
                        requestAnimationFrame(function () {
                            if (self.options.fade) self._applyFade(self.current, false);
                            self._adaptHeight(false);
                        });
                    }, 100);
                });
            }, { threshold: 0.1 });
            io.observe(self.el);
        }
    };

    /* =========================================================
       GO TO
    ========================================================= */
    
    EricSlider.prototype._goToRaw = function (rawIdx, animate) {
        if (this._animating) return;
        if (animate === undefined) animate = true;

        var opts    = this.options;
        var total   = this._count;
        var realIdx = ((rawIdx % total) + total) % total;

        // FADE
        if (opts.fade) {
            this.current = realIdx;
            this._applyFade(realIdx, animate);
            if (opts.adaptiveHeight) this._adaptHeight(animate);
            this._updateAria();
            if (opts.dots) this._updateDots();
            this._afterChange(animate);
            return;
        }

        this._animating = animate;
        this.current    = realIdx;

        var clones = opts.infinite ? opts.slidesToShow : 0;
        var dur    = (animate && !this._reducedMotion) ? opts.speed + 'ms' : '0ms';

        // VERTICAL
        if (opts.vertical) {
            var vOff = -((rawIdx + clones) * this._slideH);
            css(this._track, {
                transition: animate ? ('transform ' + dur + ' ease') : 'none',
                transform:  'translate3d(0,' + vOff + 'px,0)'
            });
            if (opts.adaptiveHeight) this._adaptHeight(animate);
            this._afterChange(animate);
            return;
        }

        // HORIZONTAL
        var hOff = -((rawIdx + clones) * this._slideW);
        css(this._track, {
            transition: animate ? ('transform ' + dur + ' ease') : 'none',
            transform:  'translate3d(' + hOff + 'px,0,0)'
        });
        if (opts.adaptiveHeight) this._adaptHeight(animate);
        this._afterChange(animate);
    };

    // Public goTo: takes real 0-based index
    EricSlider.prototype.goTo = function (idx, animate) {
        if (animate === undefined) animate = true;
        var opts  = this.options;
        var total = this._count;
        if (!opts.infinite) {
            idx = Math.max(0, Math.min(idx, total - opts.slidesToShow));
        }
        this._goToRaw(idx, animate);
    };

    // _afterChange clears _animating reliably via transitionend OR timeout fallback
    EricSlider.prototype._afterChange = function (animated) {
        var self = this;
        var dur  = animated ? this.options.speed : 0;

        // Primary: clear on transitionend so rapid navigation is unblocked as soon
        // as the CSS transition actually finishes (not just after an arbitrary delay).
        if (animated && !this.options.fade) {
            var cleared = false;
            var onTransEnd = function (e) {
                if (e.propertyName !== 'transform') return;
                cleared = true;
                self._track.removeEventListener('transitionend', onTransEnd);
                self._animating = false;
                self._updateAria();
                if (self.options.dots) self._updateDots();
            };
            this._track.addEventListener('transitionend', onTransEnd);
        }

        // Fallback timeout — ensures _animating is always cleared even if
        // transitionend misfires (tab hidden, animation skipped, fade mode).
        setTimeout(function () {
            if (self._animating) {
                self._animating = false;
                self._updateAria();
                if (self.options.dots) self._updateDots();
            }
        }, dur + 50); // +50ms buffer past transition end
    };

    /* =========================================================
       NEXT / PREV
    ========================================================= */
    
    EricSlider.prototype.next = function () {
        this._goToRaw(this.current + this.options.slidesToScroll);
    };
    EricSlider.prototype.prev = function () {
        this._goToRaw(this.current - this.options.slidesToScroll);
    };

    /* =========================================================
       AUTOPLAY
    ========================================================= */
    
    EricSlider.prototype._startAutoplay = function () {
        var self = this;
        this._clearAutoplay();
        this._timer = setInterval(function () {
            if (!self._paused) self.next();
        }, this.options.autoplaySpeed);
    };

    EricSlider.prototype._clearAutoplay = function () {
        if (this._timer) { clearInterval(this._timer); this._timer = null; }
    };

    /* =========================================================
       DOTS
    ========================================================= */
    
    EricSlider.prototype._updateDots = function () {
        if (!this._dotsEl) return;
        Array.prototype.forEach.call(this._dotsEl.children, function (li, i) {
            li.classList.toggle('eric-slider-active', i === this.current);
            li.querySelector('button').setAttribute('aria-selected', i === this.current ? 'true' : 'false');
        }, this);
    };

    /* =========================================================
       ARIA
    ========================================================= */
    
    EricSlider.prototype._updateAria = function () {
        if (!this.options.accessibility) return;
        var opts = this.options;
        var self = this;
        this._slideEls.forEach(function (slide, i) {
            var visible = opts.centerMode
                ? i === self.current
                : (i >= self.current && i < self.current + opts.slidesToShow);
            slide.setAttribute('aria-hidden', visible ? 'false' : 'true');
            slide.style.pointerEvents = visible ? '' : 'none';
            var focusable = slide.querySelectorAll('a, input, button, select, textarea, [tabindex]');
            Array.prototype.forEach.call(focusable, function (f) {
                visible ? f.removeAttribute('tabindex') : f.setAttribute('tabindex', '-1');
            });
        });
    };

    /* =========================================================
       EVENTS
    ========================================================= */
    
    EricSlider.prototype._bindEvents = function () {
        var self = this;
        var opts = this.options;
        var el   = this.el;

        // Controls bar
        if (opts.controls && this._controlsEl) {
            this._ctrlPrev.addEventListener('click', function () { self.prev(); });
            this._ctrlNext.addEventListener('click', function () { self.next(); });
            this._ctrlPause.addEventListener('click', function () {
                if (self._manualPause) {
                    self._manualPause = false;
                    self._paused = false;
                    self._ctrlPause.innerHTML = '<svg width="10" height="12" viewBox="0 0 10 12" fill="currentColor" aria-hidden="true"><rect x="0" y="0" width="3.5" height="12"/><rect x="6.5" y="0" width="3.5" height="12"/></svg>';
                    self._ctrlPause.setAttribute('aria-label', 'Pause slideshow');
                } else {
                    self._manualPause = true;
                    self._paused = true;
                    self._ctrlPause.innerHTML = '<svg width="10" height="12" viewBox="0 0 10 12" fill="currentColor" aria-hidden="true"><polygon points="0,0 10,6 0,12"/></svg>';
                    self._ctrlPause.setAttribute('aria-label', 'Play slideshow');
                }
            });
        }

        // Dots
        if (opts.dots && this._dotsEl) {
            Array.prototype.forEach.call(this._dotsEl.querySelectorAll('button'), function (btn, i) {
                btn.addEventListener('click', function () { self.goTo(i); });
            });
        }

        // Pause on hover / focus
        if (opts.autoplay && opts.pauseOnHover) {
            el.addEventListener('mouseenter', function () { if (!self._manualPause) self._paused = true; });
            el.addEventListener('mouseleave', function () { if (!self._manualPause) self._paused = false; });
        }
        if (opts.autoplay && opts.pauseOnFocus) {
            el.addEventListener('focusin',  function () { if (!self._manualPause) self._paused = true; });
            el.addEventListener('focusout', function () { if (!self._manualPause) self._paused = false; });
        }

        // Snap to real slide after infinite clone animation completes
        if (opts.infinite && !opts.fade) {
            this._track.addEventListener('transitionend', function (e) {
                if (e.propertyName !== 'transform') return;
                var liveOpts = self.options;
                if (liveOpts.vertical) {
                    var realOffV = -((self.current + liveOpts.slidesToShow) * self._slideH);
                    css(self._track, { transition: 'none', transform: 'translate3d(0,' + realOffV + 'px,0)' });
                } else {
                    var clones  = liveOpts.slidesToShow;
                    var realOff = -((self.current + clones) * self._slideW);
                    css(self._track, { transition: 'none', transform: 'translate3d(' + realOff + 'px,0,0)' });
                }
            });
        }

        // Touch / drag
        if (opts.draggable) this._bindTouch();

        // Resize — window resize
        this._onResize = function () {
            clearTimeout(self._resizeTimer);
            self._resizeTimer = setTimeout(function () { self._handleResize(); }, 150);
        };
        window.addEventListener('resize', this._onResize);

        // ResizeObserver — catches container reflows (tabs, accordions, WP blocks, etc.)
        // that change the slider's width without triggering window resize.
        if (typeof ResizeObserver !== 'undefined') {
            var lastW = self.el.offsetWidth;
            self._resizeObserver = new ResizeObserver(function () {
                var w = self.el.offsetWidth;
                if (w === lastW) return; // height-only change, ignore
                lastW = w;
                clearTimeout(self._resizeTimer);
                self._resizeTimer = setTimeout(function () { self._handleResize(); }, 150);
            });
            self._resizeObserver.observe(self.el);
        }

        // Keyboard
        if (opts.accessibility) {
            el.setAttribute('tabindex', '0');
            el.addEventListener('keydown', function (e) {
                var key = e.key || e.keyCode;
                if (key === 'ArrowLeft'  || key === 37) { e.preventDefault(); self.prev(); }
                if (key === 'ArrowRight' || key === 39) { e.preventDefault(); self.next(); }
                if (opts.vertical) {
                    if (key === 'ArrowUp'   || key === 38) { e.preventDefault(); self.prev(); }
                    if (key === 'ArrowDown' || key === 40) { e.preventDefault(); self.next(); }
                }
            });
        }
    };

    /* =========================================================
       TOUCH / DRAG
    ========================================================= */
    
    EricSlider.prototype._bindTouch = function () {
        var self = this;
        var list = this._list;
        var opts = this.options;
        var startX, startY, startOffset, dragging = false;

        function getX(e) { return e.touches ? e.touches[0].clientX : e.clientX; }
        function getY(e) { return e.touches ? e.touches[0].clientY : e.clientY; }

        function onStart(e) {
            if (self._animating) return;
            if (e.touches) e.preventDefault();
            startX       = getX(e);
            startY       = getY(e);
            startOffset  = opts.vertical
                ? -((self.current + opts.slidesToShow) * self._slideH)
                : self._getOffset(self.current);
            dragging     = true;
            css(self._track, { transition: 'none' });
            if (!e.touches) list.style.cursor = 'grabbing';
        }

        function onMove(e) {
            if (!dragging) return;
            var dx = getX(e) - startX;
            var dy = getY(e) - startY;
            // Abort if vertical scroll intent detected (horizontal slider only)
            if (!opts.vertical && Math.abs(dy) > Math.abs(dx) + 5) {
                dragging = false;
                return;
            }
            e.preventDefault();
            if (opts.vertical) {
                css(self._track, { transform: 'translate3d(0,' + (startOffset + dy) + 'px,0)' });
            } else {
                css(self._track, { transform: 'translate3d(' + (startOffset + dx) + 'px,0,0)' });
            }
        }

        function onEnd(e) {
            if (!dragging) return;
            dragging = false;
            if (!e.touches) list.style.cursor = 'grab';
            var endX  = e.changedTouches ? e.changedTouches[0].clientX : (e.clientX !== undefined ? e.clientX : startX);
            var endY  = e.changedTouches ? e.changedTouches[0].clientY : (e.clientY !== undefined ? e.clientY : startY);
            var delta = opts.vertical ? (endY - startY) : (endX - startX);
            var size  = opts.vertical ? (self._slideH * opts.slidesToShow) : self._listW;
            if (Math.abs(delta) > size / 5) {
                delta < 0 ? self.next() : self.prev();
            } else {
                // Snap back to current position
                opts.vertical ? self._positionVertical(true) : self._positionTrack(true);
            }
        }

        list.addEventListener('touchstart', onStart, { passive: false });
        list.addEventListener('touchmove',  onMove,  { passive: false });
        list.addEventListener('touchend',   onEnd);

        list.style.cursor = 'grab';
        list.addEventListener('mousedown', onStart);
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup',   onEnd);

        // Store refs for cleanup in destroy()
        this._touchList    = list;
        this._touchStart   = onStart;
        this._touchMove    = onMove;
        this._touchEnd     = onEnd;
    };

    /* =========================================================
       RESIZE
    ========================================================= */
    
    EricSlider.prototype._handleResize = function () {
        var prevShow    = this.options.slidesToShow;
        var prevPadding = this.options.centerPadding;
        this.options = this._resolveResponsive(extend(this._baseOptions));

        // Rebuild clones if slidesToShow or centerPadding changed
        if (this.options.infinite && !this.options.fade &&
            (this.options.slidesToShow !== prevShow ||
             this.options.centerPadding !== prevPadding)) {
            this._buildClones();
            this.current = 0;
        }

        this._setDimensions();
        this._setInitialPosition(); // must run before _adaptHeight so fade slides are positioned correctly
        if (this.options.adaptiveHeight) this._adaptHeight(false);

        if (this.options.dots) this._updateDots();
        this._updateAria();
    };

    /* =========================================================
       DESTROY
    ========================================================= */
    
    EricSlider.prototype.destroy = function () {
        this._clearAutoplay();
        if (this._onResize) window.removeEventListener('resize', this._onResize);
        if (this._resizeObserver) { this._resizeObserver.disconnect(); this._resizeObserver = null; }

        // Remove touch / mouse drag listeners
        if (this._touchList && this._touchStart) {
            this._touchList.removeEventListener('touchstart', this._touchStart);
            this._touchList.removeEventListener('touchmove',  this._touchMove);
            this._touchList.removeEventListener('touchend',   this._touchEnd);
            this._touchList.removeEventListener('mousedown',  this._touchStart);
            window.removeEventListener('mousemove', this._touchMove);
            window.removeEventListener('mouseup',   this._touchEnd);
        }

        var self = this;
        this._slideEls.forEach(function (slide) {
            var original = slide.firstChild;
            if (original) self.el.appendChild(original);
        });

        if (this._list        && this._list.parentNode)        this._list.parentNode.removeChild(this._list);
        if (this._dotsEl      && this._dotsEl.parentNode)      this._dotsEl.parentNode.removeChild(this._dotsEl);
        if (this._controlsEl  && this._controlsEl.parentNode)  this._controlsEl.parentNode.removeChild(this._controlsEl);

        // Unwrap el from center wrapper if one was created
        if (this._centerWrap && this._centerWrap.parentNode) {
            this._centerWrap.parentNode.insertBefore(this.el, this._centerWrap);
            this._centerWrap.parentNode.removeChild(this._centerWrap);
            this._centerWrap = null;
        }

        this.el.classList.remove(
            'eric-slider', 'eric-slider-initialized', 'eric-slider-fade',
            'eric-slider-vertical', 'eric-slider-center'
        );
        this.el.removeAttribute('tabindex');
        this.el.removeAttribute('role');
        this.el.removeAttribute('aria-roledescription');
        this.el.removeAttribute('aria-label');
    };

    return EricSlider;
}));