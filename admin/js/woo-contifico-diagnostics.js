(function ($) {
    'use strict';

    $(function () {
        var $wrapper = $('.woo-contifico-diagnostics');

        if (!$wrapper.length) {
            return;
        }

        $wrapper.on('change', '#woo-contifico-diagnostics-filter', function () {
            var $form = $(this).closest('form');

            if ($form.length) {
                $form.trigger('submit');
            }
        });
    });
})(jQuery);
