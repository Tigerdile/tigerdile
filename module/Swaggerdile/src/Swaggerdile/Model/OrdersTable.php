<?php
/*
 * OrdersTable.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

class OrdersTable extends ModelTable
{
    /*
     * Define our datastore table Kind
     *
     * @param string
     */
    protected $table = 'sd_orders';

    /*
     * Fetch all orders since a certain date for a certain
     * user.
     *
     * @param integer user_id
     * @param string datestamp
     * @return array of Orders
     *
     * This respects query features.
     */
    public function fetchOrdersSince($user_id, $date)
    {
        $rowset = $this->select(function($select) use ($user_id, $date) {
            $select->where->greaterThanOrEqualTo('sd_orders.completed', $date);
            $select->where->equalTo('sd_orders.user_id', $user_id);

            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }
}
