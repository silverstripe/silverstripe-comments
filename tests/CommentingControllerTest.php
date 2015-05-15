<?php

/**
 * @mixin PHPUnit_Framework_TestCase
 *
 * @package comments
 * @subpackage tests
 */
class CommentingControllerTest extends FunctionalTest {
	/**
	 * @var string
	 */
	public static $fixture_file = 'CommentsTest.yml';

	/**
	 * @var array
	 */
	protected $extraDataObjects = array(
		'HasComments',
	);

	/**
	 * @var bool
	 */
	protected $securityEnabled;

	/**
	 * {@inheritdoc}
	 */
	public function tearDown() {
		if($this->securityEnabled) {
			SecurityToken::enable();
		} else {
			SecurityToken::disable();
		}

		parent::tearDown();
	}

	/**
	 * {@inheritdoc}
	 */
	public function setUp() {
		parent::setUp();

		$this->securityEnabled = SecurityToken::is_enabled();
	}

	public function testRSS() {
		$item = $this->objFromFixture('HasComments', 'first');

		$response = $this->get('CommentingController/rss');

		$this->assertEquals(10, substr_count($response->getBody(), '<item>'), '10 approved, non spam comments on page 1');

		$response = $this->get('CommentingController/rss?start=10');

		$this->assertEquals(4, substr_count($response->getBody(), '<item>'), '3 approved, non spam comments on page 2');

		$response = $this->get('CommentingController/rss/HasComments');

		$this->assertEquals(10, substr_count($response->getBody(), '<item>'));

		$response = $this->get('CommentingController/rss/HasComments?start=10');

		$this->assertEquals(4, substr_count($response->getBody(), '<item>'), '3 approved, non spam comments on page 2');

		$response = $this->get('CommentingController/rss/HasComments/' . $item->ID);

		$this->assertEquals(1, substr_count($response->getBody(), '<item>'));
		$this->assertContains('<dc:creator>FA</dc:creator>', $response->getBody());

		$response = $this->get('CommentingController/rss/Fake');
		$this->assertEquals(404, $response->getStatusCode());
	}

	public function testCommentsForm() {
		SecurityToken::disable();

		$this->autoFollowRedirection = false;

		$parent = $this->objFromFixture('HasComments', 'first');

		$response = $this->post('CommentingController/CommentsForm',
			array(
				'Name' => 'Poster',
				'Email' => 'guy@test.com',
				'Comment' => 'My Comment',
				'ParentID' => $parent->ID,
				'BaseClass' => 'HasComments',
				'action_doPostComment' => 'Post'
			)
		);
		$this->assertEquals(302, $response->getStatusCode());
		$this->assertStringStartsWith('HasComments_Controller#comment-', $response->getHeader('Location'));
		$this->assertDOSEquals(
			array(
				array(
					'Name' => 'Poster',
					'Email' => 'guy@test.com',
					'Comment' => 'My Comment',
					'ParentID' => $parent->ID,
					'BaseClass' => 'HasComments',
				)
			),
			Comment::get()->filter('Email', 'guy@test.com')
		);

		/**
		 * @var Comment $parentComment
		 */
		$parentComment = $this->objFromFixture('Comment', 'firstComA');

		$this->assertEquals(0, $parentComment->ChildComments()->count());

		$response = $this->post(
			'CommentingController/reply/' . $parentComment->ID,
			array(
				'Name' => 'Test Author',
				'Email' => 'test@test.com',
				'Comment' => 'Making a reply to firstComA',
				'ParentID' => $parent->ID,
				'BaseClass' => 'HasComments',
				'ParentCommentID' => $parentComment->ID,
				'action_doPostComment' => 'Post'
			)
		);

		$this->assertEquals(302, $response->getStatusCode());
		$this->assertStringStartsWith('HasComments_Controller#comment-', $response->getHeader('Location'));
		$this->assertDOSEquals(array(
			array(
				'Name' => 'Test Author',
				'Email' => 'test@test.com',
				'Comment' => 'Making a reply to firstComA',
				'ParentID' => $parent->ID,
				'BaseClass' => 'HasComments',
				'ParentCommentID' => $parentComment->ID
			)
		), $parentComment->ChildComments());
	}
}
