<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* ============================================================================
 * EDITOR LIVE PREVIEWS — editor only, frontend untouched. Scope: everywhere
 * (the REST route must register on wp-json requests).
 *   A  Shortcode preview: Inspector panel renders a [shortcode] in an iframe.
 *   B  Canvas styling: same CSS, scoped under .editor-styles-wrapper.
 * CSS comes live from its real sources, nothing copied: theme.json, parent +
 * child style.css, Ollie assets/styles, site-css snippets, and the CSS inside
 * the PHP snippets listed below.
 * ========================================================================== */

/* HELPERS ------------------------------------------------------------------ */

// All active site-css snippets, read from the Code Snippets table (no file URL exists).
function shortcode_live_preview_get_snippet_css() {
    global $wpdb;

    $table = $wpdb->prefix . 'snippets';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return '';
    }

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

// PHP snippets whose CSS the editor needs. Read as text, never run, so their
// scope does not matter. Add the ID of every new PHP snippet with content CSS.
function shortcode_live_preview_php_style_snippet_ids() {
    return apply_filters( 'shortcode_live_preview_php_style_snippet_ids', [ 14, 49, 75, 23, 36, 37, 42, 70 ] );
}

// CSS from <style>…</style> or <<<'CSS' blocks. A match without "{" is PHP
// glue ('<style>' . fn() . '</style>'), not CSS: skip it.
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
            "SELECT code FROM {$table} WHERE id IN ({$placeholders}) AND active = 1 ORDER BY priority ASC, id ASC",
            ...$ids
        )
    );

    if ( empty( $rows ) ) {
        return '';
    }

    $css = '';
    foreach ( $rows as $code ) {
        $blocks = [];
        if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $code, $m ) ) {
            $blocks = array_merge( $blocks, $m[1] );
        }
        if ( preg_match_all( "#<<<\s*['\"]?CSS['\"]?\R(.*?)\R[ \t]*CSS\b#s", $code, $m ) ) {
            $blocks = array_merge( $blocks, $m[1] );
        }
        foreach ( $blocks as $block ) {
            if ( strpos( $block, '{' ) === false ) {
                continue;
            }
            $css .= "\n" . $block;
        }
    }

    return $css;
}

// 1  REST ENDPOINT — renders the shortcode + returns its CSS. edit_posts only.
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
                // Not wp_kses_post: it mangles shortcode attributes.
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

    // Buffer: stray notices would precede the JSON and break the response.
    ob_start();
    $returned = do_shortcode( $shortcode );
    $echoed   = ob_get_clean();

    // Prefer the returned string; fall back to echoed output.
    $html = ( is_string( $returned ) && trim( $returned ) !== '' ) ? $returned : $echoed;

    return new WP_REST_Response( shortcode_live_preview_payload( $html ), 200 );
}

// Editor overrides (preview + canvas): sliders are hidden until their JS runs,
// which it never does in the editor — show them as a static row.
function shortcode_live_preview_editor_overrides() {
    return '
        [class*="slideshow-"] {
            visibility: visible !important;
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: wrap !important;
            align-items: stretch !important;
            gap: 1rem !important;
        }
        [class*="slideshow-"] > * {
            flex: 1 1 140px !important;
            min-width: 0 !important;
            margin: 0 !important;
        }
    ';
}

// Preview-iframe-only overrides: media queries resolve against the narrow iframe,
// so grids collapse to unreadable columns — flatten them like the sliders. Not in
// the canvas, where the real grids already work.
function shortcode_live_preview_iframe_overrides() {
    return '
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

        /* Smaller Display Posts title/excerpt in the narrow panel. */
        .display-posts-listing .title { font-size: var(--er-fs-sm) !important; }

        .display-posts-listing .excerpt { font-size: var(--er-fs-xs) !important; }
    ';
}

// Assembled CSS for preview and canvas, cached; the key changes with any source.
// $with_child_css: canvas inlines child style.css, the iframe <link>s it.
function shortcode_live_preview_build_inline_css( $with_child_css = false, $for_iframe = false ) {
    $child_css_path = get_stylesheet_directory() . '/style.css';
    $child_mtime    = file_exists( $child_css_path ) ? filemtime( $child_css_path ) : 0;

    $snippet_css   = shortcode_live_preview_get_snippet_css();
    $php_style_css = shortcode_live_preview_get_php_snippet_styles();

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

    set_transient( $cache_key, $css, 5 * MINUTE_IN_SECONDS );

    return $css;
}

