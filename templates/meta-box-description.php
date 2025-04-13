<?php
/**
 * Template for the MSO Meta Description meta box.
 *
 * @package MSO_Meta_Description
 * @since   1.3.0
 *
 * Available variables from MetaBox::render_meta_box_content():
 * @var string $value      The current meta description value.
 * @var string $field_name The name attribute for the input field (e.g., 'mso_add_description').
 * @var int    $min_length Recommended minimum length.
 * @var int    $max_length Recommended maximum length.
 */

// Ensure the namespace is available if accessing constants directly
use MSO_Meta_Description\MSO_Meta_Description;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    die;
}

// Set default values for variables passed from the MetaBox class
$value = $value ?? '';
$field_name = $field_name ?? 'mso_add_description'; // Should match the name used in MetaBox::save_meta_data
$min_length = $min_length ?? MSO_Meta_Description::MIN_DESCRIPTION_LENGTH;
$max_length = $max_length ?? MSO_Meta_Description::MAX_DESCRIPTION_LENGTH;
$option_prefix = MSO_Meta_Description::get_option_prefix(); // Get option prefix from main class

?>
<div class="mso-meta-box-wrapper">
    <p>
        <label for="mso_meta_description_field">
            <strong><?php esc_html_e('Meta Description', 'mso-meta-description'); ?></strong>
        </label>
    </p>
    <textarea
            id="mso_meta_description_field" <?php // <-- MODIFIED ID to match JS ?>
            name="<?php echo esc_attr($field_name); ?>"
            rows="4"
            style="width: 100%;"
            maxlength="<?php echo esc_attr($max_length + 20); // Allow some buffer for editing ?>"
            aria-describedby="mso-description-hint" <?php // Added for accessibility ?>
    ><?php echo esc_textarea($value); ?></textarea>

    <p class="description" id="mso-description-hint"> <?php // Added ID for aria-describedby ?>
        <?php
        printf(
            /* Translators: 1: Minimum recommended characters, 2: Maximum recommended characters */
            esc_html__('Recommended length: %1$d-%2$d characters.', 'mso-meta-description'),
            esc_html($min_length),
            esc_html($max_length)
        );
        ?>
        <?php esc_html_e('Current count:', 'mso-meta-description'); ?>
        <span class="mso-char-count">0</span><?php // <-- MODIFIED Span structure (Number) ?>
        <span class="mso-length-indicator" style="font-weight: bold; margin-left: 5px;"></span><?php // <-- ADDED Span structure (Indicator text) ?>
    </p>

    <?php
    // Check if any API key is set to decide whether to show the generator section header/container
    $mistral_key_set = !empty(get_option($option_prefix . 'mistral_api_key'));
    $gemini_key_set = !empty(get_option($option_prefix . 'gemini_api_key'));
    $openai_key_set = !empty(get_option($option_prefix . 'openai_api_key'));

    // Only show the generator section if at least one key is set
    if ($mistral_key_set || $gemini_key_set || $openai_key_set) :
    ?>
        <div class="mso-ai-generator" style="margin-top: 15px;">
            <p><strong><?php esc_html_e('Generate with AI:', 'mso-meta-description'); ?></strong></p>

            <?php if ($mistral_key_set) : // <-- CORRECTED condition for Mistral ?>
                <button type="button" id="summarize-mistral" class="button mso-generate-button" data-provider="mistral">
                    <?php esc_html_e('Generate with Mistral', 'mso-meta-description'); ?>
                </button>
            <?php endif; ?>

            <?php if ($gemini_key_set) : ?>
                <button type="button" id="summarize-gemini" class="button mso-generate-button" data-provider="gemini">
                    <?php esc_html_e('Generate with Gemini', 'mso-meta-description'); ?>
                </button>
            <?php endif; ?>

            <?php if ($openai_key_set) : ?>
                <button type="button" id="summarize-openai"  class="button mso-generate-button" data-provider="openai">
                     <?php esc_html_e('Generate with ChatGPT', 'mso-meta-description'); ?>
                </button>
            <?php endif; ?>

            <?php // Spinner and Error message should be inside the conditional container ?>
            <span class="spinner" style="float: none; vertical-align: middle; margin-left: 5px; visibility: hidden;"></span> <?php // Hide spinner initially ?>
            <p id="mso-ai-error" style="color: red; margin-top: 5px; min-height: 1em;"></p> <?php // Ensure space for error message ?>

        </div> <?php // <-- CORRECTED placement of closing div ?>
    <?php
    endif; // End check for any API key set
    ?>
</div> <?php // Close mso-meta-box-wrapper ?>