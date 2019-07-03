<?php
/*
 * Wordpress.php
 *
 * The class that interfaces with the Wordpress API.
 *
 * @TODO : Proper exception objects instead of stupid ones.
 */

namespace Swaggerdile;

use Swaggerdile\Model\Factory;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\PredicateSet;

class Wordpress
{
    /*
     * WP Configuration for API
     */

    /*
     * API Key
     *
     * @var string
     */
    protected $_apiKey = false;

    /*
     * Base URL
     *
     * @var string
     */
    protected $_baseUrl = false;

    /*
     * Constructor
     *
     * @param string base URL
     * @param string API key
     */
    public function __construct($baseUrl, $apiKey)
    {
        $this->_baseUrl = $baseUrl;
        $this->_apiKey = $apiKey;
    }

    /*
     * Register a user
     *
     * @param string Username
     * @param string Email
     * @param string display name
     * @param string password
     * @param boolean notify (optional)
     *
     * @throws Exception on failure
     */
    public function register($username, $email, $displayName, $password, $notify = true)
    {
        $ret = $this->_fetch(
                "{$this->_baseUrl}userplus/register/?key={$this->_apiKey}&username=" . urlencode($username)
                . "&email=" . urlencode($email)
                . "&display_name=" . urlencode($displayName)
                . "&user_pass=" . urlencode($password)
                . ($notify ? '' : '&notify=no')
                . '&reference=Swaggerdile'
        );

        // Status should be okay
        if($ret->status != 'ok') {
            throw new \Exception($ret->error);
        }
    }

    /*
     * Check to see if a user / email is available or not.
     *
     * @param string username
     * @param string email
     *
     * @return boolean   -- true if available
     *
     * This can be done with 2 API calls, but I'd rather just go
     * straight to the DB.
     */
    public function isAvailable($username, $email)
    {
        $userTable = Factory::getInstance()->get('UserTable');

        // Do an OR query.
        $rowset = $userTable->select(function($select) use ($username, $email) {
            $where = new Where();
            $where->addPredicates(array(
                        'tigerd_users.user_login' => $username,
                        'tigerd_users.user_email' => $email,
                    ), PredicateSet::COMBINED_BY_OR);

            $select->where($where);

            return $select;
        });

        // This should be 0 to be available
        return (count($rowset) == 0);
    }

    /*
     * Send password reset to a user
     *
     * @param string username
     *
     * Throws exception on failure.
     */
    public function sendPasswordReset($username)
    {
        $ret = $this->_fetch("{$this->_baseUrl}userplus/retrieve_password/?key={$this->_apiKey}&user_login=" . urlencode($username));

        // Status should be okay
        if($ret->status != 'ok') {
            throw new \Exception($ret->error);
        }

        // Yay, worked!
    }

    /*
     * All of the calls use a common idea of fetching JSON structures.
     *
     * @param path
     * @return object (decoded JSON)
     */
    protected function _fetch($url)
    {
        // The API is 'tarded and returns 404 instead of 200
        $ret = file_get_contents($url, false, stream_context_create(
                                                array(
                                                    'http' => array(
                                                        'ignore_errors' => true
                                                    )
                                                )
        ));

        if(!$ret) {
            throw new \Exception('Failed to communicate with Tigerdile.  Try again later.');
        }

        $json = json_decode(trim($ret));

        // Check decode
        if(empty($json)) {
            throw new \Exception('Failed to process data from Tigerdile.  Try again later.');
        }

        return $json;
    }
}
