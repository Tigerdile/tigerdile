<?php
/*
 * ContentTable.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

use \Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Predicate\Literal;

class ContentTable extends ModelTable
{
    /*
     * Define our datastore table Kind
     *
     * @param string
     */
    protected $table = 'sd_content';

    /*
     * Fetch content available to the user, taking into account
     * their tier membership, NOT taking into account profile but
     * across all subscriptions.
     *
     * Does NOT show the profile owner their own content.
     *
     * @param User
     * @param array - query parameters, optional
     * @return array of Content
     *
     * Adds 'url' from profile to the content structure as that
     * is required for figuring out the URL to pieces of content.
     *
     * THIS METHOD RESPECTS ORDER BY AND LIMIT/OFFSET
     */
    public function fetchSubscribedContentForUser($user, $query = array())
    {
        // We have to get the subscriptions first to make sure the
        // user has some -- if he does not, the following query will
        // fail.
        $subscriptions = Factory::getInstance()->get('Subscriptions')
                                               ->select(function($select) use ($user) {
                    $select->columns(array('tier_id'))
                           ->where(array('user_id' => $user->getId()));
                    return $select;
        });

        // Return empty if not subscribed
        if(!$subscriptions->count()) {
            return array();
        }

        // Make our subscription list.
        $subTmp = array();

        foreach($subscriptions as $sub) {
            if($sub->tier_id) {
                $subTmp[] = $sub->tier_id;
            }
        }

        $subList = implode(',', $subTmp);

        $query['sd_subscriptions.user_id'] = $user->getId();

        $rowset = $this->select(function($select) use ($query, $subList) {

                    $select->join('sd_content_tiers_link',
                                  'sd_content.id = sd_content_tiers_link.content_id',
                                  array(), 'left')
                            ->join('sd_profiles',
                                  'sd_content.profile_id = sd_profiles.id',
                                  array('url' => 'url', 'profile_title' => 'title'))
                            ->join('sd_subscriptions',
                                  'sd_content.profile_id = sd_subscriptions.profile_id',
                                  array())
                            ->where($query);

                    // We may have an active subscription with no tiers.
                    if(strlen($subList)) {
                        $select->where(new Literal("(sd_content_tiers_link.tier_id is null or sd_content_tiers_link.tier_id in ({$subList})) and (sd_content.is_sample = 1 or sd_content.is_never_historical = 1 or sd_subscriptions.is_historical_paid = 1 or sd_subscriptions.created < sd_content.created)"));
                    } else {
                        $select->where(new Literal("sd_content_tiers_link.tier_id is null and (sd_content.is_sample = 1 or sd_content.is_never_historical = 1 or sd_subscriptions.is_historical_paid = 1 or sd_subscriptions.created < sd_content.created)"));
                    }

                    $this->_addQueryFeatures($select);

                    //print_r($select->getSqlString()); exit;
                    return $select;
                });

        return $this->_returnArray($rowset);
    }

    /*
     * Overload insert.  Automatically add in 'ordering' column
     * unless asked not to.
     *
     * @param array stuff to insert
     * @param boolean bypassOverload
     */
    public function insert($toInsert, $bypassOverload = false)
    {
        if((!$bypassOverload) && (!array_key_exists('ordering', $toInsert))) {
            $toInsert['ordering'] = new Literal("(select `AUTO_INCREMENT` from information_schema.tables where TABLE_SCHEMA=DATABASE() and TABLE_NAME='sd_content')");
        }

        return parent::insert($toInsert);
    }

    /*
     * Get largest ordering number for the given folder ID
     *
     * @param folderId
     * @return integer
     *
     * May be 0 if there are no items in the folder
     */
    public function getLargestOrdering($folderId)
    {
        $select = (new Sql($this->getAdapter(), 'sd_content'))->select()
                    ->columns(array(new Literal('MAX(ordering) as ordering')))
                    ->where(array('parent_id' => $folderId));

        $rowset = $this->selectWith($select);

        if(!count($rowset)) {
            return 0;
        } else {
            return (int)$rowset->current()->ordering;
        }
    }

    /*
     * Fetch content available to the user, taking into account
     * their tier membership.
     *
     * @param User
     * @param Profile
     * @param array - query parameters, optional, do NOT include profile ID
     *                as it will be over-written.
     * @return array of Content
     *
     * THIS METHOD RESPECTS ORDER BY AND LIMIT/OFFSET
     */
    public function fetchProfileContentForUser($user, $profile, $query = array())
    {   
        $rowset = array();

        // Add profile ID to our query.
        $query['sd_content.profile_id'] = $profile->getId();

        // Do I own the profile?
        if(is_object($user) && (($user->getId() == $profile->getOwnerId()) || ($user->isAdmin()))) {
            $rowset = $this->select(function($select) use ($query) {
                                        if(!empty($query)) {
                                            $select->where($query);
                                        }

                                        return $this->_addQueryFeatures($select);
            });
        } else {
            /*
             * I am either trying to see free content.
             *
             * Or I'm subscribed to a tier and it's content for my tier.
             *
             * Or I'm subscribed to a tier and it's content for all tiers.
             */

            // Get my tier, if I have one.
            if(is_object($user)) {
                $subscription = $user->getProfileSubscription($profile->getId());
            } else {
                $subscription = array();
            }

            // Only free content if $tierId is not set.
            if(empty($subscription)) {
                // Only show free items
                $query['is_sample'] = 1;

                $rowset = $this->select(function($select) use ($query) {
                                        $select->where($query);
                                        return $this->_addQueryFeatures($select);
                });
            } else {
                $query['sd_subscriptions.user_id'] = $user->getId();
                $rowset = $this->select(function($select) use ($subscription, $query) {
                                        $select->join('sd_content_tiers_link',
                                                      'sd_content.id = sd_content_tiers_link.content_id',
                                                      array(), 'left')
                                                ->join('sd_subscriptions',
                                                      'sd_content.profile_id = sd_subscriptions.profile_id',
                                                      array(), 'left')
                                               ->where($query)
                                               ->where(new Literal("(sd_content_tiers_link.tier_id is null or sd_content_tiers_link.tier_id=" . ((int)$subscription->getTierId()) . ') and (sd_content.is_sample = 1 or sd_subscriptions.is_historical_paid = 1 or sd_content.is_never_historical = 1 or sd_subscriptions.created < sd_content.created)'));

                                        $this->_addQueryFeatures($select);

                                        //print_r($select->getSqlString()); exit;
                                        return $select;
                        });
            }
        }

        return $this->_returnArray($rowset);
    }

    /*
     * setOrdering
     *
     * This takes an array of ID's and sets the DB order to match
     * the order of the IDs.  Takes profile ID for permissions
     * check, we will assume the user owns the profile.
     *
     * @param Profile
     * @param array
     */
    public function setOrdering($profile, $ordering)
    {
        if((!is_array($ordering)) || (!count($ordering))) {
            return;
        }

        // Get the lowest ordering number
        $rowset = $this->select(function($select) use ($profile, $ordering) {
            $select->columns(array('ordering'))
                   ->where(array(
                                'id' => $ordering,
                                'profile_id' => $profile->getId()
                    ))
                   ->order('ordering asc')
                   ->limit(1);
            return $select;
        });

        if(!count($rowset)) {
            return;
        }

        $firstOrderNum = $rowset->current()->ordering - 1;
        $idList = join(',', $ordering);

        // Now, do the re-ordering.
        // This is weird and mysql specific ... sorry :(
        $statement = $this->getAdapter()->query(
                    "set @rownumber = {$firstOrderNum}; update sd_content
                            set ordering = (@rownumber := @rownumber + 1)
                            where id in ({$idList})
                            and profile_id = {$profile->getId()}
                            order by FIELD(id, {$idList})");

        $statement->execute();
    }
}