<?php

/**
 * Helper Class for storing the configuration options. Retains the mapping between objects which
 * have comments attached and the related configuration options.
 *
 * Also handles adding the Commenting extension to the {@link DataObject} on behalf of the user.
 *
 * @deprecated since version 2.0
 *
 * @package comments
 */
class Commenting {
	/**
	 * Adds commenting to a {@link DataObject}.
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class
	 * @param bool|array $settings
	 *
	 * @throws InvalidArgumentException
	 */
	public static function add($class, $settings = false) {
		Deprecation::notice('2.0', 'Using Commenting::add is deprecated. Please use the config API instead');

		Config::inst()->update($class, 'extensions', array('CommentsExtension'));

		if($settings === false) {
			return;
		}

		if(!is_array($settings)) {
			throw new InvalidArgumentException(
				'$settings needs to be an array or null'
			);
		}

		Config::inst()->update($class, 'comments', $settings);
	}

	/**
	 * Removes commenting from a {@link DataObject}. Does not remove existing comments but does
	 * remove the extension.
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class
	 */
	public static function remove($class) {
		Deprecation::notice('2.0', 'Using Commenting::remove is deprecated. Please use the config API instead');

		$class::remove_extension('CommentsExtension');
	}

	/**
	 * Returns whether a given class name has commenting enabled.
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
	 * Sets a value for a class of a given config setting. Passing 'all' as the class sets it for
	 * everything.
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class
	 * @param string $key
	 * @param mixed $value
	 */
	public static function set_config_value($class, $key, $value = false) {
		Deprecation::notice('2.0', 'Commenting::set_config_value is deprecated. Use the config api instead');

		if($class === "all") {
			$class = 'CommentsExtension';
		}

		Config::inst()->update($class, 'comments', array($key => $value));
	}

	/**
	 * Returns a given config value for a commenting class.
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class
	 * @param string $key config value to return
	 *
	 * @return mixed
	 *
	 * @throws InvalidArgumentException
	 */
	public static function get_config_value($class, $key) {
		Deprecation::notice('2.0', 'Using Commenting::get_config_value is deprecated. Please use $parent->getCommentsOption() or CommentingController::getOption() instead');

		if(!$class) {
			$class = 'CommentsExtension';
		} elseif(!$class::has_extension('CommentsExtension')) {
			throw new InvalidArgumentException(
				sprintf('%s does not have commenting enabled', $class)
			);
		}

		return singleton($class)->getCommentsOption($key);
	}

	/**
	 * Determines whether a config value on the commenting extension matches a given value.
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class
	 * @param string $key
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function config_value_equals($class, $key, $value) {
		$check = self::get_config_value($class, $key);

		if($check && ($check == $value)) {
			return true;
		}

		return false;
	}

	/**
	 * Return whether a user can post on a given commenting instance.
	 *
	 * @deprecated since version 2.0
	 *
	 * @param string $class
	 *
	 * @return bool
	 */
	public static function can_member_post($class) {
		Deprecation::notice('2.0', 'Use $instance->canPostComment() directly instead');

		$member = Member::currentUser();

		$permission = self::get_config_value($class, 'required_permission');

		if($permission && !Permission::check($permission)) {
			return false;
		}

		$requireLogin = self::get_config_value($class, 'require_login');

		return !$requireLogin || $member;
	}
}
