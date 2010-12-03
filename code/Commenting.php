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
	private static $default_config = array(
		'require_login' 				=> false, // boolean, whether a user needs to login
		'required_permission' 			=> '',  // required permission to comment (or array of permissions)
		'use_ajax_commenting' 			=> true, // use ajax to post comments.
		'show_comments_when_disabled' 	=> false, // when comments are disabled should we show older comments (if available)
		'order_comments_by'				=> "\"Created\" DESC"
	);
	
	/**
	 * Adds commenting to a {@link DataObject}
	 *
	 * @param string classname to add commenting to
	 * @param array $setting Settings. See {@link self::$default_config} for
	 *			available settings
	 */
	public static function add($class, array $settings) {
		self::$enabled_classes[$class] = $settings;
		
		Object::add_extension($class, 'CommentsExtension');
	}
	
	/**
	 * Removes commenting from a {@link DataObject}. Does not remove existing comments
	 * but does remove the extension.
	 *
	 * @param string $class Class to remove {@link CommentsExtension} from
	 */
	public static function remove($class) {
		if(isset(self::$enabled_classes[$class])) {
			unset(self::$enabled_classes[$class]);
		}
		
		Object::remove_extension($class, 'CommentsExtension');
	}
	
	/**
	 * Sets a value of a given config setting 
	 *
	 * @param string $class
	 * @param string $key setting to change
	 * @param mixed $value value of the setting
	 */
	public static function set_config_value($class, $key, $value = false) {
		if(isset(self::$enabled_classes[$class])) {
			if(!is_array(self::$enabled_classes[$class])) self::$enabled_classes[$class] = array();
			
			self::$enabled_classes[$class][$key] = $value;
		}
		else {
			throw new Exception("$class does not have commenting enabled", E_USER_ERROR);
		}
	}
	
	/**
	 * Returns a given config value for a commenting class
	 *
	 * @param string $class
	 * @param string $key config value to return
	 *
	 * @throws Exception 
	 * @return mixed
	 */
	public static function get_config_value($class, $key) {
		if(isset(self::$enabled_classes[$class])) {
			// custom configuration
			if(isset(self::$enabled_classes[$class][$key])) return self::$enabled_classes[$class][$key];
			
			// default configuration
			if(isset(self::$default_config[$key])) return self::$default_config[$key];
			
			// config value doesn't exist
			throw new Exception("Config ($key) is not a valid configuration value", E_USER_WARNING);
		}
		else {
			throw new Exception("$class does not have commenting enabled", E_USER_ERROR);
		}
	}
	
	/**
	 * Determines whether a config value on the commenting extension
	 * matches a given value.
	 *
	 * @param string $class
	 * @param string $key
	 * @param string $value Expected value
	 *
	 * @return bool
	 */
	public static function config_value_equals($class, $key, $value) {
		try {
			$check = self::get_config_value($class, $key);
			
			if($check && ($check == $value)) return true;
		}
		catch(Exception $e) {}
		
		return false;
	}
}