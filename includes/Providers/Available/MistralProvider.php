<?php
/**
 * MSO Meta Description MistralProvider
 * // ... docblock ...
 */
namespace MSO_Meta_Description\Providers\Available;

// Change 'use' for ProviderInterface to AbstractProvider if needed, or keep both
use MSO_Meta_Description\Providers\AbstractProvider;
use MSO_Meta_Description\Providers\ProviderInterface; // Still needed for type hints if AbstractProvider doesn't redeclare
use WP_Error;

// Extend the abstract class
class MistralProvider extends AbstractProvider implements ProviderInterface
{
    // --- Implementation of Abstract Methods ---

    public function get_name(): string
    {
        return 'mistral';
    }

    protected function get_api_base(): string
    {
        return 'https://api.mistral.ai/v1/';
    }

    protected function get_default_model(): string
    {
        return 'mistral-small-latest';
    }

    protected function get_summary_endpoint(): string
    {
        return 'chat/completions';
    }

    protected function extract_error_message(?array $data): ?string
    {
        // Mistral specific error structure
        return $data['message'] ?? null;
    }

    protected function parse_model_list(array $data): array|WP_Error
    {
        // Mistral specific model list structure
        if (!isset($data['data']) || !is_array($data['data'])) {
            return new WP_Error(
                'invalid_response_structure',
                __('Unable to parse model list from Mistral: "data" array missing.', 'mso-meta-description')
            );
        }

        // Filter the list of models: keep only those whose IDs start with 'gpt-3.5' or 'gpt-4'.
        $models = array_filter($data['data'], fn($model) =>
            isset($model['displayName']) && // Ensure 'id' exists before checking
            (str_starts_with($model['id'], 'gpt-3.5') || str_starts_with($model['id'], 'gpt-4'))
        );

        return array_map(function ($model) {
            $model['displayName'] = $model['id'] ?? '';
            return $model;
        }, array_values($data['data'])); // Re-index the array just in case.
    }

    protected function build_summary_request_body(string $prompt): array
    {
        return [
            'model' => $this->model, // Use the model stored in the property
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 70,
            'temperature' => 0.6,
        ];
    }

    protected function parse_summary(array $data): string|WP_Error
    {
        // Mistral specific summary structure
        $generated_text = $data['choices'][0]['message']['content'] ?? null;

        if ($generated_text === null) {
            return new WP_Error(
                'parse_error',
                __('Mistral response missing expected summary data.', 'mso-meta-description')
            );
        }
        return trim($generated_text);
    }
}