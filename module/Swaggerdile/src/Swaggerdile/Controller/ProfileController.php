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
use Swaggerdile\Form\Profile as ProfileForm;
use Swaggerdile\Form\Post as PostForm;
use Swaggerdile\Form\Support as SupportForm;
use Swaggerdile\Form\Content as ContentForm;
use Swaggerdile\Form\Comment as CommentForm;
use Swaggerdile\Form\Patreon as PatreonForm;
use Zend\View\Helper\ServerUrl;
use Zend\Db\Sql\Predicate\Literal;
use Swaggerdile\Media;
use Patreon\API;
use Patreon\OAuth;

class ProfileController extends Controller
{
    /*
     * What profile are we operating on??
     *
     * @var Profiles
     */
    protected $_profile = false;

    /*
     * What is our activity, if any?
     *
     * @var string
     */
    protected $_activity = '';

    /*
     * Are we the owner of this profile?
     *
     * @var boolean
     */
    protected $_isOwner = false;

    /*
     * Are we subscribed to this profile?   This is our Tier ID if we are.
     *
     * @var integer
     */
    protected $_subscriberTier = 0;

    /*
     * Pre-dispatch sets up some common view crap.
     *
     * @param \Zend\Mvc\MvcEvent
     * @return value of : parent::onDispatch
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $ev)
    {
        // Figure out what profile we're doing and what action
        // (if any) we are taking.
        $this->_activity = $this->params()->fromRoute('activity', false);
        $profileUrl = $this->params()->fromRoute('profile', false);

        // grab our model, user
        $user = $this->getUser();
        $model = $this->getModel();

        // Load our profile if we have it.
        $profilesTable = $model->get('Profiles');

        if($profileUrl) {
            $res = $profilesTable->fetchByUrl(strtolower($profileUrl));

            if(count($res)) {
                $this->_profile = $res[0];

                // do we own this profile?
                $this->_isOwner = is_object($user) && ($user->isAdmin() ||
                            ($this->_profile->getOwnerId() == $user->getId()));
            }
        }

        // We should have a profile at this point.  Let's stack
        // up our view.
        $view = $this->getView();

        // Get our subscription info
        if(is_object($user) && is_object($this->_profile)) {
            $this->_subscriberTier = $user->getProfileSubscription($this->_profile->getId());
        } else {
            $this->_subscriberTier = 0;
        }

        $view->user = $user;
        $view->profile = $this->_profile;
        $view->isOwner = $this->_isOwner;
        $view->subscriberTier = $this->_subscriberTier;

        // Adult check
        if(is_object($this->_profile) && $this->_profile->getIsNsfw() &&
           (!$this->getIsAdult())) {
            $helper = new ServerUrl();
            return $this->redirect()->toUrl('/adult?returnUrl=' . urlencode($helper->__invoke(true)));
        }

        return parent::onDispatch($ev);
    }

    /*
     * View or edit a profile.
     */
    public function indexAction()
    {
        // grab our model, user
        $user = $this->getUser();
        $model = $this->getModel();

        $profilesTable = $model->get('Profiles');

        // We should have a profile at this point.  Let's stack
        // up our view.
        $view = $this->getView();

        // Mark URL failure, if applicable
        $view->urlFailure = false;

        // Are we editing?  If so, set up the editing form.
        if($this->_isOwner && ($this->_activity == 'edit')) {
            // Initial load or form post?
            $request = $this->getRequest();

            // Grab our form
            $form = new ProfileForm();

            if($request->isPost()) {
                // Grab our last tier -- that will be the add new.
                // If the fields aren't set, ignore it.
                $postData = $request->getPost();

                $form->setData($postData);

                if($form->isValid()) {
                    $data = $form->getData();

                    // Check our URL
                    $res = $profilesTable->fetchByUrl($data['profile']['url']);

                    if((!count($res)) || ($res[0]->getId() == $this->_profile->getId())) {
                        // Start a transction
                        $profilesTable->beginTransaction();

                        $profilesTable->update(
                                    array(
                                        'title' => $data['profile']['title'],
                                        'content' => Media::filterInlineData($this->_profile, $data['profile']['content']),
                                        'is_visible' => $data['profile']['isVisible'],
                                        'is_hiatus' => $data['profile']['isHiatus'],
                                        'is_nsfw' => $data['profile']['isNsfw'],
                                        'historical_fee' => $data['profile']['historicalFee'],
                                        'url' => $data['profile']['url'],
                                        'use_watermark' => $data['profile']['useWatermark'],
                                        'payment_type_id' => $data['profile']['paymentTypeId'],
                                    ),
                                    array('id' => $this->_profile->getId())
                        );

                        // Upload file, if provided.
                        $files = $request->getFiles();

                        if(count($files) && (array_key_exists('profile', $files)) &&
                           (array_key_exists('profileIcon', $files['profile'])) &&
                           ($files['profile']['profileIcon']['size'] > 0)) {
                            \Swaggerdile\Media::storeProfileIcon($this->_profile, $files['profile']['profileIcon']['tmp_name']);

                            $this->getServiceLocator()->get('Cache')
                                 ->deleteFromCache(
                                    \Swaggerdile\Media::getProfileIcon($this->_profile));
                        }

                        $profilesTable->commitTransaction();

                        return $this->redirect()->toRoute('profiles',
                           array(
                               'profile' => $data['profile']['url'], // in case it changed!
                        ));
                    } else {
                        $view->urlFailure = true;
                    }
                }
            } else {
                // Preload it
                $form->setData(array(
                    'profile' => array(
                        'title' => $this->_profile->getTitle(),
                        'content' => $this->_profile->getContent(),
                        'historicalFee' => $this->_profile->getHistoricalFee(),
                        'isNsfw' => $this->_profile->getIsNsfw(),
                        'isVisible' => $this->_profile->getIsVisible(),
                        'isHiatus' => $this->_profile->getIsHiatus(),
                        'url' => $this->_profile->getUrl(),
                        'useWatermark' => $this->_profile->getUseWatermark(),
                        'paymentTypeId' => $this->_profile->getPaymentTypeId(),
                    ),
                ));
            }

            // Push form to view
            $view->form = $form;
        } else {
            $view->form = false; // no form
        }

        return $this->getView();
    }

    /*
     * View, create, or edit posts.
     *
     * For viewing posts, the following URL parameters are understood :
     *
     * @param string o     - Sort column
     * @param string d     - Sort direction
     * @param string p     - Page number
     */
    public function postsAction()
    {
        // Only people subscribed, or owners, can get to this page.
        if(is_object($redirect = $this->_subscriberRouting())) {
            return $redirect;
        }

        // This is never valid with no profile
        if(!is_object($this->_profile)) {
            // @TODO : Route to search instead?  Best guess?
            return $this->redirect()->toRoute('home');
        }

        // Get our model and tables.
        $model = $this->getModel();
        $contentTable = $model->get('Content');

        // Get user
        $user = $this->getUser();

        // Get our view
        $view = $this->getView();

        // get our request
        $request = $this->getRequest();

        // We may have a param (post ID)
        $postId = $this->params()->fromRoute('param', 0);

        // Our loaded item of content, if applicable
        $content = false;

        // Try to load the post if we can.
        if($postId) {
            $tmp = $contentTable->fetchProfileContentForUser($user, $this->_profile, array('sd_content.id' => $postId));

            if(count($tmp)) {
                $content = $tmp[0];
            } else {
                // Does not exist
                $postId = 0;
            }
        }

        // Our form, if applicable.
        $form = false;

        /*
         * We may take one of the following actions:
         *
         * * No action - list all posts
         * * view      - view a given $postId, invalid if no postId
         * * new       - Create a new post
         * * edit      - Edit a post
         * * delete    - Delete a post
         *
         * new, edit, and delete require isOwner
         * view and "no action" are sensitive to tier level.
         */
        $needPost = true;
        $isEditing = false;
        $messages = array();
        switch($this->_activity) {
            case 'new':
            case 'edit':
                $isEditing = true;
            case 'view':
                // These require the load of the form
                if($isEditing && $this->_isOwner) {
                    // Are we deleting? @TODO -- copy/pasted code :P
                    if($request->getPost('act','') == 'Delete') {
                        if(!is_object($content)) {
                            $messages[] = 'You are trying to delete something that hasn\'t been created yet.';
                        } elseif(count($contentTable->fetchByParentId($content->getId()))) {
                            // Make sure directory is empty, if applicable
                            $messages[] = 'You cannot delete a folder unless you delete everything under it first.';
                        } else {
                            // Let's try to delete.
                            $this->getMedia()->deleteContent($content);

                            // Delete linkages
                            $contentTable->beginTransaction();

                            $this->getModel()->get('ContentTiersLink')
                                 ->delete(array('content_id' => $content->getId()));
                            $this->getModel()->get('Comments')
                                 ->delete(array('content_id' => $content->getId()));
                            $contentTable->delete(array('id' => $content->getId()));

                            $contentTable->commitTransaction();

                            // Bounce out of here.
                            // Push user to view mode.
                            return $this->redirect()->toRoute('profile-posts',
                               array(
                                   'profile' => $this->_profile->getUrl(),
                                   'activity' => null,
                            ));
                        }
                    }

                    $form = new PostForm();

                    // Load tier options.
                    $tiers = $this->_profile->getTiers();
                    $tierOptions = array();

                    foreach($tiers as $tier) {
                        $tierOptions[$tier->getId()] = "(\${$tier->getPrice()}) {$tier->getTitle()}";
                    }

                    $form->get('post')->get('visibleToTiers')
                         ->setValueOptions($tierOptions)
                         ->setAttribute('size', count($tierOptions));

                    // Process the form if its been posted.
                    if($request->isPost()) {
                        $form->setData($request->getPost());

                        if($form->isValid()) {
                            $data = $form->getData();

                            // New vs. edit is determined based on
                            // the presence of $postId
                            $contentTable->beginTransaction();

                            $result = false;

                            if($postId) {
                                $result = $contentTable->update(
                                                array(
                                                    'title' => $data['post']['title'],
                                                    'content' => Media::filterInlineData($this->_profile, $data['post']['content'], $postId),
                                                    'is_comments_disabled' => (int)$data['post']['isCommentsDisabled'],
                                                    'updated' => date('Y-m-d H:i:s'),
                                                ),
                                                array(
                                                    'id' => $postId,
                                                    'profile_id' => $this->_profile->getId(),
                                                )
                                );
                            } else {
                                // This has to be two-phase to handle
                                // inline data filtering.
                                $result = $contentTable->insert(
                                                array(
                                                    'title' => $data['post']['title'],
                                                    'content' => '',
                                                    'is_comments_disabled' => (int)$data['post']['isCommentsDisabled'],
                                                    'type_id' => 1, // Post
                                                    'profile_id' => $this->_profile->getId(),
                                                    'author_id' => $this->getUser()->getId(),
                                                    'created' => date('Y-m-d H:i:s'),
                                                    'is_sample' => 0,
                                                    'is_never_historical' => 0,
                                                )
                                );

                                $postId = $contentTable->getLastInsertValue();

                                $contentTable->update(
                                        array(
                                            'content' => Media::filterInlineData($this->_profile, $data['post']['content'], $postId),
                                        ),
                                        array(
                                            'id' => $postId,
                                        )
                                );
                            }

                            // This had better be 1
                            // Don't acknowledge it.  Someone tampered.
                            if($result != 1) {
                                $contentTable->rollbackTransaction();

                                return $this->redirect()->toRoute('profile-posts',
                                           array(
                                               'profile' => $this->_profile->getUrl(),
                                               'activity' => 'view',
                                               'param' => $postId,
                                ));
                            }

                            // Blow away existing tier linkage and replace.
                            $ctLinkTable = $model->get('ContentTiersLink');

                            $ctLinkTable->delete(array(
                                                    'content_id' => $postId,
                            ));

                            if(is_array($data['post']['visibleToTiers'])) {
                                foreach($data['post']['visibleToTiers'] as $tierId) {
                                    $ctLinkTable->insert(array(
                                                        'content_id' => $postId,
                                                        'tier_id' => $tierId,
                                    ));
                                }
                            }

                            // Commit transaction.
                            $contentTable->commitTransaction();

                            // Push user to view mode.
                            return $this->redirect()->toRoute('profile-posts',
                                       array(
                                           'profile' => $this->_profile->getUrl(),
                                           'activity' => 'view',
                                           'param' => $postId,
                            ));
                        }
                    } elseif(is_object($content)) {
                        // Load form default values.
                        $form->setData(array(
                            'post' => array(
                                'title' => $content->getTitle(),
                                'content' => $content->getContent(),
                                'isCommentsDisabled' => $content->getIsCommentsDisabled(),
                                'visibleToTiers' => $content->getTierIds(),
                            ),
                        ));
                    }
                } elseif($needPost && (!$postId)) {
                    return $this->redirect()->toRoute('profile-posts',
                               array(
                                   'profile' => $this->_profile->getUrl(),
                                   'activity' => null,
                    ));
                }

                break;
            case 'delete':
            default:
                // List all
                $needPost = false;

                // Grab query parameters
                $order = $request->getPost('o', $request->getQuery('o', 'created'));
                $orderDirection = $request->getPost('d', $request->getQuery('d', 'desc'));
                $page = (int)$request->getPost('p', $request->getQuery('p', 0));

                // Set up the sort order, as this will apply all over.  Scrub data
                if($orderDirection != 'asc') {
                    $orderDirection = 'desc';
                }

                // This array contains the list of valid sort columns.
                // Currently only title is supported.
                if(!in_array($order, array('title', 'created'))) {
                    $sortOrder = 'sd_content.created';
                    $order = 'created';
                } else {
                    $sortOrder = 'sd_content.' . $order;
                }

                // And limit / offset
                $view->page = $page;
                $view->order = $order;
                $view->orderDirection = $orderDirection;
                $view->pageSize = 60;

                $content = $contentTable->setOrderBy("${sortOrder} ${orderDirection}")
                                        ->setLimit(61)
                                        ->setOffset($page * 60)
                                        ->fetchProfileContentForUser($user, $this->_profile);
                $view->setTemplate('swaggerdile/profile/post-list.phtml');
        }

        $view->form = $form;
        $view->content = $content;
        $view->messages = $messages;

        return $view;        
    }

