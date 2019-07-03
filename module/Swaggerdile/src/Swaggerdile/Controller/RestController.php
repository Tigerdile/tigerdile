<?php
/*
 * REST Controller
 *
 * A very basic rest controller.  I should probably make a real API
 * at some point, but I just need something basic right now.
 *
 * @author sconley
 */

namespace Swaggerdile\Controller;

use Swaggerdile\Controller;


class RestController extends Controller
{
    /*
     * OVerride getResponse to make a REST-friendly request.
     */
    public function getResponse()
    {
        $response = parent::getResponse();

        // Well behaved headers
        $headers = new \Zend\Http\Headers();
        $headers->addHeaderLine('Content-Type: application/json');
        $response->setHeaders($headers);

        return $response;
    }

    /*
     * Add a favorite
     *
     * POST parameters:
     * channelId integer
     */
    public function addfavoriteAction()
    {
        $response = $this->getResponse();
        $request = $this->getRequest();
        $user = $this->getUser();
        $channelId = (int)$request->getPost('channelId', 0);

        // Data cleansing
        if((!$request->isPost()) || (!is_object($user)) ||
           (!$channelId)) {
            $response->setContent('{"status": 0}');
            return $response;
        }

        // Add a subscription for this channel.
        $user->addStreamSubscription($channelId);

        // Done!
        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Delete a favorite
     *
     * POST parameters:
     * channelId integer
     */
    public function removefavoriteAction()
    {
        $response = $this->getResponse();
        $request = $this->getRequest();
        $user = $this->getUser();
        $channelId = (int)$request->getPost('channelId', 0);

        // Data cleansing
        if((!$request->isPost()) || (!is_object($user)) ||
           (!$channelId)) {
            $response->setContent('{"status": 0}');
            return $response;
        }

        // Add a subscription for this channel.
        $user->removeStreamSubscription($channelId);

        // Done!
        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Set SFW
     *
     * POST parameters:
     * * channelId integer
     * * sfw integer (0 = no, 1 = yes)
     */
    public function setsfwAction()
    {
        $response = $this->getResponse();
        $request = $this->getRequest();
        $user = $this->getUser();
        $channelId = (int)$request->getPost('channelId', 0);
        $isSfw = (int)$request->getPost('sfw', 0);

        // Data cleansing
        if((!$request->isPost()) || (!is_object($user)) ||
           (!$channelId)) {
            $response->setContent('{"status": 0}');
            return $response;
        }

        // Try to load the channel
        $stream = $this->getModel()->get('Profiles')->fetchById($channelId);

        if((!$stream) || ($stream->getOwnerId() != $user->getId())) {
            $response->setContent('{"status": 0}');
            return $response;
        }

        // Set NSFW
        $this->getModel()->get('Profiles')->update(
                    array(
                        'is_nsfw' => (!$isSfw),
                    ),
                    array(
                        'id' => $stream->getId(),
                    )
        );

        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Get list of subscribers to one's own channel.  Requires
     * admin or owner perms.
     *
     * Parameter is channelId
     */
    public function subscribersAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId',
                            $request->getQuery('channelId', 0));

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        $response = $this->getResponse();

        // push an array back
        $subs = $this->getModel()
                     ->get('ProfileUsers')
                     ->enableArrayReturn()
                     ->fetchProfileUsers(
                        $profile,
                        array(
                            \Swaggerdile\Model\ProfileUsers::SUBSCRIBER,
                            \Swaggerdile\Model\ProfileUsers::BANNED_SUBSCRIBER,
                        )
        );

        $response->setContent(json_encode($subs));
        return $response;
    }

    /*
     * Delete public image.  'type' is the type of the image to delete.
     *
     * Parameter: type, string image type
     * Parameter: profileId, string profile ID -- we will verify
     *            this.
     */
    public function deletePublicImageAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('profileId',
                            $request->getQuery('profileId', 0));

        $type = $request->getPost('type',
                            $request->getQuery('type', ''));

        // Required
        if(!strlen($type)) {
            return $this->notFoundAction();
        }

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        // Run a delete
        \Swaggerdile\Media::deletePublicImageType($profile, $type);

        $response = $this->getResponse();
        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Ban a subscriber
     *
     * @param channelId the channel we're operating on.
     * @param userId the user to ban.
     *
     * Returns standard status response.
     */
    public function bansubscriberAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId',
                            $request->getQuery('channelId', 0));

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        // Make sure target user is actually a user.
        $targetUserId = (int)$request->getPost('userId',
                                $request->getQuery('userId', 0));

