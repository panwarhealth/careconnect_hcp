(function ($) {
    $(document).ready(function () {

        var waitForEl = function (selector, callback, count) {
            if ($(selector).length) {
                callback();
            } else {
                setTimeout(function () {
                    if (!count) count = 0;
                    count++;
                    if (count < 5) {
                        waitForEl(selector, callback, count);
                    }
                }, 500);
            }
        };

        function appendOR(selector) {
            $(selector).each(function () {
                $(this).before('<p class="my-auto mx-sm">OR</p>');
            });
        }

        waitForEl('.frm_radio:not(:first)', function () {
            appendOR('.frm_radio:not(:first)');
        });

        $(document).on('frmPageChanged', function () {
            waitForEl('.frm_radio:not(:first)', function () {
                appendOR('.frm_radio:not(:first)');
            });
        });
    });
})(jQuery);