    /*
     * Create a new profile
     */
    public function createAction()
    {
        // Have to have a user
        $user = $this->getUser();

        $view = $this->getView();

        // Shouldn't be here if we have no user.
        if(!is_object($user)) {
            // need to login.
            return $view;
        }

        // Are we an approved streamer yet?
        $isApprovedStreamer = (int)$user->getMeta('stream_approved', true);

        // Grab our request and see if it's a post -- if it is,
        // we may be creating a new profile.
        $request = $this->getRequest();

        // Error messages
        $messages = array();

        // URL
        $url = '';
        $request_success = false;

        if($isApprovedStreamer && $request->isPost()) {
            return $this->redirect()->toRoute('setup');
        } elseif($request->isPost()) { // request for approval
            $url = $request->getPost('url', '');

            // Validate it.
            $validator = new \Zend\Validator\Uri(array(
                            'allowRelative' => false));

            if((!strlen($url)) || (!$validator->isValid($url))) {
                $messages[] = 'The URL provided is invalid.  Please try again.';
            } else {
                // Send the mail
                $ip = $_SERVER['REMOTE_ADDR'];

                if(array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }

                $email = $this->_locator->get('Email');
                $email->send(
                            'support@tigerdile.com',
                            'Swaggerdile Approval Request',
                            <<<MSG
A request to use Swaggerdile has been received.

URL: {$url}
Email: {$user->getUserEmail()}
User Login: {$user->getUserLogin()}
Approval link: https://www.tigerdile.com/wp-admin/user-edit.php?user_id={$user->getId()}
IP: {$ip}


- Swaggerdile
MSG
                );

                $request_success = true;
            }
        }

        // view hinges on stream approval
        $view->isApprovedStreamer = $isApprovedStreamer;
        $view->url = $url;
        $view->request_success = $request_success;
        $view->messages = $messages;

        return $view;
    }

