<?php

namespace RestApi\Middleware;

use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Utility\Inflector;

class RestApiMiddleware extends ErrorHandlerMiddleware
{

    /**
     * Override ErrorHandlerMiddleware and add custom exception renderer
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param callable $next Callback to invoke the next middleware.
     * @return \Psr\Http\Message\ResponseInterface A response
     */
    public function __invoke($request, $response, $next)
    {
        try {
            $params = (array)$request->getAttribute('params', []);
            if (isset($params['controller'])) {
                $controllerName = $params['controller'];
                $firstChar = substr($controllerName, 0, 1);
                if (strpos($controllerName, '\\') !== false ||
                    strpos($controllerName, '/') !== false ||
                    strpos($controllerName, '.') !== false ||
                    $firstChar === strtolower($firstChar)
                ) {
                    return $next($request, $response);
                }
                $type = 'Controller';
                if (isset($params['prefix']) && $params['prefix']) {
                    $prefix = Inflector::camelize($params['prefix']);
                    $type = 'Controller/' . $prefix;
                }
                $className = App::className($controllerName, $type, 'Controller');
                $controller = ($className) ? new $className() : null;
                if ($controller && is_subclass_of($controller, 'RestApi\Controller\ApiController')) {
                    if (isset($this->renderer)) {
                        $this->renderer = 'RestApi\Error\ApiExceptionRenderer';
                    } else {
                        $this->exceptionRenderer = 'RestApi\Error\ApiExceptionRenderer';
                    }
                    $response = $this->buildResponse($response);
                }
                unset($controller);
            }

            return $next($request, $response);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $response);
        }
    }

    /**
     * Prepares the response object with content type and cors headers.
     *
     * @param $response $event The event object either beforeDispatch or afterDispatch
     *
     * @return $response
     */
    private function buildResponse($response)
    {

        if ('xml' === Configure::read('ApiRequest.responseType')) {
            $response->withType('xml');
        } else {
            $response->withType('json');
        }

        $response = $response->withHeader('Access-Control-Allow-Origin', Configure::read('ApiRequest.cors.origin'));
        $response->withHeader('Access-Control-Allow-Headers', Configure::read('ApiRequest.cors.allowedHeaders'))
            ->withHeader('Access-Control-Allow-Methods', Configure::read('ApiRequest.cors.allowedMethods'))
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', Configure::read('ApiRequest.cors.maxAge'));

        return $response;
    }
}
