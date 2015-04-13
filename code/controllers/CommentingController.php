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
		'rss',
		'CommentsForm',
		'doPostComment',
		'doPreviewComment'
	);

	/**
	 * Base class this commenting form is for
	 *
	 * @var string
	 */
	private $baseClass = "";

	/**
	 * The record this commenting form is for
	 * 
	 * @var DataObject
	 */
	private $ownerRecord = null;

	/**
	 * Parent controller record
	 *
	 * @var Controller
	 */
	private $ownerController = null;

	/**
	 * Backup url to return to
	 *
	 * @var string
	 */
	protected $fallbackReturnURL = null;

	/**
	 * Set the base class to use
	 *
	 * @param string $class
	 */
	public function setBaseClass($class) {
		$this->baseClass = $class;
	}

	/**
	 * Get the base class used
	 *
	 * @return string
	 */
	public function getBaseClass() {
		return $this->baseClass;
	}

	/**
	 * Set the record this controller is working on
	 *
	 * @param DataObject $record
	 */
	public function setOwnerRecord($record) {
		$this->ownerRecord = $record;
	}

	/**
	 * Get the record
	 *
	 * @return DataObject
	 */
	public function getOwnerRecord() {
		return $this->ownerRecord;
	}

	/**
	 * Set the parent controller
	 *
	 * @param Controller $controller
	 */
	public function setOwnerController($controller) {
		$this->ownerController = $controller;
	}

	/**
	 * Get the parent controller
	 *
	 * @return Controller
	 */
	public function getOwnerController() {
		return $this->ownerController;
	}

	/**
	 * Get the commenting option for the current state
	 *
	 * @param string $key
	 * @return mixed Result if the setting is available, or null otherwise
	 */
	public function getOption($key) {
		// If possible use the current record
		if($record = $this->getOwnerRecord()) {
			return $record->getCommentsOption($key);
		}
		
		// Otherwise a singleton of that record
		if($class = $this->getBaseClass()) {
			return singleton($class)->getCommentsOption($key);
		}

		// Otherwise just use the default options
		return singleton('CommentsExtension')->getCommentsOption($key);
	}
	
	/**
	 * Workaround for generating the link to this controller
	 *
	 * @return string
	 */
	public function Link($action = '', $id = '', $other = '') {
		return Controller::join_links(Director::baseURL(), __CLASS__ , $action, $id, $other);
	}
	
	/**
	 * Outputs the RSS feed of comments
	 *
	 * @return HTMLText
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

		// Support old pageid param
		if(!$id && !$class && ($id = $request->getVar('pageid'))) {
			$class = 'SiteTree';
		}

		$comments = Comment::get()->filter(array(
			'Moderated' => 1,
			'IsSpam' => 0,
		));

		// Check if class filter
		if($class) {
			if(!is_subclass_of($class, 'DataObject') || !$class::has_extension('CommentsExtension')) {
				return $this->httpError(404);
			}
			$this->setBaseClass($class);
			$comments = $comments->filter('BaseClass', $class);
			$link = Controller::join_links($link, $class);

			// Check if id filter
			if($id) {
				$comments = $comments->filter('ParentID', $id);
				$link = Controller::join_links($link, $id);
				$this->setOwnerRecord(DataObject::get_by_id($class, $id));
			}
		}

		$title = _t('CommentingController.RSSTITLE', "Comments RSS Feed");

		$comments = new PaginatedList($comments, $request);
		$comments->setPageLength($this->getOption('comments_per_page'));

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
		$comment = $this->getComment();
		if(!$comment) return $this->httpError(404);
		if(!$comment->canDelete()) {
			return Security::permissionFailure($this, 'You do not have permission to delete this comment');
		}
		if(!$comment->getSecurityToken()->checkRequest($this->request)) return $this->httpError(400);

		$comment->delete();

		return $this->request->isAjax()
			? true
			: $this->redirectBack();
	}

	/**
	 * Marks a given {@link Comment} as spam. Removes the comment from display
	 */
	public function spam() {
		$comment = $this->getComment();
		if(!$comment) return $this->httpError(404);
		if(!$comment->canEdit()) {
			return Security::permissionFailure($this, 'You do not have permission to edit this comment');
		}
		if(!$comment->getSecurityToken()->checkRequest($this->request)) return $this->httpError(400);
		
		$comment->IsSpam = true;
		$comment->Moderated = true;
		$comment->write();

		return $this->request->isAjax()
			? $comment->renderWith('CommentsInterface_singlecomment')
			: $this->redirectBack();
	}

	/**
	 * Marks a given {@link Comment} as ham (not spam).
	 */
	public function ham() {
		$comment = $this->getComment();
		if(!$comment) return $this->httpError(404);
		if(!$comment->canEdit()) {
			return Security::permissionFailure($this, 'You do not have permission to edit this comment');
		}
		if(!$comment->getSecurityToken()->checkRequest($this->request)) return $this->httpError(400);
		
		$comment->IsSpam = false;
		$comment->Moderated = true;
		$comment->write();

		return $this->request->isAjax()
			? $comment->renderWith('CommentsInterface_singlecomment')
			: $this->redirectBack();
	}

	/**
	 * Marks a given {@link Comment} as approved.
	 */
	public function approve() {
		$comment = $this->getComment();
		if(!$comment) return $this->httpError(404);
		if(!$comment->canEdit()) {
			return Security::permissionFailure($this, 'You do not have permission to approve this comment');
		}
		if(!$comment->getSecurityToken()->checkRequest($this->request)) return $this->httpError(400);

		$comment->IsSpam = false;
		$comment->Moderated = true;
		$comment->write();

		return $this->request->isAjax()
			? $comment->renderWith('CommentsInterface_singlecomment')
			: $this->redirectBack();
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
				$this->fallbackReturnURL = $comment->Link();
				return $comment;
			}
		}

		return false;
	}

	/**
	 * Post a comment form
	 *
	 * @return Form
	 */
	public function CommentsForm() {
		$usePreview = $this->getOption('use_preview');

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
			HiddenField::create("BaseClass")
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

		// Load member data
		$requireLogin = $this->getOption('require_login');
		$permission = $this->getOption('required_permission');
		$member = Member::currentUser();
		if(($requireLogin || $permission) && $member) {
			$fields = $form->Fields();

			$fields->removeByName('Name');
			$fields->removeByName('Email');
			$fields->insertBefore(new ReadonlyField("NameView", _t('CommentInterface.YOURNAME', 'Your name'), $member->getName()), 'URL');
			$fields->push(new HiddenField("Name", "", $member->getName()));
			$fields->push(new HiddenField("Email", "", $member->Email));
		}

		// if the record exists load the extra required data
		if($record = $this->getOwnerRecord()) {
			// we do not want to read a new URL when the form has already been submitted
			// which in here, it hasn't been.
			$form->loadDataFrom(array(
				'ParentID'		=> $record->ID,
				'ReturnURL'		=> $this->request->getURL(),
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
		// Load class and parent from data
		if(isset($data['BaseClass'])) {
			$this->setBaseClass($data['BaseClass']);
		}
		if(isset($data['ParentID']) && ($class = $this->getBaseClass())) {
			$this->setOwnerRecord($class::get()->byID($data['ParentID']));
		}
		if(!$this->getOwnerRecord()) return $this->httpError(404);
		
		// cache users data
		Cookie::set("CommentsForm_UserData", Convert::raw2json($data));
		Cookie::set("CommentsForm_Comment", $data['Comment']);
		
		// extend hook to allow extensions. Also see onAfterPostComment
		$this->extend('onBeforePostComment', $form);	
		
		// If commenting can only be done by logged in users, make sure the user is logged in
		if(!$this->getOwnerRecord()->canPostComment()) {
			return Security::permissionFailure(
				$this,
				_t(
					'CommentingController.PERMISSIONFAILURE',
					"You're not able to post comments to this page. Please ensure you are logged in and have an "
					. "appropriate permission level."
				)
			);
		}

		if($member = Member::currentUser()) {
			$form->Fields()->push(new HiddenField("AuthorID", "Author ID", $member->ID));
		} 

		// is moderation turned on
		$requireModeration = $this->getOption('require_moderation');
		if(!$requireModeration) {
			$requireModerationNonmembers = $this->getOption('require_moderation_nonmembers');
			$requireModeration = $requireModerationNonmembers ? !Member::currentUser() : false;
		}
		
		// we want to show a notification if comments are moderated
		if ($requireModeration) {
			Session::set('CommentsModerated', 1);
		}

		$comment = new Comment();
		$form->saveInto($comment);

		$comment->AllowHtml = $this->getOption('html_allowed');
		$comment->Moderated = !$requireModeration;

		// Save into DB, or call pre-save hooks to give accurate preview
		$usePreview = $this->getOption('use_preview');
		$isPreview = $usePreview && !empty($data['IsPreview']);
		if($isPreview) {
			$comment->extend('onBeforeWrite');
		} else {
			$comment->write();

			// extend hook to allow extensions. Also see onBeforePostComment
			$this->extend('onAfterPostComment', $comment);
		}
		
		// clear the users comment since it passed validation
		Cookie::set('CommentsForm_Comment', false);

		// Find parent link
		if(!empty($data['ReturnURL'])) {
			$url = $data['ReturnURL'];
		} elseif($parent = $comment->getParent()) {
			$url = $parent->Link();
		} else {
			return $this->redirectBack();
		}

		// Given a redirect page exists, attempt to link to the correct anchor
		if(!$comment->Moderated) {
			// Display the "awaiting moderation" text
			$holder = $this->getOption('comments_holder_id');
			$hash = "{$holder}_PostCommentForm_error";
		} elseif($comment->IsSpam) {
			// Link to the form with the error message contained
			$hash = $form->FormName();
		} else {
			// Link to the moderated, non-spam comment
			$hash = $comment->Permalink();
		}

		return $this->redirect(Controller::join_links($url, "#{$hash}"));
	}

	public function doPreviewComment($data, $form) {
		$data['IsPreview'] = 1;

		return $this->doPostComment($data, $form);
	}

	public function redirectBack() {
		// Don't cache the redirect back ever
		HTTP::set_cache_age(0);

		$url = null;

		// In edge-cases, this will be called outside of a handleRequest() context; in that case,
		// redirect to the homepage - don't break into the global state at this stage because we'll
		// be calling from a test context or something else where the global state is inappropraite
		if($this->request) {
			if($this->request->requestVar('BackURL')) {
				$url = $this->request->requestVar('BackURL');
			} else if($this->request->isAjax() && $this->request->getHeader('X-Backurl')) {
				$url = $this->request->getHeader('X-Backurl');
			} else if($this->request->getHeader('Referer')) {
				$url = $this->request->getHeader('Referer');
			}
		}

		if(!$url) $url = $this->fallbackReturnURL;
		if(!$url) $url = Director::baseURL();

		// absolute redirection URLs not located on this site may cause phishing
		if(Director::is_site_url($url)) {
			return $this->redirect($url);
		} else {
			return false;
		}

	}
}
