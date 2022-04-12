<?php

namespace SilverStripe\Comments\Tests;

use SilverStripe\Comments\Extensions\CommentsExtension;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Comments\Tests\CommentTestHelper;
use SilverStripe\Comments\Tests\Stubs\CommentableItem;
use SilverStripe\Comments\Tests\Stubs\CommentableItemDisabled;
use SilverStripe\Comments\Tests\Stubs\CommentableItemEnabled;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\View\Requirements;

class CommentsExtensionTest extends FunctionalTest
{
    protected static $fixture_file = 'CommentsTest.yml';

    protected static $disable_themes = true;

    protected static $extra_dataobjects = [
        CommentableItem::class,
        CommentableItemEnabled::class,
        CommentableItemDisabled::class,
    ];

    protected static $required_extensions = [
        CommentableItem::class => [
            CommentsExtension::class,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Set good default values
        Config::modify()->merge(CommentsExtension::class, 'comments', [
            'enabled' => true,
            'enabled_cms' => false,
            'require_login' => false,
            'require_login_cms' => false,
            'required_permission' => false,
            'require_moderation_nonmembers' => false,
            'require_moderation' => false,
            'require_moderation_cms' => false,
            'frontend_moderation' => false,
            'Member' => false,
        ]);

        // Configure this dataobject
        Config::modify()->merge(CommentableItem::class, 'comments', [
            'enabled_cms' => true
        ]);
    }


    public function testGetCommentsOption()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', [
            'comments_holder_id' => 'some-option'
        ]);

        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $this->assertEquals('some-option', $item->getCommentsOption('comments_holder_id'));
    }

    public function testPopulateDefaults()
    {
        $this->markTestSkipped('TODO');
    }

    public function testUpdateSettingsFields()
    {
        $this->markTestSkipped('This needs SiteTree installed');
    }

    public function testGetModerationRequired()
    {

        // the 3 options take precedence in this order, executed if true
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation_cms' => true,
            'require_moderation' => true,
            'require_moderation_nonmembers' => true
        ));

        // With require moderation CMS set to true, the value of the field
        // 'ModerationRequired' is returned
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $item->ModerationRequired = 'None';
        $item->write();

        $this->assertEquals('None', $item->getModerationRequired());
        $item->ModerationRequired = 'Required';
        $item->write();

        $this->assertEquals('Required', $item->getModerationRequired());

        $item->ModerationRequired = 'NonMembersOnly';
        $item->write();

        $this->assertEquals('NonMembersOnly', $item->getModerationRequired());

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation_cms' => false,
            'require_moderation' => true,
            'require_moderation_nonmembers' => true
        ));
        $this->assertEquals('Required', $item->getModerationRequired());

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation_cms' => false,
            'require_moderation' => false,
            'require_moderation_nonmembers' => true
        ));
        $this->assertEquals('NonMembersOnly', $item->getModerationRequired());

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation_cms' => false,
            'require_moderation' => false,
            'require_moderation_nonmembers' => false
        ));
        $this->assertEquals('None', $item->getModerationRequired());
    }

    public function testGetCommentsRequireLogin()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_login_cms' => true
        ));

        // With require moderation CMS set to true, the value of the field
        // 'ModerationRequired' is returned
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $item->CommentsRequireLogin = true;
        $this->assertTrue($item->getCommentsRequireLogin());
        $item->CommentsRequireLogin = false;
        $this->assertFalse($item->getCommentsRequireLogin());

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_login_cms' => false,
            'require_login' => false
        ));
        $this->assertFalse($item->getCommentsRequireLogin());
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_login_cms' => false,
            'require_login' => true
        ));
        $this->assertTrue($item->getCommentsRequireLogin());
    }

    public function testAllComments()
    {
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $this->assertEquals(4, $item->AllComments()->count());
    }

    public function testAllVisibleComments()
    {
        $this->logOut();

        $item = $this->objFromFixture(CommentableItem::class, 'second');
        $this->assertEquals(2, $item->AllVisibleComments()->count());
    }

    public function testComments()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => false
        ));

        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $this->assertEquals(4, $item->Comments()->count());

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => true
        ));

        $this->assertEquals(1, $item->Comments()->count());
    }

    public function testGetCommentsEnabled()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'enabled_cms' => true
        ));

        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $this->assertTrue($item->getCommentsEnabled());

        $item->ProvideComments = 0;
        $this->assertFalse($item->getCommentsEnabled());
    }

    public function testGetCommentHolderID()
    {
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'comments_holder_id' => 'commentid_test1',
        ));
        $this->assertEquals('commentid_test1', $item->getCommentHolderID());

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'comments_holder_id' => 'commtentid_test_another',
        ));
        $this->assertEquals('commtentid_test_another', $item->getCommentHolderID());
    }


    public function testGetPostingRequiredPermission()
    {
        $this->markTestSkipped('TODO');
    }

    public function testCanModerateComments()
    {
        // ensure nobody logged in
        if (Member::currentUser()) {
            Member::currentUser()->logOut();
        }

        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $this->assertFalse($item->canModerateComments());

        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $this->assertTrue($item->canModerateComments());
    }

    public function testGetCommentRSSLink()
    {
        Config::modify()->merge('SilverStripe\\Control\\Director', 'alternate_base_url', 'http://unittesting.local');

        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $link = $item->getCommentRSSLink();
        $this->assertEquals('http://unittesting.local/comments/rss', $link);
    }

    public function testGetCommentRSSLinkPage()
    {
        Config::modify()->merge('SilverStripe\\Control\\Director', 'alternate_base_url', 'http://unittesting.local');

        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $page = $item->getCommentRSSLinkPage();
        $this->assertEquals(
            'http://unittesting.local/comments/rss/SilverStripe-Comments-Tests-Stubs-CommentableItem/' . $item->ID,
            $page
        );
    }

    public function testCommentsForm()
    {
        $this->logInWithPermission('ADMIN');

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'include_js' => false,
            'comments_holder_id' => 'comments-holder',
        ));

        $item = $this->objFromFixture(CommentableItem::class, 'first');

        // The comments form is HTML to do assertions by contains
        $cf = (string) $item->CommentsForm();
        $expected = '/comments/CommentsForm/" method="POST" enctype="application/x-www-form-urlencoded">';

        $this->assertStringContainsString($expected, $cf);
        $this->assertStringContainsString('<h4>Post your comment</h4>', $cf);
        // check the comments form exists
        $expected = '<input type="text" name="Name"';
        $this->assertStringContainsString($expected, $cf);

        $expected = '<input type="email" name="Email"';
        $this->assertStringContainsString($expected, $cf);

        $expected = '<input type="text" name="URL"';
        $this->assertStringContainsString($expected, $cf);

        $expected = '<input type="hidden" name="ParentID"';
        $this->assertStringContainsString($expected, $cf);

        $expected = '<textarea name="Comment"';
        $this->assertStringContainsString($expected, $cf);

        $expected = '<input type="submit" name="action_doPostComment" value="Post" class="action"';
        $this->assertStringContainsString($expected, $cf);

        $expected = '/comments/spam/';
        $this->assertStringContainsString($expected, $cf);

        $expected = '<p>Reply to firstComA 1</p>';
        $this->assertStringContainsString($expected, $cf);

        $expected = '/comments/delete';
        $this->assertStringContainsString($expected, $cf);

        $expected = '<p>Reply to firstComA 2</p>';
        $this->assertStringContainsString($expected, $cf);

        $expected = '<p>Reply to firstComA 3</p>';
        $this->assertStringContainsString($expected, $cf);
    }

    public function testAttachedToSiteTree()
    {
        $this->markTestSkipped('TODO');
    }

    public function testPagedComments()
    {
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        // Ensure Created times are set, as order not guaranteed if all set to 0
        $comments = $item->PagedComments()->sort('ID');
        $ctr = 0;
        $timeBase = time()-10000;
        foreach ($comments as $comment) {
            $comment->Created = $timeBase + $ctr * 1000;
            $comment->write();
            $ctr++;
        }

        $results = $item->PagedComments()->toArray();

        foreach ($results as $result) {
            $result->sourceQueryParams = null;
        }

        $this->assertEquals(
            $this->objFromFixture(Comment::class, 'firstComA')->Comment,
            $results[3]->Comment
        );
        $this->assertEquals(
            $this->objFromFixture(Comment::class, 'firstComAChild1')->Comment,
            $results[2]->Comment
        );
        $this->assertEquals(
            $this->objFromFixture(Comment::class, 'firstComAChild2')->Comment,
            $results[1]->Comment
        );
        $this->assertEquals(
            $this->objFromFixture(Comment::class, 'firstComAChild3')->Comment,
            $results[0]->Comment
        );

        $this->assertEquals(4, sizeof($results ?? []));
    }

    public function testUpdateModerationFields()
    {
        $this->markTestSkipped('TODO');
    }

    public function testUpdateCMSFields()
    {
        Config::modify()->merge(
            CommentableItem::class,
            'comments',
            array(
                'require_login_cms' => false
            )
        );
        $this->logInWithPermission('ADMIN');
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $item->ProvideComments = true;
        $item->write();
        $fields = $item->getCMSFields();
        // print_r($item->getCMSFields());

        CommentTestHelper::assertFieldsForTab(
            $this,
            'Root.Comments.CommentsNewCommentsTab',
            array('NewComments'),
            $fields
        );

        CommentTestHelper::assertFieldsForTab(
            $this,
            'Root.Comments.CommentsCommentsTab',
            array('ApprovedComments'),
            $fields
        );

        CommentTestHelper::assertFieldsForTab(
            $this,
            'Root.Comments.CommentsSpamCommentsTab',
            array('SpamComments'),
            $fields
        );

        Config::modify()->merge(
            CommentableItem::class,
            'comments',
            array(
                'require_login_cms' => true
            )
        );
        $fields = $item->getCMSFields();
        CommentTestHelper::assertFieldsForTab($this, 'Root.Settings', array('Comments'), $fields);
        $settingsTab = $fields->findOrMakeTab('Root.Settings');
        $settingsChildren = $settingsTab->getChildren();
        $this->assertEquals(1, $settingsChildren->count());
        $fieldGroup = $settingsChildren->first();
        $fields = $fieldGroup->getChildren();
        CommentTestHelper::assertFieldNames(
            $this,
            array('ProvideComments', 'CommentsRequireLogin'),
            $fields
        );

        Config::modify()->merge(
            CommentableItem::class,
            'comments',
            array(
                'require_login_cms' => true,
                'require_moderation_cms' => true
            )
        );

        $fields = $item->getCMSFields();
        CommentTestHelper::assertFieldsForTab(
            $this,
            'Root.Settings',
            array('Comments', 'ModerationRequired'),
            $fields
        );
        $settingsTab = $fields->findOrMakeTab('Root.Settings');
        $settingsChildren = $settingsTab->getChildren();
        $this->assertEquals(2, $settingsChildren->count());
        $fieldGroup = $settingsChildren->first();
        $fields = $fieldGroup->getChildren();
        CommentTestHelper::assertFieldNames(
            $this,
            array('ProvideComments', 'CommentsRequireLogin'),
            $fields
        );
    }
}
