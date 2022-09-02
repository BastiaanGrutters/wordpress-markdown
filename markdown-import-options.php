<?php
if (isset($_POST['action'])) {
	$originalOptions = $options = MarkDownImport::getOptions();
    $import = false;
    if (($_POST['markdown_import_options']['import_markdown'] ?? null) === 'now') {
		unset($_POST['markdown_import_options']['import_markdown']);
		$import = true;
    }

	foreach ($_POST['markdown_import_options'] as $key => $newOption) {
		$options[$key] = $newOption;
	}
	update_option('markdown_import_options', $options);
	$options = MarkDownImport::getOptions(true);
    $report = MarkDownImport::updateMDFiles();
} else {
	$options = MarkDownImport::getOptions();
}

$postTypes = get_post_types([], 'objects');
?>
<div class="wrap">
    <div class="icon32" id="icon-options-general"></div>
    <h2>MarkDown Import Options</h2>

    <?php if (isset($report)) { ?>
    <div class="notice notice-warning is-dismissible">
        <p>MD files imported: <?php print( number_format( $report[ 'imported' ] ) ); ?></p>
        <p>MD files skipped: <?php print( number_format( $report[ 'skipped' ] ) ); ?></p>
        <p>Skipped means the MD file are still recent or the post type is no longer part of the MD Import settings</p>
    </div>
	<?php } ?>

    <form method="post" enctype="multipart/form-data">
        <table class="form-table">
			<?php
			foreach ($postTypes as $key => $postType) {
				?>
                <tr>
                    <td colspan="2">
                        <input type="checkbox" id="post-type-<?php print($key); ?>" name="markdown_import_options[post_types][]" value="<?php print($key); ?>"
							<?php print((isset($options['post_types']) && is_array($options['post_types']) && in_array($key, $options['post_types'], true)) ? 'checked ' : ''); ?>/>
                        <label for="post-type-<?php print($key); ?>"><?php _e('Enable for ', MarkDownImport::TEXT_DOMAIN); ?><?php print(isset($postType->labels, $postType->labels->name) ? $postType->labels->name : $key); ?></label>
                        <p class="description"><?php _e('Select this to enable the import function for this post type', MarkDownImport::TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
				<?php
			}
			?>
            <tr>
                <td colspan="2">
                    <label>
                        <input type="checkbox" name="markdown_import_options[show_source_link]" <?php print(($options['show_source_link'] ?? null) === 'yes' ? 'checked ' : ''); ?>value="yes" /> <?php _e('Show source link below imported posts.', MarkDownImport::TEXT_DOMAIN); ?>
                    </label>
                    <p class="description"><?php _e('Show a link to the source MarkDown below imported posts or pages.', MarkDownImport::TEXT_DOMAIN); ?></p>
                </td>
            </tr>
            <tr>
                <td><label for="updater-key">WP CRON</label></td>
                <td>
                    <p>WP CRON hook: <?php print(MarkDownImport::WP_CRON_METHOD); ?></p>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <label>
                        <input type="checkbox" name="markdown_import_options[import_markdown]" value="now" /> Import markdown now
                    </label>
                    <p class="description"><?php _e('Select import markdown for the selected post types now.', MarkDownImport::TEXT_DOMAIN); ?></p>
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
