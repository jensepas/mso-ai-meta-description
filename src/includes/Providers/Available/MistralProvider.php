<?php

/**
 * MSO AI Meta Description MistralProvider
 *
 * Implements the ProviderInterface for interacting with the Mistral API (specifically Chat Completions).
 * Handles fetching available GPT models and generating meta description summaries.
 * Extends AbstractProvider for common functionality.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description\Providers\Available;

// Change 'use' for ProviderInterface to AbstractProvider if needed, or keep both
use MSO_AI_Meta_Description\Providers\AbstractProvider;
use MSO_AI_Meta_Description\Providers\ProviderInterface;
use WP_Error;

/**
 * Mistral Provider implementation.
 *
 * Extends the AbstractProvider to inherit common API interaction logic
 * and implements ProviderInterface methods specific to Mistral's API.
 */
class MistralProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Returns the unique identifier for this provider.
     *
     * @return string The provider name ('openai').
     */
    public function get_name(): string
    {
        return 'mistral';
    }

    /**
     * Returns the title for this provider.
     *
     * @return string The provider title
     */
    public function get_title(): string
    {
        return 'Mistral';
    }

    /**
     * Returns the base URL for the Mistral API.
     *
     * @return string The base URL for Mistral API v1.
     */
    protected function get_api_base(): string
    {
        return 'https://api.mistral.ai/v1/';
    }

    /**
     * Returns the base URL for the Anthropic API key.
     *
     * @return string
     */
    public function get_url_api_key(): string
    {
        return 'https://console.mistral.ai';
    }

    /**
     * Returns the default model ID to use if none is specified.
     *
     * @return string The default Mistral model identifier.
     */
    public function get_default_model(): string
    {
        // Default Mistral model.
        return 'mistral-small-latest';
    }

    /**
     * Returns the specific API endpoint for generating summaries (chat completions).
     *
     * @return string The endpoint path for chat completions.
     */
    protected function get_summary_endpoint(): string
    {
        // Endpoint for chat completions.
        return 'chat/completions';
    }

    /**
     * Extracts the error message from Mistral API error response.
     *
     * Mistral typically returns errors in a nested 'error' object with a 'message' field.
     *
     * @param array<string, mixed> $data The decoded JSON response data, or null if decoding failed.
     * @return string The extracted error message, or null if not found.
     */
    protected function extract_error_message(array $data): string
    {
        // Mistral specific error structure
        if (isset($data['body']) && is_string($data['body'])) {
            return $data['body'];
        }

        return '';
    }

    /**
     * Parses the list of available models from the Mistral API response.
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
        // Mistral specific model list structure
        if (! isset($data['data']) || ! is_array($data['data'])) {
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

        return array_map(function ($model) {
            return [
                'id' => $model['id'] ?? '',
                'displayName' => $model['id'] ?? '',
            ];
        }, array_values($data['data'])); // Re-index the array just in case.
    }

    /**
     * Builds the request body for the Mistral chat completions endpoint.
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
            'model' => $this->model, // Use the model stored in the property
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 70,
            'temperature' => 0.6,
        ];
    }

    /**
     * Parses the generated summary text from the Mistral API response.
     *
     * Extracts the content from the expected location within the chat completion response structure.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the chat completions endpoint.
     * @return string|WP_Error The extracted summary text on success, or a WP_Error if parsing fails.
     */
    protected function parse_summary(array $data): string|WP_Error
    {
        // Mistral specific summary structure
        $generated_text = $data['choices'][0]['message']['content'] ?? null;

        if ($generated_text === null) {
            // Return an error if the summary text is missing or not a string.
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
}
