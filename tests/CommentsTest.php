<?php

/**
 * @package comments
 */
class CommentsTest extends FunctionalTest {

	public static $fixture_file = 'comments/tests/CommentsTest.yml';

	protected $extraDataObjects = array(
		'CommentableItem',
		'CommentableItemEnabled',
		'CommentableItemDisabled'
	);

	public function setUp() {
		parent::setUp();
		Config::nest();

		// Set good default values
		Config::inst()->update('CommentsExtension', 'comments', array(
			'enabled' => true,
			'enabled_cms' => false,
			'require_login' => false,
			'require_login_cms' => false,
			'required_permission' => false,
			'require_moderation_nonmembers' => false,
			'require_moderation' => false,
			'require_moderation_cms' => false,
			'frontend_moderation' => false,
			'frontend_spam' => false,
		));

		// Configure this dataobject
		Config::inst()->update('CommentableItem', 'comments', array(
			'enabled_cms' => true
		));
	}

	public function tearDown() {
		Config::unnest();
		parent::tearDown();
	}

	public function testCommentsList() {
		// comments don't require moderation so unmoderated comments can be
		// shown but not spam posts
		Config::inst()->update('CommentableItem', 'comments', array(
			'require_moderation_nonmembers' => false,
			'require_moderation' => false,
			'require_moderation_cms' => false,
		));

		$item = $this->objFromFixture('CommentableItem', 'spammed');
		$this->assertEquals('None', $item->ModerationRequired);

		$this->assertDOSEquals(array(
			array('Name' => 'Comment 1'),
			array('Name' => 'Comment 3')
		), $item->Comments(), 'Only 2 non spam posts should be shown');

		// when moderated, only moderated, non spam posts should be shown.
		Config::inst()->update('CommentableItem', 'comments', array('require_moderation_nonmembers' => true));
		$this->assertEquals('NonMembersOnly', $item->ModerationRequired);

		// Check that require_moderation overrides this option
		Config::inst()->update('CommentableItem', 'comments', array('require_moderation' => true));
		$this->assertEquals('Required', $item->ModerationRequired);

		$this->assertDOSEquals(array(
			array('Name' => 'Comment 3')
		), $item->Comments(), 'Only 1 non spam, moderated post should be shown');
		$this->assertEquals(1, $item->Comments()->Count());

		// require_moderation_nonmembers still filters out unmoderated comments
		Config::inst()->update('CommentableItem', 'comments', array('require_moderation' => false));
		$this->assertEquals(1, $item->Comments()->Count());

		Config::inst()->update('CommentableItem', 'comments', array('require_moderation_nonmembers' => false));
		$this->assertEquals(2, $item->Comments()->Count());

		// With unmoderated comments set to display in frontend
		Config::inst()->update('CommentableItem', 'comments', array(
			'require_moderation' => true,
			'frontend_moderation' => true
		));
		$this->assertEquals(1, $item->Comments()->Count());

		$this->logInWithPermission('ADMIN');
		$this->assertEquals(2, $item->Comments()->Count());

		// With spam comments set to display in frontend
		Config::inst()->update('CommentableItem', 'comments', array(
			'require_moderation' => true,
			'frontend_moderation' => false,
			'frontend_spam' => true,
		));
		if($member = Member::currentUser()) $member->logOut();
		$this->assertEquals(1, $item->Comments()->Count());

		$this->logInWithPermission('ADMIN');
		$this->assertEquals(2, $item->Comments()->Count());


		// With spam and unmoderated comments set to display in frontend
		Config::inst()->update('CommentableItem', 'comments', array(
			'require_moderation' => true,
			'frontend_moderation' => true,
			'frontend_spam' => true,
		));
		if($member = Member::currentUser()) $member->logOut();
		$this->assertEquals(1, $item->Comments()->Count());

		$this->logInWithPermission('ADMIN');
		$this->assertEquals(4, $item->Comments()->Count());
	}

