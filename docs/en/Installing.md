# Installation

## Composer

Edit your project-wide composer.json file as follows; in the "require" block add:

	"silverstripe/comments": "*"

Then in the root of your project run:

	#> composer update silverstripe/comments

## Web

To begin the installation first download the module online. You can find the version
you require for your SilverStripe installation on the [silverstripe.org](http://www.silverstripe.org) website.

After you have finished downloading the file, extract the downloaded file to your site's root
folder and ensure the name of the module is `comments`.

## All

Run a database rebuild by visiting *http://yoursite.com/dev/build*. This will add the required database
columns and tables for the module to function.

If you previously had SilverStripe version 2.4 installed then you'll also need to run the migration script
provided. More information on this is provided in the next section.

## Enabling Commenting

Out of the box the module adds commenting support to all pages on your site. This functionality can be
turned on and off on a per page basis in the CMS under the `Behaviour` tab for a given page. Once the `Allow Comments`
checkbox is ticked, republish and view the webpage.

Ensure that your template file (usually themes/yourtheme/templates/Layout/Page.ss) has the `$CommentsForm` variable
where you want comments to appear.

To enable commenting on other objects (such as your own subclasses of `DataObject`) add the following
to your `mysite/_config.php` file.

	// Adds commenting to the class SiteTree (all Pages)
	Commenting::add('SiteTree');

You can also pass configuration options to the add function to customize commenting on that object. Again
in your `mysite/_config.php` file replace the previous line with the following

	// Adds commenting to the class SiteTree (all Pages) but require the user to login and comments
	// are moderated
	Commenting::add('Foo', array(
		'require_moderation' => true,
		'require_login' => true
	));

For more configuration options see [Configuration](Configuration.md).

## Upgrading

### Migrating from version 2.* SilverStripe installations

This module replaces the built-in commenting system available in versions up to SilverStripe 2.4. To migrate from
that you'll need to run `dev/build` after installing the module.

You can do this via sake (`sake dev/build`) or via a web browser by visiting `http://yoursite.com/dev/build`