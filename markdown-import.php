<?php
/*
Plugin Name: Markdown Import
Plugin URI: http://www.bastiaangrutters.nl
Description: Allows setting MarkDown file URL per post and importing/parsing it regularly into the post content
Version: 1.2
Author: Bastiaan Grutters
Author URI:
*/


class MarkDownImport {
	public const WP_CRON_METHOD = 'markdownImportCron';
    public const TEXT_DOMAIN = 'markdown-import';
	protected const UPDATE_INTERVAL = 'hourly';
	protected const UPDATE_INTERVAL_SECONDS = 3600;

	protected $options;


	public function __construct() {
		$this->options = get_option('markdown_import_options', []);
		// Add options menu page to menu
		add_action('admin_menu', [static::class, 'addOptionsMenu']);
		add_action('admin_init', [static::class, 'editorInit']);

        // When enabled add a link to the source MarkDown below the posts
        add_filter('the_content', [$this, 'theContent']);

		// Install PSR-0-compatible class autoloader for WP
		spl_autoload_register([static::class, 'autoload']);

        // Schedule the update to run every hour starting now
		if (!wp_next_scheduled(static::WP_CRON_METHOD) ) {
			wp_schedule_event(time(), static::UPDATE_INTERVAL, static::WP_CRON_METHOD);
		}

		add_action(static::WP_CRON_METHOD, function() { static::updateMDFiles(); });
        if (!function_exists('curl_version') && !ini_get('allow_url_fopen')) {
			add_action('admin_notices', function() {
				printf('<div class="notice notice-error">
                <h3>%s</h3>
                <p>%s</p>
            </div>',
					__('MarkDown Import plugin error', static::TEXT_DOMAIN),
					__('Your server does not support file_get_contents or cURL, please set allow_url_fopen to 1 or add cURL support.', static::TEXT_DOMAIN)
				);
			});
        }
	}

