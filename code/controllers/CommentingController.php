<?php

/**
 * @package comments
 */
class CommentingController extends Controller {
	/**
	 * @var array
	 */
	private static $allowed_actions = array(
		'delete',
		'spam',
		'ham',
		'approve',
		'rss',
		'CommentsForm',
		'reply',
		'doPostComment',
		'doPreviewComment',
	);

	/**
	 * @var array
	 */
	private static $url_handlers = array(
		'reply/$ParentCommentID//$ID/$OtherID' => 'reply',
	);

	/**
	 * Fields required for this form.
	 *
	 * @config
	 *
	 * @var array
	 */
	private static $required_fields = array(
		'Name',
		'Email',
		'Comment',
	);

	/**
	 * Base class this commenting form is for.
	 *
	 * @var string
	 */
	private $baseClass = '';

	/**
	 * The record this commenting form is for.
	 *
	 * @var null|DataObject
	 */
	private $ownerRecord = null;

	/**
	 * Parent controller record.
	 *
	 * @var null|Controller
	 */
	private $ownerController = null;

	/**
	 * Backup url to return to.
	 *
	 * @var null|string
	 */
	protected $fallbackReturnURL = null;

	/**
	 * Set the base class to use.
	 *
	 * @param string $class
	 */
	public function setBaseClass($class) {
		$this->baseClass = $class;
	}

	/**
	 * Get the base class used.
	 *
	 * @return string
	 */
	public function getBaseClass() {
		return $this->baseClass;
	}

	/**
	 * Set the record this controller is working on.
	 *
	 * @param DataObject $record
	 */
	public function setOwnerRecord($record) {
		$this->ownerRecord = $record;
	}

	/**
	 * Get the record.
	 *
	 * @return null|DataObject
	 */
	public function getOwnerRecord() {
		return $this->ownerRecord;
	}

	/**
	 * Set the parent controller.
	 *
	 * @param Controller $controller
	 */
	public function setOwnerController($controller) {
		$this->ownerController = $controller;
	}

	/**
	 * Get the parent controller.
	 *
	 * @return null|Controller
	 */
	public function getOwnerController() {
		return $this->ownerController;
	}

	/**
	 * Get the commenting option for the current state.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getOption($key) {
		if($record = $this->getOwnerRecord()) {
			return $record->getCommentsOption($key);
		}

		if($class = $this->getBaseClass()) {
			return singleton($class)->getCommentsOption($key);
		}

		return singleton('CommentsExtension')->getCommentsOption($key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function Link($action = '', $id = '', $other = '') {
		return Controller::join_links(Director::baseURL(), __CLASS__, $action, $id, $other);
	}

	/**
	 * Outputs the RSS feed of comments.
	 *
	 * @return HTMLText
	 */
	public function rss() {
		return $this->getFeed($this->request)->outputToBrowser();
	}

	/**
	 * Return an RSSFeed of comments for a given set of comments or all comments on the website.
	 *
	 * To maintain backwards compatibility with 2.4 this supports mapping of
	 * PageComment/rss?pageid= as well as the new RSS format for comments of
	 * CommentingController/rss/{classname}/{id}
	 *
	 * @param SS_HTTPRequest
	 *
	 * @return RSSFeed
	 */
	public function getFeed(SS_HTTPRequest $request) {
		$link = $this->Link('rss');
		$class = $request->param('ID');
		$id = $request->param('OtherID');

		if(!$id && !$class && ($id = $request->getVar('pageid'))) {
			$class = 'SiteTree';
		}

		$comments = Comment::get()
			->filter(array(
				'Moderated' => 1,
				'IsSpam' => 0,
			));

		if($class) {
			if(!is_subclass_of($class, 'DataObject') || !$class::has_extension('CommentsExtension')) {
				return $this->httpError(404);
			}

			$this->setBaseClass($class);

			$comments = $comments->filter('BaseClass', $class);
			$link = Controller::join_links($link, $class);

			if($id) {
				$comments = $comments->filter('ParentID', $id);
				$link = Controller::join_links($link, $id);
				$this->setOwnerRecord(DataObject::get_by_id($class, $id));
			}
		}

		$title = _t('CommentingController.RSSTITLE', 'Comments RSS Feed');

		$comments = new PaginatedList($comments, $request);

		$comments->setPageLength(
			$this->getOption('comments_per_page')
		);

		return new RSSFeed(
			$comments,
			$link,
			$title,
			$link,
			'Title',
			'EscapedComment',
			'AuthorName'
		);
	}

