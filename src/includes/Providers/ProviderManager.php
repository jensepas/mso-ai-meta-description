<?php

/**
 * MSO AI Meta Description ProviderManager
 *
 * Manages the discovery, registration, and retrieval of AI provider instances.
 * It scans a designated directory for classes implementing ProviderInterface
 * and makes them available throughout the plugin.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description\Providers;

use Exception;
use FilesystemIterator;
use GlobIterator;
use MSO_AI_Meta_Description\Utils\Logger;
use ReflectionClass;
use SplFileInfo;

/**
 * Manages AI provider instances.
 */
class ProviderManager
{
    /**
     * Holds the registered provider instances, keyed by their unique name.
     * @var ProviderInterface[]
     */
    protected static array $providers = [];

    /**
     * Flag to ensure provider registration from directory happens only once per request.
     */
    protected static bool $providers_registered = false;

    /**
     * Registers a provider instance.
     * Adds the provider to the static array, keyed by its name returned by get_name().
     *
     * @param ProviderInterface $provider The provider instance to register.
     */
    public static function register_provider(ProviderInterface $provider): void
    {
        if (isset(self::$providers[$provider->get_name()])) {
            Logger::debug('Overriding registered provider', ['name' => $provider->get_name()]);
        }

        self::$providers[$provider->get_name()] = $provider;
    }

    /**
     * Retrieves a specific provider instance by its unique name.
     * Ensures that providers have been registered before attempting retrieval.
     *
     * @param string $name The unique name of the provider (e.g., 'gemini', 'openai').
     * @return ProviderInterface|null The provider instance or null if not found.
     */
    public static function get_provider(string $name): ?ProviderInterface
    {
        return self::$providers[$name] ?? null;
    }

    /**
     * Retrieves all registered provider instances.
     * Ensures that providers have been registered before returning the list.
     *
     * @return ProviderInterface[] An array of all registered provider instances.
     */
    public static function get_providers(): array
    {
        return self::$providers;
    }

    /**
     * Scans the 'Available' subdirectory for provider classes and registers them.
     *
     * This method iterates through PHP files ending in 'Provider.php', includes them,
     * uses reflection to check if they implement ProviderInterface and are instantiable,
     * and then registers valid provider instances.
     * Ensures this scanning and registration process happens only once per request lifecycle.
     */
    public static function register_providers_from_directory(): void
    {
        if (self::$providers_registered) {
            return;
        }

        $providers_dir = __DIR__ . '/Available/';
        if (! is_dir($providers_dir)) {
            self::$providers_registered = true;

            return;
        }

        try {
            $iterator = new GlobIterator($providers_dir . '*Provider.php', FilesystemIterator::KEY_AS_PATHNAME);

            foreach ($iterator as $path => $fileInfo) {
                self::process_provider_file($path, $fileInfo);
            }
        } catch (Exception $e) {
            // Catch potential errors during iterator creation/usage
            Logger::error('Error iterating through provider directory', ['path' => $providers_dir, 'exception_message' => $e->getMessage()]);
        }

        self::$providers_registered = true;
    }

    /**
     * Processes a single potential provider file.
     * Includes the file, validates the class, instantiates, and registers it.
     *
     * @param string       $path     The full path to the file.
     * @param SplFileInfo $fileInfo File information object.
     * @private
     */
    private static function process_provider_file(string $path, SplFileInfo $fileInfo): void
    {
        if (! $fileInfo->isFile() || ! $fileInfo->isReadable()) {
            Logger::debug('Skipping non-file or unreadable item in provider directory.', ['path' => $path]);

            return;
        }

        require_once $path;

        $className = $fileInfo->getBasename('.php');
        $fullQualifiedName = __NAMESPACE__ . '\\Available\\' . $className;

        try {
            if (! class_exists($fullQualifiedName)) {
                Logger::error('File included, but class not found', ['path' => $path, 'expected_class' => $fullQualifiedName]);
            } else {
                $reflection = new ReflectionClass($fullQualifiedName);

                if (! $reflection->implementsInterface(ProviderInterface::class)) {
                    Logger::error('Class found but does not implement ProviderInterface', ['class' => $fullQualifiedName]);
                } elseif (! $reflection->isInstantiable()) {
                    Logger::error('Class found but is not instantiable (e.g., abstract)', ['class' => $fullQualifiedName]);
                } else {
                    /** @var ProviderInterface $providerInstance */
                    $providerInstance = $reflection->newInstance();
                    self::register_provider($providerInstance);
                    Logger::debug('Successfully registered provider.', ['class' => $fullQualifiedName]);
                }
            }
        } catch (Exception $e) {
            Logger::error(
                'Error processing provider file',
                ['path' => $path, 'class' => $fullQualifiedName, 'exception_message' => $e->getMessage()]
            );
        }
    }

    /**
     * Gets the names of all registered providers.
     *
     * @return string[] An array of provider names (e.g., ['gemini', 'mistral']).
     */
    public static function get_provider_names(): array
    {
        self::register_providers_from_directory();

        return array_keys(self::$providers);
    }
}
