<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Swaggerdile\Controller;

use Swaggerdile\Controller;
use Zend\Db\Sql\Predicate\Expression;

// Our forms
use Swaggerdile\Form\Forgotpassword;
use Swaggerdile\Form\Login;
use Swaggerdile\Form\Signup;
use Swaggerdile\Form\Support;

use Hautelook\Phpass\PasswordHash;

class IndexController extends Controller
{
    public function indexAction()
    {
        // Set up our view
        $view = $this->getView();

        // Stack profiles into it, if we have some for the front page.
        $profiles = $this->getModel()->get('Profiles')
                         ->fetchSustainerProfiles();

        // Shuffle it
        shuffle($profiles);

        // Push it
        $view->profiles = $profiles;

        return $view;
    }

    /*
     * browseAction
     *
     * Look for new people.  Search is also implemented.
     *
     * @param string s     - Search query, optional.
     * @param string o     - Sort column
     * @param string d     - Sort direction
     * @param string p     - Page number
     */
    public function browseAction()
    {
        // Set up our view
        $view = $this->getView();

        // Request -- find out if we're searching.
        $request = $this->getRequest();

        // Grab query parameters
        $search = $request->getPost('s', $request->getQuery('s', false));
        $order = $request->getPost('o', $request->getQuery('o', 'title'));
        $orderDirection = $request->getPost('d', $request->getQuery('d', 'asc'));
        $page = (int)$request->getPost('p', $request->getQuery('p', 0));

        // Set up the sort order, as this will apply all over.  Scrub data
        if($orderDirection != 'asc') {
            $orderDirection = 'desc';
        }

        // This array contains the list of valid sort columns.
        // Currently only title is supported.
        if(!in_array($order, array('title'))) {
            $order = 'title';
        }

        // Empty search string is same as false
        if(($search !== false) && (!strlen($search))) {
            $search = false;
        }

        // grab profile table and set the search
        $profilesTable = $this->getModel()->get('Profiles');
        $profilesTable->setOrderBy("sd_profiles.${order} ${orderDirection}")
                      ->setLimit(61) // @TODO : Make configurable
                      ->setOffset($page*60);

        // First page will have sustainers first, if there's no search
        if(($search === false) && ($page == 0)) {
            $view->sustainers = $this->getModel()->get('Profiles')
                                                  ->fetchSustainerProfiles();
        } else {
            $view->sustainers = false;
        }

        // Search or fetch accordingly
        $profiles = $profilesTable->fetchActiveWithSearch($search);

        // Push it
        $view->profiles = $profiles;

        // And limit / offset
        $view->page = $page;
        $view->search = $search;
        $view->order = $order;
        $view->orderDirection = $orderDirection;

        // @TODO : Make page size variable
        $view->pageSize = 60;

        return $view;
    }


    /*
     * Generic action for static content -- just to avoid a lot of dumb
     * copy/paste.
     *
     * View to load comes from route name
     */
    public function genericAction()
    {
        $view = $this->getView();

        $routeMatch = $this->getLocator()
                           ->get('Application')
                           ->getMvcEvent()
                           ->getRouteMatch()
                           ->getMatchedRouteName();

        // Set the right view
        $view->setTemplate("swaggerdile/index/{$routeMatch}.phtml");
        return $view;
    }

