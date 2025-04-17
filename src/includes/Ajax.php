<?php

/**
 * MSO AI Meta Description Ajax Handlers
 *
 * This class manages the AJAX endpoints for the MSO AI Meta Description plugin.
 * It handles requests for generating AI summaries and fetching available AI models.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description;

use MSO_AI_Meta_Description\Api\ApiClient;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    die;
}

/**
 * Handles AJAX requests for the plugin.
 */
class Ajax
{
    /**
     * Instance of the ApiClient used to interact with external AI APIs.
     * @var ApiClient
     */
    private ApiClient $api_client;

    /**
     * The nonce action string used for verifying AJAX requests.
     * @var string
     */
    private string $nonce_action;

    /**
     * The nonce action string used for verifying AJAX requests.
     * @var array<string>
     */
    private array $registered_providers;

    /**
     * Constructor.
     *
     * Injects the ApiClient dependency and the nonce action string.
     *
     * @param ApiClient $api_client   An instance of the ApiClient.
     * @param string    $nonce_action The nonce action name for security checks.
     * @param array<string> $registered_providers List all provider.
     */
    public function __construct(ApiClient $api_client, string $nonce_action, array $registered_providers)
    {
        $this->api_client = $api_client;
        $this->nonce_action = $nonce_action;
        $this->registered_providers = $registered_providers;
    }

    /**
     * Registers the WordPress AJAX hooks for the plugin's endpoints.
     *
     * Hooks the handler methods to the corresponding 'wp_ajax_{action}' actions.
     */
    public function register_hooks(): void
    {
        // Hook for generating meta description summaries.
        add_action('wp_ajax_mso_ai_generate_summary', [$this, 'handle_generate_summary']);
        // Hook for fetching available AI models for a given provider.
        add_action('wp_ajax_mso_ai_fetch_models', [$this, 'handle_fetch_models']);
    }

    /**
     * AJAX handler for generating a meta description summary via an AI provider.
     *
     * Expects 'content', 'provider', and 'nonce' in the POST request.
     * Returns a JSON response with the generated summary or an error message.
     */
    public function handle_generate_summary(): void
    {
        // 1. Verify the security nonce.
        // The third argument `false` prevents check_ajax_referer from dying automatically,
        // allowing us to send a custom JSON error response.
        if (! check_ajax_referer($this->nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'mso-ai-meta-description')], 403);
            // wp_send_json_error includes wp_die(), so no need for return/die after it.
        }

        // 2. Check if the current user has the capability to edit posts.
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mso-ai-meta-description')], 403);
        }

        // 3. Sanitize input data from the POST request.
        $content = isset($_POST['content']) ? sanitize_text_field(wp_unslash($_POST['content'])) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';

        // 4. Validate inputs.
        // Ensure content is not empty.
        if (empty($content)) {
            wp_send_json_error(['message' => __('Content cannot be empty.', 'mso-ai-meta-description')], 400); // 400 Bad Request
        }

        // Ensure a valid provider is specified using the constant from ApiClient.
        if (empty($provider) || ! in_array($provider, $this->registered_providers)) {
            wp_send_json_error(['message' => __('Invalid AI provider specified.', 'mso-ai-meta-description')], 400); // 400 Bad Request
        }

        // 5. Call the API client to generate the summary.
        $result = $this->api_client->generate_summary($provider, $content);

        // 6. Handle the result from the API client.
        if (is_wp_error($result)) {
            // If the API client returned a WP_Error object.
            $error_data = $result->get_error_data();
            $status_code = 500; // Default to Internal Server Error.
            // Check if the error data contains a specific HTTP status code from the API call.
            if (is_array($error_data) && isset($error_data['status'])) {
                $status_code = (int) $error_data['status'];
                // Ensure the status code is a valid client or server error code (4xx or 5xx).
                if ($status_code < 400) {
                    $status_code = 500;
                }
            }
            // Send a JSON error response with the message and status code.
            wp_send_json_error(['message' => $result->get_error_message()], $status_code);
        } else {
            // If the API call was successful.
            // The ApiClient::generate_summary method should return a clean, trimmed string.
            $summary = $result;
            // Send a JSON success response containing the generated summary.
            wp_send_json_success(['summary' => $summary]);
        }
        // Note: wp_send_json_success() and wp_send_json_error() both call wp_die() internally.
    }

    /**
     * AJAX handler for fetching available AI models for a specific provider.
     *
     * Expects 'apiType' (provider name) and 'nonce' in the POST request.
     * Returns a JSON response with an array of available models or an error message.
     */
    public function handle_fetch_models(): void
    {
        // 1. Verify the security nonce.
        if (! check_ajax_referer($this->nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'mso-ai-meta-description')], 403);
        }

        // 2. Check if the current user has the capability to manage options (access settings page).
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mso-ai-meta-description')], 403);
        }

        // 3. Sanitize input data from the POST request.
        $api_type = isset($_POST['apiType']) ? sanitize_text_field(wp_unslash($_POST['apiType'])) : '';

        // 4. Validate the API type (provider).
        // Ensure a valid provider is specified using the constant from ApiClient.
        if (empty($api_type) || ! in_array($api_type, $this->registered_providers)) {
            wp_send_json_error(['message' => __('Invalid API type specified.', 'mso-ai-meta-description')], 400); // 400 Bad Request
        }

        // 5. Call the API client to fetch the models for the specified provider.
        $result = $this->api_client->fetch_models($api_type);

        // 6. Handle the result from the API client.
        if (is_wp_error($result)) {
            // If the API client returned a WP_Error object.
            $error_data = $result->get_error_data();
            $status_code = 500; // Default to Internal Server Error.
            // Check if the error data contains a specific HTTP status code.
            if (is_array($error_data) && isset($error_data['status'])) {
                $status_code = (int) $error_data['status'];
                // Ensure the status code is a valid client or server error code.
                if ($status_code < 400) {
                    $status_code = 500;
                }
            }
            // Check for a specific error code indicating the API key is missing for this provider.
            // This helps provide clearer feedback to the user on the settings page.
            if ($result->get_error_code() === 'api_key_missing') {
                $status_code = 400; // Bad Request, as the key is required.
            }
            // Send a JSON error response.
            wp_send_json_error(['message' => $result->get_error_message()], $status_code);
        } else {
            // If the API call was successful.
            // The result is expected to be an array of model data (e.g., [{id: 'model-id', name: 'Model Name'}, ...]).
            wp_send_json_success($result);
        }
        // Note: wp_send_json_success() and wp_send_json_error() both call wp_die() internally.
    }
}
