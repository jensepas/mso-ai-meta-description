<?php
namespace MSO_Meta_Description;

if (!defined('ABSPATH')) {
    die;
}

class Frontend
{
    private string $meta_key;
    private string $show_on_front;

    public function __construct(string $meta_key)
    {
        $this->meta_key = $meta_key;
        $this->show_on_front = get_option('show_on_front'); // static, page, posts
    }

    public function register_hooks(): void
    {
        // Hook slightly earlier and remove/re-add canonical tag correctly
        add_action('wp_head', [$this, 'output_meta_description'], 1);
    }

    /**
     * Insert the meta description into the head section of the website.
     */
    public function output_meta_description(): void
    {
        // Remove default canonical tag to ensure ours is placed correctly relative to description
        remove_action('wp_head', 'rel_canonical');

        $description = $this->get_current_page_description();

        if (!empty($description)) {
            printf(
                "\n\n<meta name=\"description\" content=\"%s\">\n\n",
                esc_attr(trim($description))
            );
        }

        // Re-add canonical tag
        add_action('wp_head', 'rel_canonical');
        // Explicitly call it if needed immediately (usually wp_head continues execution)
        // rel_canonical();
    }

    /**
     * Determine the correct meta description for the current view.
     *
     * @return string The meta description.
     */
    private function get_current_page_description(): string
    {
        if (is_paged()) {
            return ''; // Don't output on paginated archives
        }

        $description = '';
        $post_id = 0;

        if (is_singular()) { // Covers is_single(), is_page(), and CPT singles
            $post_id = get_queried_object_id();
            if ($post_id) {
                $description = get_post_meta($post_id, $this->meta_key, true);
            }
        } elseif (is_tag() || is_category() || is_tax()) {
            $description = term_description(); // Already stripped/escaped by WP usually
        } elseif (is_front_page()) {
            if ('page' === $this->show_on_front) {
                $post_id = (int) get_option('page_on_front');
                if ($post_id) {
                    $description = get_post_meta($post_id, $this->meta_key, true);
                }
                // Fallback to site tagline if meta is empty AND page_on_front is set
                if (empty($description) && $post_id) {
                    $description = get_bloginfo('description', 'display');
                }
            } else { // 'posts' === $this->show_on_front (or default)
                $description = get_option(MSO_Meta_Description::OPTION_PREFIX . 'front_page');
                // Fallback to site tagline if option is empty
                if (empty($description)) {
                    $description = get_bloginfo('description', 'display');
                }
            }
        } elseif (is_home()) { // Blog posts index page (could be front page if show_on_front is 'posts')
            if ('page' === $this->show_on_front) { // Only if a static page is set for posts
                $post_id = (int) get_option('page_for_posts');
                if ($post_id) {
                    $description = get_post_meta($post_id, $this->meta_key, true);
                }
                // Fallback to site tagline if meta is empty AND page_for_posts is set
                if (empty($description) && $post_id) {
                    $description = get_bloginfo('description', 'display');
                }
            }
            // If is_home() && is_front_page(), the 'is_front_page' logic above handles it.
        }
        // Potentially add more conditions: is_author(), is_search(), is_404() etc.

        return apply_filters('mso_meta_description_output', $description);
    }
}