<?php

namespace SilverStripe\Comments\Admin\CommentsGridFieldBulkAction;

use SilverStripe\Comments\Model\Comment;

/**
 * A {@link Handler} for bulk marking comments as spam
 */
class SpamHandler extends CommentHandler
{
    private static $url_segment = 'spam';

    protected $buttonClasses = 'font-icon-cross-mark';

    protected $label = 'Spam';

    /**
     * @param Comment $comment
     *
     * @return Comment
     */
    public function updateComment($comment)
    {
        $comment->markSpam();
        return $comment;
    }
}
