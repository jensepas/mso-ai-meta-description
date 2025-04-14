<?php
/**
 * MSO Meta Description ProviderManager
 *
 * Manages the discovery, registration, and retrieval of AI provider instances.
 * It scans a designated directory for classes implementing ProviderInterface
 * and makes them available throughout the plugin.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description\Providers;

use FilesystemIterator; // Used for directory iteration options.
use GlobIterator; // Used for finding files matching a pattern.
use ReflectionClass; // Used for inspecting classes to ensure they implement the interface.
use Exception; // Import base Exception class for catching errors during reflection/instantiation.

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
        // if (isset(self::$providers[$provider->get_name()])) {
        //     error_log('MSO Meta Description: Overriding registered provider: ' . $provider->get_name());
        // }

        // Store the provider instance in the static array using its name as the key.
        self::$providers[$provider->get_name()] = $provider;

        // Optional: Log registration for debugging
        // error_log('MSO Meta Description: Registered provider: ' . $provider->get_name());
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
        // Make sure the registration process has run at least once.
        self::ensure_providers_registered();
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
        // Make sure the registration process has run at least once.
        self::ensure_providers_registered();
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
        if (!is_dir($providers_dir)) {
            // Log an error if the directory is missing.
            //error_log('MSO Meta Description: Provider directory not found: ' . $providers_dir);
            // Mark as registered even if failed, to prevent repeated attempts in the same request.
            self::$providers_registered = true;
            return;
        }

        // Use GlobIterator to efficiently find all files ending with 'Provider.php' in the directory.
        // FilesystemIterator::KEY_AS_PATHNAME ensures the key is the full path.
        $iterator = new GlobIterator($providers_dir . '*Provider.php', FilesystemIterator::KEY_AS_PATHNAME);

        // Iterate through each found PHP file.
        foreach ($iterator as $path => $fileInfo) {
            // Double-check if it's a file and readable (though GlobIterator usually handles this).
            if ($fileInfo->isFile() && $fileInfo->isReadable()) {
                // Include the provider file. Use require_once to prevent fatal errors if included elsewhere.
                require_once $path;

                // Derive the base class name from the filename (e.g., "MistralProvider.php" -> "MistralProvider").
                $className = $fileInfo->getBasename('.php');
                // Construct the fully qualified class name using the current namespace and the 'Available' sub-namespace.
                // Adjust this if your namespace structure differs.
                $fqcn = __NAMESPACE__ . '\\Available\\' . $className;

                try {
                    // Check if the class actually exists after including the file.
                    if (class_exists($fqcn)) {
                        // Use Reflection to inspect the class without needing to know its exact type beforehand.
                        $reflection = new ReflectionClass($fqcn);

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
                            //error_log("MSO Meta Description: Class $fqcn found but does not implement ProviderInterface or is not instantiable.");
                        }
                    } else {
                        // Log if the file was included but the expected class name wasn't defined.
                        //error_log("MSO Meta Description: File $path included, but class $fqcn not found.");
                    }
                } catch (Exception $e) {
                    // Catch any exceptions during reflection or instantiation (e.g., constructor errors).
                    //error_log("MSO Meta Description: Error loading provider from $path: " . $e->getMessage());
                }
            }
        }

        // Mark the registration process as complete for this request.
        self::$providers_registered = true;
    }

    /**
     * Helper function to ensure the provider registration process has been executed.
     *
     * This is called by get_provider() and get_providers() to ensure the $providers array is populated.
     * Ideally, register_providers_from_directory() should be called explicitly early in the plugin's
     * initialization sequence rather than relying on this lazy check.
     */
    protected static function ensure_providers_registered(): void
    {
        if (!self::$providers_registered) {
            // This warning indicates that providers are being accessed before explicit registration.
            // It's generally better practice to call register_providers_from_directory()
            // during plugin setup (e.g., hooked into 'plugins_loaded').
            //trigger_error("Provider registration was not run before accessing providers.", E_USER_WARNING);

            // As a fallback, you could attempt registration here, but it's less predictable.
            // self::register_providers_from_directory();
        }
    }
}