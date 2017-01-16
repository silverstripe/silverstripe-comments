# Configuration

## Overview

The module provides a number of built in configuration settings below are the
default settings

In order to add commenting to your site, the minimum amount of work necessary is to add the `CommentsExtension` to
the base class for the object which holds comments.

```yaml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - SilverStripe\Comments\Extensions\CommentsExtension
```

## Configuration

In order to configure options for any class you should assign the specific option a value under the 'comments'
config of the specified class.

```yaml
SilverStripe\CMS\Model\SiteTree:
  extensions:
    - SilverStripe\Comments\Extensions\CommentsExtension
  comments:
    enabled: true # Enables commenting to be disabled for a specific class (or subclass of a parent with commenting enabled)
    enabled_cms: false # The 'enabled' option will be set via the CMS instead of config
    require_login: false # boolean, whether a user needs to login
    require_login_cms: false # The 'require_login' option will be set via the CMS instead of config
    required_permission: false # required permission to comment (or array of permissions)
    include_js: true # Enhance operation by ajax behaviour on moderation links
    use_gravatar: false # set to true to show gravatar icons,
    gravatar_size: 80 # size of gravatar in pixels.  This is the same as the standard default
    gravatar_default: 'identicon' # theme for 'not found' gravatar (see http://gravatar.com/site/implement/images/)
    gravatar_rating: 'g' # gravatar rating. This is the same as the standard default
    show_comments_when_disabled: false # when comments are disabled should we show older comments (if available)
    order_comments_by: '"Created" DESC'
    comments_per_page: 10
    comments_holder_id: 'comments-holder' # id for the comments holder
    comment_permalink_prefix: 'comment-' # id prefix for each comment. If needed make this different
    require_moderation: false
    require_moderation_nonmembers: false # requires moderation for comments posted by non-members. 'require_moderation' overrides this if set.
    require_moderation_cms: false # If true, ignore above values and configure moderation requirements via the CMS only
    frontend_moderation: false # Display unmoderated comments in the frontend, if the user can moderate them.
    frontend_spam: false # Display spam comments in the frontend, if the user can moderate them.
    html_allowed: false # allow for sanitized HTML in comments
    html_allowed_elements:
      - a
      - img
      - i
      - b
    use_preview: false # preview formatted comment (when allowing HTML). Requires include_js=true
    nested_comments: false # If true comments can be replied to up to nested_depth levels
    nested_depth: 2 # The maximum depth of the comment hierarchy for comment reply purposes
```

Enabling any of the *_cms options will instead allow these options to be configured under the settings tab
of each page in the CMS.

If you want to customize any of the configuration options after you have added
the extension (or on the built-in SiteTree commenting) use `set_config_value`

```yaml
# Set the default option for pages to require login
SilverStripe\CMS\Model\SiteTree:
  comments:
    require_login: true
```


```php
// Get the setting
$loginRequired = singleton('SilverStripe\\CMS\\Model\\SiteTree')->getCommentsOption('require_login');
```


## HTML Comments

Comments can be configured to contain a restricted set of HTML tags through the
`html_allowed` and `html_allowed_elements` settings. Raw HTML is hardly user
friendly, but combined with a rich-text editor of your own choosing it can
allow rich comment formatting.

In order to use this feature, you need to install the
[HTMLPurifier](http://htmlpurifier.org/) library. The easiest way to do this is
through [Composer](http://getcomposer.org).

```json
    {
        "require": {"ezyang/htmlpurifier": "^4.8"}
    }
```

**Important**: Rendering user-provided HTML on your website always risks
exposing your users to cross-site scripting (XSS) attacks, if the HTML isn't
properly sanitized. Don't allow tags like `<script>` or arbitrary attributes.

## Gravatars

Gravatars can be turned on by adding this to your mysite/_config/config.yml file

```yaml
SilverStripe\CMS\Model\SiteTree:
  comments:
    use_gravatar: true
````

The default size is 80 pixels, as per the gravatar site if the 's' parameter is
omitted. To change this add the following (again to mysite/_config/config.yml):

```yaml
SilverStripe\CMS\Model\SiteTree:
  comments:
    gravatar_size: 40
```

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

To change the default image style, add the following to mysite/_config/config.yml

```yaml
SilverStripe\CMS\Model\SiteTree:
  comments:
    gravatar_default: 'retro'
```

The rating of the image can be changed by adding a line similar to this to
mysite/_config/config.yml

```yaml
SilverStripe\CMS\Model\SiteTree:
  comments:
    gravatar_rating: 'r'
```

Vald values for rating are as follows:

* g: suitable for display on all websites with any audience type.
* pg: may contain rude gestures, provocatively dressed individuals, the lesser
swear words, or mild violence.
* r: may contain such things as harsh profanity, intense violence, nudity, or
hard drug use.
* x: may contain hardcore sexual imagery or extremely disturbing violence.
