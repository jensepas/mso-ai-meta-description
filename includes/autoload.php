<?php
/**
 * MSO Meta Description autoload
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
if (!defined('ABSPATH')) exit;

spl_autoload_register(function ($class) {

    if (str_starts_with($class, 'MSO_Meta_Description\\')) {
        $class = str_replace('MSO_Meta_Description\\', '', $class);
        $file = __DIR__ . '\\' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});