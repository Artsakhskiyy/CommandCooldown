<?php

namespace Artsakhskiyy\CommandCooldown;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

    /** @var array<string,array<string,int>> */
    private array $cooldowns = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->checkConfigVersion();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    private function checkConfigVersion(): void {
        $currentVersion = 1;
        $configVersion = (int) $this->getConfig()->get("config-version", 0);

        if ($configVersion < $currentVersion) {
            $this->getLogger()->warning("§l§2CommandCooldown » Your config.yml is outdated! Updating to the latest version...");
            @rename($this->getDataFolder() . "config.yml", $this->getDataFolder() . "config_old.yml");
            $this->saveResource("config.yml", true);
        }
    }

    public function onCommandEvent(CommandEvent $event): void {
        $sender = $event->getSender();

        if (!$sender instanceof Player) {
            return;
        }

        $command = strtolower(explode(" ", $event->getCommand())[0]);

        if ($sender->hasPermission("commandcooldown.bypass")) {
            return;
        }

        $excluded = array_map("strtolower", $this->getConfig()->get("excluded-commands", []));
        if (in_array($command, $excluded, true)) {
            return;
        }

        $cooldownSeconds = (int) $this->getConfig()->get("cooldown-seconds", 5);

        if (isset($this->cooldowns[$sender->getName()][$command])) {
            $lastUse = $this->cooldowns[$sender->getName()][$command];
            $remaining = $cooldownSeconds - (time() - $lastUse);

            if ($remaining > 0) {
                $msg = str_replace("{seconds}", $remaining, $this->getConfig()->get("messages")["cooldown-wait"]);
                $sender->sendMessage($msg);
                $event->cancel();
                return;
            }
        }

        $this->cooldowns[$sender->getName()][$command] = time();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (strtolower($command->getName()) === "setcooldown") {
            if (!$sender->hasPermission("commandcooldown.set")) {
                $sender->sendMessage($this->getConfig()->get("messages")["no-permission"]);
                return true;
            }

            if (count($args) !== 1 || !is_numeric($args[0])) {
                $sender->sendMessage($this->getConfig()->get("messages")["usage"]);
                return true;
            }

            $seconds = (int) $args[0];
            $this->getConfig()->set("cooldown-seconds", $seconds);
            $this->getConfig()->save();
            $this->reloadConfig();

            $msg = str_replace("{seconds}", $seconds, $this->getConfig()->get("messages")["cooldown-updated"]);
            $sender->sendMessage($msg);
            return true;
        }
        return false;
    }
}
