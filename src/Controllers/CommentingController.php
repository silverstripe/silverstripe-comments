<?php

namespace SilverStripe\Comments\Controllers;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Comments\Extensions\CommentsExtension;
use SilverStripe\Comments\Model\Comment;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\Control\Session;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * @package comments
 */
class CommentingController extends Controller
{
    /**
     * {@inheritDoc}
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
        'doPreviewComment'
    );

    /**
     * {@inheritDoc}
     */
    private static $url_handlers = array(
        'reply/$ParentCommentID//$ID/$OtherID' => 'reply',
    );

    /**
     * Fields required for this form
     *
     * @var array
     * @config
     */
    private static $required_fields = array(
        'Name',
        'Email',
        'Comment'
    );

    /**
     * Parent class this commenting form is for
     *
     * @var string
     */
    private $parentClass = '';

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
     * Set the parent class name to use
     *
     * @param string $class
     */
    public function setParentClass($class)
    {
        $this->parentClass = $this->encodeClassName($class);
    }

    /**
     * Get the parent class name used
     *
     * @return string
     */
    public function getParentClass()
    {
        return $this->decodeClassName($this->parentClass);
    }

    /**
     * Encode a fully qualified class name to a URL-safe version
     *
     * @param string $input
     * @return string
     */
    public function encodeClassName($input)
    {
        return str_replace('\\', '-', $input);
    }

    /**
     * Decode an "encoded" fully qualified class name back to its original
     *
     * @param string $input
     * @return string
     */
    public function decodeClassName($input)
    {
        return str_replace('-', '\\', $input);
    }

    /**
     * Set the record this controller is working on
     *
     * @param DataObject $record
     */
    public function setOwnerRecord($record)
    {
        $this->ownerRecord = $record;
    }

    /**
     * Get the record
     *
     * @return DataObject
     */
    public function getOwnerRecord()
    {
        return $this->ownerRecord;
    }

    /**
     * Set the parent controller
     *
     * @param Controller $controller
     */
    public function setOwnerController($controller)
    {
        $this->ownerController = $controller;
    }

    /**
     * Get the parent controller
     *
     * @return Controller
     */
    public function getOwnerController()
    {
        return $this->ownerController;
    }

    /**
     * Get the commenting option for the current state
     *
     * @param string $key
     * @return mixed Result if the setting is available, or null otherwise
     */
    public function getOption($key)
    {
        // If possible use the current record
        if ($record = $this->getOwnerRecord()) {
            return $record->getCommentsOption($key);
        }

        // Otherwise a singleton of that record
        if ($class = $this->getParentClass()) {
            return singleton($class)->getCommentsOption($key);
        }

        // Otherwise just use the default options
        return singleton(CommentsExtension::class)->getCommentsOption($key);
    }

    /**
     * Workaround for generating the link to this controller
     *
     * @param  string $action
     * @param  int    $id
     * @param  string $other
     * @return string
     */
    public function Link($action = '', $id = '', $other = '')
    {
        return Controller::join_links(Director::baseURL(), 'comments', $action, $id, $other);
    }

    /**
     * Outputs the RSS feed of comments
     *
     * @return HTMLText
     */
    public function rss()
    {
        return $this->getFeed($this->request)->outputToBrowser();
    }

