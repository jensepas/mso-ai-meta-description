/**
 * MSO Meta Description Admin Script
 */

(function ($) {
    'use strict';

    // --- Cache jQuery Selectors ---
    // Meta Box Elements (likely on post edit screens)
    const $metaBoxField = $('#mso_meta_description_field');
    const $charCountSpan = $('.mso-char-count'); // Use class from template
    const $lengthIndicatorSpan = $('.mso-length-indicator'); // Use class from template
    const $generateButtons = $('.mso-generate-button'); // Use class for all generate buttons
    const $spinner = $('.mso-ai-generator .spinner');
    const $aiErrorContainer = $('#mso-ai-error');
    const $content = $('#content');

    // Settings Page Elements (likely on options-general.php?page=admin_mso_meta_description)
    const $mistralSelect = $('#mso_meta_description_mistral_model');
    const $geminiSelect = $('#mso_meta_description_gemini_model');
    const $openaiSelect = $('#mso_meta_description_openai_model'); // <-- Corrected ID

    // --- Get Localized Variables ---
    // Ensure all expected variables are present with defaults
    /* global msoScriptVars */
    const {
        // Selected models (passed from PHP with defaults)
        selectedGeminiModel = '',
        selectedOpenaiModel = '',
        selectedMistralModel = '',
        // API Key Status (passed from PHP)
        geminiApiKeySet = false,
        openaiApiKeySet = false,
        mistralApiKeySet = false,
        // UI Strings & Config
        selectModel = '-- Select a Model --',
        errorLoadingModels = 'Error loading models.',
        apiKeyMissingError = 'API key not set for this provider.', // New string
        status = ['(Too short)', '(Too long)', '(Good)'],
        ajaxUrl = '', // Should be admin_url('admin-ajax.php')
        nonce = '' // Should be wp_create_nonce('wp_rest') or custom nonce
    } = (typeof msoScriptVars !== 'undefined') ? msoScriptVars : {};

    // --- Constants ---
    const MIN_DESCRIPTION_LENGTH = 120;
    const MAX_DESCRIPTION_LENGTH = 160;

    // --- Helper Functions ---

    /**
     * Updates the character count display and color indicator in the meta box.
     */
    const updateCharacterCount = () => {
        if (!$metaBoxField.length) return; // Only run if field exists

        const value = $metaBoxField.val() || '';
        const length = value.length;
        let color = 'inherit'; // Default color
        let indicatorText = '';

        if (length > 0) { // Only show color/indicator if there's text
            if (length < MIN_DESCRIPTION_LENGTH) {
                color = 'orange';
                indicatorText = status[0];
            } else if (length > MAX_DESCRIPTION_LENGTH) {
                color = 'red';
                indicatorText =  status[1];
            } else {
                color = 'green';
                indicatorText =  status[2];
            }
        }

        $charCountSpan.text(length);
        $lengthIndicatorSpan.text(indicatorText).css('color', color);
        // Optionally color the count number itself too
        // $charCountSpan.css('color', color);
    };

    /**
     * Toggles the visibility of a password input field.
     * Assumes button ID is inputID + '-button'
     */
    const togglePassword = (inputID) => {
        const passwordInput = document.getElementById(inputID);
        const toggleButton = document.getElementById(inputID + '-button'); // Assumes button ID format

        if (!passwordInput || !toggleButton) return; // Exit if elements not found

        const isHidden = passwordInput.type === "password";

        // Change input type
        passwordInput.type = isHidden ? "text" : "password";

        // Update button icon (use WordPress dashicons classes)
        const iconSpan = toggleButton.querySelector('.dashicons');
        if (iconSpan) {
            iconSpan.classList.toggle('dashicons-hidden', !isHidden);
            iconSpan.classList.toggle('dashicons-visibility', isHidden);
        }

        // Update button aria-label (consider using localized strings from PHP if needed)
        toggleButton.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
    };

    /**
     * Gets plain text from HTML content, removing shortcodes.
     */
    const getPlainTextFromHTML = (html) => {
        // Use a temporary element to parse HTML and extract text
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        // Remove script and style tags first
        tempDiv.querySelectorAll('script, style').forEach(el => el.remove());
        // Get innerText, which approximates rendered text
        let text = tempDiv.innerText || tempDiv.textContent || '';
        // Remove typical shortcodes and extra whitespace
        text = text.replace(/\[.*?]/g, '').replace(/\s\s+/g, ' ').trim();
        return text;
    };

    /**
     * Populates a model select dropdown for a given API type.
     */
    const populateModelSelect = async ({apiType, $select, defaultModel, apiKeySet}) => {
        if (!$select.length) return; // Don't run if the select doesn't exist on this page

        // Clear previous errors/options
        $select.empty();
        $select.append(`<option value="">${selectModel}</option>`);
        const $errorContainer = $('#mso-model-error-' + apiType); // Find error container
        $errorContainer.text(''); // Clear previous errors

        if (!apiKeySet) {
            $errorContainer.text(apiKeyMissingError);
            $select.prop('disabled', true); // Disable select if no key
            return; // Stop if API key is not set
        } else {
            $select.prop('disabled', false); // Enable if key is set
        }

        $select.siblings('.spinner').css('visibility', 'visible'); // Show spinner if available

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'mso_fetch_models',
                    nonce: nonce,
                    apiType: apiType,
                }),
            });

            if (!response.ok) { // Check HTTP status first
                const errorText = await response.text(); // Try to get error text
                throwError(`HTTP error ${response.status}: ${errorText}`);
            }

            const result = await response.json();

            if (!result.success) {
                // Throw error message from server response
                throwError(result.data?.message || 'Unknown error fetching models.');
            }

            const models = result.data; // Expecting array of model objects {id: '...', displayName: '...'}

            if (!models || !Array.isArray(models) || models.length === 0) {
                throwError('No compatible models found or returned by the API.');
            }

            // Populate the select options
            models.forEach(model => {
                if (model && model.id) { // Ensure model object and id exist
                    // Use displayName if available (esp. for Gemini), otherwise use id
                    const displayName = model.displayName || model.id;
                    // Value should always be the model id
                    const value = model.id;
                    $select.append($('<option>', {
                        value: value,
                        text: displayName,
                        selected: value === defaultModel // Check if this model is the default
                    }));
                }
            });

            // Ensure the default value is selected if it exists in the populated list
            // Note: .val() works even if the option was already marked selected above
            if (defaultModel && $select.find(`option[value="${defaultModel}"]`).length > 0) {
                $select.val(defaultModel);
            } else if ($select.find('option').length > 1) {
                // If default not found, select the first actual model option (index 1)
                $select.prop('selectedIndex', 1);
            }


        } catch (err) {
            console.error(`Error loading ${apiType} models:`, err);
            $errorContainer.text(errorLoadingModels + ' ' + (err.message || '')); // Display error
            $select.html(`<option value="">${errorLoadingModels}</option>`); // Reset select on error
        } finally {
            $select.siblings('.spinner').css('visibility', 'hidden'); // Hide spinner
        }
    };

    /**
     * Gets content, calls AJAX to generate summary, and updates the meta box field.
     */
    const summarizeContent = async (provider) => {
        if (!$metaBoxField.length) return; // Only run if meta box field exists

        $spinner.css('visibility', 'visible'); // Show spinner
        $aiErrorContainer.text(''); // Clear previous errors
        $generateButtons.prop('disabled', true); // Disable buttons during generation

        try {
            // Try getting content from Gutenberg editor first
            let htmlContent = '';
            if (typeof wp !== 'undefined' && wp.data?.select('core/editor')?.getEditedPostContent) {
                htmlContent = wp.data.select('core/editor').getEditedPostContent() || '';
            }
            // Fallback to classic editor textarea (may not exist if Gutenberg only)
            if (!htmlContent && $content.length) {
                htmlContent = $content.val() || '';
            }

            if (!htmlContent) {
                throwError('Could not retrieve post content.');
            }

            const plainText = getPlainTextFromHTML(htmlContent);

            if (!plainText) {
                throwError('Content is empty after processing.');
            }

            const response = await fetch(ajaxUrl, { // Use ajaxUrl from msoScriptVars
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    action: 'mso_generate_summary',
                    nonce: nonce,
                    content: plainText,
                    provider: provider // Pass the specific provider ('mistral', 'gemini', 'openai')
                })
            });

            console.log(response);
            if (!response.ok) { // Check HTTP status

                const errorText = await response.text();
                try { // Try to parse JSON error from server first
                    const jsonError = JSON.parse(errorText);
                    throwError(jsonError.data?.message || `HTTP error ${response.status}`);
                } catch (e) { // Fallback to plain text error
                    throwError(`HTTP error ${response.status}: ${errorText}`);
                }
            }

            const result = await response.json();

            if (result.success && result.data?.summary) {
                $metaBoxField.val(result.data.summary).trigger('input'); // Update field and trigger input event for count
            } else {
                // Throw error message from server response
                throwError(result.data?.message || 'Unknown error during summary generation.');
            }
        } catch (err) {
            console.error('Summarization Error:', err);
            $aiErrorContainer.text('Error: ' + (err.message || 'Failed to generate summary.')); // Show error to user
        } finally {
            $spinner.css('visibility', 'hidden'); // Hide spinner
            $generateButtons.prop('disabled', false); // Re-enable buttons
            updateCharacterCount(); // Update count after potential change or error
        }
    };

    const throwError = (data) => {
        if (!data) throw new Error(data);
    }

    // --- Document Ready ---
    $(document).ready(() => {

        // --- Meta Box Specific Initializations ---
        if ($metaBoxField.length) {
            updateCharacterCount(); // Initial count
            // Update count on keyup, paste, input events
            $metaBoxField.on('keyup input paste change', updateCharacterCount);

            // Attach click handler to generate buttons (using event delegation is slightly more robust)
            $('.mso-ai-generator').on('click', '.mso-generate-button', function () {
                const provider = $(this).data('provider'); // Get provider from button's data attribute
                if (provider) {
                    void summarizeContent(provider);
                }
            });
        }

        // --- Settings Page Specific Initializations ---
        if ($mistralSelect.length || $geminiSelect.length || $openaiSelect.length) {
            // Populate Mistral models if select exists and key is set
            void populateModelSelect({
                apiType: 'mistral',
                $select: $mistralSelect,
                defaultModel: selectedMistralModel, // Use selected model from PHP
                apiKeySet: mistralApiKeySet
            });

            // Populate Gemini models if select exists and key is set
            void populateModelSelect({
                apiType: 'gemini',
                $select: $geminiSelect,
                defaultModel: selectedGeminiModel,
                apiKeySet: geminiApiKeySet
            });

            // Populate OpenAI models if select exists and key is set
            void populateModelSelect({
                apiType: 'openai',
                $select: $openaiSelect, // Use corrected selector
                defaultModel: selectedOpenaiModel,
                apiKeySet: openaiApiKeySet
            });

            // Attach handlers for password toggles (find buttons more dynamically)
            $('input[type="password"][name^="mso_meta_description_"]').each(function () {
                const inputId = $(this).attr('id');
                const button = $('#' + inputId + '-button'); // Assumes button ID format
                if (button.length) {
                    button.on('click', () => togglePassword(inputId));
                }
            });
        }

    }); // End document ready

})(jQuery);