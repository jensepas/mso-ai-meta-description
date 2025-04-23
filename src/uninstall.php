<?php

/**
 * MSO AI Meta Description Uninstall
 *
 * Actions performed when the plugin is deleted via the WordPress admin interface.
 * This script runs *only* when the user clicks "Delete" for the plugin
 * from the "Plugins" page. It does *not* run on deactivation.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.0.0
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/**
 * Handles the removal of plugin data for a single WordPress site.
 *
 * Deletes plugin options, post meta associated with MSO AI Meta Description,
 * and any custom database tables created by the plugin (if applicable).
 * This function is designed to be called for each site in a multisite network
 * or just once for a single site installation.
 */
function mso_ai_meta_description_uninstall_site(): void
{
    $option_prefix = 'mso_ai_meta_description_';
    $meta_key = '_mso_ai_meta_description';
    $all_options = wp_load_alloptions();
    $options_to_delete = [];

    foreach ($all_options as $key => $value) {
        if (str_contains($key, $option_prefix)) {
            $options_to_delete[] = $key;
        }
    }

    if (! empty($options_to_delete)) {
        foreach ($options_to_delete as $option_name) {
            delete_option($option_name);
        }

    }

    delete_post_meta_by_key($meta_key);
}

if (is_multisite()) {
    $site_ids = get_sites(['fields' => 'ids']);
    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        mso_ai_meta_description_uninstall_site();
        restore_current_blog();
    }
} else {
    mso_ai_meta_description_uninstall_site();
}

wp_cache_flush();
