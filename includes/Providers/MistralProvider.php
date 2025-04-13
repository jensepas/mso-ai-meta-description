<?php
/**
 * MSO Meta Description Ajax
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description\Providers;

use WP_Error;
use MSO_Meta_Description\MSO_Meta_Description;

class MistralProvider implements ProviderInterface
{
    const API_BASE = 'https://api.mistral.ai/v1/';
    const DEFAULT_MODEL = 'mistral-small-latest';

    public function get_name(): string
    {
        return 'mistral';
    }

    public function fetch_models(): array|WP_Error
    {
        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . 'mistral_api_key');
        if (empty($api_key)) {
            return new WP_Error('api_key_missing', __('API key for Mistral is not set.', 'mso-meta-description'));
        }

        $response = wp_remote_get(self::API_BASE . 'models', [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['data'])) {
            return new WP_Error('invalid_response', __('Unable to parse model list from Mistral.', 'mso-meta-description'));
        }

        return array_values($data['data']);
    }

    public function generate_summary(string $content): string|WP_Error
    {
        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . 'mistral_api_key');
        $model = get_option(MSO_Meta_Description::get_option_prefix() . 'mistral_model', self::DEFAULT_MODEL);

        if (empty($api_key)) {
            return new WP_Error('api_key_missing', __('API key for Mistral is not set.', 'mso-meta-description'));
        }

        $prompt = sprintf(
        /* translators: 1: search term, 2: , 3: */
            __('Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Output only the description text itself, without any introductory phrases like "Here is the summary:": %3$s', 'mso-meta-description'),
            MSO_Meta_Description::MIN_DESCRIPTION_LENGTH,
            MSO_Meta_Description::MAX_DESCRIPTION_LENGTH,
            $content // Content is already sanitized/kses'd in Ajax handler
        );


        $response = wp_remote_post(self::API_BASE . 'chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 70,
                'temperature' => 0.6,
            ])
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return $data['choices'][0]['message']['content'] ?? new WP_Error('parse_error', __('Mistral response missing expected data.', 'mso-meta-description'));
    }
}