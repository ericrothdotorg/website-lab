<?php
defined('ABSPATH') || exit;

// ============================
// ACCESSIBILITY SETTINGS
// ============================

add_shortcode('er_accessibility_settings', function () {
	ob_start(); ?>

	<div id="a11y-panel">
		<h1>
			<img src="https://ericroth.org/wp-content/uploads/2025/04/universal-access-greyblue.svg" width="24" height="24" alt="" aria-hidden="true">
			Accessibility Settings
		</h1>

		<!-- LINK UNDERLINES -->

		<div class="a11y-section">
			<div class="a11y-label">
				Link underline
			</div>
			<div class="a11y-options">
				<button class="a11y-option" data-group="underline" data-value="default">
					Default
				</button>
				<button class="a11y-option" data-group="underline" data-value="always">
					Always
				</button>
				<button class="a11y-option" data-group="underline" data-value="never">
					Never
				</button>
			</div>
		</div>

		<!-- FOCUS WIDTH -->

		<div class="a11y-section">
			<div class="a11y-label">
				Focus outline width
			</div>
			<div class="a11y-options">
				<button class="a11y-option" data-group="focus-width" data-value="default">
					Default
				</button>
				<button class="a11y-option" data-group="focus-width" data-value="thick">
					Thick
				</button>
				<button class="a11y-option" data-group="focus-width" data-value="extra-thick">
					Extra Thick
				</button>
			</div>
		</div>

		<!-- FOCUS COLOR -->

		<div class="a11y-section">
			<div class="a11y-label">
				Focus outline color
			</div>
			<div class="a11y-options">
				<button class="a11y-option swatch" data-group="focus-color" data-value="orange" style="background: #ed7d31" aria-label="Orange"></button>
				<button class="a11y-option swatch" data-group="focus-color" data-value="green" style="background: #339966" aria-label="Green"></button>
				<button class="a11y-option swatch" data-group="focus-color" data-value="red" style="background: var(--color-2)" aria-label="Red"></button>
				<button class="a11y-option swatch" data-group="focus-color" data-value="purple" style="background: #7b5ea7" aria-label="Purple"></button>
				<button class="a11y-option swatch" data-group="focus-color" data-value="white" style="background: var(--color-8)" aria-label="White"></button>
				<button class="a11y-option swatch" data-group="focus-color" data-value="dark" style="background: var(--color-6)" aria-label="Dark"></button>
			</div>
		</div>

		<button id="a11y-reset">
			Reset to Defaults
		</button>
	</div>

	<?php

	return ob_get_clean();
});

// ============================
// ACCESSIBILITY WIDGET
// ============================

