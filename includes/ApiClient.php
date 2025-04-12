<?php
namespace MSO_Meta_Description;

use WP_Error;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    die;
}

class ApiClient
{
    // API Base URLs
    const MISTRAL_API_BASE = 'https://api.mistral.ai/v1/';
    const GEMINI_API_BASE = 'https://generativelanguage.googleapis.com/v1beta/';
    const OPENAI_API_BASE = 'https://api.openai.com/v1/'; // <-- ADDED OpenAI Base URL

    // Default Models
    const DEFAULT_MISTRAL_MODEL = 'mistral-small-latest';
    const DEFAULT_GEMINI_MODEL = 'gemini-2.0-flash'; // Updated to newer default
    const DEFAULT_OPENAI_MODEL = 'gpt-3.5-turbo'; // Default OpenAI model

    // Supported Providers list
    const SUPPORTED_PROVIDERS = ['mistral', 'gemini', 'openai'];

    /**
     * Fetch available models from the specified provider.
     *
     * @param string $provider 'mistral', 'gemini', or 'openai'.
     * @return array|WP_Error Array of models on success, WP_Error on failure.
     */
    public function fetch_models(string $provider): array|WP_Error
    {
        // Validate provider first
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
            return new WP_Error('invalid_provider', __('Invalid provider specified.', MSO_Meta_Description::TEXT_DOMAIN));
        }

        $api_key = get_option(MSO_Meta_Description::get_option_prefix() . $provider . '_api_key');
        if (empty($api_key)) {
            return new WP_Error('api_key_missing', sprintf(__('API key for %s is not set.', MSO_Meta_Description::TEXT_DOMAIN), ucfirst($provider)));
        }

        $url = '';
        $args = ['timeout' => 15, 'headers' => ['Content-Type' => 'application/json']];

        switch ($provider) {
            case 'mistral':
                $url = self::MISTRAL_API_BASE . 'models';
                $args['headers']['Authorization'] = 'Bearer ' . $api_key;
                break;
            case 'gemini':
                $url = self::GEMINI_API_BASE . 'models?key=' . $api_key;
                // Gemini API key is passed in URL, no Authorization header needed
                break;
            case 'openai': // <-- ADDED OpenAI case
                $url = self::OPENAI_API_BASE . 'models';
                $args['headers']['Authorization'] = 'Bearer ' . $api_key;
                break;
        }

        $response = wp_remote_get($url, $args);

