<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Comments\Controllers\CommentingController;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Comments\Model\Comment\SecurityToken as CommentSecurityToken;
use SilverStripe\Comments\Tests\Stubs\CommentableItem;
use SilverStripe\Comments\Tests\CommentTestHelper;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Email\Email;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;

/**
 * @package comments
 * @subpackage tests
 */
class CommentingControllerTest extends FunctionalTest
{
    /**
     * {@inheritDoc}
     */
    protected static $fixture_file = 'CommentsTest.yml';

    /**
     * {@inheritDoc}
     */
    protected $extraDataObjects = array(
        CommentableItem::class
    );

    protected $securityEnabled;

    public function tearDown()
    {
        if ($this->securityEnabled) {
            SecurityToken::inst()->enable();
        } else {
            SecurityToken::inst()->disable();
        }
        parent::tearDown();
    }

    public function setUp()
    {
        parent::setUp();
        $this->securityEnabled = SecurityToken::inst()->is_enabled();

        // We will assert against explicit responses, unless handed otherwise in a test for redirects
        $this->autoFollowRedirection = false;
    }

    public function testApproveUnmoderatedComment()
    {
        SecurityToken::inst()->disable();

        // mark a comment as spam then approve it
        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $comment = $this->objFromFixture(Comment::class, 'testModeratedComment1');
        $st = new CommentSecurityToken($comment);
        $url = 'comments/approve/' . $comment->ID;
        $url = $st->addToUrl($url, Member::currentUser());
        $response = $this->get($url, null, ['Referer' => '/']);
        $this->assertEquals(302, $response->getStatusCode());
        $comment = DataObject::get_by_id(Comment::class, $comment->ID);

        // Need to use 0,1 here instead of false, true for SQLite
        $this->assertEquals(0, $comment->IsSpam);
        $this->assertEquals(1, $comment->Moderated);

        // try and approve a non existent comment
        $response = $this->get('comments/approve/100000');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSetGetOwnerController()
    {
        $commController = new CommentingController();
        $commController->setOwnerController(Controller::curr());
        $this->assertEquals(Controller::curr(), $commController->getOwnerController());
        $commController->setOwnerController(null);
        $this->assertNull($commController->getOwnerController());
    }

    public function testHam()
    {
        SecurityToken::inst()->disable();

        // mark a comment as spam then ham it
        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $comment->markSpam();
        $st = new CommentSecurityToken($comment);
        $url = 'comments/ham/' . $comment->ID;
        $url = $st->addToUrl($url, Member::currentUser());
        $response = $this->get($url);
        $this->assertEquals(302, $response->getStatusCode());
        $comment = DataObject::get_by_id(Comment::class, $comment->ID);

        // Need to use 0,1 here instead of false, true for SQLite
        $this->assertEquals(0, $comment->IsSpam);
        $this->assertEquals(1, $comment->Moderated);

        // try and ham a non existent comment
        $response = $this->get('comments/ham/100000');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSpam()
    {
        // mark a comment as approved then spam it
        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $comment->markApproved();
        $st = new CommentSecurityToken($comment);
        $url = 'comments/spam/' . $comment->ID;
        $url = $st->addToUrl($url, Member::currentUser());
        $response = $this->get($url);
        $this->assertEquals(302, $response->getStatusCode());
        $comment = DataObject::get_by_id(Comment::class, $comment->ID);

        // Need to use 0,1 here instead of false, true for SQLite
        $this->assertEquals(1, $comment->IsSpam);
        $this->assertEquals(1, $comment->Moderated);

        // try and spam a non existent comment
        $response = $this->get('comments/spam/100000');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRSS()
    {
        // Delete the newly added children of firstComA so as not to have to recalculate values below
        $this->objFromFixture(Comment::class, 'firstComAChild1')->delete();
        $this->objFromFixture(Comment::class, 'firstComAChild2')->delete();
        $this->objFromFixture(Comment::class, 'firstComAChild3')->delete();

        $item = $this->objFromFixture(CommentableItem::class, 'first');

        // comments sitewide
        $response = $this->get('comments/rss');
        $comment = "10 approved, non spam comments on page 1";
        $this->assertEquals(10, substr_count($response->getBody(), "<item>"), $comment);

        $response = $this->get('comments/rss?start=10');
        $this->assertEquals(4, substr_count($response->getBody(), "<item>"), "3 approved, non spam comments on page 2");

        // all comments on a type
        $response = $this->get('comments/rss/SilverStripe-Comments-Tests-Stubs-CommentableItem');
        $this->assertEquals(10, substr_count($response->getBody(), "<item>"));

        $response = $this->get('comments/rss/SilverStripe-Comments-Tests-Stubs-CommentableItem?start=10');
        $this->assertEquals(4, substr_count($response->getBody(), "<item>"), "3 approved, non spam comments on page 2");

        // specific page
        $response = $this->get('comments/rss/SilverStripe-Comments-Tests-Stubs-CommentableItem/'.$item->ID);
        $this->assertEquals(1, substr_count($response->getBody(), "<item>"));
        $this->assertContains('<dc:creator>FA</dc:creator>', $response->getBody());

        // test accessing comments on a type that doesn't exist
        $response = $this->get('comments/rss/Fake');
        $this->assertEquals(404, $response->getStatusCode());
    }

    // This is returning a 404 which looks logical code wise but also a bit weird.
    // Test module on a clean install and check what the actual URL is first
/*    public function testReply() {
        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $item = $this->objFromFixture('CommentableItem', 'first');

        $st = new CommentSecurityToken($comment);
        $url = 'comments/reply/' . $item->ID.'?ParentCommentID=' . $comment->ID;
        error_log($url);
        $response = $this->get($url);
        error_log(print_r($response,1));

        $this->assertEquals(200, $response->getStatusCode());

    }
*/
/*
    public function testCommentsFormLoadMemberData() {
        Config::inst()->update('CommentableItem', 'comments', array(
            'use_preview' => false
        ));
        $this->logInAs('visitor');
        SecurityToken::inst()->disable();
        $parent = $this->objFromFixture('CommentableItem', 'first');
        $parent->CommentsRequireLogin = true;
        $parent->PostingRequiredPermission = true;
        //$parent->write();
        $commController = new CommentingController();
        $commController->setOwnerRecord($parent);

        $form = $commController->CommentsForm();
        $commentsFields = $form->Fields()->first()->FieldList();
        $expected = array('Name', 'Email', 'URL', 'Comment', 'PreviewComment');
        CommentTestHelper::assertFieldNames($this, $expected, $commentsFields);
    }
*/

    public function testCommentsFormUsePreview()
    {
        // test with preview on
        Config::inst()->update(CommentableItem::class, 'comments', array(
            'use_preview' => true
        ));

        $this->objFromFixture(Comment::class, 'firstComAChild1')->delete();
        $this->objFromFixture(Comment::class, 'firstComAChild2')->delete();
        $this->objFromFixture(Comment::class, 'firstComAChild3')->delete();

        SecurityToken::inst()->disable();
        $this->autoFollowRedirection = false;
        $parent = $this->objFromFixture(CommentableItem::class, 'first');
        $commController = new CommentingController();
        $commController->setOwnerRecord($parent);

        $form = $commController->CommentsForm();
        $commentsFields = $form->Fields()->first()->FieldList();
        $expected = array('Name', 'Email', 'URL', 'Comment', 'PreviewComment');
        CommentTestHelper::assertFieldNames($this, $expected, $commentsFields);

        // Turn off preview.  Assert lack of preview field
        Config::inst()->update(CommentableItem::class, 'comments', array(
            'use_preview' => false
        ));
        $form = $commController->CommentsForm();
        $commentsFields = $form->Fields()->first()->FieldList();
        $expected = array('Name', 'Email', 'URL', 'Comment');
        CommentTestHelper::assertFieldNames($this, $expected, $commentsFields);
    }

    public function testCommentsForm()
    {
        $this->autoFollowRedirection = true;

        // Delete the newly added children of firstComA so as not to change this test
        $this->objFromFixture(Comment::class, 'firstComAChild1')->delete();
        $this->objFromFixture(Comment::class, 'firstComAChild2')->delete();
        $this->objFromFixture(Comment::class, 'firstComAChild3')->delete();

        SecurityToken::inst()->disable();
        $this->autoFollowRedirection = false;
        $parent = $this->objFromFixture(CommentableItem::class, 'first');

        // Test posting to base comment
        $response = $this->post(
            'comments/CommentsForm',
            array(
                'Name' => 'Poster',
                'Email' => 'guy@test.com',
                'Comment' => 'My Comment',
                'ParentID' => $parent->ID,
                'ParentClassName' => CommentableItem::class,
                'action_doPostComment' => 'Post'
            )
        );
        $this->assertEquals(302, $response->getStatusCode());
        // $this->assertStringStartsWith('CommentableItemController#comment-', $response->getHeader('Location'));
        $this->assertDOSEquals(
            array(
                array(
                    'Name' => 'Poster',
                    'Email' => 'guy@test.com',
                    'Comment' => 'My Comment',
                    'ParentID' => $parent->ID,
                    'ParentClass' => CommentableItem::class,
                )
            ),
            Comment::get()->filter('Email', 'guy@test.com')
        );

        // Test posting to parent comment
        $parentComment = $this->objFromFixture(Comment::class, 'firstComA');
        $this->assertEquals(0, $parentComment->ChildComments()->count());

        $response = $this->post(
            'comments/reply/' . $parentComment->ID,
            array(
                'Name' => 'Test Author',
                'Email' => 'test@test.com',
                'Comment' => 'Making a reply to firstComA',
                'ParentID' => $parent->ID,
                'ParentClassName' => CommentableItem::class,
                'ParentCommentID' => $parentComment->ID,
                'action_doPostComment' => 'Post'
            )
        );
        $this->assertEquals(302, $response->getStatusCode());
        // $this->assertStringStartsWith('CommentableItemController#comment-', $response->getHeader('Location'));
        $this->assertDOSEquals(
            array(
                array(
                    'Name' => 'Test Author',
                    'Email' => 'test@test.com',
                    'Comment' => 'Making a reply to firstComA',
                    'ParentID' => $parent->ID,
                    'ParentClass' => CommentableItem::class,
                    'ParentCommentID' => $parentComment->ID
                )
            ),
            $parentComment->ChildComments()
        );
    }

    /**
     * SS4 introduces namespaces. They don't work in URLs, so we encode and decode them here.
     */
    public function testEncodeClassName()
    {
        $controller = new CommentingController;
        $this->assertSame('SilverStripe-Comments-Model-Comment', $controller->encodeClassName(Comment::class));
    }

    public function testDecodeClassName()
    {
        $controller = new CommentingController;
        $this->assertSame(Comment::class, $controller->decodeClassName('SilverStripe-Comments-Model-Comment'));
    }
}
