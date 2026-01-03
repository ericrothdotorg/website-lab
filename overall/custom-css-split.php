<?php
/* ===== CRITICAL CSS - ABOVE THE FOLD ===== */

add_action("wp_head", function () {
    ?>
    <style>

    /* == THEME RELATED == */
    
    /* Header Container: Image */
    .hero-section .ct-media-container {border-bottom-right-radius: 15vw;}
    
    /* Header Container: Layer */
    .page-title,
    .page-description,
    .ct-breadcrumbs {background: rgba(0, 0, 0, 0.65);}
    .entry-header .page-title {border-radius: 10px 10px 0 0;}
    .entry-header .ct-breadcrumbs {border-radius: 0 0 10px 10px;}
    
    /* Header Container: Content */
    .page-title {padding: 10px 20px;}
    .page-description {padding: 0 20px 20px;}
    .ct-breadcrumbs {padding: 0 20px 20px;}
		
	/* == NOT THEME RELATED == */

    /* NAVIGATION */
    a:link {font-weight: bold; color: #1e73be;}
    a:link:hover {color: #c53030;}

    /* HTML & BODY BASICS */
    html {text-size-adjust: 100%; scroll-behavior: smooth;}
    p {text-align: justify;}
    code {background: none !important; border: none; padding: 0;}
    
    /* CORE LAYOUT UTILITIES */
    .row::after {content: ""; display: table; clear: both;}
    .clearfix::after {content: ""; clear: both; display: table;}
    
    /* RESPONSIVE VISIBILITY */
    @media (max-width: 768px) {.hide-on-small {display: none;}}
    @media (min-width: 768px) {.hide-on-big {display: none;}}
    
    /* BRANDING */
    
    /* Site Logo (Without Text and rotate 3D) */
    .site-logo {animation: rotate3d 5s linear infinite; will-change: transform;}
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
		will-change: transform;
    }
    .octagon-text-outside:hover {animation-play-state: paused;}
    @keyframes rotate-image-frontpage {
		0% {transform: rotate(0deg);}
		100% {transform: rotate(360deg);}
    }
    
    /* COLUMNS - BASIC STRUCTURE */
    .two-columns, .three-columns, .four-columns {float: left;}
    .two-columns {width: 49%;}
    .two-columns:first-child {margin-right: 1%;}
    .two-columns:last-child {margin-left: 1%;}
    
    /* SOME MISC STUFF */
    .display-total-number-of-posts {font-weight: normal;}
    
    /* USER'S MOTION PREFERENCES */
    @media (prefers-reduced-motion: reduce) {
		.site-logo, .octagon-text-outside, .blob-animation img, .animate__animated, .er-social-link-icon {
			animation: none !important;
			transition: none !important;
        }
    }
    </style>
    <?php
}, 5); // Load critical Styles in Head early

/* ===== NON-CRITICAL CSS - BELOW THE FOLD ===== */

add_action("wp_footer", function () {
    ?>
    <style>

    /* == THEME RELATED == */
    
    /* Global Elements */
    .ct-search-results a {color: #1e73be;}
    .footer-breadcrumbs {background: none; margin-left: -20px;}
    @media (min-width: 992px) {.footer-breadcrumbs {margin-top: -25px;}}
    
    /* Query Templates */
    .single-query .ct-query-template-grid,
    .single-query .ct-query-template.is-layout-slider {
		background: #fafbfc;
		border: 1px solid #e1e8ed;
		border-radius: 25px;
    }
    .single-query .ct-query-template-grid:hover,
    .single-query .ct-query-template.is-layout-slider:hover {background: #f2f5f7;}
    .single-query .ct-dynamic-data {padding-bottom: 16px;}

    /* Taxonomy Blocks */
    .wp-block-term.is-layout-flow .ct-dynamic-data {padding-bottom: 16px;}
    
    /* Media Styling */
    .ct-media-container img:not(.hero-section img),
    .ct-media-container picture:not(.hero-section picture) {border-radius: 25px;}
    .ct-dynamic-media-inner[data-hover="zoom-in"] {border-radius: 25px 25px 0 0 !important;}
    
    /* Grid Layouts */
    @media (min-width: 1024px) {.grid-four-columns .ct-query-template-grid {grid-template-columns: repeat(4, 1fr);}}
    @media (max-width: 600px) {.ct-related-posts .flexy-container {--grid-columns-width: calc(100% / 2);}}
    @media (min-width: 600px) and (max-width: 1024px) {.ct-related-posts .flexy-container {--grid-columns-width: calc(100% / 3);}}
    
    /* Related Content */
    .ct-related-posts-container {border-top: 1px solid #e1e8ed !important; max-width: 1290px; margin: 0 auto;}
    
    /* Navigation Arrows */
    .flexy-arrow-next, .flexy-arrow-prev {
		width: 30px;
		height: 30px;
		transform: none;
		opacity: 0.75;
		background: #afc2cf;
		color: #fff;
		position: absolute;
		top: -55px;
    }
    .flexy-arrow-next:hover, .flexy-arrow-prev:hover {opacity: 1; background: #afc2cf;}
    .flexy-arrow-next {right: 10px; left: auto;}
    .flexy-arrow-prev {right: 60px; left: auto;}

	/* == NOT THEME RELATED == */
    
    /* Used for Photo Album */
    .flex-item-country-icons {flex: 0 0 60px;}
    
    /* Format built-in Separator */
    .wp-block-separator:not(.is-style-dots) {height: 1px; background: #e1e8ed;}

    /* Taxonomy Blocks */
    .wp-block-term.is-layout-flow {
		background: #fafbfc;
		border: 1px solid #e1e8ed;
		border-radius: 25px;
    }
    .wp-block-term.is-layout-flow:hover {background: #f2f5f7;}

    /* NAVIGATION */
    
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
    .wp-block-page-list.english > li:nth-child(1) > ul > li:nth-child(n+2):nth-child(-n+4),
    .wp-block-page-list.english > li:not(:nth-child(1)):not(:nth-child(6)),
    .wp-block-page-list.english > li:nth-child(6) > ul > li:nth-child(1),
    .wp-block-page-list.english > li:nth-child(6) > ul > li:nth-child(1) > ul > li:nth-child(-n+2),
    .wp-block-page-list.deutsch > li:nth-child(1),
    .wp-block-page-list.deutsch > li:nth-child(n+3):nth-child(-n+7),
    .wp-block-page-list.site-overview > li:nth-child(2),
    .wp-block-page-list.site-overview > li:nth-child(3),
    .wp-block-page-list.site-overview > li:nth-child(4),
    .wp-block-page-list.site-overview > li:nth-child(8) {display: none;}
    
    /* Navigate with Pages */
    .page-links {padding-bottom: 50px;}
    .page-links a, .page-links .current, .page-links .post-pages-label {border: none; font-size: 14px;}
    .page-links a {color: #1e73be;}
    .page-links a:hover {box-shadow: 0 0 0 1px #1e73be;}
    .page-links .current {border-color: #1e73be; background: #1e73be; color: #fff;}
    .post-pages-label {text-transform: uppercase;}
    
    /* COLUMNS */
    
    /* MEDIA SCREEN Columns */
    @media (max-width: 600px) {.block-editor-two-columns {margin-top: 25px;}}
    @media (max-width: 768px) {
		.two-columns:nth-of-type(2), .two-columns-var:nth-of-type(2),
		.three-columns:nth-of-type(n+2), .four-columns:nth-of-type(n+3) {margin-top: 25px;}
		.two-columns, .three-columns {width: 100%;}
		.four-columns {width: 49%;}
    }
    
    /* FLEXBOX */
    .flex-container {display: flex; align-items: center; flex-flow: row wrap;}
    .flex-container.left-align {justify-content: flex-start;}
    .flex-container.center-align {justify-content: center;}
    
    /* TABLES */
    .wp-block-table thead {background-color: #f2f5f7;}
    .wp-block-table tr:hover {background-color: #f2f5f7;}
    
    /* DETAILS & SUMMARY */
    summary {color: #1e73be; font-weight: bold; cursor: pointer;}
    summary:hover {color: #c53030;}
    
    /* Details for Text etc. */
    .details-center summary {font-weight: 700; font-size: 20px; line-height: 1.5; text-align: center;}
    .details-left summary {font-weight: 700; font-size: 20px; line-height: 1.5;}
    .details-button summary {
		color: #fff !important;
		background: #1e73be;
		display: flex;
		align-items: center;
		justify-content: center;
		margin: auto;
		padding: 10px 20px;
		border-radius: 4px;
		text-align: center;
		max-width: fit-content;
    }
    .details-button summary:hover {background: #c53030;}
    
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
		border-top: 1px solid #bfbfbf;
		border-bottom: 1px solid #bfbfbf;
    }
    .details-accordion summary > * {margin-left: 0.5rem;}
    .details-accordion summary > *:first-child {margin-left: 0;}
    .details-accordion summary::before {content: "+"; font-weight: bold; color: #1e73be; margin-right: 0.5rem;}
    .details-accordion[open] summary::before {content: "−"; color: #c53030;}
    .details-accordion summary:hover {background: #f2f5f7;}
    .details-accordion > :where(:not(summary)) {margin-left: 1.25rem; margin-right: 1.25rem;}
    
    /* TEXT & COLORS */
    
    /* Text Basics */
    .br{display: none;}
    @media (max-width: 600px) {.br {display: block;}}
    .font-color-white {color: #d9d9d9;}
    .font-color-black {color: #192a3d;}
    @media (max-width: 992px) {.font-size-75 {font-size: 75%;}}
    
    /* Text as Columns */
    .two-columns-text, .three-columns-text, .four-columns-text {
		column-gap: 40px;
		column-rule: 1px solid #808080;
    }
    .two-columns-text {column-count: 2;}
    .three-columns-text {column-count: 3;}
    .four-columns-text {column-count: 4;}
    @media (max-width:768px) {.two-columns-text,.three-columns-text,.four-columns-text{column-count: 1; column-gap: 0; column-rule: none;}}
	
    /* (Emphasized) Design Blocks */
    .emphasized-design-green, .emphasized-design-red, .emphasized-design-orange {
		font-style: italic;
		border-left: 1px solid #808080;
		margin-left: 2em;
		padding-left: 2em;
    }
    .emphasized-design-green {color: #339966;}
    .emphasized-design-red {color: #990033;}
    .emphasized-design-orange {color: #ed7d31;}
    .font-design-red {color: #990033;}
    
    /* BLOCKQUOTES */
    .wp-block-quote {
		position: relative;
		width: 85%;
		max-width: fit-content;
		margin: 35px auto 70px;
		padding: 0 25px;
		border-inline-start: 3px solid #cccccc !important;
		border-right: 3px solid #cccccc;
		border-radius: 10px;
    }
    .wp-block-quote p, .wp-block-quote ul, .wp-block-quote li {
		text-align: justify;
		font-family: Georgia, serif;
		font-style: italic;
		color: #339966;
		line-height: 1.6;
    }
    .wp-block-quote cite {position: absolute; right: 25px; font-family: sans-serif; font-size: 1rem; font-style: normal;}
    :root :where(.is-layout-flow) > :last-child.wp-block-quote {margin-block-end: 25px;}
    
    /* Styling for Pullquotes */
    figure.wp-block-pullquote {position: relative; max-width: fit-content; margin: -25px 0 0; border: none;}
    .wp-block-pullquote blockquote {border-inline-start: none !important;}
    .wp-block-pullquote blockquote p {text-align: left; font-family: Georgia, serif; font-weight: normal; color: #339966; line-height: 1.6;}
    .wp-block-pullquote blockquote cite {position: absolute; right: 25px; font-family: sans-serif; font-size: 1rem; font-style: normal;}
    
    /* Styling for My Quotes */
    .quote-text p, .quote-text ul, .quote-text li {font-size: clamp(1rem, 1.25vw + 0.5rem, 1.25rem);}
    .quote-text ul, .quote-text li {margin-left: -15px;}
    
    /* TAG CLOUDS */
    .tag-cloud a {
		background: #f2f5f7;
		font-weight: normal;
		min-height: 25px;
		padding: 3px 15px;
		margin: 0 0 5px;
		border: 1px solid #1e73be;
		border-radius: 100px;
		display: inline-block;
		transition: background 0.2s ease;
    }
    .tag-cloud a:hover {background: #e1e8ed; color: #c53030;}
    .left-align {text-align: left;}
    
    /* IMAGE STYLES & EFFECTS */
    
    /* Zoom */
    .zoom {width: 35%; transition: width 1.5s ease-in-out; will-change: width;}
    .zoom:hover {width: 100% !important;}
    .zoom-x2 {transition: transform 1.5s ease-in-out; will-change: transform;}
    .zoom-x2:hover {transform: scale(2);}
    
	/* er-social-link-icon */
	@media (prefers-reduced-motion: no-preference) {.er-social-link-icon {transition: transform 0.1s ease;} .er-social-link-icon:hover {transform: scale(1.1);}}
	
    /* Blob Animation */
    .blob-animation img {animation: animate-blob 7.5s ease-in-out infinite alternate; will-change: border-radius;}
    .blob-animation img:hover {animation-play-state: paused;}
    @keyframes animate-blob {
		0%, 100% {border-radius: 20% 80% 70% 30% / 60% 40% 60% 40%;}
		25% {border-radius: 60% 40% 30% 70% / 40% 60% 40% 60%;}
		50% {border-radius: 60% 40% 30% 70% / 60% 40% 60% 40%;}
		75% {border-radius: 20% 80% 70% 30% / 40% 60% 40% 60%;}
    }
    .blob-animation figcaption {font-size: 1.5em;}
    
    /* Imitate DPS - Bill Erickson */
    .image-bedps {clear: both; overflow: hidden; background: #fafbfc; border: 1px solid #e1e8ed; border-radius: 25px;}
    .image-bedps:hover {background: #f2f5f7;}
    .image-bedps img {aspect-ratio: 16 / 9; transition: transform 0.3s ease; will-change: transform;}
    @supports not (aspect-ratio: 16 / 9) {
		.image-bedps img {width: 100%; height: auto;}
    }
    .image-bedps img:hover {transform: scale(1.05);}
    .image-bedps figcaption {margin-top: 16px; margin-bottom: 16px; font-size: 1.125rem; font-weight: bold;}
    
    /* EMBEDS & VIDEO */
    .embed-responsively {position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;}
    .embed-responsively iframe, .embed-responsively object, .embed-responsively embed {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
    }
    .is-provider-youtube.wp-embed-aspect-4-3 .wp-block-embed__wrapper::before,
    .is-provider-videopress.wp-embed-aspect-4-3 .wp-block-embed__wrapper::before,
    .is-provider-vimeo.wp-embed-aspect-4-3 .wp-block-embed__wrapper::before {padding-top: 56.25%;}
    .wp-block-video-osho video {vertical-align: middle; width: 75%;}
    
    /* BUTTONS */
    .wp-block-button__link, .button a, .smaller-button a {
		color: #fff !important;
		background-color: #1e73be;
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
    .wp-block-button__link:hover, .button a:hover, .smaller-button a:hover {background-color: #c53030;}
    .button a {margin: auto; max-width: fit-content;}
    .smaller-button a {cursor: pointer; min-height: 25px; padding: 5px 10px 7.5px;}
    
    /* BOXES */
    .text-box {overflow: hidden; padding: 25px; margin-bottom: 25px;}
    .resizable-box {height: 333px; resize: vertical; overflow: auto; padding: 25px; border: 0.5px solid #808080;}
    .box-background {background: #fafbfc; border: 1px solid #e1e8ed; transition: background 0.2s ease;}
    .box-background:hover {background: #f2f5f7;}
    .box-shadow {box-shadow: 6px 6px 9px rgba(0, 0, 0, 0.25);}
    
    /* FORMS & INPUTS */
    select, input[type=search], input[type=email] {border: 1px solid #bfbfbf; transition: border 0.2s ease;}
    select:focus, input[type=email]:focus {border: 1px solid #bfbfbf; outline: none;}
    input[type=search]:is(:visited, :hover, :focus, :active),
    input[type=search].modal-field {border: none; border-bottom: 1px solid #bfbfbf;}
    input[type=search].modal-field:is(:visited, :hover, :focus, :active) {border: none; border-bottom: 1px solid #bfbfbf;}
    
    /* FOOTER */
    
    /* Radius on Hover */
    #footer {transition: border-radius 1s ease; will-change: border-radius;}
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
	.dropdown-separator {padding-left: 10px; padding-right: 5px;}
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
		background: #001a33;
		border: 1px solid #3a4f66;
		border-bottom-left-radius: 5px;
		border-bottom-right-radius: 5px;
		z-index: 1;
    }
    .dropdown:hover .dropdown-content, .dropdown:focus-within .dropdown-content {display: block;}

	/* Footer Column RIGHT */
	.site-updates-wrapper {display: flex; align-items: center; padding-top: 25px;}
	.site-updates-text {padding-left: 35px; margin: 0;}
	.content-text {color: #FF9900;}

    /* Animate Footer Text */
    .content-text::before {content: "content"; animation: content-words 10s linear infinite; will-change: content;}
    @keyframes content-words {
		10% {content: " posts";}
		20% {content: " traits";}
		30% {content: " projects";}
		40% {content: " topics";}
		50% {content: " galleries";}
		60% {content: " feeds";}
		70% {content: " pages";}
		80% {content: " things";}
		90% {content: " visitors";}
    }
    </style>
    <?php
}, 15); // Load deferred Footer Styles after Theme defaults
