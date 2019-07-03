<?php
/*
 * AdminController.php
 *
 * Controller only accessible to admins
 */


namespace Swaggerdile\Controller;

use Swaggerdile\Controller;

use Stripe\Stripe;
use Stripe\Balance;

class AdminController extends Controller
{
    /*
     * onDispatch filters non-admins
     *
     * @param \Zend\Mvc\MvcEvent
     * @return value of : parent::onDispatch
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $ev)
    {
        $user = $this->getUser();

        if((!$user) || (!$this->getUser()->isAdmin())) {
            return $this->notFoundAction();
        }

        return parent::onDispatch($ev);
    }

    /*
     * indexAction
     *
     * Main menu for admins
     */
    public function indexAction()
    {
        return $this->getView();
    }

    /*
     * payoutAction
     *
     * Action to approve payouts
     */
    public function payoutAction()
    {
        $view = $this->getView();

        $prTable = $this->getModel()->get('PayoutRequests');

        // Grab stripe info
        Stripe::setApiKey($this->getConfig()['stripe_secret_key']);

        // Get our balance
        $balance = Balance::retrieve();

        $availableBalance = 0;
        $pendingBalance = 0;

        foreach($balance['available'] as $avail) {
            $availableBalance += $avail['amount'];
        }

        $view->availableBalance = $availableBalance / 100;

        foreach($balance['pending'] as $avail) {
            $pendingBalance += $avail['amount'];
        }

        $view->pendingBalance = $pendingBalance / 100;

        // Fetch all outstanding
        $prTable->setOrderBy('sd_payout_requests.id desc');

        $view->payouts = $prTable->fetchOutstandingRequests();

        // fetch all owed
        $view->owed = $this->getModel()->get('BalanceSheet')
                           ->fetchOwedMoney();
        return $view;
    }
}
