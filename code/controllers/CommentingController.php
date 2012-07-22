<?php

/**
 * @package comments
 */

class CommentingController extends Controller {
	
	public static $allowed_actions = array(
		'delete',
		'spam',
		'ham',
		'approve',
		'rss',
		'CommentsForm',
		'doPostComment'
	);
	
	private $baseClass = "";
	private $ownerRecord = "";
	private $ownerController = "";
	
	public function setBaseClass($class) {
		$this->baseClass = $class;
	}
	
	public function getBaseClass() {
		return $this->baseClass;
	}
	
	public function setOwnerRecord($record) {
		$this->ownerRecord = $record;
	}
	
	public function getOwnerRecord() {
		return $this->ownerRecord;
	}
	
	public function setOwnerController($controller) {
		$this->ownerController = $controller;
	}
	
	public function getOwnerController() {
		return $this->ownerController;
	}
	
	/**
	 * Workaround for generating the link to this controller
	 *
	 * @return string
	 */
	public function Link($action = "", $id = '', $other = '') {
		return Controller::join_links(__CLASS__ , $action, $id, $other);
	}
	
	/**
	 * Return an RSS feed of comments for a given set of comments or all 
	 * comments on the website.
	 *
	 * To maintain backwards compatibility with 2.4 this supports mapping
	 * of PageComment/rss?pageid= as well as the new RSS format for comments
	 * of CommentingController/rss/{classname}/{id}
	 *
	 * @return RSS
	 */
	public function rss() {
		$link = $this->Link('rss');
		$class = $this->urlParams['ID'];
		$id = $this->urlParams['OtherID'];

		if(isset($_GET['pageid'])) {
			$id =  Convert::raw2sql($_GET['pageid']);

			$comments = Comment::get()->where(sprintf(
				"BaseClass = 'SiteTree' AND ParentID = '%s'", $id
			));

			$link = $this->Link('rss', 'SiteTree', $id);

		} else if($class && $id) {
			if(Commenting::has_commenting($class)) {
				$comments = Comment::get()->where(sprintf(
					"BaseClass = '%s' AND ParentID = '%s'", 
					Convert::raw2sql($class),
					Convert::raw2sql($id)
				));

				$link = $this->Link('rss', Convert::raw2xml($class), (int) $id);
			} else {
				return $this->httpError(404);
			}
		} else if($class) {
			if(Commenting::has_commenting($class)) {
				$comments = Comment::get()->where(sprintf(
					"BaseClass = '%s'", Convert::raw2sql($class)
				));
			} else {
				return $this->httpError(404);
			}
		} else {
			$comments = Comment::get();
		}

		$title = _t('CommentingController.RSSTITLE', "Comments RSS Feed");

		$feed = new RSSFeed($comments, $link, $title, $link, 'Title', 'Comment', 'AuthorName');
		$feed->outputToBrowser();
	}

	/**
	 * Deletes a given {@link Comment} via the URL.
	 *
	 * @param SS_HTTPRequest
	 */
	public function delete($request) {
		if(!$this->checkSecurityToken($request)) {
			return $this->httpError(400);
		}

		if(($comment = $this->getComment()) && $comment->canDelete()) {
			$comment->delete();
				
			return ($this->request->isAjax()) ? true : $this->redirectBack();
		}

		return $this->httpError(404);
	}

	/**
	 * Marks a given {@link Comment} as spam. Removes the comment from display
	 *
	 * @param SS_HTTPRequest
	 */
	public function spam() {
		if(!$this->checkSecurityToken($request)) {
			return $this->httpError(400);
		}

		$comment = $this->getComment();

		if(($comment = $this->getComment()) && $comment->canEdit()) {
			$comment->IsSpam = true;
			$comment->Moderated = true;
			$comment->write();
				
			return ($this->request->isAjax()) ? true : $this->redirectBack();
		}

		return $this->httpError(404);
	}

	/**
	 * Marks a given {@link Comment} as ham (not spam).
	 *
	 * @param SS_HTTPRequest
	 */
	public function ham($request) {
		if(!$this->checkSecurityToken($request)) {
			return $this->httpError(400);
		}

		$comment = $this->getComment();

		if(($comment = $this->getComment()) && $comment->canEdit()) {
			$comment->IsSpam = false;
			$comment->Moderated = true;
			$comment->write();
				
			return ($this->request->isAjax()) ? true : $this->redirectBack();
		}

		return $this->httpError(404);
	}

	/**
	 * Marks a given {@link Comment} as approved.
	 *
	 * @param SS_HTTPRequest
	 */
	public function approve($request) {
		if(!$this->checkSecurityToken($request)) {
			return $this->httpError(400);
		}

		$comment = $this->getComment();

		if(($comment = $this->getComment()) && $comment->canEdit()) {
			$comment->IsSpam = false;
			$comment->Moderated = true;
			$comment->write();
				
			return ($this->request->isAjax()) ? true : $this->redirectBack();
		}

		return $this->httpError(404);
	}
	
	/**
	 * Returns the comment referenced in the URL (by ID).
	 *
	 * @return Comment|false
	 */
	public function getComment() {
		$id = isset($this->urlParams['ID']) ? $this->urlParams['ID'] : false;

		if($id) {
			$comment = DataObject::get_by_id('Comment', $id);

			if($comment) {
				return $comment;
			}
		}

		return false;
	}

	/**
	 * Checks the security token given with the URL to prevent CSRF attacks 
	 * against administrators allowing users to hijack comment moderation.
	 *
	 * @param SS_HTTPRequest
	 *
	 * @return boolean
	 */
	public function checkSecurityToken($req) {
		$token = SecurityToken::inst();

		return $token->checkRequest($req);
	}

