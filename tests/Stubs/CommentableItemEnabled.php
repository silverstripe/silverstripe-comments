<?php

namespace SilverStripe\Comments\Tests\Stubs;

class CommentableItemEnabled extends ExampleDataObject
{
    private static array $defaults = [
        'ProvideComments' => true,
        'ModerationRequired' => 'Required',
        'CommentsRequireLogin' => true
    ];

    private static string $table_name = 'CommentableItemEnabled';
}
