<?php
/*
 * IsAdult.php
 *
 * Helper to determine if user has set themselves as adult or not.
 *
 * @author sconley
 */

namespace Swaggerdile\View\Helper;

use Zend\View\Helper\AbstractHelper;


class IsAdult extends AbstractHelper
{
    /*
     * Service Manager
     *
     * @var ServiceManager
     */
    protected $_sm = null;

    /*
     * Constructor
     *
     * Get the service manager
     *
     * @param ServiceManager
     */
    public function __construct($sm)
    {
        $this->_sm = $sm;
    }

    /*
     * Return boolean if adult
     *
     * @return boolean
     */
    public function __invoke()
    {
        return $this->_sm->get('IsAdult');
    }
}
