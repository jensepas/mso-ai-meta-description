<?php
/**
 * MSO AI Meta Description GeminiProvider
 *
 * Implements the ProviderInterface for interacting with the Google Gemini API.
 * Handles fetching available Gemini models and generating meta description summaries.
 * Extends AbstractProvider for common functionality.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.3.0
 */
namespace MSO_AI_Meta_Description\Providers\Available;

// Use the AbstractProvider and ProviderInterface
use MSO_AI_Meta_Description\Providers\AbstractProvider;
use MSO_AI_Meta_Description\Providers\ProviderInterface;
use WP_Error;

// Extend the abstract class
class GeminiProvider extends AbstractProvider implements ProviderInterface
{
    // --- Implementation of Abstract Methods ---

    public function get_name(): string
    {
        return 'gemini';
    }

    protected function get_api_base(): string
    {
        // Base URL for the Google Generative Language API (v1 beta).
        return 'https://generativelanguage.googleapis.com/v1beta/';
    }

    protected function get_default_model(): string
    {
        // Default Gemini model.
        return 'gemini-1.5-flash-latest';
    }

    protected function get_summary_endpoint(): string
    {
        // The endpoint path requires the model name within it for Gemini.
        // The AbstractProvider::request method will append this to the base URL.
        // Note: The API key is added as a query parameter in AbstractProvider::request for POST.
        return "models/$this->model:generateContent"; // Use the selected model property
    }

    protected function extract_error_message(?array $data): ?string
    {
        // Gemini specific error structure
        return $data['error']['message'] ?? null;
    }

    protected function parse_model_list(array $data): array|WP_Error
    {
        // Gemini specific model list structure
        if (!isset($data['models']) || !is_array($data['models'])) {
            return new WP_Error(
                'invalid_response_structure',
                __('Unable to parse model list from Gemini: "models" array missing.', 'mso-ai-meta-description')
            );
        }

        // Filter models supporting 'generateContent'
        $models = array_filter($data['models'], fn($model) =>
            isset($model['supportedGenerationMethods']) &&
            is_array($model['supportedGenerationMethods']) &&
            in_array('generateContent', $model['supportedGenerationMethods'], true) &&
            (!str_starts_with($model['displayName'], 'Gemini 1.0'))

        );

        // Map to consistent format
        return array_map(function ($model) {
            return [
                'id' => str_replace('models/', '', $model['name'] ?? ''),
                'displayName' => $model['displayName'] ?? $model['id'],
            ];
        }, array_values($models));
    }

    protected function build_summary_request_body(string $prompt): array
    {
        // Gemini specific request body structure
        return [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 90,
                'temperature' => 0.6,
            ]
        ];
    }

    protected function parse_summary(array $data): string|WP_Error
    {
        // Gemini specific summary structure
        $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($generated_text === null) {
            return new WP_Error(
                'parse_error',
                __('Gemini response missing expected summary data.', 'mso-ai-meta-description')
            );
        }
        return trim($generated_text);
    }

    /**
     * Override prepare_headers to remove the Authorization header,
     * as Gemini uses the API key in the URL query parameter.
     *
     * @param array $headers Default headers.
     * @return array Modified headers.
     */
    protected function prepare_headers(array $headers): array
    {
        // Gemini doesn't use the Bearer token header.
        unset($headers['Authorization']);
        return $headers;
    }
}