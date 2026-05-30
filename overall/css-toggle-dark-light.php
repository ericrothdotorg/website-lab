<?php
// NOTE: When in mu-plugins, add: defined('ABSPATH') || exit;

// ======================================
// CRITICAL CSS - ABOVE THE FOLD
// ======================================

add_action("wp_head", function () {
	?>
	<style>
	
	/* === COLOR PALETTE === */
	
	:root {
		--color-1:  #1e73be; /* Link, Button, Focus Outline, Toggle Accent */
		--color-2:  #c53030; /* Hover State for Link & Button */
		--color-3:  #3a4f66; /* LIGHT Mode Body Text, DARK Mode Element Border I */
		--color-4:  #192a3d; /* LIGHT Mode Headings, DARK Mode Element Border II */
		--color-5:  #e1e8ed; /* LIGHT Mode Separator & Border, DARK Mode Body Text */
		--color-6:  #070c12; /* DARK Mode Background & Element Hover */
		--color-7:  #fafbfc; /* LIGHT Mode Background & Element Hover, DARK Mode Heading */
		--color-8:  #ffffff; /* LIGHT Mode Element Background, Button Text, Form Field */
		--color-9:  #8da6b9; /* Design Block Border, Flexy Arrow, Tag & Dropdown Separator */
		--color-10: #0e1825; /* DARK Mode Element-, Box- and Card Background */
		
		--a11y-focus-color: var(--color-1); /* Accessibility Focus Color */
		--a11y-focus-width: 1px; /* Accessibility Focus Width */
		--a11y-focus-offset: 2px; /* Accessibility Focus Offset */
	}
	
	/* === THEME RELATED === */
	
	/* Header Container: Image */
	.hero-section .ct-media-container {border-bottom-right-radius: 15vw;}
	
	/* Header Container: Layer */
	.page-title,
	.page-description,
	.ct-breadcrumbs {background: rgba(0, 0, 0, 0.65);}
	.entry-header .page-title {border-radius: 10px 10px 0 0;}
	.entry-header .ct-breadcrumbs {border-radius: 0 0 10px 10px;}
	
	/* Header Container: Content */
	.page-title {padding: 10px 20px; color: var(--color-8);}
	.page-description {padding: 0 20px 20px; color: var(--color-5);}
	.ct-breadcrumbs {padding: 0 20px 20px; color: var(--color-5);}
	
	/* === NOT THEME RELATED === */
	
	/* FRONTPAGE VIDEO */
	
	/* .wp-block-cover.alignfull video {animation: kenburns 15s ease-in-out infinite alternate;}
	@keyframes kenburns {from {transform: scale(1);} to {transform: scale(1.25);}} */
	
	/* NAVIGATION */
	
	a:link {font-weight: bold; color: var(--color-1);}
	a:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	a:focus:not(:focus-visible) {outline: none;}
	
	/* HTML & BODY BASICS */
	
	html {text-size-adjust: 100%; scroll-behavior: smooth;}
	body {background: var(--color-7); color: var(--color-3);}
	body :is(h1, h2, h3, h4, h5, h6) {color: var(--color-4);}
	p {text-align: justify; hyphens: auto;}
	code {background: none !important; border: none; padding: 0;}
	body.dark-mode code {background: none;}
	
	/* CORE LAYOUT UTILITIES */
	
	.row::after {content: ""; display: table; clear: both;}
	.clearfix::after {content: ""; clear: both; display: table;}
	
	/* COLUMNS */
	
	.two-columns, .four-columns { float: left; }
	.two-columns { width: 49%; }
	.two-columns:nth-child(odd) { margin-right: 1%; }
	.two-columns:nth-child(even) { margin-left: 1%; }
	@media (max-width: 768px) {
		.two-columns:nth-of-type(2),
		.two-columns-var:nth-of-type(2),
		.four-columns:nth-of-type(n+3) {margin-top: 25px;}
		.two-columns {width: 100%;}
		.four-columns {width: 49%;}
	}
		@media (max-width: 600px) {
		.block-editor-two-columns {margin-top: 50px;}
	}
	
	/* FLEXBOX */
	
	.flex-container {display: flex; align-items: center; flex-flow: row wrap;}
	.flex-container.left-align {justify-content: flex-start;}
	.flex-container.center-align {justify-content: center;}
	
	</style>

	<!-- DARK MODE - FLASH PREVENTION -->

	<script>
(function() {
  try {
	const storedPreference = localStorage.getItem('changeMode');
	const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	if (storedPreference === 'true' || (storedPreference === null && prefersDark)) {
	  document.documentElement.style.background = '#070c12'; // Keep here too (as hardcoded Color 6 cuz CSS vars don't work in JS) to prevent Flash
	  document.documentElement.classList.add('dark-mode-preload');
	}
  } catch(e) {
	console.warn('Dark mode initialization failed:', e);
  }
})();
	</script>
	
	<?php
}, 5); // Load critical Styles in Head early

