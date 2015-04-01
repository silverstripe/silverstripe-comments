<?php

/**
 * @package comments
 */
class CommentsTest extends FunctionalTest {
	
	public static $fixture_file = 'comments/tests/CommentsTest.yml';
	
	protected $extraDataObjects = array(
		'CommentableItem'
	);

	public function setUp() {
		parent::setUp();

		Commenting::add('CommentableItem');
	}
	

	public function testCommentsList() {
		// comments don't require moderation so unmoderated comments can be 
		// shown but not spam posts
		Commenting::set_config_value('CommentableItem','require_moderation', false);

		$item = $this->objFromFixture('CommentableItem', 'spammed');

		$this->assertDOSEquals(array(
			array('Name' => 'Comment 1'),
			array('Name' => 'Comment 3')
		), $item->getComments(), 'Only 2 non spam posts should be shown');

		// when moderated, only moderated, non spam posts should be shown.
		Commenting::set_config_value('CommentableItem','require_moderation', true);

		$this->assertDOSEquals(array(
			array('Name' => 'Comment 3')
		), $item->getComments(), 'Only 1 non spam, moderated post should be shown');

		// when logged in as an user with CMS_ACCESS_CommentAdmin rights they 
		// should see all the comments whether we have moderation on or not
		$this->logInWithPermission('CMS_ACCESS_CommentAdmin');

		Commenting::set_config_value('CommentableItem','require_moderation', true);
		$this->assertEquals(4, $item->getComments()->Count());

		Commenting::set_config_value('CommentableItem','require_moderation', false);
		$this->assertEquals(4, $item->getComments()->Count());
	}

	public function testCanView() {
		$visitor = $this->objFromFixture('Member', 'visitor');
		$admin = $this->objFromFixture('Member', 'commentadmin');
		$comment = $this->objFromFixture('Comment', 'firstComA');

		$this->assertTrue($comment->canView($visitor), 
			'Unauthenticated members can view comments associated to a object with ProvideComments=1'
		);
		$this->assertTrue($comment->canView($admin),
			'Admins with CMS_ACCESS_CommentAdmin permissions can view comments associated to a page with ProvideComments=1'
		);
		
		$disabledComment = $this->objFromFixture('Comment', 'disabledCom');
		
		$this->assertFalse($disabledComment->canView($visitor),
			'Unauthenticated members can not view comments associated to a object with ProvideComments=0'
		);

		$this->assertTrue($disabledComment->canView($admin),
			'Admins with CMS_ACCESS_CommentAdmin permissions can view comments associated to a page with ProvideComments=0'
		);
	}
	
	public function testCanEdit() {
		$visitor = $this->objFromFixture('Member', 'visitor');
		$admin = $this->objFromFixture('Member', 'commentadmin');
		$comment = $this->objFromFixture('Comment', 'firstComA');
		
		$this->assertFalse($comment->canEdit($visitor));
		$this->assertTrue($comment->canEdit($admin));
	}
	
	public function testCanDelete() {
		$visitor = $this->objFromFixture('Member', 'visitor');
		$admin = $this->objFromFixture('Member', 'commentadmin');
		$comment = $this->objFromFixture('Comment', 'firstComA');
		
		$this->assertFalse($comment->canEdit($visitor));
		$this->assertTrue($comment->canEdit($admin));
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

		$origAllowed = Commenting::get_config_value('CommentableItem','html_allowed');
		
		// Without HTML allowed
		$comment1 = new Comment();
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
		Commenting::set_config_value('CommentableItem','html_allowed', true);
		$comment2 = new Comment();
		$comment2->BaseClass = 'CommentableItem';
		$comment2->Comment = '<p><script>alert("w00t")</script>my comment</p>';
		$comment2->write();
		$this->assertEquals(
			'<p>my comment</p>',
			$comment2->Comment,
			'Removes HTML tags which are not on the whitelist'
		);

		Commenting::set_config_value('CommentableItem','html_allowed', $origAllowed);
	}

	public function testDefaultTemplateRendersHtmlWithAllowHtml() {
		if(!class_exists('HTMLPurifier')) {
			$this->markTestSkipped('HTMLPurifier class not found');
		}

		$origAllowed = Commenting::get_config_value('CommentableItem', 'html_allowed');
		$item = new CommentableItem();
		$item->write();

		// Without HTML allowed
		$comment = new Comment();
		$comment->Comment = '<p>my comment</p>';
		$comment->ParentID = $item->ID;
		$comment->BaseClass = 'CommentableItem';
		$comment->write();
		
		$html = $item->customise(array('CommentsEnabled' => true))->renderWith('CommentsInterface');
		$this->assertContains(
			'&lt;p&gt;my comment&lt;/p&gt;',
			$html
		);

		Commenting::set_config_value('CommentableItem','html_allowed', true);
		$html = $item->customise(array('CommentsEnabled' => true))->renderWith('CommentsInterface');
		$this->assertContains(
			'<p>my comment</p>',
			$html
		);

		Commenting::set_config_value('CommentableItem','html_allowed', $origAllowed);
	}

}


/**
 * @package comments
 * @subpackage tests
 */
class CommentableItem extends DataObject implements TestOnly {

	private static $db = array(
		'ProvideComments' => 'Boolean',
		'Title' => 'Varchar'
	);

	public function RelativeLink() {
		return "CommentableItem_Controller";
	}

	public function canView($member = null) {
		return true;
	}

	public function Link() {
		return $this->RelativeLink();
	}

	public function AbsoluteLink() {
		return Director::absoluteURL($this->RelativeLink());
	}
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
