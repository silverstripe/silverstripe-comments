<?php

/**
 * Extension to {@link DataObject} to enable tracking comments.
 *
 * @package comments
 */
class CommentsExtension extends DataExtension {
	/**
	 * Default configuration values
	 *
	 * enabled:                     Allows commenting to be disabled even if the extension is present
	 * enabled_cms:                 Allows commenting to be enabled or disabled via the CMS
	 * require_login:               Boolean, whether a user needs to login (required for required_permission)
	 * require_login_cms:           Allows require_login to be set via the CMS
	 * required_permission:         Permission (or array of permissions) required to comment
	 * include_js:                  Enhance operation by ajax behaviour on moderation links (required for use_preview)
	 * use_gravatar:                Set to true to show gravatar icons
	 * gravatar_default:            Theme for 'not found' gravatar {@see http://gravatar.com/site/implement/images}
	 * gravatar_rating:             Gravatar rating (same as the standard default)
	 * show_comments_when_disabled: Show older comments when commenting has been disabled.
	 * comments_holder_id:          ID for the comments holder
	 * comment_permalink_prefix:    ID prefix for each comment
	 * require_moderation:          Require moderation for all comments
	 * require_moderation_cms:      Ignore other comment moderation config settings and set via CMS
	 * html_allowed:                Allow for sanitized HTML in comments
	 * use_preview:                 Preview formatted comment (when allowing HTML)
	 *
	 * @var array
	 *
	 * @config
	 */
	private static $comments = array(
		'enabled' => true,
		'enabled_cms' => false,
		'require_login' => false,
		'require_login_cms' => false,
		'required_permission' => false,
		'include_js' => true,
		'use_gravatar' => false,
		'gravatar_size' => 80,
		'gravatar_default' => 'identicon',
		'gravatar_rating' => 'g',
		'show_comments_when_disabled' => false,
		'order_comments_by' => '"Created" DESC',
		'comments_per_page' => 10,
		'comments_holder_id' => 'comments-holder',
		'comment_permalink_prefix' => 'comment-',
		'require_moderation' => false,
		'require_moderation_nonmembers' => false,
		'require_moderation_cms' => false,
		'html_allowed' => false,
		'html_allowed_elements' => array('a', 'img', 'i', 'b'),
		'use_preview' => false,
	);

	/**
	 * @var array
	 */
	private static $db = array(
		'ProvideComments' => 'Boolean',
		'ModerationRequired' => 'Enum(\'None,Required,NonMembersOnly\',\'None\')',
		'CommentsRequireLogin' => 'Boolean',
	);

	/**
	 * CMS configurable options should default to the config values
	 */
	public function populateDefaults() {
		// Set if comments should be enabled by default
		$this->owner->ProvideComments = $this->owner->getCommentsOption('enabled') ? 1 : 0;

		// If moderation options should be configurable via the CMS then
		if($this->owner->getCommentsOption('require_moderation')) {
			$this->owner->ModerationRequired = 'Required';
		} elseif($this->owner->getCommentsOption('require_moderation_nonmembers')) {
			$this->owner->ModerationRequired = 'NonMembersOnly';
		} else {
			$this->owner->ModerationRequired = 'None';
		}

		$this->owner->CommentsRequireLogin = $this->owner->getCommentsOption('require_login') ? 1 : 0;
	}


