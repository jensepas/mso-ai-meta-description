<?php
/**
 * MSO AI Meta Description Settings Registry
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
 * Manages the registration of plugin settings, sections, and fields with WordPress.
 */
class SettingsRegistry
{
    /**
     * The options group name used by register_setting().
     * @var string
     */
    public const string OPTIONS_GROUP = 'mso_ai_meta_description_options';

    /**
     * Constant register_setting.
     * @var array<string, string|null>
     */
    public const array SANITIZE_TEXT_FIELD = ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => null];

    /**
     * Constant register_setting.
     * @var array<string, string|null>
     */
    public const array SANITIZE_TEXTAREA_FIELD = ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => null];

    /**
     * Providers.
     * @var array<ProviderInterface>
     */
    private array $providers;

    /**
     * Instance of SettingsPage to access rendering callbacks.
     * @var SettingsPage
     */
    private SettingsPage $settings_page_renderer;

    /**
     * Constructor.
     * @param array<ProviderInterface> $providers List of available providers.
     * @param SettingsPage $settings_page_renderer Instance for accessing field renderers.
     */
    public function __construct(array $providers, SettingsPage $settings_page_renderer)
    {
        $this->providers = $providers;
        $this->settings_page_renderer = $settings_page_renderer;
    }

    /**
     * Registers the admin_init hook.
     */
    public function register_hooks(): void
    {
        add_action('admin_init', [$this, 'register_settings']);
        // Hook for front page setting needs to be conditional or always present
        if ('posts' === get_option('show_on_front')) {
            add_action('admin_init', [$this, 'register_front_page_setting_hook']);
        }
    }

    /**
     * Registers the plugin settings, sections, and fields dynamically.
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
            $custom_prompt_option_name = $prefix . $provider_name . '_custom_summary_prompt'; // Corrected: Per provider
            $section_id = self::get_section_id_for_provider($provider_name);

            register_setting($option_group, $api_key_option, self::SANITIZE_TEXT_FIELD);
            register_setting($option_group, $model_option, self::SANITIZE_TEXT_FIELD);
            register_setting($option_group, $custom_prompt_option_name, self::SANITIZE_TEXTAREA_FIELD); // Corrected callback

            add_settings_section(
                $section_id,
                '',
                [$this->settings_page_renderer, 'render_section_callback'],
                $section_id
            );

            add_settings_field(
                $api_key_option,
                sprintf(/* translators: %s: Provider name */ esc_html__('%s API Key', 'mso-ai-meta-description'), ucfirst($provider_title)), // Use title
                [$this->settings_page_renderer, 'render_api_key_field'],
                $section_id,
                $section_id,
                ['provider' => $provider_name, 'provider_title' => $provider_title, 'provider_url_api_key' => $provider_url_api_key]
            );

            add_settings_field(
                $model_option,
                sprintf(/* translators: %s: Provider name */ esc_html__('%s Model', 'mso-ai-meta-description'), ucfirst($provider_title)), // Use title
                [$this->settings_page_renderer, 'render_model_field'],
                $section_id,
                $section_id,
                ['provider' => $provider_name]
            );

            add_settings_field(
                $custom_prompt_option_name . $provider_name,
                esc_html__('Custom Prompt', 'mso-ai-meta-description'),
                [$this->settings_page_renderer, 'render_custom_prompt_field'],
                $section_id,
                $section_id,
                ['label_for' => $custom_prompt_option_name . '_id', 'provider_name' => $provider_name]
            );
        }

        $advanced_section_id = self::OPTIONS_GROUP . '_advanced_section';
        add_settings_section(
            $advanced_section_id,
            '',
            [$this->settings_page_renderer, 'render_section_callback'],
            $advanced_section_id
        );

        foreach ($this->providers as $provider) {
            $provider_name = $provider->get_name();
            $provider_title = $provider->get_title();
            $enable_option_name = $prefix . $provider_name . '_provider_enabled';

            register_setting($option_group, $enable_option_name, self::SANITIZE_TEXT_FIELD);

            add_settings_field(
                $enable_option_name,
                esc_html($provider_title),
                [$this->settings_page_renderer, 'render_provider_enable_field'],
                $advanced_section_id,
                $advanced_section_id,
                [
                    'label_for' => $enable_option_name . '_id',
                    'provider_name' => $provider_name,
                    'provider_title' => $provider_title,
                ]
            );
        }
    }

    /**
     * Wrapper function to hook the front page setting registration.
     * Necessary because register_setting should be called on admin_init.
     */
    public function register_front_page_setting_hook(): void
    {
        $this->register_front_page_setting();
    }

    /**
     * Registers the setting field for the front page meta description on the 'Reading' settings page.
     * This needs to be called via admin_init hook.
     */
    private function register_front_page_setting(): void
    {
        $option_name = MSO_AI_Meta_Description::OPTION_PREFIX . 'front_page';
        register_setting('reading', $option_name, self::SANITIZE_TEXT_FIELD);
        add_settings_field(
            'mso_ai_front_page_description_field',
            esc_html__('Front page meta description', 'mso-ai-meta-description'),
            [$this->settings_page_renderer, 'render_front_page_description_input'],
            'reading',
            'default',
            ['label_for' => $option_name]
        );
    }

    /**
     * Helper method to consistently generate the section ID for a provider.
     * Made static as it doesn't depend on instance state.
     *
     * @param string $provider_name The name of the provider (e.g., 'mistral').
     * @return string The generated section ID.
     */
    public static function get_section_id_for_provider(string $provider_name): string
    {
        return MSO_AI_Meta_Description::OPTION_PREFIX . $provider_name . '_section';
    }
}