// ======================================
// NON-CRITICAL CSS - BELOW THE FOLD
// ======================================

add_action("wp_footer", function () {
	?>
	<style>
	
	/* === THEME RELATED === */
	
	/* Global Elements */
	.footer-breadcrumbs {background: none; margin-left: -20px;}
	@media (min-width: 992px) {.footer-breadcrumbs {margin-top: -25px;}}

	/* === INJECTED STUFF === */
	
	.search-pattern-inject  { margin-top: -125px; margin-bottom: 25px; } /* Inject 'Random Content (EN)' in Search Page */
	.home-pattern-inject    { margin-top: -125px; margin-bottom: 25px; } /* Inject 'Random Content (EN)' in Posts Page */
	.archive-pattern-inject { margin-top: -25px;  margin-bottom: 25px; } /* Inject 'Random Content (EN)' in (Pseudo-)Archive Page */	
	
	/* Custom Sidebar Breakpoint */
	@media (max-width: 1400px) {
		[data-sidebar] {display: block !important;}
		[data-sidebar] > aside {display: none !important;}
		[data-sidebar] > * {width: 100% !important;}
	}
	
	/* Header */
	body.dark-mode[data-header*="type-1"] .ct-header [data-row*="middle"] {background: rgba(0, 0, 0, 0.75);}
	body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-title {--linkInitialColor: var(--color-8); --linkHoverColor: var(--color-5);}
	body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-description {color: var(--color-5);}
	body.dark-mode[data-header*="type-1"] .ct-header [data-id="menu"] > ul > li > a {--linkInitialColor: var(--color-8); --linkHoverColor: var(--color-5);}
	body.dark-mode[data-header*="type-1"] [data-id="trigger"] {--icon-color: var(--color-8); --icon-hover-color: var(--color-5);}
	
	/* Sidebar */
	body.dark-mode aside[data-type='type-4']:after {background: var(--color-6);}
	body.dark-mode .ct-sidebar .ct-widget {color: var(--color-5);}
	
	/* Query Templates */
	.single-query .ct-query-template-grid,
	.single-query .ct-query-template.is-layout-slider {
		border: 1px solid var(--color-5);
		border-radius: 25px;
	}
	.single-query .ct-query-template-grid:hover,
	.single-query .ct-query-template.is-layout-slider:hover {background: var(--color-7);}
	.single-query .ct-dynamic-data {padding-bottom: 16px;}
	body.dark-mode .single-query .ct-query-template-grid {background: var(--color-10); border: 1px solid var(--color-10);}
	body.dark-mode .single-query .ct-query-template-grid:hover {background: var(--color-6);}
	body.dark-mode .single-query .ct-query-template.is-layout-slider {background: var(--color-10); border: 1px solid var(--color-10);}
	body.dark-mode .single-query .ct-query-template.is-layout-slider:hover {background: var(--color-6);}
	
	/* Taxonomy Blocks */
	.wp-block-term.is-layout-flow .ct-dynamic-data {padding-bottom: 16px;}
	.wp-block-term.is-layout-flow {
		background: var(--color-8);
		border: 1px solid var(--color-5);
		border-radius: 25px;
	}
	.wp-block-term.is-layout-flow:hover {background: var(--color-7);}
	body.dark-mode .wp-block-term.is-layout-flow {background: var(--color-10); border: 1px solid var(--color-10);}
	body.dark-mode .wp-block-term.is-layout-flow:hover {background: var(--color-6);}
	
	/* Media Styling */
	.ct-media-container img:not(.hero-section img),
	.ct-media-container picture:not(.hero-section picture) {border-radius: 25px;}
	.ct-dynamic-media-inner[data-hover="zoom-in"] {border-radius: 25px 25px 0 0 !important;}
	
	/* Grid Layouts */
	@media (min-width: 1024px) {.grid-four-columns .ct-query-template-grid {grid-template-columns: repeat(4, 1fr);}}
	@media (min-width: 600px) and (max-width: 1024px) {.ct-related-posts .flexy-container {--grid-columns-width: calc(100% / 3);}}
	@media (max-width: 600px) {.ct-related-posts .flexy-container {--grid-columns-width: calc(100% / 2);}}
	
	/* Related Content */
	.ct-related-posts-container {border-top: 1px solid var(--color-5) !important; max-width: 1290px; margin: 0 auto;}
	body.dark-mode .ct-related-posts-container {border-top: 1px solid var(--color-3) !important;}
	body.dark-mode article > .ct-related-posts {border-top: 1px solid var(--color-3);}
	
	/* Navigation Arrows */
	.flexy-arrow-next, .flexy-arrow-prev {
		width: 30px;
		height: 30px;
		transform: none;
		opacity: 0.75;
		background: var(--color-9);
		color: var(--color-8);
		position: absolute;
		top: -55px;
	}
	.flexy-arrow-next:hover, .flexy-arrow-prev:hover {opacity: 1; background: var(--color-9);}
	.flexy-arrow-next:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	.flexy-arrow-next:focus:not(:focus-visible) {outline: none;}
	.flexy-arrow-prev:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	.flexy-arrow-prev:focus:not(:focus-visible) {outline: none;}
	.flexy-arrow-next {right: 10px; left: auto;}
	.flexy-arrow-prev {right: 60px; left: auto;}
	
	/* === NOT THEME RELATED === */
	
	/* DARK MODE - TOGGLE BUTTON */
	
	#dark-mode-toggle-btn {display: flex; align-items: center; gap: 10px;}
	#dark-mode-toggle-btn img {opacity: 0.5;}
	#dark-mode-toggle-btn input[type='checkbox'] {
	  position: absolute;
	  opacity: 0;
	  width: 0;
	  height: 0;
	}
	#dark-mode-toggle-btn input[type='checkbox']:focus-visible + .toggle-visual {
	  outline: 1px solid var(--color-1);
	  outline-offset: 2px;
	}
	#dark-mode-toggle-btn .toggle-visual {
	  background: var(--color-3);
	  border: 1px solid var(--color-4);
	  border-radius: 50px;
	  cursor: pointer;
	  display: inline-block;
	  position: relative;
	  transition: all ease-in-out 0.3s;
	  width: 50px;
	  height: 25px;
	}
	#dark-mode-toggle-btn .toggle-visual::after {
	  background: var(--color-4);
	  border-radius: 50%;
	  content: '';
	  cursor: pointer;
	  display: inline-block;
	  position: absolute;
	  left: 1px;
	  top: 1px;
	  transition: all ease-in-out 0.3s;
	  width: 21px;
	  height: 21px;
	}
	#change-mode-switch:checked + .toggle-visual {background: var(--color-10); border-color: var(--color-3);}
	#change-mode-switch:checked + .toggle-visual::after {background: var(--color-3); transform: translateX(25px);}
	
	/* Accessibility Labels */
	#dark-mode-status, .dark-mode-toggle-btn-accessibility-label {
	  position: absolute;
	  left: -9999px;
	  width: 1px;
	  height: 1px;
	  overflow: hidden;
	}
	
	/* DARK MODE - BASICS */
	
	body.dark-mode {background: var(--color-6); color: var(--color-5);}
	body.dark-mode :is(h1, h2, h3, h4, h5, h6) {color: var(--color-7);}
	
	/* DARK MODE - MISC */
	
	body.dark-mode :is(.cat-prefix, .cat-links) {color: var(--color-5);}
	body.dark-mode .wp-block-group.has-border-color {border-color: var(--color-4) !important;}
	
	/* BRANDING */
	
	/* Site Logo (Without Text and rotate 3D) */
	.site-logo {animation: rotate3d 5s linear infinite;}
	.site-logo:hover {animation-play-state: paused;}
	@keyframes rotate3d {
		from {transform: rotate3d(0, 0, 0, 0deg);}
		to {transform: rotate3d(1, 1, 1, 360deg);}
	}
	
	/* Site Logo (With Text and rotate 2D) */
	.octagon-text-outside {
		animation-name: rotate-image-frontpage;
		animation-duration: 15s;
		animation-iteration-count: 1;
	}
	.octagon-text-outside:hover {animation-play-state: paused;}
	@keyframes rotate-image-frontpage {
		0% {transform: rotate(0deg);}
		100% {transform: rotate(360deg);}
	}
	
	/* SEPARATOR */
	
	.wp-block-separator, .separator-75 {max-width: 75%;} /* Only works if in one Column */
	.wp-block-separator:not(.is-style-dots) {height: 1px; background: var(--color-5);}
	body.dark-mode .wp-block-separator:not(.is-style-dots) {height: 1px; background: var(--color-3);}
	
	/* NAVIGATION */
	
	a:link:hover {color: var(--color-2);}
	body.dark-mode .post-navigation [class*='nav-item'] {color: var(--color-1);}
	body.dark-mode .post-navigation [class*='nav-item']:hover {color: var(--color-2);}
	body.dark-mode .post-navigation:after {background: var(--color-3);}
	
	/* External Link Indicator (Functionality is in functions.php) */
	a.external-link::after {
		content: "";
		background: url("https://ericroth.org/wp-content/uploads/2024/03/external-link-greyblue.svg") no-repeat center;
		width: .75em;
		height: .75em;
		margin-left: .25em;
		display: inline-block;
	}
	
	/* Exclude from Page List Block (hide specific Navigation Items) */
	.wp-block-page-list.site-overview > li:nth-child(2),
	.wp-block-page-list.site-overview > li:nth-child(3),
	.wp-block-page-list.site-overview > li:nth-child(4),
	.wp-block-page-list.site-overview > li:nth-child(7),
	.wp-block-page-list.site-overview > li:nth-child(9) {display: none;}
	
	/* Navigate with Pages */
	.page-links {padding-bottom: 50px;}
	.page-links a, .page-links .current, .page-links .post-pages-label {border: none; font-size: 14px;}
	.page-links a {color: var(--color-1);}
	.page-links a:hover {box-shadow: 0 0 0 1px var(--color-1);}
	.page-links a:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	.page-links a:focus:not(:focus-visible) {outline: none;}
	.page-links .current {border-color: var(--color-1); background: var(--color-1); color: var(--color-8);}
	.post-pages-label {text-transform: uppercase;}
	
	/* TABLES */
	
	.wp-block-table thead {background: var(--color-7);}
	.wp-block-table tbody tr {background: var(--color-8);}
	.wp-block-table tr:hover {background: var(--color-7);}
	body.dark-mode .wp-block-table thead {background: var(--color-6); border: 1px solid var(--color-4); border-bottom: 3px solid var(--color-4);}
	body.dark-mode .wp-block-table th {border: 1px solid var(--color-4);}
	body.dark-mode .wp-block-table tr {border: 1px solid var(--color-4);}
	body.dark-mode .wp-block-table td {border: 1px solid var(--color-4);}
	body.dark-mode .wp-block-table tbody tr {background: var(--color-10);}
	body.dark-mode .wp-block-table tr:hover {background: var(--color-6);}
	
	/* TABS */
	
	body.page-id-17552 .tabs {overflow: hidden}
	body.page-id-17552 .tabs button {float: left; padding: 7.5px 10px; margin: 0px 2.5px; color: var(--color-1); font-weight: bold; background: var(--color-8); border: solid var(--color-5); border-width: 1px 1px 0 1px; border-radius: 5px 5px 0 0; cursor: pointer}
	body.page-id-17552 .tabs button:hover {color: var(--color-2)}
	body.page-id-17552 .tab-content {display: none; background: var(--color-8); border: 1px solid var(--color-5); border-radius: 5px 15px 15px 15px; padding: 2.5rem 1.5rem 2.5rem 2.5rem}
	body.dark-mode.page-id-17552 .tabs button {background: var(--color-10); border: solid var(--color-4); border-width: 1px 1px 0 1px}
	body.dark-mode.page-id-17552 .tab-content {background: var(--color-10); border: 1px solid var(--color-4)}
	
	/* DETAILS & SUMMARY */
	
	summary {color: var(--color-1); font-weight: bold; cursor: pointer;}
	summary:hover {color: var(--color-2);}
	summary:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	summary:focus:not(:focus-visible) {outline: none;}
	
	/* Details for Text etc. */
	.details-center summary {font-weight: 700; font-size: 20px; line-height: 1.5; text-align: center;}
	.details-left summary {font-weight: 700; font-size: 20px; line-height: 1.5;}
	.details-button summary {
		color: var(--color-8) !important;
		background: var(--color-1);
		display: flex;
		align-items: center;
		justify-content: center;
		margin: auto;
		padding: 10px 20px;
		border-radius: 4px;
		text-align: center;
		max-width: fit-content;
	}
	.details-button summary:hover {background: var(--color-2);}
	.details-button summary:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	.details-button summary:focus:not(:focus-visible) {outline: none;}
	
	/* Details for Accordion */
	.details-accordion {transition: margin-bottom 0.5s ease-in-out; margin-bottom: 0.5rem;}
	.details-accordion + .details-accordion {margin-top: -0.6rem;}
	.details-accordion[open] {padding-bottom: 0.75rem;}
	.details-accordion summary {
		display: flex;
		align-items: center;
		font-size: 1rem;
		line-height: 1.35;
		padding: 0.65rem 1rem;
		border-top: 1px solid var(--color-5);
		border-bottom: 1px solid var(--color-5);
	}
	.details-accordion summary {background: var(--color-8);}
	.details-accordion summary:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	.details-accordion summary:focus:not(:focus-visible) {outline: none;}
	.details-accordion summary > * {margin-left: 0.5rem;}
	.details-accordion summary > *:first-child {margin-left: 0;}
	.details-accordion summary::before {content: "+"; font-weight: bold; color: var(--color-1); margin-right: 0.5rem;}
	.details-accordion[open] summary::before {content: "−"; color: var(--color-1);}
	.details-accordion summary:hover {background: var(--color-7);}
	.details-accordion > :where(:not(summary)) {margin-left: 1.25rem; margin-right: 1.25rem;}
	body.dark-mode .details-accordion summary {background: var(--color-10); border-top: 1px solid var(--color-4); border-bottom: 1px solid var(--color-4);}
	body.dark-mode .details-accordion summary:hover,
	body.dark-mode .details-accordion summary:focus {background: var(--color-6);}
	
	/* TEXT & COLORS */
	
	/* Text Basics */
	.br{display: none;}
	@media (max-width: 600px) {.br {display: block;}}
	.font-color-white {color: var(--color-5);}
	.font-color-black {color: var(--color-3);}
	
	/* Text as Columns */
	.two-columns-text, .three-columns-text, .four-columns-text {
		column-gap: 40px;
		column-rule: 1px solid var(--color-3);
	}
	.two-columns-text {column-count: 2;}
	.three-columns-text {column-count: 3;}
	.four-columns-text {column-count: 4;}
	@media (max-width:768px) {.two-columns-text, .three-columns-text, .four-columns-text {column-count: 1; column-gap: 0; column-rule: none;}}
	body.dark-mode .two-columns-text,
	body.dark-mode .three-columns-text,
	body.dark-mode .four-columns-text {column-rule: 1px solid var(--color-5);}
	
	/* (Emphasized) Design Blocks */
	.emphasized-design-green, .emphasized-design-red, .emphasized-design-orange {
		font-style: italic;
		border-left: 1px solid var(--color-9);
		margin-left: 2em;
		padding-left: 2em;
	}
	.emphasized-design-green {color: #339966;} /* No var Color */
	.emphasized-design-red {color: #990033;} /* No var Color */
	.emphasized-design-orange {color: #ed7d31;} /* No var Color */
	.font-design-red {color: #990033;} /* No var Color */
	body.dark-mode .emphasized-design-red {color: #cc0044;} /* No var Color */
	body.dark-mode .font-design-red {color: #cc0044;} /* No var Color */
	
	/* BLOCKQUOTES */
	
	.wp-block-quote {
		position: relative;
		width: 85%;
		max-width: fit-content;
		margin: 35px auto 70px;
		padding: 0 25px;
		border-inline-start: 3px solid var(--color-5) !important;
		border-right: 3px solid var(--color-5);
		border-radius: 10px;
	}		  
	.wp-block-quote p, .wp-block-quote ul, .wp-block-quote li {
		text-align: justify;
		font-family: Georgia, serif;
		font-style: italic;
		color: #339966; /* No var Color */
		line-height: 1.6;
	}
	.wp-block-quote cite {position: absolute; right: 25px; font-family: sans-serif; font-size: 1rem; font-style: normal;}
	:root :where(.is-layout-flow) > :last-child.wp-block-quote {margin-block-end: 25px;}
	body.dark-mode .wp-block-quote {border-inline-start: 3px solid var(--color-3) !important; border-right: 3px solid var(--color-3);}
	body.dark-mode .my-quote-slide-content {background: var(--color-10); border: 1px solid var(--color-4);}
	
	/* Styling for Pullquotes */
	figure.wp-block-pullquote blockquote:before {margin-left: 25px;}
	figure.wp-block-pullquote {position: relative; max-width: fit-content; border: none;}
	.wp-block-pullquote blockquote {border-inline-start: none !important;}
	.wp-block-pullquote blockquote p {margin-left: 25px; text-align: left; font-family: Georgia, serif; font-weight: normal; color: #339966; line-height: 1.6;} /* No var Color */
	.wp-block-pullquote blockquote cite {position: absolute; right: 25px; font-family: sans-serif; font-size: 1rem; font-style: normal;}
	
	/* Styling for Quotes (except: My Quotes) */
	.quote-text p, .quote-text ul, .quote-text li {font-size: clamp(1rem, 1.25vw + 0.5rem, 1.25rem);}
	.quote-text ul, .quote-text li {margin-left: -20px;}
	
	/* TAG CLOUDS */
	
	.tag-cloud a {
		background: var(--color-8);
		font-weight: normal;
		line-height: 1.75;
		min-height: 25px;
		padding: 3px 15px;
		margin: 0 0 5px;
		border: 1px solid var(--color-1);
		border-radius: 100px;
		display: inline-block;
		transition: background 0.2s ease;
	}
	.tag-cloud a:hover,
	.tag-cloud a:focus-visible {background: none; color: var(--color-2);}
	.tag-cloud a:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	.tag-cloud a:focus:not(:focus-visible) {outline: none;}
	.left-align {text-align: left;}
	.tag-cloud-footer a {font-size: 16px !important;}
	.tag-cloud-footer .tag-separator {color: var(--color-9); padding-left: 5px; padding-right: 5px;}
	.tag-cloud-footer .tag-count {font-weight: normal; font-style: italic;}
	body.dark-mode .tag-cloud a {background: var(--color-10);}
	body.dark-mode .tag-cloud a:hover {background: none;}
	
	/* IMAGE STYLES & EFFECTS */
	
	/* Zoom */
	.zoom {width: 35%; transform-origin: center center; transition: transform 1.5s ease-in-out;}
	.zoom:hover {transform: scale(2.857);} /* 100% / 35% = 2.857 */
	.zoom-x2 {transition: transform 1.5s ease-in-out;}
	.zoom-x2:hover {transform: scale(2);}
	
	/* Blob Animation */
	.blob-animation img {animation: animate-blob 7.5s ease-in-out infinite alternate;}
	.blob-animation img:hover {animation-play-state: paused;}
	@keyframes animate-blob {
		0%, 100% {border-radius: 20% 80% 70% 30% / 60% 40% 60% 40%;}
		25% {border-radius: 60% 40% 30% 70% / 40% 60% 40% 60%;}
		50% {border-radius: 60% 40% 30% 70% / 60% 40% 60% 40%;}
		75% {border-radius: 20% 80% 70% 30% / 40% 60% 40% 60%;}
	}
	.blob-animation figcaption {font-size: 1.5em;}
	
	/* Dark Mode */
	body.dark-mode .image-invert {filter: invert(1);}
	body.dark-mode .wp-image-148107 {content: url("https://ericroth.org/wp-content/uploads/2025/07/github-mark-white.png");} /* Github logo in Happy Coding! */
	
	/* Imitate DPS - Bill Erickson */
	.image-bedps {clear: both; overflow: hidden; background: var(--color-8); border: 1px solid var(--color-5); border-radius: 25px;}
	.image-bedps:hover {background: var(--color-7);}
	.image-bedps img {aspect-ratio: 16 / 9; transition: transform 0.3s ease;}
	@supports not (aspect-ratio: 16 / 9) {
		.image-bedps img {width: 100%; height: auto;}
	}
	.image-bedps img:hover {transform: scale(1.05);}
	.image-bedps figcaption {margin-top: 16px; margin-bottom: 16px; font-size: 1.125rem; font-weight: bold;}
	body.dark-mode .image-bedps {background: var(--color-10); border: 1px solid var(--color-10);}
	body.dark-mode .image-bedps:hover {background: var(--color-6);}
	
	/* EMBEDS & VIDEO */
	
	.embed-responsively {position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;}
	.embed-responsively iframe, .embed-responsively object, .embed-responsively embed {position: absolute; top: 0; left: 0; width: 100%; height: 100%;}
	.is-provider-youtube.wp-embed-aspect-4-3 .wp-block-embed__wrapper::before,
	.is-provider-videopress.wp-embed-aspect-4-3 .wp-block-embed__wrapper::before,
	.is-provider-vimeo.wp-embed-aspect-4-3 .wp-block-embed__wrapper::before {padding-top: 56.25%;}
	.wp-block-video-osho video {vertical-align: middle; width: 75%;}
	
	/* BUTTONS */
	
	.wp-block-button__link, .button a, .smaller-button a {
		color: var(--color-8) !important;
		background: var(--color-1);
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 10px 20px;
		border-radius: 4px;
		text-align: center;
		text-decoration: none;
		border: 2px solid transparent;
		transition: background-color 0.2s ease, color 0.2s ease;
	}
	.wp-block-button__link:hover, .button a:hover, .smaller-button a:hover {background: var(--color-2); border: 2px solid transparent;}
	.wp-block-button__link:focus, .button a:focus, .smaller-button a:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	.wp-block-button__link:focus:not(:focus-visible), .button a:focus:not(:focus-visible), .smaller-button a:focus:not(:focus-visible) {outline: none;}
	.button a {margin: auto; max-width: fit-content;}
	.smaller-button a {cursor: pointer; min-height: 25px; padding: 5px 10px 7.5px;}
	
	/* BOXES */
	
	.text-box {overflow: hidden; padding: 25px; margin-bottom: 25px;}
	.box-background {background: var(--color-8); border: 1px solid var(--color-5); transition: background 0.2s ease;}
	.box-shadow {box-shadow: 6px 6px 9px rgba(0, 0, 0, 0.25);}
	.resizable-box {height: 333px; resize: vertical; overflow: auto; padding-right: 25px;}
	body.dark-mode .box-background {background: var(--color-10); border: 1px solid var(--color-4);}
	body.dark-mode .box-shadow {box-shadow: none;}
	
	/* FORMS & INPUTS */
	
	/* Base Styles */
	select,
	input[type=search],
	input[type=email],
	input[type=text],
	textarea {background: var(--color-8); border: 1px solid var(--color-5); transition: border 0.2s ease;}
	body.dark-mode select,
	body.dark-mode input[type=search],
	body.dark-mode input[type=email],
	body.dark-mode input[type=text],
	body.dark-mode textarea {background: var(--color-10); color: var(--color-5); border: 1px solid var(--color-3);}
	body.dark-mode option {background: var(--color-10); color: var(--color-5);}
	
	/* Focus States - Accessibility */
	select:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	select:focus:not(:focus-visible) {outline: none;}
	input[type=email]:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	input[type=email]:focus:not(:focus-visible) {outline: none;}
	input[type=text]:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	input[type=text]:focus:not(:focus-visible) {outline: none;}
	textarea:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	textarea:focus:not(:focus-visible) {outline: none;}
	input[type=search]:focus {outline: var(--a11y-focus-width) solid var(--a11y-focus-color); outline-offset: var(--a11y-focus-offset);}
	input[type=search]:focus:not(:focus-visible) {outline: none;}
	
	/* MANUAL LAYOUTS */
	
	@media (max-width: 768px) {.hide-on-small {display: none;}}
	@media (min-width: 768px) {.hide-on-big {display: none;}}
	.flex-item-country-icons {flex: 0 0 60px;} /* Used for Photo Album */
	
	/* FOOTER */
	
	/* Radius on Hover */
	#footer {transition: border-radius 1s ease;}
	#footer:hover {border-top-right-radius: 15vw;}
	
	/* Flex Container and Items */
	.flex-container {column-gap: 50px; row-gap: 25px;}
	.flex-item-footer-left,
	.flex-item-footer-middle,
	.flex-item-footer-right {
		max-width: 430px;
		flex: 1 1 auto;
	}
	
	/* Footer Column LEFT */
	.qr-and-links-wrapper {display: flex; align-items: center;}
	.qr-links-column {display: flex; flex-direction: column; padding-left: 45px;}
	.ninja-services-link {display: flex; align-items: center; margin-bottom: 15px;}
	.ninja-services-link span {padding-left: 10px;}
	
	/* Footer Column MIDDLE */
	.flex-item-footer-middle {margin-top: 20px;}
	.dropdown-separator {color: var(--color-9); padding-left: 10px; padding-right: 5px;}
	.logo-copyright-wrapper {display: flex; align-items: center; padding-top: 5px;}
	.site-logo {width: 50px; height: auto;}
	.copyright-text {padding-top: 25px; padding-left: 35px;}
	
	/* Hoverable Dropdown Menu */
	.dropdown {display: inline-block; position: relative;}
	.dropdown-content {
		display: none;
		position: absolute;
		padding: 7.5px 10px 10px;
		white-space: nowrap;
		background: #001a33; /* No var Color (same as Footer BG) */
		border: 1px solid var(--color-3);
		border-bottom-left-radius: 5px;
		border-bottom-right-radius: 5px;
		z-index: 1;
	}
	.dropdown:hover .dropdown-content, .dropdown:focus-within .dropdown-content {display: block;}
	
	/* Footer Column RIGHT */
	.site-updates-wrapper {display: flex; align-items: center; padding-top: 25px;}
	.site-updates-text {padding-left: 35px; margin: 0;}
	.content-text {color: #ed7d31;} /* No var Color */
	
	/* Animate Footer Text */
	.content-text::before {content: "content"; animation: content-words 10s linear infinite;}
	@keyframes content-words {
		9.09%  {content: " posts";}
		18.18% {content: " traits";}
		27.27% {content: " projects";}
		36.36% {content: " topics";}
		45.45% {content: " galleries";}
		54.54% {content: " feeds";}
		63.63% {content: " pages";}
		72.72% {content: " things";}
		81.81% {content: " visitors";}
		90.90% {content: " quotes";}
	}
	
	/* USER'S MOTION PREFERENCES */
	
	@media (prefers-reduced-motion: reduce) {
		.site-logo,
		.octagon-text-outside,
		.blob-animation img,
		.animate__animated,
		.er-social-link-icon,
		.content-text::before {
			animation: none !important;
			transition: none !important;
			transform: none !important;
		}
	}
	
	/* er-social-link-icon */
	@media (prefers-reduced-motion: no-preference) {.er-social-link-icon {transition: transform 0.1s ease;} .er-social-link-icon:hover {transform: scale(1.1);}}
	
	</style>

    <!-- DARK MODE - TOGGLE -->

    <script>
	  document.addEventListener('DOMContentLoaded', () => {
	    const body = document.body;
	    const changeModeSwitch = document.getElementById('change-mode-switch');
	    const changeModeButton = document.getElementById('change-mode-button');
	    const visualToggle = document.querySelector('#dark-mode-toggle-btn .toggle-visual');
	    const statusEl = document.getElementById('dark-mode-status');
	  
	    // Apply Dark Mode from localStorage
	    try {
		  const storedPreference = localStorage.getItem('changeMode');
		  if (storedPreference === 'true') {
		    body.classList.add('dark-mode');
		  }
	    } catch (e) {
		  console.warn('Could not load dark mode preference');
	    }
	  
	    // Remove preload Class (added in wp_head to prevent Flash; no longer needed once Body Class is set)
	    document.documentElement.classList.remove('dark-mode-preload');
	  
	    // Sync Toggle with current State
	    const isDark = body.classList.contains('dark-mode');
	    if (changeModeSwitch) {
		  changeModeSwitch.checked = isDark;
		  changeModeSwitch.setAttribute('aria-checked', isDark);
	    }
	    // Toggle Function
	    const changeMode = () => {
		  const isDark = body.classList.toggle('dark-mode');
		  if (changeModeSwitch) {
		    changeModeSwitch.checked = isDark;
		    changeModeSwitch.setAttribute('aria-checked', isDark);
		  }
		  if (changeModeButton) {
		    changeModeButton.setAttribute('aria-checked', isDark);
		  }
		  if (statusEl) {
		    statusEl.textContent = isDark ? 'Dark mode enabled' : 'Light mode enabled';
		  }
		  try {
		    localStorage.setItem('changeMode', isDark ? 'true' : 'false');
		  } catch (e) {
		    console.warn('LocalStorage unavailable');
		  }
	    };
	  
	    // Accessibility Setup
	    const addAccessibility = (el) => {
		  if (!el) return;
		  el.setAttribute('role', 'switch');
		  el.setAttribute('aria-checked', body.classList.contains('dark-mode'));
		  el.setAttribute('tabindex', '0');
		  el.addEventListener('keydown', (e) => {
		    if (e.key === 'Enter' || e.key === ' ') {
			  e.preventDefault();
			  changeMode();
		    }
		  });
	    };
	    addAccessibility(changeModeSwitch);
	    addAccessibility(changeModeButton);
	    if (visualToggle) {
		  visualToggle.setAttribute('aria-hidden', 'true');
	    }
	  
	    // Event Listeners
	    if (changeModeSwitch) {
		  changeModeSwitch.addEventListener('change', changeMode);
	    }
	    if (changeModeButton) {
		  changeModeButton.addEventListener('click', changeMode);
	    }
	    if (visualToggle) {
		  visualToggle.addEventListener('click', () => {
		    if (changeModeSwitch) {
			  changeModeSwitch.checked = !changeModeSwitch.checked;
		    }
		    changeMode();
		  });
	    }
	  });
    </script>
    
	<?php
}, 15); // Load deferred Footer Styles after Theme defaults
