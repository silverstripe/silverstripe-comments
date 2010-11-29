<?php

/**
 * @package comments
 */

class CommentExtension extends DataObjectDecorator {
	
	function Comments() {
		return DataObject::get('Comment', "\"RecordClassID\" = '". $this->owner->ID ."' AND \"RecordClass\" = '". $this->ownerBaseClass ."'");
	}
	
	function CommentsForm() {
		die();
	}
}