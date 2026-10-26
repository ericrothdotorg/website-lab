<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* ============================================================================
 * FRONTPAGE STUFF — front page only. Scope: front-end.
 *   1  Cover       [er_frontpage_cover]: WebGL image cover, CSS poster first
 *   2  Cover host  classes on the Group around the cover
 *   3  Styles      brand header, counter discs
 * Feature code that merely also runs on the front page stays with its feature.
 * ========================================================================== */

/* Cover image (2000 × 1200 or similar) and the page its credit links to. */
defined( 'ER_FP_IMAGE' )  || define( 'ER_FP_IMAGE',
	'https://ericroth.org/wp-content/uploads/2026/10/Milky-Way-Galaxy.jpg' );
defined( 'ER_FP_CREDIT' ) || define( 'ER_FP_CREDIT',
	'https://science.nasa.gov/image-detail/amf-5ec7f45a-11bb-46f2-8dbc-723aee71b471/' );

add_action( 'init', static function () {
	add_shortcode( 'er_frontpage_cover', 'er_fp_cover_shortcode' );
}, 20 );

/* ============================================================================
 * 1  COVER — [er_frontpage_cover]
 *    Place first, as a direct child of a Group (Shortcode or Custom HTML block).
 *    First paint: CSS poster only (blurred opening frame, inline). After load +
 *    idle: image + WebGL (push-in/out, drift, roll sway, passing stars; portrait
 *    turns the image upright). Bots, headless, software GL, any failure: poster
 *    stays.
 *    Motion (all start at 0, so the opening frame matches the poster):
 *      zoom   1.04–1.24×, 40 s each way      FS: float z=...
 *      roll   ±3.5°, ~42 s per sway           FS: float r=...
 *      drift  wide, never-repeating path      draw(): U.O
 *      stars  passing layers                  FS: float ph=T*.1...
 *    Changing the image: upload it, set ER_FP_IMAGE, regenerate the two poster
 *    strings from a blurred ~48 × 29 / 24 × 50 WebP of the opening frame.
 * ========================================================================== */

function er_fp_cover_shortcode() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	add_action( 'wp_footer', 'er_fp_cover_script', 20 );

	return '<style id="er-cover-css">' . er_fp_cover_css() . '</style>'
		. '<p class="er-cover-credit"><a href="' . esc_url( ER_FP_CREDIT ) . '" target="_blank" rel="noopener">Image Credit</a></p>'
		. '<div class="er-cover" aria-hidden="true" data-img="' . esc_url( ER_FP_IMAGE ) . '"><canvas></canvas></div>';
}

function er_fp_cover_script() {
	wp_print_inline_script_tag( er_fp_cover_js(), array( 'id' => 'er-cover-js' ) );
}

/* Poster: tiny blurred opening frame, WebP base64 (portrait | landscape). */
function er_fp_cover_poster( $portrait ) {
	return $portrait
		? 'UklGRogBAABXRUJQVlA4IHwBAACQCQCdASoYADIAPi0ShkKhoQ3+qgAMAWJQBOmUI/3kCWfriRmK+1YWbRPiWI3uWo4x'
			. 'coi+nyjXyQiHrUxN9u1xJqvl1hIRiQmMRD96AaRkfkjzmZ0AAP7/nZVX8aQYHx/1vRpUlkWRVy+d8dYrdzfjkVxYEcSv'
			. 'bLJPlPDS5zCNwA6Y0QKFWS2oiaJpiV7iMt4Wm9bA9Tz5p58J3fwXvKmnh484QPjtgtl9zcmecwD4PhjZXusPNDWNxOcu'
			. 'anN88VlDHwGjcVUaUqHhtdvePCjfVU3X7JciXoD78N7i2b2m1OA106neCgFh1e/EHg6MG9uhMO+1CiUibIBSPBprNqwr'
			. 'UN2jONTJxwtDmO4liQg74yZF8hSMSc47y9JUZ2/5yoA8WT3fTW7HQksdYiuwXImuNn4tHpEo7PTu3JHpPGr2VtD3QdQU'
			. 'Z4PufVO3VxCrx/cQfm82Ik63jBpEBn58EA5Mc/zhEKyATaSBvLxp5xMBqo/NChF+tzXT0gAAAA=='
		: 'UklGRuIBAABXRUJQVlA4INYBAAAwCgCdASowAB0APjESiEKiISEYCq6oIAMEswBOmUI9WkCU7+l4vd3A553TdN5mrMfg'
			. 'T4HFtLlA4inEyoj8y+/aOQ4LVIG1eB6FYERJq7jhmrNI/9BxeFusIAAA/v/+GNOt/8kL4tqX497Gx4xjSYBaWfI+tew+'
			. 'mlQQVOZdSafzfS5+B37+I6Y6/qgeW4QnNrsSGH/tZ0KllHc80RIYRouwqBsaD1iNKsHV2EpsCcKUwT0sKDxXodjFceln'
			. 'D9GXIxBeUwgXGqX+sGvL77ZcxrvP/K5wtCL9mj5CF5kSF5N2D3142zrsDhLV5n5WbuTYf0bJ4zDtjvvVv+CJ2QNrspEX'
			. 'mjkLXLz+3Jb5dKldJRB2EVeifIGE7zo7cvmK5e18YnZtf38UisYfPaiQTJCPlBXU1pfVhDfTC71QvMeIMDIB52mJLU7J'
			. 'JwCFY4h76koimS2N/QkBZHp8QPUB7knTxjp1ZnPbtl2VHCHtX93Mysx8ZvI620EzmWzBaRyTEoZRuX7cw06glB3nySIW'
			. 'v+nbaWqdBQgyMnXHcrJPycGkH5DmW5adgLATkCQNDRoVJ5pSA2zOnPZfKU8/G5ZuXkKG+pPLpDj+TUgXhN8Ya23vTGNm'
			. 'Rl4Gh7AAAA==';
}

