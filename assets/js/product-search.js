(function($) {
    function initProductSearch(selector) {
        $(selector).each(function() {
            var $select = $(this);
            if ($select.data('select2')) {
                return;
            }
            $select.select2({
                width: 'resolve',
                allowClear: true,
                placeholder: $select.data('placeholder') || '',
                ajax: {
                    url: fpEsperienzeAdmin.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: 'fp_search_experience_products',
                            nonce: fpEsperienzeAdmin.nonce,
                            q: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.results || [],
                            pagination: { more: data.pagination && data.pagination.more }
                        };
                    }
                }
            });
        });
    }

    $(function() {
        initProductSearch('.fp-product-search');
    });
})(jQuery);
