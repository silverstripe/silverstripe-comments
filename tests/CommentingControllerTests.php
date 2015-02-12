<?php

/**
 * @package comments
 * @subpackage tests
 */
class CommentingControllerTests extends FunctionalTest {
	
	public static $fixture_file = 'comments/tests/CommentsTest.yml';

	protected $extraDataObjects = array(
		'CommentableItem'
	);

	public function setUp() {
		parent::setUp();

		Commenting::add('CommentableItem');
	}

	public function testRSS() {
		$item = $this->objFromFixture('CommentableItem', 'first');

		// comments sitewide
		$response = $this->get('CommentingController/rss');
		$this->assertEquals(10, substr_count($response->getBody(), "<item>"), "10 approved, non spam comments on page 1");

		$response = $this->get('CommentingController/rss?start=10');
		$this->assertEquals(4, substr_count($response->getBody(), "<item>"), "3 approved, non spam comments on page 2");

		// all comments on a type
		$response = $this->get('CommentingController/rss/CommentableItem');
		$this->assertEquals(10, substr_count($response->getBody(), "<item>"));

		$response = $this->get('CommentingController/rss/CommentableItem?start=10');
		$this->assertEquals(4, substr_count($response->getBody(), "<item>"), "3 approved, non spam comments on page 2");

		// specific page
		$response = $this->get('CommentingController/rss/CommentableItem/'.$item->ID);
		$this->assertEquals(1, substr_count($response->getBody(), "<item>"));
		$this->assertContains('<dc:creator>FA</dc:creator>', $response->getBody());

		// test accessing comments on a type that doesn't exist
		$response = $this->get('CommentingController/rss/Fake');
		$this->assertEquals(404, $response->getStatusCode());
	}

	public function testRSSSecuredCommentsForm() {
		$this->markTestIncomplete("Not implemented");
	}

	public function testCommentsForm() {
		$this->markTestIncomplete("Not implemented");
	}

	public function testDoCommentsForm() {
		$this->markTestIncomplete("Not implemented");
	}

	public function testDeleteWithChildComments() {
		$this->logInAs('commentadmin');
		
		// create minimal parent hierarchy, namely parent and child
		$comment1 = $this->objFromFixture('Comment', 'firstComA');
		$comment2 = $this->objFromFixture('Comment', 'secondComC');
		$comment2->ParentCommentID = $comment1->ID;
		$comment2->write();

		// check permissions for deletion of comment
		$this->assertEquals(true, $comment1->canDelete());

		// try to delete the parent comment
		$response = $this->get('CommentingController/delete/'.$comment1->ID);
		$check = DataObject::get_by_id('Comment', $comment1->ID);
		$this->assertEquals($check->MarkedAsDeleted, true);

	}
}
