<?php

add_action('wp_head', function() {
  ?>

  <style>
    /* Dark-Light Mode Switch */
    #dark-mode-toggle-btn{align-items: center; padding-top: 2.5px; padding-left: 2.5px;}
    #dark-mode-toggle-btn input[type='checkbox']{display: none;}
    #dark-mode-toggle-btn .toggle-visual{background: #3A4F66; border: 1px solid #192a3d; border-radius: 50px; cursor: pointer; display: inline-block; position: relative; transition: all ease-in-out 0.3s; width: 50px; height: 25px;}
    #dark-mode-toggle-btn .toggle-visual::after{background: #192a3d; border-radius: 50%; content: ''; cursor: pointer; display: inline-block; position: absolute; left: 1px; top: 1px; transition: all ease-in-out 0.3s; width: 21px; height: 21px;}
    #dark-mode-toggle-btn input[type='checkbox']:checked+.toggle-visual{background: #0f1924; border-color: #3A4F66;}
    #dark-mode-toggle-btn input[type='checkbox']:checked+.toggle-visual::after{background: #3A4F66; transform: translateX(25px);}
    .dark-mode-toggle-btn-accessibility-label,.tts-toggle-btn-accessibility-label{position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;}
  </style>

  <script>
(function() {
  try {
    const storedPreference = localStorage.getItem('changeMode');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (storedPreference === 'true' || (storedPreference === null && prefersDark)) {
      document.documentElement.className += ' dark-mode-loading';
    }
  } catch(e) {}
})();
</script>

<style>
html.dark-mode-loading body {background: #0d0d0d; color: #bfbfbf;}
</style>
  <?php
});

add_action('wp_footer', function () {
  ?>
  <style>
    /* Dark Mode Basics */
    body.dark-mode {background: #0d0d0d; color: #bfbfbf;}
    body.dark-mode .toggle-mode {color: #bfbfbf !important;}
    body.dark-mode :is(h1, h2, h3, h4, h5, h6) {color: #bfbfbf;}
	
    /* Theme related: Blocksy */
    body.dark-mode[data-header*="type-1"] .ct-header [data-row*="middle"] {background: rgba(0,0,0,0.75);}
	body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-title {--linkInitialColor: #ffffff; --linkHoverColor: #bfbfbf;}
	body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-description {color: #bfbfbf;}
	body.dark-mode[data-header*="type-1"] .ct-header [data-id="menu"] > ul > li > a {--linkInitialColor: #ffffff; --linkHoverColor: #bfbfbf;}
	body.dark-mode .ct-header-search {--linkInitialColor: #ffffff; --linkHoverColor: #bfbfbf;}
	body.dark-mode .ct-search-results {background: rgba(0, 0, 0, 0.9); -webkit-box-shadow: none; box-shadow: none;}
	body.dark-mode[data-header*="type-1"] [data-id="trigger"] {--icon-color: #ffffff; --icon-hover-color: #bfbfbf;}
	body.dark-mode aside[data-type='type-4']:after {background: #0d0d0d;}
    body.dark-mode .single-query .ct-query-template-grid {background: #1a1a1a; border: 1px solid #1a1a1a;}
	body.dark-mode .single-query .ct-query-template-grid:hover {background: #0d0d0d;}
    body.dark-mode .single-query .ct-query-template.is-layout-slider {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .single-query .ct-query-template.is-layout-slider:hover {background: #0d0d0d;}
    body.dark-mode .wp-block-term.is-layout-flow {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .wp-block-term.is-layout-flow:hover {background: #0d0d0d;}
	body.dark-mode .ct-related-posts-container {border-top: 1px solid #3A4F66 !important;}
	body.dark-mode article>.ct-related-posts {border-top: 1px solid #3A4F66;}
	body.dark-mode .nav-item-prev {color: #bfbfbf;}
	body.dark-mode .nav-item-next {color: #bfbfbf;}
	body.dark-mode .post-navigation [class*='nav-item'] {color: #1e73be;}
	body.dark-mode .post-navigation [class*='nav-item']:hover {color: #c53030;}
	body.dark-mode .post-navigation:after {background: #3A4F66;}
	body.dark-mode .ct-popup-inner > article {background: #0d0d0d;}
	body.dark-mode .ct-popup-inner > article p {color: #bfbfbf;}
    body.dark-mode .ct-sidebar .ct-widget {color: #bfbfbf;}
	
    /* Display Posts Shortcode */
    body.dark-mode .display-posts-listing .listing-item {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .display-posts-listing .listing-item:hover {background: #0d0d0d;}
    body.dark-mode .display-posts-listing.grid#small-version .listing-item {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .display-posts-listing.grid#small-version .listing-item:hover {background: #0d0d0d;}
    body.dark-mode .display-posts-trending .listing-item {background: none; border: none;}
    body.dark-mode .image-bedps {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .image-bedps:hover {background: #0d0d0d;}
	
    /* Accordions */
    body.dark-mode .details-accordion summary {border-top: 1px solid #404040; border-bottom: 1px solid #404040;}
    body.dark-mode .details-accordion summary:hover, body.dark-mode .details-accordion summary:focus {background: #1a1a1a;}
	
    /* Tag Clouds */
    body.dark-mode .tag-cloud a {background: #1a1a1a;}
    body.dark-mode .tag-cloud a:hover {background: none;}
	
    /* Select Dropdowns & Form Inputs */
    body.dark-mode select {background: #1a1a1a; color: #bfbfbf; border: 1px solid #404040;}
    body.dark-mode select:focus {border: 1px solid #404040;}
    body.dark-mode option {background: #1a1a1a; color: #bfbfbf;}
    body.dark-mode input[type=search] {background: #1a1a1a; color: #bfbfbf; border: 1px solid #404040;}
    body.dark-mode input[type=search]:is(:visited,:hover,:focus,:active) {border: 1px solid #404040;}
    body.dark-mode input[type=email] {color: #bfbfbf; border: 1px solid #404040;}
    body.dark-mode input[type=search].modal-field {border: none; border-bottom: 1px solid #bfbfbf;}
    body.dark-mode input[type=search].modal-field:is(:visited,:hover,:focus,:active) {border: none; border-bottom: 1px solid #bfbfbf;}
	
    /* Tables */
    body.dark-mode .wp-block-table thead {background-color: #1a1a1a; border: 1px solid #262626;}
    body.dark-mode .wp-block-table thead {border-bottom: 3px solid #262626;}
    body.dark-mode .wp-block-table th {border: 1px solid #262626;}
    body.dark-mode .wp-block-table tr {border: 1px solid #262626;}
    body.dark-mode .wp-block-table td {border: 1px solid #262626;}
    body.dark-mode .wp-block-table tr:hover {background-color: #1a1a1a;}
	
    /* Boxes */
    body.dark-mode .text-box {background: #1a1a1a;} body.dark-mode .text-box:hover {background: #000000;}
    body.dark-mode .box-background {background: #1a1a1a; border: 1px solid #1a1a1a;} body.dark-mode .box-background:hover {background: #0d0d0d;}
    body.dark-mode .box-shadow {-webkit-box-shadow: none; box-shadow: none;}
	
    /* Various Elements */
    body.dark-mode .image-invert {-webkit-filter: invert(1); filter: invert(1);}
    body.dark-mode .wp-image-122531 {content: url("https://ericroth.org/wp-content/uploads/2024/07/SBB_NEG_2F_RGB_100.svg");} /*Swap SBB logo in My World*/
    body.dark-mode .wp-image-148107 {content: url("https://ericroth.org/wp-content/uploads/2025/07/github-mark-white.png");} /*Swap Github logo in HTML, CSS, JS & Co.*/
    body.dark-mode .cat-prefix {color: #bfbfbf;} body.dark-mode .cat-links {color: #bfbfbf;}
    body.dark-mode .wp-block-separator:not(.is-style-dots) {height: 1px; background: #3A4F66;}
    body.dark-mode .card-counter {color: #f2f2f2;}
    body.dark-mode .wp-block-group.has-border-color {border-color: #262626 !important;}
    body.dark-mode .emphasized-design-red {color: #cc0044;}
    body.dark-mode .font-design-red {color: #cc0044;}
    body.dark-mode code {background: none;}
	body.dark-mode .text-column-front {background: #1a1a1a !important;}
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
    const changeModeSwitch = document.getElementById('change-mode-switch');
    const changeModeButton = document.getElementById('change-mode-button');
    const visualToggle = document.querySelector('#dark-mode-toggle-btn .toggle-visual');
    const changeMode = () => {
        const isDark = document.body.classList.toggle('dark-mode');
        if (changeModeSwitch) changeModeSwitch.checked = isDark;
        try {
        localStorage.setItem('changeMode', isDark);
        } catch (e) {
        console.warn('LocalStorage unavailable');
        }
    };
    const applyAccessibility = (el) => {
        if (!el) return;
        el.setAttribute('role', 'switch');
        el.setAttribute('aria-checked', document.body.classList.contains('dark-mode'));
        el.setAttribute('tabindex', '0');
        el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            el.click();
        }
        });
        el.addEventListener('click', () => {
        el.setAttribute('aria-checked', document.body.classList.contains('dark-mode'));
        });
    };
    applyAccessibility(changeModeSwitch);
    applyAccessibility(changeModeButton);
    if (changeModeSwitch) {
        changeModeSwitch.addEventListener('change', changeMode);
    }
    if (changeModeButton) changeModeButton.addEventListener('click', changeMode);
    if (visualToggle) {
        visualToggle.addEventListener('click', () => {
        if (changeModeSwitch) {
            changeModeSwitch.checked = !changeModeSwitch.checked;
        }
        changeMode();
        });
    }
    // Apply Dark Mode and remove Loading Class
    document.documentElement.classList.remove('dark-mode-loading');
    const storedPreference = localStorage.getItem('changeMode');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (storedPreference === 'true' || (storedPreference === null && prefersDark)) {
        document.body.classList.add('dark-mode');
        if (changeModeSwitch) changeModeSwitch.checked = true;
    }
    });
  </script>
  <?php
});