    /*
     * Handle the support form
     */
    public function supportAction()
    {
        // Grab our form
        $form = new Support();

        // Get our view
        $view = $this->getView();

        // Get our request
        $request = $this->getRequest();

        // Get our user
        $user = $this->getUser();

        // Are we done?
        $done = false;

        if($request->isPost()) {
            $form->setData($request->getPost());

            if($form->isValid()) {
                // Grab the scrubbed data
                $data = $form->getData();

                // Get my user if I have one
                $tigerdileEmail = 'Unavailable';
                $tigerdileUser = 'Not Logged In';

                if(is_object($user)) {
                    $tigerdileEmail = $user->getUserEmail();
                    $tigerdileUser = $user->getUserLogin();
                }

/*
                // Submit it to JIRA.
                $jira = $this->getLocator()->get('Jira');

                // Get our help issue type
                $helpId = 0;

                // @TODO : This seems like a sloppy way to do it.
                // Surely there's a better way.
                try {
                    foreach($jira->getIssueTypes() as $help) {
                        if($help->getName() == 'IT Help') {
                            $helpId = $help->getId();
                            break;
                        }
                    }
                } catch(\Exception $e) {
                    // swallow, but log it
                    $this->getLocator()
                         ->get('logger')
                         ->err("Failure to communicate with Jira");
                    $this->getLocator()
                         ->get('logger')
                         ->err($e);
                }

                // try to push it to Jira
                if($helpId) {
                    try {
                        $jira->createIssue(
                            "TIGERD",
                            "Support Issue",
                            $helpId,
                            array(
                                'description' =>
                                    "{$data['message']}\n\nUser agent: " .
                                    $_SERVER['HTTP_USER_AGENT'] .
                                    "\nIP: " . $_SERVER['HTTP_CF_CONNECTING_IP'],
                                'customfield_10201' => $data['email'],
                                'customfield_10203' => $tigerdileEmail,
                                'customfield_10202' => $tigerdileUser,
                                'customfield_10200' => $data['name'],
                                'customfield_10204' => '',
                            )
                        );
                    } catch(\Exception $e) {
                        // Swallow it, but log
                        $this->getLocator()
                             ->get('logger')
                             ->err("Failure to communicate with Jira");
                        $this->getLocator()
                             ->get('logger')
                             ->err($e);
                    }
                }
*/

                // Fall back to sending email
                $this->getLocator()
                     ->get('Email')
                     ->send(
                        'support@tigerdile.com',
                        'Tigerdile Support Submission',
                        <<<MSG
Hello Supreme Tigerdile Overlords,

A user has submitted a support request.  This request is hopefully also in JIRA.

User: {$tigerdileUser} (Given: {$data['name']})
Email: {$tigerdileEmail} (Given: {$data['email']})
UA: {$_SERVER['HTTP_USER_AGENT']}
IP: {$_SERVER['HTTP_CF_CONNECTING_IP']}

{$data['message']}
MSG
                        );
                $done = true;
            }
        } else {
            // Default user name and email if we have 'em
            if(is_object($user)) {
                $form->setData(array(
                    'email' => $user->getUserEmail(),
                    'name' => $user->getUserLogin()
                ));
            }
        }

        $view->done = $done;
        $view->form = $form;
        return $view;
    }

    /*
     * loginAction
     *
     * @param return - If we have a return URL, pass it.
     *
     * The handler for logging in.
     */
    public function loginAction()
    {
        // Grab our form
        $form = new Login();

        // Get our view
        $view = $this->getView();

        // Grab request
        $request = $this->getRequest();

        // Return URL, if we've got it.
        $returnUrl = $request->getPost('return', $request->getQuery('return', false));

        // Get authentication service.
        $authService = $this->getLocator()->get('AuthService');

        // No need to be here if we're logged in.
        if($authService->hasIdentity()) {
            if($returnUrl) {
                return $this->redirect()->toUrl($returnUrl);
            } else {
                return $this->redirect()->toRoute('stream');
            }
        }

        // Error messages, if we've get them.
        $messages = array();

        // Process login, if post
        if($request->isPost()) {
            $form->setData($request->getPost());

            if($form->isValid()) {
                // Grab the clean data
                $data = $form->getData();

                // try to log in
                $authService->getAdapter()
                            ->setIdentity($data['login'])
                            ->setCredential($data['password']);

                $result = $authService->authenticate();

                // Gather messages, if any
                foreach($result->getMessages() as $message) {
                    $messages[] = $message;
                }

                if($result->isValid()) {
                    if($returnUrl) {
                        return $this->redirect()->toUrl($returnUrl);
                    } else {
                        return $this->redirect()->toRoute('dashboard');
                    }
                }
            } else {
                $messages[] = 'Login ID/Email and Password are required.';
            }
        }

        // Push return URL to view.
        $view->returnUrl = $returnUrl;

        // Push form to view
        $view->form = $form;

        // Push errors to view
        $view->messages = $messages;

        return $view;
    }

    /*
     * logoutAction
     *
     * Clear authentication
     */
    public function logoutAction()
    {
        $authService = $this->getLocator()->get('AuthService');

        $authService->getStorage()->clear();
        $authService->clearIdentity();

        return $this->redirect()->toRoute('home', array());
    }

