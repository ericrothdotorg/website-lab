<?php
defined('ABSPATH') || exit;

add_action('wp_head', function() {
  ?>
  <style>
	/* Dark Mode Toggle Button */
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

	/* Dark Mode Accessibility Labels */
	#dark-mode-status, .dark-mode-toggle-btn-accessibility-label {
	  position: absolute;
	  left: -9999px;
	  width: 1px;
	  height: 1px;
	  overflow: hidden;
	}
  </style>

  <script>
(function() {
  try {
    const storedPreference = localStorage.getItem('changeMode');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (storedPreference === 'true' || (storedPreference === null && prefersDark)) {
      document.documentElement.style.background = '#070c12'; // Keep here too (as hardcoded Color 6 cuz CSS vars don't work in JS) to prevent Flash
    }
  } catch(e) {
    console.warn('Dark mode initialization failed:', e);
  }
})();
</script>
  <?php
});

add_action('wp_footer', function () {
  ?>
  <style>
    /* Dark Mode Basics */
    body.dark-mode {background: var(--color-6); color: var(--color-5);}
    body.dark-mode :is(h1, h2, h3, h4, h5, h6) {color: var(--color-7);}
    
    /* == THEME RELATED == */
    
    /* Header */
    body.dark-mode[data-header*="type-1"] .ct-header [data-row*="middle"] {background: rgba(0, 0, 0, 0.75);}
    body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-title {--linkInitialColor: var(--color-8); --linkHoverColor: var(--color-5);}
    body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-description {color: var(--color-5);}
    body.dark-mode[data-header*="type-1"] .ct-header [data-id="menu"] > ul > li > a {--linkInitialColor: var(--color-8); --linkHoverColor: var(--color-5);}
    body.dark-mode .ct-header-search {--linkInitialColor: var(--color-8); --linkHoverColor: var(--color-5);}
    body.dark-mode[data-header*="type-1"] [data-id="trigger"] {--icon-color: var(--color-8); --icon-hover-color: var(--color-5);}
    
    /* Search Results */
    body.dark-mode .ct-search-results {background: rgba(0, 0, 0, 0.9); box-shadow: none;}
    
    /* Sidebar */
    body.dark-mode aside[data-type='type-4']:after {background: var(--color-6);}
    body.dark-mode .ct-sidebar .ct-widget {color: var(--color-5);}
    
    /* Query Templates */
    body.dark-mode .single-query .ct-query-template-grid {background: var(--color-10); border: 1px solid var(--color-10);}
    body.dark-mode .single-query .ct-query-template-grid:hover {background: var(--color-6);}
    body.dark-mode .single-query .ct-query-template.is-layout-slider {background: var(--color-10); border: 1px solid var(--color-10);}
    body.dark-mode .single-query .ct-query-template.is-layout-slider:hover {background: var(--color-6);}
    
    /* Related Posts */
    body.dark-mode .ct-related-posts-container {border-top: 1px solid var(--color-3) !important;}
    body.dark-mode article > .ct-related-posts {border-top: 1px solid var(--color-3);}
    
    /* Post Navigation */
    body.dark-mode .nav-item-prev {color: var(--color-5);}
    body.dark-mode .nav-item-next {color: var(--color-5);}
	
    /* == NOT THEME RELATED == */
	
    /* Post Navigation */
    body.dark-mode .post-navigation [class*='nav-item'] {color: var(--color-1);}
    body.dark-mode .post-navigation [class*='nav-item']:hover {color: var(--color-2);}
    body.dark-mode .post-navigation:after {background: var(--color-3);}
	
    /* Taxonomy Blocks */
    body.dark-mode .wp-block-term.is-layout-flow {background: var(--color-10); border: 1px solid var(--color-10);}
    body.dark-mode .wp-block-term.is-layout-flow:hover {background: var(--color-6);}
	
    /* DPS - Bill Erickson */
    body.dark-mode .display-posts-listing .listing-item {background: var(--color-10); border: 1px solid var(--color-10);}
    body.dark-mode .display-posts-listing .listing-item:hover {background: var(--color-6);}
    body.dark-mode .display-posts-listing.grid#small-version .listing-item {background: var(--color-10); border: 1px solid var(--color-10);}
    body.dark-mode .display-posts-listing.grid#small-version .listing-item:hover {background: var(--color-6);}
    body.dark-mode .display-posts-trending .listing-item {background: none; border: none;}
    body.dark-mode .image-bedps {background: var(--color-10); border: 1px solid var(--color-10);}
    body.dark-mode .image-bedps:hover {background: var(--color-6);}
    
    /* ACCORDIONS */
    body.dark-mode .details-accordion summary {border-top: 1px solid var(--color-3); border-bottom: 1px solid var(--color-3);}
    body.dark-mode .details-accordion summary:hover, body.dark-mode .details-accordion summary:focus {background: var(--color-10);}
    
    /* TAG CLOUDS */
    body.dark-mode .tag-cloud a {background: var(--color-10);}
    body.dark-mode .tag-cloud a:hover {background: none;}
    
	/* FORMS & INPUTS */

	/* Base Styles */
	body.dark-mode select,
	body.dark-mode input[type=search],
	body.dark-mode input[type=email],
	body.dark-mode input[type=text],
	body.dark-mode textarea {background: var(--color-10); color: var(--color-5); border: 1px solid var(--color-3);}
	body.dark-mode option {background: var(--color-10); color: var(--color-5);}

	/* Focus States - Keyboard Accessibility */
	body.dark-mode select:focus {outline: 1px solid var(--color-1); outline-offset: 2px;}
	body.dark-mode select:focus:not(:focus-visible) {outline: none;}
	body.dark-mode input[type=email]:focus {outline: 1px solid var(--color-1); outline-offset: 2px;}
	body.dark-mode input[type=email]:focus:not(:focus-visible) {outline: none;}
	body.dark-mode input[type=text]:focus {outline: 1px solid var(--color-1); outline-offset: 2px;}
	body.dark-mode input[type=text]:focus:not(:focus-visible) {outline: none;}
	body.dark-mode textarea:focus {outline: 1px solid var(--color-1); outline-offset: 2px;}
	body.dark-mode textarea:focus:not(:focus-visible) {outline: none;}
	body.dark-mode input[type=search]:focus {outline: 1px solid var(--color-1); outline-offset: 2px;}
	body.dark-mode input[type=search]:focus:not(:focus-visible) {outline: none;}

	/* Exception: Modal Search Field */
	body.dark-mode input[type=search].modal-field {background: none; border: none; border-bottom: 1px solid var(--color-3); outline: none; transition: none;}
    
    /* TABLES */
    body.dark-mode .wp-block-table thead {background-color: var(--color-10); border: 1px solid var(--color-4); border-bottom: 3px solid var(--color-4);}
    body.dark-mode .wp-block-table th {border: 1px solid var(--color-4);}
    body.dark-mode .wp-block-table tr {border: 1px solid var(--color-4);}
    body.dark-mode .wp-block-table td {border: 1px solid var(--color-4);}
    body.dark-mode .wp-block-table tr:hover {background-color: var(--color-10);}
    
    /* BOXES */
    body.dark-mode .text-box {background: var(--color-10);}
    body.dark-mode .text-box:hover {background: var(--color-6);}
    body.dark-mode .box-background {background: var(--color-10); border: 1px solid var(--color-10);}
    body.dark-mode .box-background:hover {background: var(--color-6);}
    body.dark-mode .box-shadow {box-shadow: none;}
    
    /* VARIOUS ELEMENTS */
    
    /* Image Effects */
    body.dark-mode .image-invert {filter: invert(1);}
    
    /* Logo Swaps */
    body.dark-mode .wp-image-122531 {content: url("https://ericroth.org/wp-content/uploads/2024/07/SBB_NEG_2F_RGB_100.svg");} /* SBB logo in My World */
    body.dark-mode .wp-image-148107 {content: url("https://ericroth.org/wp-content/uploads/2025/07/github-mark-white.png");} /* Github logo in Happy Coding! */
    
    /* Text & Categories */
	body.dark-mode .two-columns-text, .three-columns-text, .four-columns-text {column-rule: 1px solid var(--color-5);}
    body.dark-mode .cat-prefix {color: var(--color-5);}
    body.dark-mode .cat-links {color: var(--color-5);}
	body.dark-mode .counter-body {color: var(--color-5);}
    
    /* Separators & Borders */
    body.dark-mode .wp-block-separator:not(.is-style-dots) {height: 1px; background: var(--color-3);}
    body.dark-mode .wp-block-group.has-border-color {border-color: var(--color-4) !important;}
    
    /* Emphasized Text */
    body.dark-mode .emphasized-design-red {color: #cc0044;}
    body.dark-mode .font-design-red {color: #cc0044;}
    
    /* Code & Columns */
    body.dark-mode code {background: none;}
    body.dark-mode .text-column-front {background: var(--color-10) !important;}
	body.dark-mode .my-quote-slide-content {background: var(--color-10);}
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const changeModeSwitch = document.getElementById('change-mode-switch');
      const changeModeButton = document.getElementById('change-mode-button');
      const visualToggle = document.querySelector('#dark-mode-toggle-btn .toggle-visual');
      const statusEl = document.getElementById('dark-mode-status');
      
      // Apply dark mode from localStorage
      try {
        const storedPreference = localStorage.getItem('changeMode');
        if (storedPreference === 'true') {
          document.body.classList.add('dark-mode');
        }
      } catch (e) {
        console.warn('Could not load dark mode preference');
      }
      
      // Sync toggle with current state
      const isDark = document.body.classList.contains('dark-mode');
      if (changeModeSwitch) changeModeSwitch.checked = isDark;
      
      // Toggle function
      const changeMode = () => {
        const isDark = document.body.classList.toggle('dark-mode');
        
        if (changeModeSwitch) {
          changeModeSwitch.checked = isDark;
          changeModeSwitch.setAttribute('aria-checked', isDark);
        }
        if (changeModeButton) changeModeButton.setAttribute('aria-checked', isDark);
        if (statusEl) statusEl.textContent = isDark ? 'Dark mode enabled' : 'Light mode enabled';
        
        try {
          localStorage.setItem('changeMode', isDark ? 'true' : 'false');
        } catch (e) {
          console.warn('LocalStorage unavailable');
        }
      };
      
      // Accessibility setup
      const addAccessibility = (el) => {
        if (!el) return;
        el.setAttribute('role', 'switch');
        el.setAttribute('aria-checked', document.body.classList.contains('dark-mode'));
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
      
      if (visualToggle) visualToggle.setAttribute('aria-hidden', 'true');
      
      // Event listeners
      if (changeModeSwitch) changeModeSwitch.addEventListener('change', changeMode);
      if (changeModeButton) changeModeButton.addEventListener('click', changeMode);
      if (visualToggle) visualToggle.addEventListener('click', () => {
        if (changeModeSwitch) changeModeSwitch.checked = !changeModeSwitch.checked;
        changeMode();
      });
    });
  </script>
  <?php
});
