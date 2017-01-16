<?php

namespace SilverStripe\Comments\Tests;

use ReflectionClass;
use ReflectionException;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Comments\Admin\CommentsGridField;
use SilverStripe\Dev\SapphireTest;

class CommentsGridFieldTest extends SapphireTest
{
    public function testNewRow()
    {
        $gridfield = new CommentsGridField('testfield', 'testfield');
        //   protected function newRow($total, $index, $record, $attributes, $content) {
        $comment = new Comment();
        $comment->Name = 'Fred Bloggs';
        $comment->Comment = 'This is a comment';
        $attr = array();

        try {
            $class  = new ReflectionClass($gridfield);
            $method = $class->getMethod('newRow');
            $method->setAccessible(true);
        } catch (ReflectionException $e) {
            $this->fail($e->getMessage());
        }

        $params = array(1, 1, $comment, $attr, $comment->Comment);
        $newRow = $method->invokeArgs($gridfield, $params);
        $this->assertEquals('<tr>This is a comment</tr>', $newRow);

        $attr = array('class' => 'cssClass');
        $params = array(1, 1, $comment, $attr, $comment->Comment);
        $newRow = $method->invokeArgs($gridfield, $params);
        $this->assertEquals('<tr class="cssClass">This is a comment</tr>', $newRow);

        $comment->markSpam();
        $params = array(1, 1, $comment, $attr, $comment->Comment);
        $newRow = $method->invokeArgs($gridfield, $params);
        $this->assertEquals('<tr class="cssClass spam">This is a comment</tr>', $newRow);
    }
}
