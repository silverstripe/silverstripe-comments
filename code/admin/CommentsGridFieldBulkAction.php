<?php

/**
 * @package comments
 */
class CommentsGridFieldBulkAction extends GridFieldBulkActionHandler {

}

/**
 * A {@link GridFieldBulkActionHandler} for bulk marking comments as spam.
 *
 * @package comments
 */
class CommentsGridFieldBulkAction_Handlers extends CommentsGridFieldBulkAction {
	/**
	 * @var array
	 */
	private static $allowed_actions = array(
		'spam',
		'approve',
	);

	/**
	 * @var array
	 */
	private static $url_handlers = array(
		'spam' => 'spam',
		'approve' => 'approve',
	);


	/**
	 * @param SS_HTTPRequest $request
	 *
	 * @return SS_HTTPResponse
	 */
	public function spam(SS_HTTPRequest $request) {
		$ids = array();

		foreach($this->getRecords() as $record) {
			/**
			 * @var Comment $record
			 */
			$record->markSpam();

			array_push($ids, $record->ID);
		}

		$response = new SS_HTTPResponse(
			Convert::raw2json(array(
				'done' => true,
				'records' => $ids,
			))
		);

		$response->addHeader('Content-Type', 'text/json');

		return $response;
	}

	/**
	 * @param SS_HTTPRequest $request
	 *
	 * @return SS_HTTPResponse
	 */
	public function approve(SS_HTTPRequest $request) {
		$ids = array();

		foreach($this->getRecords() as $record) {
			/**
			 * @var Comment $record
			 */
			$record->markApproved();

			array_push($ids, $record->ID);
		}

		$response = new SS_HTTPResponse(
			Convert::raw2json(array(
				'done' => true,
				'records' => $ids,
			))
		);

		$response->addHeader('Content-Type', 'text/json');

		return $response;
	}
}
