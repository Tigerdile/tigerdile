<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Swaggerdile;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Swaggerdile\Authentication\Storage\CookieBase;
use Zend\Authentication\AuthenticationService;
use \Zend\Mvc\Controller\ControllerManager;

class Module
{
    /*
     * onBootstrap
     *
     * Run before anything else runs, on initialization of module.
     */
    public function onBootstrap(MvcEvent $e)
    {
        // Enable php settings from config file into the web app
        $services = $e->getApplication()->getServiceManager();

        $config = $services->get('config');        
        $phpSettings = $config['php_settings'];

        if ($phpSettings) {
            foreach ($phpSettings as $key => $value) {
                ini_set($key, $value);
            }
        }

        $eventManager        = $e->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        // Initialize media
        $services->get('Media');

        // Set up our custom exception strategy
        $eventManager->attach($services->get('SDExceptionStrategy'), 100);
        $eventManager->attach($services->get('SDRouteNotFoundStrategy'), 100);
    }

    /*
     * getConfig
     *
     * Load our module configuration.
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /*
     * getAutoloaderConfig
     *
     * Set up our autoloader for the module.
     *
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    /*
     * Custom view helper configuration
     */
    public function getViewHelperConfig()
    {
        return array(
            'factories' => array(
                'comments' => function ($serviceManager) {
                    return new \Swaggerdile\View\Helper\Comments($serviceManager);
                },
                'ads' => function ($serviceManager) {
                    return new \Swaggerdile\View\Helper\Ads($serviceManager);
                },
                'isAdult' => function($serviceManager) {
                    return new \Swaggerdile\View\Helper\IsAdult($serviceManager);
                },
            )
        );
    }

    /*
     * Generic Controller initializer
     *
     * @param ControllerManager
     * @param string
     * @return appropriate controller
     */
    public function getControllerInit($cm, $controller)
    {
        $sm = $cm->getServiceLocator();

        $controller = 'Swaggerdile\Controller\\' . $controller;

        $cont = new $controller();
        $cont->setUser($sm->get('User'))
             ->setLocator($sm);

        return $cont;
    }

    /*
     * Factories for controller configuration
     *
     * TODO: Make this more consistent
     */
    public function getControllerConfig()
    {
        return array(
            'factories' => array(
                'Swaggerdile\Controller\Index' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'IndexController');
                },
                'Swaggerdile\Controller\Profile' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'ProfileController');
                },
                'Swaggerdile\Controller\Checkout' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'CheckoutController');
                },
                'Swaggerdile\Controller\Dashboard' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'DashboardController');
                },
                'Swaggerdile\Controller\Email' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'EmailController');
                },
                'Swaggerdile\Controller\Admin' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'AdminController');
                },
                'Swaggerdile\Controller\Report' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'ReportController');
                },
                'Swaggerdile\Controller\Patreon' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'PatreonController');
                },
                'Swaggerdile\Controller\Stream' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'StreamController');
                },
                'Swaggerdile\Controller\Rest' => function(ControllerManager $cm) {
                    return $this->getControllerInit($cm, 'RestController');
                },
            ),
        );
    }

    /*
     * getServiceConfig
     *
     * This sets up our services like the authentication
     * service to handle logins.
     *
     * @return array
     */
    public function getServiceConfig()
    {
        return array(
                'factories' => array(
                    'AuthService' => function($sm) {
                        // Make sure database is started.
                        $sm->get('Model');
                    
                        $authService = new AuthenticationService();
                        $adapter = new \Swaggerdile\Authentication\Adapter\Wordpress();

                        $authService->setAdapter($adapter);

                        // Get configuration
                        $config = $sm->get('config');

                        $authService->setStorage(new CookieBase(
                                                    $config['cookiebase_short_secret'],
                                                    $config['cookiebase_secret'],
                                                    $config['cookiebase_name'],
                                                    $config['cookiebase_timeout'],
                                                    $config['cookiebase_domain']));

                        return $authService;
                    },

                    'IsAdult' => function($sm) {
                        $request = $sm->get('Request');

                        $cookie = $request->getCookie();
                        return (is_object($cookie) &&
                                $cookie->offsetExists('swaggerdile_opt_in') &&
                                (int)$cookie->swaggerdile_opt_in);
                    },

                    'Model' => function($sm) {
                        $dbAdapter = $sm->get('\Zend\Db\Adapter\Adapter');
                        \Zend\Db\TableGateway\Feature\GlobalAdapterFeature::setStaticAdapter($dbAdapter);

                        if($sm->get('IsAdult')) {
                            \Swaggerdile\Model\ModelTable::enableAdult();
                        } else{
                            \Swaggerdile\Model\ModelTable::disableAdult();
                        }

                        return \Swaggerdile\Model\Factory::getInstance();
                    },

                    'Media' => function($sm) {
                        $config = $sm->get('config');
                        return new \Swaggerdile\Media(  $config['media']['publicMediaBasePath'],
                                                        $config['media']['privateMediaBasePath']);
                    },

                    'Wordpress' => function($sm) {
                        $config = $sm->get('config');
                        return new \Swaggerdile\Wordpress(  $config['wordpress_api_url'],
                                                            $config['wordpress_api_key']);
                    },

                    'Email' => function($sm) {
                        $config = $sm->get('config');
                        return new \Swaggerdile\Mail($config['mail']);
                    },

                    'Crypto' => function($sm) {
                        $config = $sm->get('config');
                        return new \Swaggerdile\Crypto($config['crypto']);
                    },

                    'Cache' => function($sm) {
                        $config = $sm->get('config');

                        if(array_key_exists('cloudflare_zone', $config)) {
                            return new \Swaggerdile\Cache(
                                $config['cloudflare_zone'],
                                $config['cloudflare_user'],
                                $config['cloudflare_key']);
                        } else {
                            return new \Swaggerdile\Cache('', '', '');
                        }
                    }, 

                    'SDExceptionStrategy' => function($sm) {
                        $strategy = new \Swaggerdile\Mvc\View\Http\ExceptionStrategy();

                        // @TODO : make this configurable
                        $strategy->setDisplayExceptions(false);
                        $strategy->setExceptionTemplate("error/index");

                        return $strategy;
                    },

                    'SDRouteNotFoundStrategy' => function($sm) {
                        $strategy = new \Swaggerdile\Mvc\View\Http\RouteNotFoundStrategy();

                        // @TODO : make this configurable
                        $strategy->setDisplayExceptions(false);
                        $strategy->setNotFoundTemplate("error/404");

                        return $strategy;
                    },

                    'User' => function($sm) {
                        $authAdapter = $sm->get('AuthService');

                        if(!$authAdapter->hasIdentity()) {
                            return false;
                        }

                        $id = $authAdapter->getIdentity();

                        if(!is_object($id)) {
                            $user = $sm->get('Model')
                                      ->get('User')
                                      ->fetchById($id);
                        } else {
                            $user = $sm->get('Model')
                                      ->get('User')
                                      ->fetchById($id->getId());
                        }

                        return $user === null ? false : $user; 
                    },

                    'Jira' => function($sm) {
                        return new \chobie\Jira\Api(
                                    TIGERDILE_JIRA_URL,
                                    new \chobie\Jira\Api\Authentication\Basic(
                                        TIGERDILE_JIRA_USER,
                                        TIGERDILE_JIRA_PASSWORD
                                    )
                        );
                    },

                    'Chat' => function($sm) {
                        return new \Swaggerdile\Chat();
                    },
                ),
        );
    }
}
