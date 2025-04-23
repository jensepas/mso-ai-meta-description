<?php

/**
 * MSO AI Meta Description Frontend
 *
 * Handles the output of the meta description tag in the website's <head> section.
 * Determines the appropriate description based on the current page context (post, page, archive, front page, etc.).
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description;

if (! defined('ABSPATH')) {
    die;
}

/**
 * Manages frontend meta description output.
 */
class Frontend
{
    /**
     * The post meta key used to store the custom meta description.
     */
    private string $meta_key;

    /**
     * Stores the WordPress setting for what to display on the front page ('posts' or 'page').
     */
    private string $show_on_front;

    /**
     * Constructor.
     *
     * Initializes the class with the meta key and fetches the 'show_on_front' option.
     *
     * @param string $meta_key The post meta key for the description.
     */
    public function __construct(string $meta_key)
    {
        $this->meta_key = $meta_key;
        $this->show_on_front = (string)get_option('show_on_front');
    }

    /**
     * Registers the WordPress hook for outputting the meta description.
     */
    public function register_hooks(): void
    {
        add_action('wp_head', [$this, 'output_meta_description'], 1);
    }

    /**
     * Outputs the meta description tag in the <head> section.
     *
     * Retrieves the appropriate description for the current page and prints the meta tag.
     * Also handles removing and re-adding the default canonical tag to ensure proper placement relative to the description.
     */
    public function output_meta_description(): void
    {
        remove_action('wp_head', 'rel_canonical');

        $description = $this->get_current_page_description();

        if (! empty($description)) {
            printf(
                "\n\n<meta name=\"description\" content=\"%s\">\n\n",
                esc_attr(trim($description))
            );
        }

        add_action('wp_head', 'rel_canonical');
    }

    /**
     * Determines the correct meta description based on the current WordPress query/view.
     *
     * Checks various conditional tags (is_singular, is_tag, is_front_page, etc.)
     * to retrieve the most relevant description from post meta, term descriptions,
     * plugin options, or the site tagline as a fallback.
     *
     * @return string The determined meta description, or an empty string if none is applicable.
     */
    private function get_current_page_description(): string
    {
        if (is_paged()) {
            return '';
        }

        $description = '';

        if (is_singular()) {
            $post_id = get_queried_object_id();
            if ($post_id) {
                $description = (string) get_post_meta($post_id, $this->meta_key, true);
            }
        } elseif (is_tag() || is_category() || is_tax()) {
            $description = term_description();
        } elseif (is_front_page()) {
            $description = $this->get_front_page_description();
        } elseif (is_home()) {
            $description = $this->get_blog_home_description();
        }

        return (string) apply_filters('mso_ai_meta_description_output', $description);
    }

    /**
     * Gets the meta description specifically for the front page.
     * Handles both 'page' and 'posts' settings for 'show_on_front'.
     *
     * @return string The front page description.
     * @private
     */
    private function get_front_page_description(): string
    {
        $description = '';

        if ('page' === $this->show_on_front) {
            $page_id = (int) get_option('page_on_front');
            if ($page_id) {
                $description = (string) get_post_meta($page_id, $this->meta_key, true);
            }
        } else {
            $description = (string) get_option(MSO_AI_Meta_Description::OPTION_PREFIX . 'front_page');
        }

        if (empty($description)) {
            $description = get_bloginfo('description', 'display');
        }

        return $description;
    }

    /**
     * Gets the meta description specifically for the blog posts index page (when is_home() is true).
     * This is relevant only when 'show_on_front' is set to 'page'.
     *
     * @return string The blog home page description.
     * @private
     */
    private function get_blog_home_description(): string
    {
        $description = '';

        if ('page' === $this->show_on_front) {
            $page_id = (int) get_option('page_for_posts');
            if ($page_id) {
                $description = (string) get_post_meta($page_id, $this->meta_key, true);

                if (empty($description)) {
                    $description = get_bloginfo('description', 'display');
                }
            }
        }

        return $description;
    }
}