function er_fp_cover_css() {
	$css = <<<'CSS'
.wp-block-group.er-cover-host{position:relative;display:flex;flex-direction:column;justify-content:center;
justify-content:safe center;min-height:100svh;width:auto;max-width:none;margin:0;overflow:clip;
background-color:#0c0d14}
.er-cover-anc.er-cover-anc{margin-top:0}
:empty:has(+ .wp-block-group.er-cover-host){display:none}
.wp-block-group.er-cover-host>:not(.er-cover):not(.er-cover-credit){position:relative;z-index:1;width:100%}
.wp-block-group.er-cover-host>.er-cover+*{margin-top:0}
div.er-cover{position:absolute;inset:0;z-index:0;display:block;width:100%;height:100%;max-width:none;
margin:0;overflow:hidden;pointer-events:none;
background:#0c0d14 url(data:image/webp;base64,%LAND%) center/cover no-repeat}
@media (max-aspect-ratio:1/1){div.er-cover{background-image:url(data:image/webp;base64,%PORT%)}}
div.er-cover::before{content:"";position:absolute;inset:0;
background-image:
radial-gradient(1px 1px at 46% 24%,rgba(235,240,255,0.55) 50%,transparent 100%),
radial-gradient(1px 1px at 88% 11%,rgba(235,240,255,0.55) 50%,transparent 100%),
radial-gradient(1px 1px at 73% 17%,rgba(235,240,255,0.55) 50%,transparent 100%),
radial-gradient(1.5px 1.5px at 79% 12%,rgba(235,240,255,0.55) 50%,transparent 100%),
radial-gradient(1px 1px at 32% 9%,rgba(235,240,255,0.55) 50%,transparent 100%),
radial-gradient(1px 1px at 60% 58%,rgba(235,240,255,0.75) 50%,transparent 100%),
radial-gradient(1.5px 1.5px at 35% 16%,rgba(235,240,255,0.75) 50%,transparent 100%),
radial-gradient(1.5px 1.5px at 59% 12%,rgba(235,240,255,0.75) 50%,transparent 100%),
radial-gradient(1.5px 1.5px at 20% 33%,rgba(235,240,255,0.75) 50%,transparent 100%),
radial-gradient(1px 1px at 85% 79%,rgba(235,240,255,0.75) 50%,transparent 100%),
radial-gradient(1px 1px at 78% 79%,rgba(235,240,255,0.95) 50%,transparent 100%),
radial-gradient(1px 1px at 11% 33%,rgba(235,240,255,0.95) 50%,transparent 100%),
radial-gradient(1px 1px at 76% 22%,rgba(235,240,255,0.95) 50%,transparent 100%),
radial-gradient(1.5px 1.5px at 58% 23%,rgba(235,240,255,0.95) 50%,transparent 100%);
background-size:
233px 197px,233px 197px,233px 197px,233px 197px,233px 197px,
331px 283px,331px 283px,331px 283px,331px 283px,331px 283px,
467px 409px,467px 409px,467px 409px,467px 409px}
.er-cover canvas{position:absolute;inset:0;width:100%;height:100%;display:block;opacity:0;
transition:opacity 1.8s ease}
.er-cover canvas.is-on{opacity:1}
.wp-block-group.er-cover-host>.er-cover-credit{position:absolute;z-index:2;right:12px;bottom:8px;
width:auto;margin:0;font-size:11px;line-height:1.4;text-shadow:0 1px 2px rgba(0,0,0,.85)}
.wp-block-group.er-cover-host>.er-cover-credit a{color:rgba(225,232,237,.62);font-weight:400;
text-decoration:none}
.wp-block-group.er-cover-host>.er-cover-credit a:hover,
.wp-block-group.er-cover-host>.er-cover-credit a:focus-visible{color:#fff;text-decoration:underline}
CSS;
	return strtr( $css, array(
		'%LAND%' => er_fp_cover_poster( false ),
		'%PORT%' => er_fp_cover_poster( true ),
	) );
}

function er_fp_cover_js() {
	$js = <<<'JS'
(() => {
const root = document.querySelector('.er-cover');
if (!root || !root.dataset.img || navigator.webdriver || /lighthouse|headlesschrome/i.test(navigator.userAgent)) return;
const cv = root.querySelector('canvas');

const FS = `precision highp float;
uniform vec2 R,O;uniform float T,N,K;uniform sampler2D I;
float h(vec2 p){p=fract(p*vec2(233.34,851.73));p+=dot(p,p+23.45);return fract(p.x*p.y);}
vec3 tc(float t){return t<.5?mix(vec3(.66,.78,1.),vec3(1.,.97,.93),t*2.):mix(vec3(1.,.97,.93),vec3(1.,.8,.6),t*2.-1.);}
void main(){
vec2 u=(gl_FragCoord.xy*2.-R)/R.y;
float r=.06*sin(T*.15);u=mat2(cos(r),sin(r),-sin(r),cos(r))*u;
float as=R.x/R.y,px=2./R.y;
float P=step(as,1.),S=mix(max(as/K,min(1.,as/(.72*K))),.7,P),a=-.94*P,ca=cos(a),sa=sin(a);
float z=1.04+.20*(.5-.5*cos(T*.0785));
vec2 iu=mat2(ca,sa,-sa,ca)*(u-O)/(S*z)+vec2(-.02,.13)*P,t=vec2(iu.x/K,iu.y)*.5+.5;
vec3 bg=mix(vec3(.12,.13,.16),vec3(.08,.1,.15),gl_FragCoord.y/R.y);
float m=smoothstep(0.,.1,t.y)*smoothstep(1.,.9,t.y)*smoothstep(0.,.05,t.x)*smoothstep(1.,.95,t.x);
if(m<1.){vec2 g=u*110.,c=floor(g),f=fract(g)-.5;float hs=h(c);
if(hs<.06){vec2 sp=(vec2(h(c+1.3),h(c+7.1))-.5)*.6;float d=length(f-sp),e=h(c+4.2);
bg+=tc(h(c+9.))*smoothstep(max(px*99.,.12),0.,d)*(.1+1.1*e*e*e*e);}}
vec3 col=mix(bg,texture2D(I,clamp(t,0.,1.)).rgb,m);
for(int i=0;i<4;i++){float ph=T*.1+float(i)*.25,zz=fract(ph),id=floor(ph)*4.+float(i);
float sc=24.*max(1.-zz,.03);vec2 gg=u*sc+vec2(h(vec2(id,.3)),h(vec2(.7,id)))*97.;
vec2 cc=floor(gg),ff=fract(gg)-.5;float s=h(cc+id);
if(s<.06){vec2 sp=(vec2(h(cc+1.3+id),h(cc+7.1-id))-.5)*.62;float d=length(ff-sp),q=px*sc;
float rad=.012+.022*h(cc+5.5);
float bb=smoothstep(rad+q,rad*.3,d)+.18*exp(-d/(rad*2.+q))*smoothstep(.3,.1,d);
float fd=smoothstep(0.,.3,zz)*smoothstep(1.,.8,zz)*(.4+.6*s/.06);
col+=tc(h(cc+9.))*bb*fd;}}
vec2 w=gl_FragCoord.xy/R-.5;col*=1.-dot(w,w)*.35;
gl_FragColor=vec4(min(col,1.)+(fract(sin(dot(gl_FragCoord.xy+N,vec2(12.99,78.23)))*43758.5)-.5)*.012,1.);}`;

function start() {
	const gl = cv.getContext('webgl', { alpha: false, depth: false, stencil: false, antialias: false,
		failIfMajorPerformanceCaveat: true, powerPreference: 'low-power' });
	if (!gl) return;
	const sh = (type, src) => { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; };
	const pr = gl.createProgram();
	gl.attachShader(pr, sh(gl.VERTEX_SHADER, 'attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}'));
	gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, FS));
	gl.bindAttribLocation(pr, 0, 'p');
	gl.linkProgram(pr);
	if (!gl.getProgramParameter(pr, gl.LINK_STATUS)) return;
	gl.useProgram(pr);
	gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
	gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
	gl.enableVertexAttribArray(0);
	gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
	const U = {};
	'R O T N K'.split(' ').forEach((k) => { U[k] = gl.getUniformLocation(pr, k); });

	const img = new Image();
	img.crossOrigin = 'anonymous';
	img.decoding = 'async';
	img.src = root.dataset.img;
	img.decode().then(() => {
		const tex = gl.createTexture();
		gl.bindTexture(gl.TEXTURE_2D, tex);
		gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
		gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, img);
		[gl.TEXTURE_WRAP_S, gl.TEXTURE_WRAP_T].forEach((p) => gl.texParameteri(gl.TEXTURE_2D, p, gl.CLAMP_TO_EDGE));
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
		gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
		gl.uniform1f(U.K, img.naturalWidth / img.naturalHeight);
		animate();
	}).catch(() => {});

	function animate() {
		const MAX = Math.min(window.devicePixelRatio || 1, 1.5);
		let q = Math.min(1, MAX), W = 0, H = 0, n = 0, acc = 0;
		const size = () => {
			const r = cv.getBoundingClientRect();
			const w = Math.max(1, Math.round(r.width * q)), h = Math.max(1, Math.round(r.height * q));
			if (w !== W || h !== H) { W = cv.width = w; H = cv.height = h; gl.viewport(0, 0, W, H); }
		};

		const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		let seen = true, run = false, dead = false, raf = 0, last = 0, shown = false, clock = 0;

		const draw = (now) => {
			const w = clock / 1000;
			size();
			gl.uniform2f(U.R, W, H);
			gl.uniform2f(U.O, 0.05 * Math.sin(w * 0.15), 0.012 + 0.03 * Math.sin(w * 0.11));
			gl.uniform1f(U.T, w);
			gl.uniform1f(U.N, (now / 1000) % 1000);
			gl.drawArrays(gl.TRIANGLES, 0, 3);
			if (!shown) { shown = true; cv.classList.add('is-on'); }
		};

		const frame = (now) => {
			raf = 0;
			if (!run) return;
			const dt = last ? Math.min(100, now - last) : 16;
			last = now;
			clock += dt;
			draw(now);
			if (++n > 1) acc += dt;
			if (n === 41) {
				const avg = acc / 40;
				n = 0; acc = 0;
				if (avg > 24) {
					if (q <= 0.35) { run = false; dead = true; return; }
					q = Math.max(0.35, q * 0.8);
				} else if (avg < 15 && q < MAX) {
					q = Math.min(MAX, q * 1.12);
				}
			}
			raf = requestAnimationFrame(frame);
		};

		const toggle = () => {
			const want = seen && !document.hidden && !reduce && !dead;
			if (want && !run) { run = true; last = 0; n = 0; acc = 0; raf = raf || requestAnimationFrame(frame); }
			if (!want) run = false;
		};

		cv.addEventListener('webglcontextlost', () => { dead = true; run = false; cv.classList.remove('is-on'); });
		if (reduce) { requestAnimationFrame(draw); return; }
		if ('IntersectionObserver' in window) new IntersectionObserver((e) => { seen = e[0].isIntersecting; toggle(); }).observe(root);
		document.addEventListener('visibilitychange', toggle);
		toggle();
	}
}

const idle = window.requestIdleCallback || ((f) => setTimeout(f, 300));
const go = () => idle(start, { timeout: 2500 });
if (document.readyState === 'complete') go(); else window.addEventListener('load', go, { once: true });
})();
JS;
	/* Strips indentation and blank lines: use only block comments in the JS. */
	return preg_replace( '/^\s+|\n\s*\n/m', '', $js );
}

