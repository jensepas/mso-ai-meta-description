<?php
/**
 * MSO Meta Description MetaBox
 *
 * Handles the creation, rendering, and saving of the meta description
 * meta box displayed on post edit screens. Allows users to manually
 * enter or generate a meta description using AI.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 */

namespace MSO_Meta_Description;

use WP_Post; // Type hint for WordPress Post object.

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    die;
}

/**
 * Manages the meta box for the meta description.
 */
class MetaBox
{
    /**
     * The meta key used to store the description in post meta.
     * @var string
     */
    private string $meta_key;

    /**
     * The action name for the nonce verification.
     * @var string
     */
    private string $nonce_action;

    /**
     * The name attribute of the nonce field in the form.
     * @var string
     */
    private string $nonce_name;

    /**
     * Constructor.
     *
     * Initializes the meta box handler with necessary keys and identifiers.
     *
     * @param string $meta_key     The key used for storing the meta description in the database.
     * @param string $nonce_action The action string for nonce verification.
     * @param string $nonce_name   The name attribute for the nonce input field.
     */
    public function __construct(string $meta_key, string $nonce_action, string $nonce_name)
    {
        $this->meta_key = $meta_key;
        $this->nonce_action = $nonce_action;
        $this->nonce_name = $nonce_name;
    }

    /**
     * Add the meta description meta box to relevant post types.
     *
     * Hooks into 'add_meta_boxes' action. Registers the meta box
     * for all public post types except attachments.
     */
    public function add_meta_box(): void
    {
        // Get all registered post types that are public (visible in admin UI and frontend).
        $post_types = get_post_types(['public' => true], 'names');
        // Remove 'attachment' post type as a meta description is usually not needed for media files directly.
        unset($post_types['attachment']);

        // Loop through the relevant post types.
        foreach ($post_types as $post_type) {
            // Add the meta box to the edit screen for each post type.
            add_meta_box(
                'mso_meta_description_metabox', // Unique ID for the meta box.
                __('MSO Meta Description', 'mso-meta-description'), // Title displayed in the meta box header.
                [$this, 'render_meta_box_content'], // Callback function to render the meta box HTML.
                $post_type, // The post type screen where the meta box should appear.
                'normal', // Context (where on the screen: 'normal', 'side', 'advanced').
                'high' // Priority (within the context: 'high', 'core', 'default', 'low').
            );
        }
    }