	/**
	 * If this extension is applied to a {@link SiteTree} record then
	 * append a Provide Comments checkbox to allow authors to trigger
	 * whether or not to display comments
	 *
	 * @todo Allow customization of other {@link Commenting} configuration
	 *
	 * @param FieldList $fields
	 */
	public function updateSettingsFields(FieldList $fields) {

		$options = FieldGroup::create()->setTitle(_t('CommentsExtension.COMMENTOPTIONS', 'Comments'));

		// Check if enabled setting should be cms configurable
		if($this->owner->getCommentsOption('enabled_cms')) {
			$options->push(new CheckboxField('ProvideComments', _t('Comment.ALLOWCOMMENTS', 'Allow Comments')));
		}

		// Check if we should require users to login to comment
		if($this->owner->getCommentsOption('require_login_cms')) {
			$options->push(
				new CheckboxField(
					'CommentsRequireLogin',
					_t('Comments.COMMENTSREQUIRELOGIN', 'Require login to comment')
				)
			);
		}

		if($options->FieldList()->count()) {
			if($fields->hasTabSet()) {
				$fields->addFieldsToTab('Root.Settings', $options);
			} else {
				$fields->push($options);
			}
		}

		// Check if moderation should be enabled via cms configurable
		if($this->owner->getCommentsOption('require_moderation_cms')) {
			$moderationField = new DropdownField('ModerationRequired', 'Comment Moderation', array(
				'None' => _t('CommentsExtension.MODERATIONREQUIRED_NONE', 'No moderation required'),
				'Required' => _t('CommentsExtension.MODERATIONREQUIRED_REQUIRED', 'Moderate all comments'),
				'NonMembersOnly' => _t(
					'CommentsExtension.MODERATIONREQUIRED_NONMEMBERSONLY',
					'Only moderate non-members'
				),
			));
			if($fields->hasTabSet()) {
				$fields->addFieldsToTab('Root.Settings', $moderationField);
			} else {
				$fields->push($moderationField);
			}
		}
	}

	/**
	 * Returns the RelationList of all comments against this object. Can be used as a data source
	 * for a gridfield with write access.
	 *
	 * @return CommentList
	 */
	public function AllComments() {
		$comments = CommentList::create($this->ownerBaseClass)->forForeignID($this->owner->ID);
		$this->owner->extend('updateAllComments', $comments);
		return $comments;
	}

	public function getComments() {
		// TODO: find out why this is being triggered when combined with blog
		// Deprecation::notice('2.0', 'Use PagedComments to get paged comments');
		return $this->PagedComments();
	}

	/**
	 * Get comment moderation rules for this parent
	 *
	 * None:           No moderation required
	 * Required:       All comments
	 * NonMembersOnly: Only anonymous users
	 *
	 * @return string
	 */
	public function getModerationRequired() {
		if($this->owner->getCommentsOption('require_moderation_cms')) {
			return $this->owner->getField('ModerationRequired');
		} elseif($this->owner->getCommentsOption('require_moderation')) {
			return 'Required';
		} elseif($this->owner->getCommentsOption('require_moderation_nonmembers')) {
			return 'NonMembersOnly';
		} else {
			return 'None';
		}
	}

	/**
	 * Determine if users must be logged in to post comments
	 *
	 * @return boolean
	 */
	public function getCommentsRequireLogin() {
		if($this->owner->getCommentsOption('require_login_cms')) {
			return (bool) $this->owner->getField('CommentsRequireLogin');
		} else {
			return (bool) $this->owner->getCommentsOption('require_login');
		}
	}

	/**
	 * Returns the root level comments, with spam and unmoderated items excluded, for use in the frontend
	 *
	 * @return CommentList
	 */
	public function Comments() {
		// Get all non-spam comments
		$order = $this->owner->getCommentsOption('order_comments_by');
		$list = $this
			->AllComments()
			->sort($order)
			->filter('IsSpam', 0);

		// Filter un-moderated comments for non-administrators if moderation is enabled
		if($this->owner->ModerationRequired !== 'None') {
			$list = $list->filter('Moderated', 1);
		}

		$this->owner->extend('updateComments', $list);
		return $list;
	}

	/**
	 * Returns a paged list of the root level comments, with spam and unmoderated items excluded,
	 * for use in the frontend
	 *
	 * @return PaginatedList
	 */
	public function PagedComments() {
		$list = $this->Comments();

		// Add pagination
		$list = new PaginatedList($list, Controller::curr()->getRequest());
		$list->setPaginationGetVar('commentsstart' . $this->owner->ID);
		$list->setPageLength($this->owner->getCommentsOption('comments_per_page'));

		$this->owner->extend('updatePagedComments', $list);
		return $list;
	}

	/**
	 * Check if comments are configured for this page even if they are currently disabled.
	 * Do not include the comments on pages which don't have id's such as security pages
	 *
	 * @deprecated since version 2.0
	 *
	 * @return boolean
	 */
	public function getCommentsConfigured() {
		Deprecation::notice('2.0', 'getCommentsConfigured is deprecated. Use getCommentsEnabled instead');
		return true; // by virtue of all classes with this extension being 'configured'
	}