    /*
     * Support this profile!
     *
     * User may be logged in or logged out -- logged out user will be
     * directed to login or sign up, then re-directed back here.
     *
     * Logged in user may be subscribed or unsubscribed.  The former
     * will pre-load data.
     *
     */
    public function supportAction()
    {
        // This is never valid with no profile
        if(!is_object($this->_profile)) {
            return $this->notFoundAction();
        }

        // No matter what, we need a view
        $view = $this->getView();

        // Stack a blank message in there.
        $view->message = false;
        $view->isDecline = false;
        $isDecline = false;

        // get our user
        $user = $this->getUser();

        // If the user is not logged in, let's just kick them straight
        // to the view script.
        if(!is_object($user)) {
            return $view;
        }

        // Figure out if we're a decline
        if(!$this->_subscriberTier) {
            // We may be a declined
            $mySub = $this->getModel()->get('Subscriptions')
                          ->fetchByUserIdAndProfileId(
                                $user->getId(),
                                $this->_profile->getId());

            // Not yet 30 days
            if((!empty($mySub)) && (!empty($mySub[0]->getDeclinedOn())) &&
               (time() < (strtotime($mySub[0]->getDeclinedOn())+2592000))) {
                $view->isDecline = true;
                $isDecline = true;
            } else {
                return $this->redirect()->toRoute('shutdown');
            }
        } else {
            return $this->redirect()->toRoute('shutdown');
        }

        // At this phase, we will always need a form.
        $form = new SupportForm();

        // Load in tier information.
        $tiers = $this->_profile->getTiers();
        $tierOptionMap = array();

        foreach($tiers as $tier) {
            // Only if its available
            $available = $tier->getAvailability();

            if(($available !== false) && ($available <= 0)) {
                continue;
            }

            $tierOptionMap[$tier->getId()] = $tier->getTitle();
        }
    
        $form->get('tierId')->setValueOptions($tierOptionMap);
        
        // Grab our request.
        $request = $this->getRequest();

        // If it's a post, take specific action
        if($request->isPost()) {
            // Do we require shipping?
            $tmpTierId = (int)$request->getPost('tierId', 0);
            $addressForm = false;

            if($tmpTierId && array_key_exists($tmpTierId, $tierOptionMap)) {
                foreach($tiers as $tier) {
                    if($tier->getId() == $tmpTierId) {
                        if((int)$tier->getIsShippable()) {
                            $form->needAddressForm();
                        } else {
                            $addressForm = $form->get('address');
                            $form->remove('address');
                        }

                        break;
                    }
                }
            } else {
                $addressForm = $form->get('address');
                $form->remove('address');
            }

            // Set our data and validate.
            $form->setData($request->getPost());

            if($this->_subscriberTier &&
               $request->getPost('act', '') == 'Unsubscribe') {
                // Put the address form back in if we took it out
                if(is_object($addressForm)) {
                    $form->add($addressForm);
                }

                // Do the unsubscribe
                $this->getModel()->get('Subscriptions')
                     ->update(array(
                                'child_payment_method_id' => null,
                            ),array(
                                'id' => $this->_subscriberTier->getId(),
                            ));

                $this->_subscriberTier->setChildPaymentMethodId(null);
                $view->message = "You are no longer subscribed and will not be billed again for this profile.";
            } elseif($form->isValid()) {
                $ordersTable = $this->getModel()->get('Orders');

                // Put the address form back in if we took it out
                if(is_object($addressForm)) {
                    $form->add($addressForm);
                }

                // Store the values and push to the check out page.
                // If our total is $0, don't push.
                $data = $form->getData();

                // Lets see if they are paying anything
                $paying = 0 + $data['extra']; // fixes type issues
                $tierPrice = 0;

                if($data['tierId']) {
                    foreach($tiers as $tier) {
                        if($tier->getId() == $data['tierId']) {
                            $paying += ($tierPrice = $tier->getPrice());
                            break;
                        }
                    }
                }

                $now = time();

                if($paying < 1) {
                    $view->message = 'Your contribution must be at least $1.00.  Please review your options and try again!';
                } else {
                    // Store address
                    $addressMeta = '';

                    if($addressForm === false) {
                        $addressMeta = serialize($data['address']);
                    }

                    $prorateTotal = 0;

                    // If we need to pay based on what our payment
                    // setting is like.
                    // @TODO : fix hard code ID
                    switch($this->_profile->getPaymentTypeId()) {
                        case 1:
                        case 3:
                        case 4:
                            // Get our order information, if it exists, to see if
                            // we're doing a delta
                            $pastOrders = $ordersTable->fetchOrdersSince(
                                                             $user->getId(),
                                                             date('Y-m-d H:i:s',
                                                                  mktime(0,0,0, date('n'), 1)
                                                             ));

                            // How much have they paid?
                            $amountPastPaid = 0;

                            foreach($pastOrders as $pastOrder) {
                                // If it's not a pro rate, we get the amount from the
                                // order items.  If it is, we get the amount from
                                // the order minus the historical fee from order
                                // items if set.
                                $orderItems = $pastOrder->getOrderItems($this->_profile->getId());

                                if($pastOrder->getIsProrate()) {
                                    $historicalFee = 0;

                                    foreach($orderItems as $oi) {
                                        $historicalFee += $oi->getHistoricalPrice();
                                    }

                                    $amountPastPaid += $pastOrder->getTotalPrice() -
                                                      $historicalFee;
                                } else {
                                    foreach($orderItems as $oi) {
                                        $amountPastPaid += $oi->getTierPrice() +
                                                           $oi->getExtraPrice();
                                    }
                                }
                            }

                            // We will need to pro-rate til the next billing.
                            $daysInMonth = date('t');

                            // If we're declined, or payment types 3 or 4,
                            // we're paying the full price
                            if((!$isDecline) && ($this->_profile->getPaymentTypeId() != 3) &&
                               ($this->_profile->getPaymentTypeId() != 4)) {
                                // Daily rate
                                $dailyRate = $paying / $daysInMonth;

                                // Multiply by days til we're going to charge.
                                $targetDate = mktime(0, 0, 0, date('n')+1, 1, date('Y'));

                                $difference = $targetDate - $now;

                                if($difference <= 0) {
                                    $difference = 1;
                                } else {
                                    $difference = floor($difference/60/60/24);

                                    if($difference < 1) {
                                        $difference = 1;
                                    }
                                }

                                // Do the multiply, round it up.
                                $prorateTotal = ceil($dailyRate * $difference);

                                // Subtract what we've already paid, may be 0
                                $prorateTotal -= $amountPastPaid;

                                if($prorateTotal < 0) {
                                    $prorateTotal = 0;
                                }
                            }
                            
                            // If we're declined, we'll fall through to
                            // the default handling below.
                        case 2:
                            // Add historical fee as-is if we're paying it
                            if($data['payHistorical']) {
                                $prorateTotal += $this->_profile->getHistoricalFee();
                            }

                            if($view->isDecline || ($this->_profile->getPaymentTypeId() == 3) ||
                               ($this->_profile->getPaymentTypeId() == 4)) {
                                $prorateTotal += $paying;
                            }

                            break;
                        default:
                    }

                    // We are GO for checkout
                    // Create our order and continue.
                    $oiTable = $this->getModel()->get('OrderItems');

                    $ordersTable->insert(array(
                                    'created' => date('Y-m-d H:i:s', $now),
                                    'completed' => null,
                                    'user_id' => $this->getUser()->getId(),
                                    'total_price' => $prorateTotal,
                                    'is_prorate' => 1,
                                    'is_recurring' => 0,
                                    'full_price' => $paying,
                                    'meta' => $addressMeta,
                    ));

                    $orderId = $ordersTable->getLastInsertValue();

                    // Create our order item.
                    $oiTable->insert(array(
                                    'order_id' => $orderId,
                                    'profile_id' => $this->_profile->getId(),
                                    'tier_id' => $data['tierId'] > 0 ? $data['tierId'] : null,
                                    'tier_price' => $tierPrice,
                                    'extra_price' => strlen($data['extra']) ? $data['extra'] : 0,
                                    'historical_price' => $data['payHistorical'] ? $this->_profile->getHistoricalFee() : 0,
                    ));

                    // Pass off to collect payment and shipping address.
                    return $this->redirect()->toRoute('checkout',
                               array(
                                   'orderId' => $orderId,
                    ));
                }
            }
        } else {
            // Do we need to preload something into the form?
            if($this->_subscriberTier) {
                // get all our subscriber info here so we can populate the form.
                $subinfo = array(
                                'tierId' => $this->_subscriberTier->getTierId(),
                );

                // Remove payHistorical if we have already paid it.
                if($this->_subscriberTier->getIsHistoricalPaid()) {
                    $form->remove('payHistorical');
                } else {
                    $subinfo['payHistorical'] = 0;
                }

                $extra = ($this->_subscriberTier->getPayment() - $this->_subscriberTier->getTierPrice());

                if($extra < 0) {
                    // Mark out tier :P
                    $subinfo['tierId'] = 0;
                    $extra = 0;
                }

                $subinfo['extra'] = $extra;

                // Put in address
                $subinfo['address'] = array();

                foreach(array(
                            'ship_to_name' => 'getShipToName',
                            'address1' => 'getAddress1',
                            'address2' => 'getAddress2',
                            'city' => 'getCity',
                            'state' => 'getState',
                            'postal_code' => 'getPostalCode',
                            'country' => 'getCountry') as $key => $call) {
                    $subinfo['address'][$key] = $this->_subscriberTier->$call();
                }

                $form->setData($subinfo);
            }
        }

        // Form goes to the view
        $view->form = $form;
        $view->subscriber = $this->_subscriberTier;

        return $view;
    }

    /*
     * This is a private method to check subscribers only, and is
     * used all over this controller.
     *
     * Returns 'false' if there is no need to redirect, or a
     * redirect object if we need to bounce them.
     *
     * @return false|Zend Framework Redirect
     */
    protected function _subscriberRouting()
    {
        if((!$this->_subscriberTier) && (!$this->_isOwner)) {
            // Try to redirect them.
            $profileUrl = $this->params()->fromRoute('profile', false);

            if($profileUrl) {
                return $this->redirect()->toRoute('profiles',
                           array(
                               'profile' => $profileUrl,
                               'activity' => null,
                ));
            } else {
                return $this->redirect()->toRoute('home');
            }
        }

        return false;
    }

