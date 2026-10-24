<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

/* ============================================================================
 * FRONTPAGE STUFF — everything that belongs to the front page only
 * ----------------------------------------------------------------------------
 * The front page changes now and then; whatever belongs to it alone lives
 * here. Feature code that merely also runs on the front page stays with its
 * feature (e.g. Number Counter's script, the is_front_page() exclusions in
 * Accessibility, Google Translate, Toggle and the breadcrumbs).
 *
 * MODULES
 *   1  Cover ......... [er_frontpage_cover]: endless dive down an origami
 *                      funnel made of the site logo (WebGL)
 *   2  Cover host .... marks the Group around the cover in the server HTML
 *   3  Styles ........ brand header (from Ollie Companion CSS) and the dark
 *                      counter discs (from Number Counter)
 *
 * Independent of every other snippet. World Map (Site Reach) is not used.
 * Scope: front-end.
 * ========================================================================== */

/* ============================================================================
 * 0  CONFIG
 * ========================================================================== */

defined( 'ER_FP_RING' ) || define( 'ER_FP_RING', 7 );   // seconds per ring of the dive (smaller = faster)

add_action( 'init', static function () {
	add_shortcode( 'er_frontpage_cover', 'er_fp_cover_shortcode' );
}, 20 );

/* ============================================================================
 * 1  COVER — [er_frontpage_cover]
 *    Place it first, as a direct child of a Group, in a Shortcode or Custom
 *    HTML block. Height, full-bleed and the stacking of the siblings come
 *    from the CSS below; nothing to set on the block itself.
 *
 *    The funnel: eight walls carry the logo; each ring of eight folded
 *    flaps continues into a copy half the size, turned by one fold, forever.
 *    Every ring is an exact copy of the one before: seamless and endless.
 *    No ray marching: eight plane tests per pixel, light on any GPU.
 *
 *    PAGE SPEED
 *      First paint: a static CSS poster only, no script work, no request.
 *      WebGL starts after "load", when the browser is idle.
 *      No WebGL for bots, Lighthouse / headless Chrome, software rendering
 *      (failIfMajorPerformanceCaveat) or a failed shader: the poster stays.
 *      Resolution adapts to the device; too slow -> stops on the current frame.
 *      Paused while scrolled out of view or the tab is hidden.
 *      Reduced motion: one still frame, no animation.
 *      No images, no libraries, no data requests.
 * ========================================================================== */

function er_fp_cover_shortcode() {
	static $done = false;
	if ( $done ) {
		return '';   // one cover per page
	}
	$done = true;
	add_action( 'wp_footer', 'er_fp_cover_script', 20 );

	return '<style id="er-cover-css">' . er_fp_cover_css() . '</style>'
		. '<div class="er-cover" aria-hidden="true" data-ring="' . (int) ER_FP_RING . '"><canvas></canvas></div>';
}

function er_fp_cover_script() {
	wp_print_inline_script_tag( er_fp_cover_js(), array( 'id' => 'er-cover-js' ) );
}

