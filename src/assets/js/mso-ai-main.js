/**
 * MSO AI Meta Description JavaScript Unified
 *
 * Handles client-side interactions for the MSO AI Meta Description plugin.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

(($) => {
    'use strict';

    /**
     * Main object for the plugin's admin JavaScript functionality.
     */
    const MSO_AI_Admin = {
        MIN_DESCRIPTION_LENGTH: 120,
        MAX_DESCRIPTION_LENGTH: 160,
        elements: {},
        config: {
            selectedModels: {},
            selectModel: '-- Select a Model --',
            errorLoadingModels: 'Error loading models.',
            apiKeyMissingError: 'API key not set for this provider.',
            status: ['(Too short)', '(Too long)', '(Good)'],
            ajaxUrl: '',
            nonce: '',
            action: 'save_mso_ai_settings',
            saving_text: 'Saving...',
            error_text: 'An error occurred.',
            i18n_show_password: 'Show password',
            i18n_hide_password: 'Hide password'
        },

        /**
         * Initializes the script. Caches elements and binds events.
         */
        init() {
            if (typeof msoAiScriptVars !== 'undefined') {
                this.config = { ...this.config, ...msoAiScriptVars };
            }

            this.cacheElements();
            this.bindEvents();
            this.initPageSpecificFeatures();
        },

        /**
         * Caches frequently used DOM elements.
         */
        cacheElements() {
            this.elements = {
                $metaBoxField: $('#mso_ai_meta_description_field'),
                $charCountSpan: $('.mso-ai-char-count'),
                $lengthIndicatorSpan: $('.mso-ai-length-indicator'),
                $generateButtons: $('.mso-ai-generate-button'),
                $metaBoxGenerator: $('.mso-ai-generator'), 
                $metaBoxSpinner: $('.mso-ai-generator .spinner'),
                $aiErrorContainer: $('#mso-ai-error'),
                $content: $('#content'),
                $settingsForm: $('#mso-ai-settings-form'),
                $submitButton: $('#mso-ai-submit-button'),
                $messagesDiv: $('#mso-ai-settings-messages'),
                $navTabs: $('.nav-tab-wrapper a.nav-tab'),
                $passwordToggleButtons: $('.wp-hide-pw'),
                $togglePromptLinks: $('.mso-ai-toggle-prompt'),
                $modelSelects: $('.mso-model-select')
            };
        },

        /**
         * Binds event listeners.
         */
        bindEvents() {            
            if (this.elements.$metaBoxField.length) {
                this.elements.$metaBoxField.on('keyup input paste change', this.updateCharacterCount.bind(this));
                this.elements.$metaBoxGenerator.on('click', '.mso-ai-generate-button', this.handleGenerateClick.bind(this));
            }
            
            if (this.elements.$settingsForm.length) {
                this.elements.$passwordToggleButtons.on('click', this.handlePasswordToggleClick.bind(this));
                this.elements.$settingsForm.on('submit', this.handleSettingsSubmit.bind(this));
                this.elements.$messagesDiv.on('click', '.notice-dismiss', this.handleDismissNoticeClick.bind(this));
            }

            if (this.elements.$settingsForm.length) {
                this.elements.$settingsForm.on('click', '.mso-ai-toggle-prompt', this.handleTogglePromptClick.bind(this));
            }
        },

        /**
         * Handles click events on the show/hide toggle links for custom prompts.
         * @param {Event} e - The click event object.
         */
        handleTogglePromptClick(e) {
            e.preventDefault();
            const $link = $(e.currentTarget);
            const targetId = $link.attr('aria-controls');
            const $targetDiv = $('#' + targetId);

            if ($targetDiv.length) {
                $targetDiv.slideToggle('fast', () => {
                    const isVisible = $targetDiv.is(':visible');
                    $link.attr('aria-expanded', isVisible);
                    $link.text(isVisible ? this.config.i18n_hide_prompt : this.config.i18n_show_prompt);
                });
            }
        },

        /**
         * Initializes features specific to the current page (Meta Box or Settings).
         */
        initPageSpecificFeatures() {
            if (this.elements.$metaBoxField.length) {
                this.updateCharacterCount(); 
            }

            if (this.elements.$settingsForm.length) {
                this.populateAllModelSelects();
            }
        },

        /**
         * Updates the character count display and color indicator.
         */
        updateCharacterCount() {
            const value = this.elements.$metaBoxField.val() || '';
            const length = value.length;
            let color = 'inherit';
            let indicatorText = '';

            if (length > 0) {
                if (length < this.MIN_DESCRIPTION_LENGTH) {
                    color = 'orange';
                    indicatorText = this.config.status[0]; 
                } else if (length > this.MAX_DESCRIPTION_LENGTH) {
                    color = 'red';
                    indicatorText = this.config.status[1]; 
                } else {
                    color = 'green';
                    indicatorText = this.config.status[2]; 
                }
            }

            this.elements.$charCountSpan.text(length);
            this.elements.$lengthIndicatorSpan.text(indicatorText).css('color', color);
        },

        /**
         * Handles click events on the "Generate" buttons.
         * @param {Event} e - The click event object.
         */
        handleGenerateClick(e) {
            const provider = $(e.target).data('provider');
            if (provider) {
                this.summarizeContent(provider);
            }
        },

        /**
         * Generates a summary of post content using the specified AI provider.
         * @param {string} provider - AI provider identifier.
         */
        async summarizeContent(provider) {
            this.elements.$metaBoxSpinner.css('visibility', 'visible');
            this.elements.$aiErrorContainer.text('');
            this.elements.$generateButtons.prop('disabled', true);

            try {
                const plainText = this.getPostContentAsText();
                if (!plainText) {
                    throw new Error('Content is empty or could not be retrieved.');
                }

                const result = await this.ajaxRequest({
                    action: 'mso_ai_generate_summary',
                    content: plainText,
                    provider: provider
                });

                this.elements.$metaBoxField.val(result.summary).trigger('input');

            } catch (err) {
                const displayError = this.parseApiError(err.message || 'Failed to generate summary.', 'Error');
                this.elements.$aiErrorContainer.text(displayError);
            } finally {
                this.elements.$metaBoxSpinner.css('visibility', 'hidden');
                this.elements.$generateButtons.prop('disabled', false);
                this.updateCharacterCount();
            }
        },

        /**
         * Retrieves post content from the editor (Gutenberg or Classic) and returns plain text.
         * @returns {string} Plain text content.
         */
        getPostContentAsText() {
            let htmlContent = '';
            if (typeof wp !== 'undefined' && wp.data?.select('core/editor')?.getEditedPostContent) {
                htmlContent = wp.data.select('core/editor').getEditedPostContent() || '';
            } else if (this.elements.$content.length) {
                htmlContent = this.elements.$content.val() || '';
            }

            if (!htmlContent) return '';

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlContent;
            tempDiv.querySelectorAll('script, style').forEach(el => el.remove());
            let text = tempDiv.innerText || tempDiv.textContent || '';
            text = text.replace(/\[.*?]/g, '')
                .replace(/\s\s+/g, ' ')
                .trim();
            return text;
        },

        /**
         * Handles click events on password visibility toggle buttons.
         * @param {Event} e - The click event object.
         */
        handlePasswordToggleClick(e) {
            const $button = $(e.target).closest('button');
            const $input = $button.prev('input[type="password"], input[type="text"]');

            if (!$input.length) return;

            const isPassword = $input.attr('type') === "password";
            const newType = isPassword ? "text" : "password";
            const iconClass = isPassword ? ["dashicons-visibility", "dashicons-hidden"] : ["dashicons-hidden", "dashicons-visibility"];
            const label = isPassword ? this.config.i18n_hide_password : this.config.i18n_show_password;

            $input.attr('type', newType);
            $button.find('.dashicons').removeClass(iconClass[0]).addClass(iconClass[1]);
            $button.attr("aria-label", label);
        },

        /**
         * Populates all model select dropdowns found on the page.
         */
        populateAllModelSelects() {
            this.elements.$modelSelects.each((index, el) => {
                const $select = $(el);
                const apiType = $select.data('provider');
                if (apiType) {
                    this.populateModelSelect({
                        apiType: apiType,
                        $select: $select,
                        defaultModel: this.config.selectedModels[apiType] || null
                    });
                }
            });
        },

        /**
         * Populates a single model selection dropdown.
         * @param {object} options - Configuration options.
         */
        async populateModelSelect({ apiType, $select, defaultModel = null }) {
            const apiKeyInputId = `mso_ai_meta_description_${apiType}_api_key_id`;
            const $apiKeyInput = $(`#${apiKeyInputId}`);
            const currentApiKey = $apiKeyInput.val() || '';
            const $errorContainer = $('#mso-model-error-' + apiType);
            const $spinner = $select.siblings('.spinner');

            $select.empty().append($('<option>', { value: '', text: this.config.selectModel }));
            $errorContainer.text('');
            $select.prop('disabled', false);

            if (!currentApiKey) {
                $errorContainer.text(this.config.apiKeyMissingError);
                $select.prop('disabled', true);
                return;
            }

            $spinner.css('visibility', 'visible');

            try {
                const models = await this.ajaxRequest({
                    action: 'mso_ai_fetch_models',
                    apiType: apiType
                });

                if (!Array.isArray(models) || models.length === 0) {
                    $select.append($('<option>', { value: '', text: 'No models found' }));
                    throw new Error('No compatible models found or returned by the API.');
                }

                const currentSelectedValue = $select.val();
                let modelToSelect = defaultModel;

                models.forEach(model => {
                    if (model && model?.id) {
                        $select.append($('<option>', {
                            value: model.id,
                            text: model.displayName || model.id
                        }));
                    }
                });

                if (modelToSelect && $select.find(`option[value="${modelToSelect}"]`).length) {
                    $select.val(modelToSelect);
                } else if (currentSelectedValue && $select.find(`option[value="${currentSelectedValue}"]`).length) {
                    $select.val(currentSelectedValue);
                } else if ($select.find('option').length > 1) {
                    $select.prop('selectedIndex', 1);
                }

            } catch (err) {
                const displayError = this.parseApiError(err.message || 'Unknown error', this.config.errorLoadingModels);
                $errorContainer.text(displayError);
                $select.append($('<option>', { value: '', text: 'Error loading' }));
            } finally {
                $spinner.css('visibility', 'hidden');
            }
        },

        /**
         * Handles the submission of the settings form via AJAX.
         * @param {Event} e - The submit event object.
         */
        handleSettingsSubmit(e) {
            e.preventDefault();

            const activeTabSlug = this.getActiveTabSlug();
            if (!activeTabSlug) {
                this.displayMessage('error', 'Could not determine the active settings tab.');
                return;
            }

            const formData = this.elements.$settingsForm.serialize();
            const originalButtonText = this.elements.$submitButton.val();

            this.elements.$submitButton.val(this.config.saving_text).prop('disabled', true);
            this.elements.$messagesDiv.slideUp('fast', function () { $(this).empty(); });

            this.ajaxRequest({ active_tab: activeTabSlug }, formData)
                .then(response => {
                    this.displayMessage('success', response.message);

                    if (activeTabSlug === 'options') {
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                        return;
                    }

                    const $selectToRefresh = $(`#mso_ai_meta_description_${activeTabSlug}_model_id`);
                    if ($selectToRefresh.length) {
                        const savedModelOptionName = `mso_ai_meta_description_${activeTabSlug}_model`;
                        const newlySavedModel = response.saved_data?.[savedModelOptionName] || null;
                        this.populateModelSelect({
                            apiType: activeTabSlug,
                            $select: $selectToRefresh,
                            defaultModel: newlySavedModel
                        });
                    }
                })
                .catch(err => {
                    const displayError = this.parseApiError(err.message || this.config.error_text, this.config.error_text);
                    this.displayMessage('error', displayError);
                })
                .finally(() => {
                    this.elements.$submitButton.val(originalButtonText).prop('disabled', false);
                });
        },

        /**
         * Gets the active tab slug from the settings page.
         * @returns {string} Active tab slug or empty string.
         */
        getActiveTabSlug() {
            const $activeTabLink = this.elements.$navTabs.filter('.nav-tab-active');
            if ($activeTabLink.length) {
                try {
                    const url = new URL($activeTabLink.attr('href'), window.location.origin);
                    return url.searchParams.get('tab') || '';
                } catch (e) {
                    const href = $activeTabLink.attr('href');
                    const tabMatch = href ? href.match(/tab=([^&]*)/) : null;
                    return tabMatch ? tabMatch[1] : '';
                }
            }

            const currentUrlParams = new URLSearchParams(window.location.search);
            let tab = currentUrlParams.get('tab');
            if (!tab && this.elements.$navTabs.length) {
                try {
                    const url = new URL(this.elements.$navTabs.first().attr('href'), window.location.origin);
                    return url.searchParams.get('tab') || '';
                } catch (e) {
                    const href = this.elements.$navTabs.first().attr('href');
                    const tabMatch = href ? href.match(/tab=([^&]*)/) : null;
                    return tabMatch ? tabMatch[1] : '';
                }
            }
            return tab || '';
        },

        /**
         * Displays a dismissible notice message.
         * @param {string} type - 'success' or 'error'.
         * @param {string} message - Message content.
         */
        displayMessage(type, message) {
            const noticeClass = `notice notice-${type} is-dismissible`;
            const dismissButton = '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>';
            this.elements.$messagesDiv
                .removeClass('notice-success notice-error is-dismissible')
                .addClass(noticeClass)
                .html(`<p>${message}</p>${dismissButton}`)
                .slideDown('fast');
        },

        /**
         * Handles click on the dismiss button of notices.
         * @param {Event} e - The click event.
         */
        handleDismissNoticeClick(e) {
            $(e.target).closest('.notice').slideUp('fast', function () {
                $(this).remove();
            });
        },

        /**
         * Performs an AJAX request to the WordPress backend.
         * Automatically includes the action and nonce.
         * Handles standard success/error structure.
         *
         * @param {object} data - Data to send (action and nonce are added automatically).
         * @param {string} [baseData=''] - Optional base query string (like form serialization) to append to.
         * @returns {Promise<any>} Resolves with response.data on success, rejects with an Error on failure.
         */
        async ajaxRequest(data, baseData = '') {
            const requestData = {
                action: this.config.action,
                nonce: this.config.nonce,
                ...data
            };

            const queryString = new URLSearchParams(baseData).toString();
            const ajaxParams = new URLSearchParams(requestData).toString();
            const finalBody = `${queryString}${queryString ? '&' : ''}${ajaxParams}`;

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: finalBody,
                });

                if (!response.ok) {
                    let errorData = null;
                    try {
                        errorData = await response.json();
                    } catch (e) { }
                    const displayError = this.parseApiError(errorData?.data?.message || this.config.error_text, this.config.error_text);

                    throw new Error(displayError || `HTTP error ${response.status}`);
                }

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.data?.message || 'Unknown AJAX error.');
                }

                return result.data;

            } catch (error) {
                throw error;
            }
        },

        /**
         * Parses API error messages, attempting to extract nested JSON details.
         * @param {string} rawMessage - The raw error message string.
         * @param {string} [prefix='Error'] - Prefix for the final message (e.g., 'Error', 'Error loading models').
         * @returns {string} The parsed or cleaned error message.
         */
        parseApiError(rawMessage, prefix = 'Error') {
            let message = rawMessage || 'Unknown error';
            message = String(message).replace(/\\n/g, ' ').replace(/\s+/g, ' ').trim();
            let specificMessage = '';

            try {
                const jsonStringMatch = message.match(/{.*}/);

                if (jsonStringMatch && jsonStringMatch[0] && jsonStringMatch[0].startsWith('{') && jsonStringMatch[0].endsWith('}')) {
                    const potentialJson = jsonStringMatch[0];
                    const innerError = JSON.parse(potentialJson);

                    if (innerError?.error?.message) {
                        specificMessage = innerError.error.message;
                    } else if (innerError?.message) {
                        specificMessage = innerError.message;
                    }
                    specificMessage = String(specificMessage).trim();
                }
            } catch (e) {
                console.warn(`Could not parse inner JSON from error message: "${message}"`, e);
            }

            const finalMessage = specificMessage || message;

            if (String(finalMessage).toLowerCase().startsWith(prefix.toLowerCase() + ':')) {
                return finalMessage;
            } else {
                if (finalMessage.toLowerCase().includes('error') || finalMessage.toLowerCase().includes('fail')) {
                    return finalMessage;
                }
                return `${prefix}: ${finalMessage}`;
            }
        }
    };

    $(document).ready(() => {
        MSO_AI_Admin.init();
    });

})(jQuery);
