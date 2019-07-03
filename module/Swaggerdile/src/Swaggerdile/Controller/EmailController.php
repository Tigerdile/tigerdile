<?php
/*
 * EmailController
 *
 * Controller for handling inter-user emails.
 *
 * @author sconley
 */

namespace Swaggerdile\Controller;

use Swaggerdile\Controller;
use Swaggerdile\Form\Email;

class EmailController extends Controller
{
    /*
     * indexAction
     *
     * The email form
     *
     * Here's the rules:
     *
     * - Must be logged in to send mail
     * - If multi-recipient, must be subscribers to my profile.
     * - If single mail, must use captcha to send.
     * - Must have 'recipients' posted or getted.
     *
     * This can have a 'return' to return address it.
     */
    public function indexAction()
    {
        // Check if user logged in
        $user = $this->getUser();

        $view = $this->getView();
        $view->form = false;
        $messages = array();

        if(!is_object($user)) {
            // This will indicate we need to login, if form
            // false
            return $view;
        }

        // Grab request
        $request = $this->getRequest();

        $recipients = $request->getPost('email',
                                $request->getPost('recipients'),
                                $request->getQuery('recipients', ''));

        if(is_array($recipients) && array_key_exists('recipients', $recipients)) {
            $recipients = $recipients['recipients'];
        }

        if((!strlen($recipients)) ||
           (!preg_match('/^[\d,]+$/', $recipients))) {
            $view->messages = array('No recipients, or invalid recipients list provided.');
            return $view;
        }

        $view->return = $request->getPost('return', $request->getQuery('return', ''));

        // User table
        $userTable = $this->getModel()->get('User');

        // Validate / parse recipients for display.
        $recipientObjects = array();

        // Are we sending to a creator?
        $view->sendToCreator = false;
        $profile = false;

        if(strpos($recipients, ',') === false) { // Only 1
            $target = $userTable->fetchById($recipients);

            if(empty($target)) {
                $view->messages = array('Unknown recipient.');
                return $view;
            }

            // Load profile, if we have it.
            $profileId = (int)$request->getQuery('profileId',
                                            $request->getPost('profileId', 0));

            if($profileId) {
                $profile = $this->getModel()->get('Profiles')->fetchById($profileId);

                if($profile && ($profile->getOwnerId() == $target->getId())) {
                    $view->creatorProfile = $profile;
                    $view->sendToCreator = true;
                }
            }

            $recipientObjects[] = $target;
        } else {
            $myProfiles = array();

            foreach($user->getProfiles() as $profile) {
                $myProfiles[] = $profile->getId();
            }

            $explodedIds = explode(',', $recipients);

            $recipientObjects = $userTable->fetchSubscribedUsers(
                                            $myProfiles,
                                            array('tigerd_users.ID' => $explodedIds)
            );

            if(empty($recipientObjects)) {
                $view->messages = array('Unknown recipients.');
                return $view;
            }
        }

        $view->recipientObjects = $recipientObjects;

        // Grab our form
        $form = new Email();

        // process form
        if($request->isPost() && strlen($request->getPost('act', ''))) {
            $form->setData($request->getPost());

            if($form->isValid()) {
                $data = $form->getData();
                $queueTable = $this->getModel()->get('EmailQueue');

                $headers = "Reply-To: {$user->getUserEmail()}";

                $queueTable->beginTransaction();

                // Add in additional messages if mailing to profile.
                $content = '';

                if($view->sendToCreator) {
                    $content .= "To Profile: {$profile->getTitle()}\r\n";
                }

                $content .= "From User: {$user->getUserEmail()}\r\n\r\n";
                $content .= $data['email']['message'];

                foreach($recipientObjects as $recipient) {
                    $queueTable->insert(array(
                                        'content' => $content,
                                        'send_to' => $recipient->getUserEmail(),
                                        'subject' => $data['email']['subject'],
                                        'headers' => $headers,
                    ));
                }

                $queueTable->commitTransaction();

                if(!empty($view->return)) {
                    return $this->redirect()->toUrl($view->return);
                } else {
                    $view->form = false;
                    $view->messages = array('Your mail has been successfully sent!');

                    return $view;
                }
            }
        } else {
            $form->setData(array(
                'email' => array(
                    'recipients' => $recipients,
            )));
        }

        $view->form = $form;
        $view->messages = $messages;

        return $view;
    }
}
