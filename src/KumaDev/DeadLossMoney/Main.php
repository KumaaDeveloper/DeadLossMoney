<?php

namespace KumaDev\DeadLossMoney;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase implements Listener {

    /** @var EconomyManager|null */
    private static $economyManager;

    /** @var Config */
    private $config;

    /** @var array */
    private $moneyLost = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Load and save configuration file
        $this->saveResource("config.yml");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);

        // Initialize economy manager
        self::$economyManager = new EconomyManager($this);

        // Check if any economy plugin is installed
        if (self::$economyManager->getEconomyPlugin() === null) {
            $this->getLogger()->error("No supported economy plugin found. Disabling plugin.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
    }

    /**
     * @return EconomyManager|null
     */
    public static function getEconomyManager(): ?EconomyManager {
        return self::$economyManager;
    }

    public function getConfig(): Config {
        return $this->config;
    }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $moneyLostPercentage = $this->config->get("money_lost_percentage", 0.05);

        self::$economyManager->getMoney($player, function (float $money) use ($player, $moneyLostPercentage) {
            $moneyLost = round($money * $moneyLostPercentage, 2);
            self::$economyManager->reduceMoney($player, $moneyLost, function (bool $success) use ($player, $moneyLost) {
                if ($success) {
                    $this->moneyLost[$player->getName()] = $moneyLost;
                }
            });
        });
    }

    public function onPlayerRespawn(PlayerRespawnEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if (isset($this->moneyLost[$playerName])) {
            // Add a delay before sending the death message
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $playerName): void {
                if (isset($this->moneyLost[$playerName])) {
                    $deathMessage = str_replace("{MONEY_LOSSMONEY}", number_format($this->moneyLost[$playerName], 2), $this->config->get("death_message", "§cYou Lost Money §e{MONEY_LOSSMONEY} §cWhen Dead."));
                    $player->sendMessage(TextFormat::colorize($deathMessage));
                }
            }), 5); // Delay for 1 second (20 ticks)

            // Add a delay before sending the remaining money message
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $playerName): void {
                self::$economyManager->getMoney($player, function (float $newBalance) use ($player, $playerName): void {
                    if (isset($this->moneyLost[$playerName])) {
                        $message = $this->config->get("money_message", "§aYour remaining money is §e{YOUR_MONEY}.");
                        $message = str_replace("{YOUR_MONEY}", number_format($newBalance, 2), $message);
                        $player->sendMessage(TextFormat::colorize($message));
                        unset($this->moneyLost[$playerName]);
                    }
                });
            }), 6); // Delay for 1.1 second (22 ticks)
        }
    }
}
