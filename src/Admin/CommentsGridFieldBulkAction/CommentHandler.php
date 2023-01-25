<?php

namespace SilverStripe\Comments\Admin\CommentsGridFieldBulkAction;

use Colymba\BulkManager\BulkAction\Handler;
use Colymba\BulkTools\HTTPBulkToolsResponse;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

abstract class CommentHandler extends Handler
{
    protected $xhr = true;

    protected $destructive = false;

    /**
     * @param  HTTPRequest $request
     * @return HTTPResponse
     */
    public function index(HTTPRequest $request)
    {
        $ids = [];

        $response = new HTTPBulkToolsResponse(
            true,
            $this->gridField,
            200
        );

        foreach ($this->getRecords() as $comment) {
            array_push($ids, $comment->ID);
            $this->updateComment($comment);
            $response->addSuccessRecord($comment);
        }

        $response->setMessage(_t(__CLASS__ . '.CHANGES_APPLIED', 'Changes applied'));

        return $response;
    }

    /**
     * @param Comment $comment
     *
     * @return Comment
     */
    abstract public function updateComment($comment);
}
