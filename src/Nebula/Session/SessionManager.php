<?php

namespace Nebula\Session;

use SessionHandlerInterface;

class SessionManager {
    /**
     * The session configuration array.
     *
     * @var array
     */
    protected $sessionConfig;

    /**
     * The PDO connection instance.
     *
     * @var \PDO
     */
    protected $dbConnection;

    /**
     * Create a new class instance.
     *
     * @param array $sessionConfig The session configuration array.
     * @param \PDO $dbConnection The PDO connection instance.
     * @return void
     */
    public function __construct($sessionConfig, $dbConnection)
    {
        $this->sessionConfig = $sessionConfig;
        $this->dbConnection = $dbConnection;

        if (empty(app()->config['runningInConsole'])) {
            // Set the session save path
            $this->sessionPath = storage_path("framework/sessions");
            session_save_path($this->sessionPath);

            // Disable session garbage collection
            ini_set('session.gc_probability', 0);

            session_set_cookie_params([
                'lifetime' => $this->sessionConfig['session_lifetime'] ?? 3600,
                'secure' => !empty($_SERVER['HTTPS']) ? true : false,
                'httponly' => true
            ]);
            session_name($this->sessionConfig['name']);

            session_start();
        } else {
            $_SESSION = [];
        }

        // Update the timestampe and previous URL for posts
        $_SESSION['timestamp'] = time();

        // Handle the flash session data
        if (isset($_SESSION['flash']['to_load'])) {
            $_SESSION['flash']['loaded'] = $_SESSION['flash']['to_load'];
            $_SESSION['flash']['to_load'] = [];
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        // Handle the previous URL
        // Ignore asset fetches
        if (strpos($requestUri, '/storage') === false) {
            $forPost = $_SESSION['_previous']['url_to_load'] ?? $requestUri;
            if (isset($_SESSION['_previous']['url_to_load']) && !empty($_SESSION['_previous']['url_to_load'])) {
                $_SESSION['_previous']['url'] = $_SESSION['_previous']['url_to_load'];
                $_SESSION['_previous']['url_to_load'] = null;
            }

            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
                $_SESSION['_previous']['url_to_load'] = $forPost;
            } else {
                $_SESSION['_previous']['url_to_load'] = $requestUri;
            }
        }
    }

    /**
     * Resets the session and regenerates a fresh ID.
     *
     * @return void
     */
    public static function resetSession()
    {
        // Clear the session data
        $_SESSION = [];

        // Regenerate the session
        session_regenerate_id(true);

        // Update the timestampe and previous URL for posts
        $_SESSION['timestamp'] = time();
        $_SESSION['_previous'] = [
            'url' => $_SERVER['HTTP_REFERRER'] ?? '/'
        ];
    }
}
