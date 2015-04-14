<?php

/**
 * @package comments
 */
class CommentsGridFieldBulkAction extends GridFieldBulkActionHandler {
	
}

/**
 * A {@link GridFieldBulkActionHandler} for bulk marking comments as spam
 *
 * @package comments
 */
class CommentsGridFieldBulkAction_Handlers extends CommentsGridFieldBulkAction {
	
	private static $allowed_actions = array(
		'markAsSpam',
		'markAsNotSpam',
	);

	private static $url_handlers = array(
		'markAsSpam' => 'markAsSpam',
		'markAsNotSpam' => 'markAsNotSpam',
	);


	public function markAsSpam(SS_HTTPRequest $request) {
		$ids = array();
		
		foreach($this->getRecords() as $record) {						
			array_push($ids, $record->ID);

			$record->Moderated = 1;
			$record->IsSpam = 1;
			$record->write();
		}

		$response = new SS_HTTPResponse(Convert::raw2json(array(
			'done' => true,
			'records' => $ids
		)));

		$response->addHeader('Content-Type', 'text/json');

		return $response;	
	}


	public function markAsNotSpam(SS_HTTPRequest $request) {
		$ids = array();

		foreach($this->getRecords() as $record) {
			array_push($ids, $record->ID);

			$record->Moderated = 1;
			$record->IsSpam = 0;
			$record->write();
		}

		$response = new SS_HTTPResponse(Convert::raw2json(array(
			'done' => true,
			'records' => $ids
		)));

		$response->addHeader('Content-Type', 'text/json');

		return $response;
	}
}