<?php
/*
 * Controller
 *
 * Base controller to provide common functionality to all
 * Swaggerdile controllers.
 *
 * @author sconley
 */

namespace Swaggerdile;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Http\Header\SetCookie;
use Swaggerdile\Model\Factory;

abstract class Controller extends AbstractActionController
{
    /**
     * Keep track of our user once we have loaded it.
     *
     * @var User
     */
    protected $_user = null;

    /**
     * Keep track of our view once we have loaded it.
     *
     * @var ViewModel
     */
    protected $_view = null;

    /**
     * @TODO : do this right, hackity hack!
     *
     * @var ServiceLocator
     */
    protected $_locator = null;

    /*
     * getLocator
     *
     * Get the service locator
     *
     * @TODO : deprecate this
     */
    public function getLocator()
    {
        return $this->_locator;
    }

    /*
     * setLocator
     *
     * @TODO : decprecate this
     */
    public function setLocator($locator)
    {
        $this->_locator = $locator;
        return $this;
    }

    /*
     * getUser
     *
     * Fetch our currently logged in user, or null if no user logged in.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->_user;
    }

    /*
     * Inject user
     *
     * @param null|User
     * @return $this
     */
    public function setUser($user)
    {
        $this->_user = $user;
        return $this;
    }

    /*
     * getIsAdult
     *
     * Returns boolean, if adult cookie is set.
     *
     * @return boolean
     */
    public function getIsAdult()
    {
        return $this->_locator->get('IsAdult');
    }

    /*
     * setIsAdult
     *
     * Sets (or clears) the adultness of the user
     *
     * @param boolean
     */
    public function setIsAdult($adult)
    {
        $val = '0';

        if($adult) {
            $val = '1';
        }

        $cookie = new SetCookie('swaggerdile_opt_in', $val,
                                time() + 365 * 60 * 60 * 24,
                                '/');

        $this->getResponse()->getHeaders()->addHeader($cookie);
    }

    /*
     * getView
     *
     * Set up our view, if we're using one.
     *
     * @param boolean   disable layout?  Defaults false
     * @return ViewModel
     */
    public function getView($disableLayout = false)
    {
        if(!is_object($this->_view)) {
            $this->_view = new ViewModel();

            if(!$disableLayout) {
                // set up our layout now.
                $this->layout()->setVariable('user', $this->getUser());
            }

            $this->_view->setVariable('user', $this->getUser());
            $this->_view->setVariable('userIsAdult', $this->getIsAdult());
        }

        $this->_view->setTerminal($disableLayout);

        return $this->_view;
    }

    /*
     * getModel
     *
     * Get our model factory object.
     *
     * @return \Swaggerdile\Model\Factory
     */
    public function getModel()
    {
        return $this->_locator->get('Model');
    }

    /*
     * getMedia
     *
     * Get our media processing object.
     *
     * @return \Swaggerdile\Media
     */
    public function getMedia()
    {
        return $this->_locator->get('Media');
    }
 
    /*
     * getConfig
     *
     * Get the full config file
     *
     * @return Zend config object
     */
    public function getConfig(){
        return $this->_locator->get('config');
    }

    /*
     * getLogger
     *
     * Get the logger object
     *
     * @return Zend log object
     */
    public function getLogger()
    {
        return $this->_locator->get('logger');
    }

    /*
     * This is a very common need - rerun patreon process
     *
     * @param integer - profile ID if we have it.
     */
    protected function _rerunPatreon($profileId = null)
    {
        if(!$profileId) {
            $profileId = $this->_profile->getId();
        }

        $config = $this->getConfig();
        $command = $config['patreon_processor_cmd'] . " {$profileId}";

        // Run the command
        exec($command);
    }
}
