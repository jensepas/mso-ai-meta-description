<?php

/**
 * MSO AI Meta Description Cohere Provider
 *
 * Implements the ProviderInterface for interacting with the Cohere  API.
 * Handles generating meta description summaries using Cohere  models.
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
 * Cohere Provider implementation.
 *
 * Extends the AbstractProvider to inherit common API interaction logic
 * and implements ProviderInterface methods specific to Cohere API.
 */

class CohereProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Returns the title for this provider.
     *
     * @return string The provider title
     */
    public function get_title(): string
    {
        return 'Cohere';
    }

    /**
     * Returns the base URL for the Anthropic API key.
     *
     */
    public function get_url_api_key(): string
    {
        return 'https://dashboard.cohere.com';
    }

    /**
     * Returns the default model ID to use if none is specified.
     *
     * @return string The default Cohere model identifier.
     */
    public function get_default_model(): string
    {
        return 'command-a-03-2025';
    }

    /**
     * Returns the base URL for the Cohere API.
     *
     * @return string The base URL for Cohere API v1.
     */
    protected function get_api_base(): string
    {
        return 'https://api.cohere.ai/v2/';
    }

    /**
     * Returns the specific API endpoint for generating summaries (chat completions).
     *
     * @return string The endpoint path for chat completions.
     */
    protected function get_summary_endpoint(): string
    {
        return 'chat';
    }

    /**
     * Extracts the error message from a Cohere API error response.
     *
     * Cohere typically returns errors in a nested 'error' object with a 'message' field.
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
     * Fetches models.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the models endpoint.
     * @return array<int, array<string, string>>|WP_Error An array of models (each with 'id' and 'displayName')
     *                                                    or a WP_Error if parsing fails.
     */
    protected function parse_model_list(array $data): array|WP_Error
    {
        if (! isset($data['models']) || ! is_array($data['models'])) {
            $provider = $this->get_name();

            return new WP_Error('parse_error', sprintf(/* translators: 1: provider name */ __('Unable to parse model list from %1$d: "models" array missing.', 'mso-ai-meta-description'), $provider));
        }

        return array_map(function ($model) {
            return ['id' => $model['name'], 'displayName' => $model['name'],];
        }, $data['models']);
    }

    /**
     * Returns the unique identifier for this provider.
     *
     * @return string The provider name ('Cohere').
     */
    public function get_name(): string
    {
        return 'cohere';
    }

    /**
     * Builds the request body for the Cohere chat completions endpoint.
     *
     * Constructs the JSON payload required by the API, including the model,
     * the user prompt, and parameters like max_tokens and temperature.
     *
     * @param string $prompt The user-provided text to generate a summary from.
     * @return array<string, mixed> The request body as an associative array, ready for JSON encoding.
     */
    protected function build_summary_request_body(string $prompt): array
    {
        return ['model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]], 'stream' => false];
    }

    /**
     * Parses the generated summary text from the Cohere API response.
     *
     * Extracts the content from the expected location within the chat completion response structure.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the chat completions endpoint.
     * @return string|WP_Error The extracted summary text on success, or a WP_Error if parsing fails.
     */
    protected function parse_summary(array $data): string|WP_Error
    {
        $generated_text = null;
        if (isset($data['message']['content'][0]['type']) && is_array($data['message']['content']) && $data['message']['content'][0]['type'] === 'text') {
            $generated_text = $data['message']['content'][0]['text'] ?? null;
        }

        if ($generated_text === null) {
            $provider = $this->get_name();

            return new WP_Error('parse_error', sprintf(/* translators: 1: provider name */ __('%1$d response missing expected summary data or invalid format.', 'mso-ai-meta-description'), $provider));
        }

        return is_string($generated_text) ? trim($generated_text) : '';
    }

    /**
     * Overrides prepare_headers to set Cohere-specific authentication headers.
     *
     * @param array<string, string> $headers Default headers from AbstractProvider.
     * @return array<string, string> Modified headers for Cohere API.
     */
    protected function prepare_headers(array $headers): array
    {
        $headers['Cohere-Version'] = '2022-12-06';
        $headers['accept'] = 'application/json';

        return $headers;
    }
}
