<?php

/**
 * MSO AI Meta Description AbstractProvider
 *
 * Provides common functionality for AI provider implementations.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description\Providers;

use MSO_AI_Meta_Description\MSO_AI_Meta_Description;
use MSO_AI_Meta_Description\Utils\Logger;
use WP_Error;

abstract class AbstractProvider implements ProviderInterface
{
    /**
     * Stores the API key retrieved from settings.
     * @var string Null if not yet fetched, false if empty/not set.
     */
    protected string $api_key;

    /**
     * Stores the selected model retrieved from settings.
     * @var string
     */
    protected mixed $model;

    /**
     * Constructor. Retrieves API key and model.
     */
    public function __construct()
    {
        $prefix = MSO_AI_Meta_Description::get_option_prefix();
        // Retrieve API key, store false if empty to distinguish from not-yet-fetched (null)
        $this->api_key = (string)get_option($prefix . $this->get_name() . '_api_key', '');
        // Retrieve model, using the provider-specific default
        $this->model = (string)get_option($prefix . $this->get_name() . '_model', $this->get_default_model());
    }

    /**
     * Checks if the API key is set and valid.
     * Returns a WP_Error if the key is missing.
     *
     * @return true|WP_Error True if key is valid, WP_Error otherwise.
     */
    protected function check_api_key(): bool|WP_Error
    {
        if (empty($this->api_key)) {
            return new WP_Error(
                'api_key_missing',
                sprintf(
                    /* translators: %s: Provider name (e.g., Mistral) */
                    __('API key for %s is not set.', 'mso-ai-meta-description'),
                    ucfirst($this->get_name()) // Use get_name() for dynamic message
                )
            );
        }

        return true;
    }

    /**
     * Makes an HTTP request to the provider's API.
     * Handles common logic like headers, timeout, WP_Error check, status code check, and JSON decoding.
     *
     * @param string               $endpoint The API endpoint (relative to base URL).
     * @param array<string, mixed> $args     Arguments for wp_remote_get/post (merged with defaults).
     * @param string               $method   HTTP method ('GET' or 'POST').
     * @return array<string, mixed>|WP_Error Decoded JSON data on success, WP_Error on failure.
     */
    protected function request(string $endpoint, array $args = [], string $method = 'GET'): array|WP_Error
    {
        $key_check = $this->check_api_key();
        if (is_wp_error($key_check)) {
            return $key_check;
        }

        $url = $this->get_api_base() . $endpoint;

        // Prepare default headers - allow specific providers to override/add
        $default_headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key, // Common for Mistral/OpenAI
        ];
        // Allow specific providers (like Gemini) to modify headers if needed
        $headers = $this->prepare_headers($default_headers);

        $default_args = [
            'timeout' => 15, // Default timeout for non-generating requests
            'headers' => $headers,
        ];

        // Merge provided args with defaults. array_replace_recursive handles nested arrays like headers well.
        $request_args = array_replace_recursive($default_args, $args);
        // Ensure timeout from $args overrides default if present (array_replace_recursive handles this)
        // Ensure body is JSON encoded if present and method is POST
        if (strtoupper($method) === 'POST' && isset($request_args['body']) && is_array($request_args['body'])) {
            $request_args['body'] = wp_json_encode($request_args['body']);
            // Check for JSON encoding errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('json_encode_error', __('Failed to encode request body.', 'mso-ai-meta-description'), ['error' => json_last_error_msg()]);
            }
        }

        // Add API key for Gemini requests if needed (handled in prepare_headers)
        if ($this->get_name() === 'gemini') {
            $url = add_query_arg('key', $this->api_key, $url);
        }

        // Make the request
        if (strtoupper($method) === 'POST') {
            $response = wp_remote_post($url, $request_args);
        } else {
            $response = wp_remote_get($url, $request_args);
        }

        // --- Common Error Handling ---
        if (is_wp_error($response)) {
            Logger::error('WP HTTP API Error', ['url' => $url, 'error_code' => $response->get_error_code(), 'error_message' => $response->get_error_message()]);

            return $response; // WordPress HTTP API error
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true); // Decode as associative array

        // Handle non-200 responses
        if ($http_code < 200 || $http_code >= 300) { // Check for any non-2xx status
            $error_message = $this->extract_error_message($response) !== ''
                ? $this->extract_error_message($response)
                : __('Unknown API error occurred.', 'mso-ai-meta-description');

            // Use the centralized logger
            Logger::error(
                sprintf('%s API Error (%s)', ucfirst($this->get_name()), $endpoint), // Main message
                [ // Context array
                    'url' => $url,
                    'status' => $http_code,
                    'message' => $error_message,
                    'response_body' => $body, // Log the raw body for debugging
                ]
            );

            return new WP_Error(
                'api_error',
                sprintf(
                    /* translators: 1: Provider name, 2: HTTP status code, 3: Error message */
                    __('%1$s API Error (%2$d): %3$s', 'mso-ai-meta-description'),
                    ucfirst($this->get_name()),
                    $http_code,
                    $error_message
                ),
                ['status' => $http_code, 'response_body' => $body] // Include body in WP_Error data
            );
        }

        // Handle JSON decoding errors specifically for successful HTTP status codes
        // (Errors for non-200 codes are handled above)
        if ($data === '' && json_last_error() !== JSON_ERROR_NONE) {
            // Use the centralized logger
            Logger::error(
                sprintf('%s API JSON Decode Error (%s)', ucfirst($this->get_name()), $endpoint), // Main message
                ['url' => $url, 'status' => $http_code, 'response_body' => $body, 'json_error' => json_last_error_msg()] // Context array
            );

            return new WP_Error('json_decode_error', __('Failed to decode API response.', 'mso-ai-meta-description'), ['status' => $http_code, 'response_body' => $body]);
        }

        // Ensure $data is an array before returning, even if the API returns valid JSON that isn't an array (e.g., "true")
        if (! is_array($data)) {
            Logger::error(
                sprintf('%s API Response Not An Array (%s)', ucfirst($this->get_name()), $endpoint),
                ['url' => $url, 'status' => $http_code, 'response_body' => $body]
            );

            // Decide how to handle this: return an error or an empty array?
            // Returning an empty array might be safer for subsequent code expecting an array.
            return [];
        }

        // Return the decoded data on success
        return $data;
    }

    /**
     * Builds the standard prompt for summary generation.
     *
     * @param string $content The content to summarize.
     * @return string The formatted prompt.
     */
    protected function build_summary_prompt(string $content): string
    {
        return sprintf(
            /* translators: 1: Minimum length, 2: Maximum length, 3: The content to summarize */
            __('Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Output only the description text itself, without any introductory phrases like "Here is the summary:": %3$s', 'mso-ai-meta-description'),
            MSO_AI_Meta_Description::MIN_DESCRIPTION_LENGTH,
            MSO_AI_Meta_Description::MAX_DESCRIPTION_LENGTH,
            $content
        );
    }

    /**
     * Allows providers to modify headers if needed (e.g., Gemini uses API key in URL for GET).
     *
     * @param array<string, string> $headers Default headers from AbstractProvider.
     * @return array<string, string> Modified headers for Anthropic API.I.
     */
    protected function prepare_headers(array $headers): array
    {
        // Example: Gemini doesn't use Bearer token, uses key in URL for GET
        if ($this->get_name() === 'gemini') {
            unset($headers['Authorization']);
        }

        return $headers;
    }

    /**
     * Get the base URL for the provider's API.
     * Example: 'https://api.mistral.ai/v1/'
     *
     * @return string
     */
    abstract protected function get_api_base(): string;

    /**
     * Get the default model ID for this provider.
     * Example: 'mistral-small-latest'
     *
     * @return string
     */
    abstract public function get_default_model(): string;

    /**
     * Extracts the error message from the provider's specific error response structure.
     *
     * The structure of $data can vary depending on the API and the error.
     * Concrete implementations should safely check the expected structure.
     *
     * @param array<string, mixed> $data The decoded JSON error response, or null if decoding failed.
     * @return string The extracted error message or null if not found.
     */
    abstract protected function extract_error_message(array $data): string;

    /**
     * Parses the successful response from the 'fetch_models' API call.
     *
     * @param array<string, mixed> $data Decoded JSON response data.
     * @return array<int, array<string, string>>|WP_Error Formatted array of models or WP_Error on parsing failure.
     */
    abstract protected function parse_model_list(array $data): array|WP_Error;

    /**
     * Parses the successful response from the 'generate_summary' API call.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the chat completions endpoint.
     * @return string|WP_Error The extracted summary text on success, or a WP_Error if parsing fails.
     */
    abstract protected function parse_summary(array $data): string|WP_Error;

    /**
     * Builds the request body specific to this provider for summary generation.
     *
     * @param string $prompt The user-provided text to generate a summary from.
     * @return array<string, mixed> The request body as an associative array, ready for JSON encoding.
     */
    abstract protected function build_summary_request_body(string $prompt): array;

    /**
     * Fetches models by calling the shared request method and parsing the result.
     *
     * @return array<int, array<string, string>>|WP_Error
     */
    public function fetch_models(): array|WP_Error
    {
        // Use the shared request method
        $result = $this->request('models'); // Assumes 'models' is the common endpoint path part

        // If the request resulted in an error, return it directly
        if (is_wp_error($result)) {
            return $result;
        }

        // Delegate parsing the successful response to the concrete class
        return $this->parse_model_list($result);
    }

    /**
     * Generates summary by building prompt/body, calling the shared request method, and parsing the result.
     *
     * @param string $content The plain text content to summarize.
     * @return string|WP_Error The generated summary string on success, or a WP_Error object on failure.
     *                          The WP_Error object should contain relevant error codes and messages.
     */
    public function generate_summary(string $content): string|WP_Error
    {
        $prompt = $this->build_summary_prompt($content);
        $request_body = $this->build_summary_request_body($prompt);

        // Use the shared request method for POST
        $result = $this->request(
            $this->get_summary_endpoint(), // Get endpoint path from concrete class
            [
                'timeout' => 30, // Longer timeout for generation
                'body' => $request_body,
            ],
            'POST'
        );

        // If the request resulted in an error, return it directly
        if (is_wp_error($result)) {
            return $result;
        }

        // Delegate parsing the successful response to the concrete class
        return $this->parse_summary($result);
    }

    /**
     * Gets the specific endpoint path for summary generation.
     * Example: 'chat/completions'
     *
     * @return string
     */
    abstract protected function get_summary_endpoint(): string;
}
