<?php 

class CommentingControllerTwitterUsernameExtension extends DataExtension {

	public function alterCommentForm(&$form) {
		$fields = $form->Fields();

		// FIXME order
        $fields->insertBefore($tf = new TextField('TwitterUsername','Twitter Username'), 'Comment');
    }
}