	/**
	 * Test moderation options configured via the CMS
	 */
	public function testCommentCMSModerationList() {
		// comments don't require moderation so unmoderated comments can be
		// shown but not spam posts
		Config::inst()->update('CommentableItem', 'comments', array(
			'require_moderation' => true,
			'require_moderation_cms' => true,
		));

		$item = $this->objFromFixture('CommentableItem', 'spammed');
		$this->assertEquals('None', $item->ModerationRequired);

		$this->assertDOSEquals(array(
			array('Name' => 'Comment 1'),
			array('Name' => 'Comment 3')
		), $item->Comments(), 'Only 2 non spam posts should be shown');

		// when moderated, only moderated, non spam posts should be shown.
		$item->ModerationRequired = 'NonMembersOnly';
		$item->write();
		$this->assertEquals('NonMembersOnly', $item->ModerationRequired);

		// Check that require_moderation overrides this option
		$item->ModerationRequired = 'Required';
		$item->write();
		$this->assertEquals('Required', $item->ModerationRequired);

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

	public function testCanPostComment() {
		Config::inst()->update('CommentableItem', 'comments', array(
			'require_login' => false,
			'require_login_cms' => false,
			'required_permission' => false,
		));
		$item = $this->objFromFixture('CommentableItem', 'first');
		$item2 = $this->objFromFixture('CommentableItem', 'second');

		// Test restriction free commenting
		if($member = Member::currentUser()) $member->logOut();
		$this->assertFalse($item->CommentsRequireLogin);
		$this->assertTrue($item->canPostComment());

		// Test permission required to post
		Config::inst()->update('CommentableItem', 'comments', array(
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
		Config::inst()->update('CommentableItem', 'comments', array(
			'required_permission' => false,
		));
		$this->assertTrue($item->CommentsRequireLogin);
		if($member = Member::currentUser()) $member->logOut();
		$this->assertFalse($item->canPostComment());
		$this->logInWithPermission('ANY_PERMISSION');
		$this->assertTrue($item->canPostComment());

		// Test options set via CMS
		Config::inst()->update('CommentableItem', 'comments', array(
			'require_login' => true,
			'require_login_cms' => true,
		));
		$this->assertFalse($item->CommentsRequireLogin);
		$this->assertTrue($item2->CommentsRequireLogin);
		if($member = Member::currentUser()) $member->logOut();
		$this->assertTrue($item->canPostComment());
		$this->assertFalse($item2->canPostComment());

		// Login grants permission to post
		$this->logInWithPermission('ANY_PERMISSION');
		$this->assertTrue($item->canPostComment());
		$this->assertTrue($item2->canPostComment());

	}
	public function testDeleteComment() {
		// Test anonymous user
		if($member = Member::currentUser()) $member->logOut();
		$comment = $this->objFromFixture('Comment', 'firstComA');
		$commentID = $comment->ID;
		$this->assertNull($comment->DeleteLink(), 'No permission to see delete link');
		$delete = $this->get('CommentingController/delete/'.$comment->ID.'?ajax=1');
		$this->assertEquals(403, $delete->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertTrue($check && $check->exists());

		// Test non-authenticated user
		$this->logInAs('visitor');
		$this->assertNull($comment->DeleteLink(), 'No permission to see delete link');

		// Test authenticated user
		$this->logInAs('commentadmin');
		$comment = $this->objFromFixture('Comment', 'firstComA');
		$commentID = $comment->ID;
		$adminComment1Link = $comment->DeleteLink();
		$this->assertContains('CommentingController/delete/'.$commentID.'?t=', $adminComment1Link);

		// Test that this link can't be shared / XSS exploited
		$this->logInAs('commentadmin2');
		$delete = $this->get($adminComment1Link);
		$this->assertEquals(400, $delete->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertTrue($check && $check->exists());

		// Test that this other admin can delete the comment with their own link
		$adminComment2Link = $comment->DeleteLink();
		$this->assertNotEquals($adminComment2Link, $adminComment1Link);
		$this->autoFollowRedirection = false;
		$delete = $this->get($adminComment2Link);
		$this->assertEquals(302, $delete->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertFalse($check && $check->exists());
	}

	public function testSpamComment() {
		// Test anonymous user
		if($member = Member::currentUser()) $member->logOut();
		$comment = $this->objFromFixture('Comment', 'firstComA');
		$commentID = $comment->ID;
		$this->assertNull($comment->SpamLink(), 'No permission to see mark as spam link');
		$spam = $this->get('CommentingController/spam/'.$comment->ID.'?ajax=1');
		$this->assertEquals(403, $spam->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertEquals(0, $check->IsSpam, 'No permission to mark as spam');

		// Test non-authenticated user
		$this->logInAs('visitor');
		$this->assertNull($comment->SpamLink(), 'No permission to see mark as spam link');

		// Test authenticated user
		$this->logInAs('commentadmin');
		$comment = $this->objFromFixture('Comment', 'firstComA');
		$commentID = $comment->ID;
		$adminComment1Link = $comment->SpamLink();
		$this->assertContains('CommentingController/spam/'.$commentID.'?t=', $adminComment1Link);

		// Test that this link can't be shared / XSS exploited
		$this->logInAs('commentadmin2');
		$spam = $this->get($adminComment1Link);
		$this->assertEquals(400, $spam->getStatusCode());
		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(0, $check->IsSpam, 'No permission to mark as spam');

		// Test that this other admin can spam the comment with their own link
		$adminComment2Link = $comment->SpamLink();
		$this->assertNotEquals($adminComment2Link, $adminComment1Link);
		$this->autoFollowRedirection = false;
		$spam = $this->get($adminComment2Link);
		$this->assertEquals(302, $spam->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertEquals(1, $check->IsSpam);

		// Cannot re-spam spammed comment
		$this->assertNull($check->SpamLink());
	}

	public function testHamComment() {
		// Test anonymous user
		if($member = Member::currentUser()) $member->logOut();
		$comment = $this->objFromFixture('Comment', 'secondComC');
		$commentID = $comment->ID;
		$this->assertNull($comment->HamLink(), 'No permission to see mark as ham link');
		$ham = $this->get('CommentingController/ham/'.$comment->ID.'?ajax=1');
		$this->assertEquals(403, $ham->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertEquals(1, $check->IsSpam, 'No permission to mark as ham');

		// Test non-authenticated user
		$this->logInAs('visitor');
		$this->assertNull($comment->HamLink(), 'No permission to see mark as ham link');

		// Test authenticated user
		$this->logInAs('commentadmin');
		$comment = $this->objFromFixture('Comment', 'secondComC');
		$commentID = $comment->ID;
		$adminComment1Link = $comment->HamLink();
		$this->assertContains('CommentingController/ham/'.$commentID.'?t=', $adminComment1Link);

		// Test that this link can't be shared / XSS exploited
		$this->logInAs('commentadmin2');
		$ham = $this->get($adminComment1Link);
		$this->assertEquals(400, $ham->getStatusCode());
		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(1, $check->IsSpam, 'No permission to mark as ham');

		// Test that this other admin can ham the comment with their own link
		$adminComment2Link = $comment->HamLink();
		$this->assertNotEquals($adminComment2Link, $adminComment1Link);
		$this->autoFollowRedirection = false;
		$ham = $this->get($adminComment2Link);
		$this->assertEquals(302, $ham->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertEquals(0, $check->IsSpam);

		// Cannot re-ham hammed comment
		$this->assertNull($check->HamLink());
	}

	public function testApproveComment() {
		// Test anonymous user
		if($member = Member::currentUser()) $member->logOut();
		$comment = $this->objFromFixture('Comment', 'secondComB');
		$commentID = $comment->ID;
		$this->assertNull($comment->ApproveLink(), 'No permission to see approve link');
		$approve = $this->get('CommentingController/approve/'.$comment->ID.'?ajax=1');
		$this->assertEquals(403, $approve->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertEquals(0, $check->Moderated, 'No permission to approve');

		// Test non-authenticated user
		$this->logInAs('visitor');
		$this->assertNull($comment->ApproveLink(), 'No permission to see approve link');

		// Test authenticated user
		$this->logInAs('commentadmin');
		$comment = $this->objFromFixture('Comment', 'secondComB');
		$commentID = $comment->ID;
		$adminComment1Link = $comment->ApproveLink();
		$this->assertContains('CommentingController/approve/'.$commentID.'?t=', $adminComment1Link);

		// Test that this link can't be shared / XSS exploited
		$this->logInAs('commentadmin2');
		$approve = $this->get($adminComment1Link);
		$this->assertEquals(400, $approve->getStatusCode());
		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(0, $check->Moderated, 'No permission to approve');

		// Test that this other admin can approve the comment with their own link
		$adminComment2Link = $comment->ApproveLink();
		$this->assertNotEquals($adminComment2Link, $adminComment1Link);
		$this->autoFollowRedirection = false;
		$approve = $this->get($adminComment2Link);
		$this->assertEquals(302, $approve->getStatusCode());
		$check = DataObject::get_by_id('Comment', $commentID);
		$this->assertEquals(1, $check->Moderated);

		// Cannot re-approve approved comment
		$this->assertNull($check->ApproveLink());
	}

	public function testCommenterURLWrite() {
		$comment = new Comment();
		// We only care about the CommenterURL, so only set that
		// Check a http and https URL. Add more test urls here as needed.
		$protocols = array(
			'Http',
			'Https',
		);
		$url = '://example.com';

		foreach($protocols as $protocol) {
			$comment->CommenterURL = $protocol . $url;
			// The protocol should stay as if, assuming it is valid
			$comment->write();
			$this->assertEquals($comment->CommenterURL, $protocol . $url, $protocol . ':// is a valid protocol');
		}
	}

	public function testSanitizesWithAllowHtml() {
		if(!class_exists('HTMLPurifier')) {
			$this->markTestSkipped('HTMLPurifier class not found');
			return;
		}

        // Add p for paragraph
        // NOTE: The config method appears to append to the existing array
        Config::inst()->update('CommentableItem', 'comments', array(
            'html_allowed_elements' => array('p'),
        ));

		// Without HTML allowed
		$comment1 = new Comment();
        $comment1->AllowHtml = false;
		$comment1->BaseClass = 'CommentableItem';
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
		$comment2->BaseClass = 'CommentableItem';
		$comment2->Comment = '<p><script>alert("w00t")</script>my comment</p>';
		$comment2->write();
		$this->assertEquals(
			'<p>my comment</p>',
			$comment2->Comment,
			'Removes HTML tags which are not on the whitelist'
		);
	}

	public function testDefaultTemplateRendersHtmlWithAllowHtml() {
		if(!class_exists('HTMLPurifier')) {
			$this->markTestSkipped('HTMLPurifier class not found');
		}

        Config::inst()->update('CommentableItem', 'comments', array(
            'html_allowed_elements' => array('p'),
        ));

		$item = new CommentableItem();
		$item->write();

		// Without HTML allowed
		$comment = new Comment();
		$comment->Comment = '<p>my comment</p>';
        $comment->AllowHtml = false;
		$comment->ParentID = $item->ID;
		$comment->BaseClass = 'CommentableItem';
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
	public function testDefaultEnabled() {
		// Ensure values are set via cms (not via config)
		Config::inst()->update('CommentableItem', 'comments', array(
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
		Config::inst()->update('CommentableItem', 'comments', array(
			'enabled' => false,
			'require_login' => true,
			'require_moderation' => true
		));
		$obj = new CommentableItem();
		$this->assertFalse((bool)$obj->getCommentsOption('enabled'), "Default setting is disabled");
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

    /*
    When a parent comment is deleted, remove the children
     */
    public function testOnBeforeDelete() {
        $comment = $this->objFromFixture('Comment', 'firstComA');

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
        $this->assertFalse(DataObject::get_by_id('Comment', $commentID));
        $this->assertFalse(DataObject::get_by_id('Comment', $childCommentID));
    }

    public function testRequireDefaultRecords() {
        $this->markTestSkipped('TODO');
    }

    public function testLink() {
        $comment = $this->objFromFixture('Comment', 'thirdComD');
        $this->assertEquals('CommentableItem_Controller#comment-'.$comment->ID,
            $comment->Link());
        $this->assertEquals($comment->ID, $comment->ID);

        // An orphan comment has no link
        $comment->ParentID = 0;
        $comment->write();
        $this->assertEquals('', $comment->Link());
    }

    public function testPermalink() {
        $comment = $this->objFromFixture('Comment', 'thirdComD');
        $this->assertEquals('comment-' . $comment->ID, $comment->Permalink());
    }

    /*
    Test field labels in 2 languages
     */
    public function testFieldLabels() {
        $locale = i18n::get_locale();
        i18n::set_locale('fr');
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $labels = $comment->FieldLabels();
        $expected = array(
            'Name' => 'Nom de l\'Auteur',
            'Comment' => 'Commentaire',
            'Email' => 'Email',
            'URL' => 'URL',
            'BaseClass' => 'Base Class',
            'Moderated' => 'Modéré?',
            'IsSpam' => 'Spam?',
            'ParentID' => 'Parent ID',
            'AllowHtml' => 'Allow Html',
            'SecretToken' => 'Secret Token',
            'Depth' => 'Depth',
            'Author' => 'Author Member',
            'ParentComment' => 'Parent Comment',
            'ChildComments' => 'Child Comments',
            'ParentTitle' => 'Parent',
            'Created' => 'Date de publication'
        );
        i18n::set_locale($locale);
        $this->assertEquals($expected, $labels);
        $labels = $comment->FieldLabels();
        $expected = array(
            'Name' => 'Author Name',
            'Comment' => 'Comment',
            'Email' => 'Email',
            'URL' => 'URL',
            'BaseClass' => 'Base Class',
            'Moderated' => 'Moderated?',
            'IsSpam' => 'Spam?',
            'ParentID' => 'Parent ID',
            'AllowHtml' => 'Allow Html',
            'SecretToken' => 'Secret Token',
            'Depth' => 'Depth',
            'Author' => 'Author Member',
            'ParentComment' => 'Parent Comment',
            'ChildComments' => 'Child Comments',
            'ParentTitle' => 'Parent',
            'Created' => 'Date posted'

        );
        $this->assertEquals($expected, $labels);
    }

    public function testGetOption() {
        $this->markTestSkipped('TODO');
    }

    public function testGetParent() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $item = $this->objFromFixture('CommentableItem', 'first');
        $parent = $comment->getParent();
        $this->assertEquals($item, $parent);
    }

    public function testGetParentTitle() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $title = $comment->getParentTitle();
        $this->assertEquals('First', $title);

        // Title from a comment with no parent is blank
        $comment->ParentID = 0;
        $comment->write();
        $this->assertEquals('', $comment->getParentTitle());
    }

    public function testGetParentClassName() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $className = $comment->getParentClassName();
        $this->assertEquals('CommentableItem', $className);
    }

    public function testCastingHelper() {
        $this->markTestSkipped('TODO');
    }

    public function testGetEscapedComment() {
        $this->markTestSkipped('TODO');
    }

    public function testIsPreview() {
        $comment = new Comment();
        $comment->Name = 'Fred Bloggs';
        $comment->Comment = 'this is a test comment';
        $this->assertTrue($comment->isPreview());
        $comment->write();
        $this->assertFalse($comment->isPreview());
    }

    public function testCanCreate() {
        $comment = $this->objFromFixture('Comment', 'firstComA');

        // admin can create - this is always false
        $this->logInAs('commentadmin');
        $this->assertFalse($comment->canCreate());

        // visitor can view
        $this->logInAs('visitor');
        $this->assertFalse($comment->canCreate());
    }

    public function testCanView() {
        $comment = $this->objFromFixture('Comment', 'firstComA');

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

    public function testCanEdit() {
        $comment = $this->objFromFixture('Comment', 'firstComA');

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

    public function testCanDelete() {
        $comment = $this->objFromFixture('Comment', 'firstComA');

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

    public function testGetMember() {
        $this->logInAs('visitor');
        $current = Member::currentUser();
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $method = $this->getMethod('getMember');

        // null case
        $member = $method->invokeArgs($comment, array());
        $this->assertEquals($current, $member);

        // numeric ID case
        $member = $method->invokeArgs($comment, array($current->ID));
        $this->assertEquals($current, $member);

        // identity case
        $member = $method->invokeArgs($comment, array($current));
        $this->assertEquals($current, $member);
    }

    public function testGetAuthorName() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $this->assertEquals(
            'FA',
            $comment->getAuthorName()
        );

        $comment->Name = '';
        $this->assertEquals(
            '',
            $comment->getAuthorName()
        );

        $author = $this->objFromFixture('Member', 'visitor');
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


    public function testLinks() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $this->logInAs('commentadmin');

        $method = $this->getMethod('ActionLink');

        // test with starts of strings and tokens and salts change each time
        $this->assertStringStartsWith(
            '/CommentingController/theaction/'.$comment->ID,
            $method->invokeArgs($comment, array('theaction'))
        );

        $this->assertStringStartsWith(
            '/CommentingController/delete/'.$comment->ID,
            $comment->DeleteLink()
        );

        $this->assertStringStartsWith(
            '/CommentingController/spam/'.$comment->ID,
            $comment->SpamLink()
        );

        $comment->markSpam();
        $this->assertStringStartsWith(
            '/CommentingController/ham/'.$comment->ID,
            $comment->HamLink()
        );

        //markApproved
        $comment->markUnapproved();
        $this->assertStringStartsWith(
            '/CommentingController/approve/'.$comment->ID,
            $comment->ApproveLink()
        );
    }

    public function testMarkSpam() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $comment->markSpam();
        $this->assertTrue($comment->Moderated);
        $this->assertTrue($comment->IsSpam);
    }

    public function testMarkApproved() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $comment->markApproved();
        $this->assertTrue($comment->Moderated);
        $this->assertFalse($comment->IsSpam);
    }

    public function testMarkUnapproved() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $comment->markApproved();
        $this->assertTrue($comment->Moderated);
    }

    public function testSpamClass() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $this->assertEquals('notspam', $comment->spamClass());
        $comment->Moderated = false;
        $this->assertEquals('unmoderated', $comment->spamClass());
        $comment->IsSpam = true;
        $this->assertEquals('spam', $comment->spamClass());
    }

    public function testGetTitle() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        $this->assertEquals(
            'Comment by FA on First',
            $comment->getTitle()
        );
    }

    public function testGetCMSFields() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
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
            null #FIXME this is suspicious
        );
        $this->assertEquals($expected, $names);
    }

    public function testGetCMSFieldsCommentHasAuthor() {
        $member = Member::get()->filter('FirstName', 'visitor')->first();
        $comment = $this->objFromFixture('Comment', 'firstComA');
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
            null #FIXME this is suspicious
        );
        $this->assertEquals($expected, $names);
    }

    public function testGetCMSFieldsWithParentComment() {
        $comment = $this->objFromFixture('Comment', 'firstComA');

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
            null, #FIXME this is suspicious
            'ParentComment_Title',
            'ParentComment_Created',
            'ParentComment_AuthorName',
            'ParentComment_EscapedComment'
        );
        $this->assertEquals($expected, $names);
    }


    public function testPurifyHtml() {
        $comment = $this->objFromFixture('Comment', 'firstComA');

        $dirtyHTML = '<p><script>alert("w00t")</script>my comment</p>';
        $this->assertEquals(
            'my comment',
            $comment->purifyHtml($dirtyHTML)
        );
    }

    public function testGravatar() {
        // Turn gravatars on
        Config::inst()->update('CommentableItem', 'comments', array(
            'use_gravatar' => true
        ));
        $comment = $this->objFromFixture('Comment', 'firstComA');

        $this->assertEquals(
            'http://www.gravatar.com/avatar/d41d8cd98f00b204e9800998ecf8427e?s'.
            '=80&d=identicon&r=g',
            $comment->gravatar()
        );

        // Turn gravatars off
        Config::inst()->update('CommentableItem', 'comments', array(
            'use_gravatar' => false
        ));
        $comment = $this->objFromFixture('Comment', 'firstComA');

        $this->assertEquals(
            '',
            $comment->gravatar()
        );
    }

    public function testGetRepliesEnabled() {
        $comment = $this->objFromFixture('Comment', 'firstComA');
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => false
        ));
        $this->assertFalse($comment->getRepliesEnabled());

        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));
        $this->assertTrue($comment->getRepliesEnabled());

        $comment->Depth = 4;
        $this->assertFalse($comment->getRepliesEnabled());


        // 0 indicates no limit for nested_depth
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 0
        ));

