<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Comments\Model\Comment;
use SilverStripe\Comments\Admin\CommentsGridField;
use SilverStripe\Dev\SapphireTest;

class CommentsGridFieldTest extends SapphireTest
{
    public function testNewRow()
    {
        $gridfield = CommentsGridField::create('testfield', 'testfield');

        $comment = Comment::create();
        $comment->Name = 'Fred Bloggs';
        $comment->Comment = 'This is a comment';
        $attr = [];
        $params = [1, 1, $comment, $attr, $comment->Comment];
        $newRow = $gridfield->newRow(...$params);
        $this->assertEquals('<tr>This is a comment</tr>', $newRow);

        $attr = ['class' => 'cssClass'];
        $params = [1, 1, $comment, $attr, $comment->Comment];
        $newRow = $gridfield->newRow(...$params);
        $this->assertEquals('<tr class="cssClass">This is a comment</tr>', $newRow);
    }
}
