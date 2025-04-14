<?php
/**
 * MSO Meta Description Settings
 *
 * Handles the registration, display, and saving of plugin settings,
 * including API keys and model selections for different AI providers.
 * Uses AJAX for saving settings per tab to improve user experience.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */

namespace MSO_Meta_Description;

use MSO_Meta_Description\Api\ApiClient;

// Assuming ApiClient is used for fetching models, though not directly visible in this snippet.

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    die;
}

/**
 * Manages the plugin's settings page.
 */
class Settings
{
    /**
     * The options group name used by register_setting().
     * @var string
     */
    const OPTIONS_GROUP = 'mso_meta_description_options';

    /**
     * The slug for the settings page.
     * @var string
     */
    const PAGE_SLUG = 'admin_mso_meta_description';

    // --- Section IDs for different providers ---
    /**
     * Settings section ID for Mistral.
     * @var string
     */
    const SECTION_MISTRAL_ID = 'mso_meta_description_mistral_section';
    /**
     * Settings section ID for Gemini.
     * @var string
     */
    const SECTION_GEMINI_ID = 'mso_meta_description_gemini_section';
    /**
     * Settings section ID for OpenAI.
     * @var string
     */
    const SECTION_OPENAI_ID = 'mso_meta_description_openai_section';
    /**
     * Settings section ID for Anthropic.
     * @var string
     */
    const SECTION_ANTHROPIC_ID = 'mso_meta_description_anthropic_section';


    /**
     * The AJAX action hook for saving settings.
     * @var string
     */
    const AJAX_SAVE_ACTION = 'mso_save_settings';
    /**
     * Instance of the ApiClient, potentially used for model fetching or validation.
     * @var ApiClient
     */
    private ApiClient $api_client;

    /**
     * Constructor. Hooks into WordPress actions.
     *
     * @param ApiClient $api_client An instance of the ApiClient.
     */
    public function __construct(ApiClient $api_client)
    {
        $this->api_client = $api_client;

        // Hook the AJAX handler for saving settings.
        add_action('wp_ajax_' . self::AJAX_SAVE_ACTION, [$this, 'handle_ajax_save_settings']);

        // Hook to enqueue scripts and styles on admin pages.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Note: add_action('admin_menu', [$this, 'add_options_page']);
        // and add_action('admin_init', [$this, 'register_settings']);
        // should be called from the main plugin file or another appropriate place.
    }

    /**
     * Enqueue scripts and styles needed specifically for the plugin's settings page.
     *
     * @param string $hook_suffix The hook suffix of the current admin page.
     */
    public function enqueue_admin_scripts(string $hook_suffix): void
    {
        // Get the current screen object to check its ID.
        $current_screen = get_current_screen();

        // Only load scripts if we are on *our* specific settings page.
        // The screen ID for a page added via add_options_page is 'settings_page_{page_slug}'.
        if ($current_screen && $current_screen->id === 'settings_page_' . self::PAGE_SLUG) {

            // Enqueue the main JavaScript file for handling AJAX requests on the settings page.
            wp_enqueue_script(
                'mso-settings-ajax',
                // Get the URL for the settings AJAX JavaScript file relative to this PHP file.
                plugins_url('../assets/js/mso-settings-ajax.js', __FILE__),
                ['jquery'], // Dependencies: This script requires jQuery.
                // Add file modification time as version number for cache busting.
                filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/mso-settings-ajax.js'),
                true // Load the script in the footer.
            );

            // Pass PHP variables to the JavaScript file ('mso-settings-ajax').
            wp_localize_script(
                'mso-settings-ajax', // The handle of the script to localize.
                'msoSettingsAjax',   // The name of the JavaScript object that will contain the data.
                [
                    'ajax_url' => admin_url('admin-ajax.php'), // URL for WordPress AJAX requests.
                    'nonce' => wp_create_nonce(self::AJAX_SAVE_ACTION), // Security nonce for the AJAX action.
                    'action' => self::AJAX_SAVE_ACTION, // The AJAX action name.
                    // Localized strings for user feedback in JavaScript.
                    'saving_text' => esc_html__('Saving...', 'mso-meta-description'),
                    'saved_text' => esc_html__('Settings Saved', 'mso-meta-description'),
                    'error_text' => esc_html__('Error Saving Settings', 'mso-meta-description'),
                ]
            );
        }
    }

