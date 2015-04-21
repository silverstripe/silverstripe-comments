<?php

/**
 * @package comments
 * @subpackage tests
 */
class CommentingControllerTest extends FunctionalTest {
	
	public static $fixture_file = 'CommentsTest.yml';

	protected $extraDataObjects = array(
		'CommentableItem'
	);
	
	protected $securityEnabled;

	public function tearDown() {
		if($this->securityEnabled) {
			SecurityToken::enable();
		} else {
			SecurityToken::disable();
		}
		parent::tearDown();
	}

	public function setUp() {
		parent::setUp();
		$this->securityEnabled = SecurityToken::is_enabled();
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

	public function testCommentsForm() {
		SecurityToken::disable();
		$this->autoFollowRedirection = false;
		$parent = $this->objFromFixture('CommentableItem', 'first');

		// Test posting to base comment
		$response = $this->post('CommentingController/CommentsForm',
			array(
				'Name' => 'Poster',
				'Email' => 'guy@test.com',
				'Comment' => 'My Comment',
				'ParentID' => $parent->ID,
				'BaseClass' => 'CommentableItem',
				'action_doPostComment' => 'Post'
			)
		);
		$this->assertEquals(302, $response->getStatusCode());
		$this->assertStringStartsWith('CommentableItem_Controller#comment-', $response->getHeader('Location'));
		$this->assertDOSEquals(
			array(array(
				'Name' => 'Poster',
				'Email' => 'guy@test.com',
				'Comment' => 'My Comment',
				'ParentID' => $parent->ID,
				'BaseClass' => 'CommentableItem',
			)),
			Comment::get()->filter('Email', 'guy@test.com')
		);
		
		// Test posting to parent comment
		$parentComment = $this->objFromFixture('Comment', 'firstComA');
		$this->assertEquals(0, $parentComment->ChildComments()->count());

		$response = $this->post(
			'CommentingController/reply/'.$parentComment->ID,
			array(
				'Name' => 'Test Author',
				'Email' => 'test@test.com',
				'Comment' => 'Making a reply to firstComA',
				'ParentID' => $parent->ID,
				'BaseClass' => 'CommentableItem',
				'ParentCommentID' => $parentComment->ID,
				'action_doPostComment' => 'Post'
			)
		);
		$this->assertEquals(302, $response->getStatusCode());
		$this->assertStringStartsWith('CommentableItem_Controller#comment-', $response->getHeader('Location'));
		$this->assertDOSEquals(array(array(
			'Name' => 'Test Author',
				'Email' => 'test@test.com',
				'Comment' => 'Making a reply to firstComA',
				'ParentID' => $parent->ID,
				'BaseClass' => 'CommentableItem',
				'ParentCommentID' => $parentComment->ID
		)), $parentComment->ChildComments());
	}
}
