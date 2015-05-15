<?php

/**
 * Represents a single comment object.
 *
 * @property string $Name
 * @property string $Comment
 * @property string $Email
 * @property string $URL
 * @property string $BaseClass
 * @property bool $Moderated
 * @property bool $IsSpam
 * @property int $ParentID
 * @property bool $AllowHtml
 * @property string $SecretToken
 * @property int $Depth
 *
 * @method HasManyList ChildComments()
 * @method Member Author()
 * @method Comment ParentComment()
 *
 * @package comments
 */
class Comment extends DataObject {
	/**
	 * @var array
	 */
	private static $db = array(
		'Name' => 'Varchar(200)',
		'Comment' => 'Text',
		'Email' => 'Varchar(200)',
		'URL' => 'Varchar(255)',
		'BaseClass' => 'Varchar(200)',
		'Moderated' => 'Boolean(0)',
		'IsSpam' => 'Boolean(0)',
		'ParentID' => 'Int',
		'AllowHtml' => 'Boolean',
		'SecretToken' => 'Varchar(255)',
		'Depth' => 'Int',
	);

	/**
	 * @var array
	 */
	private static $has_one = array(
		'Author' => 'Member',
		'ParentComment' => 'Comment',
	);

	/**
	 * @var array
	 */
	private static $has_many = array(
		'ChildComments' => 'Comment'
	);

	/**
	 * @var string
	 */
	private static $default_sort = '"Created" DESC';

	/**
	 * @var array
	 */
	private static $defaults = array(
		'Moderated' => 0,
		'IsSpam' => 0,
	);

	/**
	 * @var array
	 */
	private static $casting = array(
		'Title' => 'Varchar',
		'ParentTitle' => 'Varchar',
		'ParentClassName' => 'Varchar',
		'AuthorName' => 'Varchar',
		'RSSName' => 'Varchar',
		'DeleteLink' => 'Varchar',
		'SpamLink' => 'Varchar',
		'HamLink' => 'Varchar',
		'ApproveLink' => 'Varchar',
		'Permalink' => 'Varchar',
	);

	/**
	 * @var array
	 */
	private static $searchable_fields = array(
		'Name',
		'Email',
		'Comment',
		'Created',
		'BaseClass',
	);

	/**
	 * @var array
	 */
	private static $summary_fields = array(
		'Name' => 'Submitted By',
		'Email' => 'Email',
		'Comment.LimitWordCount' => 'Comment',
		'Created' => 'Date Posted',
		'ParentTitle' => 'Post',
		'IsSpam' => 'Is Spam',
	);

	/**
	 * @var array
	 */
	private static $field_labels = array(
		'Author' => 'Author Member',
	);

	/**
	 * {@inheritdoc
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();

		if($this->AllowHtml) {
			$this->Comment = $this->purifyHtml($this->Comment);
		}

		$this->updateDepth();
	}

	/**
	 * {@inheritdoc
	 */
	public function onBeforeDelete() {
		parent::onBeforeDelete();

		foreach($this->ChildComments() as $comment) {
			$comment->delete();
		}
	}

	/**
	 * @return Comment_SecurityToken
	 */
	public function getSecurityToken() {
		return Injector::inst()->createWithArgs('Comment_SecurityToken', array($this));
	}

	/**
	 * {@inheritdoc}
	 *
	 * Migrates the old {@link PageComment} objects to {@link Comment}.
	 */
	public function requireDefaultRecords() {
		parent::requireDefaultRecords();

		if(DB::getConn()->hasTable('PageComment')) {
			$comments = DB::query('SELECT * FROM PageComment');

			if($comments) {
				while($pageComment = $comments->nextRecord()) {
					$comment = new Comment();
					$comment->update($pageComment);

					$comment->BaseClass = 'SiteTree';
					$comment->URL = '';

					if(isset($pageComment['CommenterURL'])) {
						$comment->URL = $pageComment['CommenterURL'];
					}

					if($pageComment['NeedsModeration'] == false) {
						$comment->Moderated = true;
					}

					$comment->write();
				}
			}

			DB::alteration_message('Migrated PageComment to Comment', 'changed');
			DB::getConn()->dontRequireTable('PageComment');
		}
	}

