<?php
/**
 * CheckoutController
 *
 * Class for handling checkout.
 *
 * @author sconley
 */

namespace Swaggerdile\Controller;

use Swaggerdile\Controller;
use Swaggerdile\Form\Profile as ProfileForm;
use Swaggerdile\Form\Post as PostForm;
use Stripe\Stripe;
use Stripe\Charge;
use Stripe\Customer;
use Zend\View\Helper\ServerUrl;

class CheckoutController extends Controller
{
    /*
     * Index action
     *
     * The checkout itself occurs here, handed off from ProfileController
     * typically but probably expanded later.
     *
     * Takes the order as a parameter.  Confirms order is not more than 2
     * hours old before allowing action.
     */
    public function indexAction()
    {
        /*
         * Conditions :
         *
         * 1. We should be logged in.
         * 2. We should have an order ID.
         * 3. That order ID cannot be older than 2 hours.
         * 4. Order ID should not be completed.
         * 5. Order should be owned by our user.
         */
   
        // Grab our view
        $view = $this->getView();

        // Stack in a message
        $view->message = false;

        // Grab our user, if he exists
        $user = $this->getUser();

        // Push it to view
        $view->user = $user;

        if(!is_object($user)) {
            // Hard stop here -- but pass order ID in first.
            $view->orderId = (int)$this->params()->fromRoute('orderId', 0);
            return $view;
        }

        // Stack some things in our view
        $view->order = false;
        $view->orderItems = false;
        $view->sold = false;

        // Our model
        $model = $this->getModel();
        $ordersTable = $model->get('Orders');
        $orderItemsTable = $model->get('OrderItems');
        $cpmTable = $model->get('ChildPaymentMethods');
        $pmTable = $model->get('UserPaymentMethods');
        $profilesTable = $model->get('Profiles');
        $usersTable = $model->get('User');

        // Try to grab our order ID.
        $orderId = (int)$this->params()->fromRoute('orderId', 0);
        $order = false;
        $orderItems = array();

        if((!$orderId) || (!is_object($order = $ordersTable->fetchById($orderId)))) {
            $view->message = "You may have gotten to this page in error, for which we apologize.  Please use the 'Support' link in the header to report how you got to this page.";
            return $view;
        }

        // Make sure our user owns the order and the order is valid.
        if(($order->getUserId() != $user->getId()) ||
           (($order->getCompleted() != '0000-00-00 00:00:00') && 
            ($order->getCompleted() != null))) {
            $view->message = "The order you trying to access is not available.  If you have gotten this error by clicking a link, please use the 'Support' link in the header to report how you made this happen so we can fix it in the future.";
            return $view;
        }

        // At this point, order is valid.
        $orderItems = $orderItemsTable->fetchByOrderId($orderId);

        // Get our payment methods
        $userPaymentMethods = $user->getPaymentMethods();

        // Set up our view
        $view->order = $order;
        $view->orderItems = $orderItems;

        // Pass our public token
        $view->stripePublishKey = $this->getConfig()['stripe_publish_key'];

        // Push our payment methods through
        $view->paymentMethods = $userPaymentMethods;

        // Our next action is based on if we're posting or not.
        $request = $this->getRequest();

        // Initialize Stripe
        Stripe::setApiKey($this->getConfig()['stripe_secret_key']);

        if($request->isPost()) {
            /*
             * We are, at present, expecting a stripeToken, stripeTokenType,
             * and stripeEmail.
             *
             * We may get a stripeCard if they are using an existing card
             * instead.
             */

            $data = $request->getPost();

            // Do we have required data points?
            if(((!array_key_exists('stripeCard', $data)) || empty($data['stripeCard'])) &&
               ((!array_key_exists('stripeToken', $data)) || empty($data['stripeToken']))) {
                $view->message = 'Invalid Stripe card data received.  Please try again.';
                return $view;
            }

            // do we have a customer ID ?
            $stripeCustomerId = $selectedMethodId = false;

            foreach($userPaymentMethods as $method) {
                // @TODO : use constants instead of hardcoded ID
                if($method->getPaymentMethodId() == 1) {
                    $stripeCustomerId = $method->getMetadata();
                    $selectedMethodId = $method->getId();
                    break;
                }
            }

            // start transaction
            $ordersTable->beginTransaction();

            // See what our landscape is like here.
            // Exception handling is the same.
            try {
                // Get our card token
                $cardToken = false;

                if(($stripeCustomerId !== false) && (strlen($data['stripeCard']))) {
                    $cardToken = $data['stripeCard'];
                } elseif($stripeCustomerId === false) {
                    // create customer
                    $customer = Customer::create(array(
                                            'source' => $data['stripeToken'],
                                            'email' => $user->getUserEmail(),
                    ));

                    // Insert it into our DB.
                    $pmTable->insert(array(
                                                'payment_method_id' => 1, // @TODO : use constant, not hardcode
                                                'user_id' => $user->getId(),
                                                'title' => 'Stripe',
                                                'metadata' => (string)$customer->id,
                    ));

                    $selectedMethodId = $pmTable->getLastInsertValue();
                    $stripeCustomerId = $customer->id;
                    $cardToken = $customer->sources->data[0]->id;
                } else {
                    $customer = Customer::retrieve($stripeCustomerId);
                    $card = $customer->sources->create(array(
                                            'source' => $data['stripeToken'],
                    ));

                    $cardToken = $card->id;
                }

                $charge = false;

                // Try to charge it, if we need to
                if($order->getTotalPrice() > 0) {
                    $charge = Charge::create(array(
                                        'amount' => $order->getTotalPrice() * 100,
                                        'currency' => 'usd',
                                        'customer' => $stripeCustomerId,
                                        'capture' => true,
                                        'source' => $cardToken,
                                        'expand' => array('balance_transaction'),
                    ));
                }

                // Update our DB with the new child record if needed.
                $childCardId = $cpmTable->fetchOrInsert($selectedMethodId,
                                                        $cardToken);
            } catch(\Stripe\Error\Card $e) {
                $ordersTable->rollbackTransaction();
                $view->message = $e->getMessage();
                return $view;
            }

            /*
             * Okay, so at this point, we're in a transaction, and we have
             * theoretically survived charging the card.
             *
             * Let's do the following:
             *
             * 1. Mark the order as complete.
             * 2. Update the profiles table if necesary
             * 3. Update the creator's balance sheet, extracting fees,
             *    if necessary
             * 4. Commit transaction.
             * 5. Redirect user to the profile they subscribed to.
             */
            $ordersTable->update(array(
                                    'completed' => date('Y-m-d H:i:s'),
                                ), array('id' => $order->getId()));

            // Profiles we paid for
            $paidProfiles = array();

            // @TODO : weird mix of code that supports multiple order items
            // and code that does not.  We should support multiple items
            // all the way down, but we don't yet.
            foreach($orderItems as $orderItem) {
                // Item ID's between 1 and 3 (inclusive)
                if($orderItem->getItemId() < 4) {
                    $toUpdate = $profilesTable->fetchById(
                                    $orderItem->getProfileId()
                    );

                    // Update date.  We use the DateTime object to
                    // provide date math
                    $date = new \DateTime('now', new \DateTimeZone('America/New_York'));

                    $dueDate = $toUpdate->getDueTimestamp();

                    if(time() < $dueDate) {
                        $date->setTimestamp($dueDate);
                    }

                    $profilesTable->update(array(
                        'stream_type_id' => $orderItem->getItemId(),
                        'last_paid_on' => $date->format('Y-m-d 23:59:59'),
                    ), array('id' => $toUpdate->getId()));

                    $paidProfiles[] = $toUpdate;
                } else {
                    // Not supported yet
                    throw new \Exception('Unsupported item Id');
                }
            }

            /*
             * This balance sheet stuff will be useful later, but 
             * has no purpose at the moment.
             */
            if(0) {
                // Grab our target user from profile
                // @TODO : $orderItems is ssometimes handled for multiple,
                // sometimes only handled for single.  Decide what you're going
                // to do universally and do it -- assuming single profile for now
                $targetProfile = $profilesTable->fetchById($orderItems[0]->getProfileId());

                // Balance sheet work, if needed
                if($charge !== false) {
                    $balanceSheetTable = $model->get('BalanceSheet');

                    // Calculate fee -- @TODO make fee adjustable in configuration.
                    // @TODO : VAT tax integration
                    $fee = -1 * round(
                                 ($charge->balance_transaction->fee/100) +
                                 (0.03 * $order->getTotalPrice()),
                                 2, PHP_ROUND_HALF_DOWN);

                    // Update the balance sheet
                    $balanceSheetTable->addTransaction($targetProfile->getOwnerId(),
                                                \Swaggerdile\Model\BalanceSheet::TRANSACTION_PAYMENT_RECEIVED,
                                                $order->getTotalPrice(),
                                                $user->getId(),
                                                $order->getId(),
                                                // we support plural subscriptions
                                                // but don't support paying to
                                                // multiple :P
                                                $lastSubscriptionId,
                                                "Payment from {$user->getDisplayName()}"
                            )
                             ->addTransaction($targetProfile->getOwnerId(),
                                                \Swaggerdile\Model\BalanceSheet::TRANSACTION_FEE,
                                                $fee,
                                                null,
                                                $order->getId(),
                                                $lastSubscriptionId,
                                                "Tigerdile's happy chunk!  NOM!"
                    );
                }
            }

            // Commit
            $ordersTable->commitTransaction();

            $monthlyFee = number_format($orderItems[0]->getTierPrice() + $orderItems[0]->getExtraPrice(), 2);

            // Email

            /*
             * - Receipt to user
             * - Notification to profile owner if different from user
             * - Notify admins
             */
            $emailService = $this->_locator->get('Email');
            $total = number_format($order->getTotalPrice(), 2);
            $title = htmlentities($paidProfiles[0]->getTitle());

            // @TODO: Support multiple item types, also support more
            // than one profile.

            // Assemble our receipt message and our notification message
            $receipt = <<<EOM
<p>You just paid \${$total} to support {$title}!</p>
EOM
            ;

            $toOwner = '';

            if($paidProfiles[0]->getOwnerId() != $user->getId()) {
                $helper = htmlentities($user->getUserLogin());
                $toOwner = <<<EOM
<p>Hey!  Just letting you know that {$helper} has sponsored your stream!
  Cool, huh?
</p>
<p>You've been sponsored at the {$orderItems[0]->getItemTitle()} ?>
   level, a \${$total} value!
</p>
EOM
                ;
            }

            if($orderItems[0]->getItemId() == 3) {
                $perks = <<<EOM
<p>Because you've supported Tigerdile at the VIP level, you get some
   additional perks!  It's up to you to take advantage of them.
</p>
<p>The first time you buy at the VIP level, you get either a Tigerdile
   Tadpole plush or a Tigerdile Tote Bag.  Your choice!  You need to
   give us your address, though; you can reply back to this email
   with your address and we'll send it right away.
</p>
<p>Also, you can set up a VIP image.  This is a 30 x 700 banner that
   displays on the chat listing instead of your description.  It's
   a real attention-getter!  You can set it up on your settings
   page, here:
</p>
<p>
  <a href="https://www.tigerdile.com/stream/{$paidProfiles[0]->getUrl()}/edit">
  https://www.tigerdile.com/stream/{$paidProfiles[0]->getUrl()}/edit
  </a>
</p>
<p>It's called "VIP Image" under the "Appearance" tab.</p>
<p>You also get a banner on Tigerdile's home page.  This isn't automatic at
   the moment, but we'll help you get it set up.  The image can be up to
   300 pixels wide and however many pixels tall you like.  Just respond
   back to this email and we'll set it up for you!
</p>
EOM
                ;

                if(strlen($toOwner)) {
                    $toOwner .= $perks;
                } else {
                    $receipt .= $perks;
                }
            }

            $ending = <<<EOM
<p>It means a lot when you support Tigerdile.  We're a small service and
   every little bit helps keep us in business.  We really rely on you guys!
</p>
<p>If you have any questions, concerns, or comments, feel free to respond
   to this message!  We actually send from a real email address.
</p>
<p>&nbsp;</p>
<p>- The Tigerdile Admins</p>
EOM
            ;

            $receipt .= $ending;

            if(strlen($toOwner)) {
                $toOwner .= $ending;
            }

            $emailService
                ->send($user->getUserEmail(),
                       "Receipt for your Tigerdile Payment",
                       $receipt. true);

            if(strlen($toOwner)) {
                $targetUser = $usersTable->fetchById($paidProfiles[0]->getOwnerId());

                $emailService
                    ->send($targetUser->getUserEmail(),
                           "Your Tigerdile stream got sponsored!",
                           $toOwner, true);
            }

            $emailService
                ->send('support@tigerdile.com',
                       'Sale made!',
                       <<<EOM
<p>The profile {$title} paid \${$total} to continue streaming!</p>
EOM
            );


            // Show success
            $view->sold = true;
            return $view;
        } elseif(strtotime($order->getCreated()) < (time()-(2*60*60))) {
            // Make sure order is not expired, but only if the user isn't
            // POSTing card info cause we don't want to charge them
            // then expire them.
            $view->message = "Your order has expired; you have two hours from start to finish to complete your order.  Please use your browser's back button try and submit your order again.";
            $view->order = false;
            return $view;
        }

        return $view;
    }

