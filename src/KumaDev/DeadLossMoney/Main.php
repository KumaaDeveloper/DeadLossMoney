<?php

namespace KumaDev\DeadLossMoney;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\ClosureTask;
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
    /** @var array */
    private $moneyLost = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Check for required dependencies
        if (!class_exists(libPiggyEconomy::class)) {
            $this->getLogger()->error("[DeadLossMoney] libPiggyEconomy virion not found. Please download DeVirion Now!");
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

        $this->getEconomyProvider()->getMoney($player, function (float $money) use ($player, $moneyLostPercentage) {
            $moneyLost = round($money * $moneyLostPercentage, 2);
            $this->getEconomyProvider()->takeMoney($player, $moneyLost, function () use ($player, $money, $moneyLost) {
                $moneyRemaining = $money - $moneyLost;
                $message = $this->config->get("money_message", "§aYour remaining money is §e{YOUR_MONEY}.");
                $message = str_replace("{YOUR_MONEY}", number_format($moneyRemaining, 2), $message);
                $this->moneyLost[$player->getName()] = $message;
            });

            $deathMessage = str_replace("{MONEY_LOSSMONEY}", number_format($moneyLost, 2), $this->config->get("death_message", "§cYou Lost Money §e{MONEY_LOSSMONEY} §cWhen Dead."));
            $this->deathMessages[$player->getName()] = $deathMessage;
        });
    }

    public function onPlayerRespawn(PlayerRespawnEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if (isset($this->deathMessages[$playerName])) {
            $deathMessage = TextFormat::colorize($this->deathMessages[$playerName]);
            $player->sendMessage($deathMessage);

            if (isset($this->moneyLost[$playerName])) {
                $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player, $playerName) {
                    $message = TextFormat::colorize($this->moneyLost[$playerName]);
                    $player->sendMessage($message);
                    unset($this->moneyLost[$playerName]);
                }), 4); // 4 ticks is approximately 0.2 seconds
            }

            unset($this->deathMessages[$playerName]);
        }
    }
}