    public function theContent(string $content): string {
        if (is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        global $post;
        $options = static::getOptions();
        if ($options['show_source_link'] !== 'yes') {
            return $content;
        }

        if (!in_array($post->post_type, $options['post_types'] ?? [], true)) {
            return $content;
		}

        $url = get_post_meta($post->ID, '_markdown_import', true);
        if (empty($url)) {
            return $content;
        }

		$importTime = get_post_meta($post->ID, '_markdown_import_timestamp', true);
		if (empty($importTime)) {
			return $content;
		}

        // Replace GitHub raw sources with their editable URL
        if (str_starts_with($url, 'https://raw.githubusercontent.com/')) {
			$url = str_replace(array('https://raw.githubusercontent.com/', '/main/'), array('https://github.com/', '/blob/main/'), $url);
        }

        return $content . sprintf(
                '<p>&nbsp;</p><p><span class="markdown-import-source">%s <a href="%s" target="_blank">%s</a></span></p>',
				__('Imported from', static::TEXT_DOMAIN),
                $url,
				__('MarkDown source file', static::TEXT_DOMAIN),
        );
    }

	public static function autoload($className): void {
		$className = ltrim($className, '\\');
		$fileName = '';
		$namespace = '';
		if ($lastNsPos = strrpos($className, '\\')) {
			$namespace = substr($className, 0, $lastNsPos);
			$className = substr($className, $lastNsPos + 1);
			$fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
		}
		$fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
		if (file_exists(plugin_dir_path(__FILE__) . $fileName)) {
			include(plugin_dir_path(__FILE__) . $fileName);
		}
	}

	public static function editorInit(): void {
		$options = MarkDownImport::getOptions();
		foreach ($options['post_types'] ?? [] as $postType) {
			add_meta_box('markdown-import-meta', __('MarkDown import', static::TEXT_DOMAIN), ['MarkDownImport', 'markdownImportWidget'], $postType, 'normal', 'low');
		}
		add_action('save_post', [static::class, 'saveMarkDownImportWidget']);
	}

	public static function saveMarkDownImportWidget(int $postId): void {
		if (current_user_can('edit_post') && isset($_POST['_markdown_import_url'])) {
			update_post_meta($postId, '_markdown_import', $_POST['_markdown_import_url']);
			if (isset($_POST['markdown_import_clear_timestamp']) && $postId == $_POST['markdown_import_clear_timestamp']) {
				delete_post_meta($postId, '_markdown_import_timestamp');
			}
		}
	}

	public static function markdownImportWidget(): void {
		global $post;
		$value = get_post_meta($post->ID, '_markdown_import', true);
		?>
        <div id="markdown-import-container">
            <div class="block">
                <label for="markdown-url"><?php _e('MarkDown URL', static::TEXT_DOMAIN); ?></label>
                <input type="text" id="markdown-url" name="_markdown_import_url" value="<?php print($value); ?>"/><br/>
                <br/>
				<?php _e('Note: This will overwrite the post content with the contents of the MD file changed to HTML', static::TEXT_DOMAIN); ?>
                <br/>
				<?php
				if ($value != '') {
					$lastUpdate = get_post_meta($post->ID, '_markdown_import_timestamp', true);
					if ($lastUpdate !== '' && is_numeric($lastUpdate)) {
						$lastUpdate = time() - $lastUpdate;
						$text = (int)($lastUpdate / 3600);
						if ($text === 0) {
							$text = (int)($lastUpdate % 3600 / 60) . ' ' . __('minutes', static::TEXT_DOMAIN) . ' ' . __('ago', static::TEXT_DOMAIN);
						} else {
							$text .= ' ' . __('hours', static::TEXT_DOMAIN) . ' ' . __('and', static::TEXT_DOMAIN) . ' ';
							$text .= (int)($lastUpdate % 3600 / 60) . ' ' . __('minutes', static::TEXT_DOMAIN) . ' ' . __('ago', static::TEXT_DOMAIN);
						}

						$text .= '<br /><input type="checkbox" id="markdown-import-clear-timestamp" name="markdown_import_clear_timestamp" value="' . $post->ID . '" /> <label for="markdown-import-clear-timestamp">' . __('Force a new import', static::TEXT_DOMAIN) . '</label>';
					} else {
						$text = __('never', static::TEXT_DOMAIN);
					}
					?>
                    <br/>
					<?php _e('Most recent import performed', static::TEXT_DOMAIN); ?>: <?php print($text); ?>
					<?php
				}
				?>
            </div>
        </div>
		<?php
	}

	public static function updateMDFiles(): array {
		global $wpdb;
		$results = [
			'imported' => 0,
			'skipped' => 0
		];
		$options = MarkDownImport::getOptions();
		$mdFiles = $wpdb->get_results("SELECT post_id, meta_value, post_type
				FROM $wpdb->postmeta
				JOIN $wpdb->posts ON ID = post_id
				WHERE meta_key = '_markdown_import'");
		$currentTime = time();
        foreach ($mdFiles as $mdFile) {
            if (!in_array($mdFile->post_type, $options['post_types'], true)) {
				continue;
			}

            if ($mdFile->meta_value === '') {
				$results['skipped']++;
                continue;
			}

            $lastUpdate = get_post_meta($mdFile->post_id, '_markdown_import_timestamp', true);
            // Check if this MD file is out of date, if so we update it
            if (empty($lastUpdate) || $lastUpdate + MarkDownImport::UPDATE_INTERVAL_SECONDS <= $currentTime) {
                try {
                    // load the file and parse it
                    $text = static::getMarkdownFileContent($mdFile->meta_value);
                    $html = \Michelf\Markdown::defaultTransform($text);
                    $postData = array(
                        'ID' => $mdFile->post_id,
                        'post_content' => $html
                    );
                    wp_update_post($postData);
                    update_post_meta($mdFile->post_id, '_markdown_import_timestamp', time());
                    $results['imported']++;
				} catch (RuntimeException $e) { }
            }
        }

		return $results;
	}

	public static function addOptionsMenu(): void {
		add_options_page('MarkDown Import Options', 'MarkDown Import Options',
			'activate_plugins', 'markdown_import_options',
			[static::class, 'showOptionsPage']);
	}

	public static function showOptionsPage(): void {
		include(plugin_dir_path(__FILE__) . 'markdown-import-options.php');
	}

	public static function getOptions(bool $forceReload = false) {
		global $markDownImport;
		if ($forceReload) {
			$markDownImport->options = get_option('markdown_import_options', []);
		}
		return $markDownImport->options;
	}

    protected static function getMarkdownFileContent(string $url): string {
        if (ini_get('allow_url_fopen')) {
            $content = file_get_contents($url);
            if ($content !== false) {
                return $content;
			}
		}

		if (function_exists('curl_version'))
		{
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			$content = curl_exec($curl);
			curl_close($curl);

            return $content;
		}

        throw new RuntimeException('No way to retrieve the markdown file.');
	}
}

$markDownImport = new MarkDownImport();
