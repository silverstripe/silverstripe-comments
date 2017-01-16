<?php

namespace SilverStripe\Comments\Tests;

use PHPUnit_Framework_Error_Deprecated;
use SilverStripe\Comments\Commenting;
use SilverStripe\Comments\Extensions\CommentsExtension;
use SilverStripe\Comments\Tests\Stubs\CommentableItem;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

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
                Commenting::$methodName(Member::class);
            } catch (PHPUnit_Framework_Error_Deprecated $e) {
                $expected = 'Using Commenting:' . $methodName .' is deprecated.'
                          . ' Please use the config API instead';
                $this->assertEquals($expected, $e->getMessage());
            }
        }
    }

    public function testSetConfigValue()
    {
        //    public static function set_config_value($class, $key, $value = false) {
        Commenting::set_config_value(
            CommentableItem::class,
            'comments_holder_id',
            'commentable_item'
        );

        $config = Config::inst()->get(
            CommentableItem::class,
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
            CommentsExtension::class,
            'comments'
        );
        $actual = $config['comments_holder_id'];
        $this->assertEquals(
            'all_items_actually_commentsextension',
            $actual
        );
    }

    public function testGetConfigValue()
    {
        Config::inst()->update(
            CommentableItem::class,
            'comments',
            array(
            'comments_holder_id' => 'commentable_item'
            )
        );
        $this->assertEquals(
            'commentable_item',
            Commenting::get_config_value(CommentableItem::class, 'comments_holder_id')
        );

        Config::inst()->update(
            CommentsExtension::class,
            'comments',
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
        Commenting::get_config_value(Member::class, 'comments_holder_id');
    }

    public function testConfigValueEquals()
    {
        Config::inst()->update(
            CommentableItem::class,
            'comments',
            array(
            'comments_holder_id' => 'some_value'
            )
        );

        $this->assertTrue(
            Commenting::config_value_equals(
                CommentableItem::class,
                'comments_holder_id',
                'some_value'
            )
        );

        $this->assertNull(
            Commenting::config_value_equals(
                CommentableItem::class,
                'comments_holder_id',
                'not_some_value'
            )
        );
    }

    public function testAdd()
    {
        Commenting::add(Member::class, array('comments_holder_id' => 'test_add_value'));

        $config = Config::inst()->get(
            Member::class,
            'comments'
        );
        $actual = $config['comments_holder_id'];
        $this->assertEquals(
            'test_add_value',
            $actual
        );

        Commenting::add(Member::class);

        $config = Config::inst()->get(
            Member::class,
            'comments'
        );
        $actual = $config['comments_holder_id'];
        // no settings updated
        $this->assertEquals(
            'test_add_value',
            $actual
        );

        $this->setExpectedException('InvalidArgumentException', "\$settings needs to be an array or null");
        Commenting::add(Member::class, 'illegal format, not an array');
    }

    public function testCanMemberPost()
    {
        // logout
        if ($member = Member::currentUser()) {
            $member->logOut();
        }

        Config::inst()->update(
            CommentableItem::class,
            'comments',
            array(
                'require_login' => false
            )
        );
        $this->assertTrue(Commenting::can_member_post(CommentableItem::class));

        Config::inst()->update(
            CommentableItem::class,
            'comments',
            array(
                'require_login' => true
            )
        );
        $this->assertFalse(Commenting::can_member_post(CommentableItem::class));

        $this->logInWithPermission('CMS_ACCESS_CommentAdmin');
        $this->assertTrue(Commenting::can_member_post(CommentableItem::class));

        Config::inst()->update(
            CommentableItem::class,
            'comments',
            array(
                'require_login' => false
            )
        );

        $this->assertTrue(Commenting::can_member_post(CommentableItem::class));
    }
}
