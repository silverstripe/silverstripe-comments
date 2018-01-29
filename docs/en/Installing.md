# Installation

Then in the root of your project run:

```sh
composer require silverstripe/comments`
```

Then run a database rebuild by visiting `dev/build`. This will add the required database columns and tables for the module to function, and refresh the configuration manifest.

## Enabling Commenting

Out of the box the module adds commenting support to all pages on your site. This functionality can be turned on and off on a per page basis in the CMS under the `Behaviour` tab for a given page. Once the `Allow Comments` checkbox is ticked, republish and view the webpage.

For more configuration options see [Configuration](Configuration.md).