<?php
/*
 * Wordpress.php
 *
 * Uses the Wordpress DB for authentication.
 *
 * @author sconley
 */

namespace Swaggerdile\Authentication\Adapter;

use Zend\Authentication\Result;
use Zend\Authentication\Adapter\AbstractAdapter;
use Hautelook\Phpass\PasswordHash;

class Wordpress extends AbstractAdapter
{
    /*
     * authenticate
     *
     * Authenticate checks credentials against the DB.
     *
     * @return \Zend\Authentication\Result
     */
    public function authenticate()
    {
        // May be login or email
        $userLogin = $this->getIdentity();
        $user = array();

        // Set up our hash class.  Why use this piece of crap instead
        // of bcrypt?  Cause Wordpress uses it.
        $pwHasher = new PasswordHash(8, true);

        if(strpos($userLogin, '@') === FALSE) {
            $user = \Swaggerdile\Model\Factory::getInstance()
                    ->get('User')
                    ->fetchByUserLogin($userLogin);
        } else {
            $user = \Swaggerdile\Model\Factory::getInstance()
                    ->get('User')
                    ->fetchByUserEmail($userLogin);
        }

        if(empty($user) || (!$pwHasher->CheckPassword($this->getCredential(),
                                                      $user[0]->getUserPass()))) {
            return new Result(Result::FAILURE_CREDENTIAL_INVALID, $this->identity,
                                array('Invalid login or password'));
        } else {
            return new Result(Result::SUCCESS, $user[0]->getId());
        }
    }
}
