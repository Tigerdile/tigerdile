<?php
/*
 * RouteNotFoundStrategy
 *
 * This is an exception handler class that injects our custom
 * logging and error page display.
 *
 * @author sconley
 */

namespace Swaggerdile\Mvc\View\Http;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\Response as HttpResponse;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface as Response;
use Zend\View\Model\ViewModel;
use Zend\Mvc\View\Http\RouteNotFoundStrategy as ZendRouteNotFoundStrategy;



class RouteNotFoundStrategy extends ZendRouteNotFoundStrategy
{
    /**
     * Create and return a 404 view model
     *
     * @param  MvcEvent $e
     * @return void
     */
    public function prepareNotFoundViewModel(MvcEvent $e)
    {
        $vars = $e->getResult();
        if ($vars instanceof Response) {
            // Already have a response as the result
            return;
        }

        $response = $e->getResponse();
        if ($response->getStatusCode() != 404) {
            // Only handle 404 responses
            return;
        }

        // Grab our log
        $log = $e->getApplication()->getServiceManager()->get('logger');

        if (!$vars instanceof ViewModel) {
            $model = new ViewModel();
            if (is_string($vars)) {
                $log->err("User got 404 page with message : {$vars} - page {$_SERVER['REQUEST_URI']}");
                $model->setVariable('message', $vars);
            } else {
                $log->err("User got 404 page : Page not found - page {$_SERVER['REQUEST_URI']}");
                $model->setVariable('message', 'Page not found.');
            }
        } else {
            $model = $vars;

            $log->err("User got 404 page : Page not found - page {$_SERVER['REQUEST_URI']}");
            if ($model->getVariable('message') === null) {
                $model->setVariable('message', 'Page not found.');
            }
        }

        $model->setTemplate($this->getNotFoundTemplate());

        // If displaying reasons, inject the reason
        $this->injectNotFoundReason($model);

        // If displaying exceptions, inject
        $this->injectException($model, $e);

        // Inject controller if we're displaying either the reason or the exception
        $this->injectController($model, $e);

        $e->setResult($model);
    }
}

