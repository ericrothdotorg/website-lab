<?php
// NOTE: If you ever move this into mu-plugins, add at the top: defined('ABSPATH') || exit;

// =========================
// SHORTCODE LIVE PREVIEW  (iframe-based, backend-only, no CSS mirror)
// =========================
//
// WHAT THIS DOES (in short)
//   1. Shortcode preview panel — select a shortcode block, see it rendered live
//      in the sidebar, styled like the frontend, with a dark-mode toggle.
//   2. Editor canvas styling — real blocks (e.g. .paragraph-highlight) look right
//      while editing, not just on the frontend.
//   3. Pulls all styling live from your real sources (theme.json, child
//      style.css, Ollie's CSS, your CSS snippets, plus Display Posts / slider
//      layout) — nothing hand-copied; cached for speed.
//   4. Editor-only niceties: reveals JS sliders as static rows, shrinks
//      titles/excerpts in the panel, sizes the preview with no stray scrollbar.
//
//   Backend/editor only. Toggling this snippet off or deleting it changes nothing
//   for visitors and leaves the block editor working normally.
//
// PURPOSE
//   A developer/editor tool that makes the block editor show what content looks
//   like on the FRONTEND. It does two things, both editor-only — it changes
//   nothing on the live site and needs no changes to any frontend CSS:
//     A) Shortcode preview — renders a [shortcode] in a live preview panel in the
//        block sidebar (Inspector).
//     B) Canvas styling — styles REAL blocks in the editor content area (e.g. a
//        paragraph with .paragraph-highlight) so the canvas matches the front end.
//
// HOW THE STYLES ARE ASSEMBLED (no rule is ever hand-copied / "mirrored")
//   Every layer is pulled from its real source at request/render time:
//     - parent  style.css .................... Ollie base/reset (handle 'ollie')
//     - child   style.css .................... your tokens (--color-*, --er-*)
//     - merged  theme.json ................... preset tokens (--wp--preset--*),
//                                              via wp_get_global_stylesheet()
//     - Ollie   assets/styles/*.css .......... per-core-block styles
//     - Code Snippets "site-css" snippets .... your component CSS
//                                              (.paragraph-highlight, .er-*, …),
//                                              read live from the snippets table
//     - designated PHP snippets' <style> ..... layout CSS that lives inside PHP
//                                              snippets ("Display Posts - Bill
//                                              Erickson" grid, "Eric Slider &
//                                              Animate") — extracted as TEXT,
//                                              never executed (see IDs filter)
//     - shortcode's own inline <style> ....... already inside the rendered HTML
//     - editor-only overrides ................ small tweaks that apply only in the
//                                              editor (reveal JS sliders)
//     - preview-iframe-only overrides ........ tweaks that apply ONLY inside the
//                                              narrow preview panel (flatten
//                                              grids into a card row, shrink
//                                              titles/excerpts). Never applied to
//                                              the full-width editor canvas.
//
//   Preview (A) loads these into an <iframe>, so body.dark-mode rules work by
//   toggling class="dark-mode" on the iframe body. Canvas (B) injects the same
//   CSS scoped under .editor-styles-wrapper so it styles editor content without
//   leaking into the wp-admin UI. Editing any real source updates both live —
//   nothing here to keep in sync.
//
// FILE MAP
//   HELPERS      — read site-css snippets; extract <style> from PHP snippets;
//                  editor-only overrides (shared) + preview-iframe-only overrides
//   SECTION 1    — REST endpoint: render shortcode + build the style payload
//   SECTION 2    — editor CSS for the preview panel chrome (sidebar, buttons)
//   SECTION 3    — editor JavaScript: the Inspector preview panel (React)
//   SECTION 4    — editor canvas styling for real blocks (+ CSS scoper)
//
// RUN SCOPE: keep this snippet set to "Run Everywhere". The REST endpoint
// (rest_api_init) must register on wp-json/* requests, which are neither admin
// nor frontend. The other hooks (enqueue_block_editor_assets, enqueue_block_
// assets) are admin/editor-only, so nothing here runs on the public frontend.

// ===========================================================================
// HELPERS — style sources and editor-only overrides
// ===========================================================================

// Read the CSS of ALL active Code Snippets "site-css" snippets straight from the
// Code Snippets table. This is deliberately not tied to any snippet ID: whatever
// CSS snippets you have active (Ollie Companion CSS today, plus any you add
// later) are reflected in the preview automatically, with nothing to configure.
//
// Why read from the DB: Code Snippets (v3.9.x) prints site-css inline on the
// frontend and exposes no stable static file URL to enqueue, so the table is the
// single reliable source. Prefix-safe; returns '' if Code Snippets isn't present,
// so the preview degrades gracefully instead of erroring.
function shortcode_live_preview_get_snippet_css() {
    global $wpdb;

    $table = $wpdb->prefix . 'snippets';
    // Bail quietly if Code Snippets isn't installed with the expected table.
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return '';
    }

    // All active CSS snippets, in the same priority order Code Snippets applies.
    $rows = $wpdb->get_col(
        "SELECT code FROM {$table}
         WHERE scope = 'site-css' AND active = 1
         ORDER BY priority ASC, id ASC"
    );

    if ( empty( $rows ) ) {
        return '';
    }

    return implode( "\n", $rows );
}