	/**
	 * Deletes a given {@link Comment} via the URL.
	 */
	public function delete() {
		$comment = $this->getComment();

		if(!$comment) {
			$this->httpError(404);
		}

		if(!$comment->canDelete()) {
			return Security::permissionFailure($this, 'You do not have permission to delete this comment');
		}

		if(!$comment->getSecurityToken()->checkRequest($this->request)) {
			$this->httpError(400);
		}

		$comment->delete();

		if($this->request->isAjax()) {
			return true;
		}

		return $this->redirectBack();
	}

	/**
	 * Marks a given {@link Comment} as spam. Removes the comment from display.
	 */
	public function spam() {
		$comment = $this->getComment();

		if(!$comment) {
			$this->httpError(404);
		}

		if(!$comment->canEdit()) {
			return Security::permissionFailure($this, 'You do not have permission to edit this comment');
		}

		if(!$comment->getSecurityToken()->checkRequest($this->request)) {
			$this->httpError(400);
		}

		$comment->markSpam();

		if($this->request->isAjax()) {
			return true;
		}

		return $this->redirectBack();
	}

	/**
	 * Marks a given {@link Comment} as ham (not spam).
	 */
	public function ham() {
		$comment = $this->getComment();

		if(!$comment) {
			$this->httpError(404);
		}

		if(!$comment->canEdit()) {
			return Security::permissionFailure($this, 'You do not have permission to edit this comment');
		}

		if(!$comment->getSecurityToken()->checkRequest($this->request)) {
			$this->httpError(400);
		}

		$comment->markApproved();

		if($this->request->isAjax()) {
			return true;
		}

		return $this->redirectBack();
	}

	/**
	 * Marks a given {@link Comment} as approved.
	 */
	public function approve() {
		$comment = $this->getComment();

		if(!$comment) {
			$this->httpError(404);
		}

		if(!$comment->canEdit()) {
			return Security::permissionFailure($this, 'You do not have permission to approve this comment');
		}

		if(!$comment->getSecurityToken()->checkRequest($this->request)) {
			$this->httpError(400);
		}

		$comment->markApproved();

		if($this->request->isAjax()) {
			return true;
		}

		return $this->redirectBack();
	}

	/**
	 * Returns the comment referenced in the URL. Permission checking should be done in the callee.
	 *
	 * @return bool|Comment
	 */
	public function getComment() {
		if(isset($this->urlParams['ID'])) {
			$id = $this->urlParams['ID'];

			$comment = DataObject::get_by_id('Comment', $id);

			if($comment) {
				$this->fallbackReturnURL = $comment->Link();

				return $comment;
			}
		}

		return false;
	}

	/**
	 * Create a reply form for a specified comment.
	 *
	 * @param Comment $comment
	 *
	 * @return Form
	 */
	public function ReplyForm($comment) {
		$form = $this->CommentsForm();
		$form->setName('ReplyForm_' . $comment->ID);
		$form->addExtraClass('reply-form');

		$form->loadDataFrom(array(
			'ParentCommentID' => $comment->ID,
		));

		$form->setFormAction(
			$this->Link('reply', $comment->ID)
		);

		$this->extend('updateReplyForm', $form);

		return $form;
	}

	/**
	 * Request handler for reply form.
	 *
	 * This method will disambiguate multiple reply forms in the same method.
	 *
	 * @param SS_HTTPRequest $request
	 *
	 * @return null|Form
	 */
	public function reply(SS_HTTPRequest $request) {
		if($parentID = $request->param('ParentCommentID')) {
			/**
			 * @var null|Comment $comment
			 */
			$comment = DataObject::get_by_id('Comment', $parentID, true);

			if($comment) {
				return $this->ReplyForm($comment);
			}
		}

		$this->httpError(404);

		return null;
	}

