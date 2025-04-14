<?php
/**
 * MSO Meta Description OpenAIProvider
 *
 * Implements the ProviderInterface for interacting with the OpenAI API (specifically Chat Completions).
 * Handles fetching available GPT models and generating meta description summaries.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */

namespace MSO_Meta_Description\Providers\Available;

use MSO_Meta_Description\MSO_Meta_Description; // Used for constants like OPTION_PREFIX, MIN/MAX_DESCRIPTION_LENGTH.
use MSO_Meta_Description\Providers\ProviderInterface; // Interface this class implements.
use WP_Error; // Used for returning standardized errors.

/**
 * OpenAI (GPT) Provider implementation.
 */
class OpenAIProvider implements ProviderInterface {
    /**
     * Base URL for the OpenAI API (v1).
     * @var string
     */
    const API_BASE = 'https://api.openai.com/v1/';
    /**
     * Default OpenAI model to use if none is specified in settings.
     * @var string
     */
    const DEFAULT_MODEL = 'gpt-3.5-turbo';

    /**
     * Stores the OpenAI API key retrieved from settings.
     * @var string
     */
    protected string $api_key;
    /**
     * Stores the selected OpenAI model retrieved from settings.
     * @var string
     */
    protected string $model;


    /**
     * Get the unique identifier name for this provider.
     *
     * @return string The provider name ('openai').
     */
    public function get_name(): string
    {
        return 'openai';
    }

    /**
     * Constructor.
     *
     * Retrieves the API key and selected model from WordPress options upon instantiation.
     */
    public function __construct() {
        // Get the standard option prefix for the plugin.
        $prefix = MSO_Meta_Description::get_option_prefix();
        // Retrieve the OpenAI API key from options.
        $this->api_key = get_option($prefix . 'openai_api_key');
        // Retrieve the selected OpenAI model from options, using the default if not set.
        $this->model = get_option($prefix . 'openai_model', self::DEFAULT_MODEL);
    }

    /**
     * Fetches the list of available GPT models from the OpenAI API.
     *
     * Filters models to include only 'gpt-3.5' and 'gpt-4' variants.
     *
     * @return array|WP_Error An array of model data (each with 'id', 'object', 'created', etc.)
     *                        on success, or a WP_Error object on failure.
     */
    public function fetch_models(): array|WP_Error {
        // Check if the API key was successfully retrieved in the constructor.
        if (empty($this->api_key)) {
            return new WP_Error(
                'api_key_missing', // Error code
                __('API key for OpenAI is missing.', 'mso-meta-description') // Error message
            );
        }

        // Make the HTTP GET request to the OpenAI API's models endpoint.
        $response = wp_remote_get(self::API_BASE . 'models', [
            'timeout' => 15, // Set a 15-second timeout.
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key, // Use Bearer token authentication.
                'Content-Type'  => 'application/json', // Standard header.
            ],
        ]);

        // Check if the HTTP request itself failed (e.g., DNS error, timeout).
        if (is_wp_error($response)) {
            return $response; // Return the WP_Error object from wp_remote_get.
        }

        // Retrieve the HTTP status code and response body.
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        // Decode the JSON response body into a PHP associative array.
        $data = json_decode($body, true);

        // Check for non-200 HTTP status codes, indicating an API error.
        if ($http_code !== 200) {
            // Try to extract a specific error message from the OpenAI JSON error response.
            $error_message = $data['error']['message'] ?? __('Unknown API error occurred.', 'mso-meta-description');
            // Log the full response for debugging.
            // error_log("OpenAI API Error ({$http_code}): {$error_message} - Response: {$body}");
            return new WP_Error(
                'api_error', // Error code
                sprintf(
                /* translators:1: HTTP status code, 2: Error message */
                    __('OpenAI API Error (%1$d): %2$s', 'mso-meta-description'), $http_code, $error_message), // User-friendly message
                ['status' => $http_code] // Include status code in error data
            );
        }

        // Check if the decoded data contains the expected 'data' array (which holds the models).
        if (!isset($data['data']) || !is_array($data['data'])) {
            // Log the raw response body for debugging if parsing fails.
            // error_log('OpenAI API Error - Invalid model list response: ' . $body);
            return new WP_Error(
                'invalid_response', // Error code
                __('Unable to parse model list from OpenAI.', 'mso-meta-description') // Error message
            );
        }

