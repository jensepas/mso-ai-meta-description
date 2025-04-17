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

// Exit if accessed directly.
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
     * @var string
     */
    private string $meta_key;

    /**
     * Stores the WordPress setting for what to display on the front page ('posts' or 'page').
     * @var string
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
        // Get the WordPress setting for what is displayed on the front page.
        $this->show_on_front = (string)get_option('show_on_front');
    }

    /**
     * Registers the WordPress hook for outputting the meta description.
     */
    public function register_hooks(): void
    {
        // Hook into 'wp_head' to output the meta tag.
        // Priority 1 ensures it runs early, allowing manipulation of other head elements like canonical.
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
        // Temporarily remove the default WordPress canonical tag action.
        // This is sometimes done to control the exact output order in the <head>,
        // ensuring the description tag appears before or after the canonical tag if desired.
        // In this case, it seems intended to ensure our description is output before the canonical tag is re-added.
        remove_action('wp_head', 'rel_canonical');

        // Get the meta description string based on the current page context.
        $description = $this->get_current_page_description();

        // Only output the meta tag if a description was found.
        if (! empty($description)) {
            // Print the meta description tag, ensuring the content is properly escaped.
            printf(
                "\n\n<meta name=\"description\" content=\"%s\">\n\n",
                esc_attr(trim($description)) // Trim whitespace and escape the attribute value.
            );
        }

        // Re-add the default WordPress canonical tag action.
        // This ensures that the canonical tag functionality is restored after our meta tag is output.
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
        // Don't output a description on paginated archive pages (e.g., /page/2/ of a category).
        // Search engines generally prefer to index the first page of archives.
        if (is_paged()) {
            return '';
        }

        $description = ''; // Initialize description variable.

        // Check if the current view is a single post, page, or custom post type item.
        if (is_singular()) {
            // Get the ID of the current post object being displayed.
            $post_id = get_queried_object_id();
            if ($post_id) {
                // Retrieve the custom meta description stored for this post.
                $description = get_post_meta($post_id, $this->meta_key, true); // 'true' returns a single value.
            }
        }
        // Check if the current view is a tag, category, or custom taxonomy archive page.
        elseif (is_tag() || is_category() || is_tax()) {
            // Use the description set for the term in the WordPress admin.
            $description = term_description(); // WordPress function to get the term description.
            // Note: term_description() often includes HTML filtering.
        }
        // Check if the current view is the site's front page.
        elseif (is_front_page()) {
            // Check if the front page displays a static page.
            if ('page' === $this->show_on_front) {
                // Get the ID of the page designated as the static front page.
                $post_id = (int) get_option('page_on_front');
                if ($post_id) {
                    // Retrieve the custom meta description stored for the static front page.
                    $description = get_post_meta($post_id, $this->meta_key, true);
                }
                // If no custom meta description is set for the static front page, use the site tagline as a fallback.
                if (empty($description) && $post_id) {
                    $description = get_bloginfo('description', 'display'); // Get site tagline/description.
                }
            }
            // Check if the front page displays the latest posts.
            else { // 'posts' === $this->show_on_front (or default behavior)
                // Retrieve the custom front page description saved in the plugin's settings (Reading settings page).
                $description = get_option(MSO_AI_Meta_Description::OPTION_PREFIX . 'front_page');
                // If no custom description is set in options, use the site tagline as a fallback.
                if (empty($description)) {
                    $description = get_bloginfo('description', 'display');
                }
            }
        }
        // Check if the current view is the blog posts index page (can also be the front page if 'show_on_front' is 'posts').
        elseif (is_home()) {
            // This condition specifically targets the page designated as the "Posts page"
            // *only* when a static front page is also set.
            if ('page' === $this->show_on_front) {
                // Get the ID of the page designated as the posts page.
                $post_id = (int) get_option('page_for_posts');
                if ($post_id) {
                    // Retrieve the custom meta description stored for the posts page.
                    $description = get_post_meta($post_id, $this->meta_key, true);
                }
                // If no custom meta description is set for the posts page, use the site tagline as a fallback.
                if (empty($description) && $post_id) {
                    $description = get_bloginfo('description', 'display');
                }
            }
        }

        // Allow other plugins or themes to filter the final description before output.
        return apply_filters('mso_ai_meta_description_output', $description);
    }
}