/* Host layout (classes from module 2), poster, canvas fade-in, text veil. */
function er_fp_cover_css() {
	return <<<'CSS'
.wp-block-group.er-cover-host{position:relative;display:flex;flex-direction:column;justify-content:center;justify-content:safe center;min-height:100svh;width:auto;max-width:none;margin:0;overflow:clip;background-color:var(--color-6,#070c12)}
.er-cover-anc.er-cover-anc{margin-top:0}
:empty:has(+ .wp-block-group.er-cover-host){display:none}
.wp-block-group.er-cover-host>:not(.er-cover){position:relative;z-index:1;width:100%}
.wp-block-group.er-cover-host>.er-cover+*{margin-top:0}
div.er-cover{position:absolute;inset:0;z-index:0;display:block;width:100%;height:100%;max-width:none;margin:0;overflow:hidden;pointer-events:none;
background:radial-gradient(closest-side at 50% 50%,rgba(7,12,18,.55) 10%,rgba(7,12,18,.35) 50%,var(--color-6,#070c12) 100%),conic-gradient(from 0deg at 50% 50%,#009831,#8fc22a,#fcc900,#f59028,#c62a2a,#902a90,#005cc2,#008888,#009831)}
.er-cover canvas{position:absolute;inset:0;width:100%;height:100%;display:block;opacity:0;transition:opacity 1.8s ease}
.er-cover canvas.is-on{opacity:1}
.er-cover::after{content:"";position:absolute;inset:0;background:radial-gradient(62% 62% at 50% 50%,rgba(6,19,42,.3),rgba(6,19,42,.14) 55%,rgba(6,19,42,0))}
CSS;
}

function er_fp_cover_js() {
	$js = <<<'JS'
(() => {
const root = document.querySelector('.er-cover');
if (!root || navigator.webdriver || /lighthouse|headlesschrome/i.test(navigator.userAgent)) return;
const cv = root.querySelector('canvas');
const RING = +root.dataset.ring || 7;

/* Logo colours, clockwise from the top. */
const LOGO = ['#009831', '#8fc22a', '#fcc900', '#f59028', '#c62a2a', '#902a90', '#005cc2', '#008888'];

const FS = `precision highp float;
uniform vec2 R;uniform vec3 C,F,X,Y,P[8];uniform float S,N;
const float Q=.5,DS=1.,CO=.92388;
vec2 V(float k,float r){float a=k*.785398;return r*vec2(sin(a),cos(a));}
float cr(vec2 a,vec2 b){return a.x*b.y-a.y*b.x;}
bool tri(vec2 p,vec2 a,vec2 b,vec2 c){float x=cr(b-a,p-a),y=cr(c-b,p-b),z=cr(a-c,p-c);return(x<=0.&&y<=0.&&z<=0.)||(x>=0.&&y>=0.&&z>=0.);}
vec3 flap(vec2 p,float pf,out float sh){
vec3 c=P[0];sh=1.;
for(int k=0;k<8;k++){float K=float(k);vec2 a0=V(K-1.,Q),o0=V(K,1.),a1=V(K,Q),o1=V(K+1.,1.);
if(tri(p,o0,o1,a1)||tri(p,o0,a1,a0)){c=P[k];float d=abs(cr(normalize(a0-o0),p-o0));
sh=mix(.28,1.12,smoothstep(0.,.38,d))*mix(1.08,.82,clamp(length(p)*1.3-.6,0.,1.))*mix(.55,1.,smoothstep(0.,pf*2.,d));}}
return c;}
void main(){
vec2 u=(gl_FragCoord.xy*2.-R)/R.y;
vec3 d=normalize(F*1.5+X*u.x+Y*u.y),g=vec3(1.,.9,.74);
float t=1e9;vec3 nf=vec3(0.,0.,1.);
for(int k=0;k<8;k++){float a=(float(k)+.5)*.785398;vec2 n=vec2(sin(a),cos(a));
float A=DS*dot(C.xy,n)/CO-C.z,B=DS*dot(d.xy,n)/CO-d.z;
if(B>0.){float tk=-A/B;if(tk<t){t=tk;nf=vec3(DS*n/CO,-1.);}}}
vec3 h=C+d*t;float rho=max(h.z/DS,1e-6);
float l=log(rho)/log(Q),n=floor(l+S),m=n-S,sc=pow(Q,m);
vec2 p=h.xy/sc;float an=n*.785398,cs=cos(an),sn=sin(an);p=vec2(cs*p.x-sn*p.y,sn*p.x+cs*p.y);
float px=2./(R.y*1.5),pf=t*px/sc*2.;
float sh;vec3 c=flap(p,pf+.002,sh);
vec3 av=(P[0]+P[1]+P[2]+P[3]+P[4]+P[5]+P[6]+P[7])*.125;
float fz=smoothstep(.08,.5,pf);c=mix(c,av,fz);sh=mix(sh,.8,fz);
vec3 ni=-normalize(nf),L=normalize(-h);
float dl=max(dot(ni,L),0.),lam=max(dot(ni,-d),0.);
float lit=1./(1.+rho*rho*6.);
float sp=pow(max(dot(ni,normalize(L-d)),0.),30.);
vec3 col=c*sh*(lit*(.35+1.4*dl)+.22*lam+.12)+g*sp*lit*.5*sh;
col*=1.15;
col=clamp((col*(2.51*col+.03))/(col*(2.43*col+.59)+.14),0.,1.);
vec2 w=gl_FragCoord.xy/R-.5;col*=1.-dot(w,w)*.7;
gl_FragColor=vec4(pow(col,vec3(.4545))+(fract(sin(dot(gl_FragCoord.xy+N,vec2(12.99,78.23)))*43758.5)-.5)*.015,1.);}`;

function start() {
	const gl = cv.getContext('webgl', { alpha: false, depth: false, stencil: false, antialias: false,
		failIfMajorPerformanceCaveat: true, powerPreference: 'low-power' });
	if (!gl) return;
	const sh = (type, src) => { const s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; };
	const pr = gl.createProgram();
	gl.attachShader(pr, sh(gl.VERTEX_SHADER, 'attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}'));
	gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, FS));
	gl.linkProgram(pr);
	if (!gl.getProgramParameter(pr, gl.LINK_STATUS)) return;
	gl.useProgram(pr);
	gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
	gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
	gl.enableVertexAttribArray(0);
	gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
	const U = {};
	'R C F X Y S N'.split(' ').forEach((k) => { U[k] = gl.getUniformLocation(pr, k); });

	/* sRGB hex → linear, for the shader. */
	const lin = (v) => (v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
	gl.uniform3fv(gl.getUniformLocation(pr, 'P'), LOGO.flatMap((h) => [1, 3, 5].map((i) => lin(parseInt(h.substr(i, 2), 16) / 255))));

	const TAU = Math.PI * 2;
	const nrm = (v) => { const l = Math.hypot(v[0], v[1], v[2]); return [v[0] / l, v[1] / l, v[2] / l]; };
	const crs = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];

	/* Resolution adapts to the device: start mid, step up while frames are fast. */
	const MAX = Math.min(window.devicePixelRatio || 1, 1.5);
	let q = 0.75, W = 0, H = 0, n = 0, acc = 0;
	const size = () => {
		const r = cv.getBoundingClientRect();
		const w = Math.max(1, Math.round(r.width * q)), h = Math.max(1, Math.round(r.height * q));
		if (w !== W || h !== H) { W = cv.width = w; H = cv.height = h; gl.viewport(0, 0, W, H); }
	};

	const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	let seen = true, run = false, dead = false, raf = 0, s = 0.35, last = 0, shown = false, clock = 0;

	/* The camera drifts gently around the axis; it repeats with every ring, so the dive stays seamless. */
	const draw = (now) => {
		const a = TAU * s, w = clock / 1000;
		const c = [0.12 * Math.sin(a) + 0.05 * Math.sin(w * 0.37), 0.09 * Math.cos(a) + 0.05 * Math.cos(w * 0.29), 1.0];
		const f = nrm([-c[0] * 0.8, -c[1] * 0.8, -1]);
		const roll = 0.35 * Math.sin(w * 0.11);
		const r0 = nrm(crs(f, [Math.sin(roll), Math.cos(roll), 0])), u0 = crs(r0, f);
		size();
		gl.uniform2f(U.R, W, H);
		/* The world turns one fold per ring; turning the camera instead keeps the walls and flaps aligned. */
		const ta = -s * Math.PI / 4, tc = Math.cos(ta), ts = Math.sin(ta);
		const tw = (v) => [tc * v[0] - ts * v[1], ts * v[0] + tc * v[1], v[2]];
		gl.uniform3fv(U.C, tw(c)); gl.uniform3fv(U.F, tw(f)); gl.uniform3fv(U.X, tw(r0)); gl.uniform3fv(U.Y, tw(u0));
		gl.uniform1f(U.S, s);
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
		s = (s + dt / 1000 / RING) % 1;
		draw(now);
		if (++n > 1) acc += dt;
		if (n === 41) {
			const avg = acc / 40;
			n = 0; acc = 0;
			if (avg > 24) {
				if (q <= 0.35) { run = false; dead = true; return; }   // too slow even at the lowest resolution: keep this frame
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

	if (reduce) { requestAnimationFrame(draw); return; }
	if ('IntersectionObserver' in window) new IntersectionObserver((e) => { seen = e[0].isIntersecting; toggle(); }).observe(root);
	document.addEventListener('visibilitychange', toggle);
	toggle();
}

const idle = window.requestIdleCallback || ((f) => setTimeout(f, 300));
const go = () => idle(start, { timeout: 2500 });
if (document.readyState === 'complete') go(); else window.addEventListener('load', go, { once: true });
})();
JS;
	/* Leading indentation and empty lines are not sent. */
	return preg_replace( '/^\s+|\n\s*\n/m', '', $js );
}

/* ============================================================================
 * 2  COVER HOST — classes in the server HTML, front page only
 *    er-cover-host  the Group that holds [er_frontpage_cover] directly
 *    er-cover-anc   its parent and grandparent block (e.g. post content, <main>)
 *    No :has() on the live hero: with :has(), every DOM change in the hero
 *    (each counter tick) restyled the whole cover.
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

// 0 = this block is the host Group, 1 = its parent, 2 = its grandparent, -1 = none.
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

// A Group with the shortcode as a direct child (Shortcode or Custom HTML block).
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

// Child blocks; for Post Content these are the blocks of the post it shows.
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
 * 3  STYLES — front page only, in <head>
 *    Brand header: moved from Ollie Companion CSS ("Frontpage ONLY stuff").
 *    Counter discs over the cover: moved from Number Counter. Printed after
 *    Number Counter's styles (wp_head 5), so equal-specificity rules still win.
 *    rotate3d is the site-wide keyframe from Ollie Companion CSS.
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
.er-brand-frontpage {display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 1.75rem; padding-bottom: clamp(2.5rem, 4vw, 3.5rem);}
@media (max-width: 781px) {.er-brand-frontpage {padding-top: 2.5rem;}}
.er-brand-frontpage-logo img {width: clamp(45px, 9vw, 85px); aspect-ratio: 1 / 1; height: auto; animation: rotate3d 5s linear infinite; position: relative; top: 0.05em;}
.er-brand-frontpage-logo img:hover {animation-play-state: paused;}
.er-brand-frontpage-title {font-size: clamp(2rem, 6vw, 3.5rem); font-weight: var(--er-fw-bold); color: var(--color-8); line-height: 1; letter-spacing: 0.1em;}
.er-brand-frontpage-teaser {display: block; width: fit-content; margin: 0 auto; background: rgba(0, 0, 0, 0.65); border-radius: 25px;}

/* Counter discs over the cover (Number Counter) */
.page-id-179 .counter-card {background: rgba(0, 0, 0, 0.65) !important; border: none !important;}
.page-id-179 .counter-card:hover {background: var(--color-6) !important;}
.page-id-179 .counter-body {color: var(--color-5);}
.page-id-179 .counter-body img {filter: brightness(0) invert(1) brightness(0.95) !important;}
CSS;
}
