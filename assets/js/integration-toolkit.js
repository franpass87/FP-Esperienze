(function(window, document) {
    'use strict';

    function restoreLabel(button) {
        var defaultLabel = button.getAttribute('data-default-label');
        if (!defaultLabel) {
            return;
        }

        window.setTimeout(function() {
            button.textContent = defaultLabel;
        }, 2000);
    }

    function copyUsingClipboardApi(text) {
        if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
            return Promise.reject();
        }

        return navigator.clipboard.writeText(text);
    }

    function copyUsingFallback(text) {
        var textarea = document.createElement('textarea');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        textarea.value = text;
        document.body.appendChild(textarea);

        textarea.focus();
        textarea.select();

        try {
            var result = document.execCommand('copy');
            document.body.removeChild(textarea);
            return result ? Promise.resolve() : Promise.reject();
        } catch (error) {
            document.body.removeChild(textarea);
            return Promise.reject(error);
        }
    }

    function copyContent(source) {
        var text = '';

        if (source.value !== undefined) {
            text = source.value;
        } else if (source.textContent) {
            text = source.textContent;
        }

        if (!text) {
            return Promise.reject();
        }

        return copyUsingClipboardApi(text).catch(function() {
            return copyUsingFallback(text);
        });
    }

    function handleCopyClick(event) {
        event.preventDefault();

        var button = event.currentTarget;
        var targetId = button.getAttribute('data-target');
        if (!targetId) {
            return;
        }

        var target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        copyContent(target)
            .then(function() {
                var copiedLabel = button.getAttribute('data-copied-label');
                if (copiedLabel) {
                    button.textContent = copiedLabel;
                    restoreLabel(button);
                }
            })
            .catch(function() {
                var fallbackLabel = button.getAttribute('data-fallback-label');
                if (fallbackLabel) {
                    button.textContent = fallbackLabel;
                    restoreLabel(button);
                }
            });
    }

    function init() {
        var buttons = document.querySelectorAll('.fp-integration-copy');
        Array.prototype.forEach.call(buttons, function(button) {
            button.addEventListener('click', handleCopyClick);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