        $comment->Depth = 234;
        $this->assertTrue($comment->getRepliesEnabled());
        $comment->markUnapproved();
        $this->assertFalse($comment->getRepliesEnabled());
        $comment->markSpam();
        $this->assertFalse($comment->getRepliesEnabled());

        $comment->markApproved();
        $this->assertTrue($comment->getRepliesEnabled());


    }

    public function testAllReplies() {
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));
        $comment = $this->objFromFixture('Comment', 'firstComA');
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

        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => false
        ));

        $this->assertEquals(0, $comment->allReplies()->count());
    }

    public function testReplies() {
        CommentableItem::add_extension('CommentsExtension');
        $this->logInWithPermission('ADMIN');
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));
        $comment = $this->objFromFixture('Comment', 'firstComA');
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
        $item = $this->objFromFixture('CommentableItem', 'first');
        $item->ModerationRequired = 'Required';
        $item->write();

        Config::inst()->update('CommentableItemDisabled', 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4,
            'frontend_moderation' => true
        ));

        $comment = DataObject::get_by_id('Comment', $comment->ID);

        $this->assertEquals(
            2,
            $comment->Replies()->count()
        );

        // Turn off nesting, empty array should be returned
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => false
        ));

        $this->assertEquals(
            0,
            $comment->Replies()->count()
        );

        CommentableItem::remove_extension('CommentsExtension');
    }

    public function testPagedReplies() {
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4,
            'comments_per_page' => 2 #Force 2nd page for 3 items
        ));

        $comment = $this->objFromFixture('Comment', 'firstComA');
        $pagedList = $comment->pagedReplies();
        $this->assertEquals(
            2,
            $pagedList->TotalPages()
        );
        $this->assertEquals(
            3,
            $pagedList->getTotalItems()
        );
        //TODO - 2nd page requires controller
        //
         Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => false
        ));

        $this->assertEquals(0, $comment->PagedReplies()->count());
    }

    public function testReplyForm() {
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => false,
            'nested_depth' => 4
        ));

        $comment = $this->objFromFixture('Comment', 'firstComA');

        // No nesting, no reply form
        $form = $comment->replyForm();
        $this->assertNull($form);

        // parent item so show form
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));
        $form = $comment->replyForm();

        $names = array();
        foreach ($form->Fields() as $field) {
            array_push($names, $field->getName());
        }

        $this->assertEquals(
            array(
                null, #FIXME suspicious
                'ParentID',
                'ReturnURL',
                'ParentCommentID',
                'BaseClass'
            ),
            $names
        );

        // no parent, no reply form

        $comment->ParentID = 0;
        $comment->write();
        $form = $comment->replyForm();
        $this->assertNull($form);
    }

    public function testUpdateDepth() {
        Config::inst()->update('CommentableItem', 'comments', array(
            'nested_comments' => true,
            'nested_depth' => 4
        ));

        $comment = $this->objFromFixture('Comment', 'firstComA');
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

    public function testGetToken() {
        $this->markTestSkipped('TODO');
    }

    public function testMemberSalt() {
        $this->markTestSkipped('TODO');
    }

    public function testAddToUrl() {
        $this->markTestSkipped('TODO');
    }

    public function testCheckRequest() {
        $this->markTestSkipped('TODO');
    }

    public function testGenerate() {
        $this->markTestSkipped('TODO');
    }


    protected static function getMethod($name) {
        $class = new ReflectionClass('Comment');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

}


/**
 * @package comments
 * @subpackage tests
 */
class CommentableItem extends DataObject implements TestOnly {

	private static $db = array(
		'Title' => 'Varchar'
	);

	private static $extensions = array(
		'CommentsExtension'
	);

	public function RelativeLink() {
		return "CommentableItem_Controller";
	}

	public function canView($member = null) {
        return true;
    }

    // This is needed for canModerateComments
    public function canEdit($member = null) {
        if($member instanceof Member) $memberID = $member->ID;
        else if(is_numeric($member)) $memberID = $member;
        else $memberID = Member::currentUserID();

        if($memberID && Permission::checkMember($memberID, array("ADMIN", "CMS_ACCESS_CommentAdmin"))) return true;
        return false;
    }

	public function Link() {
		return $this->RelativeLink();
	}

	public function AbsoluteLink() {
		return Director::absoluteURL($this->RelativeLink());
	}
}

class CommentableItemEnabled extends CommentableItem {
	private static $defaults = array(
		'ProvideComments' => true,
		'ModerationRequired' => 'Required',
		'CommentsRequireLogin' => true
	);
}


class CommentableItemDisabled extends CommentableItem {
	private static $defaults = array(
		'ProvideComments' => false,
		'ModerationRequired' => 'None',
		'CommentsRequireLogin' => false
	);
}

/**
 * @package comments
 * @subpackage tests
 */
class CommentableItem_Controller extends Controller implements TestOnly {

	public function index() {
		return CommentableItem::get()->first()->CommentsForm();
	}
}
