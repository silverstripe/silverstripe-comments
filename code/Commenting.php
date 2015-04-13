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
 * @deprecated since version 2.0
 *
 * @package comments
 */
class Commenting {

	/**
	 * Adds commenting to a {@link DataObject}
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string classname to add commenting to
	 * @param array $settings Settings. See {@link self::$default_config} for
	 *			available settings
	 * 
	 * @throws InvalidArgumentException
	 */
	public static function add($class, $settings = false) {
		Deprecation::notice('2.0', 'Using Commenting::add is deprecated. Please use the config API instead');
		Config::inst()->update($class, 'extensions', array('CommentsExtension'));

		// Check if settings must be customised
		if($settings === false) return;
		if(!is_array($settings)) {
			throw new InvalidArgumentException('$settings needs to be an array or null');
		}
		Config::inst()->update($class, 'comments', $settings);
	}

	/**
	 * Removes commenting from a {@link DataObject}. Does not remove existing comments
	 * but does remove the extension.
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class Class to remove {@link CommentsExtension} from
	 */
	public static function remove($class) {
		Deprecation::notice('2.0', 'Using Commenting::remove is deprecated. Please use the config API instead');
		$class::remove_extension('CommentsExtension');
	}

	/**
	 * Returns whether a given class name has commenting enabled
	 *
	 * @deprecated since version 2.0
	 *
	 * @return bool
	 */
	public static function has_commenting($class) {
		Deprecation::notice('2.0', 'Using Commenting::has_commenting is deprecated. Please use the config API instead');
		return $class::has_extension('CommentsExtension');
	}

	/**
	 * Sets a value for a class of a given config setting. Passing 'all' as the class
	 * sets it for everything
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class Class to set the value on. Passing 'all' will set it to all 
	 *			active mappings
	 * @param string $key setting to change
	 * @param mixed $value value of the setting
	 */
	public static function set_config_value($class, $key, $value = false) {
		Deprecation::notice('2.0', 'Commenting::set_config_value is deprecated. Use the config api instead');
		if($class === "all") $class = 'CommentsExtension';
		Config::inst()->update($class, 'comments', array($key => $value));
	}

	/**
	 * Returns a given config value for a commenting class
	 *
	 * @deprecated since version 2.0
	 * 
	 * @param string $class
	 * @param string $key config value to return
	 *
	 * @throws Exception
	 * @return mixed
	 */
	public static function get_config_value($class, $key) {
		Deprecation::notice(
			'2.0',
			'Using Commenting::get_config_value is deprecated. Please use $parent->getCommentsOption() or '
			. 'CommentingController::getOption() instead'
		);

		// Get settings
		if(!$class) {
			$class = 'CommentsExtension';
		} elseif(!$class::has_extension('CommentsExtension')) {
			throw new InvalidArgumentException("$class does not have commenting enabled");
		}
		return singleton($class)->getCommentsOption($key);
	}

	/**
	 * Determines whether a config value on the commenting extension
	 * matches a given value.
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class
	 * @param string $key
	 * @param string $value Expected value
	 * @return boolean
	 */
	public static function config_value_equals($class, $key, $value) {
		$check = self::get_config_value($class, $key);
		if($check && ($check == $value)) return true;
	}

	/**
	 * Return whether a user can post on a given commenting instance
	 *
	 * @deprecated since version 2.0
	 * 
	 * @param string $class
	 * @return boolean true
	 */
	public static function can_member_post($class) {
		Deprecation::notice('2.0',  'Use $instance->canPostComment() directly instead');
		$member = Member::currentUser();

		// Check permission
		$permission = self::get_config_value($class, 'required_permission');
		if($permission && !Permission::check($permission)) return false;

		// Check login required
		$requireLogin = self::get_config_value($class, 'require_login');
		return !$requireLogin || $member;
	}
}
