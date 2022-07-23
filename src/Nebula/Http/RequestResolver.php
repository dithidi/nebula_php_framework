<?php

namespace Nebula\Http;

use Nebula\Accessors\Auth;
use Nebula\Exceptions\PageNotFoundException;
use Nebula\Http\Request;
use Nebula\Routing\Router;
use App\Http\Kernel as HttpKernel;

class RequestResolver {
    /**
     * Indicates the redirect path if it exists.
     *
     * @var string
     */
    protected $redirectPath = null;

    /**
     * Resolves the current request and returns the results.
     *
     * @param \Nebula\Http\Request $request The request class instance.
     * @return mixed
     *
     * @throws \Nebula\Exceptions\PageNotFoundException
     * @throws \RuntimeException
     */
    public function resolveRequest(Request $request)
    {
        // Add request to the application
        app()->classes[Request::class] = $request;

        // Find route entry
        $route = null;
        $baseRequestPath = parse_url($request->getUri(), PHP_URL_PATH);

        foreach (app()->classes[Router::class]->routes as $appRoute) {
            if (strpos($appRoute['path'], '**') !== false) {
                $explodedPath = explode('/', $baseRequestPath);

                // Remove empty values
                $explodedPath = array_values(array_filter($explodedPath, function ($attr) { return !empty($attr); }));
                $firstAttribute = $explodedPath[0] ?? null;

                // Handle catch-all routes
                if (!empty($firstAttribute) && substr($appRoute['path'], 0, strlen($firstAttribute) + 1) == "/$firstAttribute" && strtolower($appRoute['type']) == strtolower($_SERVER['REQUEST_METHOD'])) {
                    $route = $appRoute;
                    break;
                }
            } else {
                if (preg_match("@^{$appRoute['path']}$@", $baseRequestPath, $matches) && strtolower($appRoute['type']) == strtolower($_SERVER['REQUEST_METHOD'])) {
                    $route = $appRoute;
                    break;
                }
            }
        }

        $this->validateRouteData($route);

        // Handle the old form input session data
        if (isset($_SESSION['old']['to_load'])) {
            $_SESSION['old']['loaded'] = $_SESSION['old']['to_load'];
            $_SESSION['old']['to_load'] = [];
        }

        // Handle global middleware processing
        foreach (app()->classes[HttpKernel::class]->middleware as $middleware) {
            $middlewareInstance = new $middleware();
            $results = $middlewareInstance->handle($request);

            // If results is anything other than the request, immediately return
            if (get_class($results) == Request::class) {
                $request = $results;
            } else {
                return $results;
            }
        }

        // Handle route middleware processing
        foreach ($route['middleware'] as $middleware) {
            $middlewareClassName = app()->classes[HttpKernel::class]->routeMiddleware[$middleware];
            $middlewareInstance = new $middlewareClassName();
            $results = $middlewareInstance->handle($request);

            // If results is anything other than the request, immediately return
            if (!is_null($results) && get_class($results) == Request::class) {
                $request = $results;
            } else {
                return $results;
            }
        }

        // Get list of requested methods arguments
        $reflectionMethod = new \ReflectionMethod($route['controller'], $route['action']);
        $methodArguments = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $methodArguments[] = $param->name;
        }

        // Build list of passable method data
        $methodParams = [];
        $i = 1;
        foreach ($methodArguments as $argument) {
            if ($argument == 'request') {
                $methodParams[] = $request;
            } elseif (in_array($argument, $route['routeVariables'])) {
                $methodParams[] = $matches[$i] ?? null;
                $i++;
            }
        }

        // Instatiate new route requested class and call method
        $routeClass = new $route['controller'];

        // Generate fresh CSRF token
        if (empty($_SESSION['_token']) || (!Auth::check())) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        $endpointResults = $routeClass->{$route['action']}(...$methodParams);

        if (!is_null($this->redirectPath)) {
            header('Location: ' . (!empty($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/" . $this->redirectPath);
            exit;
        }

        return $endpointResults;
    }

    /**
     * Sets the redirect path.
     *
     * @param string $redirectPath The redirect path.
     * @return string
     */
    public function setRedirectPath($redirectPath)
    {
        $this->redirectPath = $redirectPath;
    }

    /**
     * Validates the route for existing class/method combination.
     *
     * @param array $route The route data.
     * @return void
     *
     * @throws \RuntimeException
     * @throws \Nebula\Exceptions\PageNotFoundException;
     */
    protected function validateRouteData($route)
    {
        if (empty($route)) {
            throw new PageNotFoundException("Page not found", 404);
        }

        // Ensure the class and method exists
        if (!class_exists($route['controller'])) {
            throw new \RuntimeException("The class {$route['controller']} does not exist.", 500);
        }

        if (!method_exists($route['controller'], $route['action'])) {
            throw new \RuntimeException("The class {$route['controller']} does not have the requested method: {$route['action']}.", 500);
        }
    }
}
