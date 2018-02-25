<?php

namespace SilverStripe\Comments\Admin\CommentsGridFieldBulkAction;

use Colymba\BulkManager\BulkAction\Handler;
use SilverStripe\Core\Convert;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * A {@link Handler} for bulk approving comments
 */
class ApproveHandler extends Handler
{
    private static $allowed_actions = ['index'];

    private static $url_segment = 'approve';

    protected $xhr = true;

    protected $buttonClasses = 'font-icon-tick';

    protected $destructive = false;

    protected $label = 'Approve';

    /**
     * @param  HTTPRequest $request
     * @return HTTPResponse
     */
    public function index(HTTPRequest $request)
    {
        $ids = [];

        foreach ($this->getRecords() as $record) {
            array_push($ids, $record->ID);
            $record->markApproved();
        }

        $response = new HTTPResponse(Convert::raw2json([
            'done' => true,
            'records' => $ids,
        ]));

        $response->addHeader('Content-Type', 'text/json');

        return $response;
    }
}
