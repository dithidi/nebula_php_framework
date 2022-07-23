<?php

use Nebula\Application;
use Nebula\Http\{Response, Request};
use Nebula\Collections\Collection;
use Nebula\Validation\Validator;

if (! function_exists('app')) {
    /**
     * Gets access to the current application instance.
     *
     * @return \Nebula\Application
     */
    function app()
    {
        return Application::getAppInstance();
    }
}

if (! function_exists('collect')) {
    /**
     * Converts an array into a Collection instance.
     *
     * @param array
     * @return \Nebula\Support\Collection
     */
    function collect($array = [])
    {
        return new Collection($array);
    }
}

if (! function_exists('config')) {
    /**
     * Gets access to the application config.
     *
     * @param string $query Dot separated accessor for sub config values.
     * @return mixed
     */
    function config($query)
    {
        $result = '';

        $queryExploded = explode('.', $query);
        $config = app()->config;

        $level = $config;
        foreach ($queryExploded as $q) {
            if (isset($level[$q])) {
                $result = $level[$q];
                $level = $level[$q];
            } else {
                $result = null;
                break;
            }
        }

        return $result;
    }
}

if (! function_exists('env')) {
    /**
     * Get access to the environment variables.
     *
     * @return mixed
     */
    function env($key, $default = '')
    {
        if (CONFIG_CACHE) {
            throw new \Exception("Env is not enabled while configuration is cached.", 500);
        }

        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }
}

if (! function_exists('back')) {
    /**
     * Get access to the current response instance's redirect back method.
     *
     * @return bool
     */
    function back()
    {
        return response()->back();
    }
}

if (! function_exists('backWith')) {
    /**
     * Get access to the current response instance's redirect backWith method.
     *
     * @param array $sessionData The data for the flash session.
     * @return bool
     */
    function backWith($sessionData)
    {
        return response()->backWith($sessionData);
    }
}

if (! function_exists('redirect')) {
    /**
     * Get access to the current response instance's redirect method.
     *
     * @return bool
     */
    function redirect($path)
    {
        return response()->redirect($path);
    }
}

if (! function_exists('response')) {
    /**
     * Get access to the current response instance.
     *
     * @return \Nebula\Http\Response
     */
    function response()
    {
        return app()->classes[Response::class];
    }
}

if (! function_exists('request')) {
    /**
     * Get access to the current request instance.
     *
     * @return \Nebula\Http\Request
     */
    function request()
    {
        return app()->classes[Request::class];
    }
}

if (! function_exists('view')) {
    /**
     * Get access to the current response instance's view method.
     *
     * @return bool
     */
    function view($view, $data = [])
    {
        return response()->view($view, $data);
    }
}

/**
 * Defines Application path helpers.
 */

if (! function_exists('assets_path')) {
    /**
     * Gets the assets/resources path.
     *
     * @param $string The optional sub path string.
     * @return mixed
     */
    function assets_path($query = null)
    {
        $path = app()->paths['assets'];

        if (!empty($query)) {
            $path .= $query;
        }

        return $path;
    }
}

if (! function_exists('base_path')) {
    /**
     * Gets the base path.
     *
     * @param $string The optional sub path string.
     * @return mixed
     */
    function base_path($query = null)
    {
        $path = app()->paths['base'];

        if (!empty($query)) {
            $path .= $query;
        }

        return $path;
    }
}

if (! function_exists('storage_path')) {
    /**
     * Gets the storage path.
     *
     * @param $string The optional sub path string.
     * @return mixed
     */
    function storage_path($query = null)
    {
        if (empty(app())) {
            return null;
        }

        $path = app()->paths['storage'];

        if (!empty($query)) {
            $path .= $query;
        }

        return $path;
    }
}

if (! function_exists('carbon')) {
    /**
     * Parses a date into a carbon date.
     *
     * @param string $date The date string.
     * @return mixed
     */
    function carbon($date)
    {
        return \Carbon\Carbon::parse($date);
    }
}

if (! function_exists('csrf')) {
    /**
     * Generates a CSRF input field.
     *
     * @param bool $valueOnly Indicates whether to return the value or input.
     * @return string
     */
    function csrf($valueOnly = false)
    {
        $csrfToken = $_SESSION['_token'] ?? null;

        if (!empty($valueOnly)) {
            return $csrfToken;
        }

        return '<input type="hidden" name="_token" value="' . $csrfToken . '" />';
    }
}

if (! function_exists('logError')) {
    /**
     * Shortcut for logging errors.
     *
     * @param string $message The error message.
     * @return string
     */
    function logError($message)
    {
        \Nebula\Accessors\Log::error($message);
    }
}

if (! function_exists('session')) {
    /**
     * Get access to the session.
     *
     * @return bool
     */
    function session()
    {
        return $_SESSION;
    }
}

if (! function_exists('old')) {
    /**
     * Get access to the old form session.
     *
     * @param string $attribute The form attribute to fetch.
     * @return bool
     */
    function old($attribute = null)
    {
        return $_SESSION['old']['loaded'][$attribute] ?? null;
    }
}

if (! function_exists('validate')) {
    /**
     * Performs a validation of data.
     *
     * @param array $data The data for validation.
     * @param array $rules The validation rules.
     * @param bool $apiResponse Indicates whether the response should be an API response.
     * @return bool
     */
    function validate($data, $rules, $apiResponse = false)
    {
        return app()->classes[Validator::class]->validate($data, $rules, $apiResponse);
    }
}
