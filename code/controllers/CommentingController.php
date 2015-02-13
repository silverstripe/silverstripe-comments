<?php

/**
 * @package comments
 */

class CommentingController extends Controller {
	
	private static $allowed_actions = array(
		'delete',
		'spam',
		'ham',
		'approve',
		'viewcomment',
		'rss',
		'CommentsForm',
		'doPostComment',
		'doPreviewComment'
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
	 * Outputs the RSS feed of comments
	 *
	 * @return XML
	 */
	public function rss() {
		return $this->getFeed($this->request)->outputToBrowser();
	}

	/**
	 * Return an RSSFeed of comments for a given set of comments or all 
	 * comments on the website.
	 *
	 * To maintain backwards compatibility with 2.4 this supports mapping
	 * of PageComment/rss?pageid= as well as the new RSS format for comments
	 * of CommentingController/rss/{classname}/{id}
	 *
	 * @param SS_HTTPRequest
	 *
	 * @return RSSFeed
	 */
	public function getFeed(SS_HTTPRequest $request) {
		$link = $this->Link('rss');
		$class = $request->param('ID');
		$id = $request->param('OtherID');

		$comments = Comment::get()->filter(array(
			'Moderated' => 1,
			'IsSpam' => 0,
		));

		if($request->getVar('pageid')) {
			$comments = $comments->filter(array(
				'BaseClass' => 'SiteTree',
				'ParentID' => $request->getVar('pageid'),
			));

			$link = $this->Link('rss', 'SiteTree', $id);

		} elseif($class && $id) {
			if(Commenting::has_commenting($class)) {
				$comments = $comments->filter(array(
					'BaseClass' => $class,
					'ParentID' => $id,
				));

				$link = $this->Link('rss', Convert::raw2xml($class), (int) $id);
			} else {
				return $this->httpError(404);
			}
		} elseif($class) {
			if(Commenting::has_commenting($class)) {
				$comments = $comments->filter('BaseClass', $class);
			} else {
				return $this->httpError(404);
			}
		}

		$title = _t('CommentingController.RSSTITLE', "Comments RSS Feed");

		$comments = new PaginatedList($comments, $request);
		$comments->setPageLength(Commenting::get_config_value(null, 'comments_per_page'));

		return new RSSFeed(
			$comments, 
			$link, 
			$title, 
			$link, 
			'Title', 'EscapedComment', 'AuthorName'
		);
	}

	/**
	 * Deletes a given {@link Comment} via the URL.
	 */
	public function delete() {
		if(!$this->checkSecurityToken($this->request)) {
			return $this->httpError(400);
		}

		if(($comment = $this->getComment()) && $comment->canDelete()) {
			$childcount = Comment::get()->filter('ParentCommentID', $comment->ID)->count();
			if ($childcount == 0) {
				$comment->delete();
			} else {
				// mark the comment as deleted, but do not actually delete it as there are child comments
				$comment->MarkedAsDeleted = true;
				$comment->write();
			}
			
				
			return ($this->request->isAjax()) ? true : $this->redirectBack();
		}

		return $this->httpError(404);
	}

	/**
	 * Marks a given {@link Comment} as spam. Removes the comment from display
	 */
	public function spam() {
		if(!$this->checkSecurityToken($this->request)) {
			return $this->httpError(400);
		}

		$comment = $this->getComment();

		if(($comment = $this->getComment()) && $comment->canEdit()) {
			$comment->IsSpam = true;
			$comment->Moderated = true;
			$comment->write();
				
			return ($this->request->isAjax()) ? $comment->renderWith('CommentsInterface_singlecomment') : $this->redirectBack();
		}

		return $this->httpError(404);
	}

	/**
	 * Marks a given {@link Comment} as ham (not spam).
	 */
	public function ham() {
		if(!$this->checkSecurityToken($this->request)) {
			return $this->httpError(400);
		}

		$comment = $this->getComment();

		if(($comment = $this->getComment()) && $comment->canEdit()) {
			$comment->IsSpam = false;
			$comment->Moderated = true;
			$comment->write();
				
			return ($this->request->isAjax()) ? $comment->renderWith('CommentsInterface_singlecomment') : $this->redirectBack();
		}

		return $this->httpError(404);
	}

	/**
	 * Marks a given {@link Comment} as approved.
	 */
	public function approve() {
		if(!$this->checkSecurityToken($this->request)) {
			return $this->httpError(400);
		}

		$comment = $this->getComment();

		if(($comment = $this->getComment()) && $comment->canEdit()) {
			$comment->IsSpam = false;
			$comment->Moderated = true;
			$comment->write();
				
			return ($this->request->isAjax()) ? $comment->renderWith('CommentsInterface_singlecomment') : $this->redirectBack();
		}

		return $this->httpError(404);
	}


	/**
	 * Show a given {@link Comment} for AJAX reply.  No security check as it's viewing of public data
	 */
	public function viewcomment() {
		$comment = $this->getComment();
		if(($comment = $this->getComment()) && $comment->canView() && $comment->Moderated) {				
			return ($this->request->isAjax()) ? $comment->renderWith('CommentsInterface_singlecomment') : $this->redirectBack();
		} else {
			return $this->httpError(403);
		}

		return $this->httpError(404);
	}
	
	/**
	 * Returns the comment referenced in the URL (by ID). Permission checking
	 * should be done in the callee.
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
		$usePreview = Commenting::get_config_value($this->getBaseClass(), 'use_preview');
		$member = Member::currentUser();

		$fields = new FieldList(
			$dataFields = new CompositeField(
			TextField::create("Name", _t('CommentInterface.YOURNAME', 'Your name'))
				->setCustomValidationMessage(_t('CommentInterface.YOURNAME_MESSAGE_REQUIRED', 'Please enter your name'))
				->setAttribute('data-message-required', _t('CommentInterface.YOURNAME_MESSAGE_REQUIRED', 'Please enter your name')),

			EmailField::create("Email", _t('CommentingController.EMAILADDRESS', "Your email address (will not be published)"))
				->setCustomValidationMessage(_t('CommentInterface.EMAILADDRESS_MESSAGE_REQUIRED', 'Please enter your email address'))
				->setAttribute('data-message-required', _t('CommentInterface.EMAILADDRESS_MESSAGE_REQUIRED', 'Please enter your email address'))
				->setAttribute('data-message-email', _t('CommentInterface.EMAILADDRESS_MESSAGE_EMAIL', 'Please enter a valid email address')),

			TextField::create("URL", _t('CommentingController.WEBSITEURL', "Your website URL"))
				->setAttribute('data-message-url', _t('CommentInterface.COMMENT_MESSAGE_URL', 'Please enter a valid URL')),

			TextareaField::create("Comment", _t('CommentingController.COMMENTS', "Comments"))
				->setCustomValidationMessage(_t('CommentInterface.COMMENT_MESSAGE_REQUIRED', 'Please enter your comment'))
					->setAttribute('data-message-required', _t('CommentInterface.COMMENT_MESSAGE_REQUIRED', 'Please enter your comment'))
			),
			HiddenField::create("ParentID"),
			HiddenField::create("ReturnURL"),
			HiddenField::create("BaseClass"),
			HiddenField::create("ParentCommentID")
		);

		// Preview formatted comment. Makes most sense when shortcodes or
		// limited HTML is allowed. Populated by JS/Ajax.
		if($usePreview) {
			$fields->insertAfter(
				ReadonlyField::create('PreviewComment', _t('CommentInterface.PREVIEWLABEL', 'Preview'))
					->setAttribute('style', 'display: none'), // enable through JS
				'Comment'
			);
		}
	
		$dataFields->addExtraClass('data-fields');

		// save actions
		$actions = new FieldList(
			new FormAction("doPostComment", _t('CommentInterface.POST', 'Post'))
		);
		if($usePreview) {
			$actions->push(
				FormAction::create('doPreviewComment', _t('CommentInterface.PREVIEW', 'Preview'))
					->addExtraClass('action-minor')
					->setAttribute('style', 'display: none') // enable through JS
			);
		}

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
				$fields->push(new HiddenField("Email", "", $member->Email));
				
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
				"Email"		=> isset($data['Email']) ? $data['Email'] : ''
			));
			// allow previous value to fill if comment not stored in cookie (i.e. validation error)
			$prevComment = Cookie::get('CommentsForm_Comment');
			if($prevComment && $prevComment != ''){
			  $form->loadDataFrom(array("Comment" => $prevComment));
			}
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
		$usePreview = Commenting::get_config_value($class, 'use_preview');
		$isPreview = ($usePreview && isset($data['IsPreview']) && $data['IsPreview']);
		
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
		if(!$moderated){
		  $moderated_nonmembers = Commenting::get_config_value($class, 'require_moderation_nonmembers');
		  $moderated = $moderated_nonmembers ? !Member::currentUser() : false;
		}
		
		// we want to show a notification if comments are moderated
		if ($moderated) {
			Session::set('CommentsModerated', 1);
		}

		// ensure that the parent comment can be replied to, if not throw a 403 forbidden error
		$threaded = Commenting::get_config_value($class, 'thread_comments');
        if ($threaded) {
    		$parentidfield = $form->Fields()->fieldByName('ParentCommentID');
    		if ($parentidfield) {
    			$parentid = $parentidfield->Value();
                if ($parentid) {
                        $parentComment = DataObject::get_by_id('Comment', $parentid);
                        if (!$parentComment->CanReply()) {
                        	$this->httpError(403);
                        }
                }
    		}
        }

		
		$comment = new Comment();
		$form->saveInto($comment);

		$comment->AllowHtml = Commenting::get_config_value($class, 'html_allowed');
		$comment->Moderated = ($moderated) ? false : true;

		
		
        


		// Save into DB, or call pre-save hooks to give accurate preview
		if($isPreview) {
			$comment->extend('onBeforeWrite', $dummy);
		} else {
			$comment->write();	

		// extend hook to allow extensions. Also see onBeforePostComment
		$this->extend('onAfterPostComment', $comment);	
		}
		
		// clear the users comment since it passed validation
		Cookie::set('CommentsForm_Comment', false);

		$holder = Commenting::get_config_value($comment->BaseClass, 'comments_holder_id');

		$hash = ($moderated) ? $holder : $comment->Permalink();
		$url = (isset($data['ReturnURL'])) ? $data['ReturnURL'] : false;

		if (Director::is_ajax()) {
			$result = array();
			if ($url) {
				$result['success'] = true;
				if (Commenting::get_config_value($comment->BaseClass, 'require_moderation')) {
					$result['message'] = _t('COMMENT.SuccessButModerated','Your comment is awaiting moderation');
				} else {
					$result['message'] = _t('COMMENT.Success','Your comment was posted');
				}
				
			} else {
				$result['success'] = false;
				$result['message'] = _t('COMMENT.Fail','Please fix error in form');
			}
			$result['parentcommentid'] = $comment->ParentCommentID;
			$result['commentid'] = $comment->ID;
			$result['depth'] = $comment->Depth;

			$result['requiresmoderation'] = Commenting::get_config_value($comment->BaseClass, 'require_moderation');


			$interface = new SSViewer('CommentsInterfaceForm');
			
			//$controller = new CommentingController();		
			//$controller->setOwnerRecord($this->owner);
			//$controller->setBaseClass($this->ownerBaseClass);
			//$controller->setOwnerController(Controller::curr());

			$moderatedSubmitted = Session::get('CommentsModerated');
			Session::clear('CommentsModerated');

			// a little bit all over the show but to ensure a slightly easier upgrade for users
			// return back the same variables as previously done in comments
			$result['html'] = $interface->process(new ArrayData(array(
				'CommentHolderID' 			=> Commenting::get_config_value($this->ownerBaseClass, 'comments_holder_id'),
				'PostingRequiresPermission' => Commenting::get_config_value($this->ownerBaseClass, 'required_permission'),
				'CanPost' 					=> Commenting::can_member_post($this->ownerBaseClass),
				'RssLink'					=> "CommentingController/rss",
				//'RssLinkPage'				=> "CommentingController/rss/". $this->ownerBaseClass . '/'.$this->owner->ID,
				'RssLinkPage'				=> "CommentingController/rss/". $this->ownerBaseClass.'FIXME',
				'CommentsEnabled' 			=> true,
				'Parent'					=> $this->dataRecord,
				'AddCommentForm'			=> $form,
				'ModeratedSubmitted'		=> $moderatedSubmitted,
				//'ThreadedComments'          => true, // FIXME - hardcoded
	           // 'MaxThreadedCommentDepth'   => 10
	            'ThreadedComments'          => Commenting::get_config_value($this->ownerBaseClass, 'thread_comments'),
	            'MaxThreadedCommentDepth'   => Commenting::get_config_value($this->ownerBaseClass, 'maximum_thread_comment_depth')
			)))->Value;

			return json_encode($result);
		} else {
			// return either back to the form if errors, or redirect to the comment holder to show any messages
			return ($url) ? $this->redirect($url .'#'. $hash) : $this->redirectBack();
		}
	}

	public function doPreviewComment($data, $form) {
		$data['IsPreview'] = 1;

		return $this->doPostComment($data, $form);
	}
}
