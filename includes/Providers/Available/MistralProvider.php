<?php
/**
 * MSO Meta Description MistralProvider
 *
 * Implements the ProviderInterface for interacting with the Mistral AI API.
 * Handles fetching available Mistral models and generating meta description summaries.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description\Providers\Available;

use MSO_Meta_Description\MSO_Meta_Description; // Used for constants like OPTION_PREFIX, MIN/MAX_DESCRIPTION_LENGTH.
use MSO_Meta_Description\Providers\ProviderInterface; // Interface this class implements.
use WP_Error; // Used for returning standardized errors.

/**
 * Mistral AI Provider implementation.
 */
class MistralProvider implements ProviderInterface
{
    /**
     * Base URL for the Mistral AI API (v1).
     * @var string
     */
    const API_BASE = 'https://api.mistral.ai/v1/';

    /**
     * Default Mistral model to use if none is specified in settings.
     * @var string
     */
    const DEFAULT_MODEL = 'mistral-small-latest'; // A common and capable model.

    /**
     * Get the unique identifier name for this provider.
     *
     * @return string The provider name ('mistral').
     */
    public function get_name(): string
    {
        return 'mistral';
    }

    /**
     * Fetches the list of available Mistral models from the API.
     *
     * @return array|WP_Error An array of model data (each with 'id', 'object', 'created', etc.)
     *                        on success, or a WP_Error object on failure.
     */
    public function fetch_models(): array|WP_Error
    {
        // Retrieve the Mistral API key from WordPress options.
        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . 'mistral_api_key');
        // Check if the API key is set; return an error if not.
        if (empty($api_key)) {
            return new WP_Error(
                'api_key_missing', // Error code
                __('API key for Mistral is not set.', 'mso-meta-description') // Error message
            );
        }

        // Make the HTTP GET request to the Mistral API's models endpoint.
        $response = wp_remote_get(self::API_BASE . 'models', [
            'timeout' => 15, // Set a 15-second timeout.
            'headers' => [
                'Content-Type' => 'application/json', // Standard header.
                'Authorization' => 'Bearer ' . $api_key, // Use Bearer token authentication.
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
            // Try to extract a specific error message from the Mistral JSON error response.
            $error_message = $data['message'] ?? __('Unknown API error occurred.', 'mso-meta-description');
            // Log the full response for debugging.
            // error_log("Mistral API Error ({$http_code}): {$error_message} - Response: {$body}");
            return new WP_Error(
                'api_error', // Error code
                sprintf(
                /* translators:1: HTTP status code, 2: Error message */
                    __('Mistral API Error (%1$d): %2$s', 'mso-meta-description'), $http_code, $error_message), // User-friendly message
                ['status' => $http_code] // Include status code in error data
            );
        }

        // Check if the decoded data contains the expected 'data' array (which holds the models).
        if (!isset($data['data']) || !is_array($data['data'])) {
            // Log the raw response body for debugging if parsing fails.
            // error_log('Mistral API Error - Invalid model list response: ' . $body);
            return new WP_Error(
                'invalid_response', // Error code
                __('Unable to parse model list from Mistral.', 'mso-meta-description') // Error message
            );
        }

        // Map the models to include a 'displayName' for consistency, falling back to 'id'.
        // Mistral API returns models in the 'data' array, each object has an 'id'.
        return array_map(function ($model) {
            $model['displayName'] = $model['id'] ?? ''; // Use 'id' as 'displayName'
            return $model;
        }, array_values($data['data'])); // Re-index the array just in case.
    }

    /**
     * Generates a meta description summary for the given content using the Mistral Chat API.
     *
     * @param string $content The content to summarize.
     * @return string|WP_Error The generated summary string on success, or a WP_Error object on failure.
     */
    public function generate_summary(string $content): string|WP_Error
    {
        // Retrieve the Mistral API key and selected model from WordPress options.
        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . 'mistral_api_key');
        $model = get_option(MSO_Meta_Description::get_option_prefix() . 'mistral_model', self::DEFAULT_MODEL); // Use default if not set.

        // Check if the API key is set; return an error if not.
        if (empty($api_key)) {
            return new WP_Error(
                'api_key_missing', // Error code
                __('API key for Mistral is not set.', 'mso-meta-description') // Error message
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

        // Make the HTTP POST request to the Mistral API's chat completions endpoint.
        $response = wp_remote_post(self::API_BASE . 'chat/completions', [
            'timeout' => 30, // Set a 30-second timeout for potentially longer generation.
            'headers' => [
                'Content-Type' => 'application/json', // Specify JSON content type.
                'Authorization' => 'Bearer ' . $api_key, // Use Bearer token authentication.
            ],
            'body' => wp_json_encode([ // Encode the request body as JSON.
                'model' => $model, // The selected Mistral model.
                'messages' => [['role' => 'user', 'content' => $prompt]], // Structure the prompt according to the chat API format.
                'max_tokens' => 70, // Limit the maximum number of tokens in the response (adjust as needed, ~150-200 chars).
                'temperature' => 0.6, // Controls randomness (lower means more deterministic).
                // 'safe_prompt' => true // Optional: Enable guardrailing against harmful content.
            ])
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
            // Try to extract a specific error message from the Mistral JSON error response.
            $error_message = $data['message'] ?? __('Unknown API error occurred.', 'mso-meta-description');
            // Log the full response for debugging.
            // error_log("Mistral API Error ({$http_code}): {$error_message} - Response: {$body}");
            return new WP_Error(
                'api_error', // Error code
                sprintf(
                /* translators:1: HTTP status code, 2: Error message */
                    __('Mistral API Error (%1$d): %2$s', 'mso-meta-description'), $http_code, $error_message), // User-friendly message
                ['status' => $http_code] // Include status code in error data
            );
        }

        // Attempt to extract the generated text from the expected location in the successful response structure.
        // Uses null coalescing operator (??) for safer access through nested arrays.
        $generated_text = $data['choices'][0]['message']['content'] ?? null;

        // Check if the generated text was successfully extracted.
        if ($generated_text === null) {
            // Log the response body if the expected data structure is missing.
            // error_log('Mistral API Error - Could not parse summary from response: ' . $body);
            return new WP_Error(
                'parse_error', // Error code
                __('Mistral response missing expected summary data.', 'mso-meta-description') // Error message
            );
        }

        // Return the successfully extracted summary text.
        return trim($generated_text); // Trim potential leading/trailing whitespace.
    }
}