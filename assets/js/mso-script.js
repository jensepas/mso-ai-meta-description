/**
 * MSO Meta Description JavaScript
 *
 * Handles client-side interactions for the MSO Meta Description plugin, including:
 * - Character counting for the meta description field in the post editor.
 * - AJAX requests to generate AI summaries for the meta description.
 * - AJAX requests to fetch available AI models on the settings page.
 * - Toggling password visibility for API key fields on the settings page.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */

(function ($) {
    'use strict'; // Enforce stricter parsing and error handling in JavaScript.

    // --- Cache jQuery Selectors ---
    // Cache frequently used DOM elements to improve performance by avoiding repeated lookups.

    // Meta Box Elements (likely on post edit screens)
    const $metaBoxField = $('#mso_meta_description_field'); // Textarea for the meta description.
    const $charCountSpan = $('.mso-char-count'); // Span to display the character count.
    const $lengthIndicatorSpan = $('.mso-length-indicator'); // Span to display length status (e.g., "Good", "Too short").
    const $generateButtons = $('.mso-generate-button'); // Buttons to trigger AI generation.
    const $spinner = $('.mso-ai-generator .spinner'); // Spinner icon shown during AJAX calls in the meta box.
    const $aiErrorContainer = $('#mso-ai-error'); // Container to display AI generation errors in the meta box.
    const $content = $('#content'); // Classic editor content textarea (fallback).

    // Settings Page Elements (likely on options-general.php?page=admin_mso_meta_description)
    const $mistralSelect = $('#mso_meta_description_mistral_model_id'); // Select dropdown for Mistral models.
    const $geminiSelect = $('#mso_meta_description_gemini_model_id'); // Select dropdown for Gemini models.
    const $openaiSelect = $('#mso_meta_description_openai_model_id'); // Select dropdown for OpenAI models.

    // --- Get Localized Variables ---
    // Retrieve variables passed from PHP using wp_localize_script.
    // Provide default values in case the script variables are not properly localized.
    /* global msoScriptVars */ // Inform linters that msoScriptVars is a global variable.
    const {
        // Selected models (passed from PHP with defaults from settings)
        selectedGeminiModel = '',
        selectedOpenaiModel = '',
        selectedMistralModel = '',
        // API Key Status (boolean flags passed from PHP)
        geminiApiKeySet = false,
        openaiApiKeySet = false,
        mistralApiKeySet = false,
        // UI Strings & Config (localized strings and essential URLs/nonces)
        selectModel = '-- Select a Model --', // Default text for model dropdowns.
        errorLoadingModels = 'Error loading models.', // Generic error for model fetching.
        apiKeyMissingError = 'API key not set for this provider.', // Specific error when API key is missing.
        status = ['(Too short)', '(Too long)', '(Good)'], // Status indicators for description length.
        ajaxUrl = '', // URL for WordPress AJAX endpoint (should be admin_url('admin-ajax.php')).
        nonce = '' // Security nonce for AJAX requests.
    } = (typeof msoScriptVars !== 'undefined') ? msoScriptVars : {}; // Safely access msoScriptVars or use an empty object.

    // --- Constants ---
    // Define constants for recommended meta description lengths.
    const MIN_DESCRIPTION_LENGTH = 120;
    const MAX_DESCRIPTION_LENGTH = 160;

    // --- Helper Functions ---

    /**
     * Updates the character count display and color indicator in the meta box.
     * Reads the current value of the meta description textarea, calculates its length,
     * and updates the count span and length indicator span accordingly.
     */
    const updateCharacterCount = () => {
        // Only run if the meta description field exists on the current page.
        if (!$metaBoxField.length) return;

        const value = $metaBoxField.val() || ''; // Get textarea value, default to empty string.
        const length = value.length;
        let color = 'inherit'; // Default text color.
        let indicatorText = ''; // Default indicator text.

        // Determine color and indicator text based on length, only if there's content.
        if (length > 0) {
            if (length < MIN_DESCRIPTION_LENGTH) {
                color = 'orange'; // Use orange for descriptions that are too short.
                indicatorText = status[0]; // "(Too short)"
            } else if (length > MAX_DESCRIPTION_LENGTH) {
                color = 'red'; // Use red for descriptions that are too long.
                indicatorText = status[1]; // "(Too long)"
            } else {
                color = 'green'; // Use green for descriptions within the recommended length.
                indicatorText = status[2]; // "(Good)"
            }
        }

        // Update the text content of the spans and the color of the indicator.
        $charCountSpan.text(length);
        $lengthIndicatorSpan.text(indicatorText).css('color', color);
        // Optionally color the count number itself too:
        // $charCountSpan.css('color', color);
    };

    /**
     * Toggles the visibility of a password input field and updates the associated button icon/label.
     * This function is designed for the API key fields on the settings page.
     *
     * @param {string} inputID - The HTML ID of the password input field.
     * Assumes the toggle button has an ID of inputID + '-button'.
     */
    const togglePassword = (inputID) => {
        const passwordInput = document.getElementById(inputID);
        // Assumes button ID format based on input ID (e.g., 'mso_api_key_id-button').
        const toggleButton = document.getElementById(inputID + '-button');

        // Exit if either the input or the button element is not found.
        if (!passwordInput || !toggleButton) return;

        // Check if the input is currently hidden (type="password").
        const isHidden = passwordInput.type === "password";

        // Change input type between "password" and "text".
        passwordInput.type = isHidden ? "text" : "password";

        // Update the button's icon using WordPress Dashicons classes.
        const iconSpan = toggleButton.querySelector('.dashicons');
        if (iconSpan) {
            iconSpan.classList.toggle('dashicons-hidden', !isHidden); // Show 'hidden' icon when input is text.
            iconSpan.classList.toggle('dashicons-visibility', isHidden); // Show 'visibility' icon when input is password.
        }

        // Update the button's aria-label for accessibility.
        // Consider using localized strings passed from PHP for better translation support.
        toggleButton.setAttribute("aria-label", isHidden ? "Hide password" : "Show password");
    };

    /**
     * Extracts plain text from an HTML string.
     * Removes script/style tags, attempts to remove shortcodes, and trims whitespace.
     *
     * @param {string} html - The HTML content string.
     * @returns {string} The extracted plain text.
     */
    const getPlainTextFromHTML = (html) => {
        // Create a temporary, in-memory div element to parse the HTML.
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;

        // Remove script and style tags to avoid including their content.
        tempDiv.querySelectorAll('script, style').forEach(el => el.remove());

        // Use innerText if available (better approximates rendered text), fallback to textContent.
        let text = tempDiv.innerText || tempDiv.textContent || '';

        // Remove typical WordPress shortcodes (e.g., [shortcode attr="value"]).
        // Remove multiple consecutive whitespace characters and trim leading/trailing whitespace.
        text = text.replace(/\[.*?]/g, '').replace(/\s\s+/g, ' ').trim();
        return text;
    };

    /**
     * Fetches and populates a model selection dropdown for a given AI provider.
     * Handles showing/hiding a spinner, displaying errors, and pre-selecting the default model.
     *
     * @param {object} options - Configuration options.
     * @param {string} options.apiType - The provider identifier (e.g., 'mistral', 'gemini').
     * @param {jQuery} options.$select - The jQuery object for the select dropdown element.
     * @param {string} options.defaultModel - The model ID that should be selected by default.
     * @param {boolean} options.apiKeySet - Whether the API key for this provider is set.
     */
    const populateModelSelect = async ({ apiType, $select, defaultModel, apiKeySet }) => {
        // Don't run if the select dropdown doesn't exist on the current page.
        if (!$select.length) return;

        // --- Prepare UI ---
        // Clear previous options and errors.
        $select.empty();
        // Add the default placeholder option.
        $select.append(`<option value="">${selectModel}</option>`);
        // Find the specific error container for this provider.
        const $errorContainer = $('#mso-model-error-' + apiType);
        $errorContainer.text(''); // Clear any previous error messages.

        // --- Check API Key ---
        if (!apiKeySet) {
            // If the API key isn't set, display an error and disable the dropdown.
            $errorContainer.text(apiKeyMissingError);
            $select.prop('disabled', true);
            return; // Stop execution for this provider.
        } else {
            // Ensure the dropdown is enabled if the key is set.
            $select.prop('disabled', false);
        }

        // Show spinner next to the select element (assumes spinner exists as a sibling).
        $select.siblings('.spinner').css('visibility', 'visible');

        // --- Fetch Models via AJAX ---
        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    // Standard header for form data POST requests.
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                // Construct the request body with action, nonce, and provider type.
                body: new URLSearchParams({
                    action: 'mso_fetch_models', // Matches the WP AJAX action hook.
                    nonce: nonce, // Security nonce.
                    apiType: apiType, // The provider identifier.
                }),
            });

            // Check if the HTTP response status indicates an error (e.g., 404, 500).
            if (!response.ok) {
                const errorText = await response.text(); // Try to get more details from the response body.
                throwError(`HTTP error ${response.status}: ${errorText}`); // Throw an error with details.
            }

            // Parse the JSON response body.
            const result = await response.json();

            // Check the 'success' property within the JSON response (WordPress standard).
            if (!result.success) {
                // If 'success' is false, throw the error message provided in the 'data.message' property.
                throwError(result.data?.message || 'Unknown error fetching models.');
            }

            // Extract the models array from the 'data' property.
            const models = result.data; // Expecting an array like [{id: '...', displayName: '...'}, ...]

            // Validate the received models data.
            if (!models || !Array.isArray(models) || models.length === 0) {
                throwError('No compatible models found or returned by the API.');
            }

            // --- Populate Select Options ---
            models.forEach(model => {
                // Ensure the model object and its 'id' property exist.
                if (model && model.id) {
                    // Use 'displayName' if available (especially for Gemini), otherwise fallback to 'id'.
                    const displayName = model.displayName || model.id;
                    // The option's value should always be the model ID.
                    const value = model.id;
                    // Create and append the new option element.
                    $select.append($('<option>', {
                        value: value,
                        text: displayName,
                        // Mark the option as selected if its value matches the defaultModel.
                        selected: value === defaultModel
                    }));
                }
            });

            // --- Set Selected Value ---
            // Ensure the default value is selected if it exists in the populated list.
            // Note: Using .val() works correctly even if the option was already marked selected during creation.
            if (defaultModel && $select.find(`option[value="${defaultModel}"]`).length > 0) {
                $select.val(defaultModel);
            } else if ($select.find('option').length > 1) {
                // If the default model wasn't found in the list (or wasn't set),
                // select the first *actual* model option (index 1, after the placeholder).
                $select.prop('selectedIndex', 1);
            }

        } catch (err) {
            // --- Handle Errors ---
            console.error(`Error loading ${apiType} models:`, err);
            // Display a user-friendly error message in the designated container.
            $errorContainer.text(errorLoadingModels + ' ' + (err.message || ''));
            // Reset the select dropdown to show only an error message.
            $select.html(`<option value="">${errorLoadingModels}</option>`);
        } finally {
            // --- Final UI Cleanup ---
            // Hide the spinner regardless of success or failure.
            $select.siblings('.spinner').css('visibility', 'hidden');
        }
    };

    /**
     * Retrieves post content, sends an AJAX request to generate a summary for the specified provider,
     * and updates the meta description textarea with the result. Handles loading states and errors.
     *
     * @param {string} provider - The AI provider identifier (e.g., 'mistral', 'gemini', 'openai').
     */
    const summarizeContent = async (provider) => {
        // Only run if the meta description field exists on the current page.
        if (!$metaBoxField.length) return;

        // --- Prepare UI for Loading ---
        $spinner.css('visibility', 'visible'); // Show the spinner.
        $aiErrorContainer.text(''); // Clear any previous error messages.
        $generateButtons.prop('disabled', true); // Disable all generate buttons during the request.

        try {
            // --- Get Post Content ---
            let htmlContent = '';
            // Try to get content from the Gutenberg editor using the WordPress data API.
            if (typeof wp !== 'undefined' && wp.data?.select('core/editor')?.getEditedPostContent) {
                htmlContent = wp.data.select('core/editor').getEditedPostContent() || '';
            }
            // If Gutenberg content is empty or unavailable, fallback to the classic editor textarea.
            if (!htmlContent && $content.length) {
                htmlContent = $content.val() || '';
            }

            // Check if content was successfully retrieved.
            if (!htmlContent) {
                throwError('Could not retrieve post content.');
            }

            // Convert the HTML content to plain text.
            const plainText = getPlainTextFromHTML(htmlContent);

            // Check if there's any text left after processing.
            if (!plainText) {
                throwError('Content is empty after processing.');
            }

            // --- Generate Summary via AJAX ---
            const response = await fetch(ajaxUrl, { // Use the localized ajaxUrl.
                method: 'POST',
                headers: {
                    // Specify content type and charset for the request body.
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                // Construct the request body.
                body: new URLSearchParams({
                    action: 'mso_generate_summary', // Matches the WP AJAX action hook.
                    nonce: nonce, // Security nonce.
                    content: plainText, // The processed post content.
                    provider: provider // The selected AI provider.
                })
            });

            // --- Handle HTTP Response ---
            if (!response.ok) { // Check for HTTP errors (e.g., 4xx, 5xx).
                const errorText = await response.text(); // Get raw error text from response.
                try {
                    // Try to parse the error text as JSON (WordPress often sends JSON errors).
                    const jsonError = JSON.parse(errorText);
                    // Use the message from the JSON data if available, otherwise use the HTTP status.
                    throwError(jsonError.data?.message || `HTTP error ${response.status}`);
                } catch (e) {
                    // If parsing as JSON fails, use the raw error text.
                    throwError(`HTTP error ${response.status}: ${errorText}`);
                }
            }

            // --- Handle Successful Response ---
            const result = await response.json(); // Parse the successful JSON response.

            // Check if the response indicates success and contains the summary data.
            if (result.success && result.data?.summary) {
                // Update the meta description textarea value with the generated summary.
                // Trigger the 'input' event manually to ensure the character count updates.
                $metaBoxField.val(result.data.summary).trigger('input');
            } else {
                // If success is false or summary is missing, throw the error message from the response.
                throwError(result.data?.message || 'Unknown error during summary generation.');
            }
        } catch (err) {
            // --- Handle Errors (Catch Block) ---
            console.error('Summarization Error:', err);
            // Display the error message to the user in the designated container.
            $aiErrorContainer.text('Error: ' + (err.message || 'Failed to generate summary.'));
        } finally {
            // --- Final UI Cleanup ---
            $spinner.css('visibility', 'hidden'); // Hide the spinner.
            $generateButtons.prop('disabled', false); // Re-enable the generate buttons.
            updateCharacterCount(); // Update character count after potential change or error.
        }
    };

    /**
     * Simple error throwing helper.
     * Ensures that an actual Error object is thrown.
     * @param {string|*} data - The error message or data.
     */
    const throwError = (data) => {
        // If data is falsy (e.g., empty string, null), throw a generic error.
        // Otherwise, throw a new Error object with the provided data as the message.
        if (!data) throw new Error('An unknown error occurred.');
        throw new Error(data);
    }

    // --- Document Ready ---
    // Execute code once the DOM is fully loaded and ready.
    $(document).ready(() => {

        // --- Meta Box Specific Initializations ---
        // Check if the meta description textarea exists on the page.
        if ($metaBoxField.length) {
            updateCharacterCount(); // Perform initial character count on page load.
            // Attach event listeners to update the count whenever the textarea content changes.
            $metaBoxField.on('keyup input paste change', updateCharacterCount);

            // Attach click handler to the container for AI generator buttons.
            // Using event delegation on the container is slightly more robust if buttons are added dynamically.
            $('.mso-ai-generator').on('click', '.mso-generate-button', function () {
                // Get the provider identifier from the button's 'data-provider' attribute.
                const provider = $(this).data('provider');
                if (provider) {
                    // Call the summarizeContent function asynchronously (using void to indicate no need to await here).
                    void summarizeContent(provider);
                }
            });
        }

        // --- Settings Page Specific Initializations ---
        // Check if any of the model select dropdowns exist on the page.
        if ($mistralSelect.length || $geminiSelect.length || $openaiSelect.length) {

            // Populate Mistral models dropdown if it exists.
            void populateModelSelect({
                apiType: 'mistral',
                $select: $mistralSelect,
                defaultModel: selectedMistralModel, // Use the model saved in settings.
                apiKeySet: mistralApiKeySet // Use the API key status flag.
            });

            // Populate Gemini models dropdown if it exists.
            void populateModelSelect({
                apiType: 'gemini',
                $select: $geminiSelect,
                defaultModel: selectedGeminiModel,
                apiKeySet: geminiApiKeySet
            });

            // Populate OpenAI models dropdown if it exists.
            void populateModelSelect({
                apiType: 'openai',
                $select: $openaiSelect, // Use the corrected selector.
                defaultModel: selectedOpenaiModel,
                apiKeySet: openaiApiKeySet
            });

            // Attach handlers for password toggle buttons.
            // Find all password inputs whose names start with 'mso_meta_description_'.
            $('input[type="password"][name^="mso_meta_description_"]').each(function () {
                const inputId = $(this).attr('id'); // Get the ID of the input field.
                // Assume the corresponding button ID follows the pattern 'inputId-button'.
                const button = $('#' + inputId + '-button');
                // If the button exists, attach the click handler.
                if (button.length) {
                    button.on('click', () => togglePassword(inputId));
                }
            });
        }

    }); // End document ready

})(jQuery); // End of IIFE (Immediately Invoked Function Expression)