add_action('wp_footer', function () {
	if (is_front_page()) return;
?>

<!-- GLOBAL ACCESSIBILITY BUTTON -->

<a id="a11y-link"
   href="<?php echo esc_url(get_permalink(48682)); ?>"
   aria-label="Site Accessibility"
   title="Site Accessibility">
	<img src="https://ericroth.org/wp-content/uploads/2025/04/universal-access-greyblue.svg"
	     alt=""
	     aria-hidden="true">
</a>

<style>

	/* == GLOBAL BUTTON == */

	#a11y-link {position: fixed; bottom: 25px; right: 125px; z-index: 999; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; transition: transform .2s ease; background: var(--color-3); border-radius: 50%; padding: 0; cursor: pointer; box-shadow: 0 0 10px rgba(0,0,0,.2); border: none;}
	#a11y-link:hover {transform: scale(1.1);}
	#a11y-link:focus-visible {outline: 2px solid var(--color-3); outline-offset: 2px;}
	#a11y-link img {width: 22px; height: 22px; display: block; filter: brightness(0) invert(1);}

	/* == APPLIED STATES == */

	body.a11y-underline-always a:link {text-decoration: underline;}
	body.a11y-underline-never a:link {text-decoration: none;}

</style>

<script>

	var a11yPrefs={underline:null,'focus-width':null,'focus-color':null},
	a11yStore='er_a11y_prefs',
	a11yColors={orange:'#ed7d31',green:'#339966',red:'var(--color-2)',purple:'#7b5ea7',white:'var(--color-8)',dark:'var(--color-6)'},
	a11yWidths={thick:'3px','extra-thick':'5px'},
	a11yResolvedColors={};

	function a11ySave(){try{localStorage.setItem(a11yStore,JSON.stringify(a11yPrefs))}catch(e){}}

	function a11yResolve(val){
		if(a11yResolvedColors[val]) return a11yResolvedColors[val];
		var resolved=val.startsWith('var(') ? getComputedStyle(document.documentElement).getPropertyValue(val.slice(4,-1).trim()).trim() : val;
		a11yResolvedColors[val]=resolved;
		return resolved;
	}

	function a11yApplyFocus(el){
		if(!a11yPrefs['focus-color'] && !a11yPrefs['focus-width']) return;
		if(a11yPrefs['focus-color']) el.style.outlineColor = a11yResolve(a11yColors[a11yPrefs['focus-color']]);
		else el.style.outlineColor = '';
		if(a11yPrefs['focus-width'] && a11yPrefs['focus-width']!=='default') el.style.outlineWidth = a11yWidths[a11yPrefs['focus-width']];
		else el.style.outlineWidth = '';
	}

	function a11yClearFocus(el){
		if(!a11yPrefs['focus-color'] && !a11yPrefs['focus-width']) return;
		el.style.outlineColor = '';
		el.style.outlineWidth = '';
	}

	function a11yApplyUnderline(){
		var b=document.body.classList;
		b.toggle('a11y-underline-always', a11yPrefs.underline==='always');
		b.toggle('a11y-underline-never',  a11yPrefs.underline==='never');
	}

	function a11yUpdateButtons(){
		document.querySelectorAll('.a11y-option').forEach(function(btn){
			var active = a11yPrefs[btn.dataset.group];
			if(active === null) active = 'default';
			btn.classList.toggle('is-active', active === btn.dataset.value);
		});
	}

	function a11yOption(el){
		var g=el.dataset.group, v=el.dataset.value;
		a11yPrefs[g]=a11yPrefs[g]===v ? null : v;
		a11yApplyUnderline();
		a11yUpdateButtons();
		a11ySave();
	}

	function a11yReset(){
		a11yPrefs={underline:null,'focus-width':null,'focus-color':null};
		a11yApplyUnderline();
		a11yUpdateButtons();
		a11ySave();
	}

	/* LOAD SAVED PREFS */

	try{
		var raw=localStorage.getItem(a11yStore);
		if(raw) a11yPrefs=JSON.parse(raw);
	}catch(e){}

	/* BIND FOCUS EVENTS */

	document.addEventListener('DOMContentLoaded',function(){
		a11yApplyUnderline();
		document.addEventListener('focus', function(e){
			a11yApplyFocus(e.target);
		}, true);
		document.addEventListener('blur', function(e){
			a11yClearFocus(e.target);
		}, true);
		document.querySelectorAll('.a11y-option').forEach(function(btn){
			btn.addEventListener('click',function(){a11yOption(this);});
		});
		var reset=document.getElementById('a11y-reset');
		if(reset) reset.addEventListener('click',a11yReset);
		a11yUpdateButtons();
	});

</script>

<?php if (!is_page(48682)) return; ?>

<style>

	/* == SETTINGS PANEL == */

	#a11y-panel {background: var(--color-8); border: 1px solid var(--color-5); transition: background 0.2s ease; border-radius: 25px; padding: 25px; max-width: 350px; box-shadow: 6px 6px 9px rgba(0, 0, 0, 0.25);}
	#a11y-panel h1 {font-size: 1.5rem; margin: 0 0 25px; display: flex; align-items: center; gap: 10px;}
	#a11y-panel h1 img {width: 30px; height: 30px;}
	body.dark-mode #a11y-panel {background: var(--color-10); border: 1px solid var(--color-4); box-shadow: none;}

	.a11y-section {margin-bottom: 25px;}

	.a11y-label {font-weight: bold; text-transform: uppercase; margin-bottom: 10px;}

	.a11y-options {display: flex; flex-wrap: wrap; gap: 8px;}
	.a11y-option {background: var(--color-7); border: 1px solid var(--color-5); border-radius: 6px; padding: 7px 12px; font-size: .9rem; cursor: pointer; color:var(--color-3); transition: background .15s ease; border-color .15s ease;}
	.a11y-option:hover {background: var(--color-5);}
	.a11y-option.is-active {background: var(--color-1); border-color: var(--color-1); color: var(--color-8); font-weight: bold;}
	.a11y-option:focus-visible {outline: 2px solid var(--color-1); outline-offset: 2px;}
	.a11y-option.swatch {width: 32px; height: 32px; padding: 0; border-radius: 50%; border: 2px solid var(--color-5);}
	.a11y-option.swatch.is-active {outline: 3px solid var(--color-1) !important; outline-offset: var(--a11y-focus-offset);}

	#a11y-reset {display: inline-block; margin-top: 10px; background: none; border: none; color:var(--color-1); font-weight: bold; cursor: pointer; padding: 0;}
	#a11y-reset:hover {color: var(--color-2);}

</style>

<?php

},20);