	/**
	 * Post a comment form.
	 *
	 * @return Form
	 */
	public function CommentsForm() {
		$usePreview = $this->getOption('use_preview');

		$fields = new FieldList(
			$dataFields = new CompositeField(
				$this->getNameField(),
				$this->getEmailField(),
				$this->getURLField(),
				$this->getCommentField()
			),
			HiddenField::create('ParentID'),
			HiddenField::create('ReturnURL'),
			HiddenField::create('ParentCommentID'),
			HiddenField::create('BaseClass')
		);

		if($usePreview) {
			$fields->insertAfter(
				$this->getPreviewCommentField(),
				'Comment'
			);
		}

		$dataFields->addExtraClass('data-fields');

		$actions = new FieldList(
			new FormAction(
				'doPostComment',
				_t('CommentInterface.POST', 'Post')
			)
		);

		if($usePreview) {
			$actions->push(
				$this->getPreviewCommentAction()
			);
		}

		$required = new RequiredFields($this->config()->required_fields);

		$form = new Form(
			$this,
			'CommentsForm',
			$fields,
			$actions,
			$required
		);

		if($record = $this->getOwnerRecord()) {
			$member = Member::currentUser();
			if(($record->CommentsRequireLogin || $record->PostingRequiredPermission) && $member) {
				$fields = $form->Fields();

				$fields->removeByName('Name');
				$fields->removeByName('Email');
				$fields->insertBefore(new ReadonlyField('NameView', _t('CommentInterface.YOURNAME', 'Your name'), $member->getName()), 'URL');
				$fields->push(new HiddenField('Name', '', $member->getName()));
				$fields->push(new HiddenField('Email', '', $member->Email));
			}

			$form->loadDataFrom(array(
				'ParentID' => $record->ID,
				'ReturnURL' => $this->request->getURL(),
				'BaseClass' => $this->getBaseClass()
			));
		}

		$form->setRedirectToFormOnValidationError(true);

		if($data = Cookie::get('CommentsForm_UserData')) {
			$data = Convert::json2array($data);

			$data += array(
				'Name' => '',
				'URL' => '',
				'Email' => '',
			);

			$form->loadDataFrom($data);

			$prevComment = Cookie::get('CommentsForm_Comment');

			if($prevComment && $prevComment != '') {
				$form->loadDataFrom(array(
					'Comment' => $prevComment,
				));
			}
		}

		if(!empty($member)) {
			$form->loadDataFrom($member);
		}

		$this->extend('alterCommentForm', $form);

		return $form;
	}

	/**
	 * Process which creates a {@link Comment} once a user submits a comment from this form.
	 *
	 * @param array $data
	 * @param Form $form
	 *
	 * @return bool|SS_HTTPResponse
	 */
	public function doPostComment($data, $form) {
		if(isset($data['BaseClass'])) {
			$this->setBaseClass($data['BaseClass']);
		}

		if(isset($data['ParentID']) && ($class = $this->getBaseClass())) {
			$this->setOwnerRecord($class::get()->byID($data['ParentID']));
		}

		if(!$this->getOwnerRecord()) {
			return $this->httpError(404);
		}

		Cookie::set('CommentsForm_UserData', Convert::raw2json($data));
		Cookie::set('CommentsForm_Comment', $data['Comment']);

		$this->extend('onBeforePostComment', $form);

		if(!$this->getOwnerRecord()->canPostComment()) {
			return Security::permissionFailure(
				$this,
				_t(
					'CommentingController.PERMISSIONFAILURE',
					'You\'re not able to post comments to this page. Please ensure you are logged in and have an appropriate permission level.'
				)
			);
		}

		if($member = Member::currentUser()) {
			$form->Fields()->push(
				new HiddenField('AuthorID', 'Author ID', $member->ID)
			);
		}

		switch($this->getOwnerRecord()->ModerationRequired) {
			case 'Required':
				$requireModeration = true;
				break;
			case 'NonMembersOnly':
				$requireModeration = empty($member);
				break;
			default:
				$requireModeration = false;
				break;
		}

		$comment = new Comment();
		$form->saveInto($comment);

		$comment->AllowHtml = $this->getOption('html_allowed');
		$comment->Moderated = !$requireModeration;

		if($this->getOption('use_preview') && !empty($data['IsPreview'])) {
			$comment->extend('onBeforeWrite');
		} else {
			$comment->write();

			$this->extend('onAfterPostComment', $comment);
		}

		if($requireModeration && !$comment->IsSpam) {
			Session::set('CommentsModerated', 1);
		}

		Cookie::set('CommentsForm_Comment', false);

		if(!empty($data['ReturnURL'])) {
			$url = $data['ReturnURL'];
		} elseif($parent = $comment->getParent()) {
			$url = $parent->Link();
		} else {
			return $this->redirectBack();
		}

		if(!$comment->Moderated) {
			$hash = sprintf(
				'%s_PostCommentForm_error',
				$this->getOption('comments_holder_id')
			);
		} elseif($comment->IsSpam) {
			$hash = $form->FormName();
		} else {
			$hash = $comment->Permalink();
		}

		return $this->redirect(Controller::join_links($url, '#' . $hash));
	}

	/**
	 * @param array $data
	 * @param Form $form
	 *
	 * @return bool|SS_HTTPResponse
	 */
	public function doPreviewComment($data, $form) {
		$data['IsPreview'] = 1;

		return $this->doPostComment($data, $form);
	}

