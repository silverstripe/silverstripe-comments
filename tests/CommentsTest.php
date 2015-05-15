<?php

/**
 * @mixin PHPUnit_Framework_TestCase
 *
 * @package comments
 */
class CommentsTest extends FunctionalTest {
	/**
	 * @var string
	 */
	public static $fixture_file = 'comments/tests/CommentsTest.yml';

	/**
	 * @var array
	 */
	protected $extraDataObjects = array(
		'HasComments',
	);

	/**
	 * {@inheritdoc}
	 */
	public function setUp() {
		parent::setUp();
		Config::nest();

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

		Config::inst()->update('HasComments', 'comments', array(
			'enabled_cms' => true,
		));
	}

	/**
	 * {@inheritdoc}
	 */
	public function tearDown() {
		Config::unnest();

		parent::tearDown();
	}

	public function testCommentsList() {
		Config::inst()->update('HasComments', 'comments', array(
			'require_moderation_nonmembers' => false,
			'require_moderation' => false,
			'require_moderation_cms' => false,
		));

		/**
		 * @var HasComments $item
		 */
		$item = $this->objFromFixture('HasComments', 'spammed');

		$this->assertEquals('None', $item->ModerationRequired);
		$this->assertDOSEquals(
			array(
				array('Name' => 'Comment 1'),
				array('Name' => 'Comment 3')
			),
			$item->Comments(),
			'Only 2 non spam posts should be shown'
		);

		Config::inst()->update('HasComments', 'comments', array('require_moderation_nonmembers' => true));

		$this->assertEquals('NonMembersOnly', $item->ModerationRequired);

		Config::inst()->update('HasComments', 'comments', array('require_moderation' => true));

		$this->assertEquals('Required', $item->ModerationRequired);

		$this->assertDOSEquals(
			array(
				array('Name' => 'Comment 3')
			),
			$item->Comments(),
			'Only 1 non spam, moderated post should be shown'
		);
		$this->assertEquals(1, $item->Comments()->Count());

		Config::inst()->update('HasComments', 'comments', array('require_moderation' => false));

		$this->assertEquals(1, $item->Comments()->Count());

		Config::inst()->update('HasComments', 'comments', array('require_moderation_nonmembers' => false));

		$this->assertEquals(2, $item->Comments()->Count());

		Config::inst()->update('HasComments', 'comments', array(
			'require_moderation' => true,
			'frontend_moderation' => true,
		));

		$this->assertEquals(1, $item->Comments()->Count());

		$this->logInWithPermission('ADMIN');

		$this->assertEquals(2, $item->Comments()->Count());

		Config::inst()->update('HasComments', 'comments', array(
			'require_moderation' => true,
			'frontend_moderation' => false,
			'frontend_spam' => true,
		));

		if($member = Member::currentUser()) {
			$member->logOut();
		}

		$this->assertEquals(1, $item->Comments()->Count());

		$this->logInWithPermission('ADMIN');

		$this->assertEquals(2, $item->Comments()->Count());

		Config::inst()->update('HasComments', 'comments', array(
			'require_moderation' => true,
			'frontend_moderation' => true,
			'frontend_spam' => true,
		));

		if($member = Member::currentUser()) {
			$member->logOut();
		}

		$this->assertEquals(1, $item->Comments()->Count());

		$this->logInWithPermission('ADMIN');

		$this->assertEquals(4, $item->Comments()->Count());
	}

