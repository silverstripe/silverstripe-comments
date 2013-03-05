# Configuration

## Overview

The module provides a number of built in configuration settings below are the default settings

	// mysite/_config.php 
	
	Commenting::add('Foo', array(
		'require_login' => false,
		'required_permission' => false,
		'use_ajax_commenting' => true,
		'show_comments_when_disabled' => false,
		'order_comments_by' => "\"Created\" DESC",
		'comments_per_page' => 10,
		'comments_holder_id' => "comments-holder", 
		'comment_permalink_prefix' => "comment-",
		'require_moderation' => false,
		'html_allowed' => false, // allow for sanitized HTML in comments
		'html_allowed_elements' => array('a', 'img', 'i', 'b'),
		'use_preview' => false, // preview formatted comment (when allowing HTML)
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
