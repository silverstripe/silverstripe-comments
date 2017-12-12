<?php

namespace SilverStripe\Comments\Tests\Stubs;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;

/**
 * @package comments
 * @subpackage tests
 */
class CommentableItemController extends Controller implements TestOnly
{
    public function index()
    {
        return CommentableItem::get()->first()->CommentsForm();
    }
}
