# Eric Slider

**Contributors:** [ericrothdotorg](https://github.com/ericrothdotorg)

A lightweight, zero-dependency vanilla JavaScript slider / carousel. No jQuery, no bloat — just clean, accessible HTML output with a small footprint.

**Tags:** slider, carousel, javascript, vanilla-js, wordpress, wordpress-plugin, fade, autoplay, responsive, accessible, zero-dependency

---

What makes it genuinely stand out

The WordPress-native approach is quite uncommon. Most sliders are npm / bundler-first and treat WordPress as an afterthought. This one is built the other way around — the PHP enqueue pattern, LiteSpeed Cache filter, FOUC guard in wp_head, and the way visual styles are intentionally kept out of the CSS so theme developers can override freely. That's a coherent, thought-through philosophy that most "just another slider" libraries don't have. Furthermore, it's genuinely zero-dependency in the truest sense — no build step, no npm, no bundler. You drop two files in and write a new EricSlider(). That's increasingly rare as most modern libraries assume a toolchain.

Who would actually reach for it

WordPress developers, especially those working without page builders or with performance-sensitive sites, who are tired of pulling in other sliders (with all their weight and jQuery baggage) for something simple. That's a real and underserved audience. So it's not going to compete with other sliders for mindshare, but as a clean, WordPress-native, no-nonsense option it genuinely fills a gap.

---

## Live Examples

See the Eric Slider in action at

- [ericroth.org/about-me](https://ericroth.org/about-me/)
- [ericroth.org/about-me/my-quotes](https://ericroth.org/about-me/my-quotes/)
- [ericroth.org/personal](https://ericroth.org/personal/)
- [ericroth.org/professional](https://ericroth.org/professional/)
- [ericroth.org/this-site/](https://ericroth.org/this-site/)

---

## Features

- **Zero dependencies** — pure vanilla JS, no jQuery or frameworks required
- **Fade mode** — cross-fade between slides instead of sliding
- **Vertical mode** — scroll slides top-to-bottom
- **Center mode** — peek at adjacent slides with configurable padding
- **Multiple slides** — show and scroll more than one slide at a time
- **Responsive** — breakpoint-based settings via the `responsive` option
- **Adaptive height** — list height animates to match the active slide
- **Autoplay** — with pause on hover and pause on focus support
- **Dots** — clickable navigation dots
- **Controls** — prev / pause / next button bar
- **Accessible** — ARIA roles, `aria-hidden`, keyboard navigation (arrow keys), and `prefers-reduced-motion` support
- **Draggable** — mouse and touch drag support
- **Infinite loop** — clone-based seamless looping
- **FOUC guard** — slides stay hidden until initialised, preventing flash of unstyled content
- **LiteSpeed Cache friendly** — easily exclude slider images from lazy-load optimisers

---

## Files

| File | Description |
|---|---|
| `eric-slider-v2.35.0.js` | Core slider logic |
| `eric-slider-v1.23.0.css` | Structural styles (layout, fade, dots, controls) |

Visual styles (colours, border-radius, spacing) are intentionally kept out of the CSS so you can theme the slider via your own stylesheet or CSS variables.

---

## Installation

### WordPress (recommended)

Enqueue both files from your theme or a mu-plugin:

```php
// NOTE: When used in mu-plugins, add at the top: defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('eric-slider-css', get_template_directory_uri() . '/assets/eric-slider-v1.23.0.css', [], '1.23.0');
    wp_enqueue_script('eric-slider-js', get_template_directory_uri() . '/assets/eric-slider-v2.35.0.js', [], '2.35.0', true);
}, 20);
```

Add a FOUC guard in `wp_head` to keep sliders hidden until JS initialises them:

```php
add_action('wp_head', function () {
    ?>
    <style>
        .my-slideshow { visibility: hidden; }
        .eric-slider-initialized { visibility: visible; }
    </style>
    <?php
}, 6);
```

If you use **LiteSpeed Cache**, exclude slider images from its lazy-load optimiser:

```php
add_filter('litespeed_optimize_html_excluded_selectors', function($excludes) {
    $excludes[] = '.eric-slider img';
    return $excludes;
});
```

### Standalone (no WordPress)

Include both files directly in your HTML:

```html
<link rel="stylesheet" href="eric-slider-v1.23.0.css">
<script src="eric-slider-v2.35.0.js"></script>
```

---

## Basic Usage

Wrap your slides in a container and initialise:

```html
<div class="my-slideshow">
    <div><img src="image1.jpg" alt="Slide 1"></div>
    <div><img src="image2.jpg" alt="Slide 2"></div>
    <div><img src="image3.jpg" alt="Slide 3"></div>
</div>

<script>
    new EricSlider('.my-slideshow', {
        dots: true,
        autoplay: true,
        autoplaySpeed: 3000
    });
</script>
```

---

## Options

| Option | Type | Default | Description |
|---|---|---|---|
| `dots` | boolean | `false` | Show clickable navigation dots |
| `controls` | boolean | `false` | Show prev / pause / next button bar |
| `infinite` | boolean | `true` | Loop seamlessly (clone-based) |
| `autoplay` | boolean | `false` | Auto-advance slides |
| `autoplaySpeed` | number | `3000` | Milliseconds between auto-advances |
| `speed` | number | `400` | Transition duration in ms |
| `slidesToShow` | number | `1` | Number of slides visible at once |
| `slidesToScroll` | number | `1` | Number of slides to advance per step |
| `fade` | boolean | `false` | Cross-fade instead of slide. Forces `slidesToShow: 1` |
| `adaptiveHeight` | boolean | `false` | Animate list height to match active slide |
| `vertical` | boolean | `false` | Slide vertically instead of horizontally |
| `centerMode` | boolean | `false` | Show partial prev/next slides on either side |
| `centerPadding` | string | `'50px'` | Space revealed on each side in center mode |
| `draggable` | boolean | `true` | Enable mouse and touch drag |
| `pauseOnHover` | boolean | `true` | Pause autoplay when cursor is over slider |
| `pauseOnFocus` | boolean | `true` | Pause autoplay when slider has focus |
| `accessibility` | boolean | `true` | Add ARIA attributes and keyboard navigation |
| `label` | string | `'Slideshow'` | `aria-label` value for the slider region |
| `responsive` | array | `null` | Breakpoint-specific settings (see below) |

---

## Examples

### Fade with adaptive height and dots

```js
new EricSlider('.my-slideshow', {
    fade: true,
    adaptiveHeight: true,
    dots: true,
    autoplay: true,
    autoplaySpeed: 2000,
    infinite: true
});
```

### Multiple slides with responsive breakpoints

```js
new EricSlider('.my-slideshow', {
    slidesToShow: 3,
    slidesToScroll: 1,
    controls: true,
    dots: true,
    autoplay: true,
    responsive: [
        { breakpoint: 992, settings: { slidesToShow: 2 } },
        { breakpoint: 768, settings: { slidesToShow: 1 } }
    ]
});
```

### Vertical slider

```js
new EricSlider('.my-slideshow', {
    vertical: true,
    slidesToShow: 3,
    slidesToScroll: 1,
    autoplay: true,
    infinite: true
});
```

### Center mode

```js
new EricSlider('.my-slideshow', {
    centerMode: true,
    centerPadding: '175px',
    controls: true,
    dots: true,
    autoplay: true,
    responsive: [
        { breakpoint: 992, settings: { centerPadding: '75px' } },
        { breakpoint: 768, settings: { centerPadding: '0px' } }
    ]
});
```

---

## Responsive Option

Pass an array of breakpoint objects. Each breakpoint applies its `settings` when the viewport is **narrower** than `breakpoint` (in px). Breakpoints are sorted automatically.

```js
responsive: [
    { breakpoint: 1200, settings: { slidesToShow: 3 } },
    { breakpoint: 768,  settings: { slidesToShow: 2 } },
    { breakpoint: 480,  settings: { slidesToShow: 1 } }
]
```

---

## Public Methods

```js
const slider = new EricSlider('.my-slideshow', { ... });

slider.next();          // Go to next slide
slider.prev();          // Go to previous slide
slider.goTo(2);         // Go to slide index 2 (0-based), animated
slider.goTo(2, false);  // Go to slide index 2, no animation
slider.destroy();       // Tear down the slider and restore original DOM
```

---

## Theming

The CSS file contains only structural styles. Colour and visual overrides belong in your own stylesheet using these selectors:

```css
/* Dot colours */
.eric-slider-dots li button         { color: #333; opacity: 0.5; }
.eric-slider-dots li.eric-slider-active button { color: #333; opacity: 1; }

/* Control button colours */
.eric-slider-ctrl-prev,
.eric-slider-ctrl-pause,
.eric-slider-ctrl-next              { color: #333; }

/* Focus ring (uses CSS variables with fallbacks) */
/* Set --a11y-focus-color and --a11y-focus-width on :root to control globally */
```

---

## Browser Support

All modern browsers. Requires ES5 + `Array.forEach` + `classList` — supported everywhere since IE11. `ResizeObserver` and `IntersectionObserver` are used where available and degrade gracefully.

---

## License

MIT — free to use, modify, and distribute.

---

## Animate.css Integration (optional)

The included PHP / JS example also integrates [Animate.css](https://animate.style) by Dan Eden for scroll-triggered entrance animations. It is entirely optional and independent of the slider itself.

To use it, enqueue `animate.min.css` alongside Eric Slider and add one of the trigger classes (e.g. `daneden-fadeInUp`) to any element. The script uses `IntersectionObserver` to fire the animation when the element scrolls into view.

Animate.css is MIT licensed. See [animate.style](https://animate.style) for the full library and docs.

---

## Shortcode Integration (optional)

The slider is initialised by CSS class, so it works with any method of outputting HTML—hardcoded markup, custom blocks, or shortcode plugins.
The examples in `eric-slider.php` use class names designed to pair with [Display Posts Shortcode](https://displayposts.com/) by Bill Erickson, but that is entirely optional and independent of the slider itself.

Display Posts is GPL-3.0 licensed. See [display-posts-shortcode](https://github.com/billerickson/display-posts-shortcode) for the full library and docs.
