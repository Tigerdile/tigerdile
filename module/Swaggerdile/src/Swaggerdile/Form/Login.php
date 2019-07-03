<?php
/*
 * Login.php
 *
 * Login form definition.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use Zend\Form\Form;
use Zend\InputFilter\Factory;

class Login extends Form
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'login')
     */
    public function __construct($name = 'login')
    {
        // Run the parent constructor
        parent::__construct($name);

        // Add elements
        $this->add(array(
            'name' => 'login',
            'type' => 'Text',
            'required' => true,
            'options' => array(
                'label' => 'Tigerdile Username or Email',
            ),
            'filters' => array(
                array('name' => 'StripTags'),
                array('name' => 'StringTrim'),
            ),
            'attributes' => array(
                'placeholder' => 'Username or Email',
            ),
        ));

        $this->add(array(
            'name' => 'password',
            'type' => 'Password',
            'required' => true,
            'options' => array(
                'label' => 'Password',
            ),
            'filters' => array(
                array('name' => 'StripTags'),
                array('name' => 'StringTrim'),
            ),
            'attributes' => array(
                'placeholder' => 'Password',
            ),
        ));

        $this->add(array(
            'name' => 'act',
            'type' => 'Submit',
            'attributes' => array(
                'value' => 'Login',
                'class' => 'button',
            )
        ));
    }
}
