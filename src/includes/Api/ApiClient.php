<?php

/**
 * MSO AI Meta Description ApiClient
 *
 * Acts as a facade for interacting with various AI provider APIs.
 * It uses the ProviderManager to get the appropriate provider instance
 * and delegates API calls (like fetching models or generating summaries) to it.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description\Api;

use MSO_AI_Meta_Description\Providers\ProviderInterface;
use MSO_AI_Meta_Description\Providers\ProviderManager;
use WP_Error;

/**
 * Client for interacting with AI APIs through registered providers.
 */
class ApiClient
{
    /**
     * Fetches the list of available models for a specific provider.
     *
     * Retrieves the provider instance using the ProviderManager and calls its fetch_models() method.
     *
     * @param string $provider_name The unique name of the provider (e.g., 'gemini', 'openai').
     * @return array<string, string>|WP_Error An array of model data on success, or a WP_Error object on failure
     *                        (e.g., if provider not found or API call fails).
     */
    public function fetch_models(string $provider_name): array|WP_Error
    {
        
        $provider = ProviderManager::get_provider($provider_name);

        
        if (! $provider instanceof ProviderInterface) {
            return new WP_Error(
                'provider_not_found', 
                sprintf(
                    /* translators: %s: Provider name (e.g., Mistral) */
                    __('AI provider "%s" is not registered or supported.', 'mso-ai-meta-description'),
                    $provider_name
                ) 
            );
        }

        
        return $provider->fetch_models();
    }

    /**
     * Generates a meta description summary using a specific provider.
     *
     * Retrieves the provider instance using the ProviderManager and calls its generate_summary() method.
     *
     * @param string $provider_name The unique name of the provider (e.g., 'gemini', 'openai').
     * @param string $content       The content to summarize.
     * @return string|WP_Error The generated summary string on success, or a WP_Error object on failure
     *                         (e.g., if provider not found or API call fails).
     */
    public function generate_summary(string $provider_name, string $content): string|WP_Error
    {
        
        $provider = ProviderManager::get_provider($provider_name);

        
        if (! $provider instanceof ProviderInterface) {
            return new WP_Error(
                'provider_not_found', 
                sprintf(
                    /* translators: %s: Provider name (e.g., Mistral) */
                    __('AI provider "%s" is not registered or supported.', 'mso-ai-meta-description'),
                    $provider_name
                ) 
            );
        }

        
        return $provider->generate_summary($content);
    }

    /**
     * Retrieves all registered provider instances.
     * Delegates the call directly to the ProviderManager.
     *
     * @return ProviderInterface[] An array of all registered provider instances.
     */
    public function get_providers(): array
    {
        
        return ProviderManager::get_providers();
    }
}