	/**
	 * Return a link to this comment.
	 *
	 * @param string $action
	 *
	 * @return string
	 */
	public function Link($action = '') {
		if($parent = $this->getParent()) {
			return $parent->Link($action) . '#' . $this->Permalink();
		}

		return '';
	}

	/**
	 * Returns the permalink for this {@link Comment}. Inserted into the ID tag of the comment.
	 *
	 * @return string
	 */
	public function Permalink() {
		return $this->getOption('comment_permalink_prefix') . $this->ID;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param bool $includeRelations
	 */
	public function fieldLabels($includeRelations = true) {
		$labels = parent::fieldLabels($includeRelations);

		$labels['Name'] = _t('Comment.NAME', 'Author Name');
		$labels['Comment'] = _t('Comment.COMMENT', 'Comment');
		$labels['Email'] = _t('Comment.EMAIL', 'Email');
		$labels['URL'] = _t('Comment.URL', 'URL');
		$labels['IsSpam'] = _t('Comment.ISSPAM', 'Spam?');
		$labels['Moderated'] = _t('Comment.MODERATED', 'Moderated?');
		$labels['ParentTitle'] = _t('Comment.PARENTTITLE', 'Parent');
		$labels['Created'] = _t('Comment.CREATED', 'Date posted');

		return $labels;
	}

	/**
	 * Get the commenting option.
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getOption($key) {
		$record = $this->getParent();

		if(!$record && $this->BaseClass) {
			$record = singleton($this->BaseClass);
		} elseif(!$record) {
			$record = singleton('CommentsExtension');
		}

		return $record->getCommentsOption($key);
	}

	/**
	 * Returns the parent {@link DataObject} this comment is attached too.
	 *
	 * @return null|DataObject
	 */
	public function getParent() {
		if($this->BaseClass && $this->ParentID) {
			return DataObject::get_by_id($this->BaseClass, $this->ParentID, true);
		}

		return null;
	}

	/**
	 * Returns a string to help identify the parent of the comment.
	 *
	 * @return string
	 */
	public function getParentTitle() {
		$parent = $this->getParent();

		if($parent && $parent->Title) {
			return $parent->Title;
		}

		return $parent->ClassName . ' #' . $parent->ID;
	}

	/**
	 * Comment-parent class names may vary, return the parent class name.
	 *
	 * @return string
	 */
	public function getParentClassName() {
		return $this->BaseClass;
	}

	/**
	 * {@inheritdoc}
	 */
	public function castingHelper($field) {
		if($field === 'EscapedComment') {
			return $this->AllowHtml ? 'HTMLText' : 'Text';
		}

		return parent::castingHelper($field);
	}

	/**
	 * @todo escape this comment? (DOH!)
	 *
	 * @return string
	 */
	public function getEscapedComment() {
		return $this->Comment;
	}

	/**
	 * Return whether this comment is a preview (has not been written to the db).
	 *
	 * @return bool
	 */
	public function isPreview() {
		return !$this->exists();
	}

	/**
	 * @todo needs to compare to the new {@link Commenting} configuration API
	 *
	 * @param null|Member $member
	 *
	 * @return bool
	 */
	public function canCreate($member = null) {
		return false;
	}

	/**
	 * Checks for association with a page, and {@link SiteTree->ProvidePermission} flag being set
	 * to true.
	 *
	 * @param null|int|Member $member
	 *
	 * @return bool
	 */
	public function canView($member = null) {
		$member = $this->getMember($member);

		$extended = $this->extendedCan('canView', $member);

		if($extended !== null) {
			return $extended;
		}

		if(Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin')) {
			return true;
		}

		if($parent = $this->getParent()) {
			return $parent->canView($member) && $parent->has_extension('CommentsExtension') && $parent->CommentsEnabled;
		}

		return false;
	}

	/**
	 * Checks if the comment can be edited.
	 *
	 * @param null|int|Member $member
	 *
	 * @return bool
	 */
	public function canEdit($member = null) {
		$member = $this->getMember($member);

		if(!$member) {
			return false;
		}

		$extended = $this->extendedCan('canEdit', $member);

		if($extended !== null) {
			return $extended;
		}

		if(Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin')) {
			return true;
		}

		if($parent = $this->getParent()) {
			return $parent->canEdit($member);
		}

		return false;
	}

	/**
	 * Checks if the comment can be deleted.
	 *
	 * @param null|int|Member $member
	 *
	 * @return bool
	 */
	public function canDelete($member = null) {
		$member = $this->getMember($member);

		if(!$member) {
			return false;
		}

		$extended = $this->extendedCan('canDelete', $member);

		if($extended !== null) {
			return $extended;
		}

		return $this->canEdit($member);
	}

	/**
	 * Resolves Member object.
	 *
	 * @param null|int|Member $member
	 *
	 * @return null|Member
	 */
	protected function getMember($member = null) {
		if(!$member) {
			$member = Member::currentUser();
		}

		if(is_numeric($member)) {
			$member = DataObject::get_by_id('Member', $member, true);
		}

		return $member;
	}

	/**
	 * Return the authors name for the comment.
	 *
	 * @return string
	 */
	public function getAuthorName() {
		if($this->Name) {
			return $this->Name;
		} else if($author = $this->Author()) {
			return $author->getName();
		}

		return '';
	}

	/**
	 * Generate a secure admin-action link authorised for the specified member.
	 *
	 * @param string $action
	 * @param null|Member $member
	 *
	 * @return string
	 */
	protected function actionLink($action, $member = null) {
		if(!$member) {
			$member = Member::currentUser();
		}

		if(!$member) {
			return false;
		}

		$url = Controller::join_links(
			Director::baseURL(),
			'CommentingController',
			$action,
			$this->ID
		);

		$token = $this->getSecurityToken();

		return $token->addToUrl($url, $member);
	}

	/**
	 * Link to delete this comment.
	 *
	 * @param null|Member $member
	 *
	 * @return null|string
	 */
	public function DeleteLink($member = null) {
		if($this->canDelete($member)) {
			return $this->actionLink('delete', $member);
		}

		return null;
	}

	/**
	 * Link to mark as spam.
	 *
	 * @param null|Member $member
	 *
	 * @return null|string
	 */
	public function SpamLink($member = null) {
		if($this->canEdit($member) && !$this->IsSpam) {
			return $this->actionLink('spam', $member);
		}

		return null;
	}

	/**
	 * Link to mark as not-spam.
	 *
	 * @param null|Member $member
	 *
	 * @return null|string
	 */
	public function HamLink($member = null) {
		if($this->canEdit($member) && $this->IsSpam) {
			return $this->actionLink('ham', $member);
		}

		return null;
	}

	/**
	 * Link to approve this comment.
	 *
	 * @param null|Member $member
	 *
	 * @return null|string
	 */
	public function ApproveLink($member = null) {
		if($this->canEdit($member) && !$this->Moderated) {
			return $this->actionLink('approve', $member);
		}

		return null;
	}

	/**
	 * Mark this comment as spam.
	 */
	public function markSpam() {
		$this->IsSpam = true;
		$this->Moderated = true;
		$this->write();

		$this->extend('afterMarkSpam');
	}

	/**
	 * Mark this comment as approved.
	 */
	public function markApproved() {
		$this->IsSpam = false;
		$this->Moderated = true;
		$this->write();

		$this->extend('afterMarkApproved');
	}

	/**
	 * Mark this comment as unapproved.
	 */
	public function markUnapproved() {
		$this->Moderated = false;
		$this->write();

		$this->extend('afterMarkUnapproved');
	}

	/**
	 * @return string
	 */
	public function SpamClass() {
		if($this->IsSpam) {
			return 'spam';
		} else if(!$this->Moderated) {
			return 'unmoderated';
		}

		return 'notspam';
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		$title = sprintf(
			_t('Comment.COMMENTBY', 'Comment by %s', 'Name'),
			$this->getAuthorName()
		);

		$parent = $this->getParent();

		if($parent && $parent->Title) {
			$title .= sprintf(
				' %s %s',
				_t('Comment.ON', 'on'),
				$parent->Title
			);
		}

		return $title;
	}

	/**
	 * Modify the default fields shown to the user.
	 */
	public function getCMSFields() {

		$commentFieldType = 'TextareaField';

		if($this->AllowHtml) {
			$commentFieldType = 'HtmlEditorField';
		}

		$createdField = $this->obj('Created')
			->scaffoldFormField($this->fieldLabel('Created'))
			->performReadonlyTransformation();

		$nameField = TextField::create('Name', $this->fieldLabel('AuthorName'));

		$commentField = $commentFieldType::create('Comment', $this->fieldLabel('Comment'));

		$emailField = EmailField::create('Email', $this->fieldLabel('Email'));

		$urlField = TextField::create('URL', $this->fieldLabel('URL'));

		$moderatedField = CheckboxField::create('Moderated', $this->fieldLabel('Moderated'));

		$spamField = CheckboxField::create('IsSpam', $this->fieldLabel('IsSpam'));

		$fieldGroup = FieldGroup::create(array(
			$moderatedField,
			$spamField,
		));

		$fieldGroup->setTitle('Options');
		$fieldGroup->setDescription(_t(
			'Comment.OPTION_DESCRIPTION',
			'Unmoderated and spam comments will not be displayed until approved'
		));

		$fields = new FieldList(
			$createdField,
			$nameField,
			$commentField,
			$emailField,
			$urlField,
			$fieldGroup
		);

		$author = $this->Author();

		if($author && $author->exists()) {
			$authorMemberField = TextField::create('AuthorMember', $this->fieldLabel('Author'), $author->Title);
			$authorMemberField->performReadonlyTransformation();

			$fields->insertAfter(
				$authorMemberField,
				'Name'
			);
		}

		$parent = $this->ParentComment();

		if($parent && $parent->exists()) {
			$fields->push(
				new HeaderField(
					'ParentComment_Title',
					_t('Comment.ParentComment_Title', 'This comment is a reply to the below')
				)
			);

			$fields->push(
				$parent->obj('Created')
					->scaffoldFormField($parent->fieldLabel('Created'))
					->setName('ParentComment_Created')
					->setValue($parent->Created)
					->performReadonlyTransformation()
			);

			$fields->push(
				$parent->obj('AuthorName')
					->scaffoldFormField($parent->fieldLabel('AuthorName'))
					->setName('ParentComment_AuthorName')
					->setValue($parent->getAuthorName())
					->performReadonlyTransformation()
			);

			$fields->push(
				$parent->obj('EscapedComment')
					->scaffoldFormField($parent->fieldLabel('Comment'))
					->setName('ParentComment_EscapedComment')
					->setValue($parent->Comment)
					->performReadonlyTransformation()
			);
		}

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}

	/**
	 * @param  string $dirtyHtml
	 *
	 * @return string
	 */
	public function purifyHtml($dirtyHtml) {
		return $this->getHtmlPurifierService()
			->purify($dirtyHtml);
	}

	/**
	 * @return HTMLPurifier
	 */
	public function getHtmlPurifierService() {
		$config = HTMLPurifier_Config::createDefault();

		$config->set('HTML.AllowedElements', $this->getOption('html_allowed_elements'));
		$config->set('AutoFormat.AutoParagraph', true);
		$config->set('AutoFormat.Linkify', true);
		$config->set('URI.DisableExternalResources', true);
		$config->set('Cache.SerializerPath', getTempFolder());

		return new HTMLPurifier($config);
	}

	/**
	 * Calculate the Gravatar link from the email address.
	 *
	 * @return string
	 */
	public function Gravatar() {
		if($this->getOption('use_gravatar')) {
			return sprintf(
				'http://www.gravatar.com/avatar/%s?s=%s&d=%s&r=%s',
				md5(strtolower(trim($this->Email))),
				$this->getOption('gravatar_size'),
				$this->getOption('gravatar_default'),
				$this->getOption('gravatar_rating')
			);
		}

		return '';
	}

	/**
	 * Determine if replies are enabled for this instance.
	 *
	 * @return bool
	 */
	public function getRepliesEnabled() {
		if(!$this->getOption('nested_comments')) {
			return false;
		}

		$maxLevel = $this->getOption('nested_depth');

		return !$maxLevel || $this->Depth < (int) $maxLevel;
	}

	/**
	 * Returns the list of all replies.
	 *
	 * @return SS_List
	 */
	public function AllReplies() {
		if(!$this->getRepliesEnabled()) {
			return new ArrayList();
		}

		$order = $this->getOption('order_replies_by');

		if(!$order) {
			$order = $this->getOption('order_comments_by');
		}

		$list = $this
			->ChildComments()
			->sort($order);

		$this->extend('updateAllReplies', $list);

		return $list;
	}

	/**
	 * Returns the list of replies, with spam and un-moderated items excluded, for use in the
	 * frontend.
	 *
	 * @return SS_List
	 */
	public function Replies() {
		if(!$this->getRepliesEnabled()) {
			return new ArrayList();
		}

		$list = $this->AllReplies();

		$parent = $this->getParent();

		$showSpam = $parent && $parent->canModerateComments() && $this->getOption('frontend_spam');

		if(!$showSpam) {
			$list = $list->filter('IsSpam', 0);
		}

		$noModerationRequired = $parent && $parent->ModerationRequired === 'None';

		$showUnModerated = $noModerationRequired || $showSpam;

		if(!$showUnModerated) {
			$list = $list->filter('Moderated', 1);
		}

		$this->extend('updateReplies', $list);

		return $list;
	}

	/**
	 * Returns the list of replies paged, with spam and un-moderated items excluded, for use in the
	 * frontend.
	 *
	 * @return PaginatedList
	 */
	public function PagedReplies() {
		$list = $this->Replies();

		$list = new PaginatedList($list, Controller::curr()->getRequest());

		$list->setPaginationGetVar('repliesstart' . $this->ID);
		$list->setPageLength($this->getOption('comments_per_page'));

		$this->extend('updatePagedReplies', $list);

		return $list;
	}

	/**
	 * Generate a reply form for this comment.
	 *
	 * @return Form
	 */
	public function ReplyForm() {
		if(!$this->getRepliesEnabled()) {
			return null;
		}

		$parent = $this->getParent();

		if(!$parent || !$parent->exists()) {
			return null;
		}

		$controller = CommentingController::create();

		$controller->setOwnerRecord($parent);
		$controller->setBaseClass($parent->ClassName);
		$controller->setOwnerController(Controller::curr());

		return $controller->ReplyForm($this);
	}

	/**
	 * Refresh of this comment in the hierarchy.
	 */
	public function updateDepth() {
		$parent = $this->ParentComment();

		if($parent && $parent->exists()) {
			$parent->updateDepth();

			$this->Depth = $parent->Depth + 1;
		} else {
			$this->Depth = 1;
		}
	}
}

/**
 * Provides the ability to generate cryptographically secure tokens for comment moderation.
 */
class Comment_SecurityToken {
	/**
	 * @var null|string
	 */
	private $secret = null;

