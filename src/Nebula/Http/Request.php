<?php

namespace Nebula\Http;

class Request {
    /**
     * The inputs array.
     *
     * @var array
     */
    protected $inputs = [];

    /**
     * Capture the incoming data and generate a request instance.
     *
     * Automatically performs the following cleanup items: trims all incoming strings,
     * automatically converts empty strings to null, sanitizes all data with
     * htmlspecialchars.
     *
     * @return \Nebula\Http\Request
     */
    public static function capture()
    {
        $request = new self;

        // Ensure that the post size is not too large
        $maxSize = $request->getPostMaxSize();
        $postSize = $_SERVER['CONTENT_LENGTH'] ?? 0;
        if ($maxSize > 0 && $postSize > $maxSize) {
            throw new \Exception("Maximum content length has been exceeded.", 500);
        }

        foreach ($_GET as $key => $value) {
            $value = is_string($value) && $value === '' ? null : $value;
            $request->inputs[$key] = is_string($value) ? htmlspecialchars(trim($value), \ENT_NOQUOTES, 'UTF-8') : $value;
        }

        foreach ($_POST as $key => $value) {
            $value = is_string($value) && $value === '' ? null : $value;
            $request->inputs[$key] = is_string($value) ? htmlspecialchars(trim($value), \ENT_NOQUOTES, 'UTF-8') : $value;
        }

        foreach ($_FILES as $key => $value) {
            $value = is_string($value) && $value === '' ? null : $value;
            $request->inputs[$key] = is_string($value) ? htmlspecialchars(trim($value), \ENT_NOQUOTES, 'UTF-8') : $value;
        }

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
            $json = file_get_contents('php://input');
            $jsonArray = json_decode($json, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                foreach ($jsonArray as $key => $value) {
                    $value = is_string($value) && $value === '' ? null : $value;
                    $request->inputs[$key] = is_string($value) ? htmlspecialchars(trim($value), \ENT_NOQUOTES, 'UTF-8') : $value;
                }
            }
        }

        return $request;
    }

    /**
     * Dynamically proxy attribute access to the variable.
     *
     * @param string $attribute The attribute key for input access.
     * @return mixed
     */
    public function __get($attribute)
    {
        return $this->inputs[$attribute] ?? null;
    }

    /**
     * Dynamically proxy attribute isset checks to the variable.
     *
     * @param string $attribute The attribute key for input check.
     * @return mixed
     */
    public function __isset($attribute){
        $result = array_key_exists($attribute, $this->inputs);

        if (empty($result)) {
            $result = isset($this->$attribute);
        }

        return $result;
    }

    /**
     * Returns an array of all request input data.
     *
     * @return array
     */
    public function all()
    {
        return $this->inputs;
    }

    /**
     * Returns the request URI.
     *
     * @return string
     */
    public function getUri()
    {
        return $_SERVER['REQUEST_URI'] ?? null;
    }

    /**
     * Returns the request referrer.
     *
     * @return string
     */
    public function getReferrer()
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    /**
     * Determine the server 'post_max_size' as bytes.
     *
     * @return int
     */
    protected function getPostMaxSize()
    {
        if (is_numeric($postMaxSize = ini_get('post_max_size'))) {
            return (int) $postMaxSize;
        }

        $metric = strtoupper(substr($postMaxSize, -1));
        $postMaxSize = (int) $postMaxSize;

        switch ($metric) {
            case 'K':
                return $postMaxSize * 1024;
            case 'M':
                return $postMaxSize * 1048576;
            case 'G':
                return $postMaxSize * 1073741824;
            default:
                return $postMaxSize;
        }
    }
}
