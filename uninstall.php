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

// Check if the installation is multisite
if (is_multisite()) {
    // Get all blog IDs from the network
    $site_ids = get_sites(['fields' => 'ids']);

    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        mso_meta_description_uninstall_site();
        restore_current_blog();
    }
} else {
    // Single site uninstall
    mso_meta_description_uninstall_site();
}

// Clear any WordPress cache that might hold plugin data
wp_cache_flush();
