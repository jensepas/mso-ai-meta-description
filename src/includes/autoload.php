<?php

/**
 * MSO AI Meta Description autoload
 *
 * PSR-4 compliant autoloader for the MSO_AI_Meta_Description namespace.
 * This script registers a function that automatically includes class files
 * when they are first used, based on their namespace and class name.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.3.0
 */

// Exit if accessed directly to prevent direct execution of the script.
if (! defined('ABSPATH')) {
    exit; // Using exit is slightly more common here than die.
}

/**
 * Autoload function registered with spl_autoload_register.
 *
 * This function is called by PHP whenever a class or interface from the
 * MSO_AI_Meta_Description namespace is used for the first time and hasn't
 * been loaded yet. It maps the namespace structure to the directory structure
 * within the 'includes' folder.
 *
 * @param string $class The fully qualified class name (e.g., MSO_AI_Meta_Description\Admin).
 */
spl_autoload_register(function ($class) {

    // Define the base namespace prefix for this plugin. All classes managed by this autoloader
    // should start with this namespace.
    $prefix = 'MSO_AI_Meta_Description\\';

    // Check if the requested class uses the plugin's base namespace.
    // If the class name doesn't start with the prefix, this autoloader is not responsible
    // for loading it, so we return early to let other autoloader (if any) handle it.
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    // Remove the base namespace prefix from the fully qualified class name
    // to get the relative class name.
    // Example: 'MSO_AI_Meta_Description\Providers\ProviderManager' becomes 'Providers\ProviderManager'.
    $relative_class = substr($class, strlen($prefix));

    // Define the base directory corresponding to the base namespace prefix.
    // __DIR__ is the directory containing this autoloader file (which is 'includes/').
    // DIRECTORY_SEPARATOR is used for cross-platform compatibility (handles both / and \).
    $base_dir = __DIR__ . DIRECTORY_SEPARATOR;

    // Replace the namespace separators (\) with directory separators (/) in the relative class name.
    // Append '.php' to form the potential file path.
    // Example: 'Providers\ProviderManager' becomes 'Providers/ProviderManager.php'.
    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

    // Check if the constructed file path exists and is readable.
    if (file_exists($file) && is_readable($file)) {
        // If the file exists, include it once. Using require_once prevents fatal errors
        // if the file somehow gets included again elsewhere.
        require_once $file;
    }
});
