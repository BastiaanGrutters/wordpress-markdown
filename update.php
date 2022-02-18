<?php
include( '../../../wp-config.php' );
global $wpdb;
$options = MarkDownImport::getOptions();

if( current_user_can( 'activate_plugins' ) || $_GET[ 'key' ] === $options[ 'update_key' ] ) {
	$startTime = microtime( true );
	$importStats = MarkDownImport::updateMDFiles();
	$endTime = microtime( true );
?>
<html>
	<body>
		<p>MD files imported: <?php print( number_format( $importStats[ 'imported' ] ) ); ?></p>
		<p>MD files skipped: <?php print( number_format( $importStats[ 'skipped' ] ) ); ?></p>
		<p>Skipped means the MD file are still recent or the post type is no longer part of the MD Import settings</p>
		<p>Import time: <?php print( number_format( round( ( $endTime - $startTime ) * 1000 ) ) ); ?> ms</p>
	</body>
</html>
<?php
} else {
?>
<html>
	<body>
		<?php _e( 'Access denied', 'markdown-import' ); ?>
	</body>
</html>
<?php
}