<?php
namespace MSO_Meta_Description;

if (!defined('ABSPATH')) {
    die;
}

class MetaBox
{
    private string $meta_key;
    private string $nonce_action;
    private string $nonce_name;

    public function __construct(string $meta_key, string $nonce_action, string $nonce_name)
    {
        $this->meta_key = $meta_key;
        $this->nonce_action = $nonce_action;
        $this->nonce_name = $nonce_name;
    }

    /**
     * Add the meta description meta box to relevant post types.
     */
    public function add_meta_box(): void
    {
        $post_types = get_post_types(['public' => true], 'names');
        // Maybe exclude attachments if not desired
        unset($post_types['attachment']);

        foreach ($post_types as $post_type) {
            add_meta_box(
                'mso_meta_description_metabox', // Unique ID
                __('MSO Meta Description', MSO_Meta_Description::TEXT_DOMAIN), // Title
                [$this, 'render_meta_box_content'], // Callback
                $post_type, // Screen
                'normal', // Context
                'high' // Priority
            );
        }
    }

    /**
     * Render the content of the meta description meta box.
     *
     * @param \WP_Post $post The current post object.
     */
    public function render_meta_box_content(\WP_Post $post): void
    {
        wp_nonce_field($this->nonce_action, $this->nonce_name);
        $value = get_post_meta($post->ID, $this->meta_key, true);

        // Use TemplateRenderer - assumes 'meta-box-description.php' exists in a templates directory
        TemplateRenderer::render('meta-box-description', [
            'value' => $value,
            'meta_key' => $this->meta_key, // Pass meta key name if needed in template
            'field_name' => 'mso_add_description', // Keep consistent with save logic
            'min_length' => MSO_Meta_Description::MIN_DESCRIPTION_LENGTH,
            'max_length' => MSO_Meta_Description::MAX_DESCRIPTION_LENGTH,
        ]);
    }

    /**
     * Save the meta description when the post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_meta_data(int $post_id): void
    {
        // Check nonce
        if (!isset($_POST[$this->nonce_name]) || !wp_verify_nonce(sanitize_text_field($_POST[$this->nonce_name]), $this->nonce_action)) {
            return;
        }

        // Check for autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        $post_type = get_post_type($post_id);
        $post_type_object = get_post_type_object($post_type);
        if (!current_user_can($post_type_object->cap->edit_post, $post_id)) {
            return;
        }

        // Check if our field is set
        $field_name = 'mso_add_description'; // Must match the input field name in the template
        if (!isset($_POST[$field_name])) {
            return;
        }

        // Sanitize and save the data
        $new_value = sanitize_text_field($_POST[$field_name]);

        if (empty($new_value)) {
            delete_post_meta($post_id, $this->meta_key);
        } else {
            update_post_meta($post_id, $this->meta_key, $new_value);
        }
    }
}