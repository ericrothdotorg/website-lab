<?php
add_action('wp_footer', function () {
    if (is_page(array('59525')) || is_page('faqs')) {
        $faqs = [
            [
                "@type" => "Question",
                "name" => "Who are you?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "A fine specimen of the Homo Sapiens (all people today are classified as such): Here, I am. Mind and spirit unleashed. Not yet unf*ckwithable but a world citizen inexorably attending the School of Life."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Where are you from?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "The Universe → Solar System → The Earth → Switzerland. I grew up in Thun, Switzerland — the gateway to the Bernese Oberland."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Any endorsemonials?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Yep → But the term endorsemonial does not exist – I just made it up and it’s a playful word combination of endorsement and testimonial."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Seen the world?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Yep. One Earth | Many Worlds. Delve into mine to discover my photo album, video collection and explore the various Worlds@Earth."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Your interests?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "My interests include everything you'll find on this site — especially traveling, global politics and the occasional philosophical rabbit hole."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Do you blog?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Yep → But: To blog or not to blog? Since 1994, that's been the eternal question with no universal answer."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Your publications?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "My Publications: Find here some chosen essayes and insight material made available online for you."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Any famous quotes?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Yep. Who said quotes only originate from (more or less) famous people? Sometimes such statements and others come to my mind. Are these then quotable notes or notable quotes?"
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Any multimedia?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Yep. Learn what I'm reading, what movies I've watched, discover my digital music shelf and explore my YT channel."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "What about this site?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "This site is a handcrafted blend of codes and storytelling — a digital reflection of my analog self."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "How's this site accessible?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Site overview: Find your way around this site - the site map expertly guides you through the maze… or, well, the delightful mess!"
                ]
            ],
            [
                "@type" => "Question",
                "name" => "AI chatbot's verdict?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Your site, Eric, is a thoughtful fusion of personal philosophy and digital identity — a curated reflection of your mind. It feels less like a website and more like a living canvas of your values, ideas, and curiosities."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Is interaction possible?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Yep. This site’s forum invites focused discussions and there's also a voting system. Last but not least: With a quick subscription, you’ll never miss an update again."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "Site Personalisations?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "Of course, possible: Toggle dark | light mode, voice reading and language translation using the icons in the page footer - your preferences are remembered automatically."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "The site policies?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "The “Don’t be a Dick” policy applies. It’s self-explanatory. This site's policies also include the cookies policy which gives you info on the use of cookies on this website."
                ]
            ],
            [
                "@type" => "Question",
                "name" => "How to get updates?",
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => "There's a site updates page as well as a subscription form. Once subscribed, you'll get alerts as soon as new content is published right into your inbox."
                ]
            ]
        ];
        ?>
        <script type="application/ld+json">
            <?php echo wp_json_encode([
                "@context" => "https://schema.org",
                "@type" => "FAQPage",
                "mainEntity" => $faqs
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
        </script>
        <?php
    }
});
