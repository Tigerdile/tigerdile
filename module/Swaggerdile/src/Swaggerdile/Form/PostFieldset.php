<?php
/*
 * PostFieldset.php
 *
 * Posts fieldset definition.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class PostFieldset extends Fieldset implements InputFilterProviderInterface
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'post')
     */
    public function __construct($name = 'post')
    {
        // run the parent constructor
        parent::__construct($name);

        // Add our elements
        $this->add(array(
            'name' => 'title',
            'type' => 'Text',
            'required' => true,
            'options' => array(
                'label' => 'Post Title',
            ),
            'attributes' => array(
                'placeholder' => 'Post Title',
            ),
        ));

        // This will be "hidden" because we will use a div to
        // mock out the element and use an HTML editor.
        $this->add(array(
            'name' => 'content',
            'type' => 'Hidden',
            'required' => true,
            'options' => array(
                'label' => 'Post Content',
            ),
        ));

        // Disable comments?
        $this->add(array(
            'name' => 'isCommentsDisabled',
            'type' => 'Select',
            'required' => true,
            'options' => array(
                'label' => 'Do you want to disable comments on this post?',
                'value_options' => array(
                        '0' => 'No',
                        '1' => 'Yes',
                ),
            ),
        ));

        // What tiers is this comment visible to?
        $this->add(array(
            'name' => 'visibleToTiers',
            'type' => 'Select',
            'required' => false,
            'options' => array(
                'label' => 'What tiers is this post visible to?  If you pick no tiers, it will be visible to all your subscribers.  For windows, you may hold the Control (CTRL) key to select multiple tiers.  For Mac, you hold the Command (clover) key.  You can also click and drag, or hold down the Shift key, to easily select many at once.',
            ),
            'attributes' => array(
                'multiple' => 'multiple',
            ),
        ));
    }

     /**
      * Should return an array specification compatible with
      * {@link Zend\InputFilter\Factory::createInputFilter()}.
      *
      * @return array
      */
     public function getInputFilterSpecification()
     {
        return array(
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
            'isCommentsDisabled' => array(
                'name' => 'isCommentsDisabled',
                'required' => true,
            ),
            'visibleToTiers' => array(
                'name' => 'visibleToTiers',
                'required' => false,
            ),
        );
     }
}
