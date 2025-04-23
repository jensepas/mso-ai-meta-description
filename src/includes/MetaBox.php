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

use MSO_AI_Meta_Description\Providers\ProviderInterface;
use WP_Post;

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
     */
    private string $meta_key;

    /**
     * The action name for the nonce verification.
     */
    private string $nonce_action;

    /**
     * The name attribute of the nonce field in the form.
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
        $post_types = get_post_types(['public' => true]);
        unset($post_types['attachment']);

        foreach ($post_types as $post_type) {
            add_meta_box(
                'mso_ai_meta_description_meta_box',
                __('MSO AI Meta Description', 'mso-ai-meta-description'),
                [$this, 'render_meta_box_content'],
                $post_type,
                'normal',
                'high'
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
        wp_nonce_field($this->nonce_action, $this->nonce_name);

        $value = (string)get_post_meta($post->ID, $this->meta_key, true);
        $field_name = 'mso_ai_add_description';
        $min_length = MSO_AI_Meta_Description::MIN_DESCRIPTION_LENGTH;
        $max_length = MSO_AI_Meta_Description::MAX_DESCRIPTION_LENGTH;
        $option_prefix = MSO_AI_Meta_Description::get_option_prefix();
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
                    maxlength="<?php echo esc_attr((string)($max_length + 50)); ?>"
                    aria-describedby="mso-ai-description-hint"
            ><?php echo esc_textarea($value); ?></textarea>
            <p class="description" id="mso-ai_description-hint"> <?php

                printf(
                    /* Translators: 1: Minimum recommended characters, 2: Maximum recommended characters */
                    esc_html__('Recommended length: %1$d-%2$d characters.', 'mso-ai-meta-description'),
                    esc_html((string)$min_length),
                    esc_html((string)$max_length)
                );
        echo ' ';
        esc_html_e('Current count:', 'mso-ai-meta-description'); ?>
                <span class="mso-ai-char-count">0</span>
                <span class="mso-ai-length-indicator"></span>
            </p>
            <?php
        $configured_providers = [];
        foreach ($this->providers as $provider) {
            $provider_name = $provider->get_name();
            $api_key_option = $option_prefix . $provider_name . '_api_key';
            $enable_option_name = $option_prefix . $provider_name . '_provider_enabled';
            if (! empty(get_option($api_key_option)) && get_option($enable_option_name, false)) {
                $configured_providers[$provider_name] = $provider;
            }
        }

        if (! empty($configured_providers)) :
            ?>
                <div class="mso-ai-generator">
                    <p><strong><?php esc_html_e('Generate with AI:', 'mso-ai-meta-description'); ?></strong></p>
                    <?php
                    foreach ($configured_providers as $provider_name => $provider) :
                        $provider_title = $provider->get_title();
                        $button_label = sprintf(
                            /* translators: %s: Provider title  */
                            __('Generate with %s', 'mso-ai-meta-description'),
                            ucfirst($provider_title)
                        );
                        ?>
                        <button type="button" id="summarize-<?php echo esc_attr($provider_name); ?>"
                                class="button mso-ai-generate-button"
                                data-provider="<?php echo esc_attr($provider_name); ?>">
                            <?php echo esc_html($button_label); ?>
                        </button>
                    <?php endforeach; ?>
                    <span class="spinner mso-ai-spinner"></span>
                    <p id="mso-ai-error" class="mso-ai-error mso-ai-model-error"></p>
                </div>
            <?php
        endif;
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
        $field_name = 'mso_ai_add_description';

        if (! isset($_POST[$this->nonce_name]) ||
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$this->nonce_name])), $this->nonce_action) ||

            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||

            ! ($post_type = get_post_type($post_id)) ||
            ! ($post_type_object = get_post_type_object($post_type)) ||

            ! current_user_can($post_type_object->cap->edit_post, $post_id) ||

            ! isset($_POST[$field_name])
        ) {
            return;
        }

        $new_value = sanitize_text_field(wp_unslash($_POST[$field_name]));

        if (empty($new_value)) {
            delete_post_meta($post_id, $this->meta_key);
        } else {
            update_post_meta($post_id, $this->meta_key, $new_value);
        }
    }
}
