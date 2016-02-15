<?php

class CommentsExtensionTest extends SapphireTest {

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
            'Member' => false,
        ));

        $this->requiredExtensions = array(
            'CommentableItem' => 'CommentsExtension'
        );

        // Configure this dataobject
        Config::inst()->update('CommentableItem', 'comments', array(
            'enabled_cms' => true
        ));
    }

    public function tearDown() {
        Config::unnest();
        parent::tearDown();
    }

	public function testPopulateDefaults() {
		$this->markTestSkipped('TODO');
	}

	public function testUpdateSettingsFields() {
        $this->markTestSkipped('This needs SiteTree installed');
	}

	public function testGetModerationRequired() {

        // the 3 options take precedence in this order, executed if true
        Config::inst()->update('CommentableItem', 'comments', array(
            'require_moderation_cms' => true,
            'require_moderation' => true,
            'require_moderation_nonmembers' => true
        ));

        // With require moderation CMS set to true, the value of the field
        // 'ModerationRequired' is returned
        $item = $this->objFromFixture('CommentableItem', 'first');
        $item->ModerationRequired = 'None';
        $this->assertEquals('None', $item->getModerationRequired());
        $item->ModerationRequired = 'Required';
        $this->assertEquals('Required', $item->getModerationRequired());
        $item->ModerationRequired = 'NonMembersOnly';
        $this->assertEquals('NonMembersOnly', $item->getModerationRequired());

        Config::inst()->update('CommentableItem', 'comments', array(
            'require_moderation_cms' => false,
            'require_moderation' => true,
            'require_moderation_nonmembers' => true
        ));
        $this->assertEquals('Required', $item->getModerationRequired());

        Config::inst()->update('CommentableItem', 'comments', array(
            'require_moderation_cms' => false,
            'require_moderation' => false,
            'require_moderation_nonmembers' => true
        ));
        $this->assertEquals('NonMembersOnly', $item->getModerationRequired());

        Config::inst()->update('CommentableItem', 'comments', array(
            'require_moderation_cms' => false,
            'require_moderation' => false,
            'require_moderation_nonmembers' => false
        ));
        $this->assertEquals('None', $item->getModerationRequired());
	}

	public function testGetCommentsRequireLogin() {
		Config::inst()->update('CommentableItem', 'comments', array(
            'require_login_cms' => true
        ));

        // With require moderation CMS set to true, the value of the field
        // 'ModerationRequired' is returned
        $item = $this->objFromFixture('CommentableItem', 'first');
        $item->CommentsRequireLogin = true;
        $this->assertTrue($item->getCommentsRequireLogin());
        $item->CommentsRequireLogin = false;
        $this->assertFalse($item->getCommentsRequireLogin());

        Config::inst()->update('CommentableItem', 'comments', array(
            'require_login_cms' => false,
            'require_login' => false
        ));
        $this->assertFalse($item->getCommentsRequireLogin());
        Config::inst()->update('CommentableItem', 'comments', array(
            'require_login_cms' => false,
            'require_login' => true
        ));
        $this->assertTrue($item->getCommentsRequireLogin());

	}

	public function testAllComments() {
		$this->markTestSkipped('TODO');
	}

	public function testAllVisibleComments() {
		$this->markTestSkipped('TODO');
	}

	public function testComments() {
		$this->markTestSkipped('TODO');
	}

	public function testGetCommentsEnabled() {
		$this->markTestSkipped('TODO');
	}

	public function testGetCommentHolderID() {
        $item = $this->objFromFixture('CommentableItem', 'first');
        Config::inst()->update('CommentableItem', 'comments', array(
            'comments_holder_id' => 'commentid_test1',
        ));
        $this->assertEquals('commentid_test1', $item->getCommentHolderID());

        Config::inst()->update('CommentableItem', 'comments', array(
            'comments_holder_id' => 'commtentid_test_another',
        ));
        $this->assertEquals('commtentid_test_another', $item->getCommentHolderID());
	}


	public function testGetPostingRequiredPermission() {
		$this->markTestSkipped('TODO');
	}

	public function testCanModerateComments() {
        // ensure nobody logged in
        if(Member::currentUser()) { Member::currentUser()->logOut(); }

		$item = $this->objFromFixture('CommentableItem', 'first');
        $this->assertFalse($item->canModerateComments());

        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $this->assertTrue($item->canModerateComments());

	}

	public function testGetCommentRSSLink() {
	   $item = $this->objFromFixture('CommentableItem', 'first');
       $link = $item->getCommentRSSLink();
       $this->assertEquals('/CommentingController/rss', $link);
	}


	public function testGetCommentRSSLinkPage() {
		$item = $this->objFromFixture('CommentableItem', 'first');
        $page = $item->getCommentRSSLinkPage();
        $this->assertEquals(
            '/CommentingController/rss/CommentableItem/' . $item->ID,
            $page
        );
	}

	public function testCommentsForm() {
        Config::inst()->update('CommentableItem', 'comments', array(
            'include_js' => false
            )
        );
		$item = $this->objFromFixture('CommentableItem', 'first');

        // The comments form is HTML to do assertions by contains
        $cf = $item->CommentsForm();
        $expected = '<form id="Form_CommentsForm" action="/CommentingController'
        . '/CommentsForm" method="post" enctype="application/x-www-form-urlenco'
        . 'ded">';
        $this->assertContains($expected, $cf);
        $this->assertContains('<h4>Post your comment</h4>', $cf);

        // check the comments form exists
        $expected = '<input type="text" name="Name" value="ADMIN User" class="text" id="Form_CommentsForm_Name" required="required"';
        $this->assertContains($expected, $cf);

        $expected = '<input type="email" name="Email" value="ADMIN@example.org" class="email text" id="Form_CommentsForm_Email"';
        $this->assertContains($expected, $cf);

        $expected = '<input type="text" name="URL" class="text" id="Form_CommentsForm_URL" data-msg-url="Please enter a valid URL"';
        $this->assertContains($expected, $cf);

        $expected = '<input type="hidden" name="ParentID" value="' . $item->ID . '" class="hidden" id="Form_CommentsForm_ParentID" />';
        $this->assertContains($expected, $cf);

        $expected = '<textarea name="Comment" class="textarea" id="Form_CommentsForm_Comment" required="required"';
        $this->assertContains($expected, $cf);

        $expected = '<input type="submit" name="action_doPostComment" value="Post" class="action" id="Form_CommentsForm_action_doPostComment"';
        $this->assertContains($expected, $cf);

        $expected = '<a href="/CommentingController/spam/';
        $this->assertContains($expected, $cf);

        $expected = '<p>Reply to firstComA 1</p>';
        $this->assertContains($expected, $cf);

        $expected = '<a href="/CommentingController/delete';
        $this->assertContains($expected, $cf);

        $expected = '<p>Reply to firstComA 2</p>';
        $this->assertContains($expected, $cf);

        $expected = '<p>Reply to firstComA 3</p>';
        $this->assertContains($expected, $cf);

        // Check for JS inclusion
        $backend = Requirements::backend();
        $this->assertEquals(
            array(),
            $backend->get_javascript()
        );

        Config::inst()->update('CommentableItem', 'comments', array(
            'include_js' => true
            )
        );
        $cf = $item->CommentsForm();

        $backend = Requirements::backend();
        $this->assertEquals(
            array(
                'framework/thirdparty/jquery/jquery.js',
                'framework/thirdparty/jquery-entwine/dist/jquery.entwine-dist.js',
                'framework/thirdparty/jquery-validate/lib/jquery.form.js',
                'comments/thirdparty/jquery-validate/jquery.validate.min.js',
                'framework/javascript/i18n.js',
                'comments/javascript/lang/en.js',
                'comments/javascript/CommentsInterface.js'
            ),
            $backend->get_javascript()
        );
	}

	public function testAttachedToSiteTree() {
		$this->markTestSkipped('TODO');
	}

	public function testPagedComments() {
        $item = $this->objFromFixture('CommentableItem', 'first');
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
            $this->objFromFixture('Comment', 'firstComA')->Comment,
            $results[3]->Comment
        );
        $this->assertEquals(
            $this->objFromFixture('Comment', 'firstComAChild1')->Comment,
            $results[2]->Comment
        );
        $this->assertEquals(
            $this->objFromFixture('Comment', 'firstComAChild2')->Comment,
            $results[1]->Comment
        );
        $this->assertEquals(
            $this->objFromFixture('Comment', 'firstComAChild3')->Comment,
            $results[0]->Comment
        );

        $this->assertEquals(4, sizeof($results));
	}

	public function testGetCommentsOption() {
		$this->markTestSkipped('TODO');
	}

	public function testUpdateModerationFields() {
		$this->markTestSkipped('TODO');
	}

	public function testUpdateCMSFields() {
        Config::inst()->update('CommentableItem', 'comments', array(
            'require_login_cms' => false
            )
        );
        $this->logInWithPermission('ADMIN');
		$item = $this->objFromFixture('CommentableItem', 'first');
        $item->ProvideComments = true;
        $item->write();
        $fields = $item->getCMSFields();
        CommentTestHelper::assertFieldsForTab($this, 'Root.Comments',
            array('CommentsNewCommentsTab', 'CommentsCommentsTab', 'CommentsSpamCommentsTab'),
            $fields
        );

        CommentTestHelper::assertFieldsForTab($this, 'Root.Comments.CommentsNewCommentsTab',
            array('NewComments'),
            $fields
        );

        CommentTestHelper::assertFieldsForTab($this, 'Root.Comments.CommentsCommentsTab',
            array('ApprovedComments'),
            $fields
        );

        CommentTestHelper::assertFieldsForTab($this,  'Root.Comments.CommentsSpamCommentsTab',
            array('SpamComments'),
            $fields
        );

        Config::inst()->update('CommentableItem', 'comments', array(
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

        Config::inst()->update('CommentableItem', 'comments', array(
            'require_login_cms' => true,
            'require_moderation_cms' => true
            )
        );

        $fields = $item->getCMSFields();
        CommentTestHelper::assertFieldsForTab(
            $this,
            'Root.Settings',
            array('Comments', 'ModerationRequired'), $fields
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



    public function testDeprecatedMethods() {
        $item = $this->objFromFixture('CommentableItem', 'first');
        $methodNames = array(
            'getRssLinkPage',
            'getRssLink',
            'PageComments',
            'getPostingRequiresPermission',
            'canPost',
            'getCommentsConfigured'
        );

        foreach ($methodNames as $methodName) {
            try {
                $item->$methodName();
                $this->fail('Method ' . $methodName .' should be depracated');
            } catch (PHPUnit_Framework_Error_Deprecated $e) {
                $expected = 'CommentsExtension->' . $methodName . ' is '.
                'deprecated.';
                $this->assertStringStartsWith($expected, $e->getMessage());
            }
        }

        // ooh,  $this->setExpectedException('ExpectedException', 'Expected Message');

    }

}
