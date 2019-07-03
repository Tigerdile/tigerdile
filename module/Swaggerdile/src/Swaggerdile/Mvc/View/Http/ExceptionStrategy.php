<?php
/*
 * ExceptionStrategy
 *
 * This is an exception handler class that injects our custom
 * logging and error page display.
 *
 * @author sconley
 */

namespace Swaggerdile\Mvc\View\Http;

use Zend\Mvc\MvcEvent;
use Zend\Mvc\View\Http\ExceptionStrategy as ZendExceptionStrategy;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\Response as HttpResponse;
use Zend\Mvc\Application;
use Zend\Stdlib\ResponseInterface as Response;
use Zend\View\Model\ViewModel;

class ExceptionStrategy extends ZendExceptionStrategy
{
    /**
     * Create an exception view model, and set the HTTP status code
     *
     * @todo   dispatch.error does not halt dispatch unless a response is
     *         returned. As such, we likely need to trigger rendering as a low
     *         priority dispatch.error event (or goto a render event) to ensure
     *         rendering occurs, and that munging of view models occurs when
     *         expected.
     * @param  MvcEvent $e
     * @return void
     */
    public function prepareExceptionViewModel(MvcEvent $e)
    {
        // Do nothing if no error in the event
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        // Do nothing if the result is a response object
        $result = $e->getResult();
        if ($result instanceof Response) {
            return;
        }

        $errorUniqueId = uniqid('', true);;

        switch ($error) {
            case Application::ERROR_CONTROLLER_NOT_FOUND:
            case Application::ERROR_CONTROLLER_INVALID:
            case Application::ERROR_ROUTER_NO_MATCH:
                // Specifically not handling these
                // Handled by RouteNotFoundStrategy
                return;

            case Application::ERROR_EXCEPTION:
            default:
                $log = $e->getApplication()->getServiceManager()->get('logger');
                $log->err("{$errorUniqueId} - " . $e->getParam('exception'));

                $model = new ViewModel(array(
                    'message'            => "An error occurred during execution.  This probably won't fix itself, so please email <a href=\"mailto:support@tigerdile.com\">support@tigerdile.com</a> and give them this reference code: {$errorUniqueId}",
                    'exception'          => $e->getParam('exception'),
                    'display_exceptions' => $this->displayExceptions(),
                    'exception_id'       => $errorUniqueId,
                ));

                $model->setTemplate($this->getExceptionTemplate());
                $e->setResult($model);

                $response = $e->getResponse();
                if (!$response) {
                    $response = new HttpResponse();
                    $response->setStatusCode(500);
                    $e->setResponse($response);
                } else {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode === 200) {
                        $response->setStatusCode(500);
                    }
                }

                break;
        }

        // Clear error after handling it
        $e->setError(false);
    }
}

