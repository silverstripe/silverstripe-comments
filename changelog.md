# Changelog

All notable changes to this project will be documented in this file.

This project adheres to [Semantic Versioning](http://semver.org/).

## [2.1.0]

* BUG Fix missing hash change for #177
* BUG: Fix infinite loop in requireDefaultRecords()
* FIX: Redirect to a URL that does not indicate an error, style moderation method
* Reformat as PSR-2
* FIX: Layout with Gravatar fixed
* FIX: Non JS spam/ham/approve now redirect back to relevant comment
* ENHANCEMENT: Ajax spam/ham/approve/delete resurrected.
* FIX: Take account of spam/moderation status when enabling replies to a comment
* Minor: Turn off line numbers for generated CSS
* FIX: When viewing a comment permalink full comment and posting data shows
* MINOR: Update version of notifications module to one that is not deprecated
* FIX: Add missing parameters for nested comments to example configuration

## [2.0.3]

* Update documentation and configuration to supported module standard
* Increase test coverage from 54% to 92%
* Add cms as suggested module
* FIX: The AutoFormat.AutoParagraph injector of HTMLPurifier fails if the p tag is not allowed.
* FIX: Change creation of CreatedField to unchained as setName() method of DatetimeField is not chainable

## [2.0.2]

* Changelog added.
* Removed deprecated example configuration
* Handle when extension has been removed from object
* BUG Fix each gridfield having triple gridstate components
* Prevented duplicate IDs on action buttons
* Update translations
