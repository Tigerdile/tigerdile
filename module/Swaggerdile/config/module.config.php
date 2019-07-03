<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

// @TODO : Many redundant routes.  Some could be reduced or
// combined into generic routes.
return array(
    'router' => array(
        'routes' => array(
            'profiles' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/:profile[/:activity]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'index',
                            ),
                        ),
            ),
            'stream' => array(
                'type' => 'Segment',
                'options' => array(
                    'route'    => '/stream',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Stream',
                        'action'     => 'index',
                    ),
                ),
               'may_terminate' => true,
               'child_routes' => array(
                   'detail' => array(
                       'type' => 'Segment',
                       'options' => array (
                           'route' => '/:stream',
                           'defaults' => array(
                               'action' => 'stream',
                           ),
                       ),
                       'may_terminate' => true,
                       'child_routes' => array(
                            'edit' => array(
                                'type' => 'Segment',
                                'options' => array(
                                    'route' => '/edit',
                                    'defaults' => array(
                                        'action' => 'edit',
                                    ),
                                ),
                                'may_terminate' => true,
                            ),
                            'obsexport' => array(
                                'type' => 'Segment',
                                'options' => array(
                                    'route' => '/obsexport',
                                    'defaults' => array(
                                        'action' => 'obsexport',
                                    ),
                                ),
                                'may_terminate' => true,
                            ),
                        ),
                   ),
               ),
            ),
            'profile-image-dump' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/content/[:activity]/[:file]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]+',
                                'activity'    => '\d+',
                                'file' => '[\w\d_.-]+',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'filedump',
                            ),
                        ),
            ),
            'admin' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route'    => '/swaggermin[/:action]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Admin',
                                'action' => 'index',
                            ),
                        ),
            ),
            'profile-subscribers' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/subscribers',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'subscribers',
                            ),
                        ),
            ),
            'rest' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route' => '/rest/[:action]',
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Rest',
                            ),
                        ),
            ),
            'profile-patreon' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/patreon',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'patreon',
                            ),
                        ),
            ),
            'profile-import' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/import',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'import',
                            ),
                        ),
            ),
            'profile-tiermap' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/tiermap',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'tiermap',
                            ),
                        ),
            ),
            'profile-posts' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/posts[/:activity][/:param]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '[a-zA-Z0-9_-]*',
                                'param'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'posts',
                            ),
                        ),
            ),
            'profile-files' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/files[/:activity]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '.*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'files',
                            ),
                        ),
            ),
            'profile-file-move' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/mv',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'filemove',
                            ),
                        ),
            ),
            'profile-file-delete' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/rm',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'filedelete',
                            ),
                        ),
            ),
            'secure-thumbnail' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/thumbnail[/:activity]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '.+',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'thumbnail',
                            ),
                        ),
            ),
            'profile-upload' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/upload-files[/:activity]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '.*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'uploadfiles',
                            ),
                        ),
            ),
            'profile-lightbox' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/lightbox[/:activity]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '.*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'lightbox',
                            ),
                        ),
            ),
            'profile-file-manage' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/file-manage[/:activity]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'    => '.+',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'filemanage',
                            ),
                        ),
            ),
            'checkout' => array(
                        'type' => 'Segment',
                         'options' => array(
                             'route' => '/checkout[/:orderId]',
                             'constraints' => array(
                                 'orderId' => '[0-9]*',
                             ),
                             'defaults' => array(
                                 '__NAMESPACE__' => 'Swaggerdile\Controller',
                                 'controller' => 'Swaggerdile\Controller\Checkout',
                                 'action' => 'index',
                             )
                         ),
            ),
            'profile-add-comment' => array(
                        'type'    => 'Segment',
                        'options' => array(
                            'route'    => '/[:profile]/comment[/:activity]',
                            'constraints' => array(
                                'profile'     => '[a-zA-Z0-9_-]*',
                                'activity'     => '[a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'Swaggerdile\Controller',
                                'controller' => 'Swaggerdile\Controller\Profile',
                                'action' => 'addcomment',
                            ),
                        ),
            ),
            'create-profile' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/create-profile',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Profile',
                        'action'     => 'create',
                    ),
                ),
            ),
            'terms' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/terms-and-conditions',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'generic',
                    ),
                ),
            ),
            'xsplit' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/help/xsplit',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'generic',
                    ),
                ),
            ),
            'adult' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/adult',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'adult',
                    ),
                ),
            ),
/* We may use this again later, but not now.
            'browse' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/browse',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'browse',
                    ),
                ),
            ),
 */
            'pricing' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/pricing',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'generic',
                    ),
                ),
            ),
            'faq' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/faq',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'generic',
                    ),
                ),
            ),
            'login' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/login',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'login',
                    ),
                ),
            ),
            'email' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/email',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Email',
                        'action'     => 'index',
                    ),
                ),
            ),
            'logout' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/logout',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'logout',
                    ),
                ),
            ),
            'signup' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/sign-up',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'signup',
                    ),
                ),
            ),
            'setup' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/setup',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Stream',
                        'action'     => 'setup',
                    ),
                ),
            ),
            'forgot-password' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/forgot-password',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'forgotpassword',
                    ),
                ),
            ),
            'recoverpassword' => array(
                'type' => 'Segment',
                'options' => array(
                    'route'    => '/recover/[:uid]',
                    'constraints' => array(
                        'uid'     => '[a-zA-Z0-9_-]+',
                    ),
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action' => 'recoverpassword',
                    ),
                ),
            ),
                
            'dashboard' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/dashboard',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Dashboard',
                        'action'     => 'index',
                    ),
                ),
            ),
            'dashboard-subscriptions' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/dashboard/subscriptions',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Dashboard',
                        'action'     => 'subscriptions',
                    ),
                ),
            ),
            'patreon' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/patreon',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Patreon',
                        'action'     => 'index',
                    ),
                ),
            ),
            'dashboard-settings' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/dashboard/settings',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Dashboard',
                        'action'     => 'settings',
                    ),
                ),
            ),
            'dashboard-financial' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/dashboard/financial',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Dashboard',
                        'action'     => 'financial',
                    ),
                ),
            ),
            'reports' => array(
                'type' => 'Segment',
                'options' => array(
                    'route'    => '/report/[:action]',
                    'constraints' => array(
                        'profile'     => '[a-zA-Z0-9_-]*',
                        'activity'     => '[a-zA-Z0-9_-]*',
                    ),
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Report',
                    ),
                ),
            ),
            'getting-started' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/getting-started',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'generic',
                    ),
                ),
            ),
            'support' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/support',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'support',
                    ),
                ),
            ),
            'sfw' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/sfw',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'sfw',
                    ),
                ),
            ),
            'homecon' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/homecon',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'generic',
                    ),
                ),
            ),
            'help' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/help',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'generic',
                    ),
                ),
            ),
            'chat_help' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/chat-help',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'generic',
                    ),
                ),
            ),
            'order' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/order',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Checkout',
                        'action'     => 'order',
                    ),
                ),
            ),
            'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        'controller' => 'Swaggerdile\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),
    'service_manager' => array(
        'abstract_factories' => array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'factories' => array(
            'translator' => 'Zend\Mvc\Service\TranslatorServiceFactory',
        ),
    ),
    'translator' => array(
        'locale' => 'en_US',
        'translation_file_patterns' => array(
            array(
                'type'     => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.mo',
            ),
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => false,
        'display_exceptions'       => false,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
