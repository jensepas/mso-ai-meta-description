<?php
/**
 * MSO Meta Description OpenAIProvider
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */

namespace MSO_Meta_Description\Providers;

use MSO_Meta_Description\MSO_Meta_Description;
use WP_Error;
class OpenAIProvider implements ProviderInterface {
    const API_BASE = 'https://api.openai.com/v1/';
    const DEFAULT_MODEL = 'gpt-3.5-turbo';

    protected string $api_key;
    protected string $model;


    public function get_name(): string
    {
        return 'openai';
    }

    public function __construct() {
        $prefix = MSO_Meta_Description::get_option_prefix();
        $this->api_key = get_option($prefix . 'openai_api_key');
        $this->model = get_option($prefix . 'openai_model', self::DEFAULT_MODEL);
    }

    public function fetch_models(): array|WP_Error {
        if (empty($this->api_key)) {
            return new WP_Error('api_key_missing', __('API key for OpenAI is missing.', 'mso-meta-description'));
        }

        $response = wp_remote_get(self::API_BASE . 'models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15
        ]);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['data'])) {
            return new WP_Error('api_parse_error', __('Unable to fetch models from OpenAI.', 'mso-meta-description'));
        }

        $models = array_filter($data['data'], fn($model) =>
            str_starts_with($model['id'], 'gpt-3.5') || str_starts_with($model['id'], 'gpt-4')
        );
        return array_values($models);
    }

    public function generate_summary(string $content): string|WP_Error {
        if (empty($this->api_key)) {
            return new WP_Error('api_key_missing', __('API key for OpenAI is missing.', 'mso-meta-description'));
        }

        $prompt = sprintf(
        /* translators: 1: search term, 2: , 3: */
            __('Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Output only the description text itself, without any introductory phrases like "Here is the summary:": %3$s', 'mso-meta-description'),
            MSO_Meta_Description::MIN_DESCRIPTION_LENGTH,
            MSO_Meta_Description::MAX_DESCRIPTION_LENGTH,
            $content // Content is already sanitized/kses'd in Ajax handler
        );

        $response = wp_remote_post(self::API_BASE . 'chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 70,
                'temperature' => 0.6,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['choices'][0]['message']['content'] ?? new WP_Error('api_parse_error', __('Failed to extract summary from OpenAI.', 'mso-meta-description'));
    }

}