        $targetUser = $this->getModel()->get('User')->fetchById($targetUserId);

        if(!is_object($targetUser)) {
            return $this->notFoundAction();
        }

        $response = $this->getResponse();

        // Check if already banned.
        $puTable = $this->getModel()->get('ProfileUsers');

        $res = $puTable->select(array(
                                'user_id' => $targetUserId,
                                'profile_id' => $channelId,
                                'type_id' => \Swaggerdile\Model\ProfileUsers::BANNED_SUBSCRIBER,
        ));

        // Nothing to do if already banned
        if(!count($res)) {
            // Add ban
            $puTable->insert(array(
                                'user_id' => $targetUserId,
                                'profile_id' => $channelId,
                                'type_id' => \Swaggerdile\Model\ProfileUsers::BANNED_SUBSCRIBER,
            ));
        }

        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Unban a subscriber
     *
     * @param channelId the channel we're operating on.
     * @param userId the user to ban.
     *
     * Returns standard status response.
     */
    public function unbansubscriberAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId',
                            $request->getQuery('channelId', 0));

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        // Make sure target user is actually a user.
        $targetUserId = (int)$request->getPost('userId',
                                $request->getQuery('userId', 0));

        $targetUser = $this->getModel()->get('User')->fetchById($targetUserId);

        if(!is_object($targetUser)) {
            return $this->notFoundAction();
        }

        $response = $this->getResponse();

        // Check if already banned.
        $puTable = $this->getModel()->get('ProfileUsers');

        $res = $puTable->select(array(
                                'user_id' => $targetUserId,
                                'profile_id' => $channelId,
                                'type_id' => \Swaggerdile\Model\ProfileUsers::BANNED_SUBSCRIBER,
        ));

        // Nothing to do if not banned
        if(count($res)) {
            // Add ban
            $puTable->delete(array(
                                'user_id' => $targetUserId,
                                'profile_id' => $channelId,
                                'type_id' => \Swaggerdile\Model\ProfileUsers::BANNED_SUBSCRIBER,
            ));
        }

        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Send an email to subscribers.
     *
     * @param integer channelId
     * @param string subject
     * @param string email
     *
     * POST ONLY
     */
    public function emailAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId', 0);

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        // ready our response
        $response = $this->getResponse();

        // Make sure we have subject and message
        $subject = $request->getPost('subject', '');
        $message = $request->getPost('email', '');

        if((!strlen($subject)) || (!strlen($message))) {
            $response->setContent('{"status": 0}');
            return $response;
        }

        // Get our subscriber list, filtered out bans.
        $subs = $this->getModel()
                     ->get('ProfileUsers')
                     ->fetchProfileUsers(
                        $profile,
                        array(
                            \Swaggerdile\Model\ProfileUsers::SUBSCRIBER,
                        ),
                        array(
                            \Swaggerdile\Model\ProfileUsers::BANNED_SUBSCRIBER,
                        )
        );

        // if there's nobody, there's nothing to do
        if(!count($subs)) {
            $response->setContent('{"status": 1}');
            return $response;
        }

        // otherwise, send the mail
        $queueTable = $this->getModel()->get('EmailQueue');

        $headers = "Reply-To: support@tigerdile.com\nMIME-Version: 1.0\nContent-Type: text/html; charset=UTF-8";
        $message = <<<EOS
