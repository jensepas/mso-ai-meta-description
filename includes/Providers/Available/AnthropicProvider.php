<?php
/**
 * MSO Meta Description AnthropicProvider
 *
 * Implements the ProviderInterface for interacting with the Anthropic API.
 * Handles generating meta description summaries using Anthropic models.
 * Extends AbstractProvider for common functionality.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */

namespace MSO_Meta_Description\Providers\Available;

// Use the AbstractProvider and ProviderInterface
use MSO_Meta_Description\Providers\AbstractProvider;
use MSO_Meta_Description\Providers\ProviderInterface;
use WP_Error;

/**
 * Anthropic Provider implementation.
 */
// Extend the abstract class
class AnthropicProvider extends AbstractProvider implements ProviderInterface {

    /**
     * Required version header for the Anthropic API.
     */
    const ANTHROPIC_VERSION = '2023-06-01';

    // --- Implementation of Abstract Methods ---

    public function get_name(): string
    {
        // Unique lowercase identifier for Anthropic
        return 'anthropic';
    }

    protected function get_api_base(): string
    {
        // Base URL for the Anthropic API
        return 'https://api.anthropic.com/v1/';
    }

    protected function get_default_model(): string
    {
        // Default Anthropic model
        return 'claude-3-sonnet-20240229';
    }

    protected function get_summary_endpoint(): string
    {
        // Endpoint for generating messages (summaries)
        return 'messages';
    }

    protected function extract_error_message(?array $data): ?string
    {
        // Extracts the error message from Anthropic's specific JSON error structure
        return $data['error']['message'] ?? null;
    }

    /**
     * Fetches models.
     * Anthropic doesn't have a public API endpoint to list models dynamically like OpenAI.
     * We return a predefined list of common Anthropic 3 models.
     *
     * @param array $data Not used in this implementation.
     * @return array|WP_Error A predefined list of models or WP_Error.
     */
    protected function parse_model_list(array $data): array|WP_Error
    {
        // $data is ignored as we are not fetching from API
        // Return a hardcoded list of popular Anthropic 3 models
        // Anthropic specific model list structure
        if (!isset($data['data']) || !is_array($data['data'])) {
            return new WP_Error(
                'invalid_response_structure',
                __('Unable to parse model list from Anthropic: "data" array missing.', 'mso-meta-description')
            );
        }

        // Ensure the format matches the expected structure
        return array_map(function ($model) {
            return [
                'id' => $model['id'],
                'displayName' => $model['display_name'] ?? $model['id'],
            ];
        }, $data['data']);
    }

    protected function build_summary_request_body(string $prompt): array
    {
        // Builds the POST request body for summary generation, specific to Anthropic's Messages API
        return [
            'model' => $this->model, // Uses the selected model
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 150, // Anthropic needs a reasonable max_tokens; 70 might be too low sometimes
            'temperature' => 0.6,
            // 'system' => 'You are an expert meta description writer.' // Optional system prompt
        ];
    }

    protected function parse_summary(array $data): string|WP_Error
    {
        // Extracts the generated summary text from Anthropic's specific JSON response structure
        // Anthropic returns content as an array of blocks; we expect a single text block.
        $generated_text = null;
        if (isset($data['content'][0]['type']) && is_array($data['content']) && $data['content'][0]['type'] === 'text') {
            $generated_text = $data['content'][0]['text'] ?? null;
        }

        if ($generated_text === null) {
            return new WP_Error(
                'parse_error',
                __('Anthropic response missing expected summary data.', 'mso-meta-description')
            );
        }
        return trim($generated_text);
    }

    /**
     * Overrides prepare_headers to set Anthropic-specific authentication headers.
     *
     * @param array $headers Default headers from AbstractProvider.
     * @return array Modified headers for Anthropic API.
     */
    protected function prepare_headers(array $headers): array
    {
        // Remove the default 'Authorization: Bearer' header
        unset($headers['Authorization']);

        // Add Anthropic-specific headers
        $headers['x-api-key'] = $this->api_key;
        $headers['anthropic-version'] = self::ANTHROPIC_VERSION;
        // Content-Type is already set to application/json by AbstractProvider

        return $headers;
    }
}