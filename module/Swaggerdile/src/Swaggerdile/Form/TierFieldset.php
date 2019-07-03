<?php
/*
 * TierFieldset
 *
 * The form fieldset for tiers.
 *
 * @author sconley
 */
 
namespace Swaggerdile\Form;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\Regex;

class TierFieldset extends Fieldset implements InputFilterProviderInterface
{
    /*
     * Constructor to set up the form
     *
     * @param string optional form name.
     */
    public function __construct($name = 'tier')
    {
        parent::__construct($name);

        // Set up our fields

        // we may have a tier ID if the tier already exists.
        $this->add(array(
            'name' => 'tierId',
            'type' => 'Hidden',
        ));

        $this->add(array(
            'name' => 'title',
            'type' => 'Text',
            'options' => array(
                'label' => 'Tier Title',
            ),
            'attributes' => array(
                'placeholder' => 'Tier Title',
            ),
        ));

        // This will be "hidden" because we will use a div to
        // mock out the element and use an HTML editor.
        $this->add(array(
            'name' => 'content',
            'type' => 'Hidden',
            'options' => array(
                'label' => 'Tier Info',
            ),
        ));

        // Does this require shipping?
        $this->add(array(
            'name' => 'isShippable',
            'type' => 'Select',
            'options' => array(
                'label' => 'Does this tier include items that require shipping?',
                'value_options' => array(
                        '0' => 'No',
                        '1' => 'Yes',
                ),
            ),
        ));

        // Price
        $this->add(array(
            'name' => 'price',
            'type' => 'Text',
            'options' => array(
                'label' => 'What contribution level is required to access this tier?',
            ),
            'attributes' => array(
                'placeholder' => 'Dollars, no extra symbols ($ or ,)',
            ),
        ));

        // Total number of claimers
        $this->add(array(
            'name' => 'maxAvailable',
            'type' => 'Text',
            'options' => array(
                'label' => 'Maximum number of people that can subscribe at this tier, or blank for unlimited.',
            ),
            'attributes' => array(
                'placeholder' => 'A number, or blank',
            ),
        ));
    }

    /**
     * @return array
     */
    public function getInputFilterSpecification()
    {
       return array(
            'tierId' => array(
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
            'isShippable' => array(
                'name' => 'isShippable',
                'required' => true,
            ),
            'maxAvailable' => array(
                'name' => 'maxAvailable',
                'required' => false,
                'filters' => array(
                    array('name' => 'Int'),
                ),
            ),
       );
    }
}
