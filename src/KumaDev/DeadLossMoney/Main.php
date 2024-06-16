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
        $this->getLogger()->info("Plugin DeadLossMoney telah diaktifkan.");
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

    public static function getEconomyProvider(): ?EconomyProvider {
        return self::$economyProvider;
    }

    public function getConfig(): Config {
        return $this->config;
    }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $moneyLostPercentage = $this->config->get("money_lost_percentage", 0.05);

        self::$economyProvider->getMoney($player, function (float $money) use ($player, $moneyLostPercentage, $event) {
            $moneyLost = round($money * $moneyLostPercentage, 2);

            self::$economyProvider->takeMoney($player, $moneyLost, function (bool $success) use ($player, $moneyLost) {
                if ($success) {
                    // Store the death message for the player
                    $deathMessage = str_replace("{UANG_MATI}", $moneyLost, $this->config->get("death_message", "§cKamu Kehilangan Uang §e{UANG_MATI} §cSaat Mati."));
                    $this->deathMessages[$player->getName()] = $deathMessage;
                } else {
                    $player->sendMessage(TextFormat::RED . "Terjadi kesalahan saat mengurangi uangmu.");
                    $this->getLogger()->warning("Gagal mengurangi uang pemain.");
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
            $moneyMessage = str_replace("{UANG_KAMU}", number_format($remainingMoney, 2), $this->config->get("money_message", "§aSisa Uang Kamu Sebesar §e{UANG_KAMU}."));
            $player->sendMessage(TextFormat::colorize($moneyMessage));
        });
    }
}
