<?php
/*
 * Forgotpassword.php
 *
 * Forgot Password form
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use \Zend\Form\Form;
use \Zend\InputFilter\Factory;
use \Zend\Validator\EmailAddress;

class Forgotpassword extends Form
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'forgotpassword')
     */
    public function __construct($name = 'forgotpassword')
    {
        // Run the parent constructor
        parent::__construct($name);

        // Add elements
        $this->add(array(
            'name' => 'username',
            'type' => 'Text',
            'options' => array(
                'label' => 'Username or Email',
            ),
            'attributes' => array(
                'placeholder' => 'Username or Email',
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
                'username' => array(
                    'name' => 'username',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                ),
            )
        ));
    }
}