    /**
     * Adds the plugin's settings page to the WordPress Settings menu.
     * This should be hooked into 'admin_menu'.
     */
    public function add_options_page(): void
    {
        add_options_page(
            esc_html__('MSO Meta Description Settings', 'mso-meta-description'), // Page title
            esc_html__('MSO Meta Description', 'mso-meta-description'),       // Menu title
            'manage_options', // Capability required to access
            self::PAGE_SLUG,  // Menu slug (unique identifier)
            [$this, 'render_options_page'], // Function to render the page content
            25 // Position in the menu (optional)
        );
    }

    /**
     * Renders the HTML content of the options page, including tabs and the settings form.
     */
    public function render_options_page(): void
    {
        // Get the available tabs.
        $tabs = $this->get_tabs();

        // Basic nonce check for tab switching (prevents potential issues, though less critical than form submission nonce).
        // Note: The nonce name 'action' is used by wp_nonce_url by default.
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']))) {
            // Determine the active tab based on the 'tab' query parameter, default to the first tab.
            $active_tab = isset($_GET['tab']) && array_key_exists(sanitize_text_field(wp_unslash($_GET['tab'])), $tabs)
                ? sanitize_text_field(wp_unslash($_GET['tab']))
                : array_key_first($tabs); // Get the key (slug) of the first tab.

            ?>
            <div class="wrap"> <!-- Standard WordPress admin page wrapper -->
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                <!-- Placeholder for AJAX success/error messages -->
                <div id="mso-settings-messages" style="margin-top: 10px;"></div>

                <!-- Navigation tabs -->
                <h2 class="nav-tab-wrapper">
                    <?php
                    foreach ($tabs as $tab_slug => $tab_label) {
                        // Build the URL for each tab link.
                        $tab_url = add_query_arg(
                            ['page' => self::PAGE_SLUG, 'tab' => $tab_slug],
                            admin_url('options-general.php') // Base URL for settings pages.
                        );

                        // Add a nonce to the tab URL for basic verification.
                        $tab_url = wp_nonce_url($tab_url, 'action'); // Uses 'action' as the nonce action name.

                        // Add 'nav-tab-active' class to the currently active tab.
                        $active_class = ($active_tab === $tab_slug) ? ' nav-tab-active' : '';

                        // Output the tab link.
                        printf(
                            '<a href="%s" class="nav-tab%s">%s</a>',
                            esc_url($tab_url),
                            esc_attr($active_class),
                            esc_html($tab_label)
                        );
                    }
                    ?>
                </h2>

                <!-- Settings form - Submission is handled via AJAX, so 'action' is empty -->
                <form method="post" action="" id="mso-settings-form">
                    <?php
                    // Output hidden fields necessary for the settings API (like nonce),
                    // though primarily used for non-AJAX submissions to options.php.
                    // It's good practice to include it anyway.
                    settings_fields(self::OPTIONS_GROUP);

                    // Output the settings sections and fields for the *active* tab only.
                    switch ($active_tab) {
                        case 'mistral':
                            do_settings_sections(self::SECTION_MISTRAL_ID);
                            break;
                        case 'gemini':
                            do_settings_sections(self::SECTION_GEMINI_ID);
                            break;
                        case 'openai':
                            do_settings_sections(self::SECTION_OPENAI_ID);
                            break;
                        case 'anthropic':
                            do_settings_sections(self::SECTION_ANTHROPIC_ID);
                            break;
                        // Add cases for other tabs if needed.
                    }

                    // Output the standard WordPress submit button.
                    // The actual saving is triggered by JavaScript attached to this button's ID or form submission.
                    submit_button(
                        esc_html__('Save Changes', 'mso-meta-description'), // Button text
                        'primary', // Button class (primary, secondary, etc.)
                        'mso-save-settings-button', // 'name' attribute (not critical for AJAX)
                        true, // Wrap in <p> tags
                        ['id' => 'mso-submit-button'] // HTML ID for JavaScript targeting
                    );
                    ?>
                </form>
            </div>
            <?php
        } else {
            // Handle nonce failure - display an error or redirect.
            // This part seems slightly misplaced; the nonce check should ideally protect the rendering logic.
            // Consider restructuring if this nonce check is critical. Currently, it prevents the page from rendering if the nonce fails.
            // A better approach might be to show an error message within the standard page structure.
            wp_die(esc_html__('Invalid nonce specified.', 'mso-meta-description'), esc_html__('Error', 'mso-meta-description'), [
                'response' => 403,
                'back_link' => true,
            ]);
        }
    }

    /**
     * Defines the available tabs for the settings page.
     *
     * @return array Associative array where keys are tab slugs and values are display labels.
     */
    private function get_tabs(): array
    {
        return [
            'mistral' => esc_html__('Mistral Settings', 'mso-meta-description'),
            'gemini' => esc_html__('Gemini Settings', 'mso-meta-description'),
            'openai' => esc_html__('OpenAI Settings', 'mso-meta-description'),
            'anthropic' => esc_html__('Anthropic Settings', 'mso-meta-description'),
        ];
    }

    /**
     * Registers the plugin settings, sections, and fields with the WordPress Settings API.
     * This should be hooked into 'admin_init'.
     * While saving is done via AJAX, registration is still useful for defining option names
     * and potentially leveraging sanitization callbacks if needed elsewhere.
     */
    public function register_settings(): void
    {
        $option_group = 'mso_meta_description_options';
        $prefix = MSO_Meta_Description::get_option_prefix();

        // --- API Key Settings ---
        register_setting($option_group, $prefix . 'gemini_api_key', 'sanitize_text_field');
        register_setting($option_group, $prefix . 'mistral_api_key', 'sanitize_text_field');
        register_setting($option_group, $prefix . 'openai_api_key', 'sanitize_text_field');
        register_setting($option_group, $prefix . 'anthropic_api_key', 'sanitize_text_field');

        // --- Model Selection Settings ---
        register_setting(
            $option_group,
            $prefix . 'gemini_model', 'sanitize_text_field'
        );

        register_setting(
            $option_group,
            $prefix . 'mistral_model', 'sanitize_text_field'
        );

        register_setting(
            $option_group,
            $prefix . 'openai_model', 'sanitize_text_field'
        );

        register_setting(
            $option_group,
            $prefix . 'anthropic_model', 'sanitize_text_field'
        );

        // --- Mistral Section ---
        add_settings_section(self::SECTION_MISTRAL_ID, null, [$this, 'render_section_callback'], self::SECTION_MISTRAL_ID);
        add_settings_field($prefix . 'mistral_api_key', esc_html__('Mistral API Key', 'mso-meta-description'), [$this, 'render_api_key_field'], self::SECTION_MISTRAL_ID, self::SECTION_MISTRAL_ID, ['provider' => 'mistral']);
        add_settings_field($prefix . 'mistral_model', esc_html__('Mistral Model', 'mso-meta-description'), [$this, 'render_model_field'], self::SECTION_MISTRAL_ID, self::SECTION_MISTRAL_ID, ['provider' => 'mistral']);

        // --- Gemini Section ---
        add_settings_section(self::SECTION_GEMINI_ID, null, [$this, 'render_section_callback'], self::SECTION_GEMINI_ID);
        add_settings_field($prefix . 'gemini_api_key', esc_html__('Gemini API Key', 'mso-meta-description'), [$this, 'render_api_key_field'], self::SECTION_GEMINI_ID, self::SECTION_GEMINI_ID, ['provider' => 'gemini']);
        add_settings_field($prefix . 'gemini_model', esc_html__('Gemini Model', 'mso-meta-description'), [$this, 'render_model_field'], self::SECTION_GEMINI_ID, self::SECTION_GEMINI_ID, ['provider' => 'gemini']);

        // --- OpenAI Section ---
        add_settings_section(self::SECTION_OPENAI_ID, null, [$this, 'render_section_callback'], self::SECTION_OPENAI_ID);
        add_settings_field($prefix . 'openai_api_key', esc_html__('OpenAI (ChatGPT) API Key', 'mso-meta-description'), [$this, 'render_api_key_field'], self::SECTION_OPENAI_ID, self::SECTION_OPENAI_ID, ['provider' => 'openai']);
        add_settings_field($prefix . 'openai_model', esc_html__('OpenAI Model', 'mso-meta-description'), [$this, 'render_model_field'], self::SECTION_OPENAI_ID, self::SECTION_OPENAI_ID, ['provider' => 'openai']);

        // --- Anthropic Section ---
        add_settings_section(self::SECTION_ANTHROPIC_ID, null, [$this, 'render_section_callback'], self::SECTION_ANTHROPIC_ID);
        add_settings_field($prefix . 'anthropic_api_key', esc_html__('Anthropic API Key', 'mso-meta-description'), [$this, 'render_api_key_field'], self::SECTION_ANTHROPIC_ID, self::SECTION_ANTHROPIC_ID, ['provider' => 'anthropic']);
        add_settings_field($prefix . 'anthropic_model', esc_html__('Anthropic AI Model', 'mso-meta-description'), [$this, 'render_model_field'], self::SECTION_ANTHROPIC_ID, self::SECTION_ANTHROPIC_ID, ['provider' => 'anthropic']);


        // Conditionally register the front page description setting
        // if the site is configured to show latest posts on the front page.
        if ('posts' === get_option('show_on_front')) {
            $this->register_front_page_setting();
        }
    }

    /**
     * Registers the setting field for the front page meta description
     * on the standard WordPress 'Reading' settings page.
     */
    public function register_front_page_setting(): void
    {
        $option_name = MSO_Meta_Description::OPTION_PREFIX . 'front_page';
        // Register the setting to be saved on the 'reading' settings page.
        register_setting(
            'reading', // The settings group for the Reading settings page.
            $option_name,
            'sanitize_text_field' // Sanitization callback.
        );
        // Add the field to the 'reading' page, within the 'default' section.
        add_settings_field(
            'mso_front_page_description_field', // Unique ID for the field.
            esc_html__('Front page meta description', 'mso-meta-description'), // Field label.
            [$this, 'render_front_page_description_input'], // Callback to render the input.
            'reading', // Page slug.
            'default', // Section ID on the 'reading' page.
            ['label_for' => $option_name] // Associates the label with the input ID (improves accessibility).
        );
    }


    /**
     * Handles the AJAX request triggered by the JavaScript on the settings page
     * to save the settings for the currently active tab.
     */
    public function handle_ajax_save_settings(): void
    {
        // 1. Verify the security nonce sent with the AJAX request.
        // 'nonce' is the key expected in the $_POST data (matches wp_localize_script).
        check_ajax_referer(self::AJAX_SAVE_ACTION, 'nonce');

        // 2. Check if the current user has the required capability to manage options.
        if (!current_user_can('manage_options')) {
            // Send a JSON error response with a 403 Forbidden status.
            wp_send_json_error(['message' => esc_html__('You do not have permission to save these settings.', 'mso-meta-description')], 403);
            // wp_send_json_* functions include die().
        }

        // 3. Get the active tab identifier sent from the JavaScript.
        // Use sanitize_key() as tab identifiers should be simple strings.
        $active_tab = isset($_POST['active_tab']) ? sanitize_key($_POST['active_tab']) : null;

        // Ensure the active tab identifier was provided.
        if (!$active_tab) {
            wp_send_json_error(['message' => esc_html__('Could not determine which settings tab to save.', 'mso-meta-description')], 400); // 400 Bad Request status.
        }

        // 4. Determine which specific option names belong to the active tab being saved.
        $options_for_this_tab = [];
        $option_prefix = MSO_Meta_Description::OPTION_PREFIX;

        switch ($active_tab) {
            case 'mistral':
                $options_for_this_tab = [
                    $option_prefix . 'mistral_api_key',
                    $option_prefix . 'mistral_model',
                ];
                break;
            case 'gemini':
                $options_for_this_tab = [
                    $option_prefix . 'gemini_api_key',
                    $option_prefix . 'gemini_model',
                ];
                break;
            case 'openai':
                $options_for_this_tab = [
                    $option_prefix . 'openai_api_key',
                    $option_prefix . 'openai_model',
                ];
                break;
            case 'anthropic':
                $options_for_this_tab = [
                    $option_prefix . 'anthropic_api_key',
                    $option_prefix . 'anthropic_model',
                ];
                break;
            // Add cases for other tabs if necessary.
            default:
                // Handle unknown tab identifier.
                wp_send_json_error(['message' => sprintf(
                /* translators: %s: The unknown tab identifier */
                    esc_html__('Unknown settings tab: %s', 'mso-meta-description'), esc_html($active_tab))], 400);
        }

        // 5. Iterate through the options relevant to the current tab, sanitize, and save them.
        $saved_data = []; // Store sanitized data to send back in the response.
        $errors = []; // Store any validation errors (optional).

        foreach ($options_for_this_tab as $option_name) {
            // Check if the option name exists in the POST data.
            // Input fields that are empty will still be present in $_POST with an empty string value.
            if (isset($_POST[$option_name])) {
                // Sanitize the submitted value. Use sanitize_text_field for general text inputs.
                // wp_unslash() is important to remove slashes added by WordPress.
                $sanitized_value = sanitize_text_field(wp_unslash($_POST[$option_name]));

                // Save the sanitized value to the WordPress options table.
                update_option($option_name, $sanitized_value);

                // Store the sanitized value to potentially send back in the response.
                $saved_data[$option_name] = $sanitized_value;

                // --- Optional: Add specific server-side validation after sanitization ---
                /*
                if (str_contains($option_name, '_api_key') && !empty($sanitized_value)) {
                    // Example: Basic length check for API keys.
                    if (strlen($sanitized_value) < 10) {
                        $errors[$option_name] = esc_html__('API Key seems too short.', 'mso-meta-description');
                    }
                    // Example: You could add more complex validation, like making a test API call.
                }
                */

            } else {
                // If an expected field is *completely missing* from the POST data (unlikely for text/select fields
                // unless the form HTML is broken or tampered with), save an empty string.
                // This ensures that if a field is somehow removed, its option value is cleared.
                update_option($option_name, '');
                $saved_data[$option_name] = '';
            }
        }

        // 6. Send the JSON response back to the JavaScript.
        if (!empty($errors)) {
            // Send an error response if validation failed.
            wp_send_json_error([
                'message' => esc_html__('Settings saved with validation errors.', 'mso-meta-description'),
                'errors' => $errors, // Include specific field errors.
                'saved_data' => $saved_data // Optionally send back saved data even on error.
            ]);
        } else {
            // Send a success response.
            wp_send_json_success([
                'message' => esc_html__('Settings saved successfully.', 'mso-meta-description'),
                'saved_data' => $saved_data // Send back the saved data (useful for updating UI if needed).
            ]);
        }
        // wp_send_json_* functions call die() automatically, terminating the script.
    }

    /**
     * Renders descriptive text for each settings section.
     *
     * @param array $args Arguments passed from add_settings_section, contains 'id'.
     */
    public function render_section_callback(array $args): void
    {
        // Output different introductory text based on the section ID.
        switch ($args['id']) {
            case self::SECTION_MISTRAL_ID:
                echo '<p>' . esc_html__('Configure the settings for using the Mistral API.', 'mso-meta-description') . '</p>';
                break;
            case self::SECTION_GEMINI_ID:
                echo '<p>' . esc_html__('Configure the settings for using the Google Gemini API.', 'mso-meta-description') . '</p>';
                break;
            case self::SECTION_OPENAI_ID:
                echo '<p>' . esc_html__('Configure the settings for using the OpenAI API.', 'mso-meta-description') . '</p>';
                break;
            case self::SECTION_ANTHROPIC_ID:
                echo '<p>' . esc_html__('Configure the settings for using the Anthropic API.', 'mso-meta-description') . '</p>';
                break;
            default:
                // No text for unknown sections.
                break;
        }
    }

    /**
     * Renders the HTML for an API key input field (password type) with a show/hide button.
     *
     * @param array $args Arguments passed from add_settings_field, should contain 'provider'.
     */
    public function render_api_key_field(array $args): void
    {
        // Get the provider name ('mistral', 'gemini', 'openai') from the arguments.
        $provider = $args['provider'] ?? 'unknown'; // Default to prevent errors if 'provider' isn't set.
        // Construct the option name based on the provider.
        $option_name = MSO_Meta_Description::OPTION_PREFIX . $provider . '_api_key';
        // Get the currently saved value for this option.
        $value = get_option($option_name, '');
        // Create a unique HTML ID for the input field for label and button targeting.
        $field_id = esc_attr($option_name . '_id');

        // Output the password input field.
        printf(
            '<input type="password" class="regular-text" name="%s" id="%s" value="%s" autocomplete="new-password">', // Use autocomplete="new-password" to prevent browser autofill issues.
            esc_attr($option_name), // 'name' attribute for form submission.
            esc_attr($field_id),    // 'id' attribute.
            esc_attr($value)        // Current value.
        );
        // Output the show/hide password button (styled with Dashicons).
        // The functionality is handled by the inline JavaScript enqueued earlier.
        printf(
            '<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="%s">
                <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
            </button>',
            esc_attr__('Show password', 'mso-meta-description') // Accessibility label.
        );

        // Determine the correct documentation link based on the provider.
        $docs_url = '#'; // Default fallback URL.
        $provider_name = ucfirst($provider); // Capitalize provider name for display.
        switch ($provider) {
            case 'mistral':
                $docs_url = 'https://console.mistral.ai/home';
                break;
            case 'gemini':
                $docs_url = 'https://ai.google.dev/tutorials/setup';
                break;
            case 'openai':
                $docs_url = 'https://platform.openai.com/api-keys';
                $provider_name = 'OpenAI'; // Use specific capitalization.
                break;
            case 'anthropic':
                $docs_url = 'https://console.anthropic.com';
                $provider_name = 'Anthropic'; // Use specific capitalization.
                break;
        }
        // Output the help text with a link to get the API key.
        printf(
            ' <p class="description"><a href="%s" target="_blank">%s</a></p>',
            esc_url($docs_url), // Link URL.
            sprintf(
            /* translators: %s: Provider name (e.g., Mistral, Gemini, OpenAI) */
                esc_html__('Get your %s API key', 'mso-meta-description'), esc_html($provider_name)) // Link text.
        );
    }

    // --- Front Page Setting ---
    // The render_front_page_description_input method uses the standard 'reading' settings page
    // save mechanism (options.php), not AJAX, so it doesn't need changes related to the AJAX handler.

    /**
     * Renders the HTML for a model selection dropdown (<select>).
     * Models are intended to be loaded dynamically via JavaScript after an API key is entered/validated.
     *
     * @param array $args Arguments passed from add_settings_field, should contain 'provider'.
     */
    public function render_model_field(array $args): void
    {
        // Get provider name and construct option name and HTML ID.
        $provider = $args['provider'] ?? 'unknown';
        $option_name = MSO_Meta_Description::OPTION_PREFIX . $provider . '_model';
        $select_id = 'mso_meta_description_' . $provider . '_model_id'; // Unique ID for the select element.

        // Output the select element.
        printf(
            '<select id="%s" name="%s" data-provider="%s" class="mso-model-select regular-text">',
            esc_attr($select_id),       // 'id' attribute.
            esc_attr($option_name),     // 'name' attribute for form submission.
            esc_attr($provider)         // 'data-provider' attribute for JavaScript targeting.
        );

        // Initial placeholder option shown while loading or if no key is present.
        echo '<option value="">' . esc_html__('Loading models...', 'mso-meta-description') . '</option>';

        // Pre-select the currently saved value if it exists.
        // This allows the saved value to be shown initially. JavaScript should replace
        // this with the actual list fetched from the API, re-selecting this value if it's valid.
        $current_value = get_option($option_name, '');
        if (!empty($current_value)) {
            printf(
                '<option value="%s" selected>%s</option>', esc_attr($current_value), esc_html($current_value));
        }

        echo '</select>'; // Close the select tag.

        // Output the description text below the dropdown.
        echo '<p class="description">' . sprintf(
            /* translators: %s: Provider name (e.g., Mistral) */
                esc_html__('Select the %s model to use. Models loaded dynamically if API key is valid.', 'mso-meta-description'),
                esc_html(ucfirst($provider)) // Capitalized provider name.
            ) . '</p>';

        // Add a placeholder div for displaying errors related to model loading (e.g., invalid API key).
        // JavaScript can target this using the ID.
        echo '<div id="mso-model-error-' . esc_attr($provider) . '" class="mso-model-error" style="color: red;"></div>';
    }

    /**
     * Renders the input field for the front page meta description on the Reading settings page.
     * Includes basic character counting functionality via inline JavaScript.
     *
     * @param array $args Arguments passed from add_settings_field. Contains 'label_for'.
     */
    public function render_front_page_description_input(array $args): void
    {
        // Construct the option name and get its current value.
        $option_name = MSO_Meta_Description::OPTION_PREFIX . 'front_page';
        $value = get_option($option_name, '');
        // Use the 'label_for' argument passed by add_settings_field for the input ID.
        $field_id = esc_attr($args['label_for']);
        ?>
        <input
                type="text"
                name="<?php echo esc_attr($option_name); ?>"
                id="<?php echo esc_attr($field_id); ?>"
                class="regular-text"
                value="<?php echo esc_attr($value); ?>"
                maxlength="<?php echo esc_attr(MSO_Meta_Description::MAX_DESCRIPTION_LENGTH + 10); // Allow slightly more for flexibility
                ?>"
                aria-describedby="front-page-meta-description-hint" <?php // Link input to its description
        ?>
        >
        <p class="description" id="front-page-meta-description-hint"> <?php // ID for aria-describedby
            ?>
            <?php printf(
            /* translators: 1: Minimum length, 2: Maximum length */
                esc_html__('Enter the meta description for the site\'s front page when it displays the latest posts. Recommended length: %1$d-%2$d characters.', 'mso-meta-description'),
                esc_html(MSO_Meta_Description::MIN_DESCRIPTION_LENGTH),
                esc_html(MSO_Meta_Description::MAX_DESCRIPTION_LENGTH)
            ); ?>
            <?php esc_html_e('Character count', 'mso-meta-description'); ?>: <span
                    class="mso-char-count">0</span> <?php // Span to display character count
            ?>
        </p>
        <script>
            // Inline script for immediate character count feedback on the Reading settings page.
            // Use a flag to prevent this script from running multiple times if the settings field is somehow rendered more than once.
            if (typeof window.msoFrontPageDescInit === 'undefined') {
                window.msoFrontPageDescInit = true; // Set the flag.
                // Use jQuery since it's generally available in WP admin.
                jQuery(document).ready(function ($) {
                    // Get the input field and the character count span.
                    var inputField = $('#<?php echo esc_js($field_id); ?>'); // Use esc_js for safety in JS strings.
                    var countSpan = inputField.next('.description').find('.mso-char-count'); // Find the span relative to the input.

                    // Ensure both elements were found.
                    if (inputField.length && countSpan.length) {
                        // Function to update the character count display.
                        var updateCount = function () {
                            countSpan.text(inputField.val().length);
                        };
                        // Bind the update function to input events.
                        inputField.on('input change keyup', updateCount);
                        // Update the count immediately on page load.
                        updateCount();
                    }
                });
            }
        </script>
        <?php
    }

} // End class Settings