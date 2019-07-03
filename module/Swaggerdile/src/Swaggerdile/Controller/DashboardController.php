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
use Swaggerdile\Form\Settings as SettingsForm;
use Hautelook\Phpass\PasswordHash;
use Zend\Db\Sql\Sql;

use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Customer;

use Patreon\API;
use Patreon\OAuth;

class DashboardController extends Controller
{
    /*
     * View subscribed content
     *
     * The following URL parameters are understood :
     *
     * @param string o     - Sort column
     * @param string d     - Sort direction
     * @param string p     - Page number
     */
    public function indexAction()
    {
        // Set up our view
        $view = $this->getView();

        // Get our request
        $request = $this->getRequest();

        // Get user
        $user = $this->getUser();

        // Must be logged in
        if(!is_object($user)) {
            return $this->redirect()->toRoute('home');
        }

        // Get content table
        $contentTable = $this->getModel()->get('Content');

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

        $content = $contentTable->setOrderBy("${sortOrder} ${orderDirection}")
                                ->setLimit(61)
                                ->setOffset($page * 60)
                                ->fetchSubscribedContentForUser($user);

        $view->content = $content;

        // And limit / offset
        $view->page = $page;
        $view->order = $order;
        $view->orderDirection = $orderDirection;
        $view->pageSize = 60;

        return $view;
    }

    /*
     * Subscription management page -- see all subscriptions and manage
     * them.
     *
     */
    public function subscriptionsAction()
    {
        // Get user
        $user = $this->getUser();

        // Must be logged in
        if(!is_object($user)) {
            return $this->redirect()->toRoute('home');
        }

        // Get user subscriptions
        $subscriptionsTable = $this->getModel()->get('Subscriptions');

        // Get our view
        $view = $this->getView();

        $view->subscriptions = $subscriptionsTable->fetchSubscriptionsWithTier($user->getId());

        return $view;
    }

