<?php

namespace Nebula\Auth;

use Nebula\Exceptions\AuthException;
use Nebula\Session\SessionManager;

class AuthManager {
    /**
     * The active instance of the Authenticatable model.
     *
     * @var mixed
     */
    private $modelInstance;

    /**
     * The auth configuration array.
     *
     * @var array
     */
    public $authConfig;

    /**
     * The PDO connection instance.
     *
     * @var \PDO
     */
    public $dbConnection;

    /**
     * Create a new class instance.
     *
     * @param array $authConfig The authentication configuration array.
     * @param \PDO $dbConnection The PDO connection instance.
     * @return void
     */
    public function __construct($authConfig, $dbConnection)
    {
        $this->authConfig = $authConfig;
        $this->dbConection = $dbConnection;
    }

    /**
     * Sets the current authenticatable instance as a static variable.
     *
     * @param mixed
     * @return void
     */
    private function setModelInstance($model)
    {
        $this->modelInstance = $model;
    }

    /**
     * Gets the current authenticatable instance as a static variable.
     *
     * @param mixed
     * @return void
     */
    private function getModelInstance()
    {
        return $this->modelInstance ?? null;
    }

    /**
     * Logs in the authenticatable model.
     *
     * @param mixed $model The model to authenticate.
     * @return mixed
     */
    public function login($model)
    {
        // Check if model is instance matches authenticatable model
        if (get_class($model) != $this->authConfig['model'] || empty($_SERVER['REMOTE_ADDR'])) {
            throw new AuthException("Cannot login using " . get_class($model) . ". Authenticatable model is defined as " . $this->authConfig['model'], 500);
        }

        // Reset the session to prevent fixation
        SessionManager::resetSession();

        $key = $this->authConfig['key'];
        $_SESSION[$key] = $model->$key;
        $_SESSION['isAuth'] = 1;
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['_token'] = bin2hex(random_bytes(32));
        $this->setModelInstance($model);

        return true;
    }

    /**
     * Logs in the authenticatable model using an ID.
     *
     * @param mixed $id The ID of the model to authenticate.
     * @return mixed
     */
    public function loginUsingId($id)
    {
        $className = "\\" . $this->authConfig['model'];
        $modelInstance = $className::find($id);

        // Set the authenticatable model instance on the class
        if (!empty($modelInstance)) {
            // Reset the session to prevent fixation
            SessionManager::resetSession();

            $this->setModelInstance($modelInstance);
            $key = $this->authConfig['key'];
            $_SESSION[$key] = $modelInstance->$key;
            $_SESSION['isAuth'] = 1;
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['_token'] = bin2hex(random_bytes(32));
            $this->setModelInstance($modelInstance);

            return true;
        } else {
            throw new AuthException("Authenticatable model with primary key $id cannot be found.", 500);
        }

        return false;
    }

    /**
     * Gets the current auth model.
     *
     * @return mixed
     */
    public function user()
    {
        if (empty($this->modelInstance)) {
            // Check the session for model key
            // If model key is found, fetch the authenticatable model
            if (isset($_SESSION[$this->authConfig['key']]) && !empty($_SESSION['isAuth'])) {
                $className = "\\" . $this->authConfig['model'];
                $modelInstance = $className::find($_SESSION[$this->authConfig['key']]);

                // Set the authenticatable model instance on the class
                if (!empty($modelInstance)) {
                    $this->setModelInstance($modelInstance);
                }
            }
        }

        return $this->modelInstance;
    }

    /**
     * Determines if a model is logged in.
     *
     * @return bool
     */
    public function check()
    {
        $result = false;

        if (!empty($this->modelInstance) || (isset($_SESSION[$this->authConfig['key']]) && !empty($_SESSION['isAuth']))) {
            $result = true;
        }

        return $result;
    }

    /**
     * Logs an auth model out.
     *
     * @return bool
     */
    public function logout()
    {
        // Reset the model instance
        $this->modelInstance = null;

        // Reset the session to prevent fixation
        SessionManager::resetSession();

        return true;
    }
}
