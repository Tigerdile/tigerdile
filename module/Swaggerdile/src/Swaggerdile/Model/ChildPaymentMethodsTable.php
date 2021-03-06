<?php
/*
 * ChildPaymentMethodsTable.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

class ChildPaymentMethodsTable extends ModelTable
{
    /*
     * Define our datastore table Kind
     *
     * @param string
     */
    protected $table = 'sd_child_payment_methods';

    /*
     * fetchOrInsert
     *
     * For a given 'metadata' and payment method ID, return
     * a primary key OR! insert a new record and return
     * a primary key.
     *
     * @param integer payment method ID
     * @param string metadata
     * @return integer
     */
    public function fetchOrInsert($paymentMethodId, $metadata)
    {
        $rowset = $this->select(array(
                        'user_payment_method_id' => $paymentMethodId,
                        'metadata' => $metadata,
        ));

        // Got one already
        if(count($rowset)) {
            return $rowset->current()->id;
        }

        // Create new one
        $this->insert(array(
                        'user_payment_method_id' => $paymentMethodId,
                        'metadata' => $metadata,
        ));

        return $this->getLastInsertValue();
    }
}
