<?php
/**
 * MSO Meta Description MistralProvider
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description\Providers;

use MSO_Meta_Description\Providers\ProviderInterface;

class ProviderManager
{
    /** @var ProviderInterface[] */
    protected static array $providers = [];

    public static function register_provider(ProviderInterface $provider): void
    {
        self::$providers[$provider->get_name()] = $provider;
    }

    public static function get_provider(string $name): ?ProviderInterface
    {
        return self::$providers[$name] ?? null;
    }

    public static function get_providers(): array
    {
        return self::$providers;
    }

    public static function register_providers(): void
    {
        self::register_provider(new GeminiProvider());
        self::register_provider(new MistralProvider());
        self::register_provider(new OpenAIProvider());
    }
}
