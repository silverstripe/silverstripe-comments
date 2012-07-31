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
		$comment = $this->objFromFixture('Comment', 'firstComA');
		$this->assertNull($comment->DeleteLink(), 'No permission to see delete link');
		$delete = $this->get('CommentingController/delete/'.$comment->ID);
		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertTrue($check && $check->exists());

		$firstPage = $this->objFromFixture('CommentableItem', 'first');
		$this->autoFollowRedirection = false;
		$this->logInAs('commentadmin');
		
		$firstComment = $this->objFromFixture('Comment', 'firstComA');
		$firstCommentID = $firstComment->ID;
		Director::test($firstPage->RelativeLink(), null, $this->session());
		$delete = $this->get('CommentingController/delete/'.$firstComment->ID);
		$check = DataObject::get_by_id('Comment', $firstCommentID);
		$this->assertFalse($check && $check->exists());
	}

	public function testSpamComment() {
		$comment = $this->objFromFixture('Comment', 'firstComA');
		$this->assertNull($comment->SpamLink(), 'No permission to see mark as spam link');
		$spam = $this->get('CommentingController/spam/'.$comment->ID);

		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(0, $check->IsSpam, 'No permission to mark as spam');

		$this->autoFollowRedirection = false;
		$this->logInAs('commentadmin');

		$this->assertContains('CommentingController/spam/'. $comment->ID, $comment->SpamLink()->getValue());

		$spam = $this->get('CommentingController/spam/'.$comment->ID);
		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(1, $check->IsSpam);

		$this->assertNull($check->SpamLink());
	}

	public function testHamComment() {
		$comment = $this->objFromFixture('Comment', 'secondComC');
		$this->assertNull($comment->HamLink(), 'No permission to see mark as ham link');
		$ham = $this->get('CommentingController/ham/'.$comment->ID);

		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(1, $check->IsSpam, 'No permission to mark as ham');

		$this->autoFollowRedirection = false;
		$this->logInAs('commentadmin');

		$this->assertContains('CommentingController/ham/'. $comment->ID, $comment->HamLink()->getValue());

		$ham = $this->get('CommentingController/ham/'.$comment->ID);
		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(0, $check->IsSpam);

		$this->assertNull($check->HamLink());
	}
	
	public function testApproveComment() {
		$comment = $this->objFromFixture('Comment', 'secondComB');
		$this->assertNull($comment->ApproveLink(), 'No permission to see mark as approved link');
		$ham = $this->get('CommentingController/approve/'.$comment->ID);

		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(0, $check->Moderated, 'No permission to mark as approved');

		$this->autoFollowRedirection = false;
		$this->logInAs('commentadmin');

		$this->assertContains('CommentingController/approve/'. $comment->ID, $comment->ApproveLink()->getValue());

		$ham = $this->get('CommentingController/approve/'.$comment->ID);
		$check = DataObject::get_by_id('Comment', $comment->ID);
		$this->assertEquals(1, $check->Moderated);

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
}


/**
 * @package comments
 * @subpackage tests
 */
class CommentableItem extends DataObject implements TestOnly {

	public static $db = array(
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
	
	public static $allowed_actions = array(
		"*" => true
	);
	
	public function index() {
		return CommentableItem::get()->first()->CommentsForm();
	}
}
