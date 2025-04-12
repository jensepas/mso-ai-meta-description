<?php
namespace MSO_Meta_Description;

if (!defined('ABSPATH')) {
    die;
}

class TemplateRenderer
{
    /**
     * Renders a template file.
     *
     * @param string $template_name The name of the template file (without extension).
     * @param array $args Optional. Arguments to extract into the template's scope.
     * @param string $template_path Optional. Path to the templates directory relative to the plugin root.
     */
    public static function render(string $template_name, array $args = [], string $template_path = 'templates/'): void
    {
        $file_path = plugin_dir_path(dirname(__FILE__)) . trailingslashit($template_path) . $template_name . '.php';

        if (file_exists($file_path)) {
            // Extract variables into the current scope ($key becomes $value)
            extract($args);
            include $file_path;
        } else {
            // Optional: Log error or display a notice if template is missing
            // error_log("MSO Meta Description: Template file not found: " . $file_path);
            echo "<p>Error: Template file '{$template_name}.php' not found.</p>"; // Basic fallback
        }
    }
}