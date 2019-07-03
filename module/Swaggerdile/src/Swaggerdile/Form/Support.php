<?php
/*
 * Support.php
 *
 * This used to the form to support a profile.
 *
 * Now I'm using the same name to build the support form :D  We shouldn't
 * need this old code anymore.
 *
 * @author sconley
 */


namespace Swaggerdile\Form;


use Zend\Form\Form;
use Zend\InputFilter\InputFilter;
use Zend\Validator\EmailAddress;
use Zend\Form\Element\Captcha as CaptchaElement;
use Zend\Captcha\Image;

class Support extends Form
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'support')
     */
    public function __construct($name = 'support')
    {
        // Run the parent constructor
        parent::__construct($name);


        // Name
        $this->add(array(
            'name' => 'name',
            'type' => 'Text',
            'options' => array(
                'label' => 'Your Name',
            )
        ));

        // Email Address
        $this->add(array(
            'name' => 'email',
            'type' => 'Text',
            'options' => array(
                'label' => 'Email',
            )
        ));

        // MEssage body
        $this->add(array(
            'name' => 'message',
            'type' => 'Textarea',
            'options' => array(
                'label' => 'How can Tigerdile love you today?'
            )
        ));

        // Captcha -- @TODO: centralize this code with signup
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
                'value' => 'Fire Away!',
                'class' => 'button',
            )
        ));

        //setting InputFilters here
        $inputFilter = new InputFilter();

        // set up validator
        $inputFilter->add(array(
                'name' => 'name',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                    array('name' => 'StripTags'),
                ),
        ));

        $inputFilter->add(array(
                'name' => 'email',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                    array('name' => 'StripTags'),
                ),
                'validators' => array(
                    new EmailAddress(),
                ),
        ));

        $inputFilter->add(array(
                'name' => 'message',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                    array('name' => 'StripTags'),
                ),
        ));

        $inputFilter->add(array(
                'name' => 'verify',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                ),
                'validators' => array(
                    $this->get('verify')->getCaptcha(),
                )
            )
        );

        $this->setInputFilter($inputFilter);
    }
}
