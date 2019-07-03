<?php
/*
 * ProfileUsersTable.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

use Zend\Db\Sql\Select;

class ProfileUsersTable extends ModelTable
{
    /*
     * Define our datastore table Kind
     *
     * @param string
     */
    protected $table = 'sd_profile_users';

    /*
     * Get users subscribed to a profile.  This joins with the users
     * table to bring in user_login, display_name, and user_email.
     *
     * @param Profile or Profile ID
     * @param array - if provided, array of profile user types to
     *                fetch.
     * @param array - if provide, array of profile user types to
     *                exclude.  The exclude will be done smartly
     *                with a subquery.
     */
    public function fetchProfileUsers($profileId, $types = array(),
                                      $notTypes = array())
    {
        if(is_object($profileId)) {
            $profileId = $profileId->getId();
        }

        $rowset = $this->select(function($select) use ($profileId, $types,
                                                       $notTypes) {
            $select->join('tigerd_users',
                          'tigerd_users.ID = sd_profile_users.user_id',
                          array('display_name', 'user_email', 'user_login'));

            $where = array(
                        'profile_id' => (int)$profileId
            );

            if(count($types)) {
                $where['type_id'] = $types;
            }

            $select->where($where);

            if(count($notTypes)) {
                $sub = new Select('sd_profile_users');
                $sub->columns(array('user_id'))
                    ->where(array(
                        'type_id' => $notTypes,
                        'profile_id' => (int)$profileId,
                    ));

                $select->where->notIn('user_id', $sub);
            }

            return $this->_addQueryFeatures($select);
        });

        return $this->_returnArray($rowset);
    }
}