// Some frontend CSS lives inside PHP snippets that print an inline <style> block
// on wp_head/wp_footer (e.g. the Display Posts layout in one snippet, the slider
// layout in another). We CANNOT safely fire wp_head in a REST request — other
// snippets on those hooks send nocache headers, write to the DB, or inject
// scripts, which would corrupt the JSON response. Instead we read those specific
// snippets' `code` as TEXT from the Code Snippets table and extract only the CSS
// between <style>…</style>. Nothing is executed; if a snippet's markup changes,
// the match simply yields nothing and the preview degrades gracefully.
//
// The IDs below are the PHP snippets whose inline <style> holds shortcode layout
// CSS. Currently: 14 = "Display Posts - Bill Erickson", 49 = "Eric Slider &
// Animate". Filterable so you can add/remove without editing this function. If
// these snippets' IDs ever change (e.g. after a site rebuild), update this list.
function shortcode_live_preview_php_style_snippet_ids() {
    return apply_filters( 'shortcode_live_preview_php_style_snippet_ids', [ 14, 49 ] );
}

function shortcode_live_preview_get_php_snippet_styles() {
    global $wpdb;

    $ids = array_map( 'intval', array_filter( shortcode_live_preview_php_style_snippet_ids() ) );
    if ( empty( $ids ) ) {
        return '';
    }

    $table = $wpdb->prefix . 'snippets';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return '';
    }

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT code FROM {$table} WHERE id IN ({$placeholders}) AND active = 1",
            ...$ids
        )
    );

    if ( empty( $rows ) ) {
        return '';
    }

    // Extract the CSS inside every <style>…</style> block. Text only, never run.
    $css = '';
    foreach ( $rows as $code ) {
        if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $code, $m ) ) {
            foreach ( $m[1] as $block ) {
                $css .= "\n" . $block;
            }
        }
    }

    return $css;
}

