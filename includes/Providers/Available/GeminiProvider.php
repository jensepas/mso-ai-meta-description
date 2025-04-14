<?php
/**
 * MSO Meta Description GeminiProvider
 *
 * Implements the ProviderInterface for interacting with the Google Gemini API.
 * Handles fetching available Gemini models and generating meta description summaries.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description\Providers\Available;

use MSO_Meta_Description\MSO_Meta_Description; // Used for constants like OPTION_PREFIX, MIN/MAX_DESCRIPTION_LENGTH.
use MSO_Meta_Description\Providers\ProviderInterface; // Interface this class implements.
use WP_Error; // Used for returning standardized errors.

/**
 * Gemini AI Provider implementation.
 */
class GeminiProvider implements ProviderInterface
{
    /**
     * Base URL for the Google Generative Language API (v1beta).
     * @var string
     */
    const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/';

    /**
     * Default Gemini model to use if none is specified in settings.
     * @var string
     */
    const DEFAULT_MODEL = 'gemini-1.5-flash-latest'; // Updated to a common, recent model

    /**
     * Get the unique identifier name for this provider.
     *
     * @return string The provider name ('gemini').
     */
    public function get_name(): string
    {
        return 'gemini';
    }

    /**
     * Fetches the list of available Gemini models from the API.
     *
     * Filters models to include only those supporting 'generateContent'.
     *
     * @return array|WP_Error An array of model data (each with 'id', 'name', 'displayName', etc.)
     *                        on success, or a WP_Error object on failure.
     */
    public function fetch_models(): array|WP_Error
    {
        // Retrieve the Gemini API key from WordPress options.
        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . 'gemini_api_key');
        // Check if the API key is set; return an error if not.
        if (empty($api_key)) {
            return new WP_Error(
                'api_key_missing', // Error code
                __('API key for Gemini is not set.', 'mso-meta-description') // Error message
            );
        }

        // Construct the API endpoint URL for listing models, including the API key.
        $url = self::API_BASE . 'models?key=' . $api_key;
        // Make the HTTP GET request to the Gemini API.
        $response = wp_remote_get($url, ['timeout' => 15]); // Set a 15-second timeout.

        // Check if the HTTP request itself failed (e.g., DNS error, timeout).
        if (is_wp_error($response)) {
            return $response; // Return the WP_Error object from wp_remote_get.
        }

        // Retrieve the body of the HTTP response.
        $body = wp_remote_retrieve_body($response);
        // Decode the JSON response body into a PHP associative array.
        $data = json_decode($body, true);

        // Check if the decoded data contains the expected 'models' array.
        if (!isset($data['models']) || !is_array($data['models'])) {
            // Log the raw response body for debugging if parsing fails.
            // error_log('Gemini API Error - Invalid model list response: ' . $body);
            return new WP_Error(
                'invalid_response', // Error code
                __('Unable to parse model list from Gemini.', 'mso-meta-description') // Error message
            );
        }

        // Filter the list of models: keep only those that support the 'generateContent' method.
        $models = array_filter($data['models'], fn($model) =>
            isset($model['supportedGenerationMethods']) &&
            is_array($model['supportedGenerationMethods']) && // Ensure it's an array
            in_array('generateContent', $model['supportedGenerationMethods'], true) // Use strict comparison
        );

        // Map the filtered models to a more consistent format for the frontend dropdown.
        // Extracts the model ID from the full 'name' (e.g., 'models/gemini-pro' -> 'gemini-pro').
        return array_map(function ($model) {
            // Add an 'id' key based on the 'name' field.
            $model['id'] = str_replace('models/', '', $model['name'] ?? '');
            // Ensure 'displayName' exists, fallback to 'id'.
            $model['displayName'] = $model['displayName'] ?? $model['id'];
            return $model;
        }, array_values($models)); // Re-index the array after filtering.
    }

    /**
     * Generates a meta description summary for the given content using the Gemini API.
     *
     * @param string $content The content to summarize.
     * @return string|WP_Error The generated summary string on success, or a WP_Error object on failure.
     */
    public function generate_summary(string $content): string|WP_Error
    {
        // Retrieve the Gemini API key and selected model from WordPress options.
        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . 'gemini_api_key');
        $model = get_option(MSO_Meta_Description::get_option_prefix() . 'gemini_model', self::DEFAULT_MODEL); // Use default if not set.

        // Check if the API key is set; return an error if not.
        if (empty($api_key)) {
            return new WP_Error(
                'api_key_missing', // Error code
                __('API key for Gemini is not set.', 'mso-meta-description') // Error message
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

        // Construct the API endpoint URL for generating content, including the model and API key.
        $url = self::API_BASE . "models/{$model}:generateContent?key={$api_key}";

        // Prepare the request body according to the Gemini API specification.
        $request_body = [
            'contents' => [ // Array of content parts (in this case, just one).
                [
                    'parts' => [ // Array of parts within the content (just the text prompt).
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [ // Configuration for the generation process.
                'maxOutputTokens' => 90, // Limit the maximum number of tokens in the response (adjust as needed).
                'temperature' => 0.6, // Controls randomness (lower means more deterministic).
                // 'stopSequences' => ["\n"] // Optional: Stop generation at newlines if needed.
            ]
        ];

        // Make the HTTP POST request to the Gemini API.
        $response = wp_remote_post($url, [
            'timeout' => 30, // Set a 30-second timeout for potentially longer generation.
            'headers' => ['Content-Type' => 'application/json'], // Specify JSON content type.
            'body' => wp_json_encode($request_body) // Encode the request body as JSON.
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
            // Try to extract a specific error message from the Gemini JSON error response.
            $error_message = $data['error']['message'] ?? __('Unknown API error occurred.', 'mso-meta-description');
            // Log the full response for debugging.
            // error_log("Gemini API Error ({$http_code}): {$error_message} - Response: {$body}");
            return new WP_Error(
                'api_error', // Error code
                sprintf(
                /* translators:1: HTTP status code, 2: Error message */
                    __('Gemini API Error (%1$d): %2$s', 'mso-meta-description'), $http_code, $error_message), // User-friendly message
                ['status' => $http_code] // Include status code in error data
            );
        }

        // Attempt to extract the generated text from the expected location in the successful response structure.
        // Uses null coalescing operator (??) for safer access through nested arrays.
        $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        // Check if the generated text was successfully extracted.
        if ($generated_text === null) {
            // Log the response body if the expected data structure is missing.
            // error_log('Gemini API Error - Could not parse summary from response: ' . $body);
            return new WP_Error(
                'parse_error', // Error code
                __('Gemini response missing expected summary data.', 'mso-meta-description') // Error message
            );
        }

        // Return the successfully extracted summary text.
        // Consider adding trim() here if the API sometimes includes leading/trailing whitespace.
        return trim($generated_text);
    }
}