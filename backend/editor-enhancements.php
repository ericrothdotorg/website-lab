<?php
defined('ABSPATH') || exit;

// NOTE: The SHORTCODE LIVE PREVIEW snippet MUST remain set to "Run Everywhere".
// The REST endpoint (rest_api_init) needs to fire on wp-json/* requests which run outside both admin and frontend contexts.
// Switching to "Only run in Admin Area" would silently break the shortcode preview in the block editor.

// =========================
// SHORTCODE LIVE PREVIEW
// =========================

// 1. REST API ENDPOINT — Renders the shortcode server-side
//    Protected: Only logged-in users with edit_posts can call it

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
                'sanitize_callback' => 'wp_kses_post',
            ],
        ],
    ] );
}
add_action( 'rest_api_init', 'shortcode_live_preview_rest_endpoint' );

function shortcode_live_preview_render( $request ) {
    $shortcode = trim( $request->get_param( 'shortcode' ) );
    if ( empty( $shortcode ) || strpos( $shortcode, '[' ) === false ) {
        return new WP_REST_Response( [ 'html' => '' ], 200 );
    }
    $html = do_shortcode( $shortcode );
    return new WP_REST_Response( [ 'html' => $html ], 200 );
}

// 2. EDITOR-ONLY CSS — enqueue_block_editor_assets never
//    fires on the frontend, so this is 100% admin-only

