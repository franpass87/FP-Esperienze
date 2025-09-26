(function() {
    const root = document;
    if (!root) {
        return;
    }

    const forms = root.querySelectorAll('[data-fp-closure-remove]');
    if (!forms.length) {
        return;
    }

    const message = (window.fpEsperienzeAdmin && window.fpEsperienzeAdmin.i18n && window.fpEsperienzeAdmin.i18n.confirmDeleteClosure)
        ? String(window.fpEsperienzeAdmin.i18n.confirmDeleteClosure)
        : 'Are you sure you want to remove this closure?';

    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
})();
