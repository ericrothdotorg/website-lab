<?php 
add_action('wp_footer', function() {
    ?>
    <p id="cookie-notice">We serve <strong>cookies</strong> to enhance your browsing experience. Learn more about it in our <a href="https://ericroth.org/this-site/site-policies/">Site Policies</a><br><span style="display: block; text-align: center;"><button onclick="acceptCookie();"><span>Got It</span></button></span></p>
    <style>
        #cookie-notice {text-align: justify; color: #ffffff; font-family: inherit; background: rgba(0,0,0,0.75); padding: 20px; position: fixed; bottom: 15px; left: 15px; width: 100%; max-width: 300px; border-radius: 5px; margin: 0px; visibility: hidden; z-index: 9999; box-sizing: border-box}
        #cookie-notice button {font-weight: bold; color: #ffffff; background: #1e73be; border-radius: 3px; padding: 10px; margin-top: 15px; width: 50%; cursor: pointer}
        #cookie-notice button:hover {background: #c53030;}
        #cookie-notice button:hover span {display: none;}
        #cookie-notice button:hover::before {content: "Accept";}
        @media only screen and (max-width: 480px) {#cookie-notice {max-width: 100%; bottom: 0; left: 0; border-radius: 0} }
    </style>
    <script>
        function acceptCookie() {
            document.cookie = "cookieaccepted=1; max-age=86400; path=/";
            document.getElementById("cookie-notice").style.visibility = "hidden";
        }
        if (document.cookie.indexOf("cookieaccepted") < 0) {
            document.getElementById("cookie-notice").style.visibility = "visible";
        }
    </script>
    <?php
});
?>
