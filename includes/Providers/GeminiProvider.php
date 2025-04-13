<?php
/**
 * MSO Meta Description GeminiProvider
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description\Providers;

use WP_Error;
use MSO_Meta_Description\MSO_Meta_Description;

class GeminiProvider implements ProviderInterface
{
    const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/';
    const DEFAULT_MODEL = 'gemini-2.0-flash';

    public function get_name(): string
    {
        return 'gemini';
    }

    public function fetch_models(): array|WP_Error
    {
        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . 'gemini_api_key');
        if (empty($api_key)) {
            return new WP_Error('api_key_missing', __('API key for Gemini is not set.', 'mso-meta-description'));
        }

        $url = self::API_BASE . 'models?key=' . $api_key;
        $response = wp_remote_get($url, ['timeout' => 15]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['models'])) {
            return new WP_Error('invalid_response', __('Unable to parse model list from Gemini.', 'mso-meta-description'));
        }

        $models = array_filter($data['models'], fn($model) =>
            isset($model['supportedGenerationMethods']) &&
            in_array('generateContent', $model['supportedGenerationMethods'])
        );

        return array_map(function ($model) {
            $model['id'] = str_replace('models/', '', $model['name']);
            return $model;
        }, array_values($models));
    }

    public function generate_summary(string $content): string|WP_Error
    {
        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . 'gemini_api_key');
        $model = get_option(MSO_Meta_Description::get_option_prefix() . 'gemini_model', self::DEFAULT_MODEL);

        if (empty($api_key)) {
            return new WP_Error('api_key_missing', __('API key for Gemini is not set.', 'mso-meta-description'));
        }

        $prompt = sprintf(
        /* translators: 1: search term, 2: , 3: */
            __('Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Output only the description text itself, without any introductory phrases like "Here is the summary:": %3$s', 'mso-meta-description'),
            MSO_Meta_Description::MIN_DESCRIPTION_LENGTH,
            MSO_Meta_Description::MAX_DESCRIPTION_LENGTH,
            $content // Content is already sanitized/kses'd in Ajax handler
        );


        $url = self::API_BASE . "models/{$model}:generateContent?key={$api_key}";
        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'maxOutputTokens' => 90,
                    'temperature' => 0.6,
                ]
            ])
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? new WP_Error('parse_error', __('Gemini response missing expected data.', 'mso-meta-description'));
    }
}
