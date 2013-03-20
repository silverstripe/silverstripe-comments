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
		'require_login' => false, // boolean, whether a user needs to login
		'required_permission' => false,  // required permission to comment (or array of permissions)
		'include_js' => true, // Enhance operation by ajax behaviour on moderation links
		'use_gravatar' => false, // set to true to show gravatar icons,
		'gravatar_size' => 80, // size of gravatar in pixels.  This is the same as the standard default
		'show_comments_when_disabled' => false, // when comments are disabled should we show older comments (if available)
		'order_comments_by' => "\"Created\" DESC",
		'comments_per_page' => 10,
		'comments_holder_id' => "comments-holder", // id for the comments holder
		'comment_permalink_prefix' => "comment-", // id prefix for each comment. If needed make this different
		'require_moderation' => false,
		'html_allowed' => false, // allow for sanitized HTML in comments
		'html_allowed_elements' => array('a', 'img', 'i', 'b'),
		'use_preview' => false, // preview formatted comment (when allowing HTML). Requires include_js=true
	);
	
	/**
	 * Adds commenting to a {@link DataObject}
	 *
	 * @param string classname to add commenting to
	 * @param array $setting Settings. See {@link self::$default_config} for
	 *			available settings
	 * 
	 * @throws InvalidArgumentException
	 */
	public static function add($class, $settings = false) {
		if($settings && !is_array($settings)) {
			throw new InvalidArgumentException('$settings needs to be an array or null', E_USER_ERROR);
		}
		
		self::$enabled_classes[$class] = $settings;

		$class::add_extension('CommentsExtension');
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
		
		$class::remove_extension('CommentsExtension');
	}

	/**
	 * Returns whether a given class name has commenting enabled
	 *
	 * @return bool
	 */
	public static function has_commenting($class) {
		return (isset(self::$enabled_classes[$class]));
	}

	/**
	 * Sets a value for a class of a given config setting. Passing 'all' as the class
	 * sets it for everything
	 *
	 * @param string $class Class to set the value on. Passing 'all' will set it to all 
	 *			active mappings
	 * @param string $key setting to change
	 * @param mixed $value value of the setting
	 */
	public static function set_config_value($class, $key, $value = false) {
		if($class == "all") {
			if($enabledClasses = self::$enabled_classes) {
				foreach($enabledClasses as $enabled) {
					if(!is_array($enabled)) $enabled = array();
					
					$enabled[$key] = $value;
				}
			}
		}
		else if(isset(self::$enabled_classes[$class])) {
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
	public static function get_config_value($class = null, $key) {
		if(!$class || isset(self::$enabled_classes[$class])) {
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
	
	/**
	 * Return whether a user can post on a given commenting instance
	 *
	 * @param string $class
	 */
	public static function can_member_post($class) {
		$member = Member::currentUser();
		
		try {
			$login = self::get_config_value($class, 'require_login');
			$permission = self::get_config_value($class, 'required_permission');
			
			if($permission && !Permission::check($permission)) return false;
			
			if($login && !$member) return false;
		}
		catch(Exception $e) {}
		
		return true;
	}
}