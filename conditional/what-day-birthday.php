<?php
defined('ABSPATH') || exit;

add_action('wp_footer', function() {
    if (is_page(array('')) || is_single(array('134149'))) { ?>
        <script>
			function computeBirthday() {
				const day   = parseInt(document.getElementById('bday-day').value, 10);
				const month = parseInt(document.getElementById('bday-month').value, 10);
				const year  = parseInt(document.getElementById('bday-year').value, 10);
				if (isNaN(day) || isNaN(month) || isNaN(year) || year < 1) {
					alert('Please enter a valid date.');
					return;
				}
				const date = new Date(year, month - 1, day);
				if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
					alert('Invalid date — please check your input.');
					return;
				}
				document.getElementById('bday-result1').value = date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
				document.getElementById('bday-result2').value = date.toLocaleDateString('en-US', { weekday: 'long' });
			}
        </script>
    <?php }
});
