<?php
/**
 * MSO Meta Description ProviderInterface
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */
namespace MSO_Meta_Description\Providers;

use WP_Error;

interface ProviderInterface
{
    public function get_name(): string;
    public function fetch_models(): array|WP_Error;
    public function generate_summary(string $content): string|WP_Error;
}