<?php
/*
 * Profile.php
 *
 * Form to edit the layout of a profile page.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use \Zend\Form\Form;
use \Zend\Validator\Regex;
use \Zend\InputFilter\InputFilter;
use \Zend\InputFilter\Factory;

class Profile extends Form
{
    /*
     * Constructor will configure the form
     *
     * @parma string optional form name (defaults to profile)
     */
    public function __construct($name = 'profile')
    {
        // run the parent constructor
        parent::__construct($name);

        $this->add(array(
            'name' => 'profile',
            'type' => 'Swaggerdile\Form\ProfileFieldset',
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
                'value' => 'Save',
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
/*
        $this->setValidationGroup(array(
            'profile' => array(
                                'title',
                                'content',
                                'url',
                                'historicalFee',
                                'isNsfw',
                                'isVisible',
            )
        ));  */
    }
}
