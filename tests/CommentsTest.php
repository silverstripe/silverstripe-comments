<?php

/**
 * @package comments
 */
class CommentsTest extends FunctionalTest {
	
	static $fixture_file = 'comments/tests/CommentsTest.yml';
	
	function testCanView() {
		$visitor = $this->objFromFixture('Member', 'visitor');
		$admin = $this->objFromFixture('Member', 'commentadmin');
		$comment = $this->objFromFixture('Comment', 'firstComA');
		
		$this->assertTrue($comment->canView($visitor), 
			'Unauthenticated members can view comments associated to a page with ProvideComments=1'
		);
		$this->assertTrue($comment->canView($admin),
			'Admins with CMS_ACCESS_CommentAdmin permissions can view comments associated to a page with ProvideComments=1'
		);
		
		$disabledComment = $this->objFromFixture('Comment', 'disabledCom');
		
		$this->assertFalse($disabledComment->canView($visitor),
		'Unauthenticated members can not view comments associated to a page with ProvideComments=0'
		);
		$this->assertTrue($disabledComment->canView($admin),
			'Admins with CMS_ACCESS_CommentAdmin permissions can view comments associated to a page with ProvideComments=0'
		);
	}
	
	function testCanEdit() {
		$visitor = $this->objFromFixture('Member', 'visitor');
		$admin = $this->objFromFixture('Member', 'commentadmin');
		$comment = $this->objFromFixture('Comment', 'firstComA');
		
		$this->assertFalse($comment->canEdit($visitor));
		$this->assertTrue($comment->canEdit($admin));
	}
	
	function testCanDelete() {
		$visitor = $this->objFromFixture('Member', 'visitor');
		$admin = $this->objFromFixture('Member', 'commentadmin');
		$comment = $this->objFromFixture('Comment', 'firstComA');
		
		$this->assertFalse($comment->canEdit($visitor));
		$this->assertTrue($comment->canEdit($admin));
	}
	
	function testDeleteComment() {
		$firstPage = $this->objFromFixture('Page', 'first');
		$this->autoFollowRedirection = false;
		$this->logInAs('commentadmin');
		
		$firstComment = $this->objFromFixture('Comment', 'firstComA');
		$firstCommentID = $firstComment->ID;
		Director::test($firstPage->RelativeLink(), null, $this->session());
		$delete = $this->get('CommentingController/delete/'.$firstComment->ID);
	
		$this->assertFalse(DataObject::get_by_id('Comment', $firstCommentID));
	}
	
	function testCommenterURLWrite() {
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
