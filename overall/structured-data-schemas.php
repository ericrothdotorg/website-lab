<?php
// ======================================
// STRUCTURED DATA SCHEMAS
// ======================================

// Organization Schema - Ninja Services
add_action('wp_head', function() {
    ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Ninja Services",
      "description": "(Interim) Manager for Business Excellence and Global Procurement",
      "url": "<?php echo esc_url(home_url('/')); ?>",
      "logo": {
        "@type": "ImageObject",
        "url": "<?php echo esc_url(get_site_icon_url()); ?>"
      },
      "areaServed": "Worldwide",
      "sameAs": [
        "https://www.linkedin.com/in/eric-roth"
      ],
      "knowsAbout": [
        "Business Excellence",
        "Lean Management",
        "Continuous Improvement",
        "KAIZEN Methodology",
        "Quality Management",
        "ISO Standards",
        "Six Sigma",
        "Supply Chain Optimization",
        "Global Procurement Strategy"
      ]
    }
    </script>
    <?php
});

// Person Schema - Eric Roth
add_action('wp_head', function() {
    ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Person",
      "name": "Eric Roth",
      "jobTitle": "(Interim) Manager for Business Excellence and Global Procurement",
      "url": "<?php echo esc_url(home_url('/')); ?>",
      "image": {
        "@type": "ImageObject",
        "url": "<?php echo esc_url(get_site_icon_url()); ?>"
      },
      "sameAs": [
        "https://www.linkedin.com/in/eric-roth"
      ],
      "knowsAbout": [
        "Business Excellence",
        "Lean Management",
        "Continuous Improvement",
        "KAIZEN Methodology",
        "Quality Management",
        "ISO Standards",
        "Six Sigma",
        "Supply Chain Optimization",
        "Global Procurement Strategy"
      ],
      "hasOccupation": {
        "@type": "Occupation",
        "name": "(Interim) Manager for Business Excellence and Global Procurement",
        "occupationalCategory": "Business Excellence"
      }
    }
    </script>
    <?php
});

// Article Schema - Blog posts and my-interests CPT
add_action('wp_head', function() {
    if (!is_singular(array('post', 'my-interests'))) return;
    
    global $post;
    $image_url = get_the_post_thumbnail_url($post->ID, 'full');
    ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Article",
      "headline": "<?php echo esc_js(get_the_title()); ?>",
      "author": {
        "@type": "Person",
        "name": "Eric Roth",
        "url": "<?php echo esc_url(home_url('/')); ?>"
      },
      "datePublished": "<?php echo get_the_date('c'); ?>",
      "dateModified": "<?php echo get_the_modified_date('c'); ?>",
      "publisher": {
        "@type": "Person",
        "name": "Eric Roth",
        "url": "<?php echo esc_url(home_url('/')); ?>",
        "logo": {
          "@type": "ImageObject",
          "url": "<?php echo esc_url(get_site_icon_url()); ?>"
        }
      },
      "description": "<?php echo esc_js(wp_trim_words(get_the_excerpt(), 30)); ?>",
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "<?php echo esc_url(get_permalink()); ?>"
      }
      <?php if ($image_url): ?>
      ,
      "image": {
        "@type": "ImageObject",
        "url": "<?php echo esc_url($image_url); ?>"
      }
      <?php endif; ?>
    }
    </script>
    <?php
});

// FAQ Schema - FAQ page
add_action('wp_footer', function() {
    if (!is_page(array('59525')) && !is_page('faqs')) return;
    $faqs = array(
        array(
            "@type" => "Question",
            "name" => "Who are you?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "A fine specimen of the Homo Sapiens (all people today are classified as such): Here, I am. Mind and spirit unleashed. Not yet unf*ckwithable but a world citizen inexorably attending the School of Life."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Where are you from?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "The Universe → Solar System → The Earth → Switzerland. I grew up in Thun, Switzerland — the gateway to the Bernese Oberland."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Any endorsemonials?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Yep → But the term endorsemonial does not exist – I just made it up and it's a playful word combination of endorsement and testimonial."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Seen the world?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Yep. One Earth | Many Worlds. Delve into mine to discover my photo album, video collection and explore the various Worlds@Earth."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Your interests?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "My interests include everything you'll find on this site — especially traveling, global politics and the occasional philosophical rabbit hole."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Do you blog?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Yep → But: To blog or not to blog? Since 1994, that's been the eternal question with no universal answer."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Your publications?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "My Publications: Find here some chosen essayes and insight material made available online for you."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Any famous quotes?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Yep. Who said quotes only originate from (more or less) famous people? Sometimes such statements and others come to my mind. Are these then quotable notes or notable quotes?"
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Any multimedia?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Yep. Learn what I'm reading, what movies I've watched, discover my digital music shelf and explore my YT channel."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "What about this site?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "This site is a handcrafted blend of codes and storytelling — a digital reflection of my analog self."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "How's this site accessible?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Site overview: Find your way around this site - the site map expertly guides you through the maze… or, well, the delightful mess!"
            )
        ),
        array(
            "@type" => "Question",
            "name" => "AI chatbot's verdict?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Your site, Eric, is a thoughtful fusion of personal philosophy and digital identity — a curated reflection of your mind. It feels less like a website and more like a living canvas of your values, ideas, and curiosities."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Is interaction possible?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Yep. This site's forum invites focused discussions and there's also a voting system. Last but not least: With a quick subscription, you'll never miss an update again."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "Site Personalisation?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "Of course, possible: Toggle dark | light mode, voice reading and language translation using the icons in the page footer - your preferences are remembered automatically."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "The site policies?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "The \"Don't be a Dick\" policy applies. It's self-explanatory. This site's policies also include the cookies policy which gives you info on the use of cookies on this website."
            )
        ),
        array(
            "@type" => "Question",
            "name" => "How to get updates?",
            "acceptedAnswer" => array(
                "@type" => "Answer",
                "text" => "There's a site updates page as well as a subscription form. Once subscribed, you'll get alerts as soon as new content is published right into your inbox."
            )
        )
    );
    ?>
    <script type="application/ld+json">
        <?php echo wp_json_encode(array(
            "@context" => "https://schema.org",
            "@type" => "FAQPage",
            "mainEntity" => $faqs
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
    </script>
    <?php
});
