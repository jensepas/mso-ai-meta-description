<?php

/**
 * MSO AI Meta Description Ajax Handlers
 *
 * This class manages the AJAX endpoints for the MSO AI Meta Description plugin.
 * It handles requests for generating AI summaries and fetching available AI models.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.0.0
 */

namespace MSO_AI_Meta_Description;

use MSO_AI_Meta_Description\Api\ApiClient;

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
     */
    private ApiClient $api_client;

    /**
     * The nonce action string used for verifying AJAX requests.
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
        add_action('wp_ajax_mso_ai_generate_summary', [$this, 'handle_generate_summary']);
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
        if (! check_ajax_referer($this->nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'mso-ai-meta-description')], 403);
        }

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mso-ai-meta-description')], 403);
        }

        $content = isset($_POST['content']) ? sanitize_text_field(wp_unslash($_POST['content'])) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';

        if (empty($content)) {
            wp_send_json_error(['message' => __('Content cannot be empty.', 'mso-ai-meta-description')], 400);
        }

        if (empty($provider) || ! in_array($provider, $this->registered_providers)) {
            wp_send_json_error(['message' => __('Invalid AI provider specified.', 'mso-ai-meta-description')], 400);
        }

        $result = $this->api_client->generate_summary($provider, $content);

        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $status_code = 500;

            if (is_array($error_data) && isset($error_data['status'])) {
                $status_code = (int) $error_data['status'];

                if ($status_code < 400) {
                    $status_code = 500;
                }
            }

            wp_send_json_error(['message' => $result->get_error_message()], $status_code);
        } else {
            $summary = $result;

            wp_send_json_success(['summary' => $summary]);
        }
    }

    /**
     * AJAX handler for fetching available AI models for a specific provider.
     *
     * Expects 'apiType' (provider name) and 'nonce' in the POST request.
     * Returns a JSON response with an array of available models or an error message.
     */
    public function handle_fetch_models(): void
    {
        if (! check_ajax_referer($this->nonce_action, 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'mso-ai-meta-description')], 403);
        }

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'mso-ai-meta-description')], 403);
        }

        $api_type = isset($_POST['apiType']) ? sanitize_text_field(wp_unslash($_POST['apiType'])) : '';

        if (empty($api_type) || ! in_array($api_type, $this->registered_providers)) {
            wp_send_json_error(['message' => __('Invalid API type specified.', 'mso-ai-meta-description')], 400);
        }

        $result = $this->api_client->fetch_models($api_type);

        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $status_code = 500;

            if (is_array($error_data) && isset($error_data['status'])) {
                $status_code = (int) $error_data['status'];

                if ($status_code < 400) {
                    $status_code = 500;
                }
            }

            if ($result->get_error_code() === 'api_key_missing') {
                $status_code = 400;
            }

            wp_send_json_error(['message' => $result->get_error_message()], $status_code);
        } else {
            wp_send_json_success($result);
        }
    }
}