	/**
	 * Post a comment form
	 *
	 * @return Form
	 */
	public function CommentsForm() {
		
		$member = Member::currentUser();
		$fields = new FieldList(
			new TextField("Name", _t('CommentInterface.YOURNAME', 'Your name')),
			new EmailField("Email", _t('CommentingController.EMAILADDRESS', "Your email address (will not be published)")),
			new TextField("URL", _t('CommentingController.WEBSITEURL', "Your website URL")),
			new TextareaField("Comment", _t('CommentingController.COMMENTS', "Comments")),
			new HiddenField("ParentID"),
			new HiddenField("ReturnURL"),
			new HiddenField("BaseClass")
		);

		// save actions
		$actions = new FieldList(
			new FormAction("doPostComment", _t('CommentInterface.POST', 'Post'))
		);

		// required fields for server side
		$required = new RequiredFields(array(
			'Name',
			'Email',
			'Comment'
		));

		// create the comment form
		$form = new Form($this, 'CommentsForm', $fields, $actions, $required);

		// if the record exists load the extra required data
		if($record = $this->getOwnerRecord()) {
			$require_login	= Commenting::get_config_value($this->getBaseClass(), 'require_login');
			$permission		= Commenting::get_config_value($this->getBaseClass(), 'required_permission');
			
			if(($require_login || $permission) && $member) {
				$fields = $form->Fields();
				
				$fields->removeByName('Name');
				$fields->removeByName('Email');
				$fields->insertBefore(new ReadonlyField("NameView", _t('CommentInterface.YOURNAME', 'Your name'), $member->getName()), 'URL');
				$fields->push(new HiddenField("Name", "", $member->getName()));
				
				$form->setFields($fields);
			}
			
			// we do not want to read a new URL when the form has already been submitted
			// which in here, it hasn't been.
			$url = (isset($_SERVER['REQUEST_URI'])) ? Director::protocolAndHost() . '' . $_SERVER['REQUEST_URI'] : false;
			
			$form->loadDataFrom(array(
				'ParentID'		=> $record->ID,
				'ReturnURL'		=> $url,
				'BaseClass'		=> $this->getBaseClass()
			));
		}

				
		// Set it so the user gets redirected back down to the form upon form fail
		$form->setRedirectToFormOnValidationError(true);

		// load any data from the cookies
		if($data = Cookie::get('CommentsForm_UserData')) {
			$data = Convert::json2array($data); 
			
			$form->loadDataFrom(array(
				"Name"		=> isset($data['Name']) ? $data['Name'] : '',
				"URL"		=> isset($data['URL']) ? $data['URL'] : '',
				"Email"		=> isset($data['Email']) ? $data['Email'] : '',
				"Comment"	=> Cookie::get('CommentsForm_Comment')
			));			
		}

		if($member) {
			$form->loadDataFrom($member);
		}
		
		// hook to allow further extensions to alter the comments form
		$this->extend('alterCommentForm', $form);

		return $form;
	}
	
	/**
	 * Process which creates a {@link Comment} once a user submits a comment from this form.
	 *
	 * @param array $data 
	 * @param Form $form
	 */
	public function doPostComment($data, $form) {
		$class = (isset($data['BaseClass'])) ? $data['BaseClass'] : $this->getBaseClass();
		
		// if no class then we cannot work out what controller or model they
		// are on so throw an error
		if(!$class) user_error("No OwnerClass set on CommentingController.", E_USER_ERROR);
		
		// cache users data
		Cookie::set("CommentsForm_UserData", Convert::raw2json($data));
		Cookie::set("CommentsForm_Comment", $data['Comment']);
		
		// extend hook to allow extensions. Also see onAfterPostComment
		$this->extend('onBeforePostComment', $form);	
		
		// If commenting can only be done by logged in users, make sure the user is logged in
		$member = Member::currentUser();
		
		if(Commenting::can_member_post($class) && $member) {
			$form->Fields()->push(new HiddenField("AuthorID", "Author ID", $member->ID));
		} 
		
		if(!Commenting::can_member_post($class)) {
			echo _t('CommentingController.PERMISSIONFAILURE', "You're not able to post comments to this page. Please ensure you are logged in and have an appropriate permission level.");
			
			return;
		}

		// is moderation turned on
		$moderated = Commenting::get_config_value($class, 'require_moderation');
		
		$comment = new Comment();
		$form->saveInto($comment);

		$comment->Moderated = ($moderated) ? false : true;
		$comment->write();
		
		$moderationMsg = _t('CommentInterface_Form.AWAITINGMODERATION', "Your comment has been submitted and is now awaiting moderation.");
		
		// clear the users comment since it passed validation
		Cookie::set('CommentsForm_Comment', false);
		
		if(Director::is_ajax()) {
			if($comment->NeedsModeration){
				echo $moderationMsg;
			} else{
				echo $comment->renderWith('CommentInterface_singlecomment');
			}
			
			return false;
		}
		
		if($comment->NeedsModeration){
			$this->sessionMessage($moderationMsg, 'good');
		}
		
		// build up the return link. Ideally redirect to 
		$holder = Commenting::get_config_value($comment->BaseClass, 'comments_holder_id');

		$hash = ($comment->NeedsModeration) ? $holder : $comment->Permalink();
		$url = (isset($data['ReturnURL'])) ? $data['ReturnURL'] : false;
			
		return ($url) ? $this->redirect($url .'#'. $hash) : $this->redirectBack();
	}
}