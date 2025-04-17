<?php

/**
 * MSO AI Meta Description ProviderManager
 *
 * Manages the discovery, registration, and retrieval of AI provider instances.
 * It scans a designated directory for classes implementing ProviderInterface
 * and makes them available throughout the plugin.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.3.0
 */

namespace MSO_AI_Meta_Description\Providers;

use Exception; // Used for directory iteration options.
use FilesystemIterator; // Used for finding files matching a pattern.
use GlobIterator;
use MSO_AI_Meta_Description\Utils\Logger; // Used for inspecting classes to ensure they implement the interface.
use ReflectionClass;
use SplFileInfo;

// Import base Exception class for catching errors during reflection/instantiation.

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
     * @var bool
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
        // Optional: Log if overriding an existing provider with the same name
        if (isset(self::$providers[$provider->get_name()])) {
            Logger::debug('Overriding registered provider', ['name' => $provider->get_name()]);
        }

        // Store the provider instance in the static array using its name as the key.
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
        // Return the provider from the static array if it exists, otherwise return null.
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
        // Return the complete array of registered providers.
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
        // Prevent running the registration process multiple times.
        if (self::$providers_registered) {
            return;
        }

        // Define the directory where provider class files are located.
        // Assumes ProviderManager.php is in includes/Providers/ and providers are in includes/Providers/Available/
        $providers_dir = __DIR__ . '/Available/';

        // Check if the directory exists before attempting to scan it.
        if (! is_dir($providers_dir)) {
            self::$providers_registered = true;

            return;
        }

        // Use GlobIterator to efficiently find all files ending with 'Provider.php' in the directory.
        // FilesystemIterator::KEY_AS_PATHNAME ensures the key is the full path.
        $iterator = new GlobIterator($providers_dir . '*Provider.php', FilesystemIterator::KEY_AS_PATHNAME);

        // Iterate through each found PHP file.
        foreach ($iterator as $path => $fileInfo) {

            if (! $fileInfo instanceof SplFileInfo) {
                continue;
            }

            // Double-check if it's a file and readable (though GlobIterator usually handles this).
            if ($fileInfo->isFile() && $fileInfo->isReadable()) {
                // Include the provider file. Use require_once to prevent fatal errors if included elsewhere.
                require_once $path;

                // Derive the base class name from the filename (e.g., "MistralProvider.php" -> "MistralProvider").
                $className = $fileInfo->getBasename('.php');
                // Construct the fully qualified class name using the current namespace and the 'Available' sub-namespace.
                // Adjust this if your namespace structure differs.
                $fullQualifiedName = __NAMESPACE__ . '\\Available\\' . $className;

                try {
                    // Check if the class actually exists after including the file.
                    if (class_exists($fullQualifiedName)) {
                        // Use Reflection to inspect the class without needing to know its exact type beforehand.
                        $reflection = new ReflectionClass($fullQualifiedName);

                        // Verify that the class implements the required ProviderInterface
                        // and that it's a concrete class that can be instantiated (not abstract or an interface itself).
                        if ($reflection->implementsInterface(ProviderInterface::class) && $reflection->isInstantiable()) {
                            /** @var ProviderInterface $providerInstance */
                            // Create a new instance of the provider class.
                            // Assumes the provider constructor does not require arguments. If it does, use newInstanceArgs().
                            $providerInstance = $reflection->newInstance();
                            // Register the newly created instance with the manager.
                            self::register_provider($providerInstance);
                        } else {
                            // Log if a class was found but didn't meet the criteria.
                            Logger::error(
                                'Class found but does not implement ProviderInterface or is not instantiable',
                                ['class' => $fullQualifiedName]
                            );
                        }
                    } else {
                        // Log if the file was included but the expected class name wasn't defined.
                        Logger::error(
                            'File included, but class not found',
                            ['path' => $path, 'expected_class' => $fullQualifiedName]
                        );
                    }
                } catch (Exception $e) {
                    // Catch any exceptions during reflection or instantiation (e.g., constructor errors).
                    Logger::error(
                        'Error loading provider',
                        ['path' => $path, 'exception_message' => $e->getMessage()]
                    );
                }
            }
        }

        // Mark the registration process as complete for this request.
        self::$providers_registered = true;
    }
}
