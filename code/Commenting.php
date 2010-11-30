<?php

/**
 * Helper Class for storing the configuration options. Retains the mapping between
 * objects which have comments attached and the related configuration options.
 *
 * Also handles adding the Commenting extension to the {@link DataObject} on behalf
 * of the user.
 *
 * For documentation on how to use this class see docs/en/Configuration.md
 *
 * @package comments
 */

class Commenting {
	
	/**
	 * @var array map of enabled {@link DataObject} and related configuration
	 */
	private static $enabled_classes = array();
	
	/**
	 * @var array default configuration values
	 */
	private static $default_configuration = array(
		'require_login' 				=> false, // boolean, whether a user needs to login
		'required_permission' 			=> '',  // required permission to comment (or array of permissions)
		'use_ajax_commenting' 			=> true, // use ajax to post comments.
		'show_comments_when_disabled' 	=> false, // when comments are disabled should we show older comments (if available)
		'order_comments_by'				=> "\"Created\" DESC"
	);
	
	
	public function add($class, $settings) {
		
	}
	
	public function remove($class) {
		
	}
	
	public function set_config($class, $configuration) {
		
	}
	
	public function set_config_value($class, $key, $value = false) {
		
	}
	
	public function get_config($class) {
		
	}
	
	public function get_config_value($class, $key) {
		
	}
	
	public function config_value_equals($class, $key, $value) {
		
	}
}