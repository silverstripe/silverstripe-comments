<?php

/**
 * @package comments
 */
class ContentControllerCommentsExtension extends Extension {
	
	public function Comments() {
		return $this->owner->dataRecord->getComments();
	}
}