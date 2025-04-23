<?php

/**
 * MSO AI Meta Description Settings Ajax Handler
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
 * Handles AJAX requests for saving plugin settings.
 */
class SettingsAjaxHandler
{
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
     * Registers the AJAX action hook.
     */
    public function register_hooks(): void
    {
        add_action('wp_ajax_' . MSO_AI_Meta_Description::AJAX_NONCE_ACTION, [$this, 'handle_ajax_save_settings']);
    }

    /**
     * Handles the AJAX request to save settings for the currently active tab.
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

        $option_prefix = MSO_AI_Meta_Description::OPTION_PREFIX;

        if ($active_tab === SettingsPage::OPTIONS_TAB_SLUG) {
            $saved_data = $this->save_settings($option_prefix);
        } else {
            $provider_instance = ProviderManager::get_provider($active_tab);
            if (! $provider_instance) {
                wp_send_json_error(['message' => sprintf(/* translators: %s: Settings tab name */ esc_html__('Unknown settings tab: %s', 'mso-ai-meta-description'), esc_html($active_tab))], 400);
            }
            $saved_data = $this->save_provider_settings($active_tab, $option_prefix, $provider_instance);
        }

        wp_send_json_success([
            'message' => esc_html__('Settings saved successfully.', 'mso-ai-meta-description'),
            'saved_data' => $saved_data,
        ]);
    }

    /**
     * Saves settings from the 'Settings' tab.
     * @param string $option_prefix The prefix for option names.
     * @return array<string, mixed> Data that was saved.
     * @private
     */
    private function save_settings(string $option_prefix): array
    {
        $saved_data = [];
        foreach ($this->providers as $provider) {
            $provider_name = $provider->get_name();
            $enable_option_name = $option_prefix . $provider_name . '_provider_enabled';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in the calling method handle_ajax_save_settings.
            $is_enabled = isset($_POST[$enable_option_name]) && rest_sanitize_boolean(sanitize_key($_POST[$enable_option_name]));
            update_option($enable_option_name, $is_enabled);
            $saved_data[$enable_option_name] = $is_enabled;
        }

        return $saved_data;
    }

    /**
     * Saves settings for a specific provider tab.
     * @param string            $provider_name    The name (slug) of the provider.
     * @param string            $option_prefix    The prefix for option names.
     * @param ProviderInterface $provider_instance The instance of the provider.
     * @return array<string, mixed> Data that was saved.
     * @private
     */
    private function save_provider_settings(string $provider_name, string $option_prefix, ProviderInterface $provider_instance): array
    {
        $saved_data = [];

        $api_key_option = $option_prefix . $provider_name . '_api_key';
        $new_api_key_value = '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in the calling method handle_ajax_save_settings.
        if (isset($_POST[$api_key_option])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in the calling method handle_ajax_save_settings.
            $sanitized_api_key = sanitize_text_field(wp_unslash($_POST[$api_key_option]));
            update_option($api_key_option, $sanitized_api_key);
            $saved_data[$api_key_option] = '***';
            $new_api_key_value = $sanitized_api_key;
        } else {
            update_option($api_key_option, '');
            $saved_data[$api_key_option] = '';
        }

        $model_option = $option_prefix . $provider_name . '_model';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in the calling method handle_ajax_save_settings.
        $submitted_model = isset($_POST[$model_option]) ? sanitize_text_field(wp_unslash($_POST[$model_option])) : null;
        $final_model_value = '';
        if (! empty($new_api_key_value) && empty($submitted_model)) {
            $default_model = $provider_instance->get_default_model();
            $final_model_value = $default_model;
        } elseif (isset($submitted_model)) {
            $final_model_value = $submitted_model;
        }
        update_option($model_option, $final_model_value);
        $saved_data[$model_option] = $final_model_value;

        $custom_prompt_option_name = wp_unslash($option_prefix . $provider_name . '_custom_summary_prompt');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in the calling method handle_ajax_save_settings.
        if (isset($_POST[$custom_prompt_option_name])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in the calling method handle_ajax_save_settings.
            $sanitized_prompt = sanitize_textarea_field(wp_unslash($_POST[$custom_prompt_option_name]));
            update_option($custom_prompt_option_name, $sanitized_prompt);
            $saved_data[$custom_prompt_option_name] = $sanitized_prompt;
        } else {
            update_option($custom_prompt_option_name, '');
            $saved_data[$custom_prompt_option_name] = '';
        }

        return $saved_data;
    }
}
