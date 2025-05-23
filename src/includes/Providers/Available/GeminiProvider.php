<?php

/**
 * MSO AI Meta Description GeminiProvider
 *
 * Implements the ProviderInterface for interacting with the Google Gemini API.
 * Handles fetching available Gemini models and generating meta description summaries.
 * Extends AbstractProvider for common functionality.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.0.0
 */

namespace MSO_AI_Meta_Description\Providers\Available;

use MSO_AI_Meta_Description\Providers\AbstractProvider;
use MSO_AI_Meta_Description\Providers\ProviderInterface;
use WP_Error;

/**
 * Gemini Provider implementation.
 *
 * Extends the AbstractProvider to inherit common API interaction logic
 * and implements ProviderInterface methods specific to Gemini API.
 */
class GeminiProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Returns the title for this provider.
     *
     * @return string The provider title
     */
    public function get_title(): string
    {
        return 'Gemini';
    }

    /**
     * Returns the base URL for the Anthropic API key.
     *
     */
    public function get_url_api_key(): string
    {
        return 'https://aistudio.google.com';
    }

    /**
     * Returns the default model ID to use if none is specified.
     *
     * @return string The default Gemini model identifier.
     */
    public function get_default_model(): string
    {
        return 'gemini-1.5-flash-latest';
    }

    /**
     * Returns the base URL for the Gemini API.
     *
     * @return string The base URL for Gemini API v1.
     */
    protected function get_api_base(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta/';
    }

    protected function get_summary_endpoint(): string
    {
        return "models/$this->model:generateContent";
    }

    /**
     * Extracts the error message from Gemini API error response.
     *
     * Gemini typically returns errors in a nested 'error' object with a 'message' field.
     *
     * @param array<string, mixed>|null $data The decoded JSON response data, or null if decoding failed.
     * @return string The extracted error message, or null if not found.
     */
    protected function extract_error_message(?array $data): string
    {
        if (isset($data['body']) && is_string($data['body'])) {
            return $data['body'];
        }

        return '';
    }

    /**
     * Parses the list of available models from the Gemini API response.
     *
     * Filters the models to include only 'gpt-3.5' and 'gpt-4' variants
     * and formats them into a standardized array structure.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the models endpoint.
     * @return array<int, array<string, string>>|WP_Error An array of models (each with 'id' and 'displayName')
     *                                                    or a WP_Error if parsing fails.
     */
    protected function parse_model_list(array $data): array|WP_Error
    {
        if (! isset($data['models']) || ! is_array($data['models'])) {
            $provider = $this->get_name();

            return new WP_Error(
                'parse_error',
                sprintf(
                    /* translators: 1: provider name */
                    __('Unable to parse model list from %1$d: "models" array missing.', 'mso-ai-meta-description'),
                    $provider
                )
            );
        }

        $models = array_filter(
            $data['models'],
            fn ($model) =>
            isset($model['supportedGenerationMethods']) &&
            is_array($model['supportedGenerationMethods']) &&
            in_array('generateContent', $model['supportedGenerationMethods'], true) &&
            (! str_starts_with($model['displayName'], 'Gemini 1.0'))
        );

        return array_map(function ($model) {
            return [
                'id' => str_replace('models/', '', $model['name'] ?? ''),
                'displayName' => $model['displayName'] ?? $model['id'],
            ];
        }, array_values($models));
    }

    /**
     * Returns the unique identifier for this provider.
     *
     * @return string The provider name ('gemini').
     */
    public function get_name(): string
    {
        return 'gemini';
    }

    /**
     * Builds the request body for the Gemini chat completions endpoint.
     *
     * Constructs the JSON payload required by the API, including the model,
     * the user prompt, and parameters like max_tokens and temperature.
     *
     * @param string $prompt The user-provided text to generate a summary from.
     * @return array<string, mixed> The request body as an associative array, ready for JSON encoding.
     */
    protected function build_summary_request_body(string $prompt): array
    {
        return [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 40,
                'temperature' => 0.6,
            ],
        ];
    }

    /**
     * Parses the generated summary text from the Gemini API response.
     *
     * Extracts the content from the expected location within the chat completion response structure.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the chat completions endpoint.
     * @return string|WP_Error The extracted summary text on success, or a WP_Error if parsing fails.
     */
    protected function parse_summary(array $data): string|WP_Error
    {
        $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($generated_text === null) {
            $provider = $this->get_name();

            return new WP_Error(
                'parse_error',
                sprintf(
                    /* translators: 1: provider name */
                    __('%1$d response missing expected summary data or invalid format.', 'mso-ai-meta-description'),
                    $provider
                )
            );
        }

        return is_string($generated_text) ? trim($generated_text) : '';
    }

    /**
     * Override prepare_headers to remove the Authorization header,
     * as Gemini uses the API key in the URL query parameter.
     *
     * * @param array<string, string> $headers Default headers from AbstractProvider.
     * * @return array<string, string> Modified headers for Anthropic API.
 */
    protected function prepare_headers(array $headers): array
    {
        unset($headers['Authorization']);

        return $headers;
    }
}
