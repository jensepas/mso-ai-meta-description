<?php
/**
 * MSO Meta Description Uninstall
 *
 * Actions performed when the plugin is deleted via the WordPress admin interface.
 *
 * @package MSO_Meta_Description
 * @since   1.2.0
 */

// Exit if uninstall.php is not called by WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/**
 * Handles the removal of plugin data for a single WordPress site.
 *
 * Deletes plugin options, post meta associated with MSO Meta Description,
 * and any custom database tables created by the plugin.
 *
 * @return void
 */
function mso_meta_description_uninstall_site(): void
{
// Define constants based on the main plugin class.
// It's generally safer to hardcode these in uninstall.php as the main plugin file might not be loaded.
    $option_prefix = 'mso_meta_description_';
    $meta_key      = '_mso_meta_description'; // The meta key used for posts/pages

// --- Delete Plugin Options ---

// List of options specific to this plugin stored in the wp_options table.
    $options_to_delete = [
        $option_prefix . 'mistral_api_key',
        $option_prefix . 'gemini_api_key',
        $option_prefix . 'openai_api_key',
        $option_prefix . 'mistral_model',
        $option_prefix . 'gemini_model',
        $option_prefix . 'openai_model',
        $option_prefix . 'front_page', // Option added to 'reading' group but stored in wp_options
        // Add any other option names your plugin might create here.
    ];

// Loop through the options and delete them.
    foreach ($options_to_delete as $option_name) {
        delete_option($option_name);
    }

// --- Delete Post Meta ---

// Delete all instances of the custom meta key associated with this plugin from the wp_postmeta table.
// This is more efficient than looping through all posts.
// Requires WordPress 3.4+ (Plugin requires 6.0+, so this is safe).
    delete_post_meta_by_key($meta_key);
}

// Uninstall for the current site.
mso_meta_description_uninstall_site();

// Uninstall for multisite installations.
if (is_multisite()) {
    // WordPress 4.6 and later using WP_Site_Query.
    if (function_exists('get_sites') && class_exists('WP_Site_Query')) {
        $sites = get_sites();
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            mso_meta_description_uninstall_site();
            restore_current_blog();
        }
    } else {
        // WordPress versions before 4.6 using wp_get_sites.
        if (function_exists('wp_get_sites')) {
            $sites = wp_get_sites();
            foreach ($sites as $site) {
                switch_to_blog($site['blog_id']);
                mso_meta_description_uninstall_site();
                restore_current_blog();
            }
        }
    }
}
// Global uninstall actions that run only once for the entire network
if (is_multisite() && function_exists('add_action')) {
    /**
     * IMPORTANT: If your plugin creates network-wide settings or tables,
     * you should handle their removal in this action.
     *
     * Example for dropping a network-wide table:
     * function mso_meta_description_uninstall_network() {
     * global $wpdb;
     * $network_table_name = $wpdb->prefix . 'mso_meta_description_network_data';
     * $wpdb->query("DROP TABLE IF EXISTS {$network_table_name}");
     * }
     * add_action('network_uninstall_'.plugin_basename(__FILE__), 'mso_meta_description_uninstall_network');
     */
    do_action('mso_meta_description_uninstall_network');
}