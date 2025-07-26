<?php
add_action('wp_footer', function () {
    if (is_page(array('17552')) || is_single(array(''))) {
    ?>
    <!-- Styles -->
    <style>
        .tabs {
            overflow: hidden;
        }
        .tabs button {
            float: left;
            padding: 7.5px 10px;
            margin: 0px 2.5px;
            color: #1e73be;
            font-weight: bold;
            background: rgb(255, 255, 255);
            background: linear-gradient(0deg, rgba(255, 255, 255, 1) 0%, rgba(230, 230, 230, 0.75) 100%);
            border: solid #c5c5c5;
            border-width: 1px 1px 0 1px;
            border-radius: 5px 5px 0 0;
            cursor: pointer;
        }
        .tabs button:hover {
            color: #c53030;
        }
        .tab-content {
            display: none;
            border: 1px solid #c5c5c5;
            border-radius: 5px 15px 15px 15px;
            padding: 2.5rem 1.5rem 2.5rem 2.5rem;
        }
        body.dark-mode .tabs button {
            background: linear-gradient(0deg, rgba(26, 26, 26, 1) 0%, rgba(51, 51, 51, 0.75) 100%);
            border: solid #404040;
            border-width: 1px 1px 0 1px;
        }
        body.dark-mode .tab-content {
            border: 1px solid #404040;
        }
    </style>
    <!-- Script -->
    <script>
        function setupTabs(containerId) {
            const container = document.getElementById(containerId);
            const tabs = container.querySelectorAll('.tab-links');
            const tabContents = container.querySelectorAll('.tab-content');
            if (tabContents.length > 0) {
                tabContents[0].style.display = 'block';
            }
            tabs.forEach(tab => {
                tab.addEventListener('mouseover', () => {
                    const tabId = tab.getAttribute('data-tab');
                    tabContents.forEach(content => {
                        if (content.id === tabId) {
                            content.style.display = 'block';
                        } else {
                            content.style.display = 'none';
                        }
                    });
                });
            });
        }
        setupTabs('countries-tabs');
        setupTabs('cities-tabs');
    </script>
    <?php
    }
});
?>
