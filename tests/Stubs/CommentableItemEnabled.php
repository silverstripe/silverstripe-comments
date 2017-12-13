<?php

namespace SilverStripe\Comments\Tests\Stubs;

class CommentableItemEnabled extends CommentableItem
{
    private static $defaults = array(
        'ProvideComments' => true,
        'ModerationRequired' => 'Required',
        'CommentsRequireLogin' => true
    );

    private static $table_name = 'CommentableItemEnabled';
}