	/**
	 * Determine if comments are enabled for this instance
	 *
	 * @return boolean
	 */
	public function getCommentsEnabled() {
		// Don't display comments form for pseudo-pages (such as the login form)
		if(!$this->owner->exists()) return false;
		
		// Determine which flag should be used to determine if this is enabled
		if($this->owner->getCommentsOption('enabled_cms')) {
			return $this->owner->ProvideComments;
		} else {
			return $this->owner->getCommentsOption('enabled');
		}
	}

	/**
	 * Get the HTML ID for the comment holder in the template
	 *
	 * @return string
	 */
	public function getCommentHolderID() {
		return $this->owner->getCommentsOption('comments_holder_id');
	}

	/**
	 * @deprecated since version 2.0
	 */
	public function getPostingRequiresPermission() {
		Deprecation::notice('2.0', 'Use getPostingRequiredPermission instead');
		return $this->getPostingRequiredPermission();
	}

	/**
	 * Permission codes required in order to post (or empty if none required)
	 *
	 * @return string|array Permission or list of permissions, if required
	 */
	public function getPostingRequiredPermission() {
		return $this->owner->getCommentsOption('required_permission');
	}

	public function canPost() {
		Deprecation::notice('2.0', 'Use canPostComment instead');
		return $this->canPostComment();
	}

	/**
	 * Determine if a user can post comments on this item
	 *
	 * @param Member $member Member to check
	 *
	 * @return boolean
	 */
	public function canPostComment($member = null) {
		// Deny if not enabled for this object
		if(!$this->owner->CommentsEnabled) return false;

		// Check if member is required
		$requireLogin = $this->owner->CommentsRequireLogin;
		if(!$requireLogin) return true;

		// Check member is logged in
		$member = $member ?: Member::currentUser();
		if(!$member) return false;

		// If member required check permissions
		$requiredPermission = $this->owner->PostingRequiredPermission;
		if($requiredPermission && !Permission::checkMember($member, $requiredPermission)) return false;

		return true;
	}

	/**
	 * Determine if this member can moderate comments in the CMS
	 *
	 * @param Member $member
	 *
	 * @return boolean
	 */
	public function canModerateComments($member = null) {
		return $this->owner->canEdit($member);
	}

	public function getRssLink() {
		Deprecation::notice('2.0', 'Use getCommentRSSLink instead');
		return $this->getCommentRSSLink();
	}

	/**
	 * Gets the RSS link to all comments
	 *
	 * @return string
	 */
	public function getCommentRSSLink() {
		return Controller::join_links(Director::baseURL(), 'CommentingController/rss');
	}

	public function getRssLinkPage() {
		Deprecation::notice('2.0', 'Use getCommentRSSLinkPage instead');
		return $this->getCommentRSSLinkPage();
	}

	/**
	 * Get the RSS link to all comments on this page
	 *
	 * @return string
	 */
	public function getCommentRSSLinkPage() {
		return Controller::join_links(
			$this->getCommentRSSLink(), $this->ownerBaseClass, $this->owner->ID
		);
	}

	/**
	 * Comments interface for the front end. Includes the CommentAddForm and the composition
	 * of the comments display.
	 *
	 * To customize the html see templates/CommentInterface.ss or extend this function with
	 * your own extension.
	 *
	 * @todo Cleanup the passing of all this configuration based functionality
	 *
	 * @see  docs/en/Extending
	 */
	public function CommentsForm() {
		// Check if enabled
		$enabled = $this->getCommentsEnabled();
		if($enabled && $this->owner->getCommentsOption('include_js')) {
			Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
			Requirements::javascript(THIRDPARTY_DIR . '/jquery-validate/lib/jquery.form.js');
			Requirements::javascript(THIRDPARTY_DIR . '/jquery-validate/jquery.validate.pack.js');
			Requirements::javascript('comments/javascript/CommentsInterface.js');
		}

		$controller = CommentingController::create();
		$controller->setOwnerRecord($this->owner);
		$controller->setBaseClass($this->ownerBaseClass);
		$controller->setOwnerController(Controller::curr());

		$moderatedSubmitted = Session::get('CommentsModerated');
		Session::clear('CommentsModerated');

		$form = ($enabled) ? $controller->CommentsForm() : false;

		// a little bit all over the show but to ensure a slightly easier upgrade for users
		// return back the same variables as previously done in comments
		return $this
			->owner
			->customise(array(
				'AddCommentForm' => $form,
				'ModeratedSubmitted' => $moderatedSubmitted,
			))
			->renderWith('CommentsInterface');
	}

