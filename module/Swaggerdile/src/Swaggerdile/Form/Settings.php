<?php
/*
 * Settings.php
 *
 * Settings form definition.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use \Zend\Form\Form;
use \Zend\InputFilter\Factory;
use \Zend\Validator\EmailAddress;

class Settings extends Form
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'settings')
     */
    public function __construct($name = 'settings')
    {
        // Run the parent constructor
        parent::__construct($name);

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

        // Email checkboxes
        $this->add(array(
            'name' => 'info_optout',
            'type' => 'Select',
            'options' => array(
                'label' => 'Receive Informational Emails?',

                // These are 'backwards' on purpose
                'value_options' => array(
                    '0' => 'Yes',
                    '1' => 'No',
                ),
            ),
        ));

        $this->add(array(
            'name' => 'billing_optout',
            'type' => 'Select',
            'options' => array(
                'label' => 'Receive Billing Emails?',

                // These are 'backwards' on purpose
                'value_options' => array(
                    '0' => 'Yes',
                    '1' => 'No',
                ),
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
                    'required' => false,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                ),
            )
        ));
    }
}
