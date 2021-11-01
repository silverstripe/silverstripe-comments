<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Comments\Admin\CommentAdmin;
use SilverStripe\Comments\Admin\CommentsGridField;
use SilverStripe\Comments\Admin\CommentsGridFieldAction;
use SilverStripe\Comments\Admin\CommentsGridFieldConfig;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Comments\Tests\Stubs\CommentableItem;
use SilverStripe\Comments\Tests\Stubs\Team;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class CommentsGridFieldActionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        CommentableItem::class,
        Team::class,
    ];

    /** @var ArrayList */
    protected $list;

    /** @var GridField */
    protected $gridField;

    /** @var Form */
    protected $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->list = new DataList(Team::class);
        $config = CommentsGridFieldConfig::create()->addComponent(new GridFieldDeleteAction());
        $this->gridField = new CommentsGridField('testfield', 'testfield', $this->list, $config);
        $this->form = new Form(new CommentAdmin(), 'mockform', new FieldList(array($this->gridField)), new FieldList());
    }

    public function testAugmentColumns()
    {
        $action = new CommentsGridFieldAction();

        // an entry called 'Actions' is added to the columns array
        $columns = array();
        $action->augmentColumns($this->gridField, $columns);
        $expected = array('Actions');
        $this->assertEquals($expected, $columns);

        $columns = array('Actions');
        $action->augmentColumns($this->gridField, $columns);
        $expected = array('Actions');
        $this->assertEquals($expected, $columns);
    }

    public function testGetColumnAttributes()
    {
        $action = new CommentsGridFieldAction();
        $record = new Comment();
        $attrs = $action->getColumnAttributes($this->gridField, $record, Comment::class);
        $this->assertEquals(array('class' => 'col-buttons'), $attrs);
    }

    public function testGetColumnMetadata()
    {
        $action = new CommentsGridFieldAction();
        $result = $action->getColumnMetadata($this->gridField, 'Actions');
        $this->assertEquals(array('title' => ''), $result);
        $result = $action->getColumnMetadata($this->gridField, 'SomethingElse');
        $this->assertNull($result);
    }

    public function testGetColumnsHandled()
    {
        $action = new CommentsGridFieldAction();
        $result = $action->getColumnsHandled($this->gridField);
        $this->assertEquals(array('Actions'), $result);
    }

    public function testGetColumnContent()
    {
        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $action = new CommentsGridFieldAction();
        $record = new Comment();
        $record->Name = 'Name of commeter';
        $record->Comment = 'This is a comment';
        $record->write();
        $recordID = $record->ID;
        $html = $action->getColumnContent($this->gridField, $record, Comment::class);
        $this->assertStringContainsString('data-url="admin/comments/mockform/field/testfield', $html);

        $this->assertStringContainsString('value="Spam"', $html);
        $this->assertStringContainsString('id="action_CustomAction' . $recordID . 'Spam"', $html);

        $this->assertStringContainsString('value="Approve"', $html);
        $this->assertStringContainsString('id="action_CustomAction' . $recordID . 'Approve"', $html);

        // If marked as spam, only the approve button should be available
        $record->markSpam();
        $record->write();
        $html = $action->getColumnContent($this->gridField, $record, Comment::class);
        $this->assertStringContainsString('value="Approve"', $html);
        $this->assertStringNotContainsString('value="Spam"', $html);

        // If marked as spam, only the approve button should be available
        $record->markApproved();
        $record->write();
        $html = $action->getColumnContent($this->gridField, $record, Comment::class);
        $this->assertStringNotContainsString('value="Approve"', $html);
        $this->assertStringContainsString('value="Spam"', $html);
    }

    public function testGetActions()
    {
        $action = new CommentsGridFieldAction();
        $result = $action->getActions($this->gridField);
        $this->assertEquals(array('spam', 'approve'), $result);
    }

    public function testHandleAction()
    {
        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $item = new CommentableItem;
        $item->write();

        $action = new CommentsGridFieldAction();
        $record = new Comment();
        $record->Name = 'Name of commenter';
        $record->Comment = 'This is a comment';
        $record->ParentID = $item->ID;
        $record->ParentClass = $item->class;
        $record->write();
        $recordID = $record->ID;
        $arguments = array('RecordID' => $recordID);
        $data = array();
        $result = $action->handleAction($this->gridField, 'spam', $arguments, $data);
        $this->assertEquals(200, Controller::curr()->getResponse()->getStatusCode());
        $this->assertEquals(
            'Comment marked as spam.',
            Controller::curr()->getResponse()->getStatusDescription()
        );
        $record = DataObject::get_by_id(Comment::class, $recordID);
        $this->assertEquals(1, $record->Moderated);
        $this->assertEquals(1, $record->IsSpam);

        //getStatusDescription
        $result = $action->handleAction($this->gridField, 'approve', $arguments, $data);
        $this->assertEquals(200, Controller::curr()->getResponse()->getStatusCode());
        $this->assertEquals(
            'Comment approved.',
            Controller::curr()->getResponse()->getStatusDescription()
        );

        $record = DataObject::get_by_id(Comment::class, $recordID);
        $this->assertEquals(1, $record->Moderated);
        $this->assertEquals(0, $record->IsSpam);
    }
}
