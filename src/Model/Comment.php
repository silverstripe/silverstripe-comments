<?php

/**
 * Represents a single comment object.
 *
 * @property string  $Name
 * @property string  $Comment
 * @property string  $Email
 * @property string  $URL
 * @property string  $BaseClass
 * @property boolean $Moderated
 * @property boolean $IsSpam      True if the comment is known as spam
 * @property integer $ParentID    ID of the parent page / dataobject
 * @property boolean $AllowHtml   If true, treat $Comment as HTML instead of plain text
 * @property string  $SecretToken Secret admin token required to provide moderation links between sessions
 * @property integer $Depth       Depth of this comment in the nested chain
 *
 * @method HasManyList ChildComments() List of child comments
 * @method Member Author() Member object who created this comment
 * @method Comment ParentComment() Parent comment this is a reply to
 * @package comments
 */
class Comment extends DataObject
{

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

    private static $has_one = array(
        "Author" => "Member",
        "ParentComment" => "Comment",
    );

    private static $has_many = array(
        "ChildComments"    => "Comment"
    );

    private static $default_sort = '"Created" DESC';

    private static $defaults = array(
        'Moderated' => 0,
        'IsSpam' => 0,
    );

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

    private static $searchable_fields = array(
        'Name',
        'Email',
        'Comment',
        'Created',
        'BaseClass',
    );

    private static $summary_fields = array(
        'Name' => 'Submitted By',
        'Email' => 'Email',
        'Comment.LimitWordCount' => 'Comment',
        'Created' => 'Date Posted',
        'ParentTitle' => 'Post',
        'IsSpam' => 'Is Spam',
    );

    private static $field_labels = array(
        'Author' => 'Author Member',
    );

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Sanitize HTML, because its expected to be passed to the template unescaped later
        if ($this->AllowHtml) {
            $this->Comment = $this->purifyHtml($this->Comment);
        }

