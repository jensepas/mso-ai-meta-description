<?php

/**
 * MSO AI Meta Description Uninstall
 *
 * Actions performed when the plugin is deleted via the WordPress admin interface.
 * This script runs *only* when the user clicks "Delete" for the plugin
 * from the "Plugins" page. It does *not* run on deactivation.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.3.0
 */

// Exit if uninstall.php is not called by WordPress.
// WP_UNINSTALL_PLUGIN is defined by WordPress only when uninstalling a plugin.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    die; // Protects the script from direct access.
}

/**
 * Handles the removal of plugin data for a single WordPress site.
 *
 * Deletes plugin options, post meta associated with MSO AI Meta Description,
 * and any custom database tables created by the plugin (if applicable).
 * This function is designed to be called for each site in a multisite network
 * or just once for a single site installation.
 *
 * @return void
 */
function mso_ai_meta_description_uninstall_site(): void
{
    // Define constants based on the main plugin class or configuration.
    // It's generally safer to hardcode these critical keys in uninstall.php
    // because the main plugin files are not loaded during uninstallation.
    $option_prefix = 'mso_ai_meta_description_'; // Prefix used for plugin options.
    $meta_key = '_mso_ai_meta_description'; // The meta key used for storing descriptions on posts/pages.

    // --- Delete Plugin Options ---

    // List of all options specific to this plugin stored in the wp_options table.
    // Ensure this list is comprehensive and includes all options created by the plugin.
    $options_to_delete = [
        $option_prefix . 'mistral_api_key',
        $option_prefix . 'gemini_api_key',
        $option_prefix . 'openai_api_key',
        $option_prefix . 'openai_api_key',
        $option_prefix . 'anthropic_api_key',
        $option_prefix . 'gemini_model',
        $option_prefix . 'openai_model',
        $option_prefix . 'anthropic_model',
        $option_prefix . 'front_page', // Option added to 'reading' group but stored in wp_options.
        // Add any other option names your plugin might create here.
        // Example: $option_prefix . 'some_other_setting',
    ];

    // Loop through the defined options and delete each one from the wp_options table.
    foreach ($options_to_delete as $option_name) {
        delete_option($option_name); // WordPress function to remove an option.
    }

    // --- Delete Post Meta ---

    // Delete all instances of the custom meta key associated with this plugin
    // from the wp_postmeta table across all posts and pages.
    // This is more efficient than querying all posts and deleting meta individually.
    // Requires WordPress 3.4+ (Plugin requires 6.0+, so this is safe).
    delete_post_meta_by_key($meta_key);
}

// --- Handle Multisite ---

// Check if the current WordPress installation is a multisite network.
if (is_multisite()) {
    // Get all site (blog) IDs within the network.
    // 'fields' => 'ids' returns an array of IDs directly.
    $site_ids = get_sites(['fields' => 'ids']); // Ensure we get active sites

    // Iterate through each site ID in the network.
    foreach ($site_ids as $site_id) {
        // Temporarily switch the context to the specific site.
        // This makes functions like delete_option() and delete_post_meta_by_key()
        // operate on the tables for that specific site (e.g., wp_2_options, wp_2_postmeta).
        switch_to_blog($site_id);

        // Call the site-specific uninstall function to clean up data for this site.
        mso_ai_meta_description_uninstall_site();

        // Restore the context back to the original site (usually the main site).
        // This is crucial to avoid issues if the loop continues or other code runs after this.
        restore_current_blog();
    }
} else {
    // If it's not a multisite installation, just run the uninstall function once for the single site.
    mso_ai_meta_description_uninstall_site();
}

// --- Final Cleanup ---

// Clear any WordPress object cache that might hold plugin data.
// This helps ensure that stale data isn't accidentally retrieved after uninstallation.
wp_cache_flush();
