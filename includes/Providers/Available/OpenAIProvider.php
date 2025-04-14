<?php
/**
 * MSO Meta Description OpenAIProvider
 *
 * Implements the ProviderInterface for interacting with the OpenAI API (specifically Chat Completions).
 * Handles fetching available GPT models and generating meta description summaries.
 * Extends AbstractProvider for common functionality.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */

namespace MSO_Meta_Description\Providers\Available;

// Use the AbstractProvider and ProviderInterface
use MSO_Meta_Description\Providers\AbstractProvider;
use MSO_Meta_Description\Providers\ProviderInterface;
use WP_Error; // Used for returning standardized errors.

/**
 * OpenAI (GPT) Provider implementation.
 */
// Extend the abstract class
class OpenAIProvider extends AbstractProvider implements ProviderInterface {

    // --- Implementation of Abstract Methods ---

    public function get_name(): string
    {
        return 'openai';
    }

    protected function get_api_base(): string
    {
        // Base URL for the OpenAI API (v1).
        return 'https://api.openai.com/v1/';
    }

    protected function get_default_model(): string
    {
        // Default OpenAI model.
        return 'gpt-3.5-turbo';
    }

    protected function get_summary_endpoint(): string
    {
        // Endpoint for chat completions.
        return 'chat/completions';
    }

    protected function extract_error_message(?array $data): ?string
    {
        // OpenAI specific error structure
        return $data['error']['message'] ?? null;
    }

    protected function parse_model_list(array $data): array|WP_Error
    {
        // OpenAI specific model list structure
        if (!isset($data['data']) || !is_array($data['data'])) {
            return new WP_Error(
                'invalid_response_structure',
                __('Unable to parse model list from OpenAI: "data" array missing.', 'mso-meta-description')
            );
        }

        // Filter the list of models: keep only those whose IDs start with 'gpt-3.5' or 'gpt-4'.
        $models = array_filter($data['data'], fn($model) =>
            isset($model['id']) && // Ensure 'id' exists before checking
            (str_starts_with($model['id'], 'gpt-3.5') || str_starts_with($model['id'], 'gpt-4'))
        );

        // Map the models to include a 'displayName' for consistency, falling back to 'id'.
        return array_map(function ($model) {
            return [
                'id' => $model['id'] ?? '',
                'displayName' => $model['id'] ?? '',
            ];
        }, array_values($models)); // Re-index the array after filtering.
    }

    protected function build_summary_request_body(string $prompt): array
    {
        // OpenAI specific request body structure
        return [
            'model' => $this->model, // Use the model stored in the property
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 70,
            'temperature' => 0.6,
        ];
    }

    protected function parse_summary(array $data): string|WP_Error
    {
        // OpenAI specific summary structure
        $generated_text = $data['choices'][0]['message']['content'] ?? null;

        if ($generated_text === null) {
            return new WP_Error(
                'parse_error',
                __('OpenAI response missing expected summary data.', 'mso-meta-description')
            );
        }
        return trim($generated_text);
    }

    // --- Overrides ---
    // No overrides needed for prepare_headers as OpenAI uses the default Bearer token.
}