(function($) {
    'use strict';

    const data = window.fpSetupTourData || {};
    const steps = Array.isArray(data.steps) ? data.steps : [];
    const i18n = data.i18n || {};

    const $overlay = $('#fp-tour-overlay');
    const $title = $('#fp-tour-title');
    const $content = $('#fp-tour-content');
    const $next = $('#fp-tour-next');
    const $prev = $('#fp-tour-prev');
    const $skip = $('#fp-tour-skip');
    let currentIndex = 0;

    function setButtonLabels() {
        $next.text(steps.length > 1 ? (i18n.next || 'Next') : (i18n.finish || 'Done'));
        $prev.text(i18n.previous || 'Previous');
        $skip.text(i18n.skip || 'Skip tour');
    }

    function updateStep() {
        if (!steps.length) {
            closeTour();
            return;
        }

        const step = steps[currentIndex];
        $title.text(step.title || '');
        $content.text(step.content || '');

        if (currentIndex === steps.length - 1) {
            $next.text(i18n.finish || 'Done');
        } else {
            $next.text(i18n.next || 'Next');
        }

        if (currentIndex === 0) {
            $prev.prop('disabled', true).addClass('button-disabled');
        } else {
            $prev.prop('disabled', false).removeClass('button-disabled');
        }
    }

    function openTour() {
        if (!steps.length) {
            return;
        }

        currentIndex = 0;
        setButtonLabels();
        updateStep();
        $overlay.addClass('is-visible').attr('aria-hidden', 'false');
    }

    function closeTour() {
        $overlay.removeClass('is-visible').attr('aria-hidden', 'true');
    }

    $(document).on('click', '#fp-start-tour', function(event) {
        event.preventDefault();
        openTour();
    });

    $next.on('click', function() {
        if (currentIndex < steps.length - 1) {
            currentIndex++;
            updateStep();
            return;
        }

        closeTour();
    });

    $prev.on('click', function() {
        if (currentIndex === 0) {
            return;
        }

        currentIndex--;
        updateStep();
    });

    $skip.on('click', function() {
        closeTour();
    });

    $overlay.on('click', function(event) {
        if (event.target === event.currentTarget) {
            closeTour();
        }
    });
})(jQuery);
