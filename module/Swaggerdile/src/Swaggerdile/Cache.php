<?php
/*
 * Cache.php
 *
 * Class to handle Swaggerdile cache-busting with CloudFlare in a
 * logical way that does nothing if CloudFlare configuration is not
 * present.
 *
 * @author sconley
 */

namespace Swaggerdile;

use Cloudflare\Api;
use Cloudflare\Zone\Cache as CloudCache;
use Zend\View\Helper\ServerUrl;

class Cache
{
    /*
     * Our client object
     *
     * @var Cloudflare\Api
     */
    protected $_client = false;

    /*
     * CloudFlare zone ID so that we don't have to keep
     * looking up it :P
     *
     * @var string
     */
    protected $_zoneId = false;

    /*
     * Constructor to load configuration and start a client.
     *
     * @param string CloudFlare Zone ID
     * @param string Email
     * @param string API Key
     *
     * If zone is an empty string, the class will be loaded
     * in a NO-OP mode suitable for development.
     */
    public function __construct($zone, $email, $key)
    {
        if(strlen($zone)) {
            $this->_zoneId = $zone;
            $this->_client = new Api($email, $key);
        }
    }

    /*
     * Delete a URL from cache.
     *
     * @param string or array of URLs to purge
     * @param boolean add base server URL?  Default true.
     *
     * This can only delete 30 at a time due to the limitations
     * of Cloudflare's API.  If that becomes a problem, it would
     * be easy to wrap it to allow for more ... but that doesn't
     * seem like it will be an issue at this time.
     */
    public function deleteFromCache($url, $addBaseUrl = true)
    {
        // NO-OP
        if($this->_zoneId === false) {
            return;
        }

        $toDelete = array();

        if($addBaseUrl) {
            $helper = new ServerUrl();
            $baseUrl = $helper->__invoke(false);

            if(!is_array($url)) {
                $toDelete[] = $baseUrl . $url;
            } else {
                foreach($url as $u) {
                    $toDelete[] = $baseUrl . $u;
                }
            }
        } else {
            if(!is_array($url)) {
                $toDelete[] = $url;
            } else {
                $toDelete = $url;
            }
        }

        (new CloudCache($this->_client))->purge_files(
            $this->_zoneId, $toDelete);
    }
}