    /*
     * The order screen is for streaming services.  Pick your level,
     * then move on to checkout.
     */
    public function orderAction()
    {
        $view = $this->getView();

        // Only logged in people please
        $user = $this->getUser();

        if(!is_object($user)) {
            $helper = new ServerUrl();
            return $this->redirect()->toUrl('/login?returnUrl=' . urlencode($helper->__invoke(true)));
        }

        // Grab profiles
        $profiles = $this->getUser()->getProfiles();
        $view->profiles = $profiles;

        $request = $this->getRequest();

        $username = '';
        $level = 0;
        $messages = array();

        /*
         * NOTE: This could all be done with a Zend Form and that would likely
         * be a good idea.  However, it was just 2 fields and I wanted a lot
         * of fine control over how they were displayed ... so I just wrote
         * it the old fashioned inline way.
         */
        if($request->isPost()) {
            // Look up target user, if different from us.
            $data = $request->getPost();

            // Default to me if username is blank
            if(!array_key_exists('username', $data)) {
                $username = $user->getUserLogin();
            } else {
                $username = trim($data['username']);
            }

            if(($username != $user->getUserLogin()) || (empty($profiles))) {
                // Try to load profiles for the provided user.
                $targetUser = $this->getModel()->get('User')
                                   ->fetchByUserLogin($username);

                if(!count($targetUser)) {
                    $messages[] = "I'm sorry, we couldn't find that username.  Please check for typo's.";
                } elseif(!count($targetProfile = $targetUser[0]->getProfiles())) {
                    $messages[] = "I'm sorry, that user does not seem to be a Tigerdile streamer.";
                }
            } else {
                $targetProfile = $profiles;
                $targetUser = $user;
            }

            // Check level
            if((!array_key_exists('level', $data)) || (!($level = (int)$data['level']))) {
                $messages[] = "Please select a stream level.";
            } else {
                // Tamperproof
                if($level > 3) {
                    $level = 3;
                } elseif($level < 1) {
                    $level = 1;
                }
            }

            // Process if we need to (no errors)
            if(empty($messages)) {
                // get our price
                $item = $this->getModel()->get('Items')->fetchById($level);

                $total = $item->getPrice();

                // Start a transaction
                $orderTable = $this->getModel()->get('Orders');

                $orderTable->beginTransaction();
                $orderTable->insert(array(
                    'created' => date('Y-m-d H:i:s'),
                    'completed' => null,
                    'user_id' => $this->getUser()->getId(),
                    'total_price' => $total,
                    'is_prorate' => 0,
                    'is_recurring' => 0,
                    'full_price' => $total,
                ));

                $orderId = $orderTable->getLastInsertValue();

                // Create our item
                $this->getModel()->get('OrderItems')->insert(array(
                    'order_id' => $orderId,
                    'item_id' => $level,
                    'extra_price' => $total,
                    'profile_id' => $targetProfile[0]->getId(),
                ));

                $orderTable->commitTransaction();

                // Redirect to checkout
                return $this->redirect()->toRoute('checkout',
                           array(
                               'orderId' => $orderId,
                ));
            }
        } else {
            if(count($profiles)) {
                $username = $user->getUserLogin();
            }
        }

        $view->username = $username;
        $view->level = $level;
        $view->messages = $messages;

        return $view;
    }
}
