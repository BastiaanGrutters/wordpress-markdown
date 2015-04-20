<?php
if( isset( $_POST[ 'action' ] ) ) {
	$originalOptions = $options = MarkDownImport::getOptions();
	foreach( $_POST[ 'markdown_import_options' ] as $key => $newOption ) {
		$options[$key] = $newOption;
	}
	update_option( 'markdown_import_options', $options );
	$options = MarkDownImport::getOptions( true );
} else {
	$options = MarkDownImport::getOptions();
}

$postTypes = get_post_types( Array(), 'objects' );
/*$pages = get_posts( Array(
		'post_type' => 'page',
		'posts_per_page' => -1
) );*/
?>
<div class="wrap">
	<div class="icon32" id="icon-options-general"></div>
	<h2>MarkDown Import Options</h2>
	<form method="post" enctype="multipart/form-data">
		<table class="form-table">
<?php 
foreach( $postTypes as $key => $postType ) {
?>		
			<tr valign="top">
				<td colspan="2">
					<input type="checkbox" id="post-type-<?php print( $key ); ?>" name="markdown_import_options[post_types][]" 
						value="<?php print( $key ); ?>" 
						<?php print( ( isset( $options[ 'post_types' ] ) && is_array( $options[ 'post_types' ] ) && in_array( $key, $options[ 'post_types' ] ) ) ? 'checked ' : '' ); ?>/>
					<label for="post-type-<?php print( $key ); ?>"><?php _e( 'Enable for ', 'markdown-import' ); ?> <?php print( isset( $postType->labels, $postType->labels->name ) ? $postType->labels->name : $key ); ?></label>
					<p class="description"><?php _e( 'Select this to enable the import function for this post type', 'markdown-import' ); ?></p>
				</td>
			</tr>
<?php 
}
?>			
			<tr valign="top">
				<td><label for="updater-key">Update key</label></td>
				<td>
					<input type="text" name="markdown_import_options[update_key]" id="updater-key" value="<?php print( isset( $options[ 'update_key' ] ) ? $options[ 'update_key' ] : md5( rand() . time() ) ); ?>" />
					<p class="description">
						<?php _e( 'This key is used to prevent just anyone from calling the updater', 'markdown-import' ); ?><br />
						CRON URI: <?php print( plugins_url( 'update.php', __FILE__ ) ); ?>?key=<?php print( isset( $options[ 'update_key' ] ) ? $options[ 'update_key' ] : '' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<p class="submit">
						<input class="button-primary" type="submit" name="action" value="update" />
					</p>
				</td>
			</tr>
		</table>
	</form>
</div>
