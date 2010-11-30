<?php

/**
 * Comment form used to make comments 
 *
 * @package comments
 */

class CommentForm extends Form {

	/**
	 * Returns a create comment form
	 *
	 * @return Form
	 */
	function __construct($controller, $name) {

		$member = Member::currentUser();

		if((self::$comments_require_login || self::$comments_require_permission) && $member && $member->FirstName) {
			// note this was a ReadonlyField - which displayed the name in a span as well as the hidden field but
			// it was not saving correctly. Have changed it to a hidden field. It passes the data correctly but I 
			// believe the id of the form field is wrong.
			$fields->push(new ReadonlyField("NameView", _t('CommentInterface.YOURNAME', 'Your name'), $member->getName()));
			$fields->push(new HiddenField("Name", "", $member->getName()));
		} else {
			$fields->push(new TextField("Name", _t('CommentInterface.YOURNAME', 'Your name')));
		}

		$fields->push(new TextField("URL", _t('CommentForm.COMMENTERURL', "Your website URL")));
		$fields->push(new EmailField("Email", _t('CommentForm', "Your email address (will not be published)")));
		$fields->push(new TextareaField("Comment", _t('CommentInterface.YOURCOMMENT', "Comments")));
	
		$actions = new FieldSet(
			new FormAction("doPostComment", _t('CommentInterface.POST', 'Post'))
		);

		// Set it so the user gets redirected back down to the form upon form fail
		$this->setRedirectToFormOnValidationError(true);
		
		$required = new RequiredFields();

		parent::__construct($controller, $name, $fields, $actions, $required);
	}
	
	/**
	 * Process which creates a {@link Comment} once a user submits a comment from this form.
	 *
	 * @param array $data 
	 * @param Form $form
	 */
	function doPostComment($data, $form) {
		
		// cache users data
		Cookie::set("CommentInterface_Name", $data['Name']);
		Cookie::set("CommentInterface_CommenterURL", $data['CommenterURL']);
		Cookie::set("CommentInterface_Comment", $data['Comment']);

		// @todo turn this into an extension 
		if(SSAkismet::isEnabled()) {
			try {
				$akismet = new SSAkismet();
				
				$akismet->setCommentAuthor($data['Name']);
				$akismet->setCommentContent($data['Comment']);
				
				if($akismet->isCommentSpam()) {
					if(SSAkismet::getSaveSpam()) {
						$comment = Object::create('Comment');
						$this->saveInto($comment);
						$comment->setField("IsSpam", true);
						$comment->write();
					}
					echo "<b>"._t('CommentInterface_Form.SPAMDETECTED', 'Spam detected!!') . "</b><br /><br />";
					printf("If you believe this was in error, please email %s.", ereg_replace("@", " _(at)_", Email::getAdminEmail()));
					echo "<br /><br />"._t('CommentInterface_Form.MSGYOUPOSTED', 'The message you posted was:'). "<br /><br />";
					echo $data['Comment'];
					
					return;
				}
			} catch (Exception $e) {
				// Akismet didn't work, continue without spam check
			}
		}
		
		// If commenting can only be done by logged in users, make sure the user is logged in
		$member = Member::currentUser();
		if(CommentInterface::CanPostComment() && $member) {
			$this->Fields()->push(new HiddenField("AuthorID", "Author ID", $member->ID));
		} elseif(!CommentInterface::CanPostComment()) {
			echo "You're not able to post comments to this page. Please ensure you are logged in and have an appropriate permission level.";
			return;
		}

		$comment = Object::create('Comment');
		$this->saveInto($comment);
		
		// @todo this should be in the onBeforeWrite of the comment. Via an extension 
		if($session = Session::get('mollom_user_session_id')) {
			$comment->SessionID = $session;
			Session::clear('mollom_user_session_id');	
		}
		$comment->IsSpam = false;
		$comment->NeedsModeration = Comment::moderationEnabled();
		$comment->write();
		
		Cookie::set("CommentInterface_Comment", '');
		
		$moderationMsg = _t('CommentInterface_Form.AWAITINGMODERATION', "Your comment has been submitted and is now awaiting moderation.");
		
		if(Director::is_ajax()) {
			if($comment->NeedsModeration){
				echo $moderationMsg;
			} else{
				echo $comment->renderWith('CommentInterface_singlecomment');
			}
		} else {		
			if($comment->NeedsModeration){
				$this->sessionMessage($moderationMsg, 'good');
			}
			
			if($comment->ParentID) {
				$page = DataObject::get_by_id("Page", $comment->ParentID);
				if($page) {
					// if it needs moderation then it won't appear in the list. Therefore
					// we need to link to the comment holder rather than the individual comment
					$url = ($comment->NeedsModeration) ? $page->Link() . '#Comments_holder' : $page->Link() . '#Comment_' . $comment->ID;
					
					return $this->controller->redirect($url);
				}
			}
			
			return $this->controller->redirectBack();
		}
	}
}
