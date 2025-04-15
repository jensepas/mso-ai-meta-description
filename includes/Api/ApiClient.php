<?php
/**
 * MSO Meta Description ApiClient
 *
 * Acts as a facade for interacting with various AI provider APIs.
 * It uses the ProviderManager to get the appropriate provider instance
 * and delegates API calls (like fetching models or generating summaries) to it.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description\Api;

use MSO_Meta_Description\Providers\ProviderInterface; // Interface for individual providers.
use MSO_Meta_Description\Providers\ProviderManager; // Manages provider instances.
use WP_Error; // Used for returning standardized errors.

/**
 * Client for interacting with AI APIs through registered providers.
 */
class ApiClient
{
    /**
     * Array defining the supported AI providers by their unique names.
     * This should align with the names returned by ProviderInterface::get_name().
     * @var string[]
     */
    const SUPPORTED_PROVIDERS = ['gemini', 'mistral', 'openai', 'anthropic'];

    /**
     * Fetches the list of available models for a specific provider.
     *
     * Retrieves the provider instance using the ProviderManager and calls its fetch_models() method.
     *
     * @param string $provider_name The unique name of the provider (e.g., 'gemini', 'openai').
     * @return array|WP_Error An array of model data on success, or a WP_Error object on failure
     *                        (e.g., if provider not found or API call fails).
     */
    public function fetch_models(string $provider_name): array|WP_Error
    {
        // Get the specific provider instance directly using the static method.
        $provider = ProviderManager::get_provider($provider_name);

        // Check if a provider instance was found for the given name.
        if (!$provider instanceof ProviderInterface) {
            return new WP_Error(
                'provider_not_found', // Error code
                sprintf(
                /* translators: %s: Provider name (e.g., Mistral) */
                    __('AI provider "%s" is not registered or supported.', 'mso-meta-description'), $provider_name) // Error message
            );
        }

        // Delegate the call to the provider's fetch_models method.
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
        // Get the specific provider instance directly using the static method.
        $provider = ProviderManager::get_provider($provider_name);

        // Check if a provider instance was found for the given name.
        if (!$provider instanceof ProviderInterface) {
            return new WP_Error(
                'provider_not_found', // Error code
                sprintf(
                /* translators: %s: Provider name (e.g., Mistral) */
                    __('AI provider "%s" is not registered or supported.', 'mso-meta-description'), $provider_name) // Error message
            );
        }

        // Delegate the call to the provider's generate_summary method.
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
        // Return the list of providers obtained directly using the static method.
        return ProviderManager::get_providers();
    }
}