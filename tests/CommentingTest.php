<?php

class CommentingTest extends SapphireTest
{

    public function setUpOnce()
    {
        parent::setUpOnce();
    }

    public function testDeprecatedMethods()
    {
        $methods = array('add', 'remove', 'has_commenting');
        foreach ($methods as $methodName) {
            try {
                Commenting::$methodName('Member');
            } catch (PHPUnit_Framework_Error_Deprecated $e) {
                $expected = 'Using Commenting:' . $methodName .' is deprecated.'
                          . ' Please use the config API instead';
                $this->assertEquals($expected, $e->getMessage());
            }
        }
    }


    public function test_set_config_value()
    {
        //    public static function set_config_value($class, $key, $value = false) {
        Commenting::set_config_value(
            'CommentableItem',
            'comments_holder_id',
            'commentable_item'
        );

        $config = Config::inst()->get(
            'CommentableItem',
            'comments'
        );
        $actual = $config['comments_holder_id'];

        $this->assertEquals(
            'commentable_item',
            $actual
        );
        Commenting::set_config_value(
            'all',
            'comments_holder_id',
            'all_items_actually_commentsextension'
        );

        $config = Config::inst()->get(
            'CommentsExtension',
            'comments'
        );
        $actual = $config['comments_holder_id'];
        $this->assertEquals(
            'all_items_actually_commentsextension',
            $actual
        );
    }

    public function test_get_config_value()
    {
        Config::inst()->update('CommentableItem', 'comments',
            array(
            'comments_holder_id' => 'commentable_item'
            )
        );
        $this->assertEquals(
            'commentable_item',
            Commenting::get_config_value('CommentableItem', 'comments_holder_id')
        );

        Config::inst()->update('CommentsExtension', 'comments',
            array(
            'comments_holder_id' => 'comments_extension'
            )
        );
        // if class is null, method uses the CommentsExtension property
        $this->assertEquals(
            'comments_extension',
            Commenting::get_config_value(null, 'comments_holder_id')
        );

        $this->setExpectedException(
            'InvalidArgumentException',
            'Member does not have commenting enabled'
        );
        Commenting::get_config_value('Member', 'comments_holder_id');
    }

    public function test_config_value_equals()
    {
        Config::inst()->update('CommentableItem', 'comments',
            array(
            'comments_holder_id' => 'some_value'
            )
        );

        $this->assertTrue(
            Commenting::config_value_equals(
                'CommentableItem',
                'comments_holder_id',
                'some_value'
            )
        );

        $this->assertNull(
            Commenting::config_value_equals(
                'CommentableItem',
                'comments_holder_id',
                'not_some_value'
            )
        );
    }

    public function test_add()
    {
        Commenting::add('Member', array('comments_holder_id' => 'test_add_value'));

        $config = Config::inst()->get(
                'Member',
                'comments'
            );
        $actual = $config['comments_holder_id'];
        $this->assertEquals(
            'test_add_value',
            $actual
        );

        Commenting::add('Member');

        $config = Config::inst()->get(
                'Member',
                'comments'
            );
        $actual = $config['comments_holder_id'];
        // no settings updated
        $this->assertEquals(
            'test_add_value',
            $actual
        );

        $this->setExpectedException('InvalidArgumentException', "\$settings needs to be an array or null");
        Commenting::add('Member', 'illegal format, not an array');
    }

    public function test_can_member_post()
    {
        // logout
        if ($member = Member::currentUser()) {
            $member->logOut();
        }

        Config::inst()->update('CommentableItem', 'comments',
            array(
            'require_login' => false
            )
        );
        $this->assertTrue(Commenting::can_member_post('CommentableItem'));

        Config::inst()->update('CommentableItem', 'comments',
            array(
            'require_login' => true
            )
        );
        $this->assertFalse(Commenting::can_member_post('CommentableItem'));

        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $this->assertTrue(Commenting::can_member_post('CommentableItem'));

        Config::inst()->update('CommentableItem', 'comments',
            array(
            'require_login' => false
            )
        );

        $this->assertTrue(Commenting::can_member_post('CommentableItem'));
    }
}
