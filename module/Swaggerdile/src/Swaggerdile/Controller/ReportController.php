<?php
/**
 * Report Controller
 *
 * @author sconley
 */

namespace Swaggerdile\Controller;

use Swaggerdile\Controller;


class ReportController extends Controller
{
    /*
     * Order report
     *
     * The following URL parameters are understood :
     *
     * @param string o     - Sort column
     * @param string d     - Sort direction
     * @param string p     - Page number
     * @param integer dl   - If '1', download report.
     */
    public function ordersAction()
    {
        $view = $this->getView();
        $request = $this->getRequest();

        // Get user
        $user = $this->getUser();

        // Must be logged in
        if(!is_object($user)) {
            return $this->redirect()->toRoute('home');
        }

        // Must have a profile
        $profiles = $user->getProfiles();

        if(!count($profiles)) {
            return $this->redirect()->toRoute('dashboard');
        }

        // Generate a report
        $month = (int)$request->getPost('month',
                                        $request->getQuery('month', 0));
        $year = (int)$request->getPost('year',
                                        $request->getQuery('year', 0));

        // Push to view
        $view->month = $month;
        $view->year = $year;

        // Get translator
        $trans = $this->getLocator()->get('translator');

        // Downloading ?
        $downloading =  ($request->getPost('act', $request->getQuery('act', ''))
                            == $trans->translate('Download')) ||
                        ((int)$request->getPost('dl', $request->getQuery('dl', 0)));

        // Grab query parameters
        $order = $request->getPost('o', $request->getQuery('o', 'created'));
        $orderDirection = $request->getPost('d', $request->getQuery('d', 'desc'));
        $page = (int)$request->getPost('p', $request->getQuery('p', 0));

        // Set up the sort order, as this will apply all over.  Scrub data
        if($orderDirection != 'asc') {
            $orderDirection = 'desc';
        }

        // Only generate a report if month and year are both set.
        if($month && $year) {
            $start = date('Y-m-d 0:0:0', mktime(0, 0, 0, $month, 1, $year));
            $end = date('Y-m-d 0:0:0', mktime(0, 0, 0, $month+1, 1, $year));

            $oiTable = $this->getModel()->get('OrderItems');

            // @TODO : We're ignoring a lot of the potential
            // URL parameters
            $oiTable->setOrderBy("sd_tiers.price desc, tigerd_users.display_name desc");

            $report = $oiTable->fetchOrderReportForProfile($profiles[0]->getId(),
                                                           $start, $end);

            if($downloading) {
                $response = $this->getResponse();

                // Use Zend View to render CSV instead?
                $buffer = fopen('php://temp', 'r+');

                // header
                fputcsv($buffer, array(
                                    $trans->translate('Tier'),
                                    $trans->translate('User'),
                                    $trans->translate('Email'),
                                    $trans->translate('Pledge'),
                                    $trans->translate('Paid'),
                                    $trans->translate('Has Historical Access'),
                                    $trans->translate('Joined'),
                                    $trans->translate('Billed'),
                                    $trans->translate('Ship To Name'),
                                    $trans->translate('Address 1'),
                                    $trans->translate('Address 2'),
                                    $trans->translate('City'),
                                    $trans->translate('State/Province'),
                                    $trans->translate('Postal Code'),
                                    $trans->translate('Country'),
                                    $trans->translate('Total To Date'),
                        ));

                foreach($report as $line) {
                    fputcsv($buffer, array(
                        $line->getTitle(),
                        $line->getDisplayName(),
                        $line->getUserEmail(),
                        number_format($line->getTierPrice() + $line->getExtraPrice(), 2),
                        $line->getIsProrate() ? number_format($line->getTotalPrice(), 2) : number_format($line->getTierPrice() + $line->getExtraPrice() + $line->getHistoricalPrice(), 2),
                        $line->getIsHistoricalPaid() ? 'Yes' : 'No',
                        date('Y-m-d', strtotime($line->getCreated())),
                        date('Y-m-d', strtotime($line->getCompleted())),
                        $line->getIsShippable() ? $line->getShipToName() : 'N/A',
                        $line->getIsShippable() ? $line->getAddress1() : 'N/A',
                        $line->getIsShippable() ? $line->getAddress2() : 'N/A',
                        $line->getIsShippable() ? $line->getCity() : 'N/A',
                        $line->getIsShippable() ? $line->getState() : 'N/A',
                        $line->getIsShippable() ? $line->getPostalCode() : 'N/A',
                        $line->getIsShippable() ? $line->getCountry() : 'N/A',
                        number_format($line->getTotalPaid(), 2),
                    ));
                }

                $pos = ftell($buffer);
                rewind($buffer);
                $response->setContent(fread($buffer, $pos));
                fclose($buffer);

                $headers = new \Zend\Http\Headers();
                $headers->addHeaderLine('Content-Type: text/csv');
                $headers->addHeaderLine('Content-Disposition', "attachment; filename=\"swaggerdile-orders-${year}-${month}.csv\"");
                $headers->addHeaderLine('Content-Length', $pos);

                $response->setHeaders($headers);

                return $response;
            }

            $view->report = $report;
        } else {
            $view->report = false;
        }

        // And limit / offset
        $view->page = $page;
        $view->order = $order;
        $view->orderDirection = $orderDirection;
        $view->pageSize = 60;

        return $view;
    }

