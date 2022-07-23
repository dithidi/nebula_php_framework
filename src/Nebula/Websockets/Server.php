<?php

namespace Nebula\Websockets;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;
use Nebula\Accessors\Auth;
use App\Classes\SubscriptionManager;

class Server implements WampServerInterface {
    /**
     * A lookup of all the channels clients have subscribed to
     */
    protected $subscribedChannels = [];

    public function onSubscribe(ConnectionInterface $conn, $channel)
    {
        $this->handleSession($conn);

        if (!Auth::check() || empty(class_exists('\App\Classes\Websockets\SubscriptionManager')) || empty(\App\Classes\Websockets\SubscriptionManager::authenticate($channel))) {
            $conn->callError($id, $channel, 'Unauthorized.')->close();
        }

        $this->subscribedChannels[$channel->getId()] = $channel;
    }

    /**
     * Broadcasts and event with data on a channel.
     *
     * @param string JSON'ified string we'll receive from ZeroMQ.
     * @return void
     */
    public function onBroadcast($entry)
    {
        $entryData = json_decode($entry, true);

        // Ensure that the user is subscribed to the channel
        if (!array_key_exists($entryData['channel'], $this->subscribedChannels)) {
            return;
        }

        $channel = $this->subscribedChannels[$entryData['channel']];

        // re-send the data to all the clients subscribed to that category
        $channel->broadcast($entryData['data']);
    }

    public function onUnSubscribe(ConnectionInterface $conn, $topic)
    {
        // Handle unsubscribes here
    }

    public function onOpen(ConnectionInterface $conn)
    {
        logError('opened');
        $this->handleSession($conn);

        if (!Auth::check()) {
            $conn->callError('', '', 'Unauthorized.')->close();
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        logError('closed');
    }

    public function onCall(ConnectionInterface $conn, $id, $channel, array $params)
    {
        // In this application if clients send data it's because the user hacked around in console
        $conn->callError($id, $channel, 'You are not allowed to make calls')->close();
    }

    public function onPublish(ConnectionInterface $conn, $channel, $event, array $exclude, array $eligible)
    {
        // In this application if clients send data it's because the user hacked around in console
        $conn->close();
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
    }

    /**
     * Handles session by using provided cookie to do a session lookup.
     *
     * @param \Ratchet\ConnectionInterface $conn The connection interface.
     * @return void
     */
    private function handleSession($conn)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path("framework/sessions"));

            session_start();
        }

        $cookies = $conn->httpRequest->getHeader('Cookie');

        // Loop through cookies to see if nebula-session exists
        foreach ($cookies as $cookie) {
            if (strpos($cookie, 'nebula-session') !== false) {
                $foundSessionCookie = explode('=', $cookie);
                $sessionId = $foundSessionCookie[1] ?? null;
                break;
            }
        }

        if (!empty($sessionId)) {
            try {
                // Set the session save path
                $sessionPath = storage_path("framework/sessions");
                $serializedData = file_get_contents($sessionPath . "/sess_$sessionId");

                // Unserialize the session data
                $sessionData = $this->unserializeSessionData($serializedData);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }

            $clientIp = $conn->httpRequest->getHeader('X-Forwarded-For')[0] ?? null;

            // Ensure that the ip addresses match to prevent session hijacking
            if (empty($clientIp) || $clientIp != ($sessionData['ip'] ?? null)) {
                return false;
            }

            session_decode($serializedData);
        }
    }

    /**
     * Unserializes PHP session data.
     *
     * @param string $sessionData The serialized session data string.
     * @return array
     */
    private function unserializeSessionData($sessionData) {
        $method = ini_get("session.serialize_handler");

        switch ($method) {
            case "php":
                return $this->unserializePhp($sessionData);
                break;
            case "php_binary":
                return $this->unserializePhpbinary($sessionData);
                break;
            default:
                throw new Exception("Unsupported session.serialize_handler: " . $method . ". Supported: php, php_binary");
        }
    }

    /**
     * Unserializes PHP session data using php.
     *
     * @param string $sessionData The serialized session data string.
     * @return array
     */
    private static function unserializePhp($sessionData) {
        $returnData = [];
        $offset = 0;

        while ($offset < strlen($sessionData)) {
            if (!strstr(substr($sessionData, $offset), "|")) {
                throw new Exception("invalid data, remaining: " . substr($sessionData, $offset));
            }
            $pos = strpos($sessionData, "|", $offset);
            $num = $pos - $offset;
            $varname = substr($sessionData, $offset, $num);
            $offset += $num + 1;
            $data = unserialize(substr($sessionData, $offset));
            $returnData[$varname] = $data;
            $offset += strlen(serialize($data));
        }

        return $returnData;
    }

    /**
     * Unserializes PHP session data using php binary.
     *
     * @param string $sessionData The serialized session data string.
     * @return array
     */
    private static function unserializePhpbinary($sessionData) {
        $returnData = [];
        $offset = 0;

        while ($offset < strlen($sessionData)) {
            $num = ord($sessionData[$offset]);
            $offset += 1;
            $varname = substr($sessionData, $offset, $num);
            $offset += $num;
            $data = unserialize(substr($sessionData, $offset));
            $returnData[$varname] = $data;
            $offset += strlen(serialize($data));
        }

        return $returnData;
    }
}