    /**
     * Return an RSSFeed of comments for a given set of comments or all
     * comments on the website.
     *
     * @param HTTPRequest
     *
     * @return RSSFeed
     */
    public function getFeed(HTTPRequest $request)
    {
        $link = $this->Link('rss');
        $class = $this->decodeClassName($request->param('ID'));
        $id = $request->param('OtherID');

        // Support old pageid param
        if (!$id && !$class && ($id = $request->getVar('pageid'))) {
            $class = SiteTree::class;
        }

        $comments = Comment::get()->filter(array(
            'Moderated' => 1,
            'IsSpam' => 0,
        ));

        // Check if class filter
        if ($class) {
            if (!is_subclass_of($class, DataObject::class) || !$class::has_extension(CommentsExtension::class)) {
                return $this->httpError(404);
            }
            $this->setParentClass($class);
            $comments = $comments->filter('ParentClass', $class);
            $link = Controller::join_links($link, $this->encodeClassName($class));

            // Check if id filter
            if ($id) {
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
            'Title',
            'EscapedComment',
            'AuthorName'
        );
    }

    /**
     * Deletes a given {@link Comment} via the URL.
     */
    public function delete()
    {
        $comment = $this->getComment();
        if (!$comment) {
            return $this->httpError(404);
        }
        if (!$comment->canDelete()) {
            return Security::permissionFailure($this, 'You do not have permission to delete this comment');
        }
        if (!$comment->getSecurityToken()->checkRequest($this->request)) {
            return $this->httpError(400);
        }

        $comment->delete();

        return $this->request->isAjax()
            ? true
            : $this->redirectBack();
    }

    /**
     * Marks a given {@link Comment} as spam. Removes the comment from display
     */
    public function spam()
    {
        $comment = $this->getComment();
        if (!$comment) {
            return $this->httpError(404);
        }
        if (!$comment->canEdit()) {
            return Security::permissionFailure($this, 'You do not have permission to edit this comment');
        }
        if (!$comment->getSecurityToken()->checkRequest($this->request)) {
            return $this->httpError(400);
        }

        $comment->markSpam();
        return $this->renderChangedCommentState($comment);
    }

    /**
     * Marks a given {@link Comment} as ham (not spam).
     */
    public function ham()
    {
        $comment = $this->getComment();
        if (!$comment) {
            return $this->httpError(404);
        }
        if (!$comment->canEdit()) {
            return Security::permissionFailure($this, 'You do not have permission to edit this comment');
        }
        if (!$comment->getSecurityToken()->checkRequest($this->request)) {
            return $this->httpError(400);
        }

        $comment->markApproved();
        return $this->renderChangedCommentState($comment);
    }

    /**
     * Marks a given {@link Comment} as approved.
     */
    public function approve()
    {
        $comment = $this->getComment();
        if (!$comment) {
            return $this->httpError(404);
        }
        if (!$comment->canEdit()) {
            return Security::permissionFailure($this, 'You do not have permission to approve this comment');
        }
        if (!$comment->getSecurityToken()->checkRequest($this->request)) {
            return $this->httpError(400);
        }
        $comment->markApproved();
        return $this->renderChangedCommentState($comment);
    }

    /**
     * Redirect back to referer if available, ensuring that only site URLs
     * are allowed to avoid phishing.  If it's an AJAX request render the
     * comment in it's new state
     */
    private function renderChangedCommentState($comment)
    {
        $referer = $this->request->getHeader('Referer');

        // Render comment using AJAX
        if ($this->request->isAjax()) {
            return $comment->renderWith('Includes/CommentsInterface_singlecomment');
        } else {
            // Redirect to either the comment or start of the page
            if (empty($referer)) {
                return $this->redirectBack();
            } else {
                // Redirect to the comment, but check for phishing
                $url = $referer . '#comment-' . $comment->ID;
                // absolute redirection URLs not located on this site may cause phishing
                if (Director::is_site_url($url)) {
                    return $this->redirect($url);
                } else {
                    return false;
                }
            }
        }
    }

    /**
     * Returns the comment referenced in the URL (by ID). Permission checking
     * should be done in the callee.
     *
     * @return Comment|false
     */
    public function getComment()
    {
        $id = isset($this->urlParams['ID']) ? $this->urlParams['ID'] : false;
        if ($id) {
            $comment = Comment::get()->byId($id);
            if ($comment) {
                $this->fallbackReturnURL = $comment->Link();
                return $comment;
            }
        }

        return false;
    }

    /**
     * Create a reply form for a specified comment
     *
     * @param  Comment $comment
     * @return Form
     */
    public function ReplyForm($comment)
    {
        // Enables multiple forms with different names to use the same handler
        $form = $this->CommentsForm();
        $form->setName('ReplyForm_' . $comment->ID);
        $form->addExtraClass('reply-form');

        // Load parent into reply form
        $form->loadDataFrom(array(
            'ParentCommentID' => $comment->ID
        ));

        // Customise action
        $form->setFormAction($this->Link('reply', $comment->ID));

        $this->extend('updateReplyForm', $form);
        return $form;
    }


    /**
     * Request handler for reply form.
     * This method will disambiguate multiple reply forms in the same method
     *
     * @param  HTTPRequest $request
     * @throws HTTPResponse_Exception
     */
    public function reply(HTTPRequest $request)
    {
        // Extract parent comment from reply and build this way
        if ($parentID = $request->param('ParentCommentID')) {
            $comment = DataObject::get_by_id(Comment::class, $parentID, true);
            if ($comment) {
                return $this->ReplyForm($comment);
            }
        }
        return $this->httpError(404);
    }

    /**
     * Post a comment form
     *
     * @return Form
     */
    public function CommentsForm()
    {
        $usePreview = $this->getOption('use_preview');

        $nameRequired = _t('CommentInterface.YOURNAME_MESSAGE_REQUIRED', 'Please enter your name');
        $emailRequired = _t('CommentInterface.EMAILADDRESS_MESSAGE_REQUIRED', 'Please enter your email address');
        $emailInvalid = _t('CommentInterface.EMAILADDRESS_MESSAGE_EMAIL', 'Please enter a valid email address');
        $urlInvalid = _t('CommentInterface.COMMENT_MESSAGE_URL', 'Please enter a valid URL');
        $commentRequired = _t('CommentInterface.COMMENT_MESSAGE_REQUIRED', 'Please enter your comment');

        $fields = new FieldList(
            $dataFields = new CompositeField(
                // Name
                $a = TextField::create('Name', _t('CommentInterface.YOURNAME', 'Your name'))
                    ->setCustomValidationMessage($nameRequired)
                    ->setAttribute('data-msg-required', $nameRequired),
                // Email
                EmailField::create(
                    'Email',
                    _t('CommentingController.EMAILADDRESS', 'Your email address (will not be published)')
                )
                    ->setCustomValidationMessage($emailRequired)
                    ->setAttribute('data-msg-required', $emailRequired)
                    ->setAttribute('data-msg-email', $emailInvalid)
                    ->setAttribute('data-rule-email', true),
                // Url
                TextField::create('URL', _t('CommentingController.WEBSITEURL', 'Your website URL'))
                    ->setAttribute('data-msg-url', $urlInvalid)
                    ->setAttribute('data-rule-url', true),
                // Comment
                TextareaField::create('Comment', _t('CommentingController.COMMENTS', 'Comments'))
                    ->setCustomValidationMessage($commentRequired)
                    ->setAttribute('data-msg-required', $commentRequired)
            ),
            HiddenField::create('ParentID'),
            HiddenField::create('ParentClassName'),
            HiddenField::create('ReturnURL'),
            HiddenField::create('ParentCommentID')
        );

        // Preview formatted comment. Makes most sense when shortcodes or
        // limited HTML is allowed. Populated by JS/Ajax.
        if ($usePreview) {
            $fields->insertAfter(
                ReadonlyField::create('PreviewComment', _t('CommentInterface.PREVIEWLABEL', 'Preview'))
                    ->setAttribute('style', 'display: none'), // enable through JS
                'Comment'
            );
        }

        $dataFields->addExtraClass('data-fields');

        // save actions
        $actions = new FieldList(
            $postAction = new FormAction('doPostComment', _t('CommentInterface.POST', 'Post'))
        );
        if ($usePreview) {
            $actions->push(
                FormAction::create('doPreviewComment', _t('CommentInterface.PREVIEW', 'Preview'))
                    ->addExtraClass('action-minor')
                    ->setAttribute('style', 'display: none') // enable through JS
            );
        }

        // required fields for server side
        $required = new RequiredFields($this->config()->required_fields);

        // create the comment form
        $form = new Form($this, 'CommentsForm', $fields, $actions, $required);

        // if the record exists load the extra required data
        if ($record = $this->getOwnerRecord()) {
            // Load member data
            $member = Member::currentUser();
            if (($record->CommentsRequireLogin || $record->PostingRequiredPermission) && $member) {
                $fields = $form->Fields();

                $fields->removeByName('Name');
                $fields->removeByName('Email');
                $fields->insertBefore(
                    new ReadonlyField(
                        'NameView',
                        _t('CommentInterface.YOURNAME', 'Your name'),
                        $member->getName()
                    ),
                    'URL'
                );
                $fields->push(new HiddenField('Name', '', $member->getName()));
                $fields->push(new HiddenField('Email', '', $member->Email));
            }

            // we do not want to read a new URL when the form has already been submitted
            // which in here, it hasn't been.
            $form->loadDataFrom(array(
                'ParentID'        => $record->ID,
                'ReturnURL'       => $this->request->getURL(),
                'ParentClassName' => $this->getParentClass()
            ));
        }

        // Set it so the user gets redirected back down to the form upon form fail
        $form->setRedirectToFormOnValidationError(true);

        // load any data from the cookies
        if ($data = Cookie::get('CommentsForm_UserData')) {
            $data = Convert::json2array($data);

            $form->loadDataFrom(array(
                'Name'  => isset($data['Name']) ? $data['Name'] : '',
                'URL'   => isset($data['URL']) ? $data['URL'] : '',
                'Email' => isset($data['Email']) ? $data['Email'] : ''
            ));
            // allow previous value to fill if comment not stored in cookie (i.e. validation error)
            $prevComment = Cookie::get('CommentsForm_Comment');
            if ($prevComment && $prevComment != '') {
                $form->loadDataFrom(array('Comment' => $prevComment));
            }
        }

        if (!empty($member)) {
            $form->loadDataFrom($member);
        }

        // hook to allow further extensions to alter the comments form
        $this->extend('alterCommentForm', $form);

        return $form;
    }

    /**
     * Process which creates a {@link Comment} once a user submits a comment from this form.
     *
     * @param  array $data
     * @param  Form $form
     * @return HTTPResponse
     */
    public function doPostComment($data, $form)
    {
        // Load class and parent from data
        if (isset($data['ParentClassName'])) {
            $this->setParentClass($data['ParentClassName']);
        }
        if (isset($data['ParentID']) && ($class = $this->getParentClass())) {
            $this->setOwnerRecord($class::get()->byID($data['ParentID']));
        }
        if (!$this->getOwnerRecord()) {
            return $this->httpError(404);
        }

        // cache users data
        Cookie::set('CommentsForm_UserData', Convert::raw2json($data));
        Cookie::set('CommentsForm_Comment', $data['Comment']);

        // extend hook to allow extensions. Also see onAfterPostComment
        $this->extend('onBeforePostComment', $form);

        // If commenting can only be done by logged in users, make sure the user is logged in
        if (!$this->getOwnerRecord()->canPostComment()) {
            return Security::permissionFailure(
                $this,
                _t(
                    'CommentingController.PERMISSIONFAILURE',
                    "You're not able to post comments to this page. Please ensure you are logged in and have an "
                    . 'appropriate permission level.'
                )
            );
        }

        if ($member = Member::currentUser()) {
            $form->Fields()->push(new HiddenField('AuthorID', 'Author ID', $member->ID));
        }

        // What kind of moderation is required?
        switch ($this->getOwnerRecord()->ModerationRequired) {
            case 'Required':
                $requireModeration = true;
                break;
            case 'NonMembersOnly':
                $requireModeration = empty($member);
                break;
            case 'None':
            default:
                $requireModeration = false;
                break;
        }

        $comment = new Comment();
        $form->saveInto($comment);

        $comment->ParentID = $data['ParentID'];
        $comment->ParentClass = $data['ParentClassName'];

        $comment->AllowHtml = $this->getOption('html_allowed');
        $comment->Moderated = !$requireModeration;

        // Save into DB, or call pre-save hooks to give accurate preview
        $usePreview = $this->getOption('use_preview');
        $isPreview = $usePreview && !empty($data['IsPreview']);
        if ($isPreview) {
            $comment->extend('onBeforeWrite');
        } else {
            $comment->write();

            // extend hook to allow extensions. Also see onBeforePostComment
            $this->extend('onAfterPostComment', $comment);
        }

        // we want to show a notification if comments are moderated
        if ($requireModeration && !$comment->IsSpam) {
            Session::set('CommentsModerated', 1);
        }

        // clear the users comment since it passed validation
        Cookie::set('CommentsForm_Comment', false);

        // Find parent link
        if (!empty($data['ReturnURL'])) {
            $url = $data['ReturnURL'];
        } elseif ($parent = $comment->Parent()) {
            $url = $parent->Link();
        } else {
            return $this->redirectBack();
        }

        // Given a redirect page exists, attempt to link to the correct anchor
        if ($comment->IsSpam) {
            // Link to the form with the error message contained
            $hash = $form->FormName();
        } elseif (!$comment->Moderated) {
            // Display the "awaiting moderation" text
            $hash = 'moderated';
        } else {
            // Link to the moderated, non-spam comment
            $hash = $comment->Permalink();
        }

        return $this->redirect(Controller::join_links($url, "#{$hash}"));
    }

    /**
     * @param  array $data
     * @param  Form $form
     * @return HTTPResponse
     */
    public function doPreviewComment($data, $form)
    {
        $data['IsPreview'] = 1;

        return $this->doPostComment($data, $form);
    }

    /**
     * @return HTTPResponse|false
     */
    public function redirectBack()
    {
        // Don't cache the redirect back ever
        HTTP::set_cache_age(0);

        $url = null;

        // In edge-cases, this will be called outside of a handleRequest() context; in that case,
        // redirect to the homepage - don't break into the global state at this stage because we'll
        // be calling from a test context or something else where the global state is inappropraite
        if ($this->request) {
            if ($this->request->requestVar('BackURL')) {
                $url = $this->request->requestVar('BackURL');
            } elseif ($this->request->isAjax() && $this->request->getHeader('X-Backurl')) {
                $url = $this->request->getHeader('X-Backurl');
            } elseif ($this->request->getHeader('Referer')) {
                $url = $this->request->getHeader('Referer');
            }
        }

        if (!$url) {
            $url = $this->fallbackReturnURL;
        }
        if (!$url) {
            $url = Director::baseURL();
        }

        // absolute redirection URLs not located on this site may cause phishing
        if (Director::is_site_url($url)) {
            return $this->redirect($url);
        } else {
            return false;
        }
    }
}
