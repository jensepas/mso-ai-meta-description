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

// Exit if accessed directly to prevent direct execution of the script.
if (! defined('ABSPATH')) {
    die;
}

// Use statements for classes used within this file's scope (mainly for type hinting and instantiation).
// These help with code clarity and prevent potential naming conflicts.
use MSO_AI_Meta_Description\Api\ApiClient;
use MSO_AI_Meta_Description\Providers\ProviderManager;

// Define the path to the autoloader file.
$autoloader = plugin_dir_path(__FILE__) . 'includes/autoload.php';
// Check if the autoloader file exists. If not, the plugin cannot function.
if (! file_exists($autoloader)) {
    return; // Stop plugin execution if the autoloader is missing.
}
// Include the autoloader to handle automatic class loading.
require_once $autoloader;

// Explicitly include the ProviderInterface.
$provider_interface = plugin_dir_path(__FILE__) . 'includes/Providers/ProviderInterface.php';
if (file_exists($provider_interface)) {
    require_once $provider_interface;
}


// --- Main Plugin Class Definition ---

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
        // Constructor is intentionally kept private.
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
        // Check if the instance hasn't been created yet.
        if (null === self::$instance) {
            // Create the single instance.
            self::$instance = new self();
            // Run the setup process (load dependencies, instantiate components, register hooks).
            self::$instance->setup();
        }

        // Return the existing or newly created instance.
        return self::$instance;
    }

    /**
     * Set up the plugin: load dependencies, instantiate components, register hooks.
     * This method is called only once when the singleton instance is created.
     */
    private function setup(): void
    {
        // Note: Provider registration is now handled separately via the 'plugins_loaded' hook
        // in the `mso_ai_init_dynamic_providers` function below. This ensures providers are
        // registered early, before other components that might depend on them are instantiated.

        // Load any necessary files or libraries not handled by the autoloader.
        $this->load_dependencies();
        // Create instances of all the core plugin component classes.
        $this->instantiate_components();
        // Register WordPress action and filter hooks for the instantiated components.
        $this->register_hooks();
    }

    /**
     * Load required dependencies if any (beyond the autoloader).
     * Placeholder for loading function files, external libraries, etc.
     */
    private function load_dependencies(): void
    {
        // Currently, the autoloader handles class loading.
        // Add any other necessary `require_once` calls here if needed.
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

        // Instantiate the API client (facade for AI providers).
        $api_client = new ApiClient();
        // Instantiate the Settings manager (might be needed for model fetching/validation).
        $settings = new Settings($providers);
        // Instantiate the MetaBox handler, passing necessary keys and nonce's.
        $meta_box = new MetaBox(self::META_KEY, self::META_BOX_NONCE_ACTION, self::META_BOX_NONCE_NAME, $providers);
        // Instantiate the Admin handler, passing Settings and MetaBox instances.
        $this->admin = new Admin($settings, $meta_box, $providers);
        // Instantiate the Frontend handler, passing the meta key.
        $this->frontend = new Frontend(self::META_KEY);
        // Instantiate the AJAX handler, passing the API client and nonce identifier.
        $this->ajax = new Ajax($api_client, self::AJAX_NONCE_ACTION, $registered_providers);
    }

    /**
     * Register WordPress action and filter hooks for various components.
     * Connects the plugin's methods to WordPress events.
     */
    private function register_hooks(): void
    {
        // Hook the method to load the plugin's text domain for localization.
        add_action('plugins_loaded', [$this, 'load_plugin_text_domain']);

        // Register hooks defined within the Frontend and Ajax classes.
        $this->frontend->register_hooks();
        $this->ajax->register_hooks();

        // Only register admin-specific hooks if we are in the admin area or running WP-CLI.
        if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
            // Register hooks defined within the Admin class (e.g., menu pages, settings registration, meta boxes).
            $this->admin->register_hooks();
            // Add the "Settings" link to the plugin's entry on the WordPress Plugins page.
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
        // Deserialization is forbidden to maintain the singleton pattern.
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone()
    {
        // Cloning is forbidden to maintain the singleton pattern.
    }
}


// --- Plugin Execution ---

/**
 * Begins execution of the plugin.
 *
 * This function simply retrieves the singleton instance of the main plugin class.
 * The `get_instance()` method handles the actual setup and hook registration
 * if it's the first time it's being called.
 */
function mso_ai_meta_description_run(): void
{
    // Get the singleton instance. This triggers the setup process within the class.
    // Use the fully qualified class name because this function is in the global scope.
    MSO_AI_Meta_Description::get_instance();
}

// Call the function to start the plugin.
mso_ai_meta_description_run();