// Preview payload: stylesheet links + inline CSS, resolved server-side.
function shortcode_live_preview_payload( $html ) {
    $links = [];

    // Parent before child, as on the frontend.
    $parent_css = get_template_directory_uri() . '/style.css';
    $child_css  = get_stylesheet_directory_uri() . '/style.css';
    if ( file_exists( get_template_directory() . '/style.css' ) ) {
        $links[] = $parent_css;
    }
    if ( file_exists( get_stylesheet_directory() . '/style.css' ) ) {
        $links[] = add_query_arg( 'ver', filemtime( get_stylesheet_directory() . '/style.css' ), $child_css );
    }

    // Ollie per-block CSS: the frontend loads it per block; the preview can't
    // know which blocks a shortcode emits, so load all.
    $block_style_dir = get_template_directory() . '/assets/styles';
    $block_style_uri = get_template_directory_uri() . '/assets/styles';
    if ( is_dir( $block_style_dir ) ) {
        foreach ( (array) glob( $block_style_dir . '/*.css' ) as $file ) {
            $links[] = $block_style_uri . '/' . basename( $file );
        }
    }

    $inline_css = shortcode_live_preview_build_inline_css( false, true );

    return [
        'html'      => $html,
        'links'     => array_values( array_unique( $links ) ),
        'inlineCss' => $inline_css,
    ];
}

// 2  EDITOR CSS — preview panel chrome only.
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

// 3  EDITOR JS — Inspector preview panel.
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

        // Fit to content; scroll only above the 70vh cap.
        var resize = function() {
            try {
                var d = iframe.contentDocument;
                if (!d) return;
                var h = Math.max(
                    d.documentElement ? d.documentElement.scrollHeight : 0,
                    d.body ? d.body.scrollHeight : 0
                );
                if (!h) return;
                var cap = Math.floor(window.innerHeight * 0.7);
                if (h > cap) {
                    iframe.style.height = cap + 'px';
                    if (d.documentElement) d.documentElement.style.overflowY = 'auto';
                } else {
                    iframe.style.height = h + 'px';
                    if (d.documentElement) d.documentElement.style.overflowY = 'hidden';
                }
            } catch (e) {}
        };

        // srcdoc, not document.write: no open/write races. Re-measure after late reflow and images.
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

            // Reset state when React reuses this instance for another block.
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

            // Ref so the iframe's mount callback can write on first render.
            var contentRef = useRef({ html: '', links: [], inlineCss: '', dark: false });
            contentRef.current = { html: state.html, links: state.links, inlineCss: state.inlineCss, dark: isDark };

            var setIframe = function(el) {
                iframeRef.current = el;
                if (el && contentRef.current.html) {
                    writeIframe(el, contentRef.current.html, contentRef.current.links, contentRef.current.inlineCss, contentRef.current.dark);
                }
            };

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
                // Read as text first so a non-JSON response shows a diagnostic.
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
            // Latest closure for the debounced effect.
            var fetchRef = useRef(fetchPreview);
            fetchRef.current = fetchPreview;

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
                // The shortcode itself returned nothing.
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

// 4  CANVAS STYLING — real blocks in the editor content area.
function shortcode_live_preview_canvas_styles() {
    if ( ! is_admin() ) {
        return;
    }

    $raw = shortcode_live_preview_build_inline_css( true );
    if ( trim( $raw ) === '' ) {
        return;
    }

    // Scoped under .editor-styles-wrapper so nothing leaks into wp-admin; cached.
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

// Prefix every selector with $scope; recurses into @media/@supports/@container.
function shortcode_live_preview_scope_css( $css, $scope ) {
    $css = preg_replace( '#/\*.*?\*/#s', '', $css );

    $out    = '';
    $len    = strlen( $css );
    $i      = 0;
    $buffer = '';

    while ( $i < $len ) {
        $ch = $css[ $i ];

        if ( $ch === '@' ) {
            $j = $i;
            while ( $j < $len && $css[ $j ] !== '{' && $css[ $j ] !== ';' ) {
                $j++;
            }
            $prelude = substr( $css, $i, $j - $i );

            if ( $j < $len && $css[ $j ] === '{' ) {
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
                    $out .= $prelude . '{' . shortcode_live_preview_scope_css( $inner, $scope ) . '}';
                } else {
                    $out .= $prelude . '{' . $inner . '}';
                }
                $i = $k;
            } else {
                $out .= $prelude . ';';
                $i    = $j + 1;
            }
            continue;
        }

        if ( $ch === '{' ) {
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
                // body/html first: body.dark-mode .x → body.dark-mode {scope} .x
                // it so body.dark-mode .x becomes body.dark-mode .editor-styles-wrapper .x.
                if ( preg_match( '/^(html|body)([.#:\s]|$)/i', $sel ) ) {
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
