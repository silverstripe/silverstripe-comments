<?php

Deprecation::notification_version('2.0', 'comments');

define('COMMENTS_DIR', ltrim(Director::makeRelative(realpath(__DIR__)), DIRECTORY_SEPARATOR));