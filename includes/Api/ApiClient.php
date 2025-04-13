<?php

/**
 * MSO Meta Description ApiClient
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */

namespace MSO_Meta_Description\Api;

use MSO_Meta_Description\Providers\{GeminiProvider, MistralProvider, OpenAIProvider, ProviderInterface};
use WP_Error;


class ApiClient {
    protected array $providers = [];
    public const DEFAULT_GEMINI_MODEL = 'gemini-pro';
    public const DEFAULT_MISTRAL_MODEL = 'mistral-small';
    public const DEFAULT_OPENAI_MODEL = 'gpt-3.5-turbo';


    public const SUPPORTED_PROVIDERS = [
        'gemini',
        'mistral',
        'openai',
    ];

    public function __construct() {
        $this->providers = [
            'mistral' => new MistralProvider(),
            'gemini'  => new GeminiProvider(),
            'openai'  => new OpenAIProvider(),
        ];
    }

    protected function get_provider(string $name): ProviderInterface|WP_Error {
        return $this->providers[$name] ?? new WP_Error('invalid_provider', __('Invalid AI provider.', 'mso-meta-description'));
    }

    public function fetch_models(string $provider): array|WP_Error {
        $handler = $this->get_provider($provider);
        return is_wp_error($handler) ? $handler : $handler->fetch_models();
    }

    public function generate_summary(string $provider, string $content): string|WP_Error {
        $handler = $this->get_provider($provider);
        return is_wp_error($handler) ? $handler : $handler->generate_summary($content);
    }
}
