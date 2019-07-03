<?php
/*
 * Patreon.php
 *
 * Patreon form definition.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use \Zend\Form\Form;
use \Zend\InputFilter\Factory;

class Patreon extends Form
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'comment')
     */
    public function __construct($name = 'patreon')
    {
        // Run the parent constructor
        parent::__construct($name);

        // Add elements
        $this->add(array(
            'name' => 'patreon_client_id',
            'type' => 'Text',
            'options' => array(
                'label' => 'Client ID',
            ),
        ));

        $this->add(array(
            'name' => 'patreon_client_secret',
            'type' => 'Text',
            'options' => array(
                'label' => 'Client Secret',
            ),
        ));

        $this->add(array(
            'name' => 'patreon_access_token',
            'type' => 'Text',
            'options' => array(
                'label' => 'Access Token',
            ),
        ));

        $this->add(array(
            'name' => 'patreon_refresh_token',
            'type' => 'Text',
            'options' => array(
                'label' => 'Refresh Token',
            ),
        ));

        $this->add(array(
            'name' => 'act',
            'type' => 'Submit',
            'attributes' => array(
                'value' => 'Save',
                'class' => 'btn btn-success',
            )
        ));

        // Create our validation filters
        $factory = new Factory();

        // Add the input filters
        $this->setInputFilter($factory->createInputFilter(
            array(
                'patreon_client_id' => array(
                    'name' => 'patreon_client_id',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                ),
                'patreon_client_secret' => array(
                    'name' => 'patreon_client_secret',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                ),
                'patreon_access_token' => array(
                    'name' => 'patreon_access_token',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                ),
                'patreon_refresh_token' => array(
                    'name' => 'patreon_refresh_token',
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