	/**
	 * Test moderation options configured via the CMS.
	 */
	public function testCommentCMSModerationList() {
		Config::inst()->update('HasComments', 'comments', array(
			'require_moderation' => true,
			'require_moderation_cms' => true,
		));

		/**
		 * @var HasComments $item
		 */
		$item = $this->objFromFixture('HasComments', 'spammed');

		$this->assertEquals('None', $item->ModerationRequired);
		$this->assertDOSEquals(
			array(
				array('Name' => 'Comment 1'),
				array('Name' => 'Comment 3')
			),
			$item->Comments(),
			'Only 2 non spam posts should be shown'
		);

		$item->ModerationRequired = 'NonMembersOnly';
		$item->write();

		$this->assertEquals('NonMembersOnly', $item->ModerationRequired);

		$item->ModerationRequired = 'Required';
		$item->write();

		$this->assertEquals('Required', $item->ModerationRequired);

		$this->assertDOSEquals(
			array(
				array('Name' => 'Comment 3')
			),
			$item->Comments(),
			'Only 1 non spam, moderated post should be shown'
		);

		$item->ModerationRequired = 'NonMembersOnly';
		$item->write();

		$this->assertEquals(1, $item->Comments()->Count());

		$item->ModerationRequired = 'None';
		$item->write();

		$this->assertEquals(2, $item->Comments()->Count());
	}

