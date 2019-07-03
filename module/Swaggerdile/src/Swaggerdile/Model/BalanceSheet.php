<?php
/*
 * BalanceSheet.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

class BalanceSheet extends Model
{
    /*
     * Constants that are the different transaction
     * types.  These are also in the sd_transaction_types
     * table which is used for referential integrity.
     *
     * Adding a new type requires insertion into that table.
     */
    
    // A system level fee.
    const TRANSACTION_FEE = 1;

    // Payment received from another user.
    const TRANSACTION_PAYMENT_RECEIVED = 2;

    // Withdraw to bank account.
    const TRANSACTION_WITHDRAW = 3;

    // A credit to the account
    const TRANSACTION_CREDIT = 4;

    // Payment sent from account.
    const TRANSACTION_PAYMENT_SENT = 5;

    /*
     * Array mapping transaction type ID's to strings.
     *
     * @var array
     */
    static protected $_transactionTypes = array(
        1 => 'Fee',
        2 => 'Payment Received',
        3 => 'Withdraw',
        4 => 'Credit',
        5 => 'Payment Sent',
    );

    /*
     * Method to get list of transaction types.
     *
     * @return array
     */
    static public function getTransactionTypes()
    {
        return self::$_transactionTypes;
    }

    /*
     * Resolve a transaction ID to type.
     *
     * REturns null on unknown
     *
     * @param integer
     * @return string
     */
    static public function getTransactionType($id)
    {
        if(array_key_exists($id, self::$_transactionTypes)) {
            return self::$_transactionTypes[$id];
        }

        return null;
    }
}