/* ============================================================================
 * 2  COVER HOST — er-cover-host on the Group holding the shortcode, er-cover-anc
 *    on its parent and grandparent. Set server-side: :has() would restyle the
 *    cover on every counter tick.
 * ========================================================================== */

add_filter( 'render_block', 'er_fp_cover_mark_block', 10, 3 );

function er_fp_cover_mark_block( $html, $block, $instance = null ) {
	if ( ! is_front_page() ) {
		return $html;
	}
	$depth = er_fp_cover_depth( $block, $instance, 2 );
	if ( $depth < 0 ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	if ( $p->next_tag() ) {
		$p->add_class( 0 === $depth ? 'er-cover-host' : 'er-cover-anc' );
	}
	return $p->get_updated_html();
}

/* 0 = host Group, 1 = parent, 2 = grandparent, -1 = none. */
function er_fp_cover_depth( $block, $instance, $max ) {
	if ( er_fp_cover_is_host( $block ) ) {
		return 0;
	}
	if ( $max <= 0 ) {
		return -1;
	}
	foreach ( er_fp_cover_children( $block, $instance ) as $child ) {
		$d = er_fp_cover_depth( $child, null, $max - 1 );
		if ( $d >= 0 ) {
			return $d + 1;
		}
	}
	return -1;
}

function er_fp_cover_is_host( $block ) {
	if ( 'core/group' !== ( $block['blockName'] ?? '' ) ) {
		return false;
	}
	foreach ( $block['innerBlocks'] ?? array() as $inner ) {
		if ( in_array( $inner['blockName'] ?? '', array( 'core/shortcode', 'core/html' ), true )
			&& false !== strpos( (string) ( $inner['innerHTML'] ?? '' ), '[er_frontpage_cover' ) ) {
			return true;
		}
	}
	return false;
}

/* For Post Content: the blocks of the post it shows. */
function er_fp_cover_children( $block, $instance ) {
	if ( 'core/post-content' !== ( $block['blockName'] ?? '' ) ) {
		return $block['innerBlocks'] ?? array();
	}
	$id = ( $instance instanceof WP_Block && ! empty( $instance->context['postId'] ) )
		? (int) $instance->context['postId']
		: (int) get_the_ID();
	static $cache = array();
	if ( ! isset( $cache[ $id ] ) ) {
		$content      = $id ? (string) get_post_field( 'post_content', $id ) : '';
		$cache[ $id ] = false !== strpos( $content, '[er_frontpage_cover' ) ? parse_blocks( $content ) : array();
	}
	return $cache[ $id ];
}

/* ============================================================================
 * 3  STYLES — after Number Counter's styles (wp_head 5), so equal specificity
 *    wins. rotate3d keyframe: Ollie Companion CSS.
 * ========================================================================== */

add_action( 'wp_head', 'er_fp_styles', 10 );

function er_fp_styles() {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<style id="er-frontpage-css">' . er_fp_styles_css() . '</style>' . "\n";
}

function er_fp_styles_css() {
	return <<<'CSS'
	/* Brand header */
	.er-brand-frontpage {display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 1.75rem;}
	.er-brand-frontpage-logo img {width: clamp(45px, 9vw, 85px); aspect-ratio: 1 / 1; height: auto; animation: rotate3d 5s linear infinite; position: relative; top: 0.05em;}
	.er-brand-frontpage-logo img:hover {animation-play-state: paused;}
	.er-brand-frontpage-title {font-size: clamp(2rem, 6vw, 3.5rem); font-weight: var(--er-fw-bold); color: var(--color-8); line-height: 1; letter-spacing: 0.1em; text-shadow: 0.10em 0.10em 0.15em rgba(0, 0, 0, 0.25);}
	.er-brand-frontpage-teaser {display: block; width: 100%; max-width: 850px; box-sizing: border-box; margin: 0 auto; background: rgba(0, 0, 0, 0.65); border-radius: 25px;}

	/* Counter discs over the cover (Number Counter) */
	.page-id-179 .counter-card {background: rgba(0, 0, 0, 0.65) !important; border: none !important;}
	.page-id-179 .counter-card:hover {background: var(--color-6) !important;}
	.page-id-179 .counter-body {color: var(--color-5);}
	.page-id-179 .counter-value {color: #990033;}
	.page-id-179 .counter-body img {filter: brightness(0) invert(1) brightness(0.95) !important;}

	/* Stat tiles over the cover (Views & Like | Dislike Voting): front-page colours.
	   Size, squares, centring and phone layout come from the Voting snippet. */
	.page-id-179 .er-stat-line {background: rgba(0, 0, 0, 0.65) !important; border: none !important; color: var(--color-5) !important;}
	.page-id-179 .er-stat-foot {border-top-color: var(--color-3) !important; color: var(--color-5) !important;}
	.page-id-179 .er-stat-icon {filter: brightness(0) invert(1) brightness(0.95) !important;}

	/* Space above and below the content once the discs wrap (Number Counter: 1200px) and the page gets taller */
	@media (max-width: 1200px) {.wp-block-group.er-cover-host > .wp-block-group {padding-top: var(--wp--preset--spacing--large); padding-bottom: var(--wp--preset--spacing--large);}}
CSS;
}
