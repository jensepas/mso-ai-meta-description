<?php
/**
 * MSO Meta Description Ajax
 *
 * @package MSO_Meta_Description
 * @since   1.2.0
 */
namespace MSO_Meta_Description;

use WP_Error;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    die;
}

class Ajax
{
    private ApiClient $api_client;
    private string $nonce_action;

    public function __construct(ApiClient $api_client, string $nonce_action)
    {
        $this->api_client = $api_client;
        $this->nonce_action = $nonce_action;
    }

    public function register_hooks(): void
    {
        add_action('wp_ajax_mso_generate_summary', [$this, 'handle_generate_summary']);
        add_action('wp_ajax_mso_fetch_models', [$this, 'handle_fetch_models']);
        // Ajoutez les versions non connectées si nécessaire : add_action('wp_ajax_nopriv_...')
    }

    /**
     * AJAX handler for generating summary via AI.
     */
    public function handle_generate_summary(): void
    {
        // Check nonce
        if (!check_ajax_referer($this->nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'mso-meta-description')], 403);
            // No need for return after wp_send_json_error + wp_die()
        }

        // Check capability
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mso-meta-description')], 403);
        }

        // Sanitize inputs
        $content = isset($_POST['content']) ? sanitize_text_field( wp_unslash($_POST['content'])) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field( wp_unslash($_POST['provider'])) : '';

        // Validate inputs
        if (empty($content)) {
            wp_send_json_error(['message' => __('Content cannot be empty.', 'mso-meta-description')], 400);
        }

        // *** MODIFICATION ICI ***
        // Include 'openai' in the list of valid providers
        if (empty($provider) || !in_array($provider, ApiClient::SUPPORTED_PROVIDERS)) { // Use the constant from ApiClient
            wp_send_json_error(['message' => __('Invalid AI provider specified.', 'mso-meta-description')], 400);
        }

        // Call the API client
        $result = $this->api_client->generate_summary($provider, $content);

        // Handle the result
        if (is_wp_error($result)) {
            // Try to get a more specific error code if available from API client
            $error_data = $result->get_error_data();
            $status_code = 500; // Default internal server error
            if (is_array($error_data) && isset($error_data['status'])) {
                // Use status code from API if available (e.g., 400 for bad request, 401 for auth error)
                $status_code = (int) $error_data['status'];
                // Ensure status code is in valid client/server error range
                if ($status_code < 400) $status_code = 500;
            }
            wp_send_json_error(['message' => $result->get_error_message()], $status_code);
        } else {
            // Ensure summary is plain text and trimmed (already done in ApiClient now)
            // $summary = wp_strip_all_tags($result);
            // $summary = trim($summary);
            $summary = $result; // ApiClient::generate_summary should now return a clean string

            wp_send_json_success(['summary' => $summary]);
        }
        // wp_die(); // wp_send_json_success/error already include wp_die()
    }

    /**
     * AJAX handler for fetching available AI models.
     */
    public function handle_fetch_models(): void
    {
        // Check nonce
        if (!check_ajax_referer($this->nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'mso-meta-description')], 403);
        }

        // Check capability (Settings page access)
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mso-meta-description')], 403);
        }

        // Sanitize input
        $api_type = isset($_POST['apiType']) ? sanitize_text_field( wp_unslash($_POST['apiType'])) : '';

        // *** MODIFICATION ICI ***
        // Include 'openai' in the list of valid API types (providers)
        if (empty($api_type) || !in_array($api_type, ApiClient::SUPPORTED_PROVIDERS)) { // Use the constant from ApiClient
            wp_send_json_error(['message' => __('Invalid API type specified.', 'mso-meta-description')], 400);
        }

        // Call the API client
        $result = $this->api_client->fetch_models($api_type);

        // Handle the result
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $status_code = 500; // Default
            if (is_array($error_data) && isset($error_data['status'])) {
                $status_code = (int) $error_data['status'];
                if ($status_code < 400) $status_code = 500;
            }
            // Check for specific error code indicating missing API key
            if ($result->get_error_code() === 'api_key_missing') {
                $status_code = 400; // Bad request, as key is needed client-side too
            }
            wp_send_json_error(['message' => $result->get_error_message()], $status_code);
        } else {
            // Result is expected to be an array of model objects from ApiClient
            wp_send_json_success($result);
        }
        // wp_die(); // wp_send_json_success/error already include wp_die()
    }
}