    /*
     * signupAction
     *
     * Offer the signup form
     */
    public function signupAction()
    {
        // Grab our form
        $form = new Signup();

        // Get our view
        $view = $this->getView();

        // Grab request
        $request = $this->getRequest();

        // Return URL, if we've got it.
        $returnUrl = $request->getPost('return', $request->getQuery('return', false));

        // Get authentication service.
        $authService = $this->getLocator()->get('AuthService');

        // No need to be here if we're logged in.
        if($authService->hasIdentity()) {
            if($returnUrl) {
                return $this->redirect()->toUrl($returnUrl);
            } else {
                return $this->redirect()->toRoute('dashboard');
            }
        }

        // Error messages, if we've get them.
        $messages = array();

        // Process signup, if post
        if($request->isPost()) {
            $form->setData($request->getPost());

            if($form->isValid()) {
                // Grab the clean data
                $data = $form->getData();

                // Does the user already exist?
                $userTable = $this->getModel()->get('User');

                $testUsername = $userTable->fetchByUserLogin($data['username']);
                $testEmail = $userTable->fetchByUserEmail($data['email']);

                // Make sure password and confirm match.
                if($data['password'] != $data['confirmpassword']) {
                    $messages[] = 'Your password and password confirmation do not match.';
                }

                if(!empty($testUsername)) {
                    $messages[] = 'That username already exists.  Try our forgot password link?';
                }

                if(!empty($testEmail)) {
                    $messages[] = 'That email is already registered.  Try our forgot password link?';
                }

                if(empty($messages)) {
                    // Crypt the password
                    $pwHasher = new PasswordHash(8, true);

                    $password = $pwHasher->HashPassword($data['password']);

                    // Create the user.
                    $userTable->insert(array(
                        'user_login' => $data['username'],
                        'user_pass' => $password,
                        'user_nicename' => str_replace(' ', '-', strtolower($data['username'])),
                        'user_email' => $data['email'],
                        'user_registered' => date('Y-m-d H:i:s'),
                        'user_status' => 0,
                        'display_name' => $data['username'],
                        'lastactivity' => time(),
                    ));

                    // Log 'em in and return them to whence they came.
                    $authService->getAdapter()
                                ->setIdentity($data['username'])
                                ->setCredential($data['password']);

                    $result = $authService->authenticate();

                    // Gather messages, if any
                    foreach($result->getMessages() as $message) {
                        $messages[] = $message;
                    }

                    if($result->isValid()) {
                        if($returnUrl) {
                            return $this->redirect()->toUrl($returnUrl);
                        } else {
                            return $this->redirect()->toRoute('stream');
                        }
                    } else {
                        throw new \Exception("Could not log in after registering, user: {$data['username']}");
                    }
                }
            } else {
                $messages[] = 'There is a problem with your submission.';
            }
        }

        // Push return URL to view.
        $view->returnUrl = $returnUrl;

        // Push form to view
        $view->form = $form;

        // Push errors to view
        $view->messages = $messages;

        return $view;
    }

    /*
     * forgotpassword Action
     *
     * Action for resolving forgot password
     *
     */
    public function forgotpasswordAction()
    {
        // Grab our form
        $form = new Forgotpassword();

        // Get our view
        $view = $this->getView();

        // Grab request
        $request = $this->getRequest();

        // Return URL, if we've got it.
        $returnUrl = $request->getPost('return', $request->getQuery('return', false));

        // Get authentication service.
        $authService = $this->getLocator()->get('AuthService');

        // No need to be here if we're logged in.
        if($authService->hasIdentity()) {
            if($returnUrl) {
                return $this->redirect()->toUrl($returnUrl);
            } else {
                return $this->redirect()->toRoute('dashboard');
            }
        }

        // Error messages, if we've get them.
        $messages = array();

        // Process signup, if post
        if($request->isPost()) {
            $form->setData($request->getPost());

            if($form->isValid()) {
                // Grab the clean data
                $data = $form->getData();

                // See if our email matches up.
                if(strpos($data['username'], '@') === FALSE) {
                    $user = $this->getModel()->get('User')
                            ->fetchByUserLogin($data['username']);
                } else {
                    $user = $this->getModel()->get('User')
                            ->fetchByUserEmail($data['username']);
                }

                // If it does, set us up an email.  If it doesn't, error.
                if(count($user)) {
                    $user = $user[0];

                    // Set a new UUID for the user.
                    $this->getModel()->get('User')->update(array(
                                'user_activation_key' => new Expression('uuid()'),
                            ), array(
                                'ID' => $user->getId(),
                            )
                    );

                    // Reload the user
                    $user = $this->getModel()->get('User')
                                 ->fetchById($user->getId());

                    // UUID
                    $uuid = $user->getUserActivationKey();

                    // Email a link
                    $link = $this->url()->fromRoute('recoverpassword', array('uid' => $uuid), array('force_canonical' => true));

                    $this->getLocator()
                         ->get('Email')
                         ->send(
                            $user->getUserEmail(),
                            'Tigerdile Password Reset Request',
                            <<<EOS
<p>Hello there!</p>
<p>You requested a password reset on Tigerdile.com!  Or at least, we think you requested it; if you didn't, you may safely ignore this email.</p>
<p>To reset your password, click this link:</p>
<p><a href="{$link}" target="_blank">{$link}</a></p>
<p>If you have any trouble at all, please reply to this email.  This mailbox is actively monitored and we're very happy to help you!</p>
<p>&nbsp;</p>
<p>- The Tigerdile Admins</p>
EOS
                            ,
                            true
                    );

                    $messages[] = "Reset request sent!  Check your mail.";
                } else {
                    $messages[] = "We don't recognize that user.  Please check your spelling, or contact support.";
                }
            }
        }

        // Push return URL to view.
        $view->returnUrl = $returnUrl;

        // Push form to view
        $view->form = $form;

        // Push errors to view
        $view->messages = $messages;

        return $view;
    }

