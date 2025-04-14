/**
 * MSO Meta Description Settings AJAX Handler
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
jQuery(document).ready(function($) {
     $(".wp-hide-pw").on("click", function(){
         var button = $(this);
         var input = button.prev("input");
         if (input.attr("type") === "password") {
             input.attr("type", "text");
             button.find(".dashicons").removeClass("dashicons-hidden").addClass("dashicons-visibility");
             button.attr("aria-label", "' . esc_js(__('Hide password', 'mso-meta-description')) . '");
         } else {
             input.attr("type", "password");
             button.find(".dashicons").removeClass("dashicons-visibility").addClass("dashicons-hidden");
             button.attr("aria-label", "' . esc_js(__('Show password', 'mso-meta-description')) . '");
         }
     });

    var $form = $('#mso-settings-form');
    var $submitButton = $('#mso-submit-button');
    var $messagesDiv = $('#mso-settings-messages');
    var $navTabs = $('.nav-tab-wrapper a.nav-tab'); // Select tab links

    if ($form.length === 0) {
        return;
    }

    var originalButtonText = $submitButton.val();

    $form.on('submit', function(e) {
        e.preventDefault();

        // --- START: Get Active Tab ---
        var activeTabSlug = '';
        var $activeTabLink = $navTabs.filter('.nav-tab-active');

        if ($activeTabLink.length) {
            // Extract tab slug from the href (more reliable than relying on URL param after load)
            var urlParams = new URLSearchParams($activeTabLink.attr('href'));
            activeTabSlug = urlParams.get('tab');
        } else {
            // Fallback: try to get from URL (less reliable if URL changes without reload)
            var currentUrlParams = new URLSearchParams(window.location.search);
            activeTabSlug = currentUrlParams.get('tab');
        }
        // If still empty, try the first tab's slug (you might need a better default)
        if (!activeTabSlug && $navTabs.length > 0) {
            var firstTabUrlParams = new URLSearchParams($navTabs.first().attr('href'));
            activeTabSlug = firstTabUrlParams.get('tab');
        }
        // Basic validation if needed: if (!activeTabSlug) { console.error("Could not determine active tab"); return; }
        // --- END: Get Active Tab ---


        $submitButton.val(msoSettingsAjax.saving_text).prop('disabled', true);
        $messagesDiv.removeClass('notice-success notice-error is-dismissible').empty().hide();

        var formData = $form.serialize();
        var data = formData +
            '&action=' + encodeURIComponent(msoSettingsAjax.action) +
            '&nonce=' + encodeURIComponent(msoSettingsAjax.nonce) +
            '&active_tab=' + encodeURIComponent(activeTabSlug); // <-- ADD ACTIVE TAB HERE

        $.ajax({
            url: msoSettingsAjax.ajax_url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                // ... (keep existing success handling) ...
                if (response.success) {
                    $messagesDiv.addClass('notice notice-success is-dismissible')
                        .html('<p>' + response.data.message + '</p>')
                        .show();
                    // Add dismiss button
                    $messagesDiv.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
                    $messagesDiv.find('.notice-dismiss').on('click', function() { /* ... dismiss code ... */ });

                } else {
                    var errorMessage = response.data.message || msoSettingsAjax.error_text;
                    if (response.data.errors) { /* ... error details ... */ }
                    $messagesDiv.addClass('notice notice-error is-dismissible')
                        .html('<p>' + errorMessage + '</p>')
                        .show();
                    // Add dismiss button
                    $messagesDiv.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
                    $messagesDiv.find('.notice-dismiss').on('click', function() { /* ... dismiss code ... */ });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // ... (keep existing error handling) ...
                console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);
                var errorMsg = msoSettingsAjax.error_text + ' (' + textStatus + ')';
                if (jqXHR.responseText) { errorMsg += '<br><pre>...</pre>'; }
                $messagesDiv.addClass('notice notice-error')
                    .html('<p>' + errorMsg + '</p>')
                    .show();
            },
            complete: function() {
                $submitButton.val(originalButtonText).prop('disabled', false);
            }
        });
    });

    // JS for notice dismiss buttons (simplified example)
    $('body').on('click', '#mso-settings-messages .notice-dismiss', function() {
        $(this).closest('.notice').fadeTo(100, 0, function() {
            $(this).slideUp(100, function() {
                $(this).remove(); // Or just empty/hide: $messagesDiv.removeClass('notice notice-success notice-error is-dismissible').empty().hide();
            });
        });
    });


});