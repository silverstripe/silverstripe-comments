<?php

namespace SilverStripe\Comments\Tests\Stubs;

class CommentableItemDisabled extends CommentableItem
{
    private static $defaults = array(
        'ProvideComments' => false,
        'ModerationRequired' => 'None',
        'CommentsRequireLogin' => false
    );

    private static $table_name = 'CommentableItemDisabled';
}
