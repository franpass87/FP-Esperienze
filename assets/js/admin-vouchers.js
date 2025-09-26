(function($, window) {
    'use strict';

    function getI18nString(key) {
        if (!window.fpEsperienzeAdmin || !window.fpEsperienzeAdmin.i18n) {
            return '';
        }

        return window.fpEsperienzeAdmin.i18n[key] || '';
    }

    function copyToClipboard(text) {
        if (!navigator.clipboard) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            return Promise.resolve();
        }

        return navigator.clipboard.writeText(text);
    }

    $(function() {
        var $bulkForm = $('.fp-admin-vouchers-form');
        var $bulkAction = $('#fp-voucher-bulk-action');
        var $extendWrapper = $('#fp-voucher-bulk-extend');
        var $extendInput = $('#fp-voucher-bulk-extend-months');
        var $selectAll = $('#fp-vouchers-select-all');
        var $voucherCheckboxes = $bulkForm.find('input[name="voucher_ids[]"]');

        if ($bulkForm.length) {
            $bulkAction.on('change', function() {
                if ($(this).val() === 'bulk_extend') {
                    $extendWrapper.removeAttr('hidden');
                } else {
                    $extendWrapper.attr('hidden', 'hidden');
                }
            }).trigger('change');

            $selectAll.on('change', function() {
                $voucherCheckboxes.prop('checked', this.checked);
            });

            $voucherCheckboxes.on('change', function() {
                if (!this.checked) {
                    $selectAll.prop('checked', false);
                }
            });

            $bulkForm.on('submit', function(event) {
                if ($(event.target).is('.fp-voucher-action-form')) {
                    return;
                }

                var action = $bulkAction.val();

                if (!action) {
                    event.preventDefault();
                    window.alert(getI18nString('selectAction'));
                    return;
                }

                var selectedCount = $voucherCheckboxes.filter(':checked').length;
                if (!selectedCount) {
                    event.preventDefault();
                    window.alert(getI18nString('selectVouchers'));
                    return;
                }

                var message = '';
                switch (action) {
                    case 'bulk_void':
                        message = getI18nString('confirmVoid');
                        break;
                    case 'bulk_resend':
                        message = getI18nString('confirmResend');
                        break;
                    case 'bulk_extend':
                        var months = parseInt($extendInput.val(), 10) || 0;
                        if (months <= 0) {
                            event.preventDefault();
                            window.alert(getI18nString('invalidExtendMonths'));
                            return;
                        }
                        message = getI18nString('confirmExtend') + ' ' + months + ' ' + getI18nString('months');
                        break;
                    default:
                        message = '';
                }

                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        }

        $('.fp-voucher-action-form').on('submit', function(event) {
            var $form = $(this);
            var confirmKey = $form.data('confirm');
            var notice = confirmKey ? getI18nString(confirmKey) : '';

            if (confirmKey === 'extendVoucherExpiration') {
                var monthsInput = $form.find('input[name="extend_months"]');
                var months = parseInt(monthsInput.val(), 10) || 0;
                if (months <= 0) {
                    event.preventDefault();
                    window.alert(getI18nString('invalidExtendMonths'));
                    return;
                }
            }

            if (notice && !window.confirm(notice)) {
                event.preventDefault();
            }
        });

        $('.fp-voucher-copy').on('click', function(event) {
            event.preventDefault();
            var downloadUrl = $(this).data('downloadUrl');

            if (!downloadUrl) {
                return;
            }

            copyToClipboard(downloadUrl).then(function() {
                window.alert(getI18nString('pdfLinkCopied'));
            }).catch(function() {
                window.alert(getI18nString('pdfLinkCopied'));
            });
        });
    });
})(jQuery, window);
