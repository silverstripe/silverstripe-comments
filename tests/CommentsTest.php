<?php

namespace SilverStripe\Comments\Tests;

use HTMLPurifier_Config;
use HTMLPurifier;
use ReflectionClass;
use SilverStripe\Comments\Extensions\CommentsExtension;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Comments\Tests\Stubs\CommentableItem;
use SilverStripe\Comments\Tests\Stubs\CommentableItemDisabled;
use SilverStripe\Comments\Tests\Stubs\CommentableItemEnabled;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Email\Email;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;

class CommentsTest extends FunctionalTest
{
    protected static $fixture_file = 'CommentsTest.yml';

    protected static $extra_dataobjects = array(
        CommentableItem::class,
        CommentableItemEnabled::class,
        CommentableItemDisabled::class
    );

    public function setUp()
    {
        parent::setUp();

        // Set good default values
        Config::modify()->merge(CommentsExtension::class, 'comments', array(
            'enabled' => true,
            'comment_permalink_prefix' => 'comment-'
        ));
    }

    public function testCommentsList()
    {
        // comments don't require moderation so unmoderated comments can be
        // shown but not spam posts
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation_nonmembers' => false,
            'require_moderation' => false,
            'require_moderation_cms' => false,
        ));

        $item = $this->objFromFixture(CommentableItem::class, 'spammed');

        $this->assertDOSEquals(array(
            array('Name' => 'Comment 1'),
            array('Name' => 'Comment 3')
        ), $item->Comments(), 'Only 2 non spam posts should be shown');

        // when moderated, only moderated, non spam posts should be shown.
        Config::modify()->merge(CommentableItem::class, 'comments', array('require_moderation_nonmembers' => true));

        // Check that require_moderation overrides this option
        Config::modify()->merge(CommentableItem::class, 'comments', array('require_moderation' => true));

        $this->assertDOSEquals(array(
            array('Name' => 'Comment 3')
        ), $item->Comments(), 'Only 1 non spam, moderated post should be shown');
        $this->assertEquals(1, $item->Comments()->Count());

        // require_moderation_nonmembers still filters out unmoderated comments
        Config::modify()->merge(CommentableItem::class, 'comments', array('require_moderation' => false));
        $this->assertEquals(1, $item->Comments()->Count());

        Config::modify()->merge(CommentableItem::class, 'comments', array('require_moderation_nonmembers' => false));
        $this->assertEquals(2, $item->Comments()->Count());

        // With unmoderated comments set to display in frontend
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation' => true,
            'frontend_moderation' => true
        ));
        $this->assertEquals(1, $item->Comments()->Count());

        $this->logInWithPermission('ADMIN');
        $this->assertEquals(2, $item->Comments()->Count());

        // With spam comments set to display in frontend
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation' => true,
            'frontend_moderation' => false,
            'frontend_spam' => true,
        ));
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $this->assertEquals(1, $item->Comments()->Count());

        $this->logInWithPermission('ADMIN');
        $this->assertEquals(2, $item->Comments()->Count());


        // With spam and unmoderated comments set to display in frontend
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation' => true,
            'frontend_moderation' => true,
            'frontend_spam' => true,
        ));
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $this->assertEquals(1, $item->Comments()->Count());

        $this->logInWithPermission('ADMIN');
        $this->assertEquals(4, $item->Comments()->Count());
    }

    /**
     * Test moderation options configured via the CMS
     */
    public function testCommentCMSModerationList()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_moderation' => true,
            'require_moderation_cms' => true,
        ));

        $item = $this->objFromFixture(CommentableItem::class, 'spammed');

        $this->assertEquals('None', $item->getModerationRequired());

        $this->assertDOSEquals(array(
            array('Name' => 'Comment 1'),
            array('Name' => 'Comment 3')
        ), $item->Comments(), 'Only 2 non spam posts should be shown');

        // when moderated, only moderated, non spam posts should be shown.
        $item->ModerationRequired = 'NonMembersOnly';
        $item->write();

        $this->assertEquals('NonMembersOnly', $item->getModerationRequired());

        // Check that require_moderation overrides this option
        $item->ModerationRequired = 'Required';
        $item->write();
        $this->assertEquals('Required', $item->getModerationRequired());

        $this->assertDOSEquals(array(
            array('Name' => 'Comment 3')
        ), $item->Comments(), 'Only 1 non spam, moderated post should be shown');
        $this->assertEquals(1, $item->Comments()->Count());

        // require_moderation_nonmembers still filters out unmoderated comments
        $item->ModerationRequired = 'NonMembersOnly';
        $item->write();
        $this->assertEquals(1, $item->Comments()->Count());

        $item->ModerationRequired = 'None';
        $item->write();
        $this->assertEquals(2, $item->Comments()->Count());
    }

    public function testCanPostComment()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_login' => false,
            'require_login_cms' => false,
            'required_permission' => false,
        ));
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $item2 = $this->objFromFixture(CommentableItem::class, 'second');

        // Test restriction free commenting
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $this->assertFalse($item->CommentsRequireLogin);
        $this->assertTrue($item->canPostComment());

        // Test permission required to post
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_login' => true,
            'required_permission' => 'POSTING_PERMISSION',
        ));
        $this->assertTrue($item->CommentsRequireLogin);
        $this->assertFalse($item->canPostComment());
        $this->logInWithPermission('WRONG_ONE');
        $this->assertFalse($item->canPostComment());
        $this->logInWithPermission('POSTING_PERMISSION');
        $this->assertTrue($item->canPostComment());
        $this->logInWithPermission('ADMIN');
        $this->assertTrue($item->canPostComment());

        // Test require login to post, but not any permissions
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'required_permission' => false,
        ));
        $this->assertTrue($item->CommentsRequireLogin);
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $this->assertFalse($item->canPostComment());
        $this->logInWithPermission('ANY_PERMISSION');
        $this->assertTrue($item->canPostComment());

        // Test options set via CMS
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'require_login' => true,
            'require_login_cms' => true,
        ));
        $this->assertFalse($item->CommentsRequireLogin);
        $this->assertTrue($item2->CommentsRequireLogin);
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $this->assertTrue($item->canPostComment());
        $this->assertFalse($item2->canPostComment());

        // Login grants permission to post
        $this->logInWithPermission('ANY_PERMISSION');
        $this->assertTrue($item->canPostComment());
        $this->assertTrue($item2->canPostComment());
    }
    public function testDeleteComment()
    {
        // Test anonymous user
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $commentID = $comment->ID;
        $this->assertNull($comment->DeleteLink(), 'No permission to see delete link');
        $delete = $this->get('comments/delete/' . $comment->ID . '?ajax=1');
        $this->assertEquals(403, $delete->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertTrue($check && $check->exists());

        // Test non-authenticated user
        $this->logInAs('visitor');
        $this->assertNull($comment->DeleteLink(), 'No permission to see delete link');

        // Test authenticated user
        $this->logInAs('commentadmin');
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $commentID = $comment->ID;
        $adminComment1Link = $comment->DeleteLink();
        $this->assertContains('comments/delete/' . $commentID . '?t=', $adminComment1Link);

        // Test that this link can't be shared / XSS exploited
        $this->logInAs('commentadmin2');
        $delete = $this->get($adminComment1Link);
        $this->assertEquals(400, $delete->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertTrue($check && $check->exists());

        // Test that this other admin can delete the comment with their own link
        $adminComment2Link = $comment->DeleteLink();
        $this->assertNotEquals($adminComment2Link, $adminComment1Link);
        $this->autoFollowRedirection = false;
        $delete = $this->get($adminComment2Link);
        $this->assertEquals(302, $delete->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertFalse($check && $check->exists());
    }

    public function testSpamComment()
    {
        // Test anonymous user
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $commentID = $comment->ID;
        $this->assertNull($comment->SpamLink(), 'No permission to see mark as spam link');
        $spam = $this->get('comments/spam/'.$comment->ID.'?ajax=1');
        $this->assertEquals(403, $spam->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertEquals(0, $check->IsSpam, 'No permission to mark as spam');

        // Test non-authenticated user
        $this->logInAs('visitor');
        $this->assertNull($comment->SpamLink(), 'No permission to see mark as spam link');

        // Test authenticated user
        $this->logInAs('commentadmin');
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $commentID = $comment->ID;
        $adminComment1Link = $comment->SpamLink();
        $this->assertContains('comments/spam/' . $commentID . '?t=', $adminComment1Link);

        // Test that this link can't be shared / XSS exploited
        $this->logInAs('commentadmin2');
        $spam = $this->get($adminComment1Link);
        $this->assertEquals(400, $spam->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $comment->ID);
        $this->assertEquals(0, $check->IsSpam, 'No permission to mark as spam');

        // Test that this other admin can spam the comment with their own link
        $adminComment2Link = $comment->SpamLink();
        $this->assertNotEquals($adminComment2Link, $adminComment1Link);
        $this->autoFollowRedirection = false;
        $spam = $this->get($adminComment2Link);
        $this->assertEquals(302, $spam->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertEquals(1, $check->IsSpam);

        // Cannot re-spam spammed comment
        $this->assertNull($check->SpamLink());
    }

    public function testHamComment()
    {
        // Test anonymous user
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $comment = $this->objFromFixture(Comment::class, 'secondComC');
        $commentID = $comment->ID;
        $this->assertNull($comment->HamLink(), 'No permission to see mark as ham link');
        $ham = $this->get('comments/ham/' . $comment->ID . '?ajax=1');
        $this->assertEquals(403, $ham->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertEquals(1, $check->IsSpam, 'No permission to mark as ham');

        // Test non-authenticated user
        $this->logInAs('visitor');
        $this->assertNull($comment->HamLink(), 'No permission to see mark as ham link');

        // Test authenticated user
        $this->logInAs('commentadmin');
        $comment = $this->objFromFixture(Comment::class, 'secondComC');
        $commentID = $comment->ID;
        $adminComment1Link = $comment->HamLink();
        $this->assertContains('comments/ham/' . $commentID . '?t=', $adminComment1Link);

        // Test that this link can't be shared / XSS exploited
        $this->logInAs('commentadmin2');
        $ham = $this->get($adminComment1Link);
        $this->assertEquals(400, $ham->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $comment->ID);
        $this->assertEquals(1, $check->IsSpam, 'No permission to mark as ham');

        // Test that this other admin can ham the comment with their own link
        $adminComment2Link = $comment->HamLink();
        $this->assertNotEquals($adminComment2Link, $adminComment1Link);
        $this->autoFollowRedirection = false;
        $ham = $this->get($adminComment2Link);
        $this->assertEquals(302, $ham->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertEquals(0, $check->IsSpam);

        // Cannot re-ham hammed comment
        $this->assertNull($check->HamLink());
    }

    public function testApproveComment()
    {
        // Test anonymous user
        if ($member = Member::currentUser()) {
            $member->logOut();
        }
        $comment = $this->objFromFixture(Comment::class, 'secondComB');
        $commentID = $comment->ID;
        $this->assertNull($comment->ApproveLink(), 'No permission to see approve link');
        $approve = $this->get('comments/approve/' . $comment->ID . '?ajax=1');
        $this->assertEquals(403, $approve->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertEquals(0, $check->Moderated, 'No permission to approve');

        // Test non-authenticated user
        $this->logInAs('visitor');
        $this->assertNull($comment->ApproveLink(), 'No permission to see approve link');

        // Test authenticated user
        $this->logInAs('commentadmin');
        $comment = $this->objFromFixture(Comment::class, 'secondComB');
        $commentID = $comment->ID;
        $adminComment1Link = $comment->ApproveLink();
        $this->assertContains('comments/approve/' . $commentID . '?t=', $adminComment1Link);

        // Test that this link can't be shared / XSS exploited
        $this->logInAs('commentadmin2');
        $approve = $this->get($adminComment1Link);
        $this->assertEquals(400, $approve->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $comment->ID);
        $this->assertEquals(0, $check->Moderated, 'No permission to approve');

        // Test that this other admin can approve the comment with their own link
        $adminComment2Link = $comment->ApproveLink();
        $this->assertNotEquals($adminComment2Link, $adminComment1Link);
        $this->autoFollowRedirection = false;
        $approve = $this->get($adminComment2Link);
        $this->assertEquals(302, $approve->getStatusCode());
        $check = DataObject::get_by_id(Comment::class, $commentID);
        $this->assertEquals(1, $check->Moderated);

        // Cannot re-approve approved comment
        $this->assertNull($check->ApproveLink());
    }

    public function testCommenterURLWrite()
    {
        $comment = new Comment();
        // We only care about the CommenterURL, so only set that
        // Check a http and https URL. Add more test urls here as needed.
        $protocols = array(
            'Http',
            'Https',
        );
        $url = '://example.com';

        foreach ($protocols as $protocol) {
            $comment->CommenterURL = $protocol . $url;
            // The protocol should stay as if, assuming it is valid
            $comment->write();
            $this->assertEquals($comment->CommenterURL, $protocol . $url, $protocol . ':// is a valid protocol');
        }
    }

    public function testSanitizesWithAllowHtml()
    {
        if (!class_exists('\\HTMLPurifier')) {
            $this->markTestSkipped('HTMLPurifier class not found');
            return;
        }

        // Add p for paragraph
        // NOTE: The config method appears to append to the existing array
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'html_allowed_elements' => array('p'),
        ));

        // Without HTML allowed
        $comment1 = new Comment();
        $comment1->AllowHtml = false;
        $comment1->ParentClass = CommentableItem::class;
        $comment1->Comment = '<p><script>alert("w00t")</script>my comment</p>';
        $comment1->write();
        $this->assertEquals(
            '<p><script>alert("w00t")</script>my comment</p>',
            $comment1->Comment,
            'Does not remove HTML tags with html_allowed=false, ' .
            'which is correct behaviour because the HTML will be escaped'
        );

        // With HTML allowed
        $comment2 = new Comment();
        $comment2->AllowHtml = true;
        $comment2->ParentClass = CommentableItem::class;
        $comment2->Comment = '<p><script>alert("w00t")</script>my comment</p>';
        $comment2->write();
        $this->assertEquals(
            '<p>my comment</p>',
            $comment2->Comment,
            'Removes HTML tags which are not on the whitelist'
        );
    }

    public function testDefaultTemplateRendersHtmlWithAllowHtml()
    {
        if (!class_exists('\\HTMLPurifier')) {
            $this->markTestSkipped('HTMLPurifier class not found');
        }

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'html_allowed_elements' => array('p'),
        ));

        $item = new CommentableItem();
        $item->write();

        // Without HTML allowed
        $comment = new Comment();
        $comment->Comment = '<p>my comment</p>';
        $comment->AllowHtml = false;
        $comment->ParentID = $item->ID;
        $comment->ParentClass = CommentableItem::class;
        $comment->write();

        $html = $item->customise(array('CommentsEnabled' => true))->renderWith('CommentsInterface');
        $this->assertContains(
            '&lt;p&gt;my comment&lt;/p&gt;',
            $html
        );

        $comment->AllowHtml = true;
        $comment->write();
        $html = $item->customise(array('CommentsEnabled' => true))->renderWith('CommentsInterface');
        $this->assertContains(
            '<p>my comment</p>',
            $html
        );
    }


    /**
     * Tests whether comments are enabled or disabled by default
     */
    public function testDefaultEnabled()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'enabled_cms' => true,
            'require_moderation_cms' => true,
            'require_login_cms' => true
        ));

        // With default = true
        $obj = new CommentableItem();
        $this->assertTrue((bool)$obj->getCommentsOption('enabled'), "Default setting is enabled");
        $this->assertTrue((bool)$obj->ProvideComments);
        $this->assertEquals('None', $obj->ModerationRequired);
        $this->assertFalse((bool)$obj->CommentsRequireLogin);

        $obj = new CommentableItemEnabled();
        $this->assertTrue((bool)$obj->ProvideComments);
        $this->assertEquals('Required', $obj->ModerationRequired);
        $this->assertTrue((bool)$obj->CommentsRequireLogin);

        $obj = new CommentableItemDisabled();
        $this->assertFalse((bool)$obj->ProvideComments);
        $this->assertEquals('None', $obj->ModerationRequired);
        $this->assertFalse((bool)$obj->CommentsRequireLogin);

        // With default = false
        // Because of config rules about falsey values, apply config to object directly
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'enabled' => false,
            'require_login' => true,
            'require_moderation' => true
        ));

        $obj = new CommentableItem();

        $this->assertFalse((bool)$obj->getCommentsOption('enabled'), 'Default setting is disabled');
        $this->assertFalse((bool)$obj->ProvideComments);
        $this->assertEquals('Required', $obj->ModerationRequired);
        $this->assertTrue((bool)$obj->CommentsRequireLogin);

        $obj = new CommentableItemEnabled();

        $this->assertTrue((bool)$obj->ProvideComments);
        $this->assertEquals('Required', $obj->ModerationRequired);
        $this->assertTrue((bool)$obj->CommentsRequireLogin);

        $obj = new CommentableItemDisabled();

        $this->assertFalse((bool)$obj->ProvideComments);
        $this->assertEquals('None', $obj->ModerationRequired);
        $this->assertFalse((bool)$obj->CommentsRequireLogin);
    }

    public function testOnBeforeDelete()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        $child = new Comment();
        $child->Name = 'Fred Bloggs';
        $child->Comment = 'Child of firstComA';
        $child->write();
        $comment->ChildComments()->add($child);
        $this->assertEquals(4, $comment->ChildComments()->count());

        $commentID = $comment->ID;
        $childCommentID = $child->ID;

        $comment->delete();

        // assert that the new child been deleted
        $this->assertNull(DataObject::get_by_id(Comment::class, $commentID));
        $this->assertNull(DataObject::get_by_id(Comment::class, $childCommentID));
    }

    public function testRequireDefaultRecords()
    {
        $this->markTestSkipped('TODO');
    }

    public function testLink()
    {
        $comment = $this->objFromFixture(Comment::class, 'thirdComD');
        $this->assertEquals(
            'CommentableItemController#comment-' . $comment->ID,
            $comment->Link()
        );
        $this->assertEquals($comment->ID, $comment->ID);

        // An orphan comment has no link
        $comment->ParentID = 0;
        $comment->ParentClass = null;
        $comment->write();
        $this->assertEquals('', $comment->Link());
    }

    public function testPermalink()
    {
        $comment = $this->objFromFixture(Comment::class, 'thirdComD');
        $this->assertEquals('comment-' . $comment->ID, $comment->Permalink());
    }

    /**
     * Test field labels in 2 languages
     */
    public function testFieldLabels()
    {
        $locale = i18n::get_locale();
        i18n::set_locale('fr');
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $labels = $comment->FieldLabels();
        $expected = array(
            'Name' => 'Nom de l\'Auteur',
            'Comment' => 'Commentaire',
            'Email' => 'Email',
            'URL' => 'URL',
            'Moderated' => 'Modéré?',
            'IsSpam' => 'Spam?',
            'AllowHtml' => 'Allow Html',
            'SecretToken' => 'Secret Token',
            'Depth' => 'Depth',
            'Author' => 'Author Member',
            'ParentComment' => 'Parent Comment',
            'ChildComments' => 'Child Comments',
            'ParentTitle' => 'Parent',
            'Created' => 'Date de publication',
            'Parent' => 'Parent'
        );
        i18n::set_locale($locale);
        $this->assertEquals($expected, $labels);
        $labels = $comment->FieldLabels();
        $expected = array(
            'Name' => 'Author Name',
            'Comment' => 'Comment',
            'Email' => 'Email',
            'URL' => 'URL',
            'Moderated' => 'Moderated?',
            'IsSpam' => 'Spam?',
            'AllowHtml' => 'Allow Html',
            'SecretToken' => 'Secret Token',
            'Depth' => 'Depth',
            'Author' => 'Author Member',
            'ParentComment' => 'Parent Comment',
            'ChildComments' => 'Child Comments',
            'ParentTitle' => 'Parent',
            'Created' => 'Date posted',
            'Parent' => 'Parent'
        );
        $this->assertEquals($expected, $labels);
    }

    public function testGetParent()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $parent = $comment->Parent();
        $this->assertSame($item->getClassName(), $parent->getClassName());
        $this->assertSame($item->ID, $parent->ID);
    }

    public function testGetParentTitle()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $title = $comment->getParentTitle();
        $this->assertEquals('First', $title);

        // Title from a comment with no parent is blank
        $comment->ParentID = 0;
        $comment->ParentClass = null;
        $comment->write();
        $this->assertEquals('', $comment->getParentTitle());
    }

    public function testGetParentClassName()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $className = $comment->getParentClassName();
        $this->assertEquals(CommentableItem::class, $className);
    }

    public function testCastingHelper()
    {
        $this->markTestSkipped('TODO');
    }

    public function testGetEscapedComment()
    {
        $this->markTestSkipped('TODO');
    }

    public function testIsPreview()
    {
        $comment = new Comment();
        $comment->Name = 'Fred Bloggs';
        $comment->Comment = 'this is a test comment';
        $this->assertTrue($comment->isPreview());
        $comment->write();
        $this->assertFalse($comment->isPreview());
    }

    public function testCanCreate()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        // admin can create - this is always false
        $this->logInAs('commentadmin');
        $this->assertFalse($comment->canCreate());

        // visitor can view
        $this->logInAs('visitor');
        $this->assertFalse($comment->canCreate());
    }

    public function testCanView()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        // admin can view
        $this->logInAs('commentadmin');
        $this->assertTrue($comment->canView());

        // visitor can view
        $this->logInAs('visitor');
        $this->assertTrue($comment->canView());

        $comment->ParentID = 0;
        $comment->write();
        $this->assertFalse($comment->canView());
    }

    public function testCanEdit()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        // admin can edit
        $this->logInAs('commentadmin');
        $this->assertTrue($comment->canEdit());

        // visitor cannot
        $this->logInAs('visitor');
        $this->assertFalse($comment->canEdit());

        $comment->ParentID = 0;
        $comment->write();
        $this->assertFalse($comment->canEdit());
    }

    public function testCanDelete()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        // admin can delete
        $this->logInAs('commentadmin');
        $this->assertTrue($comment->canDelete());

        // visitor cannot
        $this->logInAs('visitor');
        $this->assertFalse($comment->canDelete());

        $comment->ParentID = 0;
        $comment->write();
        $this->assertFalse($comment->canDelete());
    }

    public function testGetMember()
    {
        $this->logInAs('visitor');
        $current = Security::getCurrentUser();
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $method = $this->getMethod('getMember');

        // null case
        $member = $method->invokeArgs($comment, array());
        $this->assertEquals($current->ID, $member->ID);

        // numeric ID case
        $member = $method->invokeArgs($comment, array($current->ID));
        $this->assertEquals($current->ID, $member->ID);

        // identity case
        $member = $method->invokeArgs($comment, array($current));
        $this->assertEquals($current->ID, $member->ID);
    }

    public function testGetAuthorName()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $this->assertEquals(
            'FA',
            $comment->getAuthorName()
        );

        $comment->Name = '';
        $this->assertEquals(
            '',
            $comment->getAuthorName()
        );

        $author = $this->objFromFixture(Member::class, 'visitor');
        $comment->AuthorID = $author->ID;
        $comment->write();
        $this->assertEquals(
            'visitor',
            $comment->getAuthorName()
        );

        // null the names, expect null back
        $comment->Name = null;
        $comment->AuthorID = 0;
        $this->assertNull($comment->getAuthorName());
    }


    public function testLinks()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $this->logInAs('commentadmin');

        $method = $this->getMethod('ActionLink');

        // test with starts of strings and tokens and salts change each time
        $this->assertContains(
            '/comments/theaction/' . $comment->ID,
            $method->invokeArgs($comment, array('theaction'))
        );

        $this->assertContains(
            '/comments/delete/' . $comment->ID,
            $comment->DeleteLink()
        );

        $this->assertContains(
            '/comments/spam/' . $comment->ID,
            $comment->SpamLink()
        );

        $comment->markSpam();
        $this->assertContains(
            '/comments/ham/' . $comment->ID,
            $comment->HamLink()
        );

        //markApproved
        $comment->markUnapproved();
        $this->assertContains(
            '/comments/approve/' . $comment->ID,
            $comment->ApproveLink()
        );
    }

    public function testMarkSpam()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $comment->markSpam();
        $this->assertTrue($comment->Moderated);
        $this->assertTrue($comment->IsSpam);
    }

    public function testMarkApproved()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $comment->markApproved();
        $this->assertTrue($comment->Moderated);
        $this->assertFalse($comment->IsSpam);
    }

    public function testMarkUnapproved()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $comment->markApproved();
        $this->assertTrue($comment->Moderated);
    }

    public function testSpamClass()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $this->assertEquals('notspam', $comment->spamClass());
        $comment->Moderated = false;
        $this->assertEquals('unmoderated', $comment->spamClass());
        $comment->IsSpam = true;
        $this->assertEquals('spam', $comment->spamClass());
    }

    public function testGetTitle()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $this->assertEquals(
            'Comment by FA on First',
            $comment->getTitle()
        );
    }

    public function testGetCMSFields()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $fields = $comment->getCMSFields();
        $names = array();
        foreach ($fields as $field) {
            $names[] = $field->getName();
        }
        $expected = array(
            'Created',
            'Name',
            'Comment',
            'Email',
            'URL',
            'Options'
        );
        $this->assertEquals($expected, $names);
    }

    public function testGetCMSFieldsCommentHasAuthor()
    {
        $member = Member::get()->filter('FirstName', 'visitor')->first();
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $comment->AuthorID = $member->ID;
        $comment->write();

        $fields = $comment->getCMSFields();
        $names = array();
        foreach ($fields as $field) {
            $names[] = $field->getName();
        }
        $expected = array(
            'Created',
            'Name',
            'AuthorMember',
            'Comment',
            'Email',
            'URL',
            'Options'
        );
        $this->assertEquals($expected, $names);
    }

    public function testGetCMSFieldsWithParentComment()
    {
        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        $child = new Comment();
        $child->Name = 'John Smith';
        $child->Comment = 'This is yet another test commnent';
        $child->ParentCommentID = $comment->ID;
        $child->write();

        $fields = $child->getCMSFields();
        $names = array();
        foreach ($fields as $field) {
            $names[] = $field->getName();
        }
        $expected = array(
            'Created',
            'Name',
            'Comment',
            'Email',
            'URL',
            'Options',
            'ParentComment_Title',
            'ParentComment_Created',
            'ParentComment_AuthorName',
            'ParentComment_EscapedComment'
        );
        $this->assertEquals($expected, $names);
    }

    public function testPurifyHtml()
    {
        if (!class_exists(HTMLPurifier_Config::class)) {
            $this->markTestSkipped('HTMLPurifier class not found');
            return;
        }

        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        $dirtyHTML = '<p><script>alert("w00t")</script>my comment</p>';
        $this->assertEquals(
            'my comment',
            $comment->purifyHtml($dirtyHTML)
        );
    }

    public function testGravatar()
    {
        // Turn gravatars on
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'use_gravatar' => true,
            'gravatar_size' => 80,
            'gravatar_default' => 'identicon',
            'gravatar_rating' => 'g'
        ));

        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        $this->assertEquals(
            'https://www.gravatar.com/avatar/d41d8cd98f00b204e9800998ecf8427e?s'
            . '=80&d=identicon&r=g',
            $comment->Gravatar()
        );

        // Turn gravatars off
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'use_gravatar' => false
        ));

        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        $this->assertEquals(
            '',
            $comment->Gravatar()
        );
    }

    public function testGetRepliesEnabled()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => false
        ));

        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $this->assertFalse($comment->getRepliesEnabled());

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));

        $this->assertTrue($comment->getRepliesEnabled());

        $comment->Depth = 4;
        $this->assertFalse($comment->getRepliesEnabled());


        // 0 indicates no limit for nested_depth
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 0
        ));

        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $comment->Depth = 234;

        $comment->markUnapproved();
        $this->assertFalse($comment->getRepliesEnabled());

        $comment->markSpam();
        $this->assertFalse($comment->getRepliesEnabled());

        $comment->markApproved();
        $this->assertTrue($comment->getRepliesEnabled());
    }

    public function testAllReplies()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));

        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        $this->assertEquals(
            3,
            $comment->allReplies()->count()
        );

        $child = new Comment();
        $child->Name = 'Fred Smith';
        $child->Comment = 'This is a child comment';
        $child->ParentCommentID = $comment->ID;

        // spam should be returned by this method
        $child->markSpam();
        $child->write();
        $replies = $comment->allReplies();
        $this->assertEquals(
            4,
            $comment->allReplies()->count()
        );

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => false
        ));

        $this->assertEquals(0, $comment->allReplies()->count());
    }

    public function testReplies()
    {
        CommentableItem::add_extension(CommentsExtension::class);
        $this->logInWithPermission('ADMIN');
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));
        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $this->assertEquals(
            3,
            $comment->Replies()->count()
        );

        // Test that spam comments are not returned
        $childComment = $comment->Replies()->first();
        $childComment->IsSpam = 1;
        $childComment->write();
        $this->assertEquals(
            2,
            $comment->Replies()->count()
        );

        // Test that unmoderated comments are not returned
        //
        $childComment = $comment->Replies()->first();

        // FIXME - moderation settings scenarios need checked here
        $childComment->Moderated = 0;
        $childComment->IsSpam = 0;
        $childComment->write();
        $this->assertEquals(
            2,
            $comment->Replies()->count()
        );


        // Test moderation required on the front end
        $item = $this->objFromFixture(CommentableItem::class, 'first');
        $item->ModerationRequired = 'Required';
        $item->write();

        Config::modify()->merge(CommentableItemDisabled::class, 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4,
            'frontend_moderation' => true
        ));

        $comment = DataObject::get_by_id(Comment::class, $comment->ID);

        $this->assertEquals(
            2,
            $comment->Replies()->count()
        );

        // Turn off nesting, empty array should be returned
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => false
        ));

        $this->assertEquals(
            0,
            $comment->Replies()->count()
        );

        CommentableItem::remove_extension(CommentsExtension::class);
    }

    public function testPagedReplies()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4,
            'comments_per_page' => 2
        ));

        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $pagedList = $comment->pagedReplies();

        $this->assertEquals(
            2,
            $pagedList->TotalPages()
        );

        $this->assertEquals(
            3,
            $pagedList->getTotalItems()
        );

        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => false
        ));

        $this->assertEquals(0, $comment->PagedReplies()->count());
    }

    public function testReplyForm()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => false,
            'nested_depth' => 4
        ));

        $comment = $this->objFromFixture(Comment::class, 'firstComA');

        // No nesting, no reply form
        $form = $comment->replyForm();
        $this->assertNull($form);

        // parent item so show form
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));
        $form = $comment->ReplyForm();
        $this->assertNotNull($form);
        $names = array();

        foreach ($form->Fields() as $field) {
            array_push($names, $field->getName());
        }

        $this->assertContains('NameEmailURLComment', $names, 'The CompositeField name');
        $this->assertContains('ParentID', $names);
        $this->assertContains('ParentClassName', $names);
        $this->assertContains('ReturnURL', $names);
        $this->assertContains('ParentCommentID', $names);

        // no parent, no reply form

        $comment->ParentID = 0;
        $comment->ParentClass = null;
        $comment->write();
        $form = $comment->replyForm();
        $this->assertNull($form);
    }

    public function testUpdateDepth()
    {
        Config::modify()->merge(CommentableItem::class, 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));

        $comment = $this->objFromFixture(Comment::class, 'firstComA');
        $children = $comment->allReplies()->toArray();
        // Make the second child a child of the first
        // Make the third child a child of the second
        $reply1 = $children[0];
        $reply2 = $children[1];
        $reply3 = $children[2];
        $reply2->ParentCommentID = $reply1->ID;
        $reply2->write();
        $this->assertEquals(3, $reply2->Depth);
        $reply3->ParentCommentID = $reply2->ID;
        $reply3->write();
        $this->assertEquals(4, $reply3->Depth);
    }

    public function testGetToken()
    {
        $this->markTestSkipped('TODO');
    }

    public function testMemberSalt()
    {
        $this->markTestSkipped('TODO');
    }

    public function testAddToUrl()
    {
        $this->markTestSkipped('TODO');
    }

    public function testCheckRequest()
    {
        $this->markTestSkipped('TODO');
    }

    public function testGenerate()
    {
        $this->markTestSkipped('TODO');
    }

    protected static function getMethod($name)
    {
        $class = new ReflectionClass(Comment::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }
}