    /*
     * Adult warning and acknolwedgement
     *
     * Can take a 'returnUrl' parameter to redirect on success.
     */
    public function adultAction()
    {
        $view = $this->getView();

        $request = $this->getRequest();
        $view->returnUrl = $request->getPost('returnUrl',
                                $request->getQuery('returnUrl', ''));

        if($request->isPost()) {
            if(strlen($request->getPost('decline', ''))) {
                $this->setIsAdult(false);
                return $this->redirect()->toRoute('home');
            }

            if(strlen($request->getPost('accept', ''))) {
                $this->setIsAdult(true);

                if(strlen($view->returnUrl)) {
                    return $this->redirect()->toUrl($view->returnUrl);
                }

                return $this->redirect()->toRoute('home');
            }
        }

        return $view;
    }

    /*
     * Turn on SFW mode.  This can take a 'returnUrl' parameter, and
     * just sorta defaults to dumping you on the home page otherwise.
     */
    public function sfwAction()
    {
        $this->setIsAdult(false);
        $request = $this->getRequest();
        $returnUrl = $request->getPost('returnUrl',
                        $request->getQuery('returnUrl', ''));

        if(strlen($returnUrl)) {
            return $this->redirect()->toUrl($returnUrl);
        } else {
            return $this->redirect()->toRoute('home');
        }
    }

    /*
     * Password recovery
     */
    public function recoverpasswordAction()
    {
        $view = $this->getView();
        $view->invalid = false;

        // Get our UID
        $uid = $this->params()->fromRoute('uid', false);

        $users = array();

        // Fetch by user activation get.
        if(strlen($uid)) {
            $users = $this->getModel()->get('User')->fetchByUserActivationKey($uid);
        }

        // Invalid UID
        if(!count($users)) {
            $view->invalid = true;
            return $view;
        }

        $view->message = '';

        // If there's more than one, that would be weird
        if(count($users) > 1) {
            throw new \Exception("More than one user with unique ID: {$uid}");
        }

        // Do it
        $request = $this->getRequest();

        if($request->isPost()) {
            // Get passwords
            $newpassword = trim($request->getPost('newpassword'));
            $newpasswordAgain = trim($request->getPost('newpasswordagain'));

            if(!strlen($newpassword)) {
                $view->message = "You must have some password.  It can be short or easy, we won't judge.  This isn't a bank account.";
                return $view;
            } elseif($newpassword != $newpasswordAgain) {
                $view->message = "Sorry to make you type it again, but your passwords didn't match.";
                return $view;
            }

            $pwHasher = new PasswordHash(8, true);
            
            $this->getModel()->get('User')->update(
                array(
                    'user_pass' => $pwHasher->HashPassword($newpassword),
                    'user_activation_key' => new Expression('uuid()'),
                ), array(
                    'id' => $users[0]->getId(),
                )
            );

            return $this->redirect()->toRoute('login');
        }

        return $view;
    }
}
