<?php
/**
 * MSO Meta Description Settings
 *
 * @package MSO_Meta_Description
 * @since   1.2.0
 */
namespace MSO_Meta_Description;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    die;
}

class Settings
{
    const OPTIONS_GROUP = 'mso_meta_description_options'; // Consistent group name
    const PAGE_SLUG = 'admin_mso_meta_description';
    const SECTION_ID = 'mso_meta_description_section';

    private ApiClient $api_client; // Inject if needed for validation etc.

    public function __construct(ApiClient $api_client) {
        $this->api_client = $api_client;
    }

    /**
     * Add the options page to the WordPress admin menu.
     */
    public function add_options_page(): void
    {
        add_options_page(
            __('MSO Meta Description Settings', MSO_Meta_Description::TEXT_DOMAIN),
            __('MSO Meta Description', MSO_Meta_Description::TEXT_DOMAIN),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_options_page'],
            25 // Position in menu
        );
    }

    /**
     * Render the content of the options page.
     */
    public function render_options_page(): void
    {
        // Simple inline rendering
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTIONS_GROUP); // Output nonce, action, and option_page fields for the group.
                do_settings_sections(self::PAGE_SLUG); // Print all sections defined for this page.
                submit_button(); // Output submit button.
                ?>
            </form>
        </div>
        <?php
        // Enqueue password toggle script here or ensure it's loaded globally in Admin class
        wp_enqueue_script('password-strength-meter'); // Dependency for wp-hide-pw
        wp_add_inline_script('password-strength-meter', 'jQuery(document).ready(function($){ $(".wp-hide-pw").on("click", function(){ var button = $(this); var input = button.prev("input"); if (input.attr("type") === "password") { input.attr("type", "text"); button.find(".dashicons").removeClass("dashicons-hidden").addClass("dashicons-visibility"); button.attr("aria-label", "Masquer le mot de passe"); } else { input.attr("type", "password"); button.find(".dashicons").removeClass("dashicons-visibility").addClass("dashicons-hidden"); button.attr("aria-label", "Afficher le mot de passe"); } }); });');

    }

    /**
     * Register the plugin settings and fields.
     */
    public function register_settings(): void
    {
        $option_prefix = MSO_Meta_Description::OPTION_PREFIX;
        $sanitize_callback = ['sanitize_callback' => 'sanitize_text_field'];

        // Register settings for all providers
        register_setting(self::OPTIONS_GROUP, $option_prefix . 'mistral_api_key', $sanitize_callback);
        register_setting(self::OPTIONS_GROUP, $option_prefix . 'gemini_api_key', $sanitize_callback);
        register_setting(self::OPTIONS_GROUP, $option_prefix . 'openai_api_key', $sanitize_callback); // <-- ADDED OpenAI Key

        register_setting(self::OPTIONS_GROUP, $option_prefix . 'mistral_model', $sanitize_callback);
        register_setting(self::OPTIONS_GROUP, $option_prefix . 'gemini_model', $sanitize_callback);
        register_setting(self::OPTIONS_GROUP, $option_prefix . 'openai_model', $sanitize_callback); // <-- ADDED OpenAI Model

        // Add the main settings section
        add_settings_section(
            self::SECTION_ID,
            __('API Settings', MSO_Meta_Description::TEXT_DOMAIN), // Section title
            [$this, 'render_section_callback'], // Callback for section description (optional)
            self::PAGE_SLUG // Page slug where this section appears
        );

        // --- Add fields for Mistral ---
        add_settings_field(
            $option_prefix . 'mistral_api_key',
            __('Mistral API Key', MSO_Meta_Description::TEXT_DOMAIN),
            [$this, 'render_api_key_field'],
            self::PAGE_SLUG,
            self::SECTION_ID,
            ['provider' => 'mistral'] // Pass provider to callback
        );
        add_settings_field(
            $option_prefix . 'mistral_model',
            __('Mistral Model', MSO_Meta_Description::TEXT_DOMAIN),
            [$this, 'render_model_field'],
            self::PAGE_SLUG,
            self::SECTION_ID,
            ['provider' => 'mistral']
        );

        // --- Add fields for Gemini ---
        add_settings_field(
            $option_prefix . 'gemini_api_key',
            __('Gemini API Key', MSO_Meta_Description::TEXT_DOMAIN),
            [$this, 'render_api_key_field'],
            self::PAGE_SLUG,
            self::SECTION_ID,
            ['provider' => 'gemini']
        );
        add_settings_field(
            $option_prefix . 'gemini_model',
            __('Gemini Model', MSO_Meta_Description::TEXT_DOMAIN),
            [$this, 'render_model_field'],
            self::PAGE_SLUG,
            self::SECTION_ID,
            ['provider' => 'gemini']
        );

        // --- Add fields for OpenAI (ChatGPT) ---  <-- ADDED Section
        add_settings_field(
            $option_prefix . 'openai_api_key',
            __('OpenAI (ChatGPT) API Key', MSO_Meta_Description::TEXT_DOMAIN), // Updated Label
            [$this, 'render_api_key_field'],
            self::PAGE_SLUG,
            self::SECTION_ID,
            ['provider' => 'openai'] // Specify 'openai' provider
        );
        add_settings_field(
            $option_prefix . 'openai_model',
            __('OpenAI Model', MSO_Meta_Description::TEXT_DOMAIN), // Updated Label
            [$this, 'render_model_field'],
            self::PAGE_SLUG,
            self::SECTION_ID,
            ['provider' => 'openai'] // Specify 'openai' provider
        );


        // Register front page description setting if needed ('show_on_front' == 'posts')
        if ('posts' === get_option('show_on_front')) {
            $this->register_front_page_setting();
        }
    }

    /**
     * Callback for rendering the settings section description (optional).
     */
    public function render_section_callback(): void
    {
        echo '<p>' . __('Enter your API keys and select the models for generating meta descriptions.', MSO_Meta_Description::TEXT_DOMAIN) . '</p>';
    }

    /**
     * Render API key input field.
     */
    public function render_api_key_field(array $args): void
    {
        $provider = $args['provider'] ?? 'unknown'; // Default to prevent errors
        $option_name = MSO_Meta_Description::OPTION_PREFIX . $provider . '_api_key';
        $value = get_option($option_name, '');
        $input_id = esc_attr($option_name); // Use option name as ID for label connection

        // Use password input type for keys
        printf(
            '<input type="password" class="regular-text" name="%s" id="%s" value="%s" autocomplete="off">',
            esc_attr($option_name),
            $input_id, // Use the variable here
            esc_attr($value)
        );

        // Add the show/hide password button (requires WP core script)
        printf(
            '<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="%s" data-target="%s">
                <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
            </button>',
            esc_attr__('Show password', 'default'), // Standard WP translation
            '#' . $input_id // Target the input field by ID
        );


        // Add link to get API key based on provider
        $docs_url = '#'; // Default
        $provider_name = ucfirst($provider);

        switch ($provider) {
            case 'mistral':
                $docs_url = 'https://docs.mistral.ai/platform/client/';
                break;
            case 'gemini':
                $docs_url = 'https://ai.google.dev/tutorials/setup';
                break;
            case 'openai':
                $docs_url = 'https://platform.openai.com/account/api-keys';
                $provider_name = 'OpenAI'; // Use specific name
                break;
        }

        printf(
            ' <p class="description"><a href="%s" target="_blank">%s</a></p>',
            esc_url($docs_url),
            sprintf(__('Get your %s API key', MSO_Meta_Description::TEXT_DOMAIN), esc_html($provider_name))
        );
    }

    /**
     * Render model select field (populated by JavaScript).
     */
    public function render_model_field(array $args): void
    {
        $provider = $args['provider'] ?? 'unknown';
        $option_name = MSO_Meta_Description::OPTION_PREFIX . $provider . '_model';
        // ID needs to be unique and match JS selector
        $select_id = 'mso_meta_description_' . $provider . '_model'; // Consistent ID format

        printf(
            '<select id="%s" name="%s" data-provider="%s" class="mso-model-select regular-text">', // Added regular-text class for WP styling
            esc_attr($select_id),
            esc_attr($option_name),
            esc_attr($provider)
        );
        // Placeholder option, JS will replace this
        echo '<option value="">' . __('Loading models...', MSO_Meta_Description::TEXT_DOMAIN) . '</option>';
        echo '</select>';
        echo '<p class="description">' . sprintf(__('Select the %s model to use. Models loaded dynamically if API key is valid.', MSO_Meta_Description::TEXT_DOMAIN), esc_html(ucfirst($provider))) . '</p>'; // Updated text slightly
        echo '<div id="mso-model-error-'.esc_attr($provider).'" class="mso-model-error" style="color: red;"></div>'; // Placeholder for errors
    }

    // --- Front Page Setting (No changes needed here for OpenAI) ---

    public function register_front_page_setting(): void
    {
        $option_name = MSO_Meta_Description::OPTION_PREFIX . 'front_page';
        register_setting('reading', $option_name, ['sanitize_callback' => 'sanitize_text_field']);
        add_settings_field(
            'mso_front_page_description_field',
            __('Front page meta description', MSO_Meta_Description::TEXT_DOMAIN),
            [$this, 'render_front_page_description_input'],
            'reading',
            'default',
            ['label_for' => $option_name]
        );
    }

    public function render_front_page_description_input(array $args): void
    {
        $option_name = MSO_Meta_Description::OPTION_PREFIX . 'front_page';
        $value = get_option($option_name, '');
        ?>
        <input
                type="text"
                name="<?php echo esc_attr($option_name); ?>"
                id="<?php echo esc_attr($option_name); ?>"
                class="regular-text"
                value="<?php echo esc_attr($value); ?>"
                maxlength="<?php echo MSO_Meta_Description::MAX_DESCRIPTION_LENGTH + 10; ?>"
        >
        <p class="description" id="front-page-meta-description-hint">
            <?php printf(
                __('Enter the meta description for the site\'s front page when it displays the latest posts. Recommended length: %1$d-%2$d characters.', MSO_Meta_Description::TEXT_DOMAIN),
                MSO_Meta_Description::MIN_DESCRIPTION_LENGTH,
                MSO_Meta_Description::MAX_DESCRIPTION_LENGTH
            ); ?>
            <?php esc_html_e('Character count', MSO_Meta_Description::TEXT_DOMAIN); ?>: <span class="mso-char-count">0</span>
        </p>
        <script>
            // Simple inline script for immediate feedback on this specific field
            jQuery(document).ready(function($) {
                var inputField = $('#<?php echo esc_js($option_name); ?>');
                var countSpan = inputField.next('.description').find('.mso-char-count'); // Adjust selector if needed
                if (inputField.length && countSpan.length) {
                    var updateCount = function() { countSpan.text(inputField.val().length); };
                    inputField.on('input change keyup', updateCount);
                    updateCount(); // Initial count
                }
            });
        </script>
        <?php
    }
}