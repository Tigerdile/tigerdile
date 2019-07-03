<?php
/*
 * Signup.php
 *
 * Signup form definition.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use \Zend\Form\Form;
use \Zend\InputFilter\Factory;

class Comment extends Form
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'comment')
     */
    public function __construct($name = 'comment')
    {
        // Run the parent constructor
        parent::__construct($name);

        // Add elements
        $this->add(array(
            'name' => 'parentId',
            'type' => 'Hidden',
        ));


        $this->add(array(
            'name' => 'content',
            'type' => 'Textarea',
            'options' => array(
                'label' => 'Comment Text',
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
                'content' => array(
                    'name' => 'content',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                ),
                'parentId' => array(
                    'name' => 'parentId',
                    'required' => false,
                ),
            )
        ));
    }
}
