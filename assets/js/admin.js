/**
 * RADYK Labs Admin JS
 * Shared JavaScript for RADYK admin pages.
 */

(function ($) {
    'use strict';

    // Toggle switch handler
    $(document).on ('change', '.radyk-toggle input[type="checkbox"]', function () {
        var $toggle = $(this).closest ('.radyk-toggle');
        var action  = $toggle.data ('action');
        var nonce   = $toggle.data ('nonce');

        if (! action) {return;}

        $.post (ajaxurl, {
            action: action,
            _wpnonce: nonce,
            value: this.checked ? '1' : '0'
        }).done (function (response) {
            if (! response.success) {
                // Revert on failure
                $toggle.find ('input').prop ('checked', ! this.checked);
                alert (response.data || 'Operation failed');
            }
        }).fail (function () {
            $toggle.find ('input').prop ('checked', ! this.checked);
            alert ('Network error');
        });
    });

    // Copy to clipboard helper
    window.radykCopyToClipboard = function (text) {
        navigator.clipboard.writeText (text).then (function () {
            // Show temporary toast
            var $toast = $('<div class="radyk-toast">Copied!</div>');
            $('body').append ($toast);
            setTimeout (function () {$toast.fadeOut (300, function () {$toast.remove();});}, 2000);
        });
    };

    // Auto-dismiss notices after 5 seconds
    $(document).ready (function () {
        setTimeout (function () {
            $('.notice.is-dismissible').fadeOut (300);
        }, 5000);
    });

})(jQuery);