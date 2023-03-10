<?php

namespace Rushil13579\EasyModeration\Commands;

use DateTime;
use InvalidArgumentException;
use pocketmine\command\{Command, CommandSender};
use pocketmine\plugin\Plugin;
use Rushil13579\EasyModeration\Main;
use Rushil13579\EasyModeration\utils\Expiry;
use pocketmine\Server;

class Mute extends Command {

    /** @var Main */
    private $main;

    public function __construct(Main $main) {
        $this->main = $main;

        parent::__construct('mute', 'Prevents a player from chatting on this server', '/mute <player> <time> [reason...]');
        $this->setPermission('easymoderation.mute');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!$this->testPermission($sender)) {
            $sender->sendMessage(Main::PREFIX . ' §cYou do not have permission to use this command');
            return false;
        }

        if(count($args) < 2) {
            $sender->sendMessage(Main::PREFIX . ' §cUsage: /mute <player> <time> [reason...]');
            return false;
        }

        $player = $this->main->getServer()->getPlayerExact($args[0]);

        if($player != null) {
            $playername = $player->getName();
        } else {
            $playername = $args[0];
        }

        if(count($args) > 2) {
            $reason = implode(' ', array_slice($args, 2));
        } else {
            $reason = 'Muted by administrator';
        }

        $muteList = $this->main->getNameMutes();
        $sendername = $sender->getName();

        if($muteList->isBanned($playername)) {
            $sender->sendMessage(Main::PREFIX . ' §cThis player is already muted');
            return false;
        }

        if($args[1] == 'inf' or $args[1] == 'infinite' or $args[1] == 'permanent' or $args[1] == 'perm' or $args[1] == 'forever') {
            $muteList->addBan($playername, $reason, null, $sendername);

            if($player != null) {
                $msg = "§cYou have been Permanently Muted!\n§cReason: §f{$reason}\n§cMuted By: §f{$sendername}";
                $player->sendMessage($msg);
            }

            $msg = "§f===================\n     §l§cNetwork PermMute\n\n§r§cPlayer: §f{$playername}\n§cReason: §f{$reason}\n§cMuted by: §f{$sendername}§f\n===================";
            Server::getInstance()->broadcastMessage($msg);

            if($this->main->cfg->get('mute-discord-post') == 'enabled') {
                $webhook = $this->main->cfg->get('mute-webhook');

                $msg = "__**NEW PERM MUTE**__\nPlayer Muted: $playername\nMuted By: $sendername\nReason: $reason";
                $this->main->postToDiscord($webhook, $msg);
            }
        } else {
            try {
                $expiry = new Expiry($args[1]);
                $expiryToString = Expiry::expirationTimerToString($expiry->getDate(), new DateTime);

                $muteList->addBan($playername, $reason, $expiry->getDate(), $sendername);

                if($player != null) {
                    $msg = "§cYou have been Temporarily Muted\n§cMute Time: §f{$expiryToString}\nReason §f{$reason}\nMuted By: §f{$sendername}";
                    $player->sendMessage($msg);
                }

                $msg = "§f===================\n     §l§cNetwork TempMute\n\n§r§cPlayer: §f{$playername}\n§cReason: §f{$reason}\n§cMuted by: §f{$sendername}\n§cMute Time: §f{$expiryToString}§f\n===================";
                Server::getInstance()->broadcastMessage($msg);

                if($this->main->cfg->get('mute-discord-post') == 'enabled') {
                    $webhook = $this->main->cfg->get('mute-webhook');

                    $msg = "__**NEW TEMP MUTE**__\nPlayer Muted: $playername\nMuted Time: $expiryToString\nMuted By: $sendername\nReason: $reason";
                    $this->main->postToDiscord($webhook, $msg);
                }
            } catch(InvalidArgumentException $msg) {
                $sender->sendMessage($msg->getMessage());
            }
        }
        return true;
    }

    public function getPlugin(): Plugin {
        return $this->main;
    }
}