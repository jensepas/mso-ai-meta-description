<?php

/**
 * MSO AI Meta Description ProviderInterface
 *
 * Defines the contract for all AI provider implementations within the plugin.
 * Ensures that each provider class has a consistent set of methods for
 * identifying itself, fetching available models, and generating summaries.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.0.0
 */

namespace MSO_AI_Meta_Description\Providers;

use WP_Error;

/**
 * Interface for AI service providers.
 *
 * Classes implementing this interface can be dynamically loaded and used
 * by the plugin to interact with different AI APIs (e.g., Gemini, OpenAI, Mistral).
 */
interface ProviderInterface
{
    /**
     * Get the unique identifier name for this provider.
     *
     * This name is used internally to identify the provider (e.g., 'gemini', 'openai').
     * It should be a lowercase string, typically matching the class name without 'Provider'.
     *
     * @return string The unique name of the provider.
     */
    public function get_name(): string;

    /**
     * Get the title for this provider.
     *
     * @return string The title of the provider.
     */
    public function get_title(): string;

    /**
     * Get the url for this provider.
     *
     * @return string The url of the provider.
     */
    public function get_url_api_key(): string;

    /**
     * Fetches the list of available models supported by this provider's API.
     *
     * This method should make an API call to retrieve model information.
     * It should filter or format the results as needed for display in the plugin settings
     * (e.g., returning an array where each item has 'id' and 'displayName').
     *
     * @return array<string, string>|WP_Error An array of model data on success, or a WP_Error object on failure.
     *                        The array format should be consistent, e.g., [['id' => 'model-1', 'displayName' => 'Model 1'], ...].
     */
    public function fetch_models(): array|WP_Error;

    /**
     * Generates a meta description summary for the given content using the provider's API.
     *
     * This method takes the content to be summarized, constructs the appropriate API request
     * (including prompts, model selection, API keys), makes the API call, and processes the response.
     *
     * @param string $content The plain text content to summarize.
     * @return string|WP_Error The generated summary string on success, or a WP_Error object on failure.
     *                         The WP_Error object should contain relevant error codes and messages.
     */
    public function generate_summary(string $content): string|WP_Error;

    /**
     * Get the default model identifier for this provider.
     *
     * This model might be used when no specific model is selected or available.
     * It should return a string representing the model ID (e.g., 'gpt-3.5-turbo').
     *
     * @return string The default model identifier.
     */
    public function get_default_model(): string;
}
