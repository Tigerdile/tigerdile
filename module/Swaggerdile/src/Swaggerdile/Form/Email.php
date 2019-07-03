<?php
/*
 * Email.php
 *
 * Form to create or edit content.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use \Zend\Form\Form;
use \Zend\Validator\Regex;
use \Zend\InputFilter\InputFilter;
use \Zend\InputFilter\Factory;

class Email extends Form
{
    /*
     * Constructor will configure the form
     *
     * @param string optional form name (defaults to email)
     */
    public function __construct($name = 'email')
    {
        // run the parent constructor
        parent::__construct($name);

        $this->add(array(
            'name' => 'email',
            'type' => 'Swaggerdile\Form\EmailFieldset',
            'options' => array(
                'use_as_base_fieldset' => true,
            ),
        ));

        /*
        $this->add(array(
            'type' => 'Zend\Form\Element\Csrf',
            'name' => 'csrf',
        ));
         */

        $this->add(array(
            'name' => 'act',
            'attributes' => array(
                'type' => 'submit',
                'value' => 'Send',
            ),
        ));

        //setting InputFilters here
        $inputFilter = new InputFilter();
        $factory = new Factory();

        // Now add fieldset Input filter
        foreach($this->getFieldsets() as $fieldset){
              $fieldsetInputFilter = $factory->createInputFilter($fieldset->getInputFilterSpecification());
              $inputFilter->add($fieldsetInputFilter,$fieldset->getName());
        }

        //Set InputFilter
        $this->setInputFilter($inputFilter);
    }
}
