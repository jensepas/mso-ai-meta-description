<?php
/**
 * MSO AI Meta Description Settings
 *
 * Handles the registration, display, and saving of plugin settings,
 * including API keys and model selections for different AI providers.
 * Uses AJAX for saving settings per tab to improve user experience.
 * Dynamically registers settings based on loaded providers.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description;

use MSO_AI_Meta_Description\Providers\ProviderInterface;
use MSO_AI_Meta_Description\Providers\ProviderManager;

if (! defined('ABSPATH')) {
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
    private const string OPTIONS_GROUP = 'mso_ai_meta_description_options';

    /**
     * The slug for the settings page.
     * @var string
     */
    public const string PAGE_SLUG = 'admin_mso_ai_meta_description';

    /**
     * Constant register_setting.
     * @var array<string, string|null>
     */
    public const array MY_ARRAY = ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => null,];

    /**
     * Menu icon.
     * @var string
     */
    public const string ICON_BASE64_SVG = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pg0KPHN2ZyBmaWxsPSIjMDAwMDAwIiBoZWlnaHQ9IjgwMHB4IiB3aWR0aD0iODAwcHgiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgDQoJIHZpZXdCb3g9IjAgMCA0OTAgNDkwIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxnPg0KCTxnPg0KCQk8cGF0aCBkPSJNNDE2LDBINzRDMzMuMywwLDAsMzMuNCwwLDc0djM0MmMwLDQwLjcsMzMuNCw3NCw3NCw3NGgzNDJjNDAuNywwLDc0LTMzLjQsNzQtNzRWNzRDNDkwLDMzLjQsNDU2LjYsMCw0MTYsMHogTTQ0OS4zLDQxNg0KCQkJYzAsMTguOC0xNC42LDMzLjQtMzMuNCwzMy40SDc0Yy0xOC44LDAtMzMuNC0xNC42LTMzLjQtMzMuNFY3NGMwLTE4LjgsMTQuNi0zMy40LDMzLjQtMzMuNGgzNDJjMTguOCwwLDMzLjQsMTQuNiwzMy40LDMzLjR2MzQyDQoJCQlINDQ5LjN6Ii8+DQoJCTxnPg0KCQkJPHBhdGggZD0iTTIzNC44LDE2OS44Yy0yLjQtNS41LTcuOC05LTEzLjgtOXMtMTEuNCwzLjUtMTMuOCw5TDE0NywzMDguM2MtMy4zLDcuNiwwLjIsMTYuNCw3LjgsMTkuN2MyLDAuOSw0LDEuMyw2LDEuMw0KCQkJCWM1LjgsMCwxMS4zLTMuNCwxMy44LTlsMTMuMi0zMC4yaDY2LjlsMTMuMiwzMC4yYzMuMyw3LjYsMTIuMSwxMS4xLDE5LjcsNy44YzcuNi0zLjMsMTEuMS0xMi4yLDcuOC0xOS43TDIzNC44LDE2OS44eg0KCQkJCSBNMjAwLjcsMjYwbDIwLjQtNDYuOGwyMC40LDQ2LjhIMjAwLjd6Ii8+DQoJCQk8cGF0aCBkPSJNMzI5LjMsMjE3LjljLTguMywwLTE1LDYuNy0xNSwxNXY4MS40YzAsOC4zLDYuNywxNSwxNSwxNXMxNS02LjcsMTUtMTV2LTgxLjRDMzQ0LjMsMjI0LjYsMzM3LjYsMjE3LjksMzI5LjMsMjE3Ljl6Ii8+DQoJCQk8cGF0aCBkPSJNMzI5LjMsMTY2LjRjLTguMywwLTE1LDYuNy0xNSwxNXY0YzAsOC4zLDYuNywxNSwxNSwxNXMxNS02LjcsMTUtMTV2LTRDMzQ0LjMsMTczLjEsMzM3LjYsMTY2LjQsMzI5LjMsMTY2LjR6Ii8+DQoJCTwvZz4NCgk8L2c+DQo8L2c+DQo8L3N2Zz4=';

    /**
     * Providers.
     *
     * @var array<ProviderInterface>
     */
    private array $providers;

    /**
     * Constructor. Hooks into WordPress actions.
     *
     * @param array<ProviderInterface> $providers List all provider.
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
        add_action('wp_ajax_' . MSO_AI_Meta_Description::AJAX_NONCE_ACTION, [$this, 'handle_ajax_save_settings']);
    }

    /**
     * Adds the plugin's settings page to the WordPress Settings menu.
     * This should be hooked into 'admin_menu'.
     */
    public function add_options_page(): void
    {
        add_menu_page(
            esc_html__('MSO AI Meta Description Settings', 'mso-ai-meta-description'),
            esc_html__('Meta Description', 'mso-ai-meta-description'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_options_page'],
            self::ICON_BASE64_SVG,
            25
        );
    }

    /**
     * Renders the HTML content of the options page, including tabs and the settings form.
     */
    public function render_options_page(): void
    {
        $tabs = $this->get_tabs();

        if (empty($tabs)) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('No AI providers found or loaded.', 'mso-ai-meta-description'); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce(sanitize_key($_GET['_wpnonce']))) {
            $active_tab = isset($_GET['tab']) && array_key_exists(sanitize_text_field(wp_unslash($_GET['tab'])), $tabs) ? sanitize_text_field(wp_unslash($_GET['tab'])) : array_key_first($tabs);
            ?>
            <div class="wrap"> <!-- Standard WordPress admin page wrapper -->
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                <!-- Placeholder for AJAX success/error messages -->
                <div id="mso-ai-settings-messages" class="mso-ai-settings-messages"></div>

                <!-- Navigation tabs -->
                <h2 class="nav-tab-wrapper">
                    <?php
                    foreach ($tabs as $tab_slug => $tab_label) {
                        $tab_url = add_query_arg(
                            ['page' => self::PAGE_SLUG, 'tab' => $tab_slug],
                            admin_url('admin.php')
                        );

                        $tab_url = wp_nonce_url($tab_url, 'action');
                        $active_class = ($active_tab === $tab_slug) ? ' nav-tab-active' : '';

                        printf('<a href="%s" class="nav-tab%s">%s</a>', esc_url($tab_url), esc_attr($active_class), esc_html($tab_label));
                    }
            ?>
                </h2>

                <!-- Settings form - Submission is handled via AJAX, so 'action' is empty -->
                <form method="post" action="" id="mso-ai-settings-form">
                    <?php
            settings_fields(self::OPTIONS_GROUP);

            $section_id = self::get_section_id_for_provider($active_tab);
            do_settings_sections($section_id);

            submit_button(
                esc_html__('Save Changes', 'mso-ai-meta-description'),
                'primary',
                'mso-ai-save-settings-button',
                true,
                ['id' => 'mso-ai-submit-button']
            );
            ?><span class="spinner mso-ai-spinner"></span>
                </form>
            </div>
            <?php
        } else {
            wp_die(esc_html__('Invalid nonce specified.', 'mso-ai-meta-description'), esc_html__('Error', 'mso-ai-meta-description'), ['response' => 403, 'back_link' => true,]);
        }
    }

    /**
     * Defines the available tabs for the settings page dynamically based on loaded providers.
     *
     * @return array<string, string> Associative array where keys are provider names (tab slugs) and values are display labels.
     */
    private function get_tabs(): array
    {
        $tabs = [];

        foreach ($this->providers as $provider) {
            $provider_name = $provider->get_name();
            $tabs[$provider_name] = sprintf(/* translators: 1: label */ esc_html__('%s Settings', 'mso-ai-meta-description'), ucfirst($provider_name));
        }

        return $tabs;
    }

    /**
     * Helper method to consistently generate the section ID for a provider.
     *
     * @param string $provider_name The name of the provider (e.g., 'mistral').
     * @return string The generated section ID.
     */
    private static function get_section_id_for_provider(string $provider_name): string
    {
        return MSO_AI_Meta_Description::OPTION_PREFIX . $provider_name . '_section';
    }

    /**
     * Registers the plugin settings, sections, and fields dynamically based on loaded providers.
     * This should be hooked into 'admin_init'.
     */
    public function register_settings(): void
    {
        $option_group = self::OPTIONS_GROUP;
        $prefix = MSO_AI_Meta_Description::get_option_prefix();

        foreach ($this->providers as $provider) {
            $provider_name = $provider->get_name();
            $provider_title = $provider->get_title();
            $provider_url_api_key = $provider->get_url_api_key();
            $api_key_option = $prefix . $provider_name . '_api_key';
            $model_option = $prefix . $provider_name . '_model';
            $section_id = self::get_section_id_for_provider($provider_name);

            register_setting($option_group, $api_key_option, Settings::MY_ARRAY);
            register_setting($option_group, $model_option, Settings::MY_ARRAY);

            add_settings_section(
                $section_id,
                '',
                [$this, 'render_section_callback'],
                $section_id
            );

            add_settings_field(
                $api_key_option,
                sprintf(/* translators: 1: API key */ esc_html__('%s API Key', 'mso-ai-meta-description'), ucfirst($provider_name)),
                [$this, 'render_api_key_field'],
                $section_id,
                $section_id,
                ['provider' => $provider_name, 'provider_title' => $provider_title, 'provider_url_api_key' => $provider_url_api_key]
            );

            add_settings_field(
                $model_option,
                sprintf(/* translators: 1: Model */ esc_html__('%s Model', 'mso-ai-meta-description'), ucfirst($provider_name)),
                [$this, 'render_model_field'],
                $section_id,
                $section_id,
                ['provider' => $provider_name]
            );
        }

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
        $option_name = MSO_AI_Meta_Description::OPTION_PREFIX . 'front_page';
        register_setting('reading', $option_name, Settings::MY_ARRAY);
        add_settings_field('mso_ai_front_page_description_field', esc_html__('Front page meta description', 'mso-ai-meta-description'), [$this, 'render_front_page_description_input'], 'reading', 'default', ['label_for' => $option_name]);
    }

    /**
     * Handles the AJAX request to save settings for the currently active tab (provider).
     * Saves the default model if an API key is provided but no model is selected.
     */
    public function handle_ajax_save_settings(): void
    {
        check_ajax_referer(MSO_AI_Meta_Description::AJAX_NONCE_ACTION, 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Permission denied.', 'mso-ai-meta-description')], 403);
        }

        $active_tab = isset($_POST['active_tab']) ? sanitize_key($_POST['active_tab']) : null;
        if (! $active_tab) {
            wp_send_json_error(['message' => esc_html__('Missing active tab identifier.', 'mso-ai-meta-description')], 400);
        }

        $provider_instance = ProviderManager::get_provider($active_tab);
        if (! $provider_instance) {
            wp_send_json_error(['message' => sprintf(/* translators: %s: Settings tab name */ esc_html__('Unknown settings tab: %s', 'mso-ai-meta-description'), esc_html($active_tab))], 400);
        }

        $option_prefix = MSO_AI_Meta_Description::OPTION_PREFIX;
        $api_key_option = $option_prefix . $active_tab . '_api_key';
        $model_option = $option_prefix . $active_tab . '_model';
        $saved_data = [];

        if (isset($_POST[$api_key_option])) {
            $sanitized_api_key = sanitize_text_field(wp_unslash($_POST[$api_key_option]));
            update_option($api_key_option, $sanitized_api_key);
            $saved_data[$api_key_option] = $sanitized_api_key;
            $new_api_key_value = $sanitized_api_key;
        } else {
            update_option($api_key_option, '');
            $saved_data[$api_key_option] = '';
            $new_api_key_value = '';
        }

        $submitted_model = isset($_POST[$model_option]) ? sanitize_text_field(wp_unslash($_POST[$model_option])) : null;

        if (! empty($new_api_key_value) && empty($submitted_model)) {
            if (method_exists($provider_instance, 'get_default_model')) {
                $default_model = $provider_instance->get_default_model();
                update_option($model_option, $default_model);
                $saved_data[$model_option] = $default_model;
            } else {
                update_option($model_option, '');
                $saved_data[$model_option] = '';
            }
        } elseif (isset($submitted_model)) {
            update_option($model_option, $submitted_model);
            $saved_data[$model_option] = $submitted_model;
        } else {
            update_option($model_option, '');
            $saved_data[$model_option] = '';
        }

        wp_send_json_success(['message' => esc_html__('Settings saved successfully.', 'mso-ai-meta-description'), 'saved_data' => $saved_data,
        ]);
    }

    /**
     * Renders descriptive text for each settings section.
     *
     * @param array<string, string> $args Arguments passed from add_settings_section, contains 'id'.
     */
    public function render_section_callback(array $args): void
    {
        $section_id = $args['id'];
        $prefix_length = strlen(MSO_AI_Meta_Description::OPTION_PREFIX);
        $suffix_length = strlen('_section');
        $provider_name = substr($section_id, $prefix_length, -$suffix_length);

        if ($provider_name) {
            echo '<p>' . sprintf(/* translators: %s: Provider name (e.g., Mistral) */ esc_html__('Configure the settings for using the %s API.', 'mso-ai-meta-description'), esc_html(ucfirst($provider_name))) . '</p>';
        }
    }

    /**
     * Renders the HTML for an API key input field (password type) with a show/hide button.
     * (No changes needed here as it already uses $args['provider'])
     *
     * @param array<string, string> $args Arguments passed from add_settings_field, should contain 'provider'.
     */
    public function render_api_key_field(array $args): void
    {
        $provider = $args['provider'];
        $provider_name_display = $args['provider_title'];
        $provider_url_api_key = $args['provider_url_api_key'];
        $option_name = MSO_AI_Meta_Description::OPTION_PREFIX . $provider . '_api_key';
        $value = (string)get_option($option_name, '');
        $field_id = esc_attr($option_name . '_id');

        printf('<input type="password" class="regular-text" name="%s" id="%s" value="%s" autocomplete="new-password">', esc_attr($option_name), esc_attr($field_id), esc_attr($value));
        printf('<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="%s">
                <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
            </button>', esc_attr__('Show password', 'mso-ai-meta-description'));

        printf('<p class="description"><a href="%s" target="_blank">%s</a></p>', esc_url($provider_url_api_key), sprintf(/* translators: %s: Provider name (e.g., Mistral, Gemini, OpenAI) */ esc_html__('Get your %s API key', 'mso-ai-meta-description'), esc_html($provider_name_display)));
    }

    /**
     * Renders the HTML for a model selection dropdown (<select>).
     * (No changes needed here as it already uses $args['provider'])
     *
     * @param array<string, string> $args Arguments passed from add_settings_field, should contain 'provider'.
     */
    public function render_model_field(array $args): void
    {
        $provider = $args['provider'] ?? 'unknown';
        $option_name = MSO_AI_Meta_Description::OPTION_PREFIX . $provider . '_model';
        $select_id = MSO_AI_Meta_Description::OPTION_PREFIX . $provider . '_model_id';
        $current_value = (string)get_option($option_name, '');
        printf('<select id="%s" name="%s" data-provider="%s" class="mso-model-select regular-text">', esc_attr($select_id), esc_attr($option_name), esc_attr($provider));
        echo '<option value="">' . esc_html__('Loading models...', 'mso-ai-meta-description') . '</option>';

        if (! empty($current_value)) {
            printf(/* translators: 1: id, 2: name */ '<option value="%s" selected>%s</option>', esc_attr($current_value), esc_html($current_value));
        }

        echo '</select><span class="spinner mso-ai-spinner"></span>';
        echo '<p class="description">' . sprintf(/* translators: %s: Provider name (e.g., Mistral) */ esc_html__('Select the %s model to use. Models loaded dynamically if API key is valid.', 'mso-ai-meta-description'), esc_html(ucfirst($provider))) . '</p>';
        echo '<div id="mso-model-error-' . esc_attr($provider) . '" class="mso-ai-model-error"></div>';
    }

    /**
     * Renders the input field for the front page meta description on the Reading settings page.
     * Includes basic character counting functionality via inline JavaScript.
     *
     * @param array<string, string> $args Arguments passed from add_settings_field. Contains 'label_for'.
     */
    public function render_front_page_description_input(array $args): void
    {
        $option_name = MSO_AI_Meta_Description::OPTION_PREFIX . 'front_page';
        $value = (string)get_option($option_name, '');
        $field_id = esc_attr($args['label_for']);
        ?><label>
        <input
                type="text"
                name="<?php echo esc_attr($option_name); ?>"
                id="<?php echo esc_attr($field_id); ?>"
                class="regular-text"
                value="<?php echo esc_attr($value); ?>"
                maxlength="<?php echo esc_attr((string)(MSO_AI_Meta_Description::MAX_DESCRIPTION_LENGTH + 20)); ?>"
                aria-describedby="front-page-meta-description-hint"
        ></label>
        <p class="description" id="front-page-meta-description-hint">
            <?php printf(/* translators: 1: Minimum length, 2: Maximum length */ esc_html__('Enter the meta description for the site\'s front page when it displays the latest posts. Recommended length: %1$d-%2$d characters.', 'mso-ai-meta-description'), esc_html((string)MSO_AI_Meta_Description::MIN_DESCRIPTION_LENGTH), esc_html((string)(MSO_AI_Meta_Description::MAX_DESCRIPTION_LENGTH))); ?>
            <?php esc_html_e('Character count:', 'mso-ai-meta-description'); ?>
            <span class="mso-ai-char-count">0</span> <span class="mso-ai-length-indicator"></span>
        </p>
        <script>
            if (typeof window.msoFrontPageDescInit === 'undefined') {
                window.msoFrontPageDescInit = true;
                jQuery(document).ready(function ($) {
                    const inputField = $('#<?php echo esc_js($field_id); ?>');
                    const countSpan = inputField.closest('label').next('.description').find('.mso-ai-char-count');
                    const indicatorTextIndicator = inputField.closest('label').next('.description').find('.mso-ai-length-indicator');
                    if (inputField.length && countSpan.length) {
                        const updateCount = function () {
                            const length = inputField.val().length;
                            let color = 'inherit';
                            let indicatorText = '';
                            let status = [
                                '<?php esc_html_e('(Too short)', 'mso-ai-meta-description') ?>',
                                '<?php esc_html_e('(Too long)', 'mso-ai-meta-description') ?>',
                                '<?php esc_html_e('(Good)', 'mso-ai-meta-description') ?>',
                            ];
                            if (length > 0) {
                                if (length < <?php  echo esc_js((string)MSO_AI_Meta_Description::MIN_DESCRIPTION_LENGTH) ?>) {
                                    color = 'orange';
                                    indicatorText = status[0];
                                } else if (length > <?php  echo esc_js((string)MSO_AI_Meta_Description::MAX_DESCRIPTION_LENGTH) ?>) {
                                    color = 'red';
                                    indicatorText = status[1];
                                } else {
                                    color = 'green';
                                    indicatorText = status[2];
                                }
                            }
                            countSpan.text(length);
                            indicatorTextIndicator.text(indicatorText).css('color', color);
                        };
                        inputField.on('input change keyup', updateCount);
                        updateCount();
                    }
                });
            }
        </script>
        <?php
    }
}
