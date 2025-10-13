<?php
/**
 * The core plugin class.
 *
 */
require_once plugin_dir_path(dirname(__FILE__)) . 'classes/setup.php';

class wdo_Plugin extends wdo_Setup {
	public $config;
	
	public function __construct($config) {
		$this->config = $config;
		add_action('init', array(&$this, 'init'));
	}

	public function init() {

		if(is_admin()) {
			// The class responsible for defining all actions that occur in the admin area.
			require_once plugin_dir_path(dirname(__FILE__)) . 'classes/backend.php';
			$plugin_backend = new wdo_Backend($this);
		}
	}

	
	public function debug($msg) {
		$file = plugin_dir_path(dirname(__FILE__)) . '/debug.log'; 
		$file = fopen($file, "a");
		fwrite($file, date("Y.m.d H:i:s") . ' ' . $msg . "\r\n");
		fclose($file);
	}
}