<?php
/**
 * Plugin Name: MSO Meta Description: Custom Meta Descriptions with AI
 * Description: Plugin WordPress pour ajouter des balises méta description personnalisées dans l'entête HTML, avec l'option de génération par IA (Gemini ou Mistral).
 * Author: ms-only
 * Version: 1.3.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author URI: https://www.ms-only.fr/
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: mso-meta-description
 */

namespace MSO_Meta_Description;

// Exit if accessed directly.
use MSO_Meta_Description\Api\ApiClient;

if (!defined('ABSPATH')) {
    die;
}

// Ensure autoloader is present
$autoloader = plugin_dir_path(__FILE__) . 'includes/autoload.php';
if (!file_exists($autoloader)) {
    // Optionally display an admin notice or log an error
    // error_log('MSO Meta Description: Autoloader not found.');
    return; // Stop execution if autoloader is missing
}
require_once $autoloader;

/**
 * Main plugin class. Initializes and coordinates different plugin components.
 */
class MSO_Meta_Description
{
    /** Plugin version. */
    const VERSION = '1.1.0';

    /** Text domain for localization. */
    const TEXT_DOMAIN = 'mso-meta-description';

    /** Meta key for storing the description. */
    const META_KEY = '_mso_meta_description';

    /** Nonce action for meta box saving. */
    const META_BOX_NONCE_ACTION = 'mso_save_meta_description_nonce_action';
    /** Nonce name for meta box saving. */
    const META_BOX_NONCE_NAME = 'mso_save_meta_description_nonce_field';

    /** Nonce action/name for AJAX requests. Using wp_rest nonce for simplicity here, but a custom one might be better. */
    const AJAX_NONCE = 'wp_rest'; // Matches original code

    /** Option name prefix for settings */
    const OPTION_PREFIX = 'mso_meta_description_';

    /** Minimum recommended description length. */
    const MIN_DESCRIPTION_LENGTH = 120;
    /** Maximum recommended description length. */
    const MAX_DESCRIPTION_LENGTH = 160;

    /** Instance of the plugin. */
    private static ?MSO_Meta_Description $instance = null;

    /** Admin handler instance. */
    private Admin $admin;

    /** Frontend handler instance. */
    private Frontend $frontend;

    /** AJAX handler instance. */
    private Ajax $ajax;

    /** Settings handler instance */
    private Settings $settings;

    /** API Client instance */
    private ApiClient $api_client;

    /** Meta Box handler instance */
    private MetaBox $meta_box;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Get the singleton instance of the plugin.
     *
     * @return MSO_Meta_Description
     */
    public static function get_instance(): MSO_Meta_Description
    {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup();
        }
        return self::$instance;
    }

    /**
     * Setup the plugin components.
     */
    private function setup(): void
    {
        $this->load_dependencies();
        $this->instantiate_components();
        $this->register_hooks();
    }

    /**
     * Load required dependencies. (Placeholder if needed beyond autoloader)
     */
    private function load_dependencies(): void
    {
        // Autoloader handles class loading
        // Load other necessary files if any
    }

    /**
     * Instantiate plugin components.
     */
    private function instantiate_components(): void
    {
        $this->api_client = new ApiClient();
        $this->settings = new Settings($this->api_client); // Pass API client if needed for validation/fetching defaults
        $this->meta_box = new MetaBox(self::META_KEY, self::META_BOX_NONCE_ACTION, self::META_BOX_NONCE_NAME);
        $this->admin = new Admin($this->settings, $this->meta_box);
        $this->frontend = new Frontend(self::META_KEY);
        $this->ajax = new Ajax($this->api_client, self::AJAX_NONCE); // Pass API client
    }

    /**
     * Register WordPress hooks for various components.
     */
    private function register_hooks(): void
    {
        add_action('plugins_loaded', [$this, 'load_plugin_text_domain']);

        $this->frontend->register_hooks();
        $this->ajax->register_hooks(); // AJAX hooks need to be registered for both logged-in and non-logged-in if applicable

        if (is_admin()) {
            $this->admin->register_hooks();
            // Settings link hook depends on plugin basename
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this->admin, 'add_settings_link']);
        }
    }

    /**
     * Get plugin meta key.
     */
    public static function get_meta_key(): string
    {
        return self::META_KEY;
    }

    /**
     * Get plugin option name prefix.
     */
    public static function get_option_prefix(): string
    {
        return self::OPTION_PREFIX;
    }

    /**
     * Get text domain.
     */
    public static function get_text_domain(): string
    {
        return self::TEXT_DOMAIN;
    }

    /**
     * Load plugin text domain for localization.
     */
    public function load_plugin_text_domain(): void
    {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * executing the plugin function is sufficient.
 */
function mso_meta_description_run(): void
{
    MSO_Meta_Description::get_instance();
}

mso_meta_description_run();