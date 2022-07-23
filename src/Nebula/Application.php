<?php

namespace Nebula;

use Nebula\Auth\AuthManager;
use Nebula\Cache\CacheManager;
use Nebula\Database\{ DatabaseManager, QueryBuilder };
use Nebula\Exceptions\{ ExceptionHandler, Logger };
use Nebula\Http\{ Response, Request, RequestResolver };
use Nebula\Routing\Router;
use Nebula\Session\SessionManager;
use Nebula\Validation\Validator;
use Symfony\Component\Dotenv\Dotenv;
use App\Http\Kernel as HttpKernel;

class Application {
    static $instance;

    /**
     * The application configuration.
     *
     * @var array
     */
    public $config = [];

    /**
     * Global application classes.
     *
     * @var array
     */
    public $classes = [];

    /**
     * Global application paths.
     *
     * @var array
     */
    public $paths = [];

    /**
     * Create a new class instance.
     *
     * @param string $basePath The base path for the application.
     * @param bool $isConsoleApp Indicates whether the app is in console mode.
     * @return void
     */
    public function __construct(string $basePath, $isConsoleApp = false)
    {
        $this->setAppInstance();

        $this->setAppPaths($basePath);

        // Get configuration
        if (file_exists(storage_path('framework/cache/config.json'))) {
            // Get config directly from JSON file if present
            $appConfig = json_decode(file_get_contents(storage_path('framework/cache/config.json')), true);

            if (!defined('CONFIG_CACHE')) {
                define('CONFIG_CACHE', true);
            }
        } else {
            // Initialize env handler
            $dotenv = new Dotenv();
            $dotenv->load(base_path('.env'));

            if (!defined('CONFIG_CACHE')) {
                define('CONFIG_CACHE', false);
            }

            $appConfig = include(base_path('config/config.php'));
        }

        if ($isConsoleApp) {
            // Set runningInConsole flag to true
            $appConfig['runningInConsole'] = true;
        }

        $this->config = $appConfig;

        // Register App class instances
        $this->classes[Logger::class] = new Logger($this->config['logging']);
        $this->classes[ExceptionHandler::class] = new ExceptionHandler($this->classes[Logger::class]);
        $this->classes[Validator::class] = new Validator();

        // Initialize the database connection manager and query builder
        $this->classes[DatabaseManager::class] = new DatabaseManager();
        $this->classes[QueryBuilder::class] = new QueryBuilder($this->classes[DatabaseManager::class]->getPdo());

        // Initialize the Session Manager
        $this->classes[SessionManager::class] = new SessionManager($this->config['session'], $this->classes[DatabaseManager::class]->getPdo());

        // Initialize the Auth Manager
        $this->classes[AuthManager::class] = new AuthManager($this->config['auth'], $this->classes[DatabaseManager::class]->getPdo());

        $this->classes[HttpKernel::class] = new HttpKernel();
        $this->classes[Router::class] = new Router();
        $this->classes[RequestResolver::class] = new RequestResolver();
        $this->classes[Response::class] = new Response();

        // Start the Cache Manager
        $this->classes[CacheManager::class] = new CacheManager($this->config['cache']);

        // Build the routes
        $this->classes[Router::class]->buildRoutes();
    }

    /**
     * Returns the current application instance.
     *
     * @return \Nebula\Application
     */
    public static function getAppInstance()
    {
        return static::$instance;
    }

    /**
     * Sets the current application instance as a static variable.
     *
     * @return void
     */
    protected function setAppInstance()
    {
        static::$instance = $this;
    }

    /**
     * Sets the application paths.
     *
     * @param string $basePath The base application root path.
     * @return void
     */
    protected function setAppPaths($basePath)
    {
        $this->paths['base'] = "$basePath/";
        $this->paths['storage'] = "$basePath/storage/";
        $this->paths['assets'] = "$basePath/resources/";
    }
}
