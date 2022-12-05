<?php

namespace SilverStripe\Comments\Tests\Stubs;

use SilverStripe\Comments\Extensions\CommentsExtension;
use SilverStripe\Control\Director;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

class CommentableItem extends DataObject implements TestOnly
{
    private static $db = array(
        'Title' => 'Varchar'
    );

    private static $extensions = array(
        CommentsExtension::class
    );

    private static $table_name = 'CommentableItem';

    public function RelativeLink()
    {
        return 'CommentableItemController';
    }

    public function canView($member = null)
    {
        return true;
    }

    // This is needed for canModerateComments
    public function canEdit($member = null)
    {
        if ($member instanceof Member) {
            $memberID = $member->ID;
        } elseif (is_numeric($member)) {
            $memberID = $member;
        } else {
            $currentUser = Security::getCurrentUser();
            $memberID = $currentUser ? $currentUser->ID : 0;
        }

        if ($memberID && Permission::checkMember($memberID, array('ADMIN', 'CMS_ACCESS_CommentAdmin'))) {
            return true;
        }
        return false;
    }

    public function Link()
    {
        return $this->RelativeLink();
    }

    public function AbsoluteLink()
    {
        return Director::absoluteURL($this->RelativeLink());
    }
}
