<?php
/*
 * MilestoneFieldset
 *
 * The form fieldset for milestones
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\Regex;

class MilestoneFieldset extends Fieldset implements InputFilterProviderInterface
{
    /*
     * Constructor to set up the form
     *
     * @param string optional form name.
     */
    public function __construct($name = 'milestone')
    {
        parent::__construct($name);

        // Set up our fields

        // we may have a tier ID if the tier already exists.
        $this->add(array(
            'name' => 'milestoneId',
            'type' => 'Hidden',
        ));

        $this->add(array(
            'name' => 'title',
            'type' => 'Text',
            'required' => true,
            'options' => array(
                'label' => 'Milestone Title',
            ),
            'attributes' => array(
                'placeholder' => 'Milestone Title',
            ),
        ));

        // This will be "hidden" because we will use a div to
        // mock out the element and use an HTML editor.
        $this->add(array(
            'name' => 'content',
            'type' => 'Hidden',
            'required' => true,
            'options' => array(
                'label' => 'Milestone Info',
            ),
        ));

        // Price
        $this->add(array(
            'name' => 'price',
            'type' => 'Text',
            'required' => true,
            'options' => array(
                'label' => 'How much monthly income is required to meet this goal?',
            ),
            'filters' => array(
                array('name' => 'StringTrim'),
            ),
            'validators' => array(
                new Regex('/^\d+(\.\d{2}){0,1}$/'),
            ),
            'attributes' => array(
                'placeholder' => 'Dollars, no extra symbols ($ or ,)',
            ),
        ));
    }

    /**
     * @return array
     */
    public function getInputFilterSpecification()
    {
       return array(
           'milestoneId' => array(
               'name' => 'tierId',
               'filters' => array(
                   array('name' => 'Int'),
               ),
               'required' => false,
           ),
           'title' => array(
               'name' => 'title',
               'required' => true,
               'filters' => array(
                   array('name' => 'StringTrim'),
                   array('name' => 'StripNewlines'),
               ),
           ),
           'content' => array(
               'name' => 'content',
               'required' => true,
               'filters' => array(
                   array('name' => 'StringTrim'),
               ),
           ),
           'price' => array(
               'name' => 'price',
               'required' => true,
               'filters' => array(
                   array('name' => 'StringTrim'),
               ),
               'validators' => array(
                   new Regex('/^\d+(\.\d{2}){0,1}$/'),
               ),
           ),
       );
    }
}