        // Pass provider to handler for specific parsing
        return $this->handle_api_response($response, $provider, 'fetch_models');
    }

    /**
     * Generate a summary using the specified AI provider.
     *
     * @param string $provider 'mistral', 'gemini', or 'openai'.
     * @param string $content The content to summarize.
     * @return string|WP_Error The generated summary string on success, WP_Error on failure.
     */
    public function generate_summary(string $provider, string $content): string|WP_Error
    {
         // Validate provider first
        if (!in_array($provider, self::SUPPORTED_PROVIDERS)) {
             return new WP_Error('invalid_provider', __('Invalid provider specified.', MSO_Meta_Description::TEXT_DOMAIN));
        }

        $option_prefix = MSO_Meta_Description::get_option_prefix();
        $api_key = get_option($option_prefix . $provider . '_api_key');
        $model_option = $option_prefix . $provider . '_model';

        // Use default model if option is empty
        $default_model = match($provider) {
            'mistral' => self::DEFAULT_MISTRAL_MODEL,
            'gemini' => self::DEFAULT_GEMINI_MODEL,
            'openai' => self::DEFAULT_OPENAI_MODEL,
            default => '' // Should not happen due to check above
        };
        $model = get_option($model_option, $default_model);


        if (empty($api_key)) {
            return new WP_Error('api_key_missing', sprintf(__('API key for %s is not set.', MSO_Meta_Description::TEXT_DOMAIN), ucfirst($provider)));
        }
        // No need to check if model is empty now, as we use a default
        // if (empty($model)) {
        //     return new WP_Error('model_missing', sprintf(__('Model for %s is not selected.', MSO_Meta_Description::TEXT_DOMAIN), ucfirst($provider)));
        // }


        // Consistent, detailed prompt
        $prompt = sprintf(
            __('Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Output only the description text itself, without any introductory phrases like "Here is the summary:": %3$s', MSO_Meta_Description::TEXT_DOMAIN),
            MSO_Meta_Description::MIN_DESCRIPTION_LENGTH,
            MSO_Meta_Description::MAX_DESCRIPTION_LENGTH,
            $content // Content is already sanitized/kses'd in Ajax handler
        );



        // Consistent, detailed prompt
        $prompt = sprintf(
            __('Summarize the following text into a concise meta description between %1$d and %2$d characters long. Focus on the main topic and keywords. Ensure the description flows naturally and avoid cutting words mid-sentence. Output only the description text itself, without any introductory phrases like "Here is the summary:": %3$s', MSO_Meta_Description::TEXT_DOMAIN),
            MSO_Meta_Description::MIN_DESCRIPTION_LENGTH,
            MSO_Meta_Description::MAX_DESCRIPTION_LENGTH,
            $content // Content is already sanitized/kses'd in Ajax handler
        );

        $url = '';
        $args = ['timeout' => 30, 'headers' => ['Content-Type' => 'application/json']];
        $body = [];
        // Estimate max tokens based on characters (rough estimate, varies by language/model)
        // ~3-4 characters per token on average. Max 160 chars => ~50-60 tokens. Add buffer.
        $max_tokens = 70;

        switch ($provider) {
            case 'mistral':
                $url = self::MISTRAL_API_BASE . 'chat/completions';
                $args['headers']['Authorization'] = 'Bearer ' . $api_key;
                $body = [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => $max_tokens,
                    'temperature' => 0.6,
                ];
                break;

            case 'gemini':
                 // Gemini uses different model structure in URL
                 // Ensure model name is just the ID, not prefixed like 'models/' if API requires that.
                $url = self::GEMINI_API_BASE . 'models/' . $model . ':generateContent?key=' . $api_key;
                $body = [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => $max_tokens + 20, // Gemini counts differently, allow more buffer
                        'temperature' => 0.6,
                        // Add safety settings if needed
                        // 'safetySettings' => [ ... ]
                    ]
                ];
                break;

            case 'openai': // <-- ADDED OpenAI case
                $url = self::OPENAI_API_BASE . 'chat/completions';
                $args['headers']['Authorization'] = 'Bearer ' . $api_key;
                $body = [
                    'model' => $model,
                    'messages' => [
                         // Optional: System message to guide the AI's role
                         // ['role' => 'system', 'content' => __('You are a helpful assistant specialized in writing concise and SEO-friendly meta descriptions.', MSO_Meta_Description::TEXT_DOMAIN)],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => $max_tokens,
                    'temperature' => 0.6,
                    // Could add other parameters like 'top_p' if needed
                ];
                break;
        }

        $args['body'] = wp_json_encode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_encode_error', __('Failed to encode request body.', MSO_Meta_Description::TEXT_DOMAIN));
        }

        $response = wp_remote_post($url, $args);

        // Pass provider to handler for specific parsing if needed, though generic handling might suffice
        $api_result = $this->handle_api_response($response, $provider, 'generate_summary');

        // Extract the actual text content from the response structure
        if (!is_wp_error($api_result) && is_array($api_result)) {
            $summary = null;
            switch ($provider) {
                case 'mistral': // Fall-through to openai as structure is similar
                case 'openai':
                    if (isset($api_result['choices'][0]['message']['content'])) {
                        $summary = $api_result['choices'][0]['message']['content'];
                    } else {
                        return new WP_Error('api_parse_error', sprintf(__('Could not parse summary from %s response.', MSO_Meta_Description::TEXT_DOMAIN), ucfirst($provider)));
                    }
                    break;

                case 'gemini':
                    // Handle potential safety blocks or empty responses
                    if (!empty($api_result['candidates'][0]['content']['parts'][0]['text'])) {
                         $summary = $api_result['candidates'][0]['content']['parts'][0]['text'];
                    } else {
                        $finish_reason = $api_result['candidates'][0]['finishReason'] ?? 'unknown';

                        // Optional: Log more details for debugging
                        // error_log('Gemini API Error/Block: ' . print_r($api_result, true));
                        return new WP_Error('api_parse_error', sprintf(__('Could not parse summary from Gemini response. Finish Reason: %s', MSO_Meta_Description::TEXT_DOMAIN), $finish_reason));
                    }
                    break;
            }

            if ($summary !== null) {
                // Trim whitespace and potentially remove unwanted prefixes/suffixes if AI adds them despite prompt
                return trim($summary);
            }
        }

        // Return WP_Error if handle_api_response returned one, or if parsing failed above
        return is_wp_error($api_result) ? $api_result : new WP_Error('unknown_api_error', __('An unknown error occurred during API processing.', MSO_Meta_Description::TEXT_DOMAIN));
    }

    /**
     * Standardized handling of wp_remote_get/post responses.
     *
     * @param array|WP_Error $response The response from wp_remote_get/post.
     * @param string $provider The provider ('mistral', 'gemini', 'openai').
     * @param string $action The action being performed ('fetch_models', 'generate_summary').
     * @return array|WP_Error Parsed JSON body as array, or WP_Error on failure.
     */
    private function handle_api_response(array|WP_Error $response, string $provider, string $action): array|WP_Error
    {
        if (is_wp_error($response)) {
            // Add context to the existing WP_Error object
            $response->add(
                'http_request_failed',
                sprintf(__('HTTP request failed when calling %s API for %s.', MSO_Meta_Description::TEXT_DOMAIN), ucfirst($provider), $action),
                ['provider' => $provider, 'action' => $action] // Add context data
            );
            echo 'tot'; exit;
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for HTTP errors or JSON decoding errors
        if ($http_code >= 300 || $http_code < 200 || $data === null) {
            $error_message = sprintf(__('Error %d from %s API for %s.', MSO_Meta_Description::TEXT_DOMAIN), $http_code, ucfirst($provider), $action);
            $api_error_details = '';

            // Try to extract error details from common structures
            if (is_array($data)) {
                 if (isset($data['error']['message'])) { // OpenAI/Mistral style
                    $api_error_details = $data['error']['message'];
                 } elseif (isset($data['message'])) { // Gemini style / Other
                    $api_error_details = $data['message'];
                 } elseif (isset($data['error'])) { // Generic error key
                    $api_error_details = is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
                 }
            }

            // Handle JSON decode error specifically
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $api_error_details = __('Invalid JSON response received.', MSO_Meta_Description::TEXT_DOMAIN) . ' ' . json_last_error_msg();
            } elseif(empty($body) && $data === null) {
                $api_error_details = __('Empty response body.', MSO_Meta_Description::TEXT_DOMAIN);
            }

            if (!empty($api_error_details)) {
                $error_message .= ' Details: ' . $api_error_details;
            }

            // Optional: Log the full body for debugging severe errors
            // if ($http_code >= 500) { error_log("API Error Body ($provider/$action): " . $body); }

            return new WP_Error('api_error', $error_message, ['status' => $http_code, 'body' => $body]);
        }

        // --- Success: Process data based on action ---

        if ($action === 'fetch_models') {
            $models = [];
            switch ($provider) {
                case 'mistral': // Fall-through
                case 'openai':
                    if (isset($data['data']) && is_array($data['data'])) {
                        $models = $data['data'];
                        // Filter OpenAI models for common chat models (can be adjusted)
                        if ($provider === 'openai') {
                             $models = array_filter($models, function($model) {
                                 return isset($model['id']) && (
                                     str_starts_with($model['id'], 'gpt-4') || str_starts_with($model['id'], 'gpt-3.5')
                                     // Add other relevant model families if needed
                                 );
                             });
                        }
                        // We might want to extract just the 'id' field for consistency
                        // return array_column($models, 'id');
                        return array_values($models); // Return the filtered array of model objects

                    } else {
                        return new WP_Error('api_parse_error', sprintf(__('Could not parse model list from %s response.', MSO_Meta_Description::TEXT_DOMAIN), ucfirst($provider)));
                    }
                    break; // End case 'mistral', 'openai'

                case 'gemini':
                    if (isset($data['models']) && is_array($data['models'])) {
                        // Filter Gemini models to keep those supporting 'generateContent'
                        $models = array_filter($data['models'], function($model) {
                            return isset($model['supportedGenerationMethods']) && in_array('generateContent', $model['supportedGenerationMethods']);
                        });
                        // Extract model name (e.g., 'models/gemini-2.0-flash' -> 'gemini-2.0-flash')
                        $models = array_map(function($model){
                           $model['id'] = str_replace('models/', '', $model['name']); // Add an 'id' field for consistency
                           return $model;
                        }, $models);

                        return array_values($models); // Return the filtered array of model objects
                    } else {
                        return new WP_Error('api_parse_error', __('Could not parse model list from Gemini response.', MSO_Meta_Description::TEXT_DOMAIN));
                    }
                    break; // End case 'gemini'
            }
             return new WP_Error('api_parse_error', __('Could not parse model list for the specified provider.', MSO_Meta_Description::TEXT_DOMAIN)); // Should not be reached
        }

        // For generate_summary, the calling function handles specific structure extraction.
        // Return the full parsed data array on success.
        if ($action === 'generate_summary') {
            return $data; // Return the full associative array
        }

        // Fallback for unknown action or if logic fails
        return new WP_Error('unknown_action', sprintf(__('Unknown action "%s" requested for API client.', MSO_Meta_Description::TEXT_DOMAIN), $action));
    }
}