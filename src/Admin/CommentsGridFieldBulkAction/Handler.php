<?php

namespace SilverStripe\Comments\Admin\CommentsGridFieldBulkAction;

use SilverStripe\Dev\Deprecation;
use Colymba\BulkManager\BulkAction\Handler as GridFieldBulkActionHandler;
use SilverStripe\Core\Convert;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * A {@link GridFieldBulkActionHandler} for bulk marking comments as spam
 *
 * @deprecated 3.1.0 Use concrete Spam or Approve handlers instead
 */
class Handler extends GridFieldBulkActionHandler
{
    private static $allowed_actions = array(
        'spam',
        'approve',
    );

    private static $url_handlers = array(
        'spam' => 'spam',
        'approve' => 'approve',
    );

    /**
     * @param  HTTPRequest $request
     * @return HTTPResponse
     */
    public function __construct()
    {
        Deprecation::notice('3.1.0', 'Use concrete Spam or Approve handlers instead', Deprecation::SCOPE_CLASS);
    }

    public function spam(HTTPRequest $request)
    {
        $ids = array();

        foreach ($this->getRecords() as $record) {
            array_push($ids, $record->ID);
            $record->markSpam();
        }

        $response = new HTTPResponse(json_encode(array(
            'done' => true,
            'records' => $ids
        )));

        $response->addHeader('Content-Type', 'text/json');

        return $response;
    }

    /**
     * @param  HTTPRequest $request
     * @return HTTPResponse
     */
    public function approve(HTTPRequest $request)
    {
        $ids = array();

        foreach ($this->getRecords() as $record) {
            array_push($ids, $record->ID);
            $record->markApproved();
        }

        $response = new HTTPResponse(json_encode(array(
            'done' => true,
            'records' => $ids
        )));

        $response->addHeader('Content-Type', 'text/json');

        return $response;
    }
}
