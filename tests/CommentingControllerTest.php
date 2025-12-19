<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Akismet\AkismetSpamProtector;
use SilverStripe\Comments\Controllers\CommentingController;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Comments\Model\Comment\SecurityToken as CommentSecurityToken;
use SilverStripe\Comments\Tests\Stubs\ExampleDataObject;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Security\Security;

class CommentingControllerTest extends FunctionalTest
{
    /**
     * {@inheritDoc}
     */
    protected static $fixture_file = 'CommentsTest.yml';

    /**
     * {@inheritDoc}
     */
    protected static $extra_dataobjects = [
        ExampleDataObject::class
    ];

    protected $securityEnabled;

    protected function tearDown(): void
    {
        if ($this->securityEnabled) {
            SecurityToken::inst()->enable();
        } else {
            SecurityToken::inst()->disable();
        }
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityEnabled = SecurityToken::inst()->is_enabled();

        // We will assert against explicit responses, unless handed otherwise in a test for redirects
        $this->autoFollowRedirection = false;
    }

    public function testCommentsFormUsePreview()
    {
        $parent = $this->objFromFixture(ExampleDataObject::class, 'first');
        $commController = CommentingController::create();
        $commController->setOwnerRecord($parent);
        $form = $commController->CommentsForm();

        $commentsFields = $form->Fields()->first()->FieldList();
        $expected = array('Name', 'Email', 'URL', 'Comment');
        CommentTestHelper::assertFieldNames($this, $expected, $commentsFields);

        // test with preview on
        Config::modify()->merge(ExampleDataObject::class, 'comments', array(
            'use_preview' => true
        ));

        $parent = $this->objFromFixture(ExampleDataObject::class, 'first');
        $commController = CommentingController::create();
        $commController->setOwnerRecord($parent);

        $this->objFromFixture(Comment::class, 'firstComAChild1')->delete();
        $this->objFromFixture(Comment::class, 'firstComAChild2')->delete();
        $this->objFromFixture(Comment::class, 'firstComAChild3')->delete();

        SecurityToken::inst()->disable();
        $this->autoFollowRedirection = false;

        $form = $commController->CommentsForm();
        $commentsFields = $form->Fields()->first()->FieldList();
        $expected = array('Name', 'Email', 'URL', 'Comment', 'PreviewComment');
        CommentTestHelper::assertFieldNames($this, $expected, $commentsFields);
    }

    public function testApproveUnmoderatedComment()
    {
        SecurityToken::inst()->disable();

        // mark a comment as spam then approve it
        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $comment = $this->objFromFixture(Comment::class, 'testModeratedComment1');
        $st = new CommentSecurityToken($comment);
        $url = 'comments/approve/' . $comment->ID;
        $url = $st->addToUrl($url, Security::getCurrentUser());
        $response = $this->get($url, null, ['Referer' => '/']);
        $this->assertEquals(302, $response->getStatusCode());
        $comment = Comment::get()->byID($comment->ID);

        // Need to use 0,1 here instead of false, true for SQLite
        $this->assertEquals(0, $comment->IsSpam);
        $this->assertEquals(1, $comment->Moderated);

        // try and approve a non existent comment
        $response = $this->get('comments/approve/100000');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSetGetOwnerController()
    {
        $commController = CommentingController::create();
        $commController->setOwnerController(Controller::curr());
        $this->assertEquals(Controller::curr(), $commController->getOwnerController());
        $commController->setOwnerController(null);
        $this->assertNull($commController->getOwnerController()?->getValue());
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
        $url = $st->addToUrl($url, Security::getCurrentUser());
        $response = $this->get($url);
        $this->assertEquals(302, $response->getStatusCode());
        $comment = Comment::get()->byID($comment->ID);

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
        $url = $st->addToUrl($url, Security::getCurrentUser());
        $response = $this->get($url);
        $this->assertEquals(302, $response->getStatusCode());
        $comment = Comment::get()->byID($comment->ID);

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

        $item = $this->objFromFixture(ExampleDataObject::class, 'first');

        // comments sitewide
        $response = $this->get('comments/rss');
        $comment = "10 approved, non spam comments on page 1";
        $this->assertEquals(10, substr_count($response->getBody(), "<item>"), $comment);

        $response = $this->get('comments/rss?start=10');
        $this->assertEquals(
            4,
            substr_count($response->getBody(), "<item>"),
            "3 approved, non spam comments on page 2"
        );

        // all comments on a type
        $response = $this->get('comments/rss/SilverStripe-Comments-Tests-Stubs-ExampleDataObject');
        $this->assertEquals(10, substr_count($response->getBody(), "<item>"));

        $response = $this->get('comments/rss/SilverStripe-Comments-Tests-Stubs-ExampleDataObject?start=10');
        $this->assertEquals(
            4,
            substr_count($response->getBody() ?? '', "<item>"),
            "3 approved, non spam comments on page 2"
        );

        // specific page
        $response = $this->get('comments/rss/SilverStripe-Comments-Tests-Stubs-ExampleDataObject/'.$item->ID);
        $this->assertEquals(1, substr_count($response->getBody() ?? '', "<item>"));
        $this->assertStringContainsString('<dc:creator>FA</dc:creator>', $response->getBody());

        // test accessing comments on a type that doesn't exist
        $response = $this->get('comments/rss/Fake');
        $this->assertEquals(404, $response->getStatusCode());
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
        $parent = $this->objFromFixture(ExampleDataObject::class, 'first');

        // Test posting to base comment
        $response = $this->post(
            'comments/CommentsForm',
            array(
                'Name' => 'Poster',
                'Email' => 'guy@test.com',
                'Comment' => 'My Comment',
                'ParentID' => $parent->ID,
                'ParentClassName' => ExampleDataObject::class,
                'action_doPostComment' => 'Post'
            )
        );

        $this->assertEquals(302, $response->getStatusCode());

        $this->assertListEquals(
            array(
                array(
                    'Name' => 'Poster',
                    'Email' => 'guy@test.com',
                    'Comment' => 'My Comment',
                    'ParentID' => $parent->ID,
                    'ParentClass' => ExampleDataObject::class,
                )
            ),
            Comment::get()->filter('Email', 'guy@test.com')
        );

        // Test posting to parent comment
        /** @var Comment $parentComment */
        $parentComment = $this->objFromFixture(Comment::class, 'firstComA');
        $this->assertEquals(0, $parentComment->ChildComments()->count());

        $response = $this->post(
            'comments/reply/' . $parentComment->ID,
            array(
                'Name' => 'Test Author',
                'Email' => 'test@test.com',
                'Comment' => 'Making a reply to firstComA',
                'ParentID' => $parent->ID,
                'ParentClassName' => ExampleDataObject::class,
                'ParentCommentID' => $parentComment->ID,
                'action_doPostComment' => 'Post'
            )
        );
        $this->assertEquals(302, $response->getStatusCode());

        $this->assertListEquals(
            array(
                array(
                    'Name' => 'Test Author',
                    'Email' => 'test@test.com',
                    'Comment' => 'Making a reply to firstComA',
                    'ParentID' => $parent->ID,
                    'ParentClass' => ExampleDataObject::class,
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
