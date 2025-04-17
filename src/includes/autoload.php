<?php

/**
 * MSO AI Meta Description autoload
 *
 * PSR-4 compliant autoloader for the MSO_AI_Meta_Description namespace.
 * This script registers a function that automatically includes class files
 * when they are first used, based on their namespace and class name.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */


if (! defined('ABSPATH')) {
    exit;
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
    $prefix = 'MSO_AI_Meta_Description\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $base_dir = __DIR__ . DIRECTORY_SEPARATOR;
    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

    if (file_exists($file) && is_readable($file)) {
        require_once $file;
    }
});
