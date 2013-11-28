<?php

/**
 * Comments Default Configuration
 *
 * To enable comments on your own {@link DataObject}'s you need to 
 * call Commenting::add_comments($object_name, $settings);
 *
 * Where $object_name is the name of the subclass of DataObject you want
 * to add the comments to and $settings is a map of configuration options
 * and values
 *
 * Example: mysite/_config.php
 *
 * <code>
 *	// uses the default values
 *	Commenting::add('SiteTree');
 * 
 *	// set configuration
 *	Commenting::add('SiteTree', array(
 *		'require_login' => true
 *	));
 * </code>
 *
 * To see all the configuration options read docs/en/Configuration.md or
 * consult the Commenting class.
 */

if(class_exists('SiteTree') && !Commenting::has_commenting('SiteTree')) {
	Commenting::add('SiteTree');
}

if(class_exists('ContentController')) {
	ContentController::add_extension('ContentControllerCommentsExtension');
}