        // Filter the list of models: keep only those whose IDs start with 'gpt-3.5' or 'gpt-4'.
        $models = array_filter($data['data'], fn($model) =>
            isset($model['id']) && // Ensure 'id' exists before checking
            (str_starts_with($model['id'], 'gpt-3.5') || str_starts_with($model['id'], 'gpt-4'))
        );

        // Map the models to include a 'displayName' for consistency, falling back to 'id'.
        return array_map(function ($model) {
            $model['displayName'] = $model['id'] ?? ''; // Use 'id' as 'displayName'
            return $model;
        }, array_values($models)); // Re-index the array after filtering.
    }

    /**
     * Generates a meta description summary for the given content using the OpenAI Chat API.
     *
     * @param string $content The content to summarize.
     * @return string|WP_Error The generated summary string on success, or a WP_Error object on failure.
     */
    public function generate_summary(string $content): string|WP_Error {
        // Check if the API key was successfully retrieved in the constructor.
        if (empty($this->api_key)) {
            return new WP_Error(
                'api_key_missing', // Error code
                __('API key for OpenAI is missing.', 'mso-meta-description') // Error message
            );
        }

        // Construct the prompt for the AI model.
        $prompt = sprintf(
        /* translators: 1: Minimum length, 2: Maximum length, 3: The content to summarize */
            __('Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Output only the description text itself, without any introductory phrases like "Here is the summary:": %3$s', 'mso-meta-description'),
            MSO_Meta_Description::MIN_DESCRIPTION_LENGTH, // Minimum recommended length constant.
            MSO_Meta_Description::MAX_DESCRIPTION_LENGTH, // Maximum recommended length constant.
            $content // The input content (already sanitized in the Ajax handler).
        );

        // Make the HTTP POST request to the OpenAI API's chat completions endpoint.
        $response = wp_remote_post(self::API_BASE . 'chat/completions', [
            'timeout' => 30, // Set a 30-second timeout for potentially longer generation.
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key, // Use Bearer token authentication.
                'Content-Type'  => 'application/json', // Specify JSON content type.
            ],
            'body' => wp_json_encode([ // Encode the request body as JSON.
                'model' => $this->model, // Use the model selected in settings (or default).
                'messages' => [['role' => 'user', 'content' => $prompt]], // Structure the prompt according to the chat API format.
                'max_tokens' => 70, // Limit the maximum number of tokens in the response (adjust as needed, ~150-200 chars).
                'temperature' => 0.6, // Controls randomness (lower means more deterministic).
            ]),
        ]);

        // Check if the HTTP request itself failed.
        if (is_wp_error($response)) {
            return $response; // Return the WP_Error object from wp_remote_post.
        }

        // Retrieve the HTTP status code and response body.
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        // Decode the JSON response body.
        $data = json_decode($body, true);

        // Check for non-200 HTTP status codes, indicating an API error.
        if ($http_code !== 200) {
            // Try to extract a specific error message from the OpenAI JSON error response.
            $error_message = $data['error']['message'] ?? __('Unknown API error occurred.', 'mso-meta-description');
            // Log the full response for debugging.
            // error_log("OpenAI API Error ({$http_code}): {$error_message} - Response: {$body}");
            return new WP_Error(
                'api_error', // Error code
                sprintf(
                /* translators:1: HTTP status code, 2: Error message */
                    __('OpenAI API Error (%1$d): %2$s', 'mso-meta-description'), $http_code, $error_message), // User-friendly message
                ['status' => $http_code] // Include status code in error data
            );
        }

        // Attempt to extract the generated text from the expected location in the successful response structure.
        // Uses null coalescing operator (??) for safer access through nested arrays.
        $generated_text = $data['choices'][0]['message']['content'] ?? null;

        // Check if the generated text was successfully extracted.
        if ($generated_text === null) {
            // Log the response body if the expected data structure is missing.
            // error_log('OpenAI API Error - Could not parse summary from response: ' . $body);
            return new WP_Error(
                'parse_error', // Error code
                __('OpenAI response missing expected summary data.', 'mso-meta-description') // Error message
            );
        }

        // Return the successfully extracted summary text.
        return trim($generated_text); // Trim potential leading/trailing whitespace.
    }
}