<?php

namespace SilverStripe\Comments\Model\Comment;

use SilverStripe\Control\Controller;
use SilverStripe\Security\Member;
use SilverStripe\Security\RandomGenerator;

/**
 * Provides the ability to generate cryptographically secure tokens for comment moderation
 */
class SecurityToken
{
    /**
     * @var string
     */
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
