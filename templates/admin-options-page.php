<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('options_mso_meta_description');
        do_settings_sections('admin_mso_meta_description');
        submit_button(__('Save', 'mso-meta-description'));
        ?>
    </form>
</div>