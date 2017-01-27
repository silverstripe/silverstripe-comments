<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Comments\Admin\CommentsGridField;
use SilverStripe\Comments\Admin\CommentsGridFieldAction;
use SilverStripe\Comments\Admin\CommentsGridFieldConfig;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\Tests\GridField\GridFieldTest\Team;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class CommentsGridFieldActionTest extends SapphireTest
{
    protected $usesDatabase = true;

    /** @var ArrayList */
    protected $list;

    /** @var GridField */
    protected $gridField;

    /** @var Form */
    protected $form;

    public function setUp()
    {
        parent::setUp();
        $this->list = new DataList(Team::class);
        $config = CommentsGridFieldConfig::create()->addComponent(new GridFieldDeleteAction());
        $this->gridField = new CommentsGridField('testfield', 'testfield', $this->list, $config);
        $this->form = new Form(new Controller(), 'mockform', new FieldList(array($this->gridField)), new FieldList());
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
        $this->assertContains('data-url="Controller/mockform/field/testfield', $html);
        $spamAction = 'value="Spam" class="action" id="action_CustomAction' . $recordID . 'Spam"';
        $this->assertContains($spamAction, $html);

        $approveAction = 'value="Approve" class="action" id="action_CustomAction' . $recordID . 'Approve"';
        $this->assertContains($approveAction, $html);

        // If marked as spam, only the approve button should be available
        $record->markSpam();
        $record->write();
        $html = $action->getColumnContent($this->gridField, $record, Comment::class);
        $this->assertContains($approveAction, $html);
        $this->assertNotContains($spamAction, $html);

        // If marked as spam, only the approve button should be available
        $record->markApproved();
        $record->write();
        $html = $action->getColumnContent($this->gridField, $record, Comment::class);
        $this->assertNotContains($approveAction, $html);
        $this->assertContains($spamAction, $html);
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
        $action = new CommentsGridFieldAction();
        $record = new Comment();
        $record->Name = 'Name of commenter';
        $record->Comment = 'This is a comment';
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