    /**
     * Render the content of the meta description meta box.
     *
     * Outputs the HTML for the textarea, character count, and AI generation buttons.
     *
     * @param WP_Post $post The current post object being edited.
     */
    public function render_meta_box_content(WP_Post $post): void
    {
        // Add a nonce field for security verification when saving.
        wp_nonce_field($this->nonce_action, $this->nonce_name);
        // Get the currently saved meta description value for this post.
        $value = get_post_meta($post->ID, $this->meta_key, true); // 'true' gets a single value.

        // Define variables used in the template (consider making these constants or class properties if used elsewhere).
        $field_name = 'mso_add_description'; // Name attribute for the textarea input. Must match save_meta_data().
        $min_length = MSO_Meta_Description::MIN_DESCRIPTION_LENGTH; // Minimum recommended length.
        $max_length = MSO_Meta_Description::MAX_DESCRIPTION_LENGTH; // Maximum recommended length.
        $option_prefix = MSO_Meta_Description::get_option_prefix(); // Get the plugin's option prefix.

        ?>
        <div class="mso-meta-box-wrapper">
            <p>
                <label for="mso_meta_description_field">
                    <strong><?php esc_html_e('Meta Description', 'mso-meta-description'); ?></strong>
                </label>
            </p>
            <textarea
                    id="mso_meta_description_field" <?php // Unique ID for the textarea, used by label and JavaScript. ?>
                    name="<?php echo esc_attr($field_name); ?>" <?php // Name attribute for form submission. ?>
                    rows="4"
                    class="large-text"
                    maxlength="<?php echo esc_attr($max_length + 20); // Allow some buffer beyond max recommended length for easier editing. ?>"
                    aria-describedby="mso-description-hint" <?php // Accessibility: Links textarea to the description paragraph below. ?>
            ><?php echo esc_textarea($value); /* Output the saved value, properly escaped for a textarea. */ ?></textarea>

            <p class="description" id="mso-description-hint"> <?php // Hint paragraph linked by aria-describedby. ?>
                <?php
                // Display recommended length information.
                printf(
                /* Translators: 1: Minimum recommended characters, 2: Maximum recommended characters */
                    esc_html__('Recommended length: %1$d-%2$d characters.', 'mso-meta-description'),
                    esc_html($min_length),
                    esc_html($max_length)
                );
                ?>
                <?php esc_html_e('Current count:', 'mso-meta-description'); ?>
                <span class="mso-char-count">0</span><?php // Span to display the live character count (updated by JS). ?>
                <span class="mso-length-indicator"></span><?php // Span to display length status (e.g., "Too short", "Good", updated by JS). ?>
            </p>

            <?php
            // Check if API keys are set in the plugin settings for each provider.
            $mistral_key_set = !empty(get_option($option_prefix . 'mistral_api_key'));
            $gemini_key_set = !empty(get_option($option_prefix . 'gemini_api_key'));
            $openai_key_set = !empty(get_option($option_prefix . 'openai_api_key'));
            $anthropic_key_set = !empty(get_option($option_prefix . 'anthropic_api_key'));

            // Only show the AI generator section if at least one API key is configured.
            if ($mistral_key_set || $gemini_key_set || $openai_key_set || $anthropic_key_set) :
                ?>
                <div class="mso-ai-generator">
                    <p><strong><?php esc_html_e('Generate with AI:', 'mso-meta-description'); ?></strong></p>

                    <?php // Conditionally display the button for Mistral if its API key is set. ?>
                    <?php if ($mistral_key_set) : ?>
                        <button type="button" id="summarize-mistral" class="button mso-generate-button"
                                data-provider="mistral" <?php // Data attribute used by JS to identify the provider. ?>>
                            <?php esc_html_e('Generate with Mistral', 'mso-meta-description'); ?>
                        </button>
                    <?php endif; ?>

                    <?php // Conditionally display the button for Gemini if its API key is set. ?>
                    <?php if ($gemini_key_set) : ?>
                        <button type="button" id="summarize-gemini" class="button mso-generate-button"
                                data-provider="gemini">
                            <?php esc_html_e('Generate with Gemini', 'mso-meta-description'); ?>
                        </button>
                    <?php endif; ?>

                    <?php // Conditionally display the button for OpenAI if its API key is set. ?>
                    <?php if ($openai_key_set) : ?>
                        <button type="button" id="summarize-openai" class="button mso-generate-button"
                                data-provider="openai">
                            <?php esc_html_e('Generate with ChatGPT', 'mso-meta-description'); ?>
                        </button>
                    <?php endif; ?>

                    <?php // Conditionally display the button for OpenAI if its API key is set. ?>
                    <?php if ($anthropic_key_set) : ?>
                        <button type="button" id="summarize-anthropic" class="button mso-generate-button"
                                data-provider="anthropic">
                            <?php esc_html_e('Generate with Anthropic', 'mso-meta-description'); ?>
                        </button>
                    <?php endif; ?>

                    <?php // Spinner element shown during AJAX requests (controlled by JS). ?>
                    <span class="spinner mso-spinner"></span> <?php // Inline styles to position spinner correctly and hide initially. ?>
                    <?php // Paragraph to display potential error messages from AJAX requests (controlled by JS). ?>
                    <p id="mso-ai-error" class="mso-ai-error"></p> <?php // Ensure space even when empty. ?>

                </div> <?php // End mso-ai-generator div ?>
            <?php
            endif; // End check for any API key set
            ?>
        </div> <?php // End mso-meta-box-wrapper div ?>

        <?php
    }

    /**
     * Save the meta description when the post is saved.
     *
     * Hooks into 'save_post' action. Performs security checks (nonce, autosave, permissions)
     * before sanitizing and saving the submitted meta description value.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_meta_data(int $post_id): void
    {
        // 1. Verify the nonce sent from the meta box form.
        // Use sanitize_text_field and wp_unslash for security.
        if (!isset($_POST[$this->nonce_name]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$this->nonce_name])), $this->nonce_action)) {
            return; // Nonce is missing or invalid, do not proceed.
        }

        // 2. Check if this is an autosave routine.
        // If it is, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 3. Check if the user has permission to edit the post.
        $post_type = get_post_type($post_id); // Get the post type of the post being saved.
        $post_type_object = get_post_type_object($post_type); // Get the post type object to access capabilities.
        // Check if the current user has the 'edit_post' capability for this specific post ID.
        if (!current_user_can($post_type_object->cap->edit_post, $post_id)) {
            return; // User does not have permission.
        }

        // 4. Check if our specific meta description field was submitted.
        $field_name = 'mso_add_description'; // This MUST match the 'name' attribute of the textarea.
        if (!isset($_POST[$field_name])) {
            // Field not submitted (e.g., maybe meta box was conditionally hidden).
            // Depending on requirements, you might want to delete existing meta here,
            // but returning is safer if the field might legitimately not be present.
            return;
        }

        // 5. Sanitize and save the submitted data.
        // Use sanitize_text_field for plain text content. wp_unslash removes slashes added by WP.
        $new_value = sanitize_text_field(wp_unslash($_POST[$field_name]));

        // 6. Update or delete the post meta.
        if (empty($new_value)) {
            // If the submitted value is empty, delete the meta key from the database.
            delete_post_meta($post_id, $this->meta_key);
        } else {
            // If the submitted value is not empty, update the meta key with the new sanitized value.
            // update_post_meta() handles both adding and updating.
            update_post_meta($post_id, $this->meta_key, $new_value);
        }
    }
}