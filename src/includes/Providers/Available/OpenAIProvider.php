<?php

/**
 * MSO AI Meta Description OpenAIProvider
 *
 * Implements the ProviderInterface for interacting with the OpenAI API (specifically Chat Completions).
 * Handles fetching available GPT models and generating meta description summaries.
 * Extends AbstractProvider for common functionality.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description\Providers\Available;

// Use the AbstractProvider and ProviderInterface
use MSO_AI_Meta_Description\Providers\AbstractProvider;
use MSO_AI_Meta_Description\Providers\ProviderInterface;
use WP_Error; // Used for returning standardized errors.

/**
 * OpenAI (GPT) Provider implementation.
 *
 * Extends the AbstractProvider to inherit common API interaction logic
 * and implements ProviderInterface methods specific to OpenAI's API.
 */
class OpenAIProvider extends AbstractProvider implements ProviderInterface
{
    // --- Implementation of Abstract Methods ---

    /**
     * Returns the unique identifier for this provider.
     *
     * @return string The provider name ('openai').
     */
    public function get_name(): string
    {
        return 'openai';
    }

    /**
     * Returns the title for this provider.
     *
     * @return string The provider title
     */
    public function get_title(): string
    {
        return 'OpenIA';
    }

    /**
     * Returns the base URL for the OpenAI API.
     *
     * @return string The base URL for OpenAI API v1.
     */
    protected function get_api_base(): string
    {
        // Base URL for the OpenAI API (v1).
        return 'https://api.openai.com/v1/';
    }

    /**
     * Returns the base URL for the Anthropic API key.
     *
     * @return string
     */
    public function get_url_api_key(): string
    {
        return 'https://platform.openai.com';
    }

    /**
     * Returns the default model ID to use if none is specified.
     *
     * @return string The default OpenAI model identifier.
     */
    public function get_default_model(): string
    {
        // Default OpenAI model.
        return 'gpt-3.5-turbo';
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
     * Extracts the error message from an OpenAI API error response.
     *
     * OpenAI typically returns errors in a nested 'error' object with a 'message' field.
     *
     * @param array<string, mixed> $data The decoded JSON response data, or null if decoding failed.
     * @return string The extracted error message, or null if not found.
     */
    protected function extract_error_message(array $data): string
    {
        // OpenAI specific error
        if (isset($data['body']) && is_string($data['body'])) {
            return $data['body'];
        }

        return '';
    }

    /**
     * Parses the list of available models from the OpenAI API response.
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
        // OpenAI specific model list structure: $data['data'] is an array of model objects.
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

        // Filter the list of models: keep only those whose IDs start with 'gpt-3.5' or 'gpt-4'.
        // This helps present a relevant list to the user.
        $models = array_filter(
            $data['data'],
            fn ($model) =>
            // Ensure 'id' key exists and the value is a string before checking its prefix.
            isset($model['id']) && is_string($model['id']) &&
            (str_starts_with($model['id'], 'gpt-3.5') || str_starts_with($model['id'], 'gpt-4'))
        );

        // Map the filtered models to our standardized format.
        // Use the model 'id' as both the internal ID and the display name.
        return array_map(function ($model) {
            return [
                'id' => $model['id'] ?? '', // Fallback to empty string if 'id' is somehow missing after filtering
                'displayName' => $model['id'] ?? '', // Use 'id' for display name as well
            ];
        }, array_values($models)); // Re-index the array numerically after filtering.
    }

    /**
     * Builds the request body for the OpenAI chat completions endpoint.
     *
     * Constructs the JSON payload required by the API, including the model,
     * the user prompt, and parameters like max_tokens and temperature.
     *
     * @param string $prompt The user-provided text to generate a summary from.
     * @return array<string, mixed> The request body as an associative array, ready for JSON encoding.
     */
    protected function build_summary_request_body(string $prompt): array
    {
        // OpenAI specific request body structure for chat completions.
        return [
            // Use the model selected by the user (or the default), stored in the $this->model property.
            'model' => $this->model,
            // Structure the prompt according to the 'messages' format required by the chat API.
            'messages' => [['role' => 'user', 'content' => $prompt]],
            // Limit the length of the generated summary. Adjust as needed.
            'max_tokens' => 70,
            // Control the creativity/randomness of the output. Lower values are more deterministic.
            'temperature' => 0.6,
        ];
    }

    /**
     * Parses the generated summary text from the OpenAI API response.
     *
     * Extracts the content from the expected location within the chat completion response structure.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the chat completions endpoint.
     * @return string|WP_Error The extracted summary text on success, or a WP_Error if parsing fails.
     */
    protected function parse_summary(array $data): string|WP_Error
    {
        // OpenAI specific summary structure: $data['choices'][0]['message']['content']
        // Use null coalescing operator for safety, although 'choices' should generally exist on success.
        $generated_text = $data['choices'][0]['message']['content'] ?? null;

        // Check if the expected text content was found.
        if (! is_string($generated_text)) {
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

        // Trim whitespace from the beginning and end of the generated text before returning.
        return trim($generated_text);
    }
}