	/**
	 * In edge-cases, this will be called outside of a handleRequest() context; in that case,
	 * redirect to the homepage. Don't break into the global state at this stage because we'll
	 * be calling from a test context or something else where the global state is inappropriate.
	 *
	 * @return bool|SS_HTTPResponse
	 */
	public function redirectBack() {
		HTTP::set_cache_age(0);

		$url = null;

		if($this->request) {
			if($this->request->requestVar('BackURL')) {
				$url = $this->request->requestVar('BackURL');
			} else if($this->request->isAjax() && $this->request->getHeader('X-Backurl')) {
				$url = $this->request->getHeader('X-Backurl');
			} else if($this->request->getHeader('Referer')) {
				$url = $this->request->getHeader('Referer');
			}
		}

		if(!$url) {
			$url = $this->fallbackReturnURL;
		}

		if(!$url) {
			$url = Director::baseURL();
		}

		if(Director::is_site_url($url)) {
			return $this->redirect($url);
		} else {
			return false;
		}
	}

	/**
	 * @return TextField
	 */
	protected function getNameField() {
		$nameFieldLabel = _t(
			'CommentInterface.YOURNAME',
			'Your name'
		);

		$nameRequiredLabel = _t(
			'CommentInterface.YOURNAME_MESSAGE_REQUIRED',
			'Please enter your name'
		);

		$nameField = TextField::create('Name', $nameFieldLabel);

		$nameField->setCustomValidationMessage($nameRequiredLabel);
		$nameField->setAttribute('data-msg-required', $nameRequiredLabel);

		return $nameField;
	}

	/**
	 * @return EmailField
	 */
	protected function getEmailField() {
		$emailFieldLabel = _t(
			'CommentingController.EMAILADDRESS',
			'Your email address (will not be published)'
		);

		$emailFieldRequiredLabel = _t(
			'CommentInterface.EMAILADDRESS_MESSAGE_REQUIRED',
			'Please enter your email address'
		);

		$emailFieldInvalidLabel = _t(
			'CommentInterface.EMAILADDRESS_MESSAGE_EMAIL',
			'Please enter a valid email address'
		);

		$emailField = EmailField::create('Email', $emailFieldLabel);

		$emailField->setCustomValidationMessage($emailFieldRequiredLabel);
		$emailField->setAttribute('data-msg-required', $emailFieldRequiredLabel);
		$emailField->setAttribute('data-msg-email', $emailFieldInvalidLabel);
		$emailField->setAttribute('data-rule-email', true);

		return $emailField;
	}

	/**
	 * @return TextField
	 */
	protected function getURLField() {
		$urlFieldLabel = _t(
			'CommentingController.WEBSITEURL',
			'Your website URL'
		);

		$urlInvalidLabel = _t(
			'CommentInterface.COMMENT_MESSAGE_URL',
			'Please enter a valid URL'
		);

		$urlField = TextField::create('URL', $urlFieldLabel);

		$urlField->setAttribute('data-msg-url', $urlInvalidLabel);
		$urlField->setAttribute('data-rule-url', true);

		return $urlField;
	}

	/**
	 * @return TextareaField
	 */
	protected function getCommentField() {
		$commentFieldLabel = _t(
			'CommentingController.COMMENTS',
			'Comments'
		);

		$commentRequiredLabel = _t(
			'CommentInterface.COMMENT_MESSAGE_REQUIRED',
			'Please enter your comment'
		);

		$commentField = TextareaField::create('Comment', $commentFieldLabel);

		$commentField->setCustomValidationMessage($commentRequiredLabel);
		$commentField->setAttribute('data-msg-required', $commentRequiredLabel);

		return $commentField;
	}

	/**
	 * @return ReadonlyField
	 */
	protected function getPreviewCommentField() {
		$previewCommentFieldLabel = _t(
			'CommentInterface.PREVIEWLABEL',
			'Preview'
		);

		$previewCommentField = ReadonlyField::create(
			'PreviewComment',
			$previewCommentFieldLabel
		);

		$previewCommentField->setAttribute('style', 'display: none');

		return $previewCommentField;
	}

	/**
	 * @return FormAction
	 */
	protected function getPreviewCommentAction() {
		$previewCommentActionLabel = _t(
			'CommentInterface.PREVIEW',
			'Preview'
		);

		$previewCommentAction = FormAction::create(
			'doPreviewComment',
			$previewCommentActionLabel
		);

		$previewCommentAction->addExtraClass('action-minor');
		$previewCommentAction->setAttribute('style', 'display: none');
	}
}
