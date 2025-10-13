<?php
/**
 * Helper functions.
 *
 */
class wdo_Base {
	/**
	 * Helper for using prefixes for all references.
	 */
	public function setPrefix($name) {
		return ((strpos($name, $this->config['prefix']) === 0) ? '' : $this->config['prefix']) . $this->config['prefixSeparator'] . $name;
	}

	/**
	 * Helper for getting prefixed options.
	 */
	public function getOption($name, $default = null) {
		$ret = get_option($this->setPrefix($name));
		if(!$ret && $default) {
			$ret = $default;
		}
		return $ret;
	}
	
	/**
	 * Helper for adding/updating prefixed options.
	 */
	public function setOption($name, $value) {
		return ($this->getOption($name, '') === '') ? 
			add_option($this->setPrefix($name), $value) : 
			update_option($this->setPrefix($name), $value);
	}
	
}
