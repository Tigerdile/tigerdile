<?php
/*
 * OrderItemsTable.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Literal;
use Zend\Db\Sql\Predicate\Expression;

class OrderItemsTable extends ModelTable
{
    /*
     * Define our datastore table Kind
     *
     * @param string
     */
    protected $table = 'sd_order_items';

    /*
     * Override this method to provide a join with profiles.
     *
     * Adds profile_title to the mix, and tier_title
     *
     * @param Integer order ID
     * @return array of OrderItem objects
     *
     * THIS METHOD RESPECTS QUERY FEATURES
     */
    public function fetchByOrderId($orderId)
    {
        $rowset = $this->select(function($select) use ($orderId) {
            $select->join('sd_profiles', 'sd_profiles.id = sd_order_items.profile_id',
                          array('profile_title' => 'title', 'profile_url' => 'url'))
                    ->join('sd_tiers', 'sd_tiers.id = sd_order_items.tier_id',
                          array('tier_title' => 'title'), Select::JOIN_LEFT)
                    ->join('sd_items', 'sd_items.id = sd_order_items.item_id',
                          array('item_title' => 'title',
                               'item_price' => 'price'), Select::JOIN_LEFT)
                    ->where(array('sd_order_items.order_id' => $orderId));

            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }

    /*
     * Query order items to get some order history going on
     *
     * I could re-write this to go through OrdersTable which may be a
     * more logical location, but how I wrote the query in my notepad
     * fits this table better.
     *
     * Not sure I care one way or another. :)
     *
     * @param integer profile id to generate report for
     * @param string (date format) start date
     * @param string (date format) end date
     *
     * Note we will use >= operator for start date, and < operator for
     * end date.
     *
     * @return array
     *
     * THIS RESPECTS QUERY FEATURES
     */
    public function fetchOrderReportForProfile($profile_id, $start, $end)
    {
        $rowset = $this->select(function($select) use ($profile_id, $start, $end) {
            $select->join('sd_orders', 'sd_orders.id = sd_order_items.order_id',
                          array('total_price', 'is_prorate', 'is_recurring',
                                'completed'))
                   ->join('sd_tiers', 'sd_tiers.id = sd_order_items.tier_id',
                          array('title', 'price', 'is_shippable'), 'left')
                   ->join('sd_subscriptions', 'sd_subscriptions.user_id = sd_orders.user_id and sd_subscriptions.profile_id = sd_order_items.profile_id',
                          array('ship_to_name', 'address1', 'address2', 'city', 'state',
                                'postal_code', 'country', 'created', 'is_historical_paid'))
                   ->join('tigerd_users', 'tigerd_users.ID = sd_orders.user_id',
                          array('display_name', 'user_email'))
                   ->join('sd_balance_sheet',
                          'sd_balance_sheet.subscription_id = sd_subscriptions.id',
                          array('total_paid' => new Expression('sum(sd_balance_sheet.transaction)')),
                          'left');
  
            $select->where->greaterThanOrEqualTo('sd_orders.completed', $start);
            $select->where->lessThan('sd_orders.completed', $end);
            $select->where->equalTo('sd_order_items.profile_id', $profile_id);

            $select->group(array('sd_order_items.id'));

            // Add features and return
            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }
}
