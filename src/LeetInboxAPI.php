<?php

namespace leetinbox\LeetInboxAPI;

use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;

class LeetInboxAPI {
    //My database information, change to fit your db's needs
    public const $server = 'localhost';
    public const $username = 'root';
    public const $password = '123456';
    public const $messagesTable = 'messages';

    //Create connection to the database
    $conn = new mysqli($server, $username, $password);

    private static $isSetup = false;
    private static $config = false;
    private static $dataDir = false;

    const CONFIG_NPMESSAGE = "newPlayerMessage";

    public static function getMessageCount($player) {
        $sql = "SELECT * FROM $messagesTable WHERE recipient=$player AND read=0";
        $result = $conn->query($sql);
        return $result->num_rows;
    }

    public static function getMessages($player) {
        $sql = "SELECT * FROM $messagesTable WHERE recipient=$player";
        $result = $conn->query($sql);
        $array = array();

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $array[] = $row;
            }
            return $array;
            $sql = "UPDATE $messagesTable SET read=1 WHERE recipient=$player";
            $conn->query($sql);
        }
        else {
            return false;
        }
    }

    public static function addMessage($player, $sender, $message) {
        $time = time()
        $sql = "INSERT INTO $messagesTable (time_sent, sender, recipient, message, read) VALUES ('$time', '$sender', '$player', '$message', 0)";
        if($conn->query($sql) === TRUE) {
            return true;
        }
        else {
            return false;
        }
    }

    public static function clearMessages($player) {
        $sql = "DELETE FROM $messagesTable WHERE recipient=$player";
        if($conn->query($sql) === TRUE) {
            return true;
        }
        else {
            return false;
        }
    }

    public static function countMessagesFromPlayer($fromPlayer, $toPlayer) {
        $mcount = 0;
        $messages = self::getMessages($toPlayer);
        foreach ($messages as $message) {
            if ($message["sender"] == $fromPlayer) {
                $mcount++;
            }
        }
        return $mcount;
    }

    /* ...This is optional...
    public static function sendall($sender, $message) {
    
    }
    */

}
