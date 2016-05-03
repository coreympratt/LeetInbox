<?php
namespace leetinbox\LeetInbox;

use leetinbox\LeetInboxAPI;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;

class LeetInbox extends PluginBase implements Listener {

    const CONFIG_MAXMESSAGE = 10;
    const CONFIG_SIMILARLIM = 0.45; //Adjustments may be made
    //const CONFIG_NOTIFY = "notifyOnNew"; To be done

    
    protected $messages = [];

  
    public function onLoad() {
        
    }

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->reloadConfig();

        $mailCommand = $this->getCommand("mail");
        $mailCommand->setAliases(array($this->getMessage("commands.names.mail")));
        $mailCommand->setDescription($this->getMessage("commands.description"));
        $mailCommand->setUsage($this->getMainCommandUsage());

    }

    public function onDisable() {
        
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        switch ($command->getName()) {
            case "mail":
            case $this->getMessage("commands.names.mail"):
                switch (strtolower(array_shift($args))) {
                    case "read":
                    case $this->getMessage("commands.names.read"):
                        $messages = LeetInboxAPI::getMessages($this->getUserName($sender));
                        $sender->sendMessage("[LeetInbox] " . sprintf($this->getMessage("messages.count"), count($messages)) . ".");
                        foreach ($messages as $message) {
                            $sender->sendMessage("    " . TextFormat::GREEN.  $message["sender"] . TextFormat::WHITE . ": " . $message["message"]);
                        }
                        break;
                    case "clear":
                    case $this->getMessage("commands.names.clear"):
                        LeetInboxAPI::clearMessages($this->getUserName($sender));
                        $sender->sendMessage($this->getMessage("messages.cleared"));
                        break;
                    case "send":
                    case $this->getMessage("commands.names.send"):
                        $senderName = $this->getUserName($sender);
                        $recipiant = strtolower(array_shift($args));
                        $message = implode(" ", $args);

                        if ($recipiant != NULL && $message != NULL) {

                            if ($this->isMessageSimilar($senderName, $recipiant, $message)) {
                                $sender->sendMessage($this->getMessage("messages.similar"));
                            } 
                            else {
                                $msgCount = LeetInboxAPI::countMessagesFromPlayer($senderName, $recipiant);
                                $msgCountMax = $this->getConfig()->get(LeetInbox::CONFIG_MAXMESSAGE);
                                if ($msgCount > $msgCountMax) {
                                    $sender->sendMessage(sprintf($this->getMessage("messages.too_many"), $recipiant) . " (" . ($msgCount - 1) . "/$msgCountMax)");
                                } 
                                else {
                                    LeetInboxAPI::addMessage($recipiant, $senderName, $message);
                                    $sender->sendMessage($this->getMessage("messages.sent") . " ($msgCount/$msgCountMax)");
                                    //$this->sendNotification($recipiant, $senderName);
                                }
                            } 
                        } 
                        else {
                            $sender->sendMessage($this->getSendCommandUsage());
                        }

                        break;
                    /*
                    case "sendall":
                    case $this->getMessage("commands.names.sendall"):
                        if ($sender->hasPermission("leetinbox.leetinbox.command.mail.all")) {
                            $senderName = $this->getUserName($sender);
                            $message = implode(" ", $args);
                            LeetInboxAPI::sendall($senderName, $message);
                            $sender->sendMessage($this->getMessage("messages.sent"));
                            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                                $this->sendNotification($player->getName(), $senderName);
                            }
                        } else {
                            $sender->sendMessage($this->getMessage("messages.not_allowed"));
                        }
                        break;
                    */
                    default:
                        $sender->sendMessage($this->getMessage("commands.usage.usage") . ": " . $this->getMainCommandUsage());
                }
                return true;
            default:
                return false;
        }
    }

    /* To be done
    public function sendNotification($player, $sender) {
        if ($this->getConfig()->get(LeetInbox::CONFIG_NOTIFY) &&
                ($pPlayer = $this->getServer()->getPlayerExact($player)) !== null &&
                $pPlayer->isOnline()) {
            $pPlayer->sendMessage(sprintf($this->getMessage("messages.new_message"), $sender));
        }
    }
    */

    public function isMessageSimilar($fromPlayer, $toPlayer, $newmessage) {

        $limit = $this->getConfig()->get(LeetInbox::CONFIG_SIMILARLIM);

        if ($limit == 0) {
            return false;
        }

        $messages = LeetInboxAPI::getMessages($toPlayer);
        foreach ($messages as $message) {
            if ($message["sender"] == $fromPlayer) {

                if ($this->compareStrings($message["message"], $newmessage) <= (1 - $limit)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function compareStrings($str1, $str2) {
        $str1m = metaphone($str1);
        $str2m = metaphone($str2);
        $dist = levenshtein($str1m, $str2m);

        return $dist / max(strlen($str1m), strlen($str2m));
    }

    public function getUserName($issuer) {
        if ($issuer instanceof \pocketmine\Player) {
            return $issuer->getName();
        } else {
            return "A Server";
        }
    }
    //Message updates.....
    public function onRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();

        $messagecount = LeetInboxAPI::getMessageCount($player);

        $player->sendMessage(sprintf($this->getMessage("messages.count"), $messagecount) . ".  /"
                . $this->getMessage("commands.names.mail") . " "
                . $this->getMessage("commands.names.read"));
    }

    public function getMessage($key) {
        return isset($this->messages[$key]) ? $this->messages[$key] : $key;
    }

    public function getMainCommandUsage() {
        return "/" . $this->getMessage("commands.names.mail")
                . " < " . $this->getMessage("commands.names.read") . " | "
                . $this->getMessage("commands.names.clear") . " | "
                . $this->getMessage("commands.names.send") . " | "
                . $this->getMessage("commands.names.sendall") . " >";
    }

    public function getSendCommandUsage() {
        return $this->getMessage("commands.usage.usage") . ": /"
                . $this->getMessage("commands.names.mail") . " "
                . $this->getMessage("commands.names.send") . " < "
                . $this->getMessage("commands.usage.player") . " > < "
                . $this->getMessage("commands.usage.message") . " >";
    }

    private function parseMessages(array $messages) {
        $result = [];
        foreach ($messages as $key => $value) {
            if (is_array($value)) {
                foreach ($this->parseMessages($value) as $k => $v) {
                    $result[$key . "." . $k] = $v;
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

}
