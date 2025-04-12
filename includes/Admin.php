<?php
/**
 * MSO Meta Description Admin
 *
 * @package MSO_Meta_Description
 * @since   1.2.0
 */
namespace MSO_Meta_Description;

if (!defined('ABSPATH')) {
    die;
}

class Admin
{
    private Settings $settings;
    private MetaBox $meta_box;

    public function __construct(Settings $settings, MetaBox $meta_box)
    {
        $this->settings = $settings;
        $this->meta_box = $meta_box;
    }

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this->settings, 'add_options_page']);
        add_action('admin_init', [$this->settings, 'register_settings']);
        // Front page setting registration moved to Settings class

        add_action('add_meta_boxes', [$this->meta_box, 'add_meta_box']);
        add_action('save_post', [$this->meta_box, 'save_meta_data']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts(string $hook_suffix): void
    {
        // Only enqueue on relevant pages (post edit screens and plugin settings page)
        $screen = get_current_screen();
        $is_post_edit_page = $screen && $screen->base === 'post';
        $is_settings_page = $hook_suffix === 'settings_page_admin_mso_meta_description'; // Correct hook for add_options_page

        if (!$is_post_edit_page && !$is_settings_page) {
            return;
        }

        wp_enqueue_script(
            'mso-admin-script',
            plugin_dir_url(dirname(__FILE__)) . 'js/mso-script.js', // Adjusted path
            ['jquery'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'js/mso-script.js'), // Adjusted path
            true
        );
        wp_enqueue_style(
            'mso-admin-style',
            plugin_dir_url(dirname(__FILE__)) . 'css/admin.css', // Adjusted path
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'css/admin.css') // Adjusted path
        );
        // Prepare data for JavaScript
        $script_vars = [
            'geminiModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'gemini_model', ApiClient::DEFAULT_GEMINI_MODEL),
            'mistralModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'mistral_model', ApiClient::DEFAULT_MISTRAL_MODEL),
            'openaiModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'openai_model', ApiClient::DEFAULT_OPENAI_MODEL), // Assurez-vous que la constante existe dans ApiClient
            'status' => [
                __('(Too short)', 'mso-meta-description'),
                __('(Too long)', 'mso-meta-description'),
                __('(Good)', 'mso-meta-description')
            ],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'selectModel' => __('-- Select a Model --', 'mso-meta-description'),
            'errorLoadingModels' => __('Error loading models.', 'mso-meta-description'),
            'nonce' => wp_create_nonce(MSO_Meta_Description::AJAX_NONCE), // Use constant
            'geminiApiKeySet' => !empty(get_option(MSO_Meta_Description::OPTION_PREFIX . 'gemini_api_key')),
            'mistralApiKeySet' => !empty(get_option(MSO_Meta_Description::OPTION_PREFIX . 'mistral_api_key')),
            'openaiApiKeySet' => !empty(get_option(MSO_Meta_Description::OPTION_PREFIX . 'openai_api_key')),
            'selectedGeminiModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'gemini_model', ApiClient::DEFAULT_GEMINI_MODEL),
            'selectedMistralModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'mistral_model', ApiClient::DEFAULT_MISTRAL_MODEL),
            'selectedOpenaiModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'openai_model', ApiClient::DEFAULT_OPENAI_MODEL),
        ];

        wp_localize_script('mso-admin-script', 'msoScriptVars', $script_vars);

        // Optionally enqueue admin CSS here if needed
        // wp_enqueue_style('mso-admin-style', plugin_dir_url(dirname(__FILE__)) . 'css/admin-style.css', [], MSO_Meta_Description::VERSION);
    }

    /**
     * Add a settings link to the plugin's action links.
     *
     * @param array $links An array of plugin action links.
     * @return array An updated array of plugin action links.
     */
    public function add_settings_link(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=admin_mso_meta_description')),
            __('Settings', 'mso-meta-description')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
}