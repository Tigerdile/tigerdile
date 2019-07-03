<?php
/*
 * ProfileFieldset.php
 *
 * Form to edit the layout of a profile page.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use Zend\Form\Fieldset;
use Zend\Validator\Regex;
use Zend\InputFilter\InputFilterProviderInterface;

class ProfileFieldset extends Fieldset implements InputFilterProviderInterface
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

        // Add our elements
        $this->add(array(
            'name' => 'title',
            'type' => 'Text',
            'options' => array(
                'label' => 'Profile Title',
            ),
            'attributes' => array(
                'placeholder' => 'Profile Title',
            ),
        ));

        // This will be "hidden" because we will use a div to
        // mock out the element and use an HTML editor.
        $this->add(array(
            'name' => 'content',
            'type' => 'Hidden',
            'options' => array(
                'label' => 'Profile Content',
            ),
        ));

        // URL
        $this->add(array(
            'name' => 'url',
            'type' => 'Text',
            'options' => array(
                'label' => 'Profile URL',
            ),
            'attributes' => array(
                'placeholder' => 'JUST the last part of the URL!',
            ),
        ));

        // Historical fee
        $this->add(array(
            'name' => 'historicalFee',
            'type' => 'Text',
            'options' => array(
                'label' => 'Do you want to charge new sponsors extra to see content posted before they joined?  Enter a dollar amount below if you do.',
            ),
            'attributes' => array(
                'placeholder' => 'Dollars, no extra symbols ($ or ,)',
            ),
        ));

        // Is NSFW?
        $this->add(array(
            'name' => 'isNsfw',
            'type' => 'Select',
            'options' => array(
                'label' => 'Is your adult in nature or "Not Safe for Work"?  Selecting this will require an adult opt-in to view your profile.',
                'value_options' => array(
                        '0' => 'No, Safe for Work Content',
                        '1' => 'Yes, Adult or Not Safe for Work Content',
                ),
            ),
        ));

        // Is Active?
        $this->add(array(
            'name' => 'isVisible',
            'type' => 'Select',
            'options' => array(
                'label' => 'Selecting this will make your profile publically visible and searchable.  If this is set to "No", only people with the full URL to your profile will be able to see it.',
                'value_options' => array(
                        '0' => 'No',
                        '1' => 'Yes',
                ),
            ),
        ));

        // Is hiatus?
        $this->add(array(
            'name' => 'isHiatus',
            'type' => 'Select',
            'options' => array(
                'label' => 'Turning on hiatus mode will leave your profile accessible, but will not run monthly billing.  New customers will still be charged if they choose to access historical content (if enabled), or if you use up-front billing.',
                'value_options' => array(
                        '0' => 'No',
                        '1' => 'Yes',
                ),
            ),
        ));

        // Use watermarking?
        $this->add(array(
            'name' => 'useWatermark',
            'type' => 'Select',
            'options' => array(
                'label' => 'Would you like to use Watermarking?',
                'value_options' => array(
                    '0' => 'None',
                    '1' => 'DileTracks Stealth Watermarking',
                    '2' => 'Unobtrusive Watermarking',
                    '3' => 'Obtrusive Watermarking',
                ),
            ),
        ));

        // Add file button
        $this->add(array(
            'name' => 'profileIcon',
            'type' => 'File',
            'options' => array(
                'label' => "Please pick an image that will be used as your 'icon' in the browse listing, among other places.  This image should be 280 x 200 pixels in size, and should be a JPEG.  If you provide an incorrect size or file format, Swaggerdile will try to resize and reformat the picture for you; however, for best quality, please provide the correct size and format to begin with.  This is optional, but very recommended as your 'Icon' will be an unattractive black box otherwise!",
            ),
        ));

        // Payment method to use
        // @TODO : source this from the DB.
        $this->add(array(
            'name' => 'paymentTypeId',
            'type' => 'Select',
            'options' => array(
                'label' => 'How do you want your customers to pay?',
                'value_options' => array(
                    '1' => 'Monthly: Prorated Up-Front Payment Required',
                    '2' => 'Monthly: No Up-Front Payment',
                    '3' => 'Monthly: Full Up-Front Payment Required, Full First Month Payment',
                    '4' => 'Monthly: Full Up-Front Payment Required, Skip First Month Payment'
                )
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
            'url' => array(
                'name' => 'url',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                    array('name' => 'StripNewlines'),
                    array('name' => 'StringToLower'),
                ),
                'validators' => array(
                    new Regex('/^[\w\d\-]+$/'),
                ),
            ),
            'historicalFee' => array(
                'name' => 'historicalFee',
                'required' => true,
                'filters' => array(
                    array('name' => 'StringTrim'),
                ),
                'validators' => array(
                    new Regex('/^\d+(\.\d{2}){0,1}$/'),
                ),
            ),
            'isNsfw' => array(
                'name' => 'isNsfw',
                'required' => true,
            ),
            'isVisible' => array(
                'name' => 'isVisible',
                'required' => true,
            ),
            'isHiatus' => array(
                'name' => 'isHiatus',
                'required' => true,
            ),
            'useWatermark' => array(
                'name' => 'useWatermark',
                'required' => false,
            ),
            'paymentTypeId' => array(
                'name' => 'paymentTypeId',
                'required' => true,
            ),
        );
     }
}
