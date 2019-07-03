<?php
/*
 * SubscriptionsTable.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Literal;
use Zend\Db\Sql\Predicate\Expression;

class SubscriptionsTable extends ModelTable
{
    /*
     * Define our datastore table Kind
     *
     * @param string
     */
    protected $table = 'sd_subscriptions';

    /*
     * This fetches a combined subscription and tier record for
     * a given user and profile combination.
     *
     * This adds 'getTierId', 'getTierTitle', and 'getTierPrice'
     * to the object.
     *
     * @param integer userId
     * @param integer profileId
     * @return Subscriptions|null
     *
     */
    public function fetchSubscriptionWithTier($userId, $profileId)
    {
        $rowset = $this->select(function($select) use ($profileId, $userId) {
            $select->join(  'sd_tiers',
                            'sd_tiers.id = sd_subscriptions.tier_id',
                            array(
                                'tier_title' => 'title',
                                'tier_price' => 'price',
                            ), Select::JOIN_LEFT)
                   ->where(array(
                            'sd_subscriptions.profile_id' => $profileId,
                            'sd_subscriptions.user_id' => $userId,
                            'sd_subscriptions.is_active' => 1))
                   ->order(array('sd_tiers.price' => 'desc'))
                   ->limit(1);
            return $select;
        });

        return $this->_returnSingle($rowset);
    }

    /*
     * This fetches a combined subscription and tier records for
     * a given user
     *
     * This adds 'getTierId', 'getTierTitle', and 'getTierPrice'
     * to the object, and also getProfileTitle and getProfileUrl
     *
     * @param integer userId
     * @return array of Subscriptions
     *
     * THIS METHOD RESPECTS ORDER BY AND LIMIT/OFFSET
     */
    public function fetchSubscriptionsWithTier($userId)
    {
        $rowset = $this->select(function($select) use ($userId) {
            $select->join(  'sd_tiers',
                            'sd_tiers.id = sd_subscriptions.tier_id',
                            array(
                                'tier_title' => 'title',
                                'tier_price' => 'price',
                            ), Select::JOIN_LEFT)
                   ->join(  'sd_profiles',
                            'sd_profiles.id = sd_subscriptions.profile_id',
                            array(
                                'profile_title' => 'title',
                                'profile_url' => 'url',
                                'profile_historical_fee' => 'historical_fee',
                            )
                    )
                   ->where(array(
                            'sd_subscriptions.user_id' => $userId));

            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }

    /*
     * This fetches a combined subscription and payment information
     * array.
     *
     * This adds 'getChildMeta', 'getParentMeta', and 'getPaymentMethodId'
     * to the object.  And 'getProfileTitle'
     *
     * @param integer userId
     * @return array of Subscriptions
     *
     * THIS METHOD RESPECTS ORDER BY AND LIMIT/OFFSET
     */
    public function fetchActiveSubscriptionsWithPayment($userId)
    {
        $rowset = $this->select(function($select) use ($userId) {
            $select->join(  'sd_child_payment_methods',
                            'sd_child_payment_methods.id = sd_subscriptions.child_payment_method_id',
                            array(
                                'child_meta' => 'metadata',
                            ))
                    ->join( 'sd_user_payment_methods',
                            'sd_user_payment_methods.id = sd_child_payment_methods.user_payment_method_id',
                            array(
                                'parent_meta' => 'metadata',
                                'user_payment_method_id' => 'id',
                            ))
                    ->join( 'sd_profiles',
                            'sd_profiles.id = sd_subscriptions.profile_id',
                            array('profile_title' => 'title'))
                    ->where(array(
                            'sd_subscriptions.user_id' => $userId,
                            'sd_subscriptions.is_active' => 1));

            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }

    /*
     * This fetches user data for active subscriptions with
     * no tier set.
     *
     * @param integer profileId
     * @return array of Subscriptions with some user data
     *
     * THIS METHOD RESPECTS ORDER BY AND LIMIT/OFFSET
     */
    public function fetchTierlessSubscribers($profileId)
    {
        $rowset = $this->select(function($select) use ($profileId) {
            $select->join(  'tigerd_users',
                            'tigerd_users.ID = sd_subscriptions.user_id',
                            array('display_name', 'user_email', 'user_login')
                    )
                    ->join( 'sd_balance_sheet',
                            'sd_balance_sheet.subscription_id = sd_subscriptions.id',
                            array('total_paid' => new Expression('sum(sd_balance_sheet.transaction)')),
                            'left'
                        )
                    ->where(array(
                            'sd_subscriptions.profile_id' => $profileId,
                            'sd_subscriptions.tier_id' => null
                    ))
                    ->where(array(new Literal('(sd_balance_sheet.type_id=2 or sd_balance_sheet.type_id is null)')))
                    ->group(array('sd_subscriptions.id'));

            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }

    /*
     * This generates a report of declined card subscriptions.
     *
     * @param integer profile_id
     * @param string start date
     * @param string end date
     *
     * @return array of objects
     *
     * THIS METHOD RESPECTS ORDER BY AND LIMIT/OFFSET
     */
    public function fetchDeclinedSubscriptionReport($profile_id)
    {
        $rowset = $this->select(function($select) use ($profile_id) {
            $select->join(  'tigerd_users',
                            'tigerd_users.ID = sd_subscriptions.user_id',
                            array('display_name', 'user_email')
                        )
                   ->join(  'sd_tiers',
                            'sd_tiers.id = sd_subscriptions.tier_id',
                            array('tier_title' => 'title'),
                            'left'
                        );

            $select->where->isNotNull('sd_subscriptions.declined_on');
            $select->where->equalTo('sd_subscriptions.profile_id', $profile_id);
            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }
}