    /*
     * File manage action
     *
     * Pull up form to modify a file or directory.
     */
    public function filemanageAction()
    {
        // This is never valid with no profile, and must be owner
        if((!is_object($this->_profile)) || (!$this->_isOwner)) {
            // @TODO : Route to search instead?  Best guess?
            return $this->redirect()->toRoute('home');
        }

        // Get our model and tables.
        $model = $this->getModel();
        $contentTable = $model->get('Content');
        $ctLinkTable = $model->get('ContentTiersLink');
        $commentsTable = $model->get('Comments');

        // Get user
        $user = $this->getUser();

        // Get our view
        $view = $this->getView();

        // get our request
        $request = $this->getRequest();

        // Push CWD if exists
        $cwd = $request->getQuery('cwd', $request->getPost('cwd', 0));
        $view->cwd = $cwd;

        // cwd 0 == null
        $parentId = null;
        $parent = null;

        if(!$cwd) {
            $cwd = null;
            $fullPath = array();
        } else {
            // Validate the path
            $fullPath = $this->_segmentizePath($cwd);
            $parent = $fullPath[count($fullPath)-1];
            $parentId = $parent->getId();
        }

        // If we're editing, let's load it
        // @TODO : this.
        $postId = false;

        // Initialize
        $content = false;

        if(is_numeric($this->_activity)) {
            $postId = (int)$this->_activity;

            $tmp = $contentTable->fetchProfileContentForUser($user, $this->_profile, array('sd_content.id' => $postId));

            // Error if its empty
            if(!count($tmp)) {
                // @TODO : Route to search instead?  Best guess?
                return $this->redirect()->toRoute('home');
            }

            $content = $tmp[0];
            $typeId = $content->getTypeId();
        } elseif($this->_activity == 'mkdir') {
            $typeId = 3; // @TODO : fix hardcode
        } else { // editing file
            $typeId = 2; // @TODO : fix hardcode;
        }

        $view->typeId = $typeId;

        // Grabo our form
        $form = new ContentForm();

        // Load tier options.
        // @TODO : Duplicate code, centralize.
        $tiers = $this->_profile->getTiers();
        $tierOptions = array();

        foreach($tiers as $tier) {
            $tierOptions[$tier->getId()] = "(\${$tier->getPrice()}) {$tier->getTitle()}";
        }

        $form->get('content')->get('visibleToTiers')
             ->setValueOptions($tierOptions)
             ->setAttribute('size', count($tierOptions));

        // Message list
        $messages = array();

        // Process the form if its been posted.
        if($request->isPost()) {
            $form->setData($request->getPost());

            // File is required if type == 2 and postId is not set yet.
            $files = $request->getFiles();

            // Are we deleting ?
            if($request->getPost('act', '') == 'Delete') {
                if(!is_object($content)) {
                    $messages[] = 'You are trying to delete something that hasn\'t been created yet.';
                } elseif(count($contentTable->fetchByParentId($content->getId()))) {
                    // Make sure directory is empty, if applicable
                    $messages[] = 'You cannot delete a folder unless you delete everything under it first.';
                } else {
                    // Let's try to delete.
                    $this->getMedia()->deleteContent($content);

                    // Delete linkages
                    $contentTable->beginTransaction();

                    $ctLinkTable->delete(array('content_id' => $content->getId()));
                    $commentsTable->delete(array('content_id' => $content->getId()));
                    $contentTable->delete(array('id' => $content->getId()));

                    $contentTable->commitTransaction();

                    // Bounce out of here.
                    // Push user to view mode.
                    return $this->redirect()->toRoute('profile-files',
                               array(
                                   'profile' => $this->_profile->getUrl(),
                                   'activity' => $cwd,
                                   'param' => $postId,
                    ));
                }
            } elseif(($typeId == 2) && (!$postId) && ((!count($files)) || (!$files['content']['file']['size']))) {
                // Force the validator for the other fields
                $form->isValid();

                // set error condition on file upload field
                $form->get('content')->get('file')->setMessages(array("File is required for a new file upload."));
            } elseif($form->isValid()) {
                $data = $form->getData();

                // New vs. edit is determined based on
                // the presence of $postId
                $contentTable->beginTransaction();

                $result = false;

                if($postId) {
                    $result = $contentTable->update(
                                    array(
                                        'title' => $data['content']['title'],
                                        'content' => Media::filterInlineData($this->_profile, $data['content']['content'], $postId),
                                        'is_comments_disabled' => (int)$data['content']['isCommentsDisabled'],
                                        'is_sample' => (int)$data['content']['isSample'],
                                        'is_never_historical' => (int)$data['content']['isNeverHistorical'],
                                        'updated' => date('Y-m-d H:i:s'),
                                        'parent_id' => $parentId,
                                    ),
                                    array(
                                        'id' => $postId,
                                        'profile_id' => $this->_profile->getId(),
                                    )
                    );
                } else {
                    // This has to be two-phase to support filtering
                    // images
                    $result = $contentTable->insert(
                                    array(
                                        'title' => $data['content']['title'],
                                        'content' => '',
                                        'is_comments_disabled' => (int)$data['content']['isCommentsDisabled'],
                                        'type_id' => $typeId, // Post
                                        'profile_id' => $this->_profile->getId(),
                                        'author_id' => $this->getUser()->getId(),
                                        'created' => date('Y-m-d H:i:s'),
                                        'is_sample' => (int)$data['content']['isSample'],
                                        'is_never_historical' => (int)$data['content']['isNeverHistorical'],
                                        'parent_id' => $parentId,
                                    )
                    );

                    $postId = $contentTable->getLastInsertValue();

                    $contentTable->update(
                        array(
                            'content' => Media::filterInlineData($this->_profile, $data['content']['content'], $postId),
                        ),
                        array(
                            'id' => $postId,
                        )
                    );

                    // add to path -- if not file ID
                    if($typeId == 3) {
                        if(!$cwd) {
                            $cwd = $postId;
                        } else {
                            $cwd .= '/' . $postId;
                        }
                    }
                }

                // This had better be 1
                // Don't acknowledge it.  Someone tampered.
                if($result != 1) {
                    $contentTable->rollbackTransaction();

                    // @TODO : send to file list instead.
                    return $this->redirect()->toRoute('profile-posts',
                               array(
                                   'profile' => $this->_profile->getUrl(),
                                   'activity' => 'view',
                                   'param' => $postId,
                    ));
                }

                // Blow away existing tier linkage and replace.
                $ctLinkTable->delete(array(
                                        'content_id' => $postId,
                ));

                if(is_array($data['content']['visibleToTiers'])) {
                    foreach($data['content']['visibleToTiers'] as $tierId) {
                        $ctLinkTable->insert(array(
                                            'content_id' => $postId,
                                            'tier_id' => $tierId,
                        ));
                    }
                } else { // normalize
                    $data['content']['visibleToTiers'] = array();
                }

                // Apply permissions recursively if applicable.
                if(($typeId == 3) && ((int)$request->getPost('applyAllFiles', 0))) {
                    if(!is_object($content)) {
                        $content = $contentTable->fetchById($postId);
                    }

                    $content->updateChildren(array(
                                        'is_comments_disabled' => (int)$data['content']['isCommentsDisabled'],
                                        'is_sample' => (int)$data['content']['isSample'],
                                        'is_never_historical' => (int)$data['content']['isNeverHistorical'],
                                        'updated' => date('Y-m-d H:i:s'),
                    ), $data['content']['visibleToTiers'],
                   ((int)$request->getPost('applyRecursive', 0) == 1));
                }

                // Deal with a file if we have it.
                if(count($files) && $files['content']['file']['size']) {
                    // Centralize code -- this is copy/pasted :P

                    // We need to load a file off the private file system.
                    $config = $this->getConfig();

                    // Where are private media objects stored?
                    // @TODO : Move to media object ... probably all this upload
                    // logic should be in media object ?
                    $privateMediaBasePath = $config['media']['privateMediaBasePath'];

                    $profilePath = "{$privateMediaBasePath}/{$this->_profile->getId()}";

                    if(!is_dir($profilePath)) {
                        if(!mkdir($profilePath)) {
                            throw new \Exception('There was a critical error with the application and we could not store your file.  Please try again, or contact support.');
                        }
                    }

                    // And finally, thumbnail path.
                    $thumbPath = "{$profilePath}/thumbnails";

                    if(!is_dir($thumbPath)) {
                        if(!mkdir($thumbPath)) {
                            throw new \Exception('There was a critical error with the application and we could not store your file.  Please try again, or contact support.');
                        }
                    }

                    // Grab media class
                    $media = $this->getMedia();

                    // Start a finfo
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);

                    // Figure out mime type of file
                    $mimeType = $finfo->file($files['content']['file']['tmp_name']);

                    // Move file into place.
                    if(!move_uploaded_file($files['content']['file']['tmp_name'], "${profilePath}/${postId}")) {
                        $contentTable->rollbackTransaction();
                        throw new \Exception("Failed to store ${$files['content']['file']['name']} - please try again or contact support");
                    }

                    // If it's an image, let's make a thumbnail if we can.
                    // @TODO : make this a separate process so we're not
                    // leaving the browser waiting whilst we crunch thumbnails?
                    // @TODO : TIFFs are not supported by PHP GD.
                    //         should imagemagick them ?
                    if(substr($mimeType, 0, 5) == 'image') {
                        // Let's load!
                        $source = @imagecreatefromstring(file_get_contents("${profilePath}/${postId}"));

                        // Fail ?
                        if($source !== FALSE) {
                            $destImage = $media->scaleImage($source, 200, 200, false);

                            // save it, if we can.  Failure IS an option, don't
                            // care if it fails.
                            imagejpeg($destImage, "${thumbPath}/${postId}", 90);

                            imagedestroy($destImage);
                            imagedestroy($source);
                        }
                    }
                }

                // Commit transaction.
                $contentTable->commitTransaction();

                // Push user to view mode.
                return $this->redirect()->toRoute('profile-files',
                           array(
                               'profile' => $this->_profile->getUrl(),
                               'activity' => $cwd,
                ));
            }
        } elseif(is_object($content)) {
            // Load form default values.
            $form->setData(array(
                'content' => array(
                    'title' => $content->getTitle(),
                    'content' => $content->getContent(),
                    'isCommentsDisabled' => $content->getIsCommentsDisabled(),
                    'visibleToTiers' => $content->getTierIds(),
                    'isSample' => $content->getIsSample(),
                    'isNeverHistorical' => $content->getIsNeverHistorical(),
                ),
            ));
        } elseif(is_object($parent)) { // get a few defaults from the parent
            $form->setData(array(
                'content' => array(
                    'isCommentsDisabled' => $parent->getIsCommentsDisabled(),
                    'visibleToTiers' => $parent->getTierIds(),
                    'isSample' => $parent->getIsSample(),
                    'isNeverHistorical' => $parent->getIsNeverHistorical(),
                ),
            ));
        }

        $view->content = $content;
        $view->form = $form;
        $view->messages = $messages;

        return $view;
    }

    /*
     * Files action
     *
     * For handling browsing and uploading of files.
     *
     * @param integer dir - what directory are we in?
     * @param string o     - Sort column
     * @param string d     - Sort direction
     * @param string p     - Page number
     * @param integer dl   - If 0, we will not download if a file.
     *
     */
    public function filesAction()
    {
        // This is never valid with no profile
        if(!is_object($this->_profile)) {
            return $this->redirect()->toRoute('home');
        }

        // Get our model and tables.
        $model = $this->getModel();
        $contentTable = $model->get('Content');

        // Get user
        $user = $this->getUser();

        // Get our view
        $view = $this->getView();

        // get our request
        $request = $this->getRequest();

        $order = $request->getPost('o', $request->getQuery('o', 'default'));
        $orderDirection = $request->getPost('d', $request->getQuery('d', 'asc'));
        $page = (int)$request->getPost('p', $request->getQuery('p', 0));
        $dl = (int)$request->getPost('dl', $request->getQuery('dl', 1));

        // Set up the sort order, as this will apply all over.  Scrub data
        if($orderDirection != 'asc') {
            $orderDirection = 'desc';
        }

        // This array contains the list of valid sort columns.
        // Currently only created is supported.  If not 'created',
        // we will use the ordering column
        if(!in_array($order, array('created'))) {
            $order = 'ordering asc, sd_content.id';
            $view->order = 'default';
        } else {
            // Prevent DB information leakage -- don't want to expose
            // table names.
            $view->order = $order;
        }

        // And limit / offset
        $view->page = $page;
        $view->orderDirection = $orderDirection;

        // Path segment objects
        $segmentObjects = array();

        // Do we have a directory, or are we at the root?
        if(($this->_activity == 'reorder') && $request->isPost() &&
           $this->_isOwner) {
            // We are re-ordering   This should be a post, and should
            // have 'order'
            $order = $request->getPost('order', array());

            // these should both be arrays
            if(!is_array($order)) {
                throw new \Exception('order is not set on ordering');
            }

            // And numbers
            foreach($order as $o) {
                if(!preg_match('/^\d+$/', $o)) {
                    throw new\Exception('order contains non-numeric values on ordering');
                }
            }

            // Save it
            $contentTable->setOrdering($this->_profile, $order);

            $response = $this->getResponse();

            $headers = new \Zend\Http\Headers();
            $headers->addHeaderLine('Content-Type: text/plain');

            $response->setHeaders($headers);
            $response->setContent('OK');

            return $response;
        } elseif(strlen($this->_activity)) {
            try {
                $segmentObjects = $this->_segmentizePath($this->_activity);
            } catch(\Exception $e) {
                return $this->notFoundAction();
            }

            $content = $segmentObjects[count($segmentObjects)-1];
        }

        $view->path = $this->_activity;
        $view->pathSegments = $segmentObjects;

        // We need to either load a file or get a directory listing.
        if((empty($this->_activity)) || ($content->getTypeId() == 3)) {
            // Set up our table -- offsets and limits apply at this stage
            // after path is verified.
            $contentTable->setOrderBy("sd_content.${order} ${orderDirection}")
                     ->setLimit(61) // @TODO : Make configurable
                     ->setOffset($page*60);


            // Directory listing
            $parentId = empty($this->_activity) ? null : $content->getId();

            $directoryContents = $contentTable->fetchProfileContentForUser($user, $this->_profile,
                                                            array(
                                                                'sd_content.parent_id' => $parentId,
                                                                'sd_content.type_id' => array(2, 3),
            ));

            $view->directoryContents = $directoryContents;
        } else {
            // We need to load a file off the private file system.
            $config = $this->getConfig();

            // Where are private media objects stored?
            $privateMediaBasePath = $config['media']['privateMediaBasePath'];

            // Let's fetch our media file, if it's there.
            $mediaFilePath = "{$privateMediaBasePath}/{$this->_profile->getId()}/{$content->getId()}";

            // If it's not there, we've got a problem.  A very sad
            // problem.
            if(!is_file($mediaFilePath)) {
                return $this->notFoundAction();
            }

            // Some common items
            $type = $content->getMimeType();
            $size = filesize($mediaFilePath);
            $filename = $content->getTitle();
            $response = false;

            // TODO: Unify this code better and move it out of controller :P

            // If we're dealing with images, let's try to watermark
            // it if desired.  Only GIF, JPEG, and PNG
            if(in_array($type, array('image/gif', 'image/png', 'image/jpeg',
                                     'image/pjpeg'))) {
                $response = new \Zend\Http\Response();
                $response->setStatusCode(200);

                // We may wish to watermark
                switch($this->_profile->getUseWatermark()) {
                    case 2: // unobtrusive
                        list($_, $simpleType) = explode('/', $type);

                        if($simpleType == 'pjpeg') {
                            $simpleType = 'jpeg';
                        }

                        $watermarker = new \Swaggerdile\Image\TextPainter(
                                                $mediaFilePath,
                                                date('m/d/Y H:i:s')
                                                . '.' . $user->getId(),
                                                getcwd() . '/public/fonts/tahoma-bold.ttf',
                                                14, strtoupper($simpleType));
                        $watermarker->setTextColor(255, 255, 0);
                        $watermarker->setQuality(90);
                        $watermarker->setPosition('left', 'bottom');
                        $watermarker->addWatermark();

                        ob_start();
                        $watermarker->show();
                        $imageData = ob_get_contents();
                        ob_end_clean();

                        break;
                    case 3: // obtrusive
                        list($_, $simpleType) = explode('/', $type);

                        if($simpleType == 'pjpeg') {
                            $simpleType = 'jpeg';
                        }

                        $watermarker = new \Swaggerdile\Image\TextPainter(
                                                $mediaFilePath,
                                                (string)$user->getId(),
                                                getcwd() . '/public/fonts/tahoma-bold.ttf',
                                                25, strtoupper($simpleType));
                        $watermarker->setFontSizePercent(5);
                        $watermarker->setTextColor(0, 0, 0, 25);
                        $watermarker->setQuality(90);
                        $watermarker->setPosition('left', 'center');
                        $watermarker->addWatermark();
                        $watermarker->setPosition('center', 'center');
                        $watermarker->addWatermark();
                        $watermarker->setPosition('right', 'center');
                        $watermarker->addWatermark();

                        ob_start();
                        $watermarker->show();
                        $imageData = ob_get_contents();
                        ob_end_clean();

                        break;
                    case 1: // Steganography
                        try {
                            $processor = new \KzykHys\Steganography\Processor();
                            $image = $processor->encode($mediaFilePath, 
                                                        "{$user->getId()}",
                                                        array('compression' => 9));

                            ob_start();
                            $image->render();
                            $imageData = ob_get_contents();
                            ob_end_clean();

                            $type = 'image/png';
                            $filename = preg_replace('/(\.jpg)|(\.gif)|(\.jpeg)$/', '.png', $filename);
                            break;
                        } catch(\Exception $e) {
                            // Nothing to do in this case -- roll over to default.
                        }
                    default: // none
                        $imageData = file_get_contents($mediaFilePath);
                }

                // Append user ID
                $imageData .= is_object($user) ? $user->getId() : 0;
                $size = strlen($imageData);
                $response->setContent($imageData);
            } else {
                // Let's let them grab the file.
                $response = new \Zend\Http\Response\Stream();
                $response->setStatusCode(200);
                $response->setStream(fopen($mediaFilePath, 'r'));
            }

            // Common headers

            $headers = new \Zend\Http\Headers();

            if($dl) {
                $headers->addHeaderLine('Content-Type', 'application/octet-stream')
                        ->addHeaderLine('Content-Disposition', "attachment; filename=\"{$filename}\"")
                        ->addHeaderLine('Content-Length', $size);
            } else {
                $headers->addHeaderLine('Content-Type', $type)
                        ->addHeaderLine('Content-Length', $size);
            }

            $response->setHeaders($headers);

            return $response;
        }

        // @TODO : Make page size variable
        $view->pageSize = 60;

        // Done!
        return $view;
    }

    /*
     * File upload handler
     *
     * Designed to be used with dropzone
     *
     * This may get hit in kind of rapid fire.  Files will be in
     * an array called 'file'.  There will be an array, synchronized
     * with 'file', called 'fileFullpath' which has the directory path
     * being built under it.
     *
     * 'activity' will be the parent ID to use or 0 / null if none
     */
    public function uploadfilesAction()
    {
        // This is never valid with no profile
        if(!is_object($this->_profile)) {
            // @TODO : Route to search instead?  Best guess?
            return $this->redirect()->toRoute('home');
        }

        // Non-owners can go get bent ;P
        if(!$this->_isOwner) {
            return $this->notFoundAction();
        }

        // Grab user
        $user = $this->getUser();

        // Grab request
        $request = $this->getRequest();

        // This better be a post
        if(!$request->isPost()) {
            return $this->notFoundAction();
        }

        // Validate our path
        $parentId = null;
        $path = array();

        if(strlen($this->_activity)) {
            try {
                $path = $this->_segmentizePath($this->_activity);
                $parentId = $path[count($path)-1]->getId();
            } catch(\Exception $e) {
                return $this->notFoundAction();
            }
        }

        // Set up a view to return our results
        // no layout
        $view = $this->getView(true);
        $view->newFiles = array();
        $view->path = $this->_activity;

        // See if we have files
        $files = $request->getFiles();
        $fileFullpaths = $request->getPost('fileFullpath', array());

        if(!count($files)) {
            return $view; // ?? Not sure what to do in this case
        }

        // Grab private configuration, set up profile directory
        // if needed.

        // We need to load a file off the private file system.
        $config = $this->getConfig();

        // Where are private media objects stored?
        // @TODO : Move to media object ... probably all this upload
        // logic should be in media object ?
        $privateMediaBasePath = $config['media']['privateMediaBasePath'];

        // Make it if it doesn't exist
        if(!is_dir($privateMediaBasePath)) {
            if(!mkdir($privateMediaBasePath)) {
                throw new \Exception('There was a critical error with the application and we could not store your file.  Please try again, or contact support.');
            }
        }

        $profilePath = "{$privateMediaBasePath}/{$this->_profile->getId()}";

        if(!is_dir($profilePath)) {
            if(!mkdir($profilePath)) {
                throw new \Exception('There was a critical error with the application and we could not store your file.  Please try again, or contact support.');
            }
        }

        // And finally, thumbnail path.
        $thumbPath = "{$profilePath}/thumbnails";

        if(!is_dir($thumbPath)) {
            if(!mkdir($thumbPath)) {
                throw new \Exception('There was a critical error with the application and we could not store your file.  Please try again, or contact support.');
            }
        }

        // Grab content table
        $contentTable = $this->getModel()->get('Content');
        $ctLink = $this->getModel()->get('ContentTiersLink');

        // Right now
        $now = date('Y-m-d H:i:s');

        // Default permissions - NOTE, this is duplicated in _buildPath
        // consolidate.
        $isSample = false;
        $isCommentsDisabled = false;
        $isNeverHistorical = false;
        $tiers = array();

        if(!$parentId) {
            $parentId = null;
            $parent = null;
        } else {
            $parent = $contentTable->fetchById($parentId);

            if(empty($parent) ||
               ($parent->getProfileId() != $this->_profile->getId())) {
                // Permission problem or invalid parent ID.
                // This will only happen if someone is monkeying with
                // my URL. :P
                return $this->notFoundAction();
            }

            // Inherit defaults
            $isSample = $parent->getIsSample();
            $isCommentsDisabled = $parent->getIsCommentsDisabled();
            $isNeverHistorical = $parent->getIsNeverHistorical();

            // Grab parent tiers to copy onto children.
            $tiers = $parent->getTierIds();
        }

        // Grab media class
        $media = $this->getMedia();

        // Start a finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        // Keep track of what we made
        $filesCreated = array();

        // Move files into place
        $numFiles = count($files['file']);
        for($i = 0; $i < $numFiles; $i++) {
            $file = $files['file'][$i];
            $fullPath = strlen($fileFullpaths[$i]) ? dirname($fileFullpaths[$i]) : '';

            // Make the file structure if we need to.
            $localParentId = $parentId;

            if(strlen($fullPath)) {
                $localParentId = $this->_buildPath($parent, $fullPath);
            }

            // Figure out mime type of file
            $mimeType = $finfo->file($file['tmp_name']);

            // Transaction for each file, because we both want the
            // query to succeed (likely) and the file copy to succeed
            // (potentially "less likely").
            $contentTable->beginTransaction();
            $contentTable->insert(array(
                                    'title' => $file['name'],
                                    'type_id' => 2, // @TODO : fix hardcode
                                    'author_id' => $user->getId(),
                                    'content' => '',
                                    'created' => $now,
                                    'profile_id' => $this->_profile->getId(),
                                    'parent_id' =>  $localParentId,
                                    'is_sample' => $isSample,
                                    'is_comments_disabled' => $isCommentsDisabled,
                                    'is_never_historical' => $isNeverHistorical,
                                    'mime_type' => $mimeType,
            ), true);

            // Grab our new ID, move file into place.
            $filesCreated[] = ($contentId = $contentTable->getLastInsertValue());

            // Move file into place.
            if(!move_uploaded_file($file['tmp_name'], "${profilePath}/${contentId}")) {
                $contentTable->rollbackTransaction();
                throw new \Exception("Failed to store ${file['name']} - please try again or contact support");
            }

            // Insert tiers
            foreach($tiers as $tier) {
                $ctLink->insert(array(
                                    'tier_id' => $tier,
                                    'content_id' => $contentId,
                ));
            }

            $contentTable->commitTransaction();

            // If it's an image, let's make a thumbnail if we can.
            // @TODO : make this a separate process so we're not
            // leaving the browser waiting whilst we crunch thumbnails?
            if(substr($mimeType, 0, 5) == 'image') {
                // Let's load!
                $source = imagecreatefromstring(file_get_contents("${profilePath}/${contentId}"));

                // Fail ?
                if($source !== FALSE) {
                    $destImage = $media->scaleImage($source, 200, 200, false);

                    // save it, if we can.  Failure IS an option, don't
                    // care if it fails.
                    imagejpeg($destImage, "${thumbPath}/${contentId}", 90);

                    imagedestroy($destImage);
                    imagedestroy($source);
                }
            }
        }

        // Set ordering on the new rows
        $contentTable->update(
                            array(
                                'ordering' => new Literal('sd_content.id'),
                            ),
                            array(
                                'id' => $filesCreated,
                            )
        );
                            

        // This was, theoretically, successful.  So let's render some HTML
        // to return
        $view->newFiles = $contentTable->fetchById($filesCreated);

        return $view;
    }

    /*
     * Grab secure thumbnail
     *
     */
    public function thumbnailAction()
    {
        // This is never valid with no profile
        if(!is_object($this->_profile)) {
            return $this->notFoundAction();
        }

        // Try to get the content, make sure we're allowed to see it
        $content = $this->getModel()->get('Content')->fetchProfileContentForUser(
                                                  $this->getUser(), $this->_profile,
                                                  array(
                                                        'sd_content.id' => (int)$this->_activity,
        ));

        // If we're not allowed, 404
        if(empty($content)) {
            return $this->notFoundAction();
        }

        // See if the file is there
        // @TODO : centralize this logic
        $thumbFile = $this->getMedia()->getPrivateFileBasePath() . "/{$this->_profile->getId()}/thumbnails/" . ((int)$this->_activity);

        if(!is_file($thumbFile)) {
            return $this->notFoundAction();
        }

        // Prep a response object
        // @TODO : fix hardcode
        $response = new \Zend\Http\Response\Stream();
        $response->setStatusCode(200);
        $response->setStream(fopen($thumbFile, 'r'));

        $headers = new \Zend\Http\Headers();
        $headers->addHeaderLine('Content-Type', 'image/jpeg')
                ->addHeaderLine('Content-Length', filesize($thumbFile))
                ->addHeaderLine('Content-Transfer-Encoding', 'binary');

        $response->setHeaders($headers);

        return $response;
    }

    /*
     * Grab secure file from the 'file dump'
     *
     * activity will have the content ID, file will have the file name
     * we are fetching.
     */
    public function filedumpAction()
    {
        // This is never valid with no profile
        if(!is_object($this->_profile)) {
            return $this->notFoundAction();
        }

        // Try to get the content, make sure we're allowed to see it
        $content = $this->getModel()->get('Content')->fetchProfileContentForUser(
                                                  $this->getUser(), $this->_profile,
                                                  array(
                                                        'sd_content.id' => (int)$this->_activity,
        ));

        // If we're not allowed, 404
        if(empty($content)) {
            return $this->notFoundAction();
        }

        // get our file request, sanitize
        $file = preg_replace('/[^\w\d.]/', '', $this->params()->fromRoute('file', ''));

        // See if the file is there
        // @TODO : centralize this logic
        $thumbFile = $this->getMedia()->getPrivateFileBasePath() . "/{$this->_profile->getId()}/dump/" . ((int)$this->_activity) . "-{$file}";

        if(!is_file($thumbFile)) {
            return $this->notFoundAction();
        }

        // Prep a response object
        // @TODO : fix hardcode
        $response = new \Zend\Http\Response\Stream();
        $response->setStatusCode(200);
        $response->setStream(fopen($thumbFile, 'r'));

        $headers = new \Zend\Http\Headers();
        $headers->addHeaderLine('Content-Type', Media::getMimeTypeFromFile($thumbFile))
                ->addHeaderLine('Content-Length', filesize($thumbFile))
                ->addHeaderLine('Content-Transfer-Encoding', 'binary');

        $response->setHeaders($headers);

        return $response;
    }

    /*
     * Grab an HTML snip for the lightbox.
     *
     * This HTML snip will have next/previous links that have to be computed from
     * the database.  Which makes this minorly complex.
     *
     * @param integer dir  - what directory are we in? - this will be in activity
     * @param integer id   - What content ID are we fetching?
     * @param string o     - Sort column
     * @param string d     - Sort direction
     * @param integer p    - Position number
     *
     */
    public function lightboxAction()
    {
        // This is never valid with no profile
        if(!is_object($this->_profile)) {
            return $this->notFoundAction();
        }

        $parentId = null;
        $path = array();

        if(strlen($this->_activity)) {
            // We may or may not have access to a directory ID.
            try {
                $path = $this->_segmentizePath($this->_activity);
                $parentId = $path[count($path)-1]->getId();
            } catch(\Exception $e) {
                return $this->notFoundAction();
            }
        }

        // Get our model and tables.
        $model = $this->getModel();
        $contentTable = $model->get('Content');

        // Get user
        $user = $this->getUser();

        // Get our view
        $view = $this->getView(true);

        // get our request
        $request = $this->getRequest();

        $contentId = (int)$request->getPost('id', $request->getQuery('id', 0));
        $order = $request->getPost('o', $request->getQuery('o', 'created'));
        $orderDirection = $request->getPost('d', $request->getQuery('d', 'asc'));
        $position = (int)$request->getPost('p', $request->getQuery('p', 0));

        // Content ID is required
        if(!$contentId) {
            return $this->notFoundAction();
        }

        // Set up the sort order, as this will apply all over.  Scrub data
        if($orderDirection != 'asc') {
            $orderDirection = 'desc';
        }

        // This array contains the list of valid sort columns.
        // Currently only created is supported.
        // @TODO : This array should be shared with filesAction
        // This WILL get out of sync later and I will hate my life :)
        if(!in_array($order, array('created'))) {
            $order = 'ordering asc, sd_content.id';
        }

        // And limit / offset
        $view->contentId = $contentId;
        $view->path = $this->_activity;
        $view->order = $order;
        $view->orderDirection = $orderDirection;
        $view->position = $position;

        // Set up our table
        $contentTable->setOrderBy("sd_content.${order} ${orderDirection}");

        if($position > 0) {
            $contentTable->setLimit(3)
                         ->setOffset($position-1);
        } else {
            $contentTable->setLimit(2)
                         ->setOffset(0);
        }

        $contents = $contentTable->fetchProfileContentForUser($user, $this->_profile,
                                                        array(
                                                            'sd_content.parent_id' => $parentId,
                                                            'sd_content.type_id' => array(2,3),
        ));

        $view->contents = $contents;

        return $view;
    }

    /*
     * Add a new comment, form post
     *
     * Activity should contain the content ID we are commenting on.
     */
    public function addcommentAction()
    {
        // This had better be a post or it makes no sense
        $request = $this->getRequest();

        $contentId = (int)$this->_activity;

        // try to load it
        $contentTable = $this->getModel()->get('Content');
        $commentsTable = $this->getModel()->get('Comments');

        $user = $this->getUser();

        $tmp = $contentTable->fetchProfileContentForUser($user, $this->_profile,
                                                        array(
                                                            'sd_content.id' => $contentId,
                                                            'is_comments_disabled' => 0,
        ));

        // Are we deleting ?
        $deleteId = (int)$request->getPost('delete',
                                            $request->getQuery('delete', 0));

        // Comments may be disabled or not available to user
        // bounce them back
        if(((!$deleteId) && (!$request->isPost())) || (!count($tmp))) {
            return $this->redirect()->toRoute('profile-posts',
                                       array(
                                           'profile' => $this->_profile->getUrl(),
                                           'activity' => 'view',
                                           'param' => $contentId,
            ));
        }

        $content = $tmp[0]; // Just to have it, why not!

        // If we're deleting, make sure we have permissions
        if($deleteId) {
            $comment = $commentsTable->select(array(
                                                'id' => $deleteId,
                                                'content_id' => $contentId,
            ));

            // Permission check
            if((count($comment)) && ((!$isOwner) ||
                                      ($comment->current()->author_id != $user->getId()))) {

                // Re-home children
                $commentsTable->beginTransaction();
                $commentsTable->update(
                                    array(
                                        'parent_id' => $comment->current()->parent_id,
                                    ),
                                    array(
                                        'parent_id' => $comment->current()->id,
                                    )
                );

                $commentsTable->delete(array('id' => $deleteId));
                $commentsTable->commitTransaction();
            }

            return $this->redirect()->toRoute('profile-posts',
                                       array(
                                           'profile' => $this->_profile->getUrl(),
                                           'activity' => 'view',
                                           'param' => $contentId,
            ));
        }

        $form = new CommentForm();
        $form->setData($request->getPost());

        if($form->isValid()) {
            $data = $form->getData();

            // Validate parent ID if it exists
            $parentId = (int)$data['parentId'];

            if($parentId) {
                $verify = $commentsTable->select(array(
                                                    'id' => $parentId,
                                                    'content_id' => $contentId,
                ));

                // error if not existing
                if(!count($verify)) {
                    $this->redirect()->toRoute('profile-posts',
                                       array(
                                           'profile' => $this->_profile->getUrl(),
                                           'activity' => 'view',
                                           'param' => $contentId,
                    ));
                }
            }

            $commentsTable->insert(array(
                'parent_id' => $parentId ? $parentId : null,
                'content' => $data['content'],
                'author_id' => $user->getId(),
                'content_id' => $contentId,
                'is_deleted' => 0,
                'created' => date('Y-m-d H:i:s'),
            ));
        }

        return $this->redirect()->toRoute('profile-posts',
                                       array(
                                           'profile' => $this->_profile->getUrl(),
                                           'activity' => 'view',
                                           'param' => $contentId,
        ));
    }

    /*
     * Build a path from a base parent ID, making sure the entire path exists.
     * Returns the 'bottom' of the path, where a new file could be put.
     *
     * If any elements of the path exist, they will be reused.
     *
     * THIS METHOD ASSUMES ADMIN PRIV'S!
     *
     * @param integer Content object or null
     * @param string path
     *
     * @return integer|null
     */
    public function _buildPath($parent, $path)
    {
        // The silly, simple case
        if(!strlen($path)) {
            return is_object($parent) ? $parent->getId() : null;
        }

        // Default permissions - NOTE, this is duplicated in uploadfilesAction
        // consolidate. @TODO
        $isSample = false;
        $isCommentsDisabled = false;
        $isNeverHistorical = false;
        $tiers = array();

        // Override from parent
        if(is_object($parent)) {
            // Inherit defaults
            $isSample = $parent->getIsSample();
            $isCommentsDisabled = $parent->getIsCommentsDisabled();
            $isNeverHistorical = $parent->getIsNeverHistorical();

            // Grab parent tiers to copy onto children.
            $tiers = $parent->getTierIds();
            $previous = $parent->getId();
        } else {
            $previous = null;
        }

        // Get content table
        $contentTable = $this->getModel()->get('Content');
        $ctLink = $this->getModel()->get('ContentTiersLink');

        // At some point, we may be only inserting -- in which case, we can stop
        // selecting :)
        $justInserting = false;
        $now = date('Y-m-d H:i:s');

        $components = explode('/', $path);

        foreach($components as $component) {
            // see if we have a folder already, unless we know its not there.
            if(!$justInserting) {
                $child = $contentTable->select(array(
                                    'parent_id' => $previous,
                                    'title' => $component,
                                    'type_id' => 3, // @TODO: fix hardcode
                                    'profile_id' => $this->_profile->getId(),
                        ));
            } else {
                $child = array();
            }

            // we only care about the first one, if set.
            if(count($child)) {
                $previous = $child->current()->id;
            } else {
                // Once you start, you can't stop.
                $justInserting = true;

                $contentTable->insert(array(
                                        'title' => $component,
                                        'content' => '',
                                        'is_comments_disabled' => $isCommentsDisabled,
                                        'type_id' => 3, // directory @TODO fix hardcode
                                        'profile_id' => $this->_profile->getId(),
                                        'author_id' => $this->getUser()->getId(),
                                        'created' => $now,
                                        'is_sample' => $isSample,
                                        'is_never_historical' => $isNeverHistorical,
                                        'parent_id' => $previous,
                ));

                $previous = $contentTable->getLastInsertValue();

                // Insert tiers
                foreach($tiers as $tier) {
                    $ctLink->insert(array(
                                        'tier_id' => $tier,
                                        'content_id' => $previous,
                    ));
                }
            }
        }

        return $previous;
    }

    /*
     * Validate and segment a path string
     *
     * Path strings will be of the format: 123/123/123/123
     *
     * Each 'step' of the path will be validated to see if the current
     * user has access.  Throws exception on access failure, returns array
     * of content objects that is the parsed path on success.
     *
     * @param string path
     * @return array
     */
    protected function _segmentizePath($path)
    {
        // Let's load the segments of our path.
        $segments = explode('/', $path);

        // Previous segment ID
        $prevSegment = null;

        // Get content table
        $contentTable = $this->getModel()->get('Content');

        $user = $this->getUser();

        $segmentObjects = array();

        // Iterate over segments and get each component.  Stop if
        // we come across a bad component.
        foreach($segments as $segment) {
            $content = $contentTable->fetchProfileContentForUser($user, $this->_profile,
                                                      array(
                                                            'sd_content.type_id' => array(2,3), // @TODO : fix hardcoded
                                                            'sd_content.id' => $segment,
                                                            'sd_content.parent_id' => $prevSegment
            ));

            if(empty($content)) {
                // Exception -- the entire path is hosed
                throw new \Exception("Path segment {$segment} failed");
            }

            // De-array it.
            $segmentObjects[] = $content[0];
            $prevSegment = $content[0]->getId();
        }

        return $segmentObjects;
    }

    /*
     * Subscribers list page
     */
    public function subscribersAction()
    {
        // This is never valid with no profile
        if(!is_object($this->_profile)) {
            // @TODO : Route to search instead?  Best guess?
            return $this->redirect()->toRoute('home');
        }

        // Non-owners can go get bent ;P
        if(!$this->_isOwner) {
            return $this->notFoundAction();
        }

        // The view
        $view = $this->getView();

        // Get our list of subscribers, broken down by tier.
        $tiersTable = $this->getModel()->get('Tiers');
        $subsciptionsTable = $this->getModel()->get('Subscriptions');

        $view->tierlessSubscribers = $subsciptionsTable->fetchTierlessSubscribers(
                                            $this->_profile->getId());
        $view->subscribers = $tiersTable->getUsersByTier($this->_profile->getId());       

        return $view;
    }

    /*
     * Bulk file move handler.
     *
     * Takes a posted array: selectedFiles[]
     * return url goes in 'return'
     */
    public function filemoveAction()
    {
        $request = $this->getRequest();

        // This is never valid with no profile, or for non-owners
        if((!$request->isPost()) || (!is_object($this->_profile)) || 
           (!$this->_isOwner)) {
            return $this->notFoundAction();
        }

        // Check our files.
        $selectedFiles = $request->getPost('selectedFiles', array());

        if(empty($selectedFiles)) {
            return $this->notFoundAction();
        }

        // Make sure they are all integers
        foreach($selectedFiles as $selected) {
            if(!preg_match('/^\d+$/', $selected)) {
                throw new \Exception('Invalid non-numeric file ID passed for file move');
            }
        }

        // Get content table
        $contentTable = $this->getModel()->get('Content');

        // Try to grab 'em
        $files = $contentTable->query(array(
                                'id' => $selectedFiles,
                                'profile_id' => $this->_profile->getId(),
        ));

        // Abort if there's none
        if(!count($files)) {
            throw new \Exception('No files selected for file move');
        }

        // If we have an act -- let's move
        if($request->getPost('act')) {
            $folderId = (int)$request->getPost('folderId', 0);

            // Make sure the folder is real and owned by me.
            if($folderId) {
                $folder = $contentTable->query(array(
                                'id' => $folderId,
                                'profile_id' => $this->_profile->getId(),
                ));

                // if we don't have it -- crash out
                if(!count($folder)) {
                    throw new \Exception('Someone tried to move files into a folder that is not theirs or does not exist.');
                }
            }

            // Get the last content from the folder
            $largestOrdering = $contentTable->getLargestOrdering($folderId);

            // Get sanitized file list
            $fileIdList = array();
            foreach($files as $file) {
                $fileIdList[] = $file->getId();
            }

            $contentTable->update(array(
                                'parent_id' => $folderId > 0 ? $folderId : null,
                                'ordering' => new Literal("sd_content.id + {$largestOrdering}"),
                                ),array(
                                    'id' => $fileIdList,
                                )
            );

            $returnUrl = $request->getPost('return', '');

            if(strlen($returnUrl)) {
                return $this->redirect()->toUrl($returnUrl);
            } else {
                return $this->redirect()->toRoute('profile-files', array('profile' => $this->_profile->getUrl()));
            }
        }

        // Get a view
        $view = $this->getView(true);


        // Query folders and build our structure.
        $folderMap = array();

        foreach($contentTable->select(array(
                        'profile_id' => $this->_profile->getId(),
                        'type_id' => 3,)) as $folder) {
            $folderMap[$folder->id] = $folder->getArrayCopy();
        }

        function _buildFolderPath(&$folder, &$folderMap, $path='') {
            if(strlen($path)) {
                $path = $folder['title'] . '/' . $path;
            } else {
                $path = $folder['title'];
            }

            if($folder['parent_id'] && array_key_exists($folder['parent_id'], $folderMap)) {
                return _buildFolderPath($folderMap[$folder['parent_id']], $folderMap, $path);
            } else {
                return '/' . $path;
            }
        };

        // create folder options
        $folders = array();

        foreach($folderMap as $id => $folder) {
            // Don't move a folder into itself
            if(!in_array($id, $selectedFiles)) {
                $folders[$id] = _buildFolderPath($folder, $folderMap);
            }
        }

        // sort
        asort($folders);

        // Prefix in '/'
        $folders = array('0' => '/') + $folders;

        $view->folders = $folders;
        $view->files = $files;
        $view->returnUrl = $request->getPost('return', '');

        return $view;
    }

    /*
     * Bulk file delete handler
     *
     * Takes a posted array: selectedFiles[]
     * return url goes in 'return'
     */
    public function filedeleteAction()
    {
        $request = $this->getRequest();

        // This is never valid with no profile, or for non-owners
        if((!$request->isPost()) || (!is_object($this->_profile)) || 
           (!$this->_isOwner)) {
            return $this->notFoundAction();
        }

        // Check our files.
        $selectedFiles = $request->getPost('selectedFiles', array());

        if(empty($selectedFiles)) {
            return $this->notFoundAction();
        }

        // Make sure they are all integers
        foreach($selectedFiles as $selected) {
            if(!preg_match('/^\d+$/', $selected)) {
                return $this->notFoundAction();
            }
        }

        // Get content table
        $contentTable = $this->getModel()->get('Content');

        // Try to grab 'em
        $files = $contentTable->query(array(
                                'id' => $selectedFiles,
                                'profile_id' => $this->_profile->getId(),
        ));

        // Abort if there's none
        if(!count($files)) {
            return $this->notFoundAction();
        }

        // If we have an act -- let's move
        if($request->getPost('act')) {
            // Get sanitized file list
            $fileIdList = array();
            foreach($files as $file) {
                // We can't delete directories
                // @TODO: this later ?
                if($file->getTypeId() == 3) {
                    continue;
                }

                $fileIdList[] = $file->getId();

                // Let's try to delete.
                $this->getMedia()->deleteContent($file);
            }

            // Just in case it was only folders
            if(count($fileIdList)) {
                $contentTable->beginTransaction();
                
                $this->getModel()->get('Comments')
                                 ->delete(array(
                                    'content_id' => $fileIdList,
                                ));
                $this->getModel()->get('ContentTiersLink')
                                 ->delete(array(
                                    'content_id' => $fileIdList,
                                ));
                $contentTable->delete(array(
                                    'id' => $fileIdList,
                                )
                );
                $contentTable->commitTransaction();
            }

            $returnUrl = $request->getPost('return', '');

            if(strlen($returnUrl)) {
                return $this->redirect()->toUrl($returnUrl);
            } else {
                return $this->redirect()->toRoute('profile-files', array('profile' => $this->_profile->getUrl()));
            }
        }

        // Get a view
        $view = $this->getView(true);

        $view->files = $files;
        $view->returnUrl = $request->getPost('return', '');

        return $view;
    }

    /*
     * Patreon action -- Manage Patreon settings
     *
     */
    public function patreonAction()
    {
        $view = $this->getView();

        // This is never valid with no profile, or for non-owners
        if((!is_object($this->_profile)) || (!$this->_isOwner)) {
            return $this->notFoundAction();
        }

        $request = $this->getRequest();
        $form = new PatreonForm();
        $messages = array();

        // Grab our model
        $profilesTable = $this->getModel()->get('Profiles');
        $view->patreonValidated = false;

        if($request->isPost()) {
            $data = $request->getPost();
            $form->setData($data);

            if($form->isValid()) {
                // Let's see if its valid
                $cleaned = $form->getData();

                // Try to ping the token
                $oauthClient = new OAuth($cleaned['patreon_client_id'],
                                         $cleaned['patreon_client_secret']);

                $refresh = $oauthClient->refresh_token(
                                        $cleaned['patreon_refresh_token'],
                                        null); // unused parameter

                if(array_key_exists('error', $refresh)) {
                    $messages[] = 'The access keys provided are incorrect.  Please double-check them.';
                    $messages[] = 'If this problem continues, feel free to contact Swaggerdile support!  The link is in the header menu.';
                } else {
                    $tokenExpires = time() + $refresh['expires_in'];

                    // Make sure we're not appowing a duplicate
                    $api = new API($refresh['access_token']);
                    $campaign = $api->fetch_campaign();

                    // error check
                    if(array_key_exists('error', $campaign) || (!array_key_exists('data', $campaign)) ||
                       (!count($campaign['data']))) {
                        $messages[] = 'It looks like you don\'t have a Patreon page set up?  This is an odd error.';
                        $messages[] = 'Feel free to try again later, or contact Swaggerdile support using the link in the header.';
                    } else{
                        $myPatId = $campaign['data'][0]['relationships']['creator']['data']['id'];
                        $campaignId = $campaign['data'][0]['id'];

                        foreach($campaign['included'] as $include) {
                            if(($include['type'] == 'user') && ($include['id'] == $myPatId)) {
                                $myPatEmail = $include['attributes']['email'];
                            }
                        }

                        // Check campaign ID
                        $possibleProfile = $profilesTable->fetchByPatreonCampaignId($campaignId);

                        if(count($possibleProfile) && ($possibleProfile[0]->getId() != $this->_profile->getId())) {
                            $messages[] = 'There is another profile already linked to that Patreon.';
                            $messages[] = 'You cannot split your Patreon onto 2 different Swaggerdile profiles.  If you have any questions, please contact Swaggerdile support by clicking the Support link in the header.';
                        }
                    }

                    // Update the form.
                    $form->setData(array(
                                    'patreon_client_id' => $cleaned['patreon_client_id'],
                                    'patreon_client_secret' => $cleaned['patreon_client_secret'],
                                    'patreon_access_token' => $refresh['access_token'],
                                    'patreon_refresh_token' => $refresh['refresh_token'],
                    ));

                    if(!count($messages)) {
                        $profilesTable->update(array(
                                        'patreon_client_id' => $cleaned['patreon_client_id'],
                                        'patreon_client_secret' => $cleaned['patreon_client_secret'],
                                        'patreon_access_token' => $refresh['access_token'],
                                        'patreon_refresh_token' => $refresh['refresh_token'],
                                        'patreon_access_expires' => date('Y-m-d H:i:s', $tokenExpires),
                                        'patreon_campaign_id' => $campaignId,
                                        ), array('id' => $this->_profile->getId()));


                        // Update Patreon Users table
                        $patsUsersTable = $this->getModel()->get('PatreonUsers');

                        if($patsUsersTable->fetchById($myPatId)) {
                            $patsUsersTable->update(array(
                                            'user_id' => $this->getUser()->getId(),
                                            'email' => $myPatEmail,
                                            ), array('id' => $myPatId));
                        } else {
                            $patsUsersTable->insert(array(
                                            'id' => $myPatId,
                                            'user_id' => $this->getUser()->getId(),
                                            'email' => $myPatEmail,
                            ));
                        }

                        $view->patreonValidated = true;

                        // run import
                        $this->_rerunPatreon();
                    }
                }
            }
        } else {
            // Get our form from profile
            $form->setData(array(
                'patreon_client_id' => $this->_profile->getPatreonClientId(),
                'patreon_client_secret' => $this->_profile->getPatreonClientSecret(),
                'patreon_access_token' => $this->_profile->getPatreonAccessToken(),
                'patreon_refresh_token' => $this->_profile->getPatreonRefreshToken(),
            ));

            $view->patreonValidated = (strlen($this->_profile->getPatreonAccessExpires())
                                       &&
                                       (strtotime($this->_profile->getPatreonAccessExpires()) > time()));

        }


        # What are our tiers?
        $view->tiers = $this->getModel()
                            ->get('Tiers')
                            ->setOrderBy(array('price' => 'asc'))
                            ->fetchByProfileId($this->_profile->getId());

        # What are our Patreon tiers?
        $view->patreonTiers = $this->getModel()
                                   ->get('ProfilePatreonTiers')
                                   ->setOrderBy(array('price' => 'asc'))
                                   ->fetchByProfileId($this->_profile->getId());

        $view->form = $form;
        $view->messages = $messages;
        return $view;
    }

    /*
     * Patreon import
     *
     * Parameters: import_profile_title, import_profile_images,
     * import_profile_text, import_milestones, import_tiers, import_all
     */
    public function importAction()
    {
        $request = $this->getRequest();

        // This is never valid with no profile, or for non-owners
        // Or for non-POST.
        if((!$request->isPost()) || (!is_object($this->_profile)) ||
           (!$this->_isOwner)) {
            return $this->notFoundAction();
        }

        $data = $request->getPost();

        // Get our models
        $profilesTable = $this->getModel()->get('Profiles');
        $milestonesTable = $this->getModel()->get('Milestones');
        $tiersTable = $this->getModel()->get('Tiers');

        // Grab our data
        $patreonApi = new API($this->_profile->getPatreonAccessToken());

        $campaign = $patreonApi->fetch_campaign();
        $profileData = $campaign['data'][0]['attributes'];

        $update = array();

        // Start transaction
        $profilesTable->beginTransaction();

        if(array_key_exists('import_profile_title', $data) &&
           $data['import_profile_title']) {
            $update['title'] = $profileData['creation_name'];
        }

        if(array_key_exists('import_profile_images', $data) &&
           $data['import_profile_images'] &&
           array_key_exists('image_url', $profileData) &&
           strlen($profileData['image_url'])) {
            $tempFile = tempnam(sys_get_temp_dir(), 'pat');
            $fd = fopen($tempFile, 'w');
            fputs($fd, file_get_contents("https:{$profileData['image_url']}"));
            fclose($fd);

            Media::storeProfileIcon($this->_profile, $tempFile);
            unlink($tempFile);
        }

        if(array_key_exists('import_profile_text', $data) &&
           $data['import_profile_text']) {
            $update['content'] = $profileData['summary'];
        }

        if(array_key_exists('import_milestones', $data) &&
           $data['import_milestones']) {
            // Delete existing milestones, if there.
            $milestonesTable->delete(array(
                                    'profile_id' => $this->_profile->getId()));

            // import new ones.
            foreach($campaign['included'] as $included) {
                if($included['type'] == 'goal') {
                    $milestonesTable->insert(array(
                        'profile_id' => $this->_profile->getId(),
                        'title' => $included['attributes']['title'],
                        'content' => $included['attributes']['description'],
                        'price' => $included['attributes']['amount_cents'] / 100,
                    ));
                }
            }
        }

        if(array_key_exists('import_tiers', $data) &&
           $data['import_tiers']) {
            // Delete existing tiers
            $tiersTable->delete(array(
                        'profile_id' => $this->_profile->getId()));

            // Import new ones
            foreach($campaign['included'] as $included) {
                if($included['type'] == 'reward' &&
                   $included['id'] > 0) { // "everyone" and
                                          // "patreons only" tiers
                    $tiersTable->insert(array(
                        'profile_id' => $this->_profile->getId(),
                        'title' => $included['attributes']['title'],
                        'content' => $included['attributes']['description'],
                        'is_shippable' => (int)$included['attributes']['requires_shipping'],
                        'price' => $included['attributes']['amount_cents']/100,
                        'max_available' => (int)$included['attributes']['user_limit'] ? (int)$included['attributes']['user_limit'] : null,
                    ));
                }
            }

            // Clear out the stuff in Patreon tables in order to rebuild it.
            $this->getModel()->get('ProfilePatreons')->delete(array(
                                            'profile_id' => $this->_profile->getId()));
            $this->getModel()->get('ProfilePatreonTiers')->delete(array(
                                            'profile_id' => $this->_profile->getId()));
        }

        if(count($update)) {
            $profilesTable->update($update,
                                   array('id' => $this->_profile->getId()));
        }

        // Commit transaction
        $profilesTable->commitTransaction();

        // run import
        $this->_rerunPatreon();

        return $this->redirect()->toRoute('profile-patreon',
           array(
               'profile' => $this->_profile->getUrl(),
        ));
    }

    /*
     * Perform the mapping of patreon rewards to tiers
     *
     */
    public function tiermapAction()
    {
        $request = $this->getRequest();

        // This is never valid with no profile, or for non-owners
        // Or for non-POST.
        if((!$request->isPost()) || (!is_object($this->_profile)) ||
           (!$this->_isOwner)) {
            return $this->notFoundAction();
        }

        $data = $request->getPost();

        // Process the linkages
        $ppTiersTable = $this->getModel()->get('ProfilePatreonTiers');
        $tiers = $this->getModel()->get('Tiers');

        // grab our available ID's
        $availIds = array();

        foreach($tiers->fetchByProfileId($this->_profile->getId()) as $tier) {
            $availIds[$tier->getId()] = true;
        }

        $ppTiersTable->beginTransaction();
        foreach($ppTiersTable->fetchByProfileId($this->_profile->getId()) as $ppTier) {
            if(array_key_exists("{$ppTier->getId()}", $data['patreonReward']) &&
               array_key_exists((int)$data['patreonReward']["{$ppTier->getId()}"],
                                $availIds)) {
                $tierId = (int)$data['patreonReward']["{$ppTier->getId()}"];

                $ppTiersTable->update(array(
                                    'tier_id' => $tierId ? $tierId : null
                            ), array('id' => $ppTier->getId()));
            } else {
                $ppTiersTable->update(array(
                                    'tier_id' => null
                            ), array('id' => $ppTier->getId()));
            }
        }
        $ppTiersTable->commitTransaction();

        return $this->redirect()->toRoute('profile-patreon',
           array(
               'profile' => $this->_profile->getUrl(),
        ));
    }
}
