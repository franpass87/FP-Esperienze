/**
 * FP Esperienze Frontend JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize frontend functionality
        FPEsperienze.init();
    });

    window.FPEsperienze = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Experience card hover effects
            $('.fp-experience-card').hover(
                function() {
                    $(this).addClass('hovered');
                },
                function() {
                    $(this).removeClass('hovered');
                }
            );

            // Smooth scroll for anchor links
            $('a[href^="#"]').on('click', function(e) {
                var target = $(this.getAttribute('href'));
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 80
                    }, 600);
                }
            });
        }
    };

})(jQuery);