    /*
     * Declined payment report
     *
     * The following URL parameters are understood :
     *
     * @param string o     - Sort column
     * @param string d     - Sort direction
     * @param string p     - Page number
     * @param integer dl   - If '1', download report.
     */
    public function declinesAction()
    {
        $view = $this->getView();
        $request = $this->getRequest();

        // Get user
        $user = $this->getUser();

        // Must be logged in
        if(!is_object($user)) {
            return $this->redirect()->toRoute('home');
        }

        // Must have a profile
        $profiles = $user->getProfiles();

        if(!count($profiles)) {
            return $this->redirect()->toRoute('dashboard');
        }

        // Get translator
        $trans = $this->getLocator()->get('translator');

        // Downloading ?
        $downloading =  ($request->getPost('act', $request->getQuery('act', ''))
                            == $trans->translate('Download')) ||
                        ((int)$request->getPost('dl', $request->getQuery('dl', 0)));

        // Grab query parameters
        $order = $request->getPost('o', $request->getQuery('o', 'created'));
        $orderDirection = $request->getPost('d', $request->getQuery('d', 'desc'));
        $page = (int)$request->getPost('p', $request->getQuery('p', 0));

        // Set up the sort order, as this will apply all over.  Scrub data
        if($orderDirection != 'asc') {
            $orderDirection = 'desc';
        }

        $subsTable = $this->getModel()->get('Subscriptions');

        // @TODO : We're ignoring a lot of the potential
        // URL parameters
        $subsTable->setOrderBy("sd_subscriptions.declined_on desc, tigerd_users.display_name desc");

        $report = $subsTable->fetchDeclinedSubscriptionReport($profiles[0]->getId());

        if($downloading) {
            $response = $this->getResponse();

            // Use Zend View to render CSV instead?
            $buffer = fopen('php://temp', 'r+');

            // header
            fputcsv($buffer, array(
                                $trans->translate('User'),
                                $trans->translate('Email'),
                                $trans->translate('Tier'),
                                $trans->translate('Pledge'),
                                $trans->translate('Joined'),
                                $trans->translate('Declined'),
                    ));

            foreach($report as $line) {
                fputcsv($buffer, array(
                    $line->getDisplayName(),
                    $line->getUserEmail(),
                    $line->getTierTitle(),
                    number_format($line->getPayment(), 2),
                    date('Y-m-d', strtotime($line->getCreated())),
                    date('Y-m-d', strtotime($line->getDeclinedOn())),
                ));
            }

            $pos = ftell($buffer);
            rewind($buffer);
            $response->setContent(fread($buffer, $pos));
            fclose($buffer);

            $headers = new \Zend\Http\Headers();
            $headers->addHeaderLine('Content-Type: text/csv');
            $headers->addHeaderLine('Content-Disposition', "attachment; filename=\"swaggerdile-card-declines.csv\"");
            $headers->addHeaderLine('Content-Length', $pos);

            $response->setHeaders($headers);

            return $response;
        }

        $view->report = $report;

        // And limit / offset
        $view->page = $page;
        $view->order = $order;
        $view->orderDirection = $orderDirection;
        $view->pageSize = 60;

        return $view;
    }
}
