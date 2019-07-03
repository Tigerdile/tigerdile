<?php
/**
 *CookieBase.php
 *
 *Implements a "cookiebase" style session.
 *------------------------------------------
 *##### What's a CookieBase?
 *It's a modification of WordPress' authentication.
 *###### How it works:
 * 1. Some information about a user should be available instantly no matter
 *    what -- user ID, role name, maybe other basic tidbits.  These pieces
 *    of information will be stored IN THE COOKIE as a base64 encoded item.
 *
 *    Part of the encoded base64 will be a hash; this hash is the information
 *    in the cookie hashed against a secret key on the server side.  This
 *    hash is used to verify that the data in the cookie has not been
 *    tampered with ala WordPress.
 *
 * 2. Additional information that we do not want to store in the cookie
 *    (or cannot store due to size) will be in the database. We will
 *    "pass through" memcache on the way to the DB.
 *
 * @author sconley
 */

namespace Swaggerdile\Authentication\Storage;

use Zend\Authentication\Storage\StorageInterface;
use Zend\Authentication\Exception\UnexpectedValueException;

class CookieBase implements StorageInterface
{
    /**
     * CookieBase needs a server secret.
     *
     * Short secret is used *in* the hash, and secret is used to
     * actually do the hash.  This is just to make it harder
     * to crack.
     */

    /*
     * @var string
     */
    protected $_shortSecret;

    /*
     * @var string
     */
    protected $_secret;

    /*
     * @var string
     *
     * Name of cookie to set
     */
    protected $_cookieName;

    /*
     * @var integer
     *
     * Timeout (in seconds) of session
     */
    protected $_timeout;

    /*
     * @var string
     *
     * Cookie domain
     */
    protected $_domain;

    /*
     * @var string
     *
     * Associated data
     */
    protected $_data;

    /*
     * @var string
     *
     * Wordpress Cookie name
     */
    protected $_wpCookieName;

    /**
     * Allow configuration of cookiebase.
     *
     * @param string
     * @param string
     * @param string
     * @param integer optional timeout in seconds - default to 1 hour
     * @param string domain for cookie, default ''
     */
    public function __construct($shortSecret, $secret, $cookieName, $timeout = 3600,
                                $domain = '')
    {
        $this->_shortSecret = $shortSecret;
        $this->_secret = $secret;
        $this->_cookieName = $cookieName;
        $this->_timeout = $timeout;
        $this->_domain = $domain;
        $this->_data = false;

        // This could be injected better, but, right now I just want
        // it to work.  I'd like to remove all WP in the future
        // @TODO: Remove with WP
        $this->_wpCookieName = "wordpress_logged_in_" . COOKIEHASH;
    }
    
    /**
     * Implementation of StorageInterface methods
     */
    
    /**
     * isEmpty
     * 
     * Is it empty?  Check the cookie.
     * --------------------------------
     *
     * An invalid cookie is the same as an empty one.
     *
     * @return boolean
     */
    public function isEmpty()
    {
        // @TODO: Remove with WP
        if((!array_key_exists($this->_wpCookieName, $_COOKIE)) &&
           ((!array_key_exists($this->_cookieName, $_COOKIE)) ||
                (!strlen($_COOKIE[$this->_cookieName])))) {
            return true;
        }

        try {
            $this->_data = $this->read();
        } catch(UnexpectedValueException $e) {
            return true;
        }

        return false;
    }
    
    
    /**
     * write
     * 
     * Our write method will write data into our cookie
     * --------------------------------------------------------------------------------------------
     *
     * @param mixed
     */
    public function write($user)
    {
        // Here's our data payload
        $data = serialize($user);

        // Add timestamp
        $data .= '|' . time();

        
        // Make our hash
        $hash = hash_hmac('sha512', $this->_shortSecret . '^' . $data . '^',
                          $this->_secret);

        // Append it and set it
        setcookie($this->_cookieName, base64_encode($data . '|' . $hash), 
                  time()+$this->_timeout, '/', $this->_domain);

        // update data cache
        $this->_data = $user;
    }
    
    /**
     * Our read method will return whatever was put in the cookie
     *
     * It will throw an exception if the session has been tampered 
     * with or is timed out.
     *
     * @return mixed
     *
     */
    public function read()
    {
        if($this->_data !== false) {
            return $this->_data;
        }

        // Use our cookie if we have it, otherwise fall back to WordPress.
        if(array_key_exists($this->_cookieName, $_COOKIE)) {
            list($data, $time, $hash) =
                        explode('|', base64_decode($_COOKIE[$this->_cookieName]));

            // Do integrigy checks
            if(($time > (time() - $this->_timeout)) &&
               ($hash == hash_hmac('sha512', $this->_shortSecret . '^' . $data . '|' .
                                     $time . '^', $this->_secret))) {
                return ($this->_data = unserialize($data));
            }
        }

        // @TODO: Remove with WordPress

        // Otherwise, try to load from WordPress if we've got it.
        if(!array_key_exists($this->_wpCookieName, $_COOKIE)) {
            // We're hopeless
            throw new UnexpectedValueException();
        }

        // Validate it
        list($username, $expiration, $token, $hmac) =
                explode('|', $_COOKIE[$this->_wpCookieName]);

        // Grab our password from the DB to calculate password fragment.
        if((!strlen($username)) || (!strlen($expiration)) || (!strlen($hmac)) ||
           (!strlen($token))) {
            throw new UnexpectedValueException();
        }

        $user = \Swaggerdile\Model\Factory::getInstance()
                            ->get('User')->fetchByUserLogin($username);

        if(empty($user)) {
            throw new UnexpectedValueException();
        }

        // Do the needful
        $passwordFragment = substr($user[0]->getUserPass(), 8, 4);

        $key = hash_hmac('md5',
                         "{$username}|{$passwordFragment}|{$expiration}|{$token}",
                         LOGGED_IN_KEY . LOGGED_IN_SALT
        );

        $hash = hash_hmac('sha256', "{$username}|{$expiration}|{$token}", $key);

        // Does this all match up?
        if(strcmp($hash, $hmac)) {
            throw new UnexpectedValueException();
        }

        // Set our own cookie
        $this->write($user[0]->getId());
        return $user[0]->getId();
    }
    
    /**
     * clear
     * 
     * Finally clear it
     */
    public function clear()
    {
        setcookie($this->_cookieName, '', 0, '/', $this->_domain);
    }
}