	public function testCanPostComment() {
		Config::inst()->update('HasComments', 'comments', array(
			'require_login' => false,
			'require_login_cms' => false,
			'required_permission' => false,
		));

		/**
		 * @var HasComments $item
		 */
		$item = $this->objFromFixture('HasComments', 'first');

		/**
		 * @var HasComments $item2
		 */
		$item2 = $this->objFromFixture('HasComments', 'second');

		if($member = Member::currentUser()) {
			$member->logOut();
		}

		$this->assertFalse($item->CommentsRequireLogin);
		$this->assertTrue($item->canPostComment());

		Config::inst()->update('HasComments', 'comments', array(
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

		Config::inst()->update('HasComments', 'comments', array(
			'required_permission' => false,
		));

		$this->assertTrue($item->CommentsRequireLogin);

		if($member = Member::currentUser()) {
			$member->logOut();
		}

		$this->assertFalse($item->canPostComment());

		$this->logInWithPermission('ANY_PERMISSION');

		$this->assertTrue($item->canPostComment());

		Config::inst()->update('HasComments', 'comments', array(
			'require_login' => true,
			'require_login_cms' => true,
		));

		$this->assertFalse($item->CommentsRequireLogin);
		$this->assertTrue($item2->CommentsRequireLogin);

		if($member = Member::currentUser()) {
			$member->logOut();
		}

		$this->assertTrue($item->canPostComment());
		$this->assertFalse($item2->canPostComment());

		$this->logInWithPermission('ANY_PERMISSION');

		$this->assertTrue($item->canPostComment());
		$this->assertTrue($item2->canPostComment());

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
		if($member = Member::currentUser()) {
			$member->logOut();
		}

		/**
		 * @var Comment $comment
		 */
		$comment = $this->objFromFixture('Comment', 'firstComA');

		$this->assertNull($comment->DeleteLink(), 'No permission to see delete link');

		$delete = $this->get(sprintf(
			'CommentingController/delete/%s?ajax=1',
			$comment->ID
		));

		$this->assertEquals(403, $delete->getStatusCode());

		$this->logInAs('visitor');

		$this->assertNull($comment->DeleteLink(), 'No permission to see delete link');

		$this->logInAs('commentadmin');

		$commentAdminLink = $comment->DeleteLink();

		$this->assertContains(
			sprintf(
				'CommentingController/delete/%s?t=',
				$comment->ID
			),
			$commentAdminLink
		);

		$this->logInAs('commentadmin2');

		$delete = $this->get($commentAdminLink);

		$this->assertEquals(400, $delete->getStatusCode());
		$this->assertNotEquals($comment->DeleteLink(), $commentAdminLink);

		$this->autoFollowRedirection = false;

		$delete = $this->get($comment->DeleteLink());

		$this->assertEquals(302, $delete->getStatusCode());
		$this->assertFalse(DataObject::get_by_id('Comment', $comment->ID));
	}

	public function testSpamComment() {
		if($member = Member::currentUser()) {
			$member->logOut();
		}

		/**
		 * @var Comment $comment
		 */
		$comment = $this->objFromFixture('Comment', 'firstComA');

		$this->assertNull($comment->SpamLink(), 'No permission to see mark as spam link');

		$spam = $this->get(sprintf(
			'CommentingController/spam/%s?ajax=1',
			$comment->ID
		));

		$this->assertEquals(403, $spam->getStatusCode());
		$this->assertEquals(0, $comment->IsSpam, 'No permission to mark as spam');

		$this->logInAs('visitor');

		$this->assertNull($comment->SpamLink(), 'No permission to see mark as spam link');

		$this->logInAs('commentadmin');

		$commentAdminLink = $comment->SpamLink();

		$this->assertContains(
			sprintf(
				'CommentingController/spam/%s?t=',
				$comment->ID
			),
			$commentAdminLink
		);

		$this->logInAs('commentadmin2');

		$spam = $this->get($commentAdminLink);

		$this->assertEquals(400, $spam->getStatusCode());
		$this->assertNotEquals($comment->SpamLink(), $commentAdminLink);

		$this->autoFollowRedirection = false;

		$spam = $this->get($comment->SpamLink());

		$this->assertEquals(302, $spam->getStatusCode());

		/**
		 * @var Comment $comment
		 */
		$comment = DataObject::get_by_id('Comment', $comment->ID);

		$this->assertEquals(1, $comment->IsSpam);
		$this->assertNull($comment->SpamLink());
	}

	public function testHamComment() {
		if($member = Member::currentUser()) {
			$member->logOut();
		}

		/**
		 * @var Comment $comment
		 */
		$comment = $this->objFromFixture('Comment', 'secondComC');

		$this->assertNull($comment->HamLink(), 'No permission to see mark as ham link');

		$ham = $this->get(sprintf(
			'CommentingController/ham/%s?ajax=1',
			$comment->ID
		));

		$this->assertEquals(403, $ham->getStatusCode());

		$this->logInAs('visitor');

		$this->assertNull($comment->HamLink(), 'No permission to see mark as ham link');

		$this->logInAs('commentadmin');

		$adminCommentLink = $comment->HamLink();

		$this->assertContains(
			sprintf(
				'CommentingController/ham/%s?t=',
				$comment->ID
			),
			$adminCommentLink
		);

		$this->logInAs('commentadmin2');

		$ham = $this->get($adminCommentLink);

		$this->assertEquals(400, $ham->getStatusCode());
		$this->assertNotEquals($comment->HamLink(), $adminCommentLink);

		$this->autoFollowRedirection = false;

		$ham = $this->get($comment->HamLink());

		$this->assertEquals(302, $ham->getStatusCode());

		/**
		 * @var Comment $comment
		 */
		$comment = DataObject::get_by_id('Comment', $comment->ID);

		$this->assertEquals(0, $comment->IsSpam);
		$this->assertNull($comment->HamLink());
	}

	public function testApproveComment() {
		if($member = Member::currentUser()) {
			$member->logOut();
		}

		/**
		 * @var Comment $comment
		 */
		$comment = $this->objFromFixture('Comment', 'secondComB');

		$this->assertNull($comment->ApproveLink(), 'No permission to see approve link');

		$approve = $this->get(sprintf(
			'CommentingController/approve/%s?ajax=1',
			$comment->ID
		));

		$this->assertEquals(403, $approve->getStatusCode());

		$this->logInAs('visitor');

		$this->assertNull($comment->ApproveLink(), 'No permission to see approve link');

		$this->logInAs('commentadmin');

		$adminCommentLink = $comment->ApproveLink();

		$this->assertContains(
			sprintf(
				'CommentingController/approve/%s?t=',
				$comment->ID
			),
			$adminCommentLink
		);

		$this->logInAs('commentadmin2');

		$approve = $this->get($adminCommentLink);

		$this->assertEquals(400, $approve->getStatusCode());

		$this->assertNotEquals($comment->ApproveLink(), $adminCommentLink);

		$this->autoFollowRedirection = false;

		$approve = $this->get($comment->ApproveLink());

		$this->assertEquals(302, $approve->getStatusCode());

		/**
		 * @var Comment $comment
		 */
		$comment = DataObject::get_by_id('Comment', $comment->ID);

		$this->assertEquals(1, $comment->Moderated);
		$this->assertNull($comment->ApproveLink());
	}

	public function testCommenterURLWrite() {
		$comment = new Comment();

		$protocols = array(
			'HTTP',
			'HTTPS',
		);

		$url = '://example.com';

		foreach($protocols as $protocol) {
			$comment->URL = $protocol . $url;
			$comment->write();

			$this->assertEquals($comment->URL, $protocol . $url, $protocol . ':// is a valid protocol');
		}
	}

	public function testSanitizesWithAllowHtml() {
		if(!class_exists('HTMLPurifier')) {
			$this->markTestSkipped('HTMLPurifier class not found');
		}

		$originalHtmlAllowed = Commenting::get_config_value('HasComments', 'html_allowed');

		$comment1 = new Comment();
		$comment1->BaseClass = 'HasComments';
		$comment1->Comment = '<p><script>alert("w00t")</script>my comment</p>';
		$comment1->write();

		$this->assertEquals(
			'<p><script>alert("w00t")</script>my comment</p>',
			$comment1->Comment,
			'Does not remove HTML tags with html_allowed=false, ' .
			'which is correct behaviour because the HTML will be escaped'
		);

		Commenting::set_config_value('HasComments', 'html_allowed', true);

		$comment2 = new Comment();
		$comment2->BaseClass = 'HasComments';
		$comment2->Comment = '<p><script>alert("w00t")</script>my comment</p>';
		$comment2->write();

		$this->assertEquals(
			'<p>my comment</p>',
			$comment2->Comment,
			'Removes HTML tags which are not on the whitelist'
		);

		Commenting::set_config_value('HasComments', 'html_allowed', $originalHtmlAllowed);
	}

	public function testDefaultTemplateRendersHtmlWithAllowHtml() {
		if(!class_exists('HTMLPurifier')) {
			$this->markTestSkipped('HTMLPurifier class not found');
		}

		$originalHtmlAllowed = Commenting::get_config_value('HasComments', 'html_allowed');

		$item = new HasComments();
		$item->write();

		$comment = new Comment();
		$comment->Comment = '<p>my comment</p>';
		$comment->ParentID = $item->ID;
		$comment->BaseClass = 'HasComments';
		$comment->write();

		$html = $item
			->customise(array(
				'CommentsEnabled' => true,
			))
			->renderWith('CommentsInterface');

		$this->assertContains(
			'&lt;p&gt;my comment&lt;/p&gt;',
			$html
		);

		Commenting::set_config_value('HasComments', 'html_allowed', true);

		$html = $item
			->customise(array(
				'CommentsEnabled' => true,
			))
			->renderWith('CommentsInterface');

		$this->assertContains(
			'<p>my comment</p>',
			$html
		);

		Commenting::set_config_value('HasComments', 'html_allowed', $originalHtmlAllowed);
	}

}

/**
 * @mixin CommentsExtension
 *
 * @package comments
 * @subpackage tests
 */
class HasComments extends DataObject implements TestOnly {
	/**
	 * @var array
	 */
	private static $db = array(
		'ProvideComments' => 'Boolean',
		'Title' => 'Varchar',
	);

	/**
	 * @var array
	 */
	private static $extensions = array(
		'CommentsExtension',
	);

	/**
	 * @return string
	 */
	public function RelativeLink() {
		return 'HasComments_Controller';
	}

	/**
	 * {@inheritdoc}
	 */
	public function canView($member = null) {
		return true;
	}

	/**
	 * @return string
	 */
	public function Link() {
		return $this->RelativeLink();
	}

	/**
	 * @return string
	 */
	public function AbsoluteLink() {
		return Director::absoluteURL($this->RelativeLink());
	}
}

/**
 * @package comments
 * @subpackage tests
 */
class HasComments_Controller extends Controller implements TestOnly {
	/**
	 * @return Form
	 */
	public function index() {
		return HasComments::get()->first()->CommentsForm();
	}
}
