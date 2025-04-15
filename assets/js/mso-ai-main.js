/**
 * MSO AI Meta Description JavaScript Unified
 *
 * Handles client-side interactions for the MSO AI Meta Description plugin, including:
 * - Character counting for meta description field
 * - AI summary generation via AJAX
 * - Model fetching for different AI providers
 * - Settings page form handling
 * - Password visibility toggling
 *
 * @package MSO_AI_Meta_Description
 * @since   1.3.0
 */

(function ($) {
    'use strict';

    // --- Constants ---
    const MIN_DESCRIPTION_LENGTH = 120;
    const MAX_DESCRIPTION_LENGTH = 160;

    // --- Cache DOM Elements ---
    // Meta Box Elements
    const $metaBoxField = $('#mso_ai_meta_description_field');
    const $charCountSpan = $('.mso-ai-char-count');
    const $lengthIndicatorSpan = $('.mso-ai-length-indicator');
    const $generateButtons = $('.mso-ai-generate-button');
    const $spinner = $('.mso-ai-generator .spinner');
    const $aiErrorContainer = $('#mso-ai-error');
    const $content = $('#content');

    // Settings Page Elements
    const $mistralSelect = $('#mso_ai_meta_description_mistral_model_id');
    const $geminiSelect = $('#mso_ai_meta_description_gemini_model_id');
    const $openaiSelect = $('#mso_ai_meta_description_openai_model_id');
    const $anthropicSelect = $('#mso_ai_meta_description_anthropic_model_id');
    const $form = $('#mso-ai-settings-form');
    const $submitButton = $('#mso-ai-submit-button');
    const $messagesDiv = $('#mso-ai-settings-messages');
    const $navTabs = $('.nav-tab-wrapper a.nav-tab');
    const $passwordInputs = $('input[type="password"][name^="AI_Meta_Description_"]');
    const $passwordToggleButtons = $('.wp-hide-pw');

    // --- Get Localized Variables ---
    /* global msoAiScriptVars */

    // Script Variables (with defaults)
    const {
        // Selected models
        selectedGeminiModel = '',
        selectedOpenaiModel = '',
        selectedMistralModel = '',
        selectedAnthropicModel = '',
        // API Key Status
        geminiApiKeySet = false,
        openaiApiKeySet = false,
        mistralApiKeySet = false,
        anthropicApiKeySet = false,
        // UI Strings & Config
        selectModel = '-- Select a Model --',
        errorLoadingModels = 'Error loading models.',
        apiKeyMissingError = 'API key not set for this provider.',
        status = ['(Too short)', '(Too long)', '(Good)'],
        ajaxUrl = '',
        nonce = '',
        action = 'save_mso_ai_settings',
        saving_text = 'Saving...',
        error_text = 'An error occurred.',
        i18n_show_password = 'Show password',
        i18n_hide_password = 'Hide password'
    } = (typeof msoAiScriptVars !== 'undefined') ? msoAiScriptVars : {};

    // --- Helper Functions ---

    /**
     * Simple error throwing helper.
     * @param {string|*} data - The error message or data.
     */
    const throwError = (data) => {
        if (!data) throw new Error('An unknown error occurred.');
        throw new Error(data);
    };

    /**
     * Updates the character count display and color indicator.
     */
    const updateCharacterCount = () => {
        if (!$metaBoxField.length) return;

        const value = $metaBoxField.val() || '';
        const length = value.length;
        let color = 'inherit';
        let indicatorText = '';

        if (length > 0) {
            if (length < MIN_DESCRIPTION_LENGTH) {
                color = 'orange';
                indicatorText = status[0]; // "(Too short)"
            } else if (length > MAX_DESCRIPTION_LENGTH) {
                color = 'red';
                indicatorText = status[1]; // "(Too long)"
            } else {
                color = 'green';
                indicatorText = status[2]; // "(Good)"
            }
        }

        $charCountSpan.text(length);
        $lengthIndicatorSpan.text(indicatorText).css('color', color);
    };

    /**
     * Toggles password visibility for an input field.
     * @param {string|Element} input - Input element or ID.
     */
    const togglePassword = (input) => {
        const passwordInput = typeof input === 'string' ? document.getElementById(input) : input;
        const toggleButton = $(passwordInput).next('.wp-hide-pw')[0] ||
            document.getElementById(passwordInput.id + '-button');

        if (!passwordInput || !toggleButton) return;

        const isPassword = passwordInput.type === "password";
        const newType = isPassword ? "text" : "password";
        const iconClass = isPassword ?
            ["dashicons-visibility", "dashicons-hidden"] :
            ["dashicons-hidden", "dashicons-visibility"];
        const label = isPassword ? i18n_hide_password : i18n_show_password;

        passwordInput.type = newType;

        const iconSpan = toggleButton.querySelector('.dashicons');
        if (iconSpan) {
            iconSpan.classList.remove(iconClass[0]);
            iconSpan.classList.add(iconClass[1]);
        }

        toggleButton.setAttribute("aria-label", label);
    };

    /**
     * Extracts plain text from HTML content.
     * @param {string} html - HTML content string.
     * @returns {string} Plain text.
     */
    const getPlainTextFromHTML = (html) => {
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;

        tempDiv.querySelectorAll('script, style').forEach(el => el.remove());

        let text = tempDiv.innerText || tempDiv.textContent || '';
        text = text.replace(/\[.*?]/g, '').replace(/\s\s+/g, ' ').trim();
        return text;
    };

    /**
     * Populates a model selection dropdown for an AI provider.
     * @param {object} options - Configuration options.
     */
    const populateModelSelect = async ({ apiType, $select, defaultModel, apiKeySet }) => {
        if (!$select.length) return;

        $select.empty();
        $select.append(`<option value="">${selectModel}</option>`);
        const $errorContainer = $('#mso-ai-model-error-' + apiType);
        $errorContainer.text('');

        if (!apiKeySet) {
            $errorContainer.text(apiKeyMissingError);
            $select.prop('disabled', true);
            return;
        } else {
            $select.prop('disabled', false);
        }

        $select.siblings('.spinner').css('visibility', 'visible');

        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'mso_ai_fetch_models',
                    nonce: nonce,
                    apiType: apiType,
                }),
            });

            if (!response.ok) {
                const errorText = await response.text();
                throwError(`HTTP error ${response.status}: ${errorText}`);
            }

            const result = await response.json();

            if (!result.success) {
                throwError(result.data?.message || 'Unknown error fetching models.');
            }

            const models = result.data;

            if (!models || !Array.isArray(models) || models.length === 0) {
                throwError('No compatible models found or returned by the API.');
            }

            models.forEach(model => {
                if (model && model.id) {
                    const displayName = model.displayName || model.id;
                    const value = model.id;
                    $select.append($('<option>', {
                        value: value,
                        text: displayName,
                        selected: value === defaultModel
                    }));
                }
            });

            if (defaultModel && $select.find(`option[value="${defaultModel}"]`).length > 0) {
                $select.val(defaultModel);
            } else if ($select.find('option').length > 1) {
                $select.prop('selectedIndex', 1);
            }

        } catch (err) {
            console.error(`Error loading ${apiType} models:`, err);
            $errorContainer.text(errorLoadingModels + ' ' + (err.message || ''));
            $select.html(`<option value="">${errorLoadingModels}</option>`);
        } finally {
            $select.siblings('.spinner').css('visibility', 'hidden');
        }
    };

    /**
     * Generates a summary of post content using the specified AI provider.
     * @param {string} provider - AI provider identifier.
     */
    const summarizeContent = async (provider) => {
        if (!$metaBoxField.length) return;

        $spinner.css('visibility', 'visible');
        $aiErrorContainer.text('');
        $generateButtons.prop('disabled', true);

        try {
            let htmlContent = '';
            if (typeof wp !== 'undefined' && wp.data?.select('core/editor')?.getEditedPostContent) {
                htmlContent = wp.data.select('core/editor').getEditedPostContent() || '';
            }
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

            const response = await fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    action: 'mso_ai_generate_summary',
                    nonce: nonce,
                    content: plainText,
                    provider: provider
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                try {
                    const jsonError = JSON.parse(errorText);
                    throwError(jsonError.data?.message || `HTTP error ${response.status}`);
                } catch (e) {
                    throwError(`HTTP error ${response.status}: ${errorText}`);
                }
            }

            const result = await response.json();

            if (result.success && result.data?.summary) {
                $metaBoxField.val(result.data.summary).trigger('input');
            } else {
                throwError(result.data?.message || 'Unknown error during summary generation.');
            }
        } catch (err) {
            console.error('Summarization Error:', err);
            $aiErrorContainer.text('Error: ' + (err.message || 'Failed to generate summary.'));
        } finally {
            $spinner.css('visibility', 'hidden');
            $generateButtons.prop('disabled', false);
            updateCharacterCount();
        }
    };

    /**
     * Gets the active tab slug from the settings page.
     * @returns {string} Active tab slug.
     */
    const getActiveTabSlug = () => {
        const $activeTabLink = $navTabs.filter('.nav-tab-active');
        if ($activeTabLink.length) {
            const urlParams = new URLSearchParams($activeTabLink.attr('href'));
            return urlParams.get('tab');
        }

        const currentUrlParams = new URLSearchParams(window.location.search);
        let tab = currentUrlParams.get('tab');

        if (!tab && $navTabs.length) {
            const firstTabParams = new URLSearchParams($navTabs.first().attr('href'));
            tab = firstTabParams.get('tab');
        }

        return tab || '';
    };

    /**
     * Display a message in the settings form.
     * @param {string} type - Message type (success/error).
     * @param {string} message - Message content.
     */
    const displayMessage = (type, message) => {
        const noticeClass = `notice notice-${type} is-dismissible`;
        const dismissButton = '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';

        $messagesDiv
            .removeClass('notice-success notice-error is-dismissible')
            .addClass(noticeClass)
            .html(`<p>${message}</p>${dismissButton}`)
            .show();
    };

    /**
     * Handle AJAX errors from settings form submission.
     * @param {object} jqXHR - jQuery XHR object.
     * @param {string} textStatus - Error status text.
     * @param {string} errorThrown - Error message.
     */
    const handleAjaxError = (jqXHR, textStatus, errorThrown) => {
        console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText);

        let errorMsg = `${error_text} (${textStatus})`;
        if (jqXHR.responseText) {
            errorMsg += `<br><pre>${jqXHR.responseText}</pre>`;
        }

        displayMessage('error', errorMsg);
    };

    // --- Initialization ---
    $(document).ready(() => {
        // Initialize Meta Box functionality
        if ($metaBoxField.length) {
            updateCharacterCount();
            $metaBoxField.on('keyup input paste change', updateCharacterCount);

            $('.mso-ai-generator').on('click', '.mso-ai-generate-button', function () {
                const provider = $(this).data('provider');
                if (provider) {
                    void summarizeContent(provider);
                }
            });
        }

        // Initialize Settings Page functionality
        if ($mistralSelect.length || $geminiSelect.length || $openaiSelect.length || $anthropicSelect.length) {
            // Populate model selects

            void populateModelSelect({
                apiType: 'mistral',
                $select: $mistralSelect,
                defaultModel: selectedMistralModel,
                apiKeySet: mistralApiKeySet
            });

            void populateModelSelect({
                apiType: 'gemini',
                $select: $geminiSelect,
                defaultModel: selectedGeminiModel,
                apiKeySet: geminiApiKeySet
            });

            void populateModelSelect({
                apiType: 'openai',
                $select: $openaiSelect,
                defaultModel: selectedOpenaiModel,
                apiKeySet: openaiApiKeySet
            });

            void populateModelSelect({
                apiType: 'anthropic',
                $select: $anthropicSelect,
                defaultModel: selectedAnthropicModel,
                apiKeySet: anthropicApiKeySet
            });
        }

        // Initialize Password Toggle buttons
        $passwordInputs.each(function() {
            const inputId = $(this).attr('id');
            const $button = $('#' + inputId + '-button');
            if ($button.length) {
                $button.on('click', () => togglePassword(inputId));
            }
        });

        $passwordToggleButtons.on('click', function() {
            togglePassword($(this).prev('input')[0]);
        });

        // Initialize Settings Form submission
        if ($form.length) {
            const originalButtonText = $submitButton.val();

            $form.on('submit', (e) => {
                e.preventDefault();

                const activeTabSlug = getActiveTabSlug();
                const formData = $form.serialize();
                const data = `${formData}&action=${encodeURIComponent(action)}&nonce=${encodeURIComponent(nonce)}&active_tab=${encodeURIComponent(activeTabSlug)}`;

                $submitButton.val(saving_text).prop('disabled', true);
                $messagesDiv.removeClass('notice-success notice-error is-dismissible').empty().hide();

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data,
                    dataType: 'json',
                    success(response) {
                        if (response.success) {
                            displayMessage('success', response.data.message);
                        } else {
                            const errorMessage = response.data.message || error_text;
                            displayMessage('error', errorMessage);
                        }
                    },
                    error: handleAjaxError,
                    complete() {
                        $submitButton.val(originalButtonText).prop('disabled', false);
                    }
                });
            });

            // Initialize notice dismiss button handler
            $('body').on('click', '#mso-ai-settings-messages .notice-dismiss', function () {
                $(this).closest('.notice').fadeTo(100, 0, function () {
                    $(this).slideUp(100, function () {
                        $(this).remove();
                    });
                });
            });
        }
    });

})(jQuery);