<?php
/*
 * Crypto.php
 *
 * Handles public key crypto of sensitive files.
 *
 * @author sconley
 */

namespace Swaggerdile;

use ParagonIE\Halite\File as CryptoFile;
use ParagonIE\Halite\Asymmetric\EncryptionPublicKey;


class Crypto
{
    /*
     * Configuration
     *
     * @var array
     */
    protected $_config = array();

    /*
     * Our public key, if we have it.
     *
     * @var string
     */
    protected $_publicKey = false;

    /*
     * Initialize
     *
     * @param array
     */
    public function __construct($config)
    {
        $this->_config = $config;

        // Try to load our key
        $this->_publicKey = new EncryptionPublicKey(
                                file_get_contents($config['publicKey']));
    }

    /*
     * Encrypt a file.  This is a public key encryption, basically
     * "one way".
     *
     * @param string source file name
     * @param string destination file name
     */
    public function seal($source, $destination)
    {
        CryptoFile::seal($source, $destination, $this->_publicKey, true);
    }

    /*
     * Push a file to the secure document repo server.
     *
     * @param string filename
     */
    public function sendToRepo($filename)
    {
        $post = array(
                    'filearg' => curl_file_create($filename,
                                                  'application/octet-stream',
                                                  basename($filename)),
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_config['repoServerUrl']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        curl_close($ch);

        // This should be an empty string
        if($res !== TRUE) {
            throw new \Exception("Failed to send file to repo server!");
        }
    }
}
