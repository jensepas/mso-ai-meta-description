<?php

/**
 * Plugin Name: MSO AI Meta Description: Custom Meta Descriptions with AI
 * Description: WordPress plugin to add custom meta description tags in the HTML header, with the option to generate by AI.
 * Author: ms-only
 * Version: 1.4.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author URI: https://www.ms-only.fr/
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: mso-ai-meta-description
 *
 * @package MSO_AI_Meta_Description
 */

namespace MSO_AI_Meta_Description;

if (! defined('ABSPATH')) {
    die;
}

use MSO_AI_Meta_Description\Api\ApiClient;
use MSO_AI_Meta_Description\Providers\ProviderManager;

$autoloader = plugin_dir_path(__FILE__) . 'includes/autoload.php';
if (! file_exists($autoloader)) {
    return;
}

require_once $autoloader;

$provider_interface = plugin_dir_path(__FILE__) . 'includes/Providers/ProviderInterface.php';
if (file_exists($provider_interface)) {
    require_once $provider_interface;
}

/**
 * Main plugin class (MSO_AI_Meta_Description).
 *
 * Initializes and coordinates different plugin components like Admin, Frontend, AJAX, Settings, etc.
 * Implements the Singleton pattern to ensure only one instance exists.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */
final class MSO_AI_Meta_Description
{
    /** Plugin version number. Used for cache busting scripts/styles. */
    public const string VERSION = '1.4.0';

    /** Text domain for localization (internationalization). Must match plugin header and .pot file. */
    public const string TEXT_DOMAIN = 'mso-ai-meta-description';

    /** The meta key used to store the custom meta description in the post meta table. */
    public const string META_KEY = '_mso_ai_meta_description';

    /** Nonce action string used for verifying the meta box save request. */
    public const string META_BOX_NONCE_ACTION = 'mso_ai_save_meta_description_nonce_action';
    /** Nonce field name used in the meta box form. */
    public const string META_BOX_NONCE_NAME = 'mso_ai_save_meta_description_nonce_field';

    /**
     * Nonce action/name for general AJAX requests within the plugin.
     * Using 'wp_rest' might be okay if interacting with REST API, but a custom nonce is often better for non-REST AJAX.
     * Consider changing to something like 'mso_ai_ajax_nonce'.
     */
    public const string AJAX_NONCE_ACTION = 'mso_ai_meta_description_ajax_actions';

    /** Prefix used for all plugin options stored in the wp_options table. Helps avoid naming conflicts. */
    public const string OPTION_PREFIX = 'mso_ai_meta_description_';

    /** Minimum recommended length for a meta description. */
    public const int MIN_DESCRIPTION_LENGTH = 120;
    /** Maximum recommended length for a meta description. */
    public const int MAX_DESCRIPTION_LENGTH = 160;

    /**
     * The default prompt template used for generating summaries.
     * Placeholders: %1$d (min length), %2$d (max length), %3$s (content).
     * @var string
     */
    public const string DEFAULT_SUMMARY_PROMPT_TEMPLATE = 'Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Output only the description text itself, without any introductory phrases like "Here is the summary:": %3$s';

    /** Holds the single instance of this class (Singleton pattern). */
    private static ?MSO_AI_Meta_Description $instance = null;

    /** Instance of the Admin class, handling admin-specific functionality. */
    private Admin $admin;

    /** Instance of the Frontend class, handling frontend output (meta tag). */
    private Frontend $frontend;

    /** Instance of the Ajax class, handling AJAX requests. */
    private Ajax $ajax;

    /**
     * Private constructor to prevent direct instantiation (Singleton pattern).
     * Use `get_instance()` to get the object.
     */
    private function __construct()
    {

    }

    /**
     * Get the singleton instance of the plugin.
     *
     * Creates the instance on the first call and runs the setup method.
     * Subsequent calls return the existing instance.
     *
     * @return MSO_AI_Meta_Description The single instance of the main plugin class.
     */
    public static function get_instance(): MSO_AI_Meta_Description
    {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup();
        }

        return self::$instance;
    }

    /**
     * Set up the plugin: load dependencies, instantiate components, register hooks.
     * This method is called only once when the singleton instance is created.
     */
    private function setup(): void
    {
        $this->load_dependencies();
        $this->instantiate_components();
        $this->register_hooks();
    }

    /**
     * Load required dependencies if any (beyond the autoloader).
     * Placeholder for loading function files, external libraries, etc.
     */
    private function load_dependencies(): void
    {

    }

    /**
     * Instantiate plugin components (classes).
     * Creates objects for handling different parts of the plugin's functionality.
     */
    private function instantiate_components(): void
    {
        ProviderManager::register_providers_from_directory();
        $providers = ProviderManager::get_providers();
        $registered_providers = ProviderManager::get_provider_names();
        $api_client = new ApiClient();
        $settings = new Settings($providers);
        $meta_box = new MetaBox(self::META_KEY, self::META_BOX_NONCE_ACTION, self::META_BOX_NONCE_NAME, $providers);
        $this->admin = new Admin($settings, $meta_box, $providers);
        $this->frontend = new Frontend(self::META_KEY);
        $this->ajax = new Ajax($api_client, self::AJAX_NONCE_ACTION, $registered_providers);
    }

    /**
     * Register WordPress action and filter hooks for various components.
     * Connects the plugin's methods to WordPress events.
     */
    private function register_hooks(): void
    {
        add_action('plugins_loaded', [$this, 'load_plugin_text_domain']);

        $this->frontend->register_hooks();
        $this->ajax->register_hooks();

        if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
            $this->admin->register_hooks();

            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this->admin, 'add_settings_link']);
        }
    }

    /**
     * Static getter for the plugin's option prefix.
     * Provides a consistent way to access the option prefix constant.
     * @return string The option prefix ('mso_ai_meta_description_').
     */
    public static function get_option_prefix(): string
    {
        return self::OPTION_PREFIX;
    }

    /**
     * Load the plugin's text domain for localization (translation).
     * Allows the plugin's strings to be translated using .mo files.
     */
    public function load_plugin_text_domain(): void
    {
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Prevent deserialization of the singleton instance.
     */
    public function __wakeup()
    {

    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone()
    {

    }
}

/**
 * Begins execution of the plugin.
 *
 * This function simply retrieves the singleton instance of the main plugin class.
 * The `get_instance()` method handles the actual setup and hook registration
 * if it's the first time it's being called.
 */
function mso_ai_meta_description_run(): void
{
    MSO_AI_Meta_Description::get_instance();
}

mso_ai_meta_description_run();
