<?php
/*
 * EmailFieldset.php
 *
 * The fields required to send an email
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Form\Element\Captcha as CaptchaElement;
use Zend\Captcha\Image;
use Zend\Validator\Regex;

class EmailFieldset extends Fieldset implements InputFilterProviderInterface
{
    /*
     * Constructor will configure the form
     *
     * @param string optional form name (defaults to email)
     */
    public function __construct($name = 'email')
    {
        // Run the parent constructor
        parent::__construct($name);

        /*
         * Stuff in an email:
         *
         * Recipients list (hidden field with list of ID's)
         * Subject
         * Message
         * Captcha
         *
         * All are required
         */
        $this->add(array(
            'name' => 'recipients',
            'type' => 'Hidden',
        ));

        $this->add(array(
            'name' => 'subject',
            'type' => 'Text',
            'options' => array(
                'label' => 'Subject',
            ),
            'attributes' => array(
                'placeholder' => 'Subject',
            ),
        ));

        $this->add(array(
            'name' => 'message',
            'type' => 'Textarea',
            'options' => array(
                'label' => 'Message',
            ),
            'attributes' => array(
                'placeholder' => 'Message',
            ),
        ));

        $captcha = new Image(array(
                        'timeout' => 3600,
                        'useNumbers' => 1,
                        'expiration' => 3600,
                        'font' => getcwd() . '/public/fonts/captcha.ttf',
                        'imgDir' => getcwd() . '/public/captcha/',
                        'imgUrl' => '/captcha/',
                        'lineNoiseLevel' => 0,
                        'dotNoiseLevel' => 50,
                        'wordLen' => 5,
                        'fontSize' => 28,
                        'height' => 75,
        ));

        $verify = new CaptchaElement('verify');
        $verify->setCaptcha($captcha);
        $verify->setLabel('Type in the letters and numbers seen here: ');

        $this->add($verify);
    }

     /**
      * Should return an array specification compatible with
      * {@link Zend\InputFilter\Factory::createInputFilter()}.
      *
      * @return array
      */
     public function getInputFilterSpecification()
     {
        return array(
            'recipients' => array(
                'name' => 'recipients',
                'required' => true,
                'validators' => array(
                    new Regex('/^[\d,]+$/'),
                ),
            ),
            'subject' => array(
                'name' => 'subject',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                    array('name' => 'StripTags'),
                    array('name' => 'StripNewlines'),
                ),
            ),
            'message' => array(
                'name' => 'message',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                    array('name' => 'StripTags'),
                ),
            ),
            'verify' => array(
                'name' => 'verify',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                ),
                'validators' => array(
                    $this->get('verify')->getCaptcha(),
                ),
            ),
        );
    }

}
