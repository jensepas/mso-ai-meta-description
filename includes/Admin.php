<?php
/**
 * MSO Meta Description Admin Class
 *
 * Handles the administrative side of the plugin, including:
 * - Registering hooks for settings pages, meta boxes, and scripts.
 * - Enqueuing necessary admin scripts and styles.
 * - Adding a settings link to the plugin list page.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    die;
}

/**
 * Manages admin-specific functionality.
 */
class Admin
{
    /**
     * Instance of the Settings class.
     * @var Settings
     */
    private Settings $settings;

    /**
     * Instance of the MetaBox class.
     * @var MetaBox
     */
    private MetaBox $meta_box;

    /**
     * Constructor.
     *
     * Injects dependencies for Settings and MetaBox classes.
     *
     * @param Settings $settings The Settings class instance.
     * @param MetaBox  $meta_box The MetaBox class instance.
     */
    public function __construct(Settings $settings, MetaBox $meta_box)
    {
        $this->settings = $settings;
        $this->meta_box = $meta_box;
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
        // Hook the method to add the plugin's options page to the admin menu.
        add_action('admin_menu', [$this->settings, 'add_options_page']);
        // Hook the method to register plugin settings with the WordPress Settings API.
        add_action('admin_init', [$this->settings, 'register_settings']);
        // Note: Front page setting registration is handled within the Settings class's register_settings method.

        // Hook the method to add the custom meta box to post editing screens.
        add_action('add_meta_boxes', [$this->meta_box, 'add_meta_box']);
        // Hook the method to save the custom meta data when a post is saved.
        add_action('save_post', [$this->meta_box, 'save_meta_data']);

        // Hook the method to enqueue scripts and styles on admin pages.
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
        // Get the current screen object to check its properties.
        $screen = get_current_screen();
        // Determine if the current page is a post edit screen (post, page, or custom post type).
        $is_post_edit_page = $screen && $screen->base === 'post';
        // Determine if the current page is the plugin's settings page.
        // Note: The hook suffix for pages added via add_options_page is 'settings_page_{menu_slug}'.
        $is_settings_page = $hook_suffix === $screen->id; // Use constant from Settings class

        // Only proceed if we are on a relevant admin page.
        if (!$is_post_edit_page && !$is_settings_page) {
            return; // Exit early if not on a relevant page.
        }

        // Enqueue the main admin JavaScript file.
        wp_enqueue_script(
            'mso-admin-script', // Unique handle for the script.
            // Get the URL for the script file relative to the plugin's root directory.
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/mso-script.js',
            ['jquery'], // Dependencies: This script requires jQuery.
            // Add file modification time as version number for cache busting.
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/mso-script.js'),
            true // Load the script in the footer.
        );

        // Enqueue the admin CSS file.
        wp_enqueue_style(
            'mso-admin-style', // Unique handle for the stylesheet.
            // Get the URL for the CSS file relative to the plugin's root directory.
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css', // Adjusted path
            [], // Dependencies: No CSS dependencies.
            // Add file modification time as version number for cache busting.
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/admin.css') // Adjusted path
        );

        // Prepare an array of PHP variables to pass to the JavaScript file ('mso-admin-script').
        $script_vars = [
            // Pass selected models (or defaults) from settings.
            'geminiModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'gemini_model'),
            'mistralModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'mistral_model'),
            'openaiModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'openai_model'),
            'anthropicModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'anthropic_model'),
            // Localized strings for character count status messages.
            'status' => [
                __('(Too short)', 'mso-meta-description'),
                __('(Too long)', 'mso-meta-description'),
                __('(Good)', 'mso-meta-description')
            ],
            // URL for WordPress AJAX requests.
            'ajaxUrl' => admin_url('admin-ajax.php'),
            // Localized string for the default option in model select dropdowns.
            'selectModel' => __('-- Select a Model --', 'mso-meta-description'),
            // Localized string for model loading errors.
            'errorLoadingModels' => __('Error loading models.', 'mso-meta-description'),
            // Security nonce for AJAX requests initiated by this script.
            'nonce' => wp_create_nonce(MSO_Meta_Description::AJAX_NONCE_ACTION), // Use constant defined in main plugin file or Ajax class
            // Boolean flags indicating if API keys have been set (useful for enabling/disabling UI elements).
            'geminiApiKeySet' => !empty(get_option(MSO_Meta_Description::OPTION_PREFIX . 'gemini_api_key')),
            'mistralApiKeySet' => !empty(get_option(MSO_Meta_Description::OPTION_PREFIX . 'mistral_api_key')),
            'openaiApiKeySet' => !empty(get_option(MSO_Meta_Description::OPTION_PREFIX . 'openai_api_key')),
            'anthropicApiKeySet' => !empty(get_option(MSO_Meta_Description::OPTION_PREFIX . 'anthropic_api_key')),
            // Pass the currently selected models again (might be redundant if already passed above, but can be useful for specific JS logic).
            'selectedGeminiModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'gemini_model'),
            'selectedMistralModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'mistral_model'),
            'selectedOpenaiModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'openai_model'),
            'selectedAnthropicModel' => get_option(MSO_Meta_Description::OPTION_PREFIX . 'anthropic_model'),
        ];

        // Make the PHP variables available in JavaScript under the 'msoScriptVars' object.
        wp_localize_script('mso-admin-script', 'msoScriptVars', $script_vars);

        // Optional: Enqueue other specific admin CSS if needed.
        // wp_enqueue_style('mso-admin-style', plugin_dir_url(dirname(__FILE__)) . 'css/admin-style.css', [], MSO_Meta_Description::VERSION);
    }

    /**
     * Add a settings link to the plugin's action links on the Plugins page.
     *
     * This method is typically hooked into 'plugin_action_links_{plugin_basename}'.
     *
     * @param array $links An array of existing plugin action links (e.g., Activate, Deactivate, Edit).
     * @return array An updated array of plugin action links including the new Settings link.
     */
    public function add_settings_link(array $links): array
    {
        // Create the HTML for the settings link.
        $settings_link = sprintf(
        // Use admin_url() to generate the correct URL for the settings page.
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=' . Settings::PAGE_SLUG)), // Use constant for page slug
            // Localized text for the link.
            __('Settings', 'mso-meta-description')
        );
        // Add the new settings link to the beginning of the links array.
        array_unshift($links, $settings_link);
        // Return the modified links array.
        return $links;
    }
}