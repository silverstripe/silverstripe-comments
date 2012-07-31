## Configuration

The module provides a number of built in configuration settings below are the default settings

	// mysite/_config.php 
	
	Commenting::add('Foo', array(
		'require_login' => false,
		'required_permission' => false,
		'use_ajax_commenting' => true,
		'show_comments_when_disabled' => false,
		'order_comments_by' => "\"Created\" DESC",
		'comments_per_page' => 10,
		'rss_comments_per_page' => 10,
		'comments_holder_id' => "comments-holder", 
		'comment_permalink_prefix' => "comment-",
		'require_moderation' => false
	);
	
If you want to customize any of the configuration options after you have added the extension (or
on the built-in SiteTree commenting) use `set_config_value`

	// mysite/_config.php - Sets require_login to true for all pages
	Commenting::set_config_value('SiteTree', 'require_login', true);
	
	// mysite/_config.php - Returns the setting 
	Commenting::get_config_value('SiteTree', 'require_login');
	
	