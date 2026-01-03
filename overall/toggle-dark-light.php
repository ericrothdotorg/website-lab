<?php
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
	  outline: 1px solid #1e73be;
	  outline-offset: 2px;
	}
	#dark-mode-toggle-btn .toggle-visual {
	  background: #3A4F66;
	  border: 1px solid #192A3D;
	  border-radius: 50px;
	  cursor: pointer;
	  display: inline-block;
	  position: relative;
	  transition: all ease-in-out 0.3s;
	  width: 50px;
	  height: 25px;
	}
	#dark-mode-toggle-btn .toggle-visual::after {
	  background: #192A3D;
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
	#change-mode-switch:checked + .toggle-visual {background: #0f1924; border-color: #3A4F66;}
	#change-mode-switch:checked + .toggle-visual::after {background: #3A4F66; transform: translateX(25px);}

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
      document.documentElement.classList.add('dark-mode-loading');
      document.body.classList.add('dark-mode');
    }
  } catch(e) {
    console.warn('Dark mode initialization failed:', e);
  }
})();
</script>

  <style>
    html.dark-mode-loading body {background: #0d0d0d; color: #bfbfbf;}
	html.dark-mode-loading body .toggle-mode {color: #bfbfbf !important;}
	html.dark-mode-loading body :is(h1, h2, h3, h4, h5, h6) {color: #bfbfbf;}
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
    
    /* == THEME RELATED == */
    
    /* Header */
    body.dark-mode[data-header*="type-1"] .ct-header [data-row*="middle"] {background: rgba(0, 0, 0, 0.75);}
    body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-title {--linkInitialColor: #ffffff; --linkHoverColor: #bfbfbf;}
    body.dark-mode[data-header*="type-1"] .ct-header [data-id="logo"] .site-description {color: #bfbfbf;}
    body.dark-mode[data-header*="type-1"] .ct-header [data-id="menu"] > ul > li > a {--linkInitialColor: #ffffff; --linkHoverColor: #bfbfbf;}
    body.dark-mode .ct-header-search {--linkInitialColor: #ffffff; --linkHoverColor: #bfbfbf;}
    body.dark-mode[data-header*="type-1"] [data-id="trigger"] {--icon-color: #ffffff; --icon-hover-color: #bfbfbf;}
    
    /* Search Results */
    body.dark-mode .ct-search-results {background: rgba(0, 0, 0, 0.9); box-shadow: none;}
    
    /* Sidebar */
    body.dark-mode aside[data-type='type-4']:after {background: #0d0d0d;}
    body.dark-mode .ct-sidebar .ct-widget {color: #bfbfbf;}
    
    /* Query Templates */
    body.dark-mode .single-query .ct-query-template-grid {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .single-query .ct-query-template-grid:hover {background: #0d0d0d;}
    body.dark-mode .single-query .ct-query-template.is-layout-slider {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .single-query .ct-query-template.is-layout-slider:hover {background: #0d0d0d;}
    
    /* Related Posts */
    body.dark-mode .ct-related-posts-container {border-top: 1px solid #3a4f66 !important;}
    body.dark-mode article > .ct-related-posts {border-top: 1px solid #3a4f66;}
    
    /* Post Navigation */
    body.dark-mode .nav-item-prev {color: #bfbfbf;}
    body.dark-mode .nav-item-next {color: #bfbfbf;}
	
    /* == NOT THEME RELATED == */
	
    /* Post Navigation */
    body.dark-mode .post-navigation [class*='nav-item'] {color: #1e73be;}
    body.dark-mode .post-navigation [class*='nav-item']:hover {color: #c53030;}
    body.dark-mode .post-navigation:after {background: #3a4f66;}
	
    /* Taxonomy Blocks */
    body.dark-mode .wp-block-term.is-layout-flow {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .wp-block-term.is-layout-flow:hover {background: #0d0d0d;}
	
    /* DPS - Bill Erickson */
    body.dark-mode .display-posts-listing .listing-item {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .display-posts-listing .listing-item:hover {background: #0d0d0d;}
    body.dark-mode .display-posts-listing.grid#small-version .listing-item {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .display-posts-listing.grid#small-version .listing-item:hover {background: #0d0d0d;}
    body.dark-mode .display-posts-trending .listing-item {background: none; border: none;}
    body.dark-mode .image-bedps {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .image-bedps:hover {background: #0d0d0d;}
    
    /* ACCORDIONS */
    body.dark-mode .details-accordion summary {border-top: 1px solid #404040; border-bottom: 1px solid #404040;}
    body.dark-mode .details-accordion summary:hover, body.dark-mode .details-accordion summary:focus {background: #1a1a1a;}
    
    /* TAG CLOUDS */
    body.dark-mode .tag-cloud a {background: #1a1a1a;}
    body.dark-mode .tag-cloud a:hover {background: none;}
    
    /* FORMS & INPUTS */
    
    /* Select Dropdowns */
    body.dark-mode select {background: #1a1a1a; color: #bfbfbf; border: 1px solid #404040;}
    body.dark-mode select:focus {border: 1px solid #404040;}
    body.dark-mode option {background: #1a1a1a; color: #bfbfbf;}
    
    /* Search & Email Inputs */
    body.dark-mode input[type=search] {background: #1a1a1a; color: #bfbfbf; border: 1px solid #404040;}
    body.dark-mode input[type=search]:is(:visited, :hover, :focus, :active) {border: 1px solid #404040;}
    body.dark-mode input[type=email] {color: #bfbfbf; border: 1px solid #404040;}
    body.dark-mode input[type=search].modal-field {border: none; border-bottom: 1px solid #bfbfbf;}
    body.dark-mode input[type=search].modal-field:is(:visited, :hover, :focus, :active) {border: none; border-bottom: 1px solid #bfbfbf;}
    
    /* TABLES */
    body.dark-mode .wp-block-table thead {background-color: #1a1a1a; border: 1px solid #262626; border-bottom: 3px solid #262626;}
    body.dark-mode .wp-block-table th {border: 1px solid #262626;}
    body.dark-mode .wp-block-table tr {border: 1px solid #262626;}
    body.dark-mode .wp-block-table td {border: 1px solid #262626;}
    body.dark-mode .wp-block-table tr:hover {background-color: #1a1a1a;}
    
    /* BOXES */
    body.dark-mode .text-box {background: #1a1a1a;}
    body.dark-mode .text-box:hover {background: #000000;}
    body.dark-mode .box-background {background: #1a1a1a; border: 1px solid #1a1a1a;}
    body.dark-mode .box-background:hover {background: #0d0d0d;}
    body.dark-mode .box-shadow {box-shadow: none;}
    
    /* VARIOUS ELEMENTS */
    
    /* Image Effects */
    body.dark-mode .image-invert {filter: invert(1);}
    
    /* Logo Swaps */
    body.dark-mode .wp-image-122531 {content: url("https://ericroth.org/wp-content/uploads/2024/07/SBB_NEG_2F_RGB_100.svg");} /* SBB logo in My World */
    body.dark-mode .wp-image-148107 {content: url("https://ericroth.org/wp-content/uploads/2025/07/github-mark-white.png");} /* Github logo in HTML, CSS, JS & Co. */
    
    /* Text & Categories */
    body.dark-mode .cat-prefix {color: #bfbfbf;}
    body.dark-mode .cat-links {color: #bfbfbf;}
    body.dark-mode .card-counter {color: #f2f2f2;}
    
    /* Separators & Borders */
    body.dark-mode .wp-block-separator:not(.is-style-dots) {height: 1px; background: #3a4f66;}
    body.dark-mode .wp-block-group.has-border-color {border-color: #262626 !important;}
    
    /* Emphasized Text */
    body.dark-mode .emphasized-design-red {color: #cc0044;}
    body.dark-mode .font-design-red {color: #cc0044;}
    
    /* Code & Columns */
    body.dark-mode code {background: none;}
    body.dark-mode .text-column-front {background: #1a1a1a !important;}
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const changeModeSwitch = document.getElementById('change-mode-switch');
      const changeModeButton = document.getElementById('change-mode-button');
      const visualToggle = document.querySelector('#dark-mode-toggle-btn .toggle-visual');
      
      let debounceTimer;
      const changeMode = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          const isDark = document.body.classList.toggle('dark-mode');
          const statusEl = document.getElementById('dark-mode-status');
          if (changeModeSwitch) changeModeSwitch.checked = isDark;
          
          // Update aria-checked on all accessible elements
          const updateAriaChecked = () => {
            if (changeModeSwitch) changeModeSwitch.setAttribute('aria-checked', isDark);
            if (changeModeButton) changeModeButton.setAttribute('aria-checked', isDark);
          };
          updateAriaChecked();
          
          // === Status announcement ===
          if (statusEl) {
            statusEl.textContent = isDark ? 'Dark mode enabled' : 'Light mode enabled';
          }
          
          try {
            localStorage.setItem('changeMode', isDark);
          } catch (e) {
            console.warn('LocalStorage unavailable');
          }
        }, 150);
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
      };
      
      applyAccessibility(changeModeSwitch);
      applyAccessibility(changeModeButton);
      
      // Mark visual toggle as decorative only
      if (visualToggle) {
        visualToggle.setAttribute('aria-hidden', 'true');
      }
      
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
      
      // Remove Loading Class - mode already applied in head
      document.documentElement.classList.remove('dark-mode-loading');
      
      // Sync toggle state with current mode (already set by inline script)
      if (changeModeSwitch) {
        changeModeSwitch.checked = document.body.classList.contains('dark-mode');
      }
    });
  </script>
  <?php
});
