# Installation

To begin the installation first download the module from online. You can find what version
you require for your SilverStripe installation on the silverstripe.org website.

After you have finished downloading the file, extract the downloaded file to your sites root
folder and ensure the name of the module is `comments`.

Run a database rebuild by visiting *http://yoursite.com/dev/build*. This will add the required database
columns and tables for the module to function.

If you previously had SilverStripe 2.4 installed then you will also need to run the migration script
provided. More information on this is in the next section

## Migrating old comments

This module replaces the built in commenting system available in versions up to SilverStripe 2.4. To migrate from
that you need to run the `InitialCommentMigration` task. You can do this via sake or via a web browser by visiting
*http://yoursite.com/dev/tasks/InitialCommentMigration*