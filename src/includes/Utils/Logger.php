<?php

/**
 * MSO AI Meta Description Logger Utility
 *
 * Provides a centralized way to handle logging within the plugin.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.3.0
 */

namespace MSO_AI_Meta_Description\Utils;

/**
 * Handles logging operations for the plugin.
 */
class Logger
{
    /**
     * Logs a debug message only if WP_DEBUG is enabled.
     *
     * Use this method for detailed debugging information
     * that is only useful during development or troubleshooting.
     *
     * @param string $message The main log message.
     * @param mixed  ...$context Optional additional context data (e.g., arrays, objects) to include in the log.
     */
    public static function debug(string $message, ...$context): void
    {
        self::log_message('DEBUG', $message, $context);
    }

    /**
     * Logs an error message regardless of WP_DEBUG status.
     *
     * Use this method for actual errors that should always
     * be recorded, even in production (e.g., critical failures, data corruption).
     *
     * @param string $message The main error message.
     * @param mixed  ...$context Optional additional context data.
     */
    public static function error(string $message, ...$context): void
    {
        self::log_message('ERROR', $message, $context);
    }

    /**
     * Logs an informational message.
     *
     * @param string $message The informational message.
     * @param mixed  ...$context Optional context.
     */
    public static function info(string $message, ...$context): void
    {
        self::log_message('INFO', $message, $context);
    }

    /**
     * Private helper method to format and log the message.
     *
     * @param string $level   The log level ('DEBUG', 'ERROR', 'INFO').
     * @param string $message The main log message.
     * @param array<int|string, mixed>  $context The context data array.
     */
    private static function log_message(string $level, string $message, array $context): void
    {
        // Check if WordPress debug mode is enabled before proceeding.
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            // Prepare the log message with a clear prefix based on the level.
            $log_entry = sprintf('[MSO AI Meta Description %s] %s', $level, $message);

            // If additional context data is provided...
            if (! empty($context)) {
                // Convert the context into a readable string (uses print_r for arrays/objects).
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Used to serialize context data for logging purposes.
                $context_str = print_r($context, true);
                // Add the formatted context to the log message.
                $log_entry .= ' | Context: ' . $context_str;
            }

            // Call error_log to write to the WordPress debug.log file.
            // The phpcs:ignore annotations are now centralized here.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Centralized logging method.
            error_log($log_entry);
        }
    }
} // End class Logger
