# Configuration

## Overview

The module provides a number of built in configuration settings below are the default settings

	// mysite/_config.php 
	
	Commenting::add('Foo', array(
		'require_login' => false, // boolean, whether a user needs to login
		'required_permission' => false,  // required permission to comment (or array of permissions)
		'include_js' => true, // Enhance operation by ajax behaviour on moderation links
		'show_comments_when_disabled' => false, // when comments are disabled should we show older comments (if available)
		'order_comments_by' => "\"Created\" DESC",
		'comments_per_page' => 10,
		'comments_holder_id' => "comments-holder", // id for the comments holder
		'comment_permalink_prefix' => "comment-", // id prefix for each comment. If needed make this different
		'require_moderation' => false,
		'html_allowed' => false, // allow for sanitized HTML in comments
		'html_allowed_elements' => array('a', 'img', 'i', 'b'),
		'use_preview' => false, // preview formatted comment (when allowing HTML). Requires include_js=true
		'use_gravatar' => false,
		'gravatar_size' => 80
	);
	
If you want to customize any of the configuration options after you have added the extension (or
on the built-in SiteTree commenting) use `set_config_value`

	// mysite/_config.php - Sets require_login to true for all pages
	Commenting::set_config_value('SiteTree', 'require_login', true);
	
	// mysite/_config.php - Returns the setting 
	Commenting::get_config_value('SiteTree', 'require_login');
	
## HTML Comments

Comments can be configured to contain a restricted set of HTML tags
through the `html_allowed` and `html_allowed_elements` settings.
Raw HTML is hardly user friendly, but combined with a rich-text editor
of your own choosing it can allow rich comment formatting.

In order to use this feature, you need to install the
[HTMLPurifier](http://htmlpurifier.org/) library.
The easiest way to do this is through [Composer](http://getcomposer.org).

	{
		"require": {"ezyang/htmlpurifier": "4.*"}
	}

**Important**: Rendering user-provided HTML on your website always risks
exposing your users to cross-site scripting (XSS) attacks, if the HTML
isn't properly sanitized. Don't allow tags like `<script>` or arbitrary attributes.

## Gravatars

Gravatars can be turned on by adding this to your mysite/_config.php file

	Commenting::set_config_value('SiteTree', 'use_gravatar', true);

The default size is 80 pixels, as per the gravatar site if the 's' parameter is omitted.  To change this add the following (again to mysite/_config.php):

	Commenting::set_config_value('SiteTree', 'gravatar_size', 40);