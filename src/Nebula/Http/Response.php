<?php

namespace Nebula\Http;

use Nebula\Collections\Collection;
use Nebula\Database\Model;
use Nebula\Http\RequestResolver;
use eftec\bladeone\BladeOne;

class Response {
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Set common response headers
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Strict-Transport-Security: max-age=15768000');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Adds a key/value pair to response header.
     *
     * @param string $attribute The header attribute.
     * @param string $value The header value.
     * @return void
     *
     * @throws \RuntimeException
     */
    public function header($attribute, $value)
    {
        header("$attribute: $value");
    }

    /**
     * Returns a view response.
     *
     * @param string $view The view template to use for the response.
     * @param array $data The data for the view.
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function view($view, $data = [])
    {
        // Initialize the Blade compiler
        $views = assets_path('views');
        $cache = storage_path('framework/cache/views');
        $blade = new BladeOne($views, $cache, BladeOne::MODE_DEBUG);

        // Set content type to HTML
        $this->header('Content-Type', 'text/html');

        // Clean the outgoing data
        foreach ($data as &$datum) {
            if (is_object($datum) && !empty($datum) && (get_parent_class($datum) == Model::class || get_class($datum) == Collection::class)) {
                $datum->clean();
            }
        }

        try {
            echo $blade->run($view, $data);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage(), 500, $e);
        }

        return true;
    }

    /**
     * Returns a JSON response.
     *
     * @param array $data The data to return in response.
     * @param array $data The data for the view.
     * @param bool $clean The optional clean parameter.
     * @return bool
     */
    public function json($data, $statusCode = 200, $clean = true)
    {
        // Clean the outgoing data
        if (!empty($clean)) {
            foreach ($data as &$datum) {
                if (is_object($datum) && !empty($datum) && (get_parent_class($datum) == Model::class || get_class($datum) == Collection::class)) {
                    $datum->clean();
                }
            }
        }

        // Set status code and content type
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Returns a redirect response.
     *
     * @param string $path The URL path to redirect.
     * @return \Nebula\Http\Response
     */
    public function redirect($path)
    {
        $path = ltrim($path, '/');
        app()->classes[RequestResolver::class]->setRedirectPath($path);

        return $this;
    }

    /**
     * Returns a redirect back response.
     *
     * @return \Nebula\Http\Response
     */
    public function back()
    {
        $path = ltrim($_SESSION['_previous']['url'], '/');
        app()->classes[RequestResolver::class]->setRedirectPath($path);

        return $this;
    }

    /**
     * Returns a redirect back response.
     *
     * @param array $sessionData The flash session data.
     * @return bool
     */
    public function backWith($sessionData)
    {
        $_SESSION['flash']['to_load'] = $sessionData;

        $path = ltrim($_SESSION['_previous']['url'], '/');
        header('Location: ' . (!empty($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/" . $path);

        return true;
    }

    /**
     * Sets the session data for flash session.
     *
     * @param array|string $sessionData The flash session data.
     * @param mixed $optional The optional second argument.
     * @return \Nebula\Http\Response
     */
    public function with($sessionData, $optional = null)
    {
        if (is_array($sessionData)) {
            $_SESSION['flash']['to_load'] = $sessionData;
        } else {
            $_SESSION['flash']['to_load'][$sessionData] = $optional;
        }

        return $this;
    }

    /**
     * Sets the session data for the old form inputs.
     *
     * @return \Nebula\Http\Response
     */
    public function withInputs()
    {
        $_SESSION['old']['to_load'] = request()->all();

        return $this;
    }
}