<!DOCTYPE html>
<html>
<body>
<p><strong>Stream announcement from {$user->getDisplayName()}:</strong></p>
{$message}
<p>This message was sent by a Tigerdile stream user.  You are getting
   this message because you have favorite'd to this user's stream channel.
   To unsubscribe, go to your user management screen:
   <a href="https://www.tigerdile.com/user-management">User Management</a>
</p>
<p>The email address that sent this message is monitored by an actual
   person, and you may also reply back with any problems or questions.
</p>
<p>This mail was sent by Tigerdile, LLC, PO Box 555, Brookline, NH, 03033 USA</p>
EOS
        ;

        $queueTable->beginTransaction();
        foreach($subs as $sub) {
            $queueTable->insert(array(
                            'content' => $message,
                            'send_to' => $sub->getUserEmail(),
                            'subject' => $subject,
                            'headers' => $headers,
            ));
        }
        $queueTable->commitTransaction();

        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Add a moderator
     *
     * POST parameters:
     * channelId integer
     * user string
     */
    public function addmoderatorAction()
    {
        $response = $this->getResponse();
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId', 0);
        $modUsername = $request->getPost('user', '');

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId())) ||
           (!strlen($modUsername))) {
            return $this->notFoundAction();
        }

        // Try to load the potential mod
        $modUser = $this->getModel()->get('User')->fetchByUserLogin($modUsername);

        if(!count($modUser)) {
            return $this->notFoundAction();
        }

        $modUser = $modUser[0];

        // ready our response
        $response = $this->getResponse();

        // Is there already a moderator for this channel?
        $ptTable = $this->getModel()->get('ProfileUsers');

        $alreadyMods = $ptTable->fetchByUserId_and_ProfileId_and_TypeId(
                            $modUser->getId(), $profile->getId(),
                            \Swaggerdile\Model\ProfileUsers::MODERATOR
        );

        if(count($alreadyMods)) {
            $response->setContent('{"status": 1}');
            return $response;
        }

        // Add a moderator for this channel.
        $ptTable->insert(array(
                    'user_id' => $modUser->getId(),
                    'profile_id' => $profile->getId(),
                    'type_id' => \Swaggerdile\Model\ProfileUsers::MODERATOR,
        ));

        // Update chat server
        $this->getLocator()->get('Chat')->refreshUser();

        // Done!
        $response->setContent("{\"status\": 1, \"user_id\": {$modUser->getId()}}");
        return $response;
    }

    /*
     * Add a chat user ban.  TODO: Combine code with addmoderatorß
     *
     * POST parameters:
     * channelId integer
     * user string
     */
    public function addchatbanAction()
    {
        $response = $this->getResponse();
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId', 0);
        $modUsername = $request->getPost('user', '');

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId())) ||
           (!strlen($modUsername))) {
            return $this->notFoundAction();
        }

        // Try to load the potential user
        $modUser = $this->getModel()->get('User')->fetchByUserLogin($modUsername);

        if(!count($modUser)) {
            return $this->notFoundAction();
        }

        $modUser = $modUser[0];

        // Are we already streaming with them?
        $ptTable = $this->getModel()->get('ProfileUsers');

        // Check it
        $alreadyMods = $ptTable->fetchByUserId_and_ProfileId_and_TypeId(
                            $modUser->getId(), $profile->getId(),
                            \Swaggerdile\Model\ProfileUsers::BANNED_USER
        );

        if(count($alreadyMods)) {
            $response->setContent('{"status": 1}');
            return $response;
        }

        // add the relationship.
        $ptTable->insert(array(
                    'user_id' => $modUser->getId(),
                    'profile_id' => $profile->getId(),
                    'type_id' => \Swaggerdile\Model\ProfileUsers::BANNED_USER,
        ));

        // Update chat server
        $this->getLocator()->get('Chat')->refreshUser();

        // Done!
        $response->setContent("{\"status\": 1, \"user_id\": {$modUser->getId()}}");
        return $response;
    }

    /*
     * Delete a moderator.  @TODO: Condense code with delete subscriber, etc.
     *
     * @param channelId the channel we're operating on.
     * @param userId the user to delete from mod list.
     *
     * Returns standard status response.
     */
    public function deletemoderatorAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId',
                            $request->getQuery('channelId', 0));

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        // Make sure target user is actually a user.
        $targetUserId = (int)$request->getPost('userId',
                                $request->getQuery('userId', 0));

        $targetUser = $this->getModel()->get('User')->fetchById($targetUserId);

        if(!is_object($targetUser)) {
            return $this->notFoundAction();
        }

        $response = $this->getResponse();

        // Is there already a moderator for this channel?
        $ptTable = $this->getModel()->get('ProfileUsers');

        // Just delete, no need to check
        $ptTable->delete(array(
                                'user_id' => $targetUserId,
                                'profile_id' => $channelId,
                                'type_id' => \Swaggerdile\Model\ProfileUsers::MODERATOR,
        ));

        // Update chat server
        $this->getLocator()->get('Chat')->refreshUser();

        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Delete a ban.  @TODO: Condense code with delete subscriber, etc.
     *
     * @param channelId the channel we're operating on.
     * @param userId the user to delete from mod list.
     *
     * Returns standard status response.
     */
    public function deletechatbanAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId',
                            $request->getQuery('channelId', 0));

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        // Make sure target user is actually a user.
        $targetUserId = (int)$request->getPost('userId',
                                $request->getQuery('userId', 0));

        $targetUser = $this->getModel()->get('User')->fetchById($targetUserId);

        if(!is_object($targetUser)) {
            return $this->notFoundAction();
        }

        $response = $this->getResponse();

        // Is there already a moderator for this channel?
        $ptTable = $this->getModel()->get('ProfileUsers');

        // Just delete, no need to check
        $ptTable->delete(array(
                                'user_id' => $targetUserId,
                                'profile_id' => $channelId,
                                'type_id' => \Swaggerdile\Model\ProfileUsers::BANNED_USER,
        ));

        // Update chat server
        $this->getLocator()->get('Chat')->refreshUser();

        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Get the multi-stream info for a given channel.
     *
     * @param int channelId to get multi-stream info for.
     *
     * Note: This is not considered secret info, so no permission
     * checks are done.
     */
    public function getmultistreamAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId',
                            $request->getQuery('channelId', 0));

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        if(!is_object($profile)) {
            return $this->notFoundAction();
        }

        // Load multi-stream info
        $retInfo = array(
            'status' => true,
            'multistream_chat_option' => $profile->getMultistreamChatOption(),
        );

        $joinedStreams = array();

        $msTable = $this->getModel()->get('Multistream');

        $otherStreams = $msTable->fetchAssociatedProfiles($profile->getId());

        foreach($otherStreams as $os) {
            $joinedStreams[] = array(
                    'id' => $os->getId(),
                    'name' => $os->getTitle(),
            );
        }

        $retInfo['streams'] = $joinedStreams;

        $response = $this->getResponse();
        $response->setContent(json_encode($retInfo));
        return $response;
    }

    /*
     * Add a streamer to the channel.
     *
     * @param int channelId to add to multi-stream
     * @param string user    user to add to multi-stream
     *
     */
    public function addmultistreamAction()
    {
        $response = $this->getResponse();
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId', 0);
        $modUsername = $request->getPost('user', '');

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId())) ||
           (!strlen($modUsername))) {
            return $this->notFoundAction();
        }

        // Try to load the potential user
        $modUser = $this->getModel()->get('User')->fetchByUserLogin($modUsername);

        if(!count($modUser)) {
            return $this->notFoundAction();
        }

        $modUser = $modUser[0];

        // Do they have profiles
        $profiles = $modUser->getProfiles();

        if(!count($profiles)) {
            return $this->notFoundAction();
        }

        $targetProfile = $profiles[0];

        // Are we already streaming with them?
        $msTable = $this->getModel()->get('Multistream');

        // Check it
        $alreadyMs = $msTable->fetchByFirstProfileId_and_SecondProfileId(
                        $profile->getId(), $targetProfile->getId()
        );

        if(count($alreadyMs)) {
            $response->setContent('{"status": 1}');
            return $response;
        }

        // add the relationship.
        $msTable->insert(array(
                    'first_profile_id' => $profile->getId(),
                    'second_profile_id' => $targetProfile->getId(),
                    'is_approved' => 1,
        ));

        // Done!
        $response->setContent("{\"status\": 1, \"user_id\": {$modUser->getId()}}");
        return $response;
    }

    /*
     * Delete a multistream participant.
     *
     * @param channelId the channel we're operating on.
     * @param profile ID to delete from the multi-stream
     *
     * Returns standard status response.
     */
    public function deletemultistreamAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId',
                            $request->getQuery('channelId', 0));

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        // Try to delete it
        $targetProfileId = (int)$request->getPost('profile',
                                $request->getQuery('profile', 0));


        $targetProfile = $this->getModel()->get('Profiles')->fetchById($targetProfileId);

        if(!is_object($targetProfile)) {
            return $this->notFoundAction();
        }

        $response = $this->getResponse();

        // Grab table
        $msTable = $this->getModel()->get('Multistream');

        // Just delete, no need to check
        $msTable->delete(array(
                                'first_profile_id' => (int)$channelId,
                                'second_profile_id' => (int)$targetProfileId,
        ));

        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Set your multi-stream chat
     *
     * @param channelId the channel we're operating on.
     * @param profile ID to set the chat
     *
     * Returns standard status response.
     */
    public function setmultichatAction()
    {
        $request = $this->getRequest();

        $channelId = (int)$request->getPost('channelId',
                            $request->getQuery('channelId', 0));

        // Load profile
        $profile = $this->getModel()->get('Profiles')->fetchById($channelId);

        // Check perms
        $user = $this->getUser();

        if((!is_object($user)) || (!is_object($profile)) ||
           ((!$user->isAdmin()) && ($user->getId() != $profile->getOwnerId()))) {
            return $this->notFoundAction();
        }

        // Try to set it
        $targetProfileId = (int)$request->getPost('profile',
                                $request->getQuery('profile', 0));

        if($targetProfileId) {
            $targetProfile = $this->getModel()->get('Profiles')->fetchById($targetProfileId);

            if(!is_object($targetProfile)) {
                return $this->notFoundAction();
            }
        }

        $response = $this->getResponse();

        // TODO : we should probably make sure they're actually streaming
        // with this user.  But I'm not sure I care right now.

        // Set it!
        $this->getModel()->get('Profiles')->update(
                array(
                        'multistream_chat_option' => $targetProfileId,
                ),
                array(
                        'id' => $profile->getId(),
                )
        );

        $response->setContent('{"status": 1}');
        return $response;
    }

    /*
     * Check if a given profile/stream URL is available.
     *
     * @param url  ... The URL to check
     *
     * Returns a JSON dict with a single member; 'available', which will
     * be true or false.
     */
    public function checkurlAction()
    {
        $request = $this->getRequest();

        // Get URL string
        $url = $request->getPost('url', $request->getQuery('url', ''));

        // Try to load a profile for it
        $profiles = $this->getModel()->get('Profiles')->fetchByUrl($url);

        // respond
        $response = $this->getResponse();

        if(count($profiles)) {
            $response->setContent('{"available": 0}');
        } else {
            $response->setContent('{"available": 1}');
        }

        return $response;
    }
}
