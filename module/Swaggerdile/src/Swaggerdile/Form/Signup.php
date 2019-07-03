<?php
/*
 * Signup.php
 *
 * Signup form definition.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use Zend\Form\Form;
use Zend\InputFilter\Factory;
use Zend\Validator\EmailAddress;
use Zend\Form\Element\Captcha as CaptchaElement;
use Zend\Captcha\Image;
use Zend\Validator\Regex;

class Signup extends Form
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'signup')
     */
    public function __construct($name = 'signup')
    {
        // Run the parent constructor
        parent::__construct($name);

        // Add elements
        $this->add(array(
            'name' => 'username',
            'type' => 'Text',
            'options' => array(
                'label' => 'Username',
            ),
            'attributes' => array(
                'placeholder' => 'Username',
            ),
        ));

        // Add elements
        $this->add(array(
            'name' => 'email',
            'type' => 'Text',
            'options' => array(
                'label' => 'Email',
            ),
            'attributes' => array(
                'placeholder' => 'Email',
            ),
        ));

        $this->add(array(
            'name' => 'password',
            'type' => 'Password',
            'required' => true,
            'options' => array(
                'label' => 'Password',
            ),
            'attributes' => array(
                'placeholder' => 'Password',
            ),
        ));

        $this->add(array(
            'name' => 'confirmpassword',
            'type' => 'Password',
            'required' => true,
            'options' => array(
                'label' => 'Confirm Password',
            ),
            'attributes' => array(
                'placeholder' => 'Confirm Password',
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

        $this->add(array(
            'name' => 'act',
            'type' => 'Submit',
            'attributes' => array(
                'value' => 'Submit',
                'class' => 'button',
            )
        ));

        // Create our validation filters
        $factory = new Factory();

        // Add the input filters
        $this->setInputFilter($factory->createInputFilter(
            array(
                'username' => array(
                    'name' => 'username',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                    'validators' => array(
                        new Regex('/^[\w\d_ @.-]+$/'),
                    )
                ),
                'email' => array(
                    'name' => 'email',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                    'validators' => array(
                        new EmailAddress(),
                    ),
                ),
                'password' => array(
                    'name' => 'password',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
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
            )
        ));
    }
}