	/**
	 * Returns whether this extension instance is attached to a {@link SiteTree} object
	 *
	 * @return bool
	 */
	public function attachedToSiteTree() {
		$class = $this->ownerBaseClass;

		return (is_subclass_of($class, 'SiteTree')) || ($class == 'SiteTree');
	}

	/**
	 * @deprecated 1.0 Please use {@link CommentsExtension->CommentsForm()}
	 */
	public function PageComments() {
		// This method is very commonly used, don't throw a warning just yet
		Deprecation::notice('1.0', '$PageComments is deprecated. Please use $CommentsForm');
		return $this->CommentsForm();
	}

	/**
	 * Get the commenting option for this object
	 *
	 * This can be overridden in any instance or extension to customise the option available
	 *
	 * @param string $key
	 *
	 * @return mixed Result if the setting is available, or null otherwise
	 */
	public function getCommentsOption($key) {
		$settings = $this->owner // In case singleton is called on the extension directly
			? $this->owner->config()->comments
			: Config::inst()->get(__CLASS__, 'comments');
		$value = null;
		if(isset($settings[$key])) $value = $settings[$key];

		// To allow other extensions to customise this option
		if($this->owner) $this->owner->extend('updateCommentsOption', $key, $value);
		return $value;
	}

	/**
	 * Add moderation functions to the current fieldlist
	 *
	 * @param FieldList $fields
	 */
	protected function updateModerationFields(FieldList $fields) {
		$commentsConfig = CommentsGridFieldConfig::create();

		$newComments = $this->owner->AllComments()->filter('Moderated', 0);

		$newGrid = new GridField(
			'NewComments',
			_t('CommentsAdmin.NewComments', 'Unmoderated'),
			$newComments,
			$commentsConfig
		);

		$approvedComments = $this->owner->AllComments()->filter('Moderated', 1)->filter('IsSpam', 0);

		$approvedGrid = new GridField(
			'ApprovedComments',
			_t('CommentsAdmin.Comments', 'Displayed'),
			$approvedComments,
			$commentsConfig
		);

		$spamComments = $this->owner->AllComments()->filter('Moderated', 1)->filter('IsSpam', 1);

		$spamGrid = new GridField(
			'SpamComments',
			_t('CommentsAdmin.SpamComments', 'Spam'),
			$spamComments,
			$commentsConfig
		);

		$newCount = '(' . count($newComments) . ')';
		$approvedCount = '(' . count($approvedComments) . ')';
		$spamCount = '(' . count($spamComments) . ')';

		if($fields->hasTabSet()) {
			$tabs = new TabSet(
				'Comments',
				new Tab('CommentsNewCommentsTab', _t('CommentAdmin.NewComments', 'Unmoderated') . ' ' . $newCount,
					$newGrid
				),
				new Tab('CommentsCommentsTab', _t('CommentAdmin.Comments', 'Displayed') . ' ' . $approvedCount,
					$approvedGrid
				),
				new Tab('CommentsSpamCommentsTab', _t('CommentAdmin.SpamComments', 'Spam') . ' ' . $spamCount,
					$spamGrid
				)
			);
			$fields->addFieldToTab('Root', $tabs);
		} else {
			$fields->push($newGrid);
			$fields->push($approvedGrid);
			$fields->push($spamGrid);
		}
	}

	public function updateCMSFields(FieldList $fields) {
		// Disable moderation if not permitted
		if($this->owner->canModerateComments()) {
			$this->updateModerationFields($fields);
		}

		// If this isn't a page we should merge the settings into the CMS fields
		if(!$this->attachedToSiteTree()) {
			$this->updateSettingsFields($fields);
		}
	}
}
