<?php

namespace KumaDev\DeadLossMoney;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use DaPigGuy\libPiggyEconomy\libPiggyEconomy;
use DaPigGuy\libPiggyEconomy\providers\EconomyProvider;
use DaPigGuy\libPiggyEconomy\exceptions\MissingProviderDependencyException;

class Main extends PluginBase implements Listener {

    /** @var EconomyProvider|null */
    private static $economyProvider;

    /** @var Config */
    private $config;

    /** @var array */
    private $deathMessages = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Check for required dependencies
        if (!class_exists(libPiggyEconomy::class)) {
            $this->getLogger()->error("[DeadLossMoney] libPiggyEconomy virion not found. Please download DeVirion Now!.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        // Initialize economy library
        libPiggyEconomy::init();
        
        // Load and save configuration file
        $this->saveResource("config.yml");
        $this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $economyConfig = $this->config->get("economy", []);

        // Initialize economy provider
        try {
            self::$economyProvider = libPiggyEconomy::getProvider($economyConfig);
        } catch (MissingProviderDependencyException $e) {
            $this->getLogger()->error("[DeadLossMoney] Dependencies for provider not found: " . $e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
    }

    /**
     * @return EconomyProvider|null
     */
    public static function getEconomyProvider(): ?EconomyProvider {
        return self::$economyProvider;
    }

    public function getConfig(): Config {
        return $this->config;
    }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $moneyLostPercentage = $this->config->get("money_lost_percentage", 0.05);

        self::$economyProvider->getMoney($player, function (float $money) use ($player, $moneyLostPercentage) {
            $moneyLost = round($money * $moneyLostPercentage, 2);

            self::$economyProvider->takeMoney($player, $moneyLost, function (bool $success) use ($player, $moneyLost) {
                if ($success) {
                    // Store the death message for the player
                    $deathMessage = str_replace("{MONEY_LOSSMONEY}", $moneyLost, $this->config->get("death_message", "§cYou Lost Money §e{MONEY_LOSSMONEY} §cWhen Dead."));
                    $this->deathMessages[$player->getName()] = $deathMessage;
                } else {
                    $player->sendMessage(TextFormat::RED . "An error occurred while reducing your money.");
                    $this->getLogger()->warning("Failed to reduce player's money.");
                }
            });
        });
    }

    public function onPlayerRespawn(PlayerRespawnEvent $event): void {
        $player = $event->getPlayer();

        // Send death message if exists
        if (isset($this->deathMessages[$player->getName()])) {
            $player->sendMessage(TextFormat::colorize($this->deathMessages[$player->getName()]));
            unset($this->deathMessages[$player->getName()]); // Clear the message after sending
        }

        // Send remaining money message
        self::$economyProvider->getMoney($player, function (float $remainingMoney) use ($player) {
            $moneyMessage = str_replace("{YOUR_MONEY}", number_format($remainingMoney, 2), $this->config->get("money_message", "§aYour remaining money is §e{YOUR_MONEY}."));
            $player->sendMessage(TextFormat::colorize($moneyMessage));
        });
    }
}
