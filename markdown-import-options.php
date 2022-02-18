<?php
if (isset($_POST['action'])) {
	$originalOptions = $options = MarkDownImport::getOptions();
	foreach ($_POST['markdown_import_options'] as $key => $newOption) {
		$options[$key] = $newOption;
	}
	update_option('markdown_import_options', $options);
	$options = MarkDownImport::getOptions(true);
} else {
	$options = MarkDownImport::getOptions();
}

$postTypes = get_post_types([], 'objects');
?>
<div class="wrap">
    <div class="icon32" id="icon-options-general"></div>
    <h2>MarkDown Import Options</h2>
    <form method="post" enctype="multipart/form-data">
        <table class="form-table">
			<?php
			foreach ($postTypes as $key => $postType) {
				?>
                <tr>
                    <td colspan="2">
                        <input type="checkbox" id="post-type-<?php print($key); ?>" name="markdown_import_options[post_types][]" value="<?php print($key); ?>"
							<?php print((isset($options['post_types']) && is_array($options['post_types']) && in_array($key, $options['post_types'], true)) ? 'checked ' : ''); ?>/>
                        <label for="post-type-<?php print($key); ?>"><?php _e('Enable for ', 'markdown-import'); ?><?php print(isset($postType->labels, $postType->labels->name) ? $postType->labels->name : $key); ?></label>
                        <p class="description"><?php _e('Select this to enable the import function for this post type', 'markdown-import'); ?></p>
                    </td>
                </tr>
				<?php
			}
			?>
            <tr>
                <td><label for="updater-key">WP CRON</label></td>
                <td>
                    <p>WP CRON hook: <?php print(MarkDownImport::WP_CRON_METHOD); ?></p>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <p class="submit">
                        <input class="button-primary" type="submit" name="action" value="update"/>
                    </p>
                </td>
            </tr>
        </table>
    </form>
</div>
