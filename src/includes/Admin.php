<?php

/**
 * MSO AI Meta Description Admin Class
 *
 * Handles the administrative side of the plugin, including:
 * - Registering hooks for settings pages, meta boxes, and scripts.
 * - Enqueuing necessary admin scripts and styles.
 * - Adding a settings link to the plugin list page.
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
 * Manages admin-specific functionality.
 */
class Admin
{
    /**
     * Instance of the Settings class.
     */
    private Settings $settings;

    /**
     * Instance of the MetaBox class.
     */
    private MetaBox $meta_box;

    /**
     * Providers.
     * @var array<ProviderInterface>
     */
    private array $providers;

    /**
     * Constructor.
     *
     * Injects dependencies for Settings and MetaBox classes.
     *
     * @param Settings $settings The Settings class instance.
     * @param MetaBox  $meta_box The MetaBox class instance.
     * @param array<ProviderInterface> $providers.
     */
    public function __construct(Settings $settings, MetaBox $meta_box, array $providers)
    {
        $this->settings = $settings;
        $this->meta_box = $meta_box;
        $this->providers = $providers;
    }

    /**
     * Registers WordPress hooks for admin functionality.
     *
     * Hooks methods from the Settings and MetaBox classes into the appropriate
     * WordPress actions (admin_menu, admin_init, add_meta_boxes, save_post).
     * Also hooks the method for enqueuing admin scripts.
     */
    public function register_hooks(): void
    {
        add_action('admin_menu', [$this->settings, 'add_options_page']);
        add_action('admin_init', [$this->settings, 'register_settings']);
        add_action('add_meta_boxes', [$this->meta_box, 'add_meta_box']);
        add_action('save_post', [$this->meta_box, 'save_meta_data']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * Loads the necessary JavaScript and CSS files for the plugin's admin interface,
     * specifically on post edit screens and the plugin's settings page.
     * Also localizes script variables for use in JavaScript.
     *
     * @param string $hook_suffix The hook suffix of the current admin page.
     */
    public function enqueue_admin_scripts(string $hook_suffix): void
    {
        $screen = get_current_screen();
        $is_post_edit_page = $screen && $screen->base === 'post';
        $settings_page_hook = 'toplevel_page_' . Settings::PAGE_SLUG;
        $is_settings_page = $hook_suffix === $settings_page_hook;

        if (! $is_post_edit_page && ! $is_settings_page) {
            return;
        }

        wp_enqueue_script(
            'mso-ai-admin-script',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/mso-ai-main.js',
            ['jquery'],
            MSO_AI_Meta_Description::VERSION,
            true
        );

        wp_enqueue_style(
            'mso-ai-admin-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/mso-ai-admin.css',
            [],
            MSO_AI_Meta_Description::VERSION
        );

        $selected_models = [];
        $option_prefix = MSO_AI_Meta_Description::get_option_prefix();

        foreach ($this->providers as $provider) {
            $provider_name = $provider->get_name();
            $option_name = $option_prefix . $provider_name . '_model';
            $selected_models[$provider_name] = (string) get_option($option_name, '');
        }

        $script_vars = [
            'selectedModels' => $selected_models,
            'status' => [
                __('(Too short)', 'mso-ai-meta-description'),
                __('(Too long)', 'mso-ai-meta-description'),
                __('(Good)', 'mso-ai-meta-description'),
            ],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'selectModel' => __('-- Select a Model --', 'mso-ai-meta-description'),
            'errorLoadingModels' => __('Error loading models.', 'mso-ai-meta-description'),
            'nonce' => wp_create_nonce(MSO_AI_Meta_Description::AJAX_NONCE_ACTION),
            'selectedGeminiModel' => get_option(MSO_AI_Meta_Description::OPTION_PREFIX . 'gemini_model'),
            'selectedMistralModel' => get_option(MSO_AI_Meta_Description::OPTION_PREFIX . 'mistral_model'),
            'selectedOpenaiModel' => get_option(MSO_AI_Meta_Description::OPTION_PREFIX . 'openai_model'),
            'selectedAnthropicModel' => get_option(MSO_AI_Meta_Description::OPTION_PREFIX . 'anthropic_model'),
            'action' => MSO_AI_Meta_Description::AJAX_NONCE_ACTION,
            'saving_text' => esc_html__('Saving...', 'mso-ai-meta-description'),
            'saved_text' => esc_html__('Settings Saved', 'mso-ai-meta-description'),
            'error_text' => esc_html__('Error Saving Settings', 'mso-ai-meta-description'),
        ];

        wp_localize_script('mso-ai-admin-script', 'msoAiScriptVars', $script_vars);
    }

    /**
     * Add a settings link to the plugin's action links on the Plugins page.
     *
     * This method is typically hooked into 'plugin_action_links_{plugin_basename}'.
     *
     * @param array<string, string> $links An array of existing plugin action links (e.g., Activate, Deactivate, Edit).
     * @return array<int|string, string> An updated array of plugin action links including the new Settings link.
     */
    public function add_settings_link(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . Settings::PAGE_SLUG)),
            __('Settings', 'mso-ai-meta-description')
        );
        array_unshift($links, $settings_link);

        return $links;
    }
}