function shortcode_live_preview_editor_css() {
	
    $css = '
        /* --- MIRRORED FROM: Slick Slider (wp_head). Keep in sync when changing that snippet --- */

		.slideshow-single-item, .slideshow-single-item-no-dots, .slideshow-multiple-items, .slideshow-multiple-items-3, .slideshow-multiple-items-4, .slideshow-multiple-items-vertical, .slideshow-multiple-items-center-mode {visibility: visible !important;}
        .slideshow-multiple-items-3.display-posts-listing {display: flex !important; flex-wrap: wrap !important; gap: 25px !important;}
        .slideshow-multiple-items-3.display-posts-listing .listing-item {flex: 0 0 calc(33.333% - 17px) !important; max-width: calc(33.333% - 17px) !important; box-sizing: border-box !important;}
        .slideshow-multiple-items-4.display-posts-listing {display: flex !important; flex-wrap: wrap !important; gap: 25px !important;}
        .slideshow-multiple-items-4.display-posts-listing .listing-item {flex: 0 0 calc(25% - 19px) !important; max-width: calc(25% - 19px) !important; box-sizing: border-box !important;}
        .slideshow-multiple-items-vertical.display-posts-listing {display: flex !important; flex-direction: column !important; gap: 20px !important;}
        .slideshow-multiple-items-3 .listing-item img, .slideshow-multiple-items-4 .listing-item img {width: 100% !important; height: auto !important;}

        /* --- MIRRORED FROM: Display Posts (wp_head). Keep in sync when changing that snippet --- */
		
        .display-posts-listing {cursor: pointer;}
        .display-posts-listing .listing-item {clear: both; overflow: hidden; background: #fafbfc; border: 1px solid #e1e8ed; border-radius: 25px;}
        .display-posts-listing .listing-item:hover {background: #f2f5f7;}
        .display-posts-listing img {aspect-ratio: 16/9; width: 100%; height: auto; display: block;}
        .display-posts-listing .title {display: block; margin: 16px 0; text-align: center; font-size: 1.125rem; width: 100%;}
        .display-posts-listing .excerpt {clear: right; display: block; text-align: center; margin: 0 16px 20px;}
        .listing-item .excerpt-dash {display: none;}
        .display-posts-listing.grid {display: grid; grid-template-columns: repeat(2, 1fr); grid-gap: 1.75rem 1.5rem;}
        .display-posts-listing.grid img {display: block; max-width: 100%; height: auto;}
        .display-posts-listing.grid .title {margin: 12px 0; font-size: 1.125rem;}
        .display-posts-trending {display: flex; flex-wrap: wrap; gap: 20px;}
        .display-posts-trending .listing-item {display: flex; align-items: center; justify-content: space-between; flex: 1 1 calc(16.66% - 20px); box-sizing: border-box; margin-bottom: 20px; background: none; border: none;}
        .display-posts-trending .image {width: 80px; height: 80px; margin: 0 15px 0 0; overflow: hidden; border-radius: 50%; display: flex; justify-content: center; align-items: center;}
        .display-posts-trending .image img {width: 100%; height: 100%; object-fit: cover; border-radius: 50%;}
        .display-posts-trending .title {text-align: left; font-size: 1rem; margin: 0; flex: 1; overflow-wrap: anywhere;}
        .display-taxonomies .listing-item a.image {display: block;}
        .display-taxonomies .listing-item a.image img {width: 100%; height: auto;}

        /* --- UNIQUE TO THIS SNIPPET — Not defined anywhere else --- */
		
        .interface-interface-skeleton__sidebar,
        .interface-complementary-area__fill,
        .interface-complementary-area.editor-sidebar {transition: width 0.25s ease !important;}
        .sc-sidebar-expanded .interface-interface-skeleton__sidebar {width: 600px !important; min-width: 600px !important; flex-shrink: 0 !important;}
        .sc-sidebar-expanded .interface-interface-skeleton__content {flex: 1 1 auto !important; min-width: 0 !important; overflow: hidden !important;}
        .sc-inspector-preview-body {background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 12px; overflow-y: auto; max-height: 70vh; box-sizing: border-box; width: 100%;}
        .sc-inspector-actions {display: flex; justify-content: space-between; align-items: center; margin-top: 8px;}
        .sc-inspector-btn {font-size: 11px; color: #1e73be; background: none; border: none; cursor: pointer; padding: 0;}
        .sc-inspector-btn:hover {color: #c53030; text-decoration: underline;}
    ';
	
    wp_register_style( 'shortcode-live-preview-editor-css', false );
    wp_enqueue_style( 'shortcode-live-preview-editor-css' );
    wp_add_inline_style( 'shortcode-live-preview-editor-css', $css );
}
add_action( 'enqueue_block_editor_assets', 'shortcode_live_preview_editor_css' );

// 3. EDITOR JAVASCRIPT — Only loads in block editor

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
    function debounce(fn, delay) {
        var timer;
        return function() {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() { fn.apply(null, args); }, delay);
        };
    }
	
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

    function withShortcodePreview(BlockEdit) {
        return function(props) {
            if (props.name !== 'core/shortcode') {
                return createElement(BlockEdit, props);
            }
            var shortcode   = props.attributes.text || '';
            var _state      = useState({ html: '', loading: false, hasError: false, fetched: '' });
            var state       = _state[0];
            var setState    = _state[1];
            var _expanded   = useState(false);
            var isExpanded  = _expanded[0];
            var setExpanded = _expanded[1];
            useEffect(function() {
                setSidebarExpanded(isExpanded);
                return function() { setSidebarExpanded(false); };
            }, [isExpanded]);

            function fetchPreview(sc) {
                if (!sc || sc.indexOf('[') === -1) {
                    setState({ html: '', loading: false, hasError: false, fetched: '' });
                    return;
                }
                setState(function(prev) { return { html: prev.html, loading: true, hasError: false, fetched: prev.fetched }; });
                fetch(REST_URL, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                    body:    JSON.stringify({ shortcode: sc }),
                })
                .then(function(res) { return res.json(); })
                .then(function(data) { setState({ html: data.html || '', loading: false, hasError: false, fetched: sc }); })
                .catch(function() { setState({ html: '', loading: false, hasError: true, fetched: sc }); });
            }
            var debouncedFetch = useRef(debounce(function(sc) { fetchPreview(sc); }, 600)).current;

            // Auto-fetch when Shortcode changes
            useEffect(function() {
                if (shortcode && shortcode !== state.fetched) { debouncedFetch(shortcode); }
            }, [shortcode]);
            var statusStyle = { color: '#808080', fontSize: '13px', fontStyle: 'italic', margin: 0 };
            var errorStyle  = { color: '#c53030', fontSize: '13px', margin: 0 };
            return createElement(Fragment, null,
                createElement(BlockEdit, props),
                createElement(InspectorControls, null,
                    createElement(PanelBody, { title: '\u26a1 Shortcode Preview', initialOpen: true },
                        !shortcode || shortcode.indexOf('[') === -1
                            ? createElement('p', { style: statusStyle }, 'Enter a shortcode to see a preview.')
                            : state.hasError
                                ? createElement('p', { style: errorStyle }, '\u26a0 Could not render shortcode.')
                                : state.loading && !state.html
                                    ? createElement('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', padding: '4px 0' } },
                                        createElement(Spinner, null),
                                        createElement('span', { style: statusStyle }, 'Rendering\u2026')
                                      )
                                    : createElement(Fragment, null,
                                        state.loading ? createElement('p', { style: statusStyle }, 'Updating\u2026') : null,
                                        createElement('div', { className: 'sc-inspector-preview-body', dangerouslySetInnerHTML: { __html: state.html } }),
                                        createElement('div', { className: 'sc-inspector-actions' },
                                            createElement('button', { className: 'sc-inspector-btn', onClick: function() { setExpanded(function(prev) { return !prev; }); } },
                                                isExpanded ? '\u21d4 Collapse sidebar' : '\u21d4 Expand sidebar'
                                            ),
                                            createElement('button', { className: 'sc-inspector-btn', onClick: function() { fetchPreview(shortcode); } }, '\u21ba Refresh')
                                        )
                                      )
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

// =========================
// PATTERN LIVE PREVIEW
// =========================

// 1. PATTERN EDITOR CSS — Mirrors frontend styles for block editor
//    preview of patterns that load CSS conditionally on wp_footer

function pattern_editor_css() {

    $css = '
        /* --- MIRRORED FROM: Number Counter (wp_footer). Keep in sync when changing that snippet --- */

        .counter-grid {display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; justify-content: center;}
        .counter-card {margin: 10px 5px; border-radius: 25px; overflow: hidden;}
        .counter-card .counter-body {padding: 15px 0; text-align: center; color: #3A4F66;}
        .counter-value {color: #990033; font-size: 1.75rem; font-weight: bold;}
        .counter-value, .counter-label {vertical-align: middle;}
        .counter-label {padding-left: 10px; font-weight: normal;}
        @media (max-width: 992px) {.counter-grid {grid-template-columns: repeat(3, 1fr);}}
        @media (max-width: 600px) {.counter-grid {grid-template-columns: repeat(2, 1fr);}}
    ';

    wp_register_style('pattern-editor-css', false);
    wp_enqueue_style('pattern-editor-css');
    wp_add_inline_style('pattern-editor-css', $css);
}
add_action('enqueue_block_editor_assets', 'pattern_editor_css');
