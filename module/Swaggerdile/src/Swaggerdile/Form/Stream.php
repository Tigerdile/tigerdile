<?php
/*
 * Stream.php
 *
 * Form management for the stream editing pages.
 *
 * @author sconley
 */

namespace Swaggerdile\Form;

use \Zend\Form\Form;
use \Zend\InputFilter\Factory;
use \Zend\Validator\Regex;
use \Zend\Validator\EmailAddress;

class Stream extends Form
{
    /*
     * Constructor configures the form.
     *
     * @param string optional form name (defaults to 'stream')
     */
    public function __construct($name = 'stream')
    {
        // Run the parent
        parent::__construct($name);

        // STREAM SETTINGS
        $this->add(array(
            'name' => 'title',
            'type' => 'Text',
            'options' => array(
                'label' => 'Stream Title',
            ),
            'attributes' => array(
                'placeholder' => 'Stream Title',
            ),
        ));

        $this->add(array(
            'name' => 'stream_blurb',
            'type' => 'Text',
            'options' => array(
                'label' => 'Stream Description',
            ),
            'attributes' => array(
                'placeholder' => 'Stream Description',
            ),
        ));

        $this->add(array(
            'name' => 'vipImage',
            'type' => 'File',
            'options' => array(
                'label' => 'VIP Image',
            ),
        ));

        $this->add(array(
            'name' => 'url',
            'type' => 'Text',
            'options' => array(
                'label' => 'Stream URL',
            ),
            'attributes' => array(
                'placeholder' => 'Stream URL',
            ),
        ));

        $this->add(array(
            'name' => 'rtmp_password',
            'type' => 'Text',
            'options' => array(
                'label' => 'RTMP Password',
            ),
            'attributes' => array(
                'placeholder' => 'RTMP Password',
            ),
        ));

        $this->add(array(
            'name' => 'viewer_password',
            'type' => 'Text',
            'options' => array(
                'label' => 'Viewer Password',
            ),
            'attributes' => array(
                'placeholder' => 'Viewer Password',
            ),
        ));

        $this->add(array(
            'name' => 'is_nsfw',
            'type' => 'Select',
            'options' => array(
                'label' => 'Safe For Work Mode',
                'value_options' => array(
                    '0' => 'Safe For Work (All Audiences)',
                    '1' => 'Not Safe For Work (Adult)',
                ),
            ),
            'attributes' => array(
                'placeholder' => 'Safe For Work Mode',
            ),
        ));

        // APPEARANCE OPTIONS
        // This will be "hidden" because we will use a div to
        // mock out the element and use an HTML editor.
        $this->add(array(
            'name' => 'above_stream_html',
            'type' => 'Hidden',
            'options' => array(
                'label' => 'What Appears Above Your Stream',
            ),
        ));

        $this->add(array(
            'name' => 'aspect_ratio',
            'type' => 'Select',
            'options' => array(
                'label' => 'Aspect Ratio of Stream',
                'value_options' => array(
                    '1' => '16:9 (Widescreen)',
                    '0' => '4:3 (TV)',
                )
            ),
        ));

        $this->add(array(
            'name' => 'offlineImage',
            'type' => 'File',
            'options' => array(
                'label' => 'Stream Offline Image',
            ),
        ));

        $this->add(array(
            'name' => 'below_stream_html',
            'type' => 'Hidden',
            'options' => array(
                'label' => 'What Appears Below Your Stream',
            ),
        ));

        $this->add(array(
            'name' => 'backgroundImage',
            'type' => 'File',
            'options' => array(
                'label' => 'Upload a Background Picture',
            ),
        ));

        $this->add(array(
            'name' => 'donation_email',
            'type' => 'Text',
            'options' => array(
                'label' => 'PayPal Donation Email',
            ),
            'attributes' => array(
                'placeholder' => 'PayPal Donation Email',
            ),
        ));

        $this->add(array(
            'name' => 'donateImage',
            'type' => 'File',
            'options' => array(
                'label' => 'Upload a Donation Button Image',
            ),
        ));

        // CHAT SETTINGS
        $this->add(array(
            'name' => 'is_allow_guests',
            'type' => 'Select',
            'options' => array(
                'label' => 'Allow Guests in Your Chat?',
                'value_options' => array(
                    '0' => 'No',
                    '1' => 'Yes',
                ),
            ),
        ));

        $factory = new Factory();

        $this->setInputFilter($factory->createInputFilter(
            array(
                'title' => array(
                    'name' => 'title',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                ),
                'stream_blurb' => array(
                    'name' => 'stream_blurb',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StripTags'),
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
                'rtmp_password' => array(
                    'name' => 'rtmp_password',
                    'required' => true,
                    'filters' => array(
                        array('name' => 'StringTrim'),
                        array('name' => 'StripNewlines'),
                        array('name' => 'StringToLower'),
                    ),
                    'validators' => array(
                        new Regex('/^[a-z\d_\-]+$/'),
                    ),
                ),
                'is_nsfw' => array(
                    'name' => 'is_nsfw',
                    'required' => true,
                ),
                'donation_email' => array(
                    'name' => 'donation_email',
                    'required' => false,
                    'filters' => array(
                        array('name' => 'StripTags'),
                        array('name' => 'StringTrim'),
                    ),
                    'validators' => array(
                        new EmailAddress(),
                    ),
                ),
                'above_stream_html' => array(
                    'name' => 'above_stream_html',
                    'allow_empty' => true,
                    'required' => false,
                ),
                'below_stream_html' => array(
                    'name' => 'below_stream_html',
                    'required' => false,
                    'allow_empty' => true,
                ),
            )
        ));
    }
}