        // Check comment depth
        $this->updateDepth();
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        // Delete all children
        foreach ($this->ChildComments() as $comment) {
            $comment->delete();
        }
    }

    /**
     * @return Comment_SecurityToken
     */
    public function getSecurityToken()
    {
        return Injector::inst()->createWithArgs('Comment_SecurityToken', array($this));
    }

    /**
     * Migrates the old {@link PageComment} objects to {@link Comment}
     */
    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        if (DB::getConn()->hasTable('PageComment')) {
            $comments = DB::query('SELECT * FROM "PageComment"');

            if ($comments) {
                while ($pageComment = $comments->next()) {
                    // create a new comment from the older page comment
                    $comment = new Comment();
                    $comment->update($pageComment);

                    // set the variables which have changed
                    $comment->BaseClass = 'SiteTree';
                    $comment->URL = (isset($pageComment['CommenterURL'])) ? $pageComment['CommenterURL'] : '';
                    if ((int) $pageComment['NeedsModeration'] == 0) {
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
     * Return a link to this comment
     *
     * @param string $action
     *
     * @return string link to this comment.
     */
    public function Link($action = '')
    {
        if ($parent = $this->getParent()) {
            return $parent->Link($action) . '#' . $this->Permalink();
        }
    }

    /**
     * Returns the permalink for this {@link Comment}. Inserted into
     * the ID tag of the comment
     *
     * @return string
     */
    public function Permalink()
    {
        $prefix = $this->getOption('comment_permalink_prefix');
        return $prefix . $this->ID;
    }

    /**
     * Translate the form field labels for the CMS administration
     *
     * @param boolean $includerelations
     *
     * @return array
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);

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
     * Get the commenting option
     *
     * @param string $key
     *
     * @return mixed Result if the setting is available, or null otherwise
     */
    public function getOption($key)
    {
        // If possible use the current record
        $record = $this->getParent();

        if (!$record && $this->BaseClass) {
            // Otherwise a singleton of that record
            $record = singleton($this->BaseClass);
        } elseif (!$record) {
            // Otherwise just use the default options
            $record = singleton('CommentsExtension');
        }

        return ($record->hasMethod('getCommentsOption')) ? $record->getCommentsOption($key) : null;
    }

    /**
     * Returns the parent {@link DataObject} this comment is attached too
     *
     * @return DataObject
     */
    public function getParent()
    {
        return $this->BaseClass && $this->ParentID
            ? DataObject::get_by_id($this->BaseClass, $this->ParentID, true)
            : null;
    }


    /**
     * Returns a string to help identify the parent of the comment
     *
     * @return string
     */
    public function getParentTitle()
    {
        if ($parent = $this->getParent()) {
            return $parent->Title ?: ($parent->ClassName . ' #' . $parent->ID);
        }
    }

    /**
     * Comment-parent classnames obviously vary, return the parent classname
     *
     * @return string
     */
    public function getParentClassName()
    {
        return $this->BaseClass;
    }

    public function castingHelper($field)
    {
        // Safely escape the comment
        if ($field === 'EscapedComment') {
            return $this->AllowHtml ? 'HTMLText' : 'Text';
        }
        return parent::castingHelper($field);
    }

    /**
     * Content to be safely escaped on the frontend
     *
     * @return string
     */
    public function getEscapedComment()
    {
        return $this->Comment;
    }

    /**
     * Return whether this comment is a preview (has not been written to the db)
     *
     * @return boolean
     */
    public function isPreview()
    {
        return !$this->exists();
    }

    /**
     * @todo needs to compare to the new {@link Commenting} configuration API
     *
     * @param Member $member
     *
     * @return bool
     */
    public function canCreate($member = null)
    {
        return false;
    }

    /**
     * Checks for association with a page, and {@link SiteTree->ProvidePermission}
     * flag being set to true.
     *
     * @param Member $member
     *
     * @return Boolean
     */
    public function canView($member = null)
    {
        $member = $this->getMember($member);

        $extended = $this->extendedCan('canView', $member);
        if ($extended !== null) {
            return $extended;
        }

        if (Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin')) {
            return true;
        }

        if ($parent = $this->getParent()) {
            return $parent->canView($member)
                && $parent->has_extension('CommentsExtension')
                && $parent->CommentsEnabled;
        }

        return false;
    }

    /**
     * Checks if the comment can be edited.
     *
     * @param null|int|Member $member
     *
     * @return Boolean
     */
    public function canEdit($member = null)
    {
        $member = $this->getMember($member);

        if (!$member) {
            return false;
        }

        $extended = $this->extendedCan('canEdit', $member);
        if ($extended !== null) {
            return $extended;
        }

        if (Permission::checkMember($member, 'CMS_ACCESS_CommentAdmin')) {
            return true;
        }

        if ($parent = $this->getParent()) {
            return $parent->canEdit($member);
        }

        return false;
    }

    /**
     * Checks if the comment can be deleted.
     *
     * @param null|int|Member $member
     *
     * @return Boolean
     */
    public function canDelete($member = null)
    {
        $member = $this->getMember($member);

        if (!$member) {
            return false;
        }

        $extended = $this->extendedCan('canDelete', $member);
        if ($extended !== null) {
            return $extended;
        }

        return $this->canEdit($member);
    }

    /**
     * Resolves Member object.
     *
     * @param Member|int|null $member
     * @return Member|null
     */
    protected function getMember($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }

        if (is_numeric($member)) {
            $member = DataObject::get_by_id('Member', $member, true);
        }

        return $member;
    }

    /**
     * Return the authors name for the comment
     *
     * @return string
     */
    public function getAuthorName()
    {
        if ($this->Name) {
            return $this->Name;
        } elseif ($author = $this->Author()) {
            return $author->getName();
        }
    }

    /**
     * Generate a secure admin-action link authorised for the specified member
     *
     * @param string $action An action on CommentingController to link to
     * @param Member $member The member authorised to invoke this action
     *
     * @return string
     */
    protected function actionLink($action, $member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }
        if (!$member) {
            return false;
        }

        $url = Controller::join_links(
            Director::baseURL(),
            'CommentingController',
            $action,
            $this->ID
        );

        // Limit access for this user
        $token = $this->getSecurityToken();
        return $token->addToUrl($url, $member);
    }

    /**
     * Link to delete this comment
     *
     * @param Member $member
     *
     * @return string
     */
    public function DeleteLink($member = null)
    {
        if ($this->canDelete($member)) {
            return $this->actionLink('delete', $member);
        }
    }

    /**
     * Link to mark as spam
     *
     * @param Member $member
     *
     * @return string
     */
    public function SpamLink($member = null)
    {
        if ($this->canEdit($member) && !$this->IsSpam) {
            return $this->actionLink('spam', $member);
        }
    }

    /**
     * Link to mark as not-spam (ham)
     *
     * @param Member $member
     *
     * @return string
     */
    public function HamLink($member = null)
    {
        if ($this->canEdit($member) && $this->IsSpam) {
            return $this->actionLink('ham', $member);
        }
    }

    /**
     * Link to approve this comment
     *
     * @param Member $member
     *
     * @return string
     */
    public function ApproveLink($member = null)
    {
        if ($this->canEdit($member) && !$this->Moderated) {
            return $this->actionLink('approve', $member);
        }
    }

    /**
     * Mark this comment as spam
     */
    public function markSpam()
    {
        $this->IsSpam = true;
        $this->Moderated = true;
        $this->write();
        $this->extend('afterMarkSpam');
    }

    /**
     * Mark this comment as approved
     */
    public function markApproved()
    {
        $this->IsSpam = false;
        $this->Moderated = true;
        $this->write();
        $this->extend('afterMarkApproved');
    }

    /**
     * Mark this comment as unapproved
     */
    public function markUnapproved()
    {
        $this->Moderated = false;
        $this->write();
        $this->extend('afterMarkUnapproved');
    }

    /**
     * @return string
     */
    public function SpamClass()
    {
        if ($this->IsSpam) {
            return 'spam';
        } elseif (!$this->Moderated) {
            return 'unmoderated';
        } else {
            return 'notspam';
        }
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $title = sprintf(_t('Comment.COMMENTBY', 'Comment by %s', 'Name'), $this->getAuthorName());

        if ($parent = $this->getParent()) {
            if ($parent->Title) {
                $title .= sprintf(' %s %s', _t('Comment.ON', 'on'), $parent->Title);
            }
        }

        return $title;
    }

    /*
     * Modify the default fields shown to the user
     */
    public function getCMSFields()
    {
        $commentField = $this->AllowHtml ? 'HtmlEditorField' : 'TextareaField';
        $fields = new FieldList(
            $this
                ->obj('Created')
                ->scaffoldFormField($this->fieldLabel('Created'))
                ->performReadonlyTransformation(),
            TextField::create('Name', $this->fieldLabel('Name')),
            $commentField::create('Comment', $this->fieldLabel('Comment')),
            EmailField::create('Email', $this->fieldLabel('Email')),
            TextField::create('URL', $this->fieldLabel('URL')),
            FieldGroup::create(array(
                CheckboxField::create('Moderated', $this->fieldLabel('Moderated')),
                CheckboxField::create('IsSpam', $this->fieldLabel('IsSpam')),
            ))
                ->setTitle(_t('Comment.OPTIONS', 'Options'))
                ->setDescription(_t(
                    'Comment.OPTION_DESCRIPTION',
                    'Unmoderated and spam comments will not be displayed until approved'
                ))
        );

        // Show member name if given
        if (($author = $this->Author()) && $author->exists()) {
            $fields->insertAfter(
                TextField::create('AuthorMember', $this->fieldLabel('Author'), $author->Title)
                    ->performReadonlyTransformation(),
                'Name'
            );
        }

        // Show parent comment if given
        if (($parent = $this->ParentComment()) && $parent->exists()) {
            $fields->push(new HeaderField(
                'ParentComment_Title',
                _t('Comment.ParentComment_Title', 'This comment is a reply to the below')
            ));
            // Created date
            // FIXME - the method setName in DatetimeField is not chainable, hence
            // the lack of chaining here
            $createdField = $parent
                    ->obj('Created')
                    ->scaffoldFormField($parent->fieldLabel('Created'));
            $createdField->setName('ParentComment_Created');
            $createdField->setValue($parent->Created);
            $createdField->performReadonlyTransformation();
            $fields->push($createdField);

            // Name (could be member or string value)
            $fields->push(
                $parent
                    ->obj('AuthorName')
                    ->scaffoldFormField($parent->fieldLabel('AuthorName'))
                    ->setName('ParentComment_AuthorName')
                    ->setValue($parent->getAuthorName())
                    ->performReadonlyTransformation()
            );

            // Comment body
            $fields->push(
                $parent
                    ->obj('EscapedComment')
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
     * @param  String $dirtyHtml
     *
     * @return String
     */
    public function purifyHtml($dirtyHtml)
    {
        $purifier = $this->getHtmlPurifierService();
        return $purifier->purify($dirtyHtml);
    }

    /**
     * @return HTMLPurifier (or anything with a "purify()" method)
     */
    public function getHtmlPurifierService()
    {
        $config = HTMLPurifier_Config::createDefault();
        $allowedElements = $this->getOption('html_allowed_elements');
        $config->set('HTML.AllowedElements', $allowedElements);

        // This injector cannot be set unless the 'p' element is allowed
        if (in_array('p', $allowedElements)) {
            $config->set('AutoFormat.AutoParagraph', true);
        }

        $config->set('AutoFormat.Linkify', true);
        $config->set('URI.DisableExternalResources', true);
        $config->set('Cache.SerializerPath', getTempFolder());
        return new HTMLPurifier($config);
    }

    /**
     * Calculate the Gravatar link from the email address
     *
     * @return string
     */
    public function Gravatar()
    {
        $gravatar = '';
        $use_gravatar = $this->getOption('use_gravatar');
        if ($use_gravatar) {
            $gravatar = 'http://www.gravatar.com/avatar/' . md5(strtolower(trim($this->Email)));
            $gravatarsize = $this->getOption('gravatar_size');
            $gravatardefault = $this->getOption('gravatar_default');
            $gravatarrating = $this->getOption('gravatar_rating');
            $gravatar .= '?s=' . $gravatarsize . '&d=' . $gravatardefault . '&r=' . $gravatarrating;
        }

        return $gravatar;
    }

    /**
     * Determine if replies are enabled for this instance
     *
     * @return boolean
     */
    public function getRepliesEnabled()
    {
        // Check reply option
        if (!$this->getOption('nested_comments')) {
            return false;
        }

        // Check if depth is limited
        $maxLevel = $this->getOption('nested_depth');
        $notSpam = ($this->SpamClass() == 'notspam');
        return $notSpam && (!$maxLevel || $this->Depth < $maxLevel);
    }

    /**
     * Returns the list of all replies
     *
     * @return SS_List
     */
    public function AllReplies()
    {
        // No replies if disabled
        if (!$this->getRepliesEnabled()) {
            return new ArrayList();
        }

        // Get all non-spam comments
        $order = $this->getOption('order_replies_by')
            ?: $this->getOption('order_comments_by');
        $list = $this
            ->ChildComments()
            ->sort($order);

        $this->extend('updateAllReplies', $list);
        return $list;
    }

    /**
     * Returns the list of replies, with spam and unmoderated items excluded, for use in the frontend
     *
     * @return SS_List
     */
    public function Replies()
    {
        // No replies if disabled
        if (!$this->getRepliesEnabled()) {
            return new ArrayList();
        }
        $list = $this->AllReplies();

        // Filter spam comments for non-administrators if configured
        $parent = $this->getParent();
        $showSpam = $this->getOption('frontend_spam') && $parent && $parent->canModerateComments();
        if (!$showSpam) {
            $list = $list->filter('IsSpam', 0);
        }

        // Filter un-moderated comments for non-administrators if moderation is enabled
        $showUnmoderated = $parent && (
            ($parent->ModerationRequired === 'None')
            || ($this->getOption('frontend_moderation') && $parent->canModerateComments())
        );
        if (!$showUnmoderated) {
            $list = $list->filter('Moderated', 1);
        }

        $this->extend('updateReplies', $list);
        return $list;
    }

    /**
     * Returns the list of replies paged, with spam and unmoderated items excluded, for use in the frontend
     *
     * @return PaginatedList
     */
    public function PagedReplies()
    {
        $list = $this->Replies();

        // Add pagination
        $list = new PaginatedList($list, Controller::curr()->getRequest());
        $list->setPaginationGetVar('repliesstart'.$this->ID);
        $list->setPageLength($this->getOption('comments_per_page'));

        $this->extend('updatePagedReplies', $list);
        return $list;
    }

    /**
     * Generate a reply form for this comment
     *
     * @return Form
     */
    public function ReplyForm()
    {
        // Ensure replies are enabled
        if (!$this->getRepliesEnabled()) {
            return null;
        }

        // Check parent is available
        $parent = $this->getParent();
        if (!$parent || !$parent->exists()) {
            return null;
        }

        // Build reply controller
        $controller = CommentingController::create();
        $controller->setOwnerRecord($parent);
        $controller->setBaseClass($parent->ClassName);
        $controller->setOwnerController(Controller::curr());

        return $controller->ReplyForm($this);
    }

    /**
     * Refresh of this comment in the hierarchy
     */
    public function updateDepth()
    {
        $parent = $this->ParentComment();
        if ($parent && $parent->exists()) {
            $parent->updateDepth();
            $this->Depth = $parent->Depth + 1;
        } else {
            $this->Depth = 1;
        }
    }
}


/**
 * Provides the ability to generate cryptographically secure tokens for comment moderation
 */
class Comment_SecurityToken
{

    private $secret = null;

    /**
     * @param Comment $comment Comment to generate this token for
     */
    public function __construct($comment)
    {
        if (!$comment->SecretToken) {
            $comment->SecretToken = $this->generate();
            $comment->write();
        }
        $this->secret = $comment->SecretToken;
    }

    /**
     * Generate the token for the given salt and current secret
     *
     * @param string $salt
     *
     * @return string
     */
    protected function getToken($salt)
    {
        return hash_pbkdf2('sha256', $this->secret, $salt, 1000, 30);
    }

    /**
     * Get the member-specific salt.
     *
     * The reason for making the salt specific to a user is that it cannot be "passed in" via a
     * querystring, requiring the same user to be present at both the link generation and the
     * controller action.
     *
     * @param string $salt   Single use salt
     * @param Member $member Member object
     *
     * @return string Generated salt specific to this member
     */
    protected function memberSalt($salt, $member)
    {
        // Fallback to salting with ID in case the member has not one set
        return $salt . ($member->Salt ?: $member->ID);
    }

    /**
     * @param string $url    Comment action URL
     * @param Member $member Member to restrict access to this action to
     *
     * @return string
     */
    public function addToUrl($url, $member)
    {
        $salt = $this->generate(15); // New random salt; Will be passed into url
        // Generate salt specific to this member
        $memberSalt = $this->memberSalt($salt, $member);
        $token = $this->getToken($memberSalt);
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
     * @return boolean
     */
    public function checkRequest($request)
    {
        $member = Member::currentUser();
        if (!$member) {
            return false;
        }

        $salt = $request->getVar('s');
        $memberSalt = $this->memberSalt($salt, $member);
        $token = $this->getToken($memberSalt);

        // Ensure tokens match
        return $token === $request->getVar('t');
    }


    /**
     * Generates new random key
     *
     * @param integer $length
     *
     * @return string
     */
    protected function generate($length = null)
    {
        $generator = new RandomGenerator();
        $result = $generator->randomToken('sha256');
        if ($length !== null) {
            return substr($result, 0, $length);
        }
        return $result;
    }
}