	/**
	 * @param Comment $comment Comment to generate this token for.
	 */
	public function __construct($comment) {
		if(!$comment->SecretToken) {
			$comment->SecretToken = $this->generate();
			$comment->write();
		}

		$this->secret = $comment->SecretToken;
	}

	/**
	 * Generate the token for the given salt and current secret.
	 *
	 * @param string $salt
	 *
	 * @return string
	 */
	protected function getToken($salt) {
		return hash_pbkdf2('sha256', $this->secret, $salt, 1000, 30);
	}

	/**
	 * Get the member-specific salt.
	 *
	 * The reason for making the salt specific to a user is that it cannot be "passed in" via a
	 * query string, requiring the same user to be present at both the link generation and the
	 * controller action.
	 *
	 * @param string $salt
	 * @param Member $member
	 *
	 * @return string
	 */
	protected function memberSalt($salt, $member) {
		$pepper = $member->Salt;

		if(!$pepper) {
			$pepper = $member->ID;
		}

		return $salt . $pepper;
	}

	/**
	 * @param string $url
	 * @param Member $member
	 *
	 * @return string
	 */
	public function addToUrl($url, $member) {
		$salt = $this->generate(15);
		$token = $this->getToken($this->memberSalt($salt, $member));

		return Controller::join_links(
			$url,
			sprintf(
				'?t=%s&s=%s',
				urlencode($token),
				urlencode($salt)
			)
		);
	}

	/**
	 * @param SS_HTTPRequest $request
	 *
	 * @return bool
	 */
	public function checkRequest($request) {
		$member = Member::currentUser();

		if(!$member) {
			return false;
		}

		$salt = $request->getVar('s');
		$token = $this->getToken($this->memberSalt($salt, $member));

		return $token === $request->getVar('t');
	}

	/**
	 * Generates new random key.
	 *
	 * @param null|int $length
	 *
	 * @return string
	 */
	protected function generate($length = null) {
		$generator = new RandomGenerator();

		$result = $generator->randomToken('sha256');

		if($length !== null) {
			return substr($result, 0, $length);
		}

		return $result;
	}
}
