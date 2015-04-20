<?php
/*
Plugin Name: Markdown Import
Plugin URI: http://www.bastiaangrutters.nl
Description: Allows setting MarkDown file URL per post and importing/parsing it regularly into the post content
Version: 1.0
Author: Bastiaan Grutters
Author URI:
*/

//include( plugin_dir_path( __FILE__ ) . 'widgets.php' );

class MarkDownImport {
	private $options;
	private static $updateInterval = 86400;

	public function __construct() {
		$this->options = get_option( 'markdown_import_options', Array() );
		// Add options menu page to menu
		add_action( 'admin_menu', Array( 'MarkDownImport', 'addOptionsMenu' ) );
		add_action( 'admin_init', Array( 'MarkDownImport', 'editorInit' ) );
		
		// Install PSR-0-compatible class autoloader for WP
		spl_autoload_register( Array( 'MarkDownImport', 'autoload' ) );
	}
	
	public static function autoload( $className ) {
		$className = ltrim($className, '\\');
		$fileName  = '';
		$namespace = '';
		if ($lastNsPos = strrpos($className, '\\')) {
			$namespace = substr($className, 0, $lastNsPos);
			$className = substr($className, $lastNsPos + 1);
			$fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
		}
		$fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
		if ( file_exists ( plugin_dir_path( __FILE__ ) . $fileName ) ){
			include( plugin_dir_path( __FILE__ ) . $fileName );
		}
	}
	
	public static function editorInit() {
		$options = MarkDownImport::getOptions();
		foreach( $options[ 'post_types' ] as $postType ) {
			add_meta_box( 'markdown-import-meta', __( 'MarkDown import', 'markdown-import' ), Array( 'MarkDownImport', 'markdownImportWidget' ), $postType, 'normal', 'low' );
		}
		add_action( 'save_post', Array( 'MarkDownImport', 'saveMarkDownImportWidget' ) );
	}
	
	public static function saveMarkDownImportWidget( $postId ) {
		if( current_user_can( 'edit_post' ) && isset( $_POST[ '_markdown_import_url' ] ) ) {
			update_post_meta( $postId, '_markdown_import', $_POST[ '_markdown_import_url' ] );
			if( isset( $_POST[ 'markdown_import_clear_timestamp' ] ) && $postId == $_POST[ 'markdown_import_clear_timestamp' ] ) {
				delete_post_meta( $postId, '_markdown_import_timestamp' );
			}
		}
	}
	
	public static function markdownImportWidget() {
		global $post;
		$value = get_post_meta( $post->ID, '_markdown_import', true );
?>
	<div id="markdown-import-container">
		<div class="block">
			<label for="markdown-url"><?php _e( 'MarkDown URL', 'markdown-import' ); ?></label> 
			<input type="text" id="markdown-url" name="_markdown_import_url" value="<?php print( $value ); ?>" /><br />
			<br />
			<?php _e( 'Note: This will overwrite the post content with the contents of the MD file changed to HTML', 'markdown-import' ); ?><br />
<?php 
		if( $value != '' ) {
			$lastUpdate = get_post_meta( $post->ID, '_markdown_import_timestamp', true );
			if( $lastUpdate != '' && is_numeric( $lastUpdate ) ) {
				$lastUpdate = time() - $lastUpdate;
				$text = intval( $lastUpdate / 3600 );
				if( $text == 0 ) {
					$text = intval( $lastUpdate % 3600 / 60 ) . ' ' . __( 'minutes', 'markdown-import' ) . ' ' . __( 'ago', 'markdown-import' );
				} else {
					$text .= ' ' . __( 'hours', 'markdown-import' ) . ' ' . __( 'and', 'markdown-import' ) . ' '; 
					$text .= intval( $lastUpdate % 3600 / 60 ) . ' ' . __( 'minutes', 'markdown-import' ) . ' ' . __( 'ago', 'markdown-import' );
				}
				
				$text .= '<br /><input type="checkbox" id="markdown-import-clear-timestamp" name="markdown_import_clear_timestamp" value="' . $post->ID . '" /> <label for="markdown-import-clear-timestamp">' . __( 'Force a new import', 'markdown-import' ) . '</label>';
			} else {
				$text = __( 'never', 'markdown-import' );
			}
?>
			<br />
			<?php _e( 'Most recent import performed', 'markdown-import' ); ?>: <?php print( $text ); ?>
<?php
		}
?>			
		</div>
	</div>
<?php
	}
	
	public static function updateMDFiles() {
		global $wpdb;
		$results = Array(
 			'imported' => 0,
			'skipped' => 0
		);
		$options = MarkDownImport::getOptions();
		$mdFiles = $wpdb->get_results( "SELECT post_id, meta_value, post_type
				FROM $wpdb->postmeta
				JOIN $wpdb->posts ON ID = post_id
				WHERE meta_key = '_markdown_import'" );
		$currentTime = time();
		foreach( $mdFiles as $mdFile ) {
			if( in_array( $mdFile->post_type, $options[ 'post_types' ] ) && $mdFile->meta_value != '' ) {
				$lastUpdate = get_post_meta( $mdFile->post_id, '_markdown_import_timestamp', true );
				// Check if this MD file is out of date, if so we update it
				if( $lastUpdate == '' || $lastUpdate + MarkDownImport::$updateInterval <= $currentTime ) {
					// load the file and parse it
					$text = file_get_contents( $mdFile->meta_value );
					$html = \Michelf\Markdown::defaultTransform( $text );
					$postData = Array(
						'ID' => $mdFile->post_id,
						'post_content' => $html
					);
					wp_update_post( $postData );
					update_post_meta( $mdFile->post_id, '_markdown_import_timestamp', time() );
					$results[ 'imported' ] ++;
				} else {
					$results[ 'skipped' ] ++;
				}
			} elseif( $mdFile->meta_value != '' ) {
				$results[ 'skipped' ] ++;
			}
		}
		return $results;
	}
	
	public static function addOptionsMenu() {
		add_options_page( 'MarkDown Import Options', 'MarkDown Import Options',
				'activate_plugins', 'markdown_import_options',
				Array( 'MarkDownImport', 'showOptionsPage' ) );
	}

	public static function showOptionsPage() {
		include( plugin_dir_path( __FILE__ ) . 'markdown-import-options.php' );
	}

	public static function getOptions( $forceReload = false ) {
		global $markDownImport;
		if( $forceReload ) {
			$markDownImport->options = get_option( 'markdown_import_options', Array() );
		}
		return $markDownImport->options;
	}

	public static function wordpressInit() {
	}

	public static function wpEnqueueScripts() {
		wp_enqueue_style( 'markdown-import-style', plugins_url( 'markdown-import.css', __FILE__ ) );
	}
}

$markDownImport = new MarkDownImport();
