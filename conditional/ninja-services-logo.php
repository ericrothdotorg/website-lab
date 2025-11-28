<?php

add_action('wp_footer', function() {
    if (
        is_page(array(
            // English Pages
            '179','87873','59078','55867','138768','113713','123635','65752','8977','100674','83147','55706','65756','87873','151412',
            // German Pages
            '150455','150449','149904','149998','150034','150120','150200','150223','150233','151417')) ||
        is_single(array(
            // English Singles
            '140909', '127567', '127484', '121987', '121984', '141168','121985', '135495', '121986', '121983','150592',
            // German Singles
            '150592')) ||
        is_tax('topics', 124)
    ) {
        ?>
        <script>
        (function() {
            const initNinja = function() {
                const targetContainer = document.querySelector(".hero-section[data-type='type-2'] > .entry-header.ct-container");
                if (targetContainer) {
                    const wrapper = document.createElement("div");
                    wrapper.className = "custom-ninja-wrapper";
                    wrapper.setAttribute("role", "img");
                    wrapper.setAttribute("aria-label", "Illustration of a Ninja Character");
                    const link = document.createElement("a");
                    link.href = "https://ericroth.org/services/";
                    link.style.pointerEvents = "auto";
                    link.title = "Ninja Services";
                    link.setAttribute("aria-label", "Visit Ninja Services Page");
                    link.setAttribute("role", "link");
                    link.setAttribute("tabindex", "0");
                    const img = document.createElement("img");
                    img.src = "https://ericroth.org/wp-content/uploads/2025/08/Ninja-Character-Stroke-1px.png";
                    img.alt = "Ninja Character Illustration";
                    img.className = "custom-ninja-image daneden-slideInRight";
                    img.width = 100;
                    img.height = 100;
                    img.loading = "lazy";
                    img.decoding = "async";
                    img.setAttribute("fetchpriority", "low");
                    link.appendChild(img);
                    wrapper.appendChild(link);
                    targetContainer.appendChild(wrapper);
                }
            };
            if ('requestIdleCallback' in window) {
                requestIdleCallback(initNinja);
            } else {
                window.addEventListener("load", initNinja);
            }
        })();
        </script>
        <style>
        .custom-ninja-wrapper {
            position: absolute;
            top: calc(50% - 25px);
            right: 25px;
            transform: translateY(-50%);
            z-index: 99;
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .custom-ninja-image {
            max-width: 100%;
            height: auto;
            border: 0;
        }
        @media (min-width: 1200px) {.entry-header.ct-container .page-description p {margin-right: 150px;}}
        @media (max-width: 1200px) {.entry-header.ct-container .page-description p {margin-right: 140px;}}
        @media (max-width: 1024px) {.custom-ninja-wrapper {width: 90px; height: 90px;} .entry-header.ct-container .page-description p {margin-right: 130px;}}
        @media (max-width: 992px) {.custom-ninja-wrapper {width: 80px; height: 80px;} .entry-header.ct-container .page-description p {margin-right: 120px;}}
        @media (max-width: 768px) {.custom-ninja-wrapper {width: 70px; height: 70px;}}
        @media (max-width: 480px) {.custom-ninja-wrapper {width: 60px; height: 60px;}}
        </style>
        <?php
    }
});
