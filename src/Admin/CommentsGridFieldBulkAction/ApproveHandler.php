<?php

namespace SilverStripe\Comments\Admin\CommentsGridFieldBulkAction;

use SilverStripe\Comments\Model\Comment;

/**
 * A {@link Handler} for bulk approving comments
 */
class ApproveHandler extends CommentHandler
{
    private static $url_segment = 'approve';

    protected $buttonClasses = 'font-icon-tick';

    protected $label = 'Approve';

    /**
     * @param Comment $comment
     *
     * @return Comment
     */
    public function updateComment($comment)
    {
        $comment->markApproved();
        return $comment;
    }
}
