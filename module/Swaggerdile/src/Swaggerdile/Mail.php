<?php
/*
 * Mail.php
 *
 * Swaggerdile Mail class, for easy sending of simple messages
 * without requiring a lot of song and dance.
 *
 * @author sconley
 */

namespace Swaggerdile;

use Zend\Mail\Message;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;


use Swaggerdile\Model\Factory as ModelFactory;

class Mail
{
    /*
     * Our mail configuration
     */
    protected $_config = array();

    /*
     * Instance a new mail system
     *
     * @param config from Zend Config
     */
    public function __construct($conf)
    {
        $this->_config = $conf;
    }

    /*
     * Send an email
     *
     * @param string to
     * @param string subject
     * @param string message
     * @param boolean isHtml.  If true, we'll set headers.
     */
    public function send($to, $subject, $message, $isHtml = false)
    {
        $toSend = new Message();
        $toSend ->addTo($to)
                ->addFrom($this->_config['sendFrom'],
                          $this->_config['sendFromUser'])
                ->setSubject($subject);

        $transport = new $this->_config['transport'];

        if(is_array($this->_config['smtpOptions']) &&
           count($this->_config['smtpOptions'])) {
            $opts = new SmtpOptions($this->_config['smtpOptions']);
            $transport->setOptions($opts);
        }

        if(!$isHtml) {
            $toSend->setBody($message);
        } else {
            $html = new MimePart($message);
            $html->type = 'text/html';

            $body = new MimeMessage();
            $body->addPart($html);

            $toSend->setBody($body);
        }

        $transport->send($toSend);
    }

    /*
     * Queue bulk emails to go through Tigerdile's bulk sender.
     *
     * @param array of emails or User objects.
     * @param string subject
     * @param string message
     * @param boolean OPTIONAL parse replacement vars
     * @param string OPTIONAL header
     *
     * If 'parse replacement vars' is true, we will scan for variables in the
     * 'message' of the format @@user_table_column@@.  We will extract them
     * and replace them.
     *
     * The $to array needs to be User objects for this to make sense.  They
     * will be replaced with empty strings otherwise.
     *
     * It's a good idea to wrap this in a transaction as it is not atomic.
     */
    public function bulk($to, $subject, $message, $parseReplacements = false, $headers = null)
    {
        // We're going to be working with a particular table.
        $emailTable = ModelFactory::getInstance()->get('EmailQueue');

        if((!is_array($to)) || (!count($to))) {
            // Can't do anything with this
            return;
        }

        // Precompute replacements if we need
        $replacements = array();

        if($parseReplacements) {
            if(preg_match_all('/@@[\w\d]+@@/U', $subject, $matches)) {
                $replacements = $matches[0];
            }

            if(preg_match_all('/@@[\w\d]+@@/U', $message, $matches)) {
                $replacements = array_merge($replacements, $matches[0]);
            }
        }

        foreach($to as $person) {
            $newMessage = $message;
            $newSubject = $subject;

            if($parseReplacements) {
                $myProperties = array();

                if(is_object($person)) {
                    $myProperties = $person->toArray();
                }

                foreach($replacements as $replacement) {
                    $val = '';

                    if(array_key_exists($replacement, $myProperties)) {
                        $val = $myProperties[$replacement];
                    }

                    $newMessage = preg_preplace("/@@{$replacement}@@/", $val, $newMessage);
                    $newSubject = preg_preplace("/@@{$replacement}@@/", $val, $newSubject);
                }
            }

            $emailTable->insert(array(
                            'content' => $newMessage,
                            'send_to' => is_object($person) ? $person->getUserEmail() : $person,
                            'subject' => $newSubject,
                            'headers' => $headers,
            ));
        }
    }
}
