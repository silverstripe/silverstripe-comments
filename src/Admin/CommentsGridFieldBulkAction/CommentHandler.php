<?php

namespace SilverStripe\Comments\Admin\CommentsGridFieldBulkAction;

use Colymba\BulkManager\BulkAction\Handler;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Core\Convert;
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

        foreach ($this->getRecords() as $comment) {
            array_push($ids, $comment->ID);
            $this->updateComment($comment);
        }

        $response = new HTTPResponse(json_encode([
            'done' => true,
            'records' => $ids,
        ]));

        $response->addHeader('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @param Comment $comment
     *
     * @return Comment
     */
    abstract public function updateComment($comment);
}