// 1. REST API ENDPOINT — Renders the shortcode server-side and returns the
//    component CSS alongside it. Protected: only logged-in users with edit_posts.
function shortcode_live_preview_rest_endpoint() {
    register_rest_route( 'custom/v1', '/shortcode-preview', [
        'methods'             => 'POST',
        'callback'            => 'shortcode_live_preview_render',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'args' => [
            'shortcode' => [
                'required'          => true,
                'type'              => 'string',
                // A shortcode is plain text, NOT HTML. wp_kses_post is an HTML
                // sanitizer and can mangle/strip shortcode attributes (e.g.
                // post_parent__in="48419, 123"), producing an empty render.
                // sanitize_textarea_field keeps the shortcode intact (allows the
                // spaces/quotes/commas shortcodes use) while stripping tags.
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'shortcode_live_preview_rest_endpoint' );

function shortcode_live_preview_render( $request ) {
    $shortcode = trim( $request->get_param( 'shortcode' ) );
    if ( empty( $shortcode ) || strpos( $shortcode, '[' ) === false ) {
        return new WP_REST_Response( [ 'html' => '', 'links' => [], 'inlineCss' => '' ], 200 );
    }

    // Render inside an output buffer. Some shortcodes (e.g. Display Posts with
    // post_parent__in) run secondary queries that can emit PHP notices/warnings.
    // If any such output reached the REST response body it would appear BEFORE the
    // JSON and make the response unparseable — which shows as a silent blank box.
    // Buffering captures the real return value and discards any stray output.
    ob_start();
    $returned = do_shortcode( $shortcode );
    $echoed   = ob_get_clean();

    // Prefer the returned string (correct shortcodes return, not echo). If a
    // shortcode echoed instead, fall back to the captured output. Ignore pure
    // whitespace so an empty result is reported as empty (triggering the "no
    // output" message client-side) rather than as a blank iframe.
    $html = ( is_string( $returned ) && trim( $returned ) !== '' ) ? $returned : $echoed;

    return new WP_REST_Response( shortcode_live_preview_payload( $html ), 200 );
}

// Editor-only style overrides. These apply ONLY inside the preview iframe and the
// editor canvas — never the frontend. Two things:
//   1. Reveal sliders. The slider CSS sets .slideshow-multiple-items* to
//      visibility:hidden until its JavaScript adds .eric-slider-initialized. That
//      JS doesn't run in the editor, so without this the slider preview is blank.
//      We force those containers visible so they render as a plain row of items
//      (like the non-slider grid) — a static preview, not a working carousel.
//   2. Smaller Display Posts titles. The frontend title sizes are tuned for full
//      width; in the narrow preview panel they look oversized. Shrink them here.
// Returns raw CSS; callers scope it (canvas) or inject it as-is (iframe).
function shortcode_live_preview_editor_overrides() {
    return '
        /* 1. Reveal + lay out JS-driven sliders in the editor.
              The slider CSS hides every .slideshow-* container until its
              JavaScript adds .eric-slider-initialized, and that JS never runs in
              the editor — so without this a slider previews as a blank box.
              We match ANY class containing "slideshow-", which covers every
              variant (single-item, single-item-no-dots, multiple-items-*,
              vertical, center-mode, quotes) and any future one, with no list to
              maintain.

              Layout: slide spacing normally comes from the carousel JS.
              Revealed statically, ALL variants can hold several slides at once
              (a "single-item" slider only shows one at a time once the JS runs),
              so every slider gets the SAME wrapping row with the SAME gap. The
              children use a min-width basis and are allowed to grow, so a wide
              container shows several cards per row and a narrow one (the sidebar
              panel) shows a single column — with identical spacing either way.
              The result is a static row/column of cards, not a working carousel. */
        [class*="slideshow-"] {
            visibility: visible !important;
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: wrap !important;
            align-items: stretch !important;
            gap: 1rem !important;
        }
        /* Every direct child (a slide) behaves the same: never shrink below its
           basis, grow to fill the row, and reset any margin so the flex gap is
           the single source of spacing. */
        [class*="slideshow-"] > * {
            flex: 1 1 140px !important;
            min-width: 0 !important;
            margin: 0 !important;
        }
    ';
}

// PREVIEW-IFRAME-ONLY overrides. These are NOT applied to the editor canvas.
//
// WHY: the preview iframe is only as wide as the Inspector panel (~280px, or
// 600px when the sidebar is expanded). CSS media queries inside an iframe
// resolve against the IFRAME's width, so the desktop breakpoints the grids rely
// on never match and each grid falls back to its BASE column count:
//   - .display-posts-listing.grid ... base `repeat(2, 1fr)` (see the "Display
//     Posts - Bill Erickson" snippet; #four-columns / #six-columns only widen at
//     min-width:600px / 992px)
//   - .er-cpt-grid ................. base `repeat(4, 1fr)` (see "Ollie Companion
//     CSS"; it only narrows at max-width:1024px / 600px)
// Either way the cards are crushed into columns far too narrow to read — the
// grids "do not look fine" while the sliders do.
//
// FIX: give the grids the exact same treatment the sliders already get above —
// a wrapping flex row whose children have a min-width basis and may grow. In the
// narrow panel that yields a single vertical column of cards (same as sliders);
// with the sidebar expanded to 600px it yields several per row. Identical gap in
// both cases, and no breakpoint depends on the container width.
//
// The canvas is deliberately excluded: it is full width, its media queries DO
// match, and the real grids already render correctly there. Flattening them in
// the canvas would break something that currently works.
//
// The Display Posts title/excerpt shrink also lives here (not in the shared
// overrides): it exists purely because the frontend sizes look oversized in the
// narrow preview panel — a reason that does not apply to the full-width canvas.
function shortcode_live_preview_iframe_overrides() {
    return '
        /* Flatten grids into the same static card row/column the sliders use. */
        .display-posts-listing.grid,
        .er-cpt-grid {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: wrap !important;
            align-items: stretch !important;
            gap: 1rem !important;
        }
        .display-posts-listing.grid > *,
        .er-cpt-grid > * {
            flex: 1 1 140px !important;
            min-width: 0 !important;
            margin: 0 !important;
        }

        /* Smaller Display Posts titles in the preview panel only. */
        .display-posts-listing .title { font-size: var(--er-fs-sm) !important; }

        /* Smaller Display Posts excerpts in the preview panel only (match titles). */
        .display-posts-listing .excerpt { font-size: var(--er-fs-xs) !important; }
    ';
}

// Assemble the inline CSS the preview and canvas both need: theme.json tokens +
// your site-css snippets + the layout CSS extracted from designated PHP snippets
// + editor-only overrides. This is identical on every render and involves a few
// DB reads and a theme.json compile, so cache the result in a short transient.
//
// The cache is invalidated automatically: the version key includes filemtime of
// the child style.css and a hash of the snippet inputs, so editing any source
// produces a new key and a fresh build. Pass $with_child_css=true for the canvas
// (which inlines child style.css); the preview iframe <link>s it instead.
function shortcode_live_preview_build_inline_css( $with_child_css = false, $for_iframe = false ) {
    $child_css_path = get_stylesheet_directory() . '/style.css';
    $child_mtime    = file_exists( $child_css_path ) ? filemtime( $child_css_path ) : 0;

    $snippet_css   = shortcode_live_preview_get_snippet_css();
    $php_style_css = shortcode_live_preview_get_php_snippet_styles();

    // Version key: any change to the snippet CSS or the child stylesheet busts it.
    $version = md5( $child_mtime . '|' . $with_child_css . '|' . $for_iframe . '|' . $snippet_css . '|' . $php_style_css );
    $cache_key = 'sc_live_preview_css_' . $version;

    $cached = get_transient( $cache_key );
    if ( is_string( $cached ) ) {
        return $cached;
    }

    $theme_json_css = function_exists( 'wp_get_global_stylesheet' ) ? wp_get_global_stylesheet() : '';
    $child_css      = ( $with_child_css && file_exists( $child_css_path ) ) ? file_get_contents( $child_css_path ) : '';

    $css = $theme_json_css . "\n" . $child_css . "\n" . $snippet_css . "\n" . $php_style_css
        . "\n" . shortcode_live_preview_editor_overrides()
        . ( $for_iframe ? "\n" . shortcode_live_preview_iframe_overrides() : '' );

    // Short TTL: fresh enough for editing, still spares repeated rebuilds in a
    // working session. The version key already guarantees correctness on change.
    set_transient( $cache_key, $css, 5 * MINUTE_IN_SECONDS );

    return $css;
}

// Build the preview payload. Every style layer is resolved HERE, on the server,
// from its real source — so the preview matches the frontend regardless of what
// the block-editor canvas iframe happens to load (this is deliberately NOT
// dependent on cloning editor DOM nodes, which do not include the frontend-only
// child stylesheet or the Code Snippets site-css).
//
//   html       rendered shortcode; self-contained shortcodes already carry their <style>
//   links[]    real stylesheet URLs the iframe <link>s, exactly as the frontend would:
//                - child style.css (tokens: --color-*, --er-*)
//                - parent style.css (Ollie base), loaded before the child
//   inlineCss  CSS text injected into the iframe <style>, in cascade order:
//                - theme.json compiled CSS (variables/presets/styles => --wp--preset--*)
//                - your Code Snippets site-css (e.g. "Ollie Companion CSS":
//                  .paragraph-highlight, .er-*), plus Display Posts / slider CSS
function shortcode_live_preview_payload( $html ) {
    $links = [];

    // Parent then child style.css, mirroring the child theme's own enqueue order
    // (child depends on parent, so child wins). get_template_directory_uri() is
    // the PARENT; get_stylesheet_directory_uri() is the CHILD.
    // VERIFIED against Ollie's functions.php: the parent enqueues its style.css on
    // the frontend (handle 'ollie'), and it contains real base/reset rules.
    $parent_css = get_template_directory_uri() . '/style.css';
    $child_css  = get_stylesheet_directory_uri() . '/style.css';
    if ( file_exists( get_template_directory() . '/style.css' ) ) {
        $links[] = $parent_css;
    }
    if ( file_exists( get_stylesheet_directory() . '/style.css' ) ) {
        // Cache-bust with filemtime so token edits show immediately in the preview.
        $links[] = add_query_arg( 'ver', filemtime( get_stylesheet_directory() . '/style.css' ), $child_css );
    }

    // Ollie's per-block CSS. VERIFIED against the parent's functions.php: Ollie
    // enqueues assets/styles/core-*.css conditionally via wp_enqueue_block_style()
    // (loaded only when a given core block is present on the page). The editor
    // preview can't know which blocks a shortcode will emit, so we include the set
    // and let the browser apply whatever matches — same files, real source, editor
    // only. Guarded by glob so it's a no-op if the folder/theme changes.
    $block_style_dir = get_template_directory() . '/assets/styles';
    $block_style_uri = get_template_directory_uri() . '/assets/styles';
    if ( is_dir( $block_style_dir ) ) {
        foreach ( (array) glob( $block_style_dir . '/*.css' ) as $file ) {
            $links[] = $block_style_uri . '/' . basename( $file );
        }
    }

    // theme.json tokens + site-css snippets + PHP-snippet layout CSS + editor
    // overrides, assembled and cached once. Child style.css is <link>ed above
    // (not inlined here), so pass false.
    $inline_css = shortcode_live_preview_build_inline_css( false, true );

    return [
        'html'      => $html,
        'links'     => array_values( array_unique( $links ) ),
        'inlineCss' => $inline_css,
    ];
}

// 2. EDITOR CSS — "enqueue_block_editor_assets"
//    Never fires on the frontend, only loads in block editor.
//    This now styles ONLY the preview panel chrome (the iframe frame, the
//    expand/collapse sidebar behaviour, the action buttons). All the mirrored
//    site CSS that used to live here is GONE — the iframe pulls the real styles.
function shortcode_live_preview_editor_css() {

    $css = '
        /* --- Sidebar expand / collapse (unique to this snippet) --- */
        .interface-interface-skeleton__sidebar,
        .interface-complementary-area__fill,
        .interface-complementary-area.editor-sidebar {transition: width 0.25s ease !important;}
        .sc-sidebar-expanded .interface-interface-skeleton__sidebar {width: 600px !important; min-width: 600px !important; flex-shrink: 0 !important;}
        .sc-sidebar-expanded .interface-interface-skeleton__content {flex: 1 1 auto !important; min-width: 0 !important; overflow: hidden !important;}

        /* --- Preview panel chrome --- */
        .sc-inspector-preview-frame {width: 100%; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff; display: block; max-height: 70vh;}
        .sc-inspector-actions {display: flex; justify-content: space-between; align-items: center; margin-top: 8px;}
        .sc-inspector-btn {font-size: 11px; color: #1e73be; background: none; border: none; cursor: pointer; padding: 0;}
        .sc-inspector-btn:hover {color: #c53030; text-decoration: underline;}
        .sc-inspector-toggle-row {display: flex; align-items: center; gap: 6px; margin: 8px 0 0;}
        .sc-inspector-toggle-row label {font-size: 11px; color: #555; cursor: pointer;}
    ';

    wp_register_style( 'shortcode-live-preview-editor-css', false );
    wp_enqueue_style( 'shortcode-live-preview-editor-css' );
    wp_add_inline_style( 'shortcode-live-preview-editor-css', $css );
}
add_action( 'enqueue_block_editor_assets', 'shortcode_live_preview_editor_css' );

// 3. EDITOR JAVASCRIPT — "enqueue_block_editor_assets"
//    Never fires on the frontend, only loads in block editor.
function shortcode_live_preview_editor_assets() {
    $rest_url = esc_url( rest_url( 'custom/v1/shortcode-preview' ) );
    $nonce    = wp_create_nonce( 'wp_rest' );
    $js = <<<JS

(function(wp) {
    var addFilter         = wp.hooks.addFilter;
    var createElement     = wp.element.createElement;
    var useState          = wp.element.useState;
    var useEffect         = wp.element.useEffect;
    var useRef            = wp.element.useRef;
    var Fragment          = wp.element.Fragment;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody         = wp.components.PanelBody;
    var Spinner           = wp.components.Spinner;
    var REST_URL = '{$rest_url}';
    var NONCE    = '{$nonce}';

    function setSidebarExpanded(expanded) {
        var skeleton = document.querySelector('.interface-interface-skeleton');
        if (!skeleton) return;
        var innerEls = document.querySelectorAll(
            '.interface-complementary-area__fill, .interface-complementary-area.editor-sidebar'
        );
        if (expanded) {
            skeleton.classList.add('sc-sidebar-expanded');
            innerEls.forEach(function(el) {
                el.setAttribute('data-sc-orig-width', el.style.width || '');
                el.style.width = '600px';
            });
        } else {
            skeleton.classList.remove('sc-sidebar-expanded');
            innerEls.forEach(function(el) {
                el.style.width = el.getAttribute('data-sc-orig-width') || '';
                el.removeAttribute('data-sc-orig-width');
            });
        }
    }

    // Build the iframe <head> from the REST payload: real frontend stylesheet
    // URLs (child/parent style.css) + inline CSS (theme.json tokens + Code
    // Snippets site-css). This is authoritative and frontend-faithful; it does
    // NOT clone editor DOM nodes, which wouldn't include the frontend-only child
    // stylesheet or the site-css and are moving into the canvas iframe anyway.
    function buildHead(links, inlineCss) {
        var out = [];
        (links || []).forEach(function(href) {
            out.push('<link rel="stylesheet" href="' + href + '">');
        });
        if (inlineCss) out.push('<style>' + inlineCss + '</style>');
        return out.join('\\n');
    }

    // Write the assembled document into the iframe and auto-size it to content.
    function writeIframe(iframe, bodyHtml, links, inlineCss, dark) {
        if (!iframe) return;
        var bodyClass = dark ? 'dark-mode' : '';
        var docHtml =
            '<!DOCTYPE html><html><head><meta charset="utf-8">' +
            buildHead(links, inlineCss) +
            '<style>html,body{margin:0;padding:12px;box-sizing:border-box;background:transparent;}html{overflow-y:hidden;}</style>' +
            '</head><body class="' + bodyClass + '">' +
            bodyHtml +
            '</body></html>';

        // Auto-height: fit the iframe to its rendered content. A scrollbar should
        // appear ONLY when the content is genuinely taller than the 70vh cap.
        var resize = function() {
            try {
                var d = iframe.contentDocument;
                if (!d) return;
                var h = Math.max(
                    d.documentElement ? d.documentElement.scrollHeight : 0,
                    d.body ? d.body.scrollHeight : 0
                );
                if (!h) return;
                // The panel CSS caps the iframe at 70vh. Compute that cap here so we
                // can decide whether scrolling is actually needed.
                var cap = Math.floor(window.innerHeight * 0.7);
                if (h > cap) {
                    // Content exceeds the cap: let the iframe scroll internally.
                    iframe.style.height = cap + 'px';
                    if (d.documentElement) d.documentElement.style.overflowY = 'auto';
                } else {
                    // Fits: size exactly to content and forbid any scrollbar.
                    iframe.style.height = h + 'px';
                    if (d.documentElement) d.documentElement.style.overflowY = 'hidden';
                }
            } catch (e) {}
        };

        // Use srcdoc rather than document.open/write/close. srcdoc hands the
        // iframe a complete document the browser parses in one pass — reliable in
        // the non-iframe (legacy) editor and not subject to open/write races.
        // Measure only after the iframe's own load event (styles/markup applied),
        // then again shortly after for late reflow, and once more per image.
        iframe.onload = function() {
            resize();
            setTimeout(resize, 60);
            setTimeout(resize, 300);
            try {
                var d = iframe.contentDocument;
                if (d) {
                    d.querySelectorAll('img').forEach(function(img) {
                        if (!img.complete) img.addEventListener('load', resize, { once: true });
                    });
                }
            } catch (e) {}
        };
        iframe.setAttribute('srcdoc', docHtml);
    }

    function withShortcodePreview(BlockEdit) {
        return function(props) {
            if (props.name !== 'core/shortcode') {
                return createElement(BlockEdit, props);
            }
            var shortcode   = props.attributes.text || '';
            var _state      = useState({ html: '', links: [], inlineCss: '', loading: false, hasError: false, fetched: '', didFetch: false });
            var state       = _state[0];
            var setState    = _state[1];
            var _expanded   = useState(false);
            var isExpanded  = _expanded[0];
            var setExpanded = _expanded[1];
            var _dark       = useState(false);
            var isDark      = _dark[0];
            var setDark     = _dark[1];
            var iframeRef   = useRef(null);

            // If React reuses this component instance across different shortcode
            // blocks, reset our per-block state when the block's clientId changes.
            // Without this, 'fetched' from the previous block can persist and match
            // the new block's shortcode, so the auto-fetch guard skips and the
            // preview never fires (an empty box with no spinner).
            var lastClientId = useRef(props.clientId);
            if (lastClientId.current !== props.clientId) {
                lastClientId.current = props.clientId;
                if (state.fetched !== '' || state.html !== '' || state.didFetch) {
                    setState({ html: '', links: [], inlineCss: '', loading: false, hasError: false, fetched: '', didFetch: false });
                }
            }

            useEffect(function() {
                setSidebarExpanded(isExpanded);
                return function() { setSidebarExpanded(false); };
            }, [isExpanded]);

            // Keep the latest content in a ref so the iframe's mount callback can
            // write immediately, even on the first render (when a plain ref would
            // still be null and the write-effect would no-op — the cause of the
            // "only renders after toggling dark mode" bug).
            var contentRef = useRef({ html: '', links: [], inlineCss: '', dark: false });
            contentRef.current = { html: state.html, links: state.links, inlineCss: state.inlineCss, dark: isDark };

            // Callback ref: fires when the <iframe> element mounts/unmounts. On
            // mount, write current content straight away.
            var setIframe = function(el) {
                iframeRef.current = el;
                if (el && contentRef.current.html) {
                    writeIframe(el, contentRef.current.html, contentRef.current.links, contentRef.current.inlineCss, contentRef.current.dark);
                }
            };

            // (Re)write on subsequent changes to content or the dark-mode toggle,
            // once the iframe already exists.
            useEffect(function() {
                if (iframeRef.current && state.html) {
                    writeIframe(iframeRef.current, state.html, state.links, state.inlineCss, isDark);
                }
            }, [state.html, state.inlineCss, isDark]);

            function fetchPreview(sc) {
                if (!sc || sc.indexOf('[') === -1) {
                    setState({ html: '', links: [], inlineCss: '', loading: false, hasError: false, fetched: '' });
                    return;
                }
                setState(function(prev) { return { html: prev.html, links: prev.links, inlineCss: prev.inlineCss, loading: true, hasError: false, fetched: prev.fetched }; });
                fetch(REST_URL, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                    body:    JSON.stringify({ shortcode: sc }),
                })
                // Read the raw body as text first, then parse. This way a non-JSON
                // response (a PHP notice, an HTML error page, an empty body) never
                // fails silently — we can show what actually came back.
                .then(function(res) {
                    return res.text().then(function(text) {
                        return { ok: res.ok, status: res.status, text: text };
                    });
                })
                .then(function(r) {
                    var data;
                    try {
                        data = JSON.parse(r.text);
                    } catch (e) {
                        // Body wasn't valid JSON — surface a short diagnostic.
                        var snippet = (r.text || '').replace(/\s+/g, ' ').trim().slice(0, 300);
                        setState({ html: '', links: [], inlineCss: '', loading: false, hasError: true, errorMsg: 'Server returned a non-JSON response (HTTP ' + r.status + '): ' + (snippet || '(empty body)'), fetched: sc, didFetch: true });
                        return;
                    }
                    if (!r.ok) {
                        setState({ html: '', links: [], inlineCss: '', loading: false, hasError: true, errorMsg: 'Request failed (HTTP ' + r.status + ').', fetched: sc, didFetch: true });
                        return;
                    }
                    setState({ html: data.html || '', links: data.links || [], inlineCss: data.inlineCss || '', loading: false, hasError: false, fetched: sc, didFetch: true });
                })
                .catch(function(err) {
                    setState({ html: '', links: [], inlineCss: '', loading: false, hasError: true, errorMsg: 'Network error: ' + (err && err.message ? err.message : 'request did not complete'), fetched: sc, didFetch: true });
                });
            }
            // Keep a ref to the latest fetchPreview so the debounced effect always
            // calls the current closure (not a stale one captured on first mount).
            // This is what makes multiple shortcode blocks each fetch correctly.
            var fetchRef = useRef(fetchPreview);
            fetchRef.current = fetchPreview;

            // Auto-fetch when the shortcode text changes, debounced. The timer lives
            // inside the effect and is cleared on change/unmount, so switching
            // between blocks can't leave a stale or cancelled request.
            useEffect(function() {
                if (!shortcode || shortcode === state.fetched) {
                    return;
                }
                var t = setTimeout(function() {
                    fetchRef.current(shortcode);
                }, 500);
                return function() { clearTimeout(t); };
            }, [shortcode, state.fetched]);

            var statusStyle = { color: '#808080', fontSize: '13px', fontStyle: 'italic', margin: 0 };
            var errorStyle  = { color: '#c53030', fontSize: '13px', margin: 0 };

            var previewBody;
            if (!shortcode || shortcode.indexOf('[') === -1) {
                previewBody = createElement('p', { style: statusStyle }, 'Enter a shortcode to see a preview.');
            } else if (state.hasError) {
                previewBody = createElement('p', { style: errorStyle },
                    '\u26a0 ' + (state.errorMsg || 'Could not render shortcode.')
                );
            } else if (state.loading && !state.html) {
                previewBody = createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', padding: '4px 0' } },
                    createElement(Spinner, null),
                    createElement('span', { style: statusStyle }, 'Rendering\u2026')
                );
            } else if (state.didFetch && !state.loading && !state.html) {
                // Fetch completed but the shortcode produced no HTML. This is not a
                // preview bug — the shortcode itself returned nothing (e.g. its
                // query matched no posts). Say so explicitly instead of a blank box.
                previewBody = createElement('p', { style: statusStyle },
                    'Shortcode ran but returned no output \u2014 e.g. its query matched no posts (check attributes like post_parent__in / id).'
                );
            } else {
                previewBody = createElement(Fragment, null,
                    state.loading ? createElement('p', { style: statusStyle }, 'Updating\u2026') : null,
                    createElement('iframe', {
                        className: 'sc-inspector-preview-frame',
                        ref: setIframe,
                        title: 'Shortcode preview'
                    }),
                    createElement('div', { className: 'sc-inspector-toggle-row' },
                        createElement('input', {
                            type: 'checkbox',
                            id: 'sc-dark-toggle',
                            checked: isDark,
                            onChange: function() { setDark(function(p) { return !p; }); }
                        }),
                        createElement('label', { htmlFor: 'sc-dark-toggle' }, 'Preview dark mode')
                    ),
                    createElement('div', { className: 'sc-inspector-actions' },
                        createElement('button', { className: 'sc-inspector-btn', onClick: function() { setExpanded(function(prev) { return !prev; }); } },
                            isExpanded ? '\u21d4 Collapse sidebar' : '\u21d4 Expand sidebar'
                        ),
                        createElement('button', { className: 'sc-inspector-btn', onClick: function() { fetchPreview(shortcode); } }, '\u21ba Refresh')
                    )
                );
            }

            return createElement(Fragment, null,
                createElement(BlockEdit, props),
                createElement(InspectorControls, null,
                    createElement(PanelBody, { title: '\u26a1 Shortcode Preview', initialOpen: true },
                        previewBody
                    )
                )
            );
        };
    }
    addFilter('editor.BlockEdit', 'custom/shortcode-live-preview', withShortcodePreview);

}(window.wp));
JS;
    wp_register_script(
        'shortcode-live-preview-editor',
        '',
        [ 'wp-hooks', 'wp-element', 'wp-blocks', 'wp-block-editor', 'wp-components' ],
        null,
        true
    );
    wp_add_inline_script( 'shortcode-live-preview-editor', $js );
    wp_enqueue_script( 'shortcode-live-preview-editor' );
}
add_action( 'enqueue_block_editor_assets', 'shortcode_live_preview_editor_assets' );

// 4. EDITOR CANVAS STYLING — style REAL blocks (e.g. .paragraph-highlight) in the
//    editor content area, so a page looks in the editor like it does on the front.
//
//    This is separate from the shortcode preview above. It targets the editor
//    canvas is div.editor-styles-wrapper in the same document. Per WordPress
//    guidance, styles reach that area when scoped under .editor-styles-wrapper.
//    It uses the same assembled CSS as the preview (child style.css + theme.json
//    tokens + your Code Snippets CSS + layout CSS), scoped so it can't leak into
//    the wp-admin UI.
//
//    Editor-only (guarded by is_admin()); the live frontend is untouched.
function shortcode_live_preview_canvas_styles() {
    if ( ! is_admin() ) {
        return;
    }

    // Same assembled CSS the preview uses, but with child style.css inlined (the
    // canvas has no <link> for it). Assembled + cached by the shared helper.
    $raw = shortcode_live_preview_build_inline_css( true );
    if ( trim( $raw ) === '' ) {
        return;
    }

    // Scope every rule under .editor-styles-wrapper so it applies to editor content
    // (and satisfies the non-iframe editor's requirement) without touching admin UI.
    // We prefix selectors rather than wrapping, so body.dark-mode etc. still resolve.
    // Cache the scoped output too, keyed by the raw CSS, since scoping isn't free.
    $scope_key = 'sc_live_preview_scoped_' . md5( $raw );
    $scoped    = get_transient( $scope_key );
    if ( ! is_string( $scoped ) ) {
        $scoped = shortcode_live_preview_scope_css( $raw, '.editor-styles-wrapper' );
        set_transient( $scope_key, $scoped, 5 * MINUTE_IN_SECONDS );
    }

    wp_register_style( 'shortcode-live-preview-canvas', false );
    wp_enqueue_style( 'shortcode-live-preview-canvas' );
    wp_add_inline_style( 'shortcode-live-preview-canvas', $scoped );
}
add_action( 'enqueue_block_assets', 'shortcode_live_preview_canvas_styles' );

// Prefix every top-level selector in $css with $scope, so the rules only apply
// inside the editor content wrapper. Handles @media/@supports by recursing into
// their blocks; leaves :root as-is (custom properties must stay global to resolve).
function shortcode_live_preview_scope_css( $css, $scope ) {
    // Strip comments first (keeps the regex simple and output smaller).
    $css = preg_replace( '#/\*.*?\*/#s', '', $css );

    $out    = '';
    $len    = strlen( $css );
    $i      = 0;
    $buffer = '';

    while ( $i < $len ) {
        $ch = $css[ $i ];

        if ( $ch === '@' ) {
            // At-rule. Read its prelude up to '{' or ';'.
            $j = $i;
            while ( $j < $len && $css[ $j ] !== '{' && $css[ $j ] !== ';' ) {
                $j++;
            }
            $prelude = substr( $css, $i, $j - $i );

            if ( $j < $len && $css[ $j ] === '{' ) {
                // Block at-rule. Find its matching close brace.
                $depth = 1;
                $k     = $j + 1;
                while ( $k < $len && $depth > 0 ) {
                    if ( $css[ $k ] === '{' ) {
                        $depth++;
                    } elseif ( $css[ $k ] === '}' ) {
                        $depth--;
                    }
                    $k++;
                }
                $inner = substr( $css, $j + 1, $k - $j - 2 );

                if ( preg_match( '/^\s*@(media|supports|container)/i', $prelude ) ) {
                    // Recurse: scope the rules inside conditional groups.
                    $out .= $prelude . '{' . shortcode_live_preview_scope_css( $inner, $scope ) . '}';
                } else {
                    // Other at-rules (@keyframes, @font-face, @page): leave intact.
                    $out .= $prelude . '{' . $inner . '}';
                }
                $i = $k;
            } else {
                // Statement at-rule (@import, @charset): leave as-is.
                $out .= $prelude . ';';
                $i    = $j + 1;
            }
            continue;
        }

        if ( $ch === '{' ) {
            // $buffer holds one or more comma-separated selectors. Scope each.
            $selectors = explode( ',', trim( $buffer ) );
            $scoped    = array();
            foreach ( $selectors as $sel ) {
                $sel = trim( $sel );
                if ( $sel === '' ) {
                    continue;
                }
                // :root defines custom properties — must stay global to resolve.
                if ( stripos( $sel, ':root' ) === 0 ) {
                    $scoped[] = $sel;
                    continue;
                }
                // If the selector starts with body / html, splice the scope AFTER
                // it so body.dark-mode .x becomes body.dark-mode .editor-styles-wrapper .x.
                if ( preg_match( '/^(html|body)([.#:\s]|$)/i', $sel ) ) {
                    // Insert scope right after the leading body/html token chain.
                    $scoped[] = preg_replace(
                        '/^((?:html|body)[^\s]*)(\s+|$)/i',
                        '$1 ' . $scope . ' ',
                        $sel,
                        1
                    );
                } else {
                    $scoped[] = $scope . ' ' . $sel;
                }
            }
            // Copy the declaration block verbatim.
            $depth = 1;
            $k     = $i + 1;
            while ( $k < $len && $depth > 0 ) {
                if ( $css[ $k ] === '{' ) {
                    $depth++;
                } elseif ( $css[ $k ] === '}' ) {
                    $depth--;
                }
                $k++;
            }
            $decls = substr( $css, $i + 1, $k - $i - 2 );
            $out  .= implode( ',', $scoped ) . '{' . $decls . '}';
            $buffer = '';
            $i      = $k;
            continue;
        }

        $buffer .= $ch;
        $i++;
    }

    return $out;
}