    /*
     * User settings page
     */
    public function settingsAction()
    {
        // Get user
        $user = $this->getUser();

        // Must be logged in
        if(!is_object($user)) {
            return $this->redirect()->toRoute('home');
        }

        // Grab form
        $form = new SettingsForm();

        // Grab request
        $request = $this->getRequest();

        // Get View
        $view = $this->getView();

        // Messages, if any
        $messages = array();

        // Grab user meta
        $meta = $user->getMeta('', true);

        // Get the user model
        $userTable = $this->getModel()->get('User');
        $subscriptionsTable = $this->getModel()->get('Subscriptions');
        $cpmTable = $this->getModel()->get('ChildPaymentMethods');

        // Initialize Stripe
        Stripe::setApiKey($this->getConfig()['stripe_secret_key']);

/* This doesn't work anymore - this is Swaggerdile stuff that was broke
 * when i switched the credit card to my own stripe account.
        // Get common subscription data
        $subs = $subscriptionsTable->fetchActiveSubscriptionsWithPayment($user->getId());

        $customerInfo = false;
        
        // Get payment data if we have it -- all the meta's should
        // be the same for now.
        if(count($subs)) {
            $customerInfo = \Stripe\Customer::retrieve($subs[0]->getParentMeta());
        }
*/
        $customerInfo = false;

        // Process request or set defaults as applicable
        if($request->isPost()) {
            $form->setData($request->getPost());

            if($form->isValid()) {
                // Grab the clean data
                $data = $form->getData();

                // Check to see if the email address is already in use.
                $otherEmails = $userTable->fetchByUserEmail($data['email']);

                // Are we trying to change password?  If so, make sure confirm
                // matches.
                if(array_key_exists('password', $data) &&
                   strlen($data['password']) &&
                   ($data['password'] != $data['confirmpassword'])) {
                    $messages[] = 'Your password and password confirmation do not match.';
                } elseif(count($otherEmails) && ($otherEmails[0]->getId() != $user->getId())) {
                    $messages[] = 'The email you have entered is already in use by another user.  Please try a different email address.';
                } else {
                    $updateUser = array(
                                        'user_email' => $data['email'],
                    );

                    if(array_key_exists('password', $data) && strlen($data['password'])) {
                        $pwHasher = new PasswordHash(8, true);
                        $updateUser['user_pass'] = $pwHasher->HashPassword($data['password']);
                    }

                    $userTable->beginTransaction();
                    $userTable->update($updateUser, array('ID' => $user->getId()));

                    // Do meta calls
                    $metaSql = new Sql($userTable->getAdapter(), 'tigerd_usermeta');

                    if($data['info_optout'] && ((!array_key_exists('info_optout', $meta)) ||
                                                (!(int)$meta['info_optout']))) {
                        // insert new one
                        $obj = $metaSql->insert()->values(array(
                                            'user_id' => $user->getId(),
                                            'meta_key' => 'info_optout',
                                            'meta_value' => '1',
                        ));

                        $metaSql->prepareStatementForSqlObject($obj)->execute();
                    } elseif((!$data['info_optout']) && (array_key_exists('info_optout', $meta) &&
                                                         (int)$meta['info_optout'])) {
                        $obj = $metaSql->delete()->where(array(
                                            'user_id' => $user->getId(),
                                            'meta_key' => 'info_optout',
                        ));

                        $metaSql->prepareStatementForSqlObject($obj)->execute();
                    }

                    if($data['billing_optout'] && ((!array_key_exists('billing_optout', $meta)) ||
                                                (!(int)$meta['billing_optout']))) {
                        // insert new one
                        $obj = $metaSql->insert()->values(array(
                                            'user_id' => $user->getId(),
                                            'meta_key' => 'billing_optout',
                                            'meta_value' => '1',
                        ));

                        $metaSql->prepareStatementForSqlObject($obj)->execute();
                    } elseif((!$data['billing_optout']) && (array_key_exists('billing_optout', $meta) &&
                                                         (int)$meta['billing_optout'])) {
                        $obj = $metaSql->delete()->where(array(
                                            'user_id' => $user->getId(),
                                            'meta_key' => 'billing_optout',
                        ));

                        $metaSql->prepareStatementForSqlObject($obj)->execute();
                    }

                    // Handle card changes
                    $postData = $request->getPost();

                    if((!empty($postData['stripeToken'])) &&
                       ($postData['stripeTokenType'] == 'card') &&
                       (is_object($customerInfo))) {
                        $card = $customerInfo->sources->create(array(
                                        'source' => $postData['stripeToken'],
                        ));

                        $cpmTable
                             ->insert(array(
                                        'user_payment_method_id' =>
                                            $subs[0]->getUserPaymentMethodId(),
                                        'metadata' => $card->id,
                        ));

                        $messages[] = '...New card added!';
                    }

                    foreach($postData as $key => $val) {
                        $matches = array();

                        if(preg_match('/^sub_(\d+)$/', $key, $matches)) {
                            $res = $cpmTable->fetchByMetadata($val);

                            if(count($res)){
                                $subscriptionsTable->update(array(
                                        'child_payment_method_id' => $res[0]->getId(),
                                    ),array(
                                        'id' => $matches[1],
                                        'user_id' => $user->getId(),
                                    )
                                );
                            }
                        }
                    }

                    $userTable->commitTransaction();

                    // Refresh
                    $subs = $subscriptionsTable->fetchActiveSubscriptionsWithPayment($user->getId());

                    $messages[] = 'Saved!';
                }
            }
        } else{
            $form->setData(array(
                'email' => $user->getUserEmail(),
                'info_optout' => array_key_exists('info_optout', $meta) ? $meta['info_optout'] : '0',
                'billing_optout' => array_key_exists('billing_optout', $meta) ? $meta['billing_optout'] : '0',
            ));
        }

        // Fetch and combine our card data.
        $cardMap = array();

        // Get payment data if we have it -- all the meta's should
        // be the same for now.
        if(count($subs)) {
            $customerInfo = \Stripe\Customer::retrieve($subs[0]->getParentMeta());

            foreach($customerInfo->sources->data as $card) {
                $cardMap[$card->id] = "{$card->brand} (**** **** **** {$card->last4}) expiring {$card->exp_month}/{$card->exp_year}";
            }
        }

        $view->subscriptions = $subs;
        $view->cards = $cardMap;

        // Push form to view
        $view->form = $form;

        // Push errors to view
        $view->messages = $messages;

        // Pass our public token
        $view->stripePublishKey = $this->getConfig()['stripe_publish_key'];

        return $view;
    }

