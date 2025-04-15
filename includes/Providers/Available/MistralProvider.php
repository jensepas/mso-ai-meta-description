<?php
/**
 * MSO AI Meta Description MistralProvider
 * // ... docblock ...
 */
namespace MSO_AI_Meta_Description\Providers\Available;

// Change 'use' for ProviderInterface to AbstractProvider if needed, or keep both
use MSO_AI_Meta_Description\Providers\AbstractProvider;
use MSO_AI_Meta_Description\Providers\ProviderInterface; // Still needed for type hints if AbstractProvider doesn't redeclare
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
                __('Unable to parse model list from Mistral: "data" array missing.', 'mso-ai-meta-description')
            );
        }

        return array_map(function ($model) {
            return [
                'id' => $model['id'] ?? '',
                'displayName' => $model['id'] ?? '',
            ];
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
                __('Mistral response missing expected summary data.', 'mso-ai-meta-description')
            );
        }
        return trim($generated_text);
    }
}