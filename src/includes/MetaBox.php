<?php
/**
 * MSO AI Meta Description MetaBox
 *
 * Handles the creation, rendering, and saving of the meta description
 * meta box displayed on post edit screens. Allows users to manually
 * enter or generate a meta description using AI.
 * Dynamically displays generation buttons based on configured providers.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.4.0
 */

namespace MSO_AI_Meta_Description;

// Import necessary classes
use MSO_AI_Meta_Description\Providers\ProviderInterface;
use WP_Post;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
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
     * Providers.
     *
     * @var array<ProviderInterface>
     */
    private array $providers;

    /**
     * Constructor.
     *
     * Initializes the meta box handler with necessary keys and identifiers.
     *
     * @param string $meta_key The key used for storing the meta description in the database.
     * @param string $nonce_action The action string for nonce verification.
     * @param string $nonce_name The name attribute for the nonce input field.
     * @param array<ProviderInterface> $providers List all provider.
     */
    public function __construct(string $meta_key, string $nonce_action, string $nonce_name, array $providers)
    {
        $this->meta_key = $meta_key;
        $this->nonce_action = $nonce_action;
        $this->nonce_name = $nonce_name;
        $this->providers = $providers;
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
        $post_types = get_post_types(['public' => true]);
        // Remove 'attachment' post type as a meta description is usually not needed for media files directly.
        unset($post_types['attachment']);

        // Loop through the relevant post types.
        foreach ($post_types as $post_type) {
            // Add the meta box to the edit screen for each post type.
            add_meta_box(
                'mso_ai_meta_description_meta_box', // Unique ID for the meta box.
                __('MSO AI Meta Description', 'mso-ai-meta-description'), // Title displayed in the meta box header.
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
     * Dynamically determines which AI buttons to show based on configured providers.
     *
     * @param WP_Post $post The current post object being edited.
     */
    public function render_meta_box_content(WP_Post $post): void
    {
        // Add a nonce field for security verification when saving.
        wp_nonce_field($this->nonce_action, $this->nonce_name);
        // Get the currently saved meta description value for this post.
        $value = (string)get_post_meta($post->ID, $this->meta_key, true); // 'true' gets a single value.

        // Define variables used in the template
        $field_name = 'mso_ai_add_description'; // Name attribute for the textarea input. Must match save_meta_data().
        $min_length = (string)MSO_AI_Meta_Description::MIN_DESCRIPTION_LENGTH; // Minimum recommended length.
        $max_length = (string)MSO_AI_Meta_Description::MAX_DESCRIPTION_LENGTH; // Maximum recommended length.
        $option_prefix = MSO_AI_Meta_Description::get_option_prefix(); // Get the plugin's option prefix.

        ?>
        <div class="mso-ai-meta-box-wrapper">
            <p>
                <label for="mso_ai_meta_description_field">
                    <strong><?php esc_html_e('Meta Description', 'mso-ai-meta-description'); ?></strong>
                </label>
            </p>
            <textarea
                    id="mso_ai_meta_description_field"
                    name="<?php echo esc_attr($field_name); ?>"
                    rows="4"
                    class="large-text"
                    maxlength="<?php echo esc_attr($max_length); ?>"
                    aria-describedby="mso-ai-description-hint"
            ><?php echo esc_textarea($value); ?></textarea>

            <p class="description" id="mso-ai_description-hint"> <?php // Hint paragraph linked by aria-describedby.
                ?>
                <?php
                // Display recommended length information.
                printf(
                    /* Translators: 1: Minimum recommended characters, 2: Maximum recommended characters */
                    esc_html__('Recommended length: %1$d-%2$d characters.', 'mso-ai-meta-description'),
                    esc_html($min_length),
                    esc_html($max_length)
                );
        ?>
                <?php esc_html_e('Current count:', 'mso-ai-meta-description'); ?>
                <span class="mso-ai-char-count">0</span>
                <span class="mso-ai-length-indicator"></span>
            </p>

            <?php
            $configured_providers = []; // Store providers with API keys set
        foreach ($this->providers as $provider) {
            $provider_name = $provider->get_name();
            $api_key_option = $option_prefix . $provider_name . '_api_key';
            if (! empty(get_option($api_key_option))) {
                $configured_providers[$provider_name] = $provider; // Add to configured list
            }
        }
        // --- End dynamic check ---

        // Only show the AI generator section if at least one provider is configured.
        if (! empty($configured_providers)) :
            ?>
                <div class="mso-ai-generator">
                    <p><strong><?php esc_html_e('Generate with AI:', 'mso-ai-meta-description'); ?></strong></p>

                    <?php
                // --- Dynamically render buttons ---
                foreach ($configured_providers as $provider_name => $provider) :
                    // Special label for OpenAI
                    $provider_title = $provider->get_title();
                    $button_label = sprintf(
                        /* translators: %s: Provider title  */
                        __('Generate with %s', 'mso-ai-meta-description'),
                        ucfirst($provider_title) // Capitalize the provider name
                    );
                    ?>
                        <button type="button" id="summarize-<?php echo esc_attr($provider_name); ?>"
                                class="button mso-ai-generate-button"
                                data-provider="<?php echo esc_attr($provider_name); ?>">
                            <?php echo esc_html($button_label); ?>
                        </button>
                    <?php endforeach; ?>
                    <?php // --- End dynamic rendering ---?>

                    <span class="spinner mso-ai-spinner"></span>
                    <p id="mso-ai-error" class="mso-ai-error mso-ai-model-error"></p>

                </div>
            <?php
        endif; // End check for configured providers
        ?>
        </div>
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
        // Use sanitize_text_field for security.
        if (! isset($_POST[$this->nonce_name]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$this->nonce_name])), $this->nonce_action)) {
            return; // Nonce is missing or invalid, do not proceed.
        }

        // 2. Check if this is an autosave routine.
        // If it is, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 3. Check if the user has permission to edit the post.
        $post_type = get_post_type($post_id); // Get the post type of the post being saved.

        if (! $post_type) {
            // Could not determine post type, likely invalid post ID.
            return;
        }

        $post_type_object = get_post_type_object($post_type);
        // Check if the current user has the 'edit_post' capability for this specific post ID.
        if (! $post_type_object || ! current_user_can($post_type_object->cap->edit_post, $post_id)) {
            return;
        }

        // 4. Check if our specific meta description field was submitted.
        $field_name = 'mso_ai_add_description'; // This MUST match the 'name' attribute of the textarea.
        if (! isset($_POST[$field_name])) {
            // Field not submitted (e.g., maybe meta box was conditionally hidden).
            return;
        }

        // 5. Sanitize and save the submitted data.
        // Use sanitize_text_field for plain text content. removes slashes added by WP.
        $new_value = sanitize_text_field(wp_unslash($_POST[$field_name]));

        // 6. Update or delete the post meta.
        if (empty($new_value)) {
            // If the submitted value is empty, delete the meta key from the database.
            delete_post_meta($post_id, $this->meta_key);
        } else {
            // If the submitted value is not empty, update the meta key with the new sanitized value.
            update_post_meta($post_id, $this->meta_key, $new_value);
        }
    }
}