    /*
     * Action to handle financial matters
     *
     * The following URL parameters are understood :
     *
     * @param string o     - Sort column
     * @param string d     - Sort direction
     * @param string p     - Page number
     */
    public function financialAction()
    {
        // Get view
        $view = $this->getView();

        // Get user
        $user = $this->getUser();

        // Must be logged in
        if(!is_object($user)) {
            return $this->redirect()->toRoute('home');
        }

        // Must have a profile
        if(!count($user->getProfiles())) {
            return $this->redirect()->toRoute('dashboard');
        }

        // Get our meta
        $userMeta = $user->getMeta();

        // Get request
        $request = $this->getRequest();

        // Load the balance sheet
        $sheetTable = $this->getModel()->get('BalanceSheet');

        // Are we using WF surepay?
        $wfFirstname = $wfLastname = $wfEmail = false;

        if(array_key_exists('wf_recipient', $userMeta) &&
           strlen($userMeta['wf_recipient'])) {
            $wf_info = json_decode($userMeta['wf_recipient'], true);

            $wfFirstname = $wf_info['first_name'];
            $wfLastname = $wf_info['last_name'];
            $wfEmail = $wf_info['email'];
        }

        // Get our current balance
        $currentBalanceResult = $sheetTable->setLimit(1)->setOffset(0)
                                           ->setOrderBy('id desc')
                                           ->fetchByUserId($user->getId());

        $currentBalance = 0;

        if(!empty($currentBalanceResult)) {
            $currentBalance = $currentBalanceResult[0]->getBalance();
        }

        // Keep track of user messages.
        $messages = array();

        // Process any posting done
        if($request->isPost()) {
            /*
             * We will be posting the following things in these
             * combinations:
             *
             * * REQUIRED: is_us_citizen
             * * MAY HAVE: upload_document  (file)
             *
             * THESE REQUIRE has_legal_docs AND is_us_citizen
             * meta.
             *
             * * MAY HAVE: paypal_email
             * * MAY HAVE: wf pay info
             * * MAY HAVE: payout request
             */
            $isUsCitizen = (int)$request->getPost('is_us_citizen', 0);

            // Set meta if we need
            if((!array_key_exists('is_us_citizen', $userMeta)) ||
               ($userMeta['is_us_citizen'] != $isUsCitizen)) {
                $user->setMeta('is_us_citizen', $isUsCitizen);
            }

            // Allow the upload of documents if available.
            if(in_array((int)$isUsCitizen, array(1,2))) {
                $files = $request->getFiles();

                if(array_key_exists('upload_document', $files) &&
                   $files['upload_document']['size']) {
                    /* 
                     * Let's process the file.
                     *
                     * We need to :
                     *
                     * 1. Encrypt it
                     * 2. Send it up to the doc-repo
                     * 3. Email support about it
                     */
                    $crypto = $this->_locator->get('Crypto');
                    $cryptoTempFile = "/tmp/{$user->getId()}";
                    $crypto->seal(
                                $files['upload_document']['tmp_name'],
                                $cryptoTempFile);

                    // Curl it
                    $crypto->sendToRepo($cryptoTempFile);

                    // Delete it
                    unlink($cryptoTempFile);

                    $email = $this->_locator->get('Email');
                    $email->send(
                                'support@tigerdile.com',
                                'New User Documentation Submission',
                                <<<MSG
Hello Supreme Tigerdile Overlords,

There is a document to review.  Document was submitted by:

User ID: {$user->getId()}
User Name: {$user->getUserLogin()}
Email: {$user->getUserEmail()}

Enjoy!

- Swaggerdile
MSG
                    );

                    $user->setMeta('has_legal_docs', '1');
                    $userMeta['has_legal_docs'] = 1;

                    $messages[] = 'Documents uploaded!  Thank you!';
                }

                // And allow the setting of payment information if
                // we have legal docs AND citizen indicator set.
                if(array_key_exists('has_legal_docs', $userMeta) &&
                   (int)$userMeta['has_legal_docs']) {
                    // Paypal address, if set
                    $paypalEmail = $request->getPost('paypal_email', '');
                    $user->setMeta('paypal_email', $paypalEmail);
                    $userMeta['paypal_email'] = $paypalEmail;

                    // US Citizens only
                    if($isUsCitizen == 1) {
                        // Bank info, if set
                        $wfFirstname = $request->getPost('wf_firstname', '');
                        $wfLastname = $request->getPost('wf_lastname', '');
                        $wfEmail = $request->getPost('wf_email', '');

                        $wf_info = array(
                                        'first_name' => $wfFirstname,
                                        'last_name' => $wfLastname,
                                        'email' => $wfEmail,
                        );

                        $user->setMeta('wf_recipient', json_encode($wf_info));
                    }
                }
            }

            // Process payout requests
            $payoutType = 2; // paypal

            switch($request->getPost('act')) {
                case 'Payout Via WF SurePay':
                    $payoutType = 1;
                case 'Payout Via PayPal':
                    // Current balance must be over $1.00
                    if($currentBalance < 1) {
                        $messages[] = 'You cannot do a withdraw if your balance is less than $1.00.  The fees would eat you alive!';
                        break;
                    }

                    // Get our requested amount, get our paypal target, make sure
                    // they are correct
                    $payoutAmount  = preg_replace('/[^\d\.]/', '',
                                                  $request->getPost('sendAmount', '0'));
                    $payoutTo = $request->getPost('sendToPaypal', '');

                    if((!is_numeric($payoutAmount) || ($payoutAmount < 1))) {
                        $messages[] = 'You cannot withdraw less than 1 dollar from your account.';
                        break;
                    } elseif($payoutAmount > $currentBalance) {
                        $messages[] = 'You cannot withdraw more than you have in your account.';
                        break;
                    } elseif($payoutType == 2) { // validate email
                        $validator = new \Zend\Validator\EmailAddress();

                        if((!strlen($payoutTo)) || (!$validator->isValid($payoutTo))) {
                            $messages[] = 'The paypal email address you have provided does not seem to be valid.  Please check it and try again.';
                            break;
                        }
                    }

                    $payoutRequestsTable = $this->getModel()->get('PayoutRequests');
                    $payoutRequestsTable->beginTransaction();

                    // Make a balance sheet entry.
                    $sheetTable->addTransaction(
                                    $user->getId(),
                                    \Swaggerdile\Model\BalanceSheet::TRANSACTION_WITHDRAW,
                                    -1 * $payoutAmount,
                                    null, null, null,
                                    'Withdraw Request');

                    $payoutRequestsTable->insert(array(
                                        'type_id' => $payoutType,
                                        'user_id' => $user->getId(),
                                        'created' => date('Y-m-d H:i:s'),
                                        'amount' => $payoutAmount,
                                        'is_paid' => 0,
                                        'balance_sheet_id' => $sheetTable->getLastInsertValue(),
                                        'target' => $payoutType == 2 ? $payoutTo : "{$wfLastname}, {$wfFirstname}, {$wfEmail}",
                    ));

                    // Email overlords
                    $email = $this->_locator->get('Email');
                    $email->send(
                                'support@tigerdile.com',
                                'User Payout Request',
                                <<<MSG
Hello Supreme Tigerdile Overlords,

Payout request made.  Go here to process:
https://www.swaggerdile.com/swaggermin/payout

User ID: {$user->getId()}
User Name: {$user->getUserLogin()}
Email: {$user->getUserEmail()}

Enjoy!

- Swaggerdile
MSG
                    );

                    $payoutRequestsTable->commitTransaction();

                    $currentBalance = $currentBalance-$payoutAmount;

                    $messages[] = 'Payout request submitted.  Please allow up to 5 business days for processing.';
                default:
            }
        }

        // Grab query parameters
        $order = $request->getPost('o', $request->getQuery('o', 'id'));
        $orderDirection = $request->getPost('d', $request->getQuery('d', 'desc'));
        $page = (int)$request->getPost('p', $request->getQuery('p', 0));

        // Set up the sort order, as this will apply all over.  Scrub data
        if($orderDirection != 'asc') {
            $orderDirection = 'desc';
        }

        // This array contains the list of valid sort columns.
        // Currently only created is supported.
        if(!in_array($order, array('id', 'created'))) {
            $sortOrder = 'sd_balance_sheet.id';
            $order = 'id';
        } else {
            $sortOrder = 'sd_balance_sheet.' . $order;
        }

        $sheet = $sheetTable->setOrderBy("${sortOrder} ${orderDirection}")
                            ->setLimit(61)
                            ->setOffset($page * 60)
                            ->fetchByUserId($user->getId());

        // Pass our public token
        $view->sheet = $sheet;
        $view->wfFirstname = $wfFirstname;
        $view->wfLastname = $wfLastname;
        $view->wfEmail = $wfEmail;
        $view->meta = $userMeta;
        $view->page = $page;
        $view->orderDirection = $orderDirection;
        $view->order = $order;
        $view->pageSize = 60;
        $view->messages = $messages;
        $view->currentBalance = $currentBalance;

        return $view;
    }
}
