# Configuration

## Overview

The module provides a number of built in configuration settings below are the 
default settings

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
		'gravatar_size' => 80,
		'thread_comments' => false, // allow replies to existing comments.  Requires include_js=true,
		'maximum_thread_comment_depth' => 10
	));
	
If you want to customize any of the configuration options after you have added 
the extension (or on the built-in SiteTree commenting) use `set_config_value`

	// mysite/_config.php - Sets require_login to true for all pages
	Commenting::set_config_value('SiteTree', 'require_login', true);
	
	// mysite/_config.php - Returns the setting 
	Commenting::get_config_value('SiteTree', 'require_login');
	
## HTML Comments

Comments can be configured to contain a restricted set of HTML tags through the 
`html_allowed` and `html_allowed_elements` settings. Raw HTML is hardly user 
friendly, but combined with a rich-text editor of your own choosing it can 
allow rich comment formatting.

In order to use this feature, you need to install the
[HTMLPurifier](http://htmlpurifier.org/) library. The easiest way to do this is 
through [Composer](http://getcomposer.org).

	{
		"require": {"ezyang/htmlpurifier": "4.*"}
	}

**Important**: Rendering user-provided HTML on your website always risks 
exposing your users to cross-site scripting (XSS) attacks, if the HTML isn't 
properly sanitized. Don't allow tags like `<script>` or arbitrary attributes.

## Gravatars

Gravatars can be turned on by adding this to your mysite/_config.php file

	Commenting::set_config_value('SiteTree', 'use_gravatar', true);

The default size is 80 pixels, as per the gravatar site if the 's' parameter is 
omitted. To change this add the following (again to mysite/_config.php):

	Commenting::set_config_value('SiteTree', 'gravatar_size', 40);

If the email address used to comment does not have a gravatar, it is possible 
to configure the default image shown.  Valid values can be found at 
http://gravatar.com/site/implement/images/, and at the time of writing are the 
following:

* 404: do not load any image if none is associated with the email hash, instead 
return an HTTP 404 (File Not Found) response.
* mm: (mystery-man) a simple, cartoon-style silhouetted outline of a person 
(does not vary by email hash).
* identicon: a geometric pattern based on an email hash
* monsterid: a generated 'monster' with different colors, faces, etc
* wavatar: generated faces with differing features and backgrounds
* retro: awesome generated, 8-bit arcade-style pixelated faces
* blank: a transparent PNG image (border added to HTML below for demonstration 
purposes)

To change the default image style, add the following to mysite/_config.php

    Commenting::set_config_value('SiteTree', 'gravatar_default', 'retro');

The rating of the image can be changed by adding a line similar to this to 
mysite/_config.php

    Commenting::set_config_value('SiteTree', 'gravatar_rating', 'r');

Vald values for rating are as follows:

* g: suitable for display on all websites with any audience type.
* pg: may contain rude gestures, provocatively dressed individuals, the lesser 
swear words, or mild violence.
* r: may contain such things as harsh profanity, intense violence, nudity, or 
hard drug use.
* x: may contain hardcore sexual imagery or extremely disturbing violence.

## Threaded Comments

Comments can be replied to by adding this to your mysite/_config.php file

	Commenting::set_config_value('SiteTree', 'thread_comments', true);
	Commenting::set_config_value('SiteTree', 'maximum_thread_comment_depth', 8);
	Commenting::set_config_value('SiteTree', 'order_comments_by', 'Lineage');

'Lineage' here refers to a string representing the ancestry of a comment, e.g. 
0000700124 would mean that comment with ID 124 replied to comment with ID of 7.
This technique avoid expensive hierarchical traversal of the comments tree from
a database query point of view.

A 'Reply' button will appear by comments and when this is clicked the end user
will be able to reply to a comment up until the maximum depth of hierarchy has
been reached.

The moderation flag is also respected, so either the comment appears immediately or
a message indicating moderation is shown.

## Addition of Twitter Username to Comment Posting / Rendering

The addition of Twitter username can be achieved by adding the following to mysite/_config.php

	Object::add_extension('Comment', 'CommentTwitterUsernameExtension');
	Object::add_extension('CommentingController', 'CommentingControllerTwitterUsernameExtension');

This adds the following functionality:

* The end user can optionally enter a Twitter username
* When comments are rendering the relevant Twitter handle is shown, along with the number of
followers for that user.

Note that in or to show this widget correctly, you need to include the following in your theme
when rendering comments:

```
<script>window.twttr = (function (d, s, id) {
  var t, js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id; js.src= "https://platform.twitter.com/widgets.js";
  fjs.parentNode.insertBefore(js, fjs);
  return window.twttr || (t = { _e: [], ready: function (f) { t._e.push(f) } });
}(document, "script", "twitter-wjs"));
</script>
```