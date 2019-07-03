<?php
/*
 * ContentFieldset.php
 *
 * Content fieldset definition.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class ContentFieldset extends Fieldset implements InputFilterProviderInterface
{
    /*
     * Constructor will configure the form.
     *
     * @param string optional form name (defaults to 'post')
     */
    public function __construct($name = 'content')
    {
        // run the parent constructor
        parent::__construct($name);

        // Add our elements
        $this->add(array(
            'name' => 'title',
            'type' => 'Text',
            'required' => true,
            'options' => array(
                'label' => 'Name',
            ),
            'attributes' => array(
                'placeholder' => 'Name',
            ),
        ));

        // This will be "hidden" because we will use a div to
        // mock out the element and use an HTML editor.
        $this->add(array(
            'name' => 'content',
            'type' => 'Hidden',
            'options' => array(
                'label' => 'Content',
            ),
        ));

        // Is sample?
        $this->add(array(
            'name' => 'isSample',
            'type' => 'Select',
            'required' => true,
            'options' => array(
                'label' => 'Is this a sample, available to non-subscribers?',
                'value_options' => array(
                        '0' => 'No - Only Subscribers May See It',
                        '1' => 'Yes - Everyone Can See It',
                ),
            ),
        ));

        // Never historical
        $this->add(array(
            'name' => 'isNeverHistorical',
            'type' => 'Select',
            'required' => true,
            'options' => array(
                'label' => 'If you have set a Historical Fee on your profile, then users who have not paid the fee will only see content posted from the time that they joined.  You may wish to have some content available to all your subscribers, no matter when they joined.  Would you like to bypass the Historical Fee for this item and allow your subscribers to see it regardless of when they joined?',
                'value_options' => array(
                        '0' => 'No - Historical Fee Rules Apply',
                        '1' => 'Yes - Bypass Historical Fee Rule',
                ),
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
                        '0' => 'No - Comments are Enabled',
                        '1' => 'Yes - Comments are Disabled',
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

        // File to upload along with this content
        $this->add(array(
            'name' => 'file',
            'type' => 'File',
            'required' => false,
            'options' => array(
                'label' => 'Upload File',
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
                'filters' => array(
                    array('name' => 'StringTrim'),
                ),
                'required' => false,
            ),
            'isSample' => array(
                'name' => 'isSample',
                'required' => true,
            ),
            'isNeverHistorical' => array(
                'name' => 'isNeverHistorical',
                'required' => true,
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
