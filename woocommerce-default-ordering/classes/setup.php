<?php
/**
 * Setup functions on activate & deactivate events:
 * - Initialize custom options, database, etc.
 * - Upgrade custom options, database, etc.
 * - Cleanup on deactivate
 */
require_once plugin_dir_path(dirname(__FILE__)) . 'classes/base.php';

class wdo_Setup extends wdo_Base {

	/**
	 * Specify all codes required for plugin activation here.
	 */
	public function activate() {
		$this->debug('[plugin] Activate');

		// Initialize custom things on plugin activation
		$this->install();
	}

	/**
	 * Specify all codes required for plugin deactivation here.
	 */
	public function deactivate() {
		$this->debug('[plugin] Deactivate');
	}

	/**
	 * Specify all codes required for plugin uninstall here.
	 *
	 */
	public function uninstall() {
		$this->debug('[plugin] Uninstall');
	}
	

	public function install() {
		$this->debug('[plugin] Install');
		
		// Initialize plugin options
		$this->initOptions();
	}

	/**
	 * Storing custom options
	 */
	public function initOptions() {
	}

}
