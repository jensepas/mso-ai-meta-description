<?php

/**
 * MSO AI Meta Description Settings Page
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

if (! defined('ABSPATH')) {
    die;
}

/**
 * Manages the rendering of the plugin's settings page and its fields.
 */
class SettingsPage
{
    /**
     * The slug for the settings page.
     * @var string
     */
    public const string PAGE_SLUG = 'admin_mso_ai_meta_description';

    /**
     * The options group name used by register_setting().
     * @var string
     */
    private const string OPTIONS_GROUP = 'mso_ai_meta_description_options';

    /**
     * Menu icon.
     * @var string
     */
    public const string ICON_BASE64_SVG = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pg0KPHN2ZyBmaWxsPSIjMDAwMDAwIiBoZWlnaHQ9IjgwMHB4IiB3aWR0aD0iODAwcHgiIHZlcnNpb249IjEuMSIgaWQ9IkNhcGFfMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgDQoJIHZpZXdCb3g9IjAgMCA0OTAgNDkwIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxnPg0KCTxnPg0KCQk8cGF0aCBkPSJNNDE2LDBINzRDMzMuMywwLDAsMzMuNCwwLDc0djM0MmMwLDQwLjcsMzMuNCw3NCw3NCw3NGgzNDJjNDAuNywwLDc0LTMzLjQsNzQtNzRWNzRDNDkwLDMzLjQsNDU2LjYsMCw0MTYsMHogTTQ0OS4zLDQxNg0KCQkJYzAsMTguOC0xNC42LDMzLjQtMzMuNCwzMy40SDc0Yy0xOC44LDAtMzMuNC0xNC42LTMzLjQtMzMuNFY3NGMwLTE4LjgsMTQuNi0zMy40LDMzLjQtMzMuNGgzNDJjMTguOCwwLDMzLjQsMTQuNiwzMy40LDMzLjR2MzQyDQoJCQlINDQ5LjN6Ii8+DQoJCTxnPg0KCQkJPHBhdGggZD0iTTIzNC44LDE2OS44Yy0yLjQtNS41LTcuOC05LTEzLjgtOXMtMTEuNCwzLjUtMTMuOCw5TDE0NywzMDguM2MtMy4zLDcuNiwwLjIsMTYuNCw3LjgsMTkuN2MyLDAuOSw0LDEuMyw2LDEuMw0KCQkJCWM1LjgsMCwxMS4zLTMuNCwxMy44LTlsMTMuMi0zMC4yaDY2LjlsMTMuMiwzMC4yYzMuMyw3LjYsMTIuMSwxMS4xLDE5LjcsNy44YzcuNi0zLjMsMTEuMS0xMi4yLDcuOC0xOS43TDIzNC44LDE2OS44eg0KCQkJCSBNMjAwLjcsMjYwbDIwLjQtNDYuOGwyMC40LDQ2LjhIMjAwLjd6Ii8+DQoJCQk8cGF0aCBkPSJNMzI5LjMsMjE3LjljLTguMywwLTE1LDYuNy0xNSwxNXY4MS40YzAsOC4zLDYuNywxNSwxNSwxNXMxNS02LjcsMTUtMTV2LTgxLjRDMzQ0LjMsMjI0LjYsMzM3LjYsMjE3LjksMzI5LjMsMjE3Ljl6Ii8+DQoJCQk8cGF0aCBkPSJNMzI5LjMsMTY2LjRjLTguMywwLTE1LDYuNy0xNSwxNXY0YzAsOC4zLDYuNywxNSwxNSwxNXMxNS02LjcsMTUtMTV2LTRDMzQ0LjMsMTczLjEsMzM3LjYsMTY2LjQsMzI5LjMsMTY2LjR6Ii8+DQoJCTwvZz4NCgk8L2c+DQo8L2c+DQo8L3N2Zz4=';

    /**
     * Options tab slug.
     * @var string
     */
    public const string OPTIONS_TAB_SLUG = 'options';

    /**
     * Providers.
     * @var array<ProviderInterface>
     */
    private array $providers;

