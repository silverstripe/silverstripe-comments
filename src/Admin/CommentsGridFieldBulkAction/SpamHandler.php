<?php

namespace SilverStripe\Comments\Admin\CommentsGridFieldBulkAction;

use Colymba\BulkManager\BulkAction\Handler;
use SilverStripe\Core\Convert;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * A {@link Handler} for bulk marking comments as spam
 */
class SpamHandler extends Handler
{
    private static $allowed_actions = ['index'];

    private static $url_segment = 'spam';

    protected $xhr = true;

    protected $buttonClasses = 'font-icon-cross-mark';

    protected $destructive = false;

    protected $label = 'Spam';

    /**
     * @param  HTTPRequest $request
     * @return HTTPResponse
     */
    public function index(HTTPRequest $request)
    {
        $ids = [];

        foreach ($this->getRecords() as $record) {
            array_push($ids, $record->ID);
            $record->markSpam();
        }

        $response = new HTTPResponse(Convert::raw2json([
            'done' => true,
            'records' => $ids,
        ]));

        $response->addHeader('Content-Type', 'text/json');

        return $response;
    }
}
