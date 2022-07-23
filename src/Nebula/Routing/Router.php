<?php

namespace Nebula\Routing;

class Router {
    /**
     * The route list.
     *
     * @var array
     */
    public $routes = [];

    /**
     * Build the application routes.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function buildRoutes()
    {
        if (file_exists(storage_path('framework/cache/routes.json'))) {
            // Get routes directly from JSON file if present
            $this->routes = json_decode(file_get_contents(storage_path('framework/cache/routes.json')), true);
        }

        // Parse routes yaml as array
        if (!file_exists(base_path('routes/web.php'))) {
            throw new \RuntimeException("A routes file has not been found at routes/web.php. Please create one.", 500);
        }

        $routes = include(base_path('routes/web.php'));

        $this->parseRoutes($routes, [], true);
    }

    /**
     * Parses the raw routes array.
     *
     * @param array $routes The array containing the raw routes.
     * @param array $options The options array (prefix, middleware, etc.).
     * @param bool $first Indicates whether it is the starting routes.
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function parseRoutes(array $routes, array $options = [], bool $first = false)
    {
        foreach ($routes as $key => $value) {
            if ($key == 'routes') {
                foreach ($value as $route) {
                    $this->addRoute($route, $options);
                }
            } elseif ($key == 'group') {
                foreach ($value as $group) {
                    $options = $this->formatGroupOptions($group, $options, $first ?? false);
                    $this->parseRoutes($group, $options);
                }
            }
        }
    }

    /**
     * Adds a route to the class array.
     *
     * @param array $route The route data array.
     * @param array $options The options array.
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function addRoute($route, $options)
    {
        // Validate the route
        $this->validateRoute($route);

        // Prepare path by adding prefix if available
        $path = '/' . trim($route['path'], '/');
        if (!empty($options['prefix'])) {
            $path = rtrim(rtrim($options['prefix']) . '/' . trim($route['path'], '/'), '/');
        }

        // Handle dynamic route variables
        preg_match_all("/\{[^\}]*\}/", $path, $matches);
        $routeVariables = [];

        if (!empty($matches[0])) {
            // Build route variables list
            foreach ($matches[0] as $var) {
                $routeVariables[] = preg_replace("/\{|\}/", "", $var);
            }

            // Add regex pattern for route
            $path = preg_replace("@{[^\}]*\}@", "([^/]*)", $path);
        }

        // Add the default controller namespace if one was not provided
        if ($route['controller'][0] != "\\") {
            $route['controller'] = "\\App\\Http\\Controllers\\{$route['controller']}";
        }

        $this->routes[] = [
            'path' => $path,
            'type' => strtolower($route['type']),
            'middleware' => $options['middleware'] ?? [],
            'controller' => $route['controller'],
            'action' => $route['action'],
            'routeVariables' => $routeVariables
        ];
    }

    /**
     * Formats a group's options.
     *
     * @param array $group The group array.
     * @param array $options The options array.
     * @return array
     */
    protected function formatGroupOptions($group, $options, $first = false)
    {
        if (!empty($first)) {
            $options['prefix'] = '';
            $options['middleware'] = [];
        }

        // Add group prefixes
        if (isset($group['prefix'])) {
            if (!empty($group['prefix'])) {
                $options['prefix'] = !empty($options['prefix'])
                    ? '/' . trim($options['prefix'], '/') . '/' . trim($group['prefix'], '/')
                    : '/' . trim($group['prefix'], '/');
            }
        } else {
            unset($options['prefix']);
        }

        // Handle group middleware
        if (isset($group['middleware'])) {
            if (!empty($group['middleware'])) {
                $options['middleware'] = !empty($options['middleware'])
                    ? array_values(array_unique(array_merge($options['middleware'], $group['middleware'])))
                    : $group['middleware'];
            }
        } else {
            unset($options['middleware']);
        }

        return $options;
    }

    /**
     * Validates the route.
     *
     * @param array $route The route data.
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function validateRoute($route)
    {
        if (!isset($route['path'])) {
            throw new \RuntimeException('Route is missing the "path" attribute.', 500);
        }

        if (empty($route['controller'])) {
            throw new \RuntimeException('Route is missing a valid controller.', 500);
        }

        if (empty($route['action'])) {
            throw new \RuntimeException('Route is missing a valid action.', 500);
        }

        if (empty($route['type']) || !in_array(strtolower($route['type']), ['get', 'post', 'put', 'delete'])) {
            throw new \RuntimeException('Route is missing a valid type (get, post, etc.).', 500);
        }
    }
}