    /**
     * Constructor.
     * @param array<ProviderInterface> $providers List of available providers.
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
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
     * Registers the admin menu hook.
     */
    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'add_options_page']);
    }

    /**
     * Adds the plugin's settings page to the WordPress Settings menu.
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
     * Renders the HTML content of the options page.
     */
    public function render_options_page(): void
    {
        $tabs = $this->get_tabs();

        if (empty($tabs)) {
            $this->render_no_tabs_warning();

            return;
        }

        $active_tab = $this->get_current_active_tab($tabs);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder">
                    <div id="post-body-content">
                        <div class="postbox">
                            <div class="inside">
                                <div id="mso-ai-settings-messages" class="mso-ai-settings-messages"></div>
                                <?php $this->render_navigation_tabs($tabs, $active_tab); ?>
                                <?php $this->render_settings_form($tabs, $active_tab); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renders a warning message when no tabs are available.
     * @private
     */
    private function render_no_tabs_warning(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="notice notice-warning inline">
                <p><?php esc_html_e('No AI providers found or enabled in Settings.', 'mso-ai-meta-description'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Determines the currently active tab slug.
     *
     * @param array<string, string> $tabs Available tabs.
     * @return string The slug of the active tab.
     * @private
     */
    private function get_current_active_tab(array $tabs): string
    {
        if (isset($_GET['tab'])) {
            $requested_tab = sanitize_text_field(wp_unslash($_GET['tab']));

            if (array_key_exists($requested_tab, $tabs)) {
                $nonce_action = 'view-settings-tab-' . $requested_tab;

                if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), $nonce_action)) {
                    return $requested_tab;
                }
            }
        }

        return array_key_first($tabs) ?? '';
    }

    /**
     * Renders the navigation tabs.
     *
     * @param array<string, string> $tabs       Available tabs.
     * @param string                $active_tab The slug of the currently active tab.
     * @private
     */
    private function render_navigation_tabs(array $tabs, string $active_tab): void
    {
        $allowed_tab_html = [
            'a' => [
                'href' => true,
                'class' => true,
            ],
            'span' => [
                'class' => true,
                'title' => true,
            ],
        ];
        ?>
        <h2 class="nav-tab-wrapper">
            <?php
            foreach ($tabs as $tab_slug => $tab_label) {
                echo wp_kses(
                    $this->build_tab_link($tab_slug, $tab_label, $active_tab),
                    $allowed_tab_html
                );
            }
        ?>
        </h2>
        <?php
    }

    /**
     * Builds the HTML for a single navigation tab link.
     *
     * @param string $tab_slug   The slug for this tab.
     * @param string $tab_label  The display label for this tab.
     * @param string $active_tab The slug of the currently active tab.
     * @return string HTML link element for the tab.
     * @private
     */
    private function build_tab_link(string $tab_slug, string $tab_label, string $active_tab): string
    {
        $tab_url = add_query_arg(
            ['page' => self::PAGE_SLUG, 'tab' => $tab_slug],
            admin_url('admin.php')
        );

        $active_class = ($active_tab === $tab_slug) ? ' nav-tab-active' : '';
        $tab_url = wp_nonce_url($tab_url, 'view-settings-tab-' . $tab_slug);

        $is_configured = true;
        if ($tab_slug !== self::OPTIONS_TAB_SLUG) {
            $prefix = MSO_AI_Meta_Description::get_option_prefix();
            $api_key_set = (bool) get_option($prefix . $tab_slug . '_api_key');
            $model_set = (bool) get_option($prefix . $tab_slug . '_model');
            $is_configured = $api_key_set && $model_set;
        }
        $warning_icon = $is_configured ? '' : ' <span class="dashicons dashicons-warning" title="' . esc_attr__('API Key or Model might be missing', 'mso-ai-meta-description') . '"></span>';

        return sprintf(
            '<a href="%s" class="nav-tab%s">%s%s</a>',
            esc_url($tab_url),
            esc_attr($active_class),
            esc_html($tab_label),
            $warning_icon // Already includes potential span tag
        );
    }

    /**
     * Renders the settings form structure and content.
     *
     * @param array<string, string> $tabs       Available tabs.
     * @param string                $active_tab The slug of the currently active tab.
     * @private
     */
    private function render_settings_form(array $tabs, string $active_tab): void
    {
        ?>
        <form method="post" action="" id="mso-ai-settings-form">
            <?php
            settings_fields(self::OPTIONS_GROUP);

        $this->render_settings_sections($tabs, $active_tab);

        submit_button(
            esc_html__('Save Changes', 'mso-ai-meta-description'),
            'primary',
            'mso-ai-save-settings-button', // Generic ID, JS can target form submit
            true,
            ['id' => 'mso-ai-submit-button-' . esc_attr($active_tab)] // Keep specific ID if needed by JS
        );
        ?>
            <span class="spinner mso-ai-spinner"></span>
        </form>
        <?php
    }

    /**
     * Renders the appropriate settings sections based on the active tab.
     *
     * @param array<string, string> $tabs       Available tabs.
     * @param string                $active_tab The slug of the currently active tab.
     * @private
     */
    private function render_settings_sections(array $tabs, string $active_tab): void
    {
        $advanced_section_id = self::OPTIONS_GROUP . '_advanced_section';

        if ($active_tab === self::OPTIONS_TAB_SLUG) {
            do_settings_sections($advanced_section_id);
        } elseif (array_key_exists($active_tab, $tabs)) {
            $section_id = self::get_section_id_for_provider($active_tab);
            do_settings_sections($section_id);
        } else {
            echo '<p>' . esc_html__('Please select a valid settings tab.', 'mso-ai-meta-description') . '</p>';
        }
    }

    /**
     * Defines the available tabs for the settings page dynamically based on loaded providers.
     *
     * @return array<string, string> Associative array where keys are provider names (tab slugs) and values are display labels.
     */
    private function get_tabs(): array
    {
        $tabs[self::OPTIONS_TAB_SLUG] = esc_html__('Settings', 'mso-ai-meta-description');

        $prefix = MSO_AI_Meta_Description::get_option_prefix();

        foreach ($this->providers as $provider) {
            $provider_name = $provider->get_name();
            $provider_title = $provider->get_title();
            $enable_option_name = $prefix . $provider_name . '_provider_enabled';

            if (get_option($enable_option_name, false)) {
                /* translators: %s: Provider name (e.g., Mistral) */
                $tabs[$provider_name] = sprintf(esc_html__('%s Settings', 'mso-ai-meta-description'), $provider_title);
            }
        }

        return $tabs;
    }

    /**
     * Helper function to get the default prompt text template.
     * Avoids duplication with AbstractProvider.
     *
     * @return string The default prompt template.
     */
    private function get_default_summary_prompt_template(): string
    {
        return
            /* translators: 1: min length, 2: max length, 3: content */
            __('Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Maintain the language of the original text. Output only the description text itself: %3$s', 'mso-ai-meta-description');
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
            echo '<h2>' . sprintf(/* translators: %s: Provider name (e.g., Mistral) */ esc_html__('Configure the settings for using the %s API.', 'mso-ai-meta-description'), esc_html(ucfirst($provider_name))) . '</h2>';
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
     * Renders the HTML for the custom summary prompt textarea field.
     *
     * @param array<string, string> $args Arguments passed from add_settings_field.
     */
    public function render_custom_prompt_field(array $args): void
    {
        $option_name = MSO_AI_Meta_Description::get_option_prefix() . $args['provider_name'] . '_custom_summary_prompt';
        $value = (string)get_option($option_name, '');
        $field_id = esc_attr($args['label_for']);
        $details_container_id = esc_attr($option_name . '_details');
        $default_prompt = $this->get_default_summary_prompt_template();

        $is_initially_visible = ! empty($value);
        $initial_display_style = $is_initially_visible ? '' : 'display: none;';
        $initial_aria_expanded = $is_initially_visible ? 'true' : 'false';
        $initial_link_text = $is_initially_visible
            ? esc_html__('Hide custom prompt', 'mso-ai-meta-description')
            : esc_html__('Customize the prompt', 'mso-ai-meta-description');

        printf(
            '<a href="#" class="mso-ai-toggle-prompt" role="button" aria-expanded="%s" aria-controls="%s">%s</a>',
            esc_attr($initial_aria_expanded),
            esc_attr($details_container_id),
            esc_html($initial_link_text)
        );

        echo '<div id="' . esc_attr($details_container_id) . '" class="mso-ai-prompt-details"  style="' . esc_attr($initial_display_style) . '">';

        echo '<textarea name="' . esc_attr($option_name) . '" id="' . esc_attr($field_id) . '" class="large-text" rows="8" placeholder="' . esc_attr($default_prompt) . '">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' .
            esc_html__('Customize the prompt sent to the AI for generating meta descriptions. Leave empty to use the default prompt.', 'mso-ai-meta-description') . '<br>' .
            esc_html__('Available placeholders:', 'mso-ai-meta-description') . ' <code>%1$d</code> (' . esc_html__('min length 120 characters', 'mso-ai-meta-description') . '), <code>%2$d</code> (' . esc_html__('max length 160 characters', 'mso-ai-meta-description') . '), <code>%3$s</code> (' . esc_html__('content', 'mso-ai-meta-description') . ').<br>' .
            '<strong>' . esc_html__('Default prompt:', 'mso-ai-meta-description') . '</strong><br><em>' . esc_html($default_prompt) . '</em>' .
            '</p>';

        echo '</div>';
    }

    /**
     * Renders the HTML for the provider enable/disable checkbox field.
     *
     * @param array<string, string> $args Arguments passed from add_settings_field.
     *                                    Should contain 'provider_name' and 'provider_title'.
     */
    public function render_provider_enable_field(array $args): void
    {
        $provider_name = $args['provider_name'];
        $provider_title = $args['provider_title'];
        if (empty($provider_name)) {
            return;
        }

        $option_name = MSO_AI_Meta_Description::get_option_prefix() . $provider_name . '_provider_enabled';
        $value = get_option($option_name, false);
        $field_id = esc_attr($args['label_for']);

        echo '<label for="' . esc_attr($field_id) . '">';
        echo '<input type="checkbox" name="' . esc_attr($option_name) . '" id="' . esc_attr($field_id) . '" value="1" ' . checked(1, $value, false) . '>';
        echo '</label>';
        /* translators: 1: Provider name (e.g., Mistral) */
        echo sprintf(esc_html__('Enable %s', 'mso-ai-meta-description'), esc_html($provider_title));
        echo '<br>';
        /* translators: 1: Provider name (e.g., Mistral) */
        echo sprintf(esc_html__('Show the "Generate with %1$s" button in the WordPress Editor.', 'mso-ai-meta-description'), esc_html($provider_title));
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
        ?><label for="<?php echo esc_attr($field_id); ?>">
        <input
                type="text"
                name="<?php echo esc_attr($option_name); ?>"
                id="<?php echo esc_attr($field_id); ?>"
                class="regular-text"
                value="<?php echo esc_attr($value); ?>"
                maxlength="<?php echo esc_attr((string)(MSO_AI_Meta_Description::MAX_DESCRIPTION_LENGTH + 50)); ?>"
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
