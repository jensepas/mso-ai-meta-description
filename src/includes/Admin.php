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
        add_action('admin_head', [$this, 'add_contextual_help']);
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
            'saving_text' => __('Saving...', 'mso-ai-meta-description'),
            'saved_text' => __('Settings Saved', 'mso-ai-meta-description'),
            'error_text' => __('Error Saving Settings', 'mso-ai-meta-description'),
            'i18n_show_prompt' => __('Customize the prompt', 'mso-ai-meta-description'),
            'i18n_hide_prompt' => __('Hide custom prompt', 'mso-ai-meta-description'),
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

    /**
     * Adds contextual help tabs to the plugin's settings page.
     *
     * This method is hooked into 'admin_head'.
     */
    public function add_contextual_help(): void
    {
        $screen = get_current_screen();

        $settings_page_hook = 'toplevel_page_' . Settings::PAGE_SLUG;
        if (! $screen || $screen->id !== $settings_page_hook) {
            return;
        }

        $screen->add_help_tab([
            'id' => 'mso_ai_help_overview',
            'title' => __('Overview', 'mso-ai-meta-description'),
            'content' => '<p>' . __('This page allows you to configure the API keys and select models for the different AI providers used by MSO AI Meta Description.', 'mso-ai-meta-description') . '</p>' .
                '<p>' . __('Navigate through the tabs (Mistral, Gemini, etc.) to enter your credentials for each service you want to use.', 'mso-ai-meta-description') . '</p>' .
                '<p>' . __('Once an API key is saved and valid, the available models for that provider will be loaded automatically in the dropdown.', 'mso-ai-meta-description') . '</p>',
        ]);

        $screen->add_help_tab([
            'id' => 'mso_ai_help_api_keys',
            'title' => __('API Keys', 'mso-ai-meta-description'),
            'content' => '<p>' . __('You need to obtain an API key from each AI provider you wish to use (OpenAI, Mistral, Gemini, Anthropic, Cohere).', 'mso-ai-meta-description') . '</p>' .
                '<p>' . __('Enter the corresponding API key in the input field for each provider and click "Save Changes".', 'mso-ai-meta-description') . '</p>' .
                '<p>' . __('Make sure your keys have the necessary permissions to list models and generate text.', 'mso-ai-meta-description') . '</p>',
        ]);

        $screen->set_help_sidebar(
            '<p><strong>' . __('For more information:', 'mso-ai-meta-description') . '</strong></p>' .
            '<p><a href="https://www.ms-only.fr/" target="_blank">' . __('Plugin Website', 'mso-ai-meta-description') . '</a></p>' .
            '<p><a href="https://wordpress.org/support/plugin/mso-ai-meta-description/" target="_blank">' . __('Support Forum', 'mso-ai-meta-description') . '</a></p>'
        );
    }
}
