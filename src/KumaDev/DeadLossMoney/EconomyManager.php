<?php

declare(strict_types=1);

namespace KumaDev\DeadLossMoney;

use Closure;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use onebone\economyapi\EconomyAPI;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class EconomyManager {
    /** @var Plugin|null $eco */
    private ?Plugin $eco;
    /** @var PluginBase $plugin */
    private PluginBase $plugin;

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
        $manager = $plugin->getServer()->getPluginManager();

        $economyAPI = $manager->getPlugin("EconomyAPI");
        $bedrockEconomy = $manager->getPlugin("BedrockEconomy");

        if ($economyAPI && $bedrockEconomy) {
            $plugin->getLogger()->error("Both EconomyAPI and BedrockEconomy plugins are present. Disabling plugin.");
            $plugin->getServer()->getPluginManager()->disablePlugin($plugin);
            return;
        }

        $this->eco = $economyAPI ?? $bedrockEconomy ?? null;
    }

    public function getEconomyPlugin(): ?Plugin {
        return $this->eco;
    }

    public function getMoney(Player $player, Closure $callback): void {
        if ($this->eco instanceof EconomyAPI) {
            $money = $this->eco->myMoney($player->getName());
            assert(is_float($money));
            $callback($money);
        } elseif ($this->eco instanceof Plugin) {
            // BedrockEconomyAPI should be checked dynamically to avoid class not found error
            if (class_exists('\cooldogedev\BedrockEconomy\api\BedrockEconomyAPI')) {
                $api = \cooldogedev\BedrockEconomy\api\BedrockEconomyAPI::legacy();
                $api->getPlayerBalance($player->getName(), \cooldogedev\BedrockEconomy\api\legacy\ClosureContext::create(static function(?int $balance) use($callback) : void {
                    $callback($balance ?? 0);
                }));
            }
        }
    }

    public function reduceMoney(Player $player, int $amount, Closure $callback): void {
        if ($this->eco instanceof EconomyAPI) {
            $callback($this->eco->reduceMoney($player->getName(), $amount) === EconomyAPI::RET_SUCCESS);
        } elseif ($this->eco instanceof Plugin) {
            // BedrockEconomyAPI should be checked dynamically to avoid class not found error
            if (class_exists('\cooldogedev\BedrockEconomy\api\BedrockEconomyAPI')) {
                $api = \cooldogedev\BedrockEconomy\api\BedrockEconomyAPI::legacy();
                $api->subtractFromPlayerBalance($player->getName(), (int) ceil($amount), \cooldogedev\BedrockEconomy\api\legacy\ClosureContext::create(static function(bool $success) use($callback) : void {
                    $callback($success);
                }));
            }
        }
    }

    public function addMoney(Player $player, int $amount, Closure $callback): void {
        if ($this->eco instanceof EconomyAPI) {
            $callback($this->eco->addMoney($player->getName(), $amount, EconomyAPI::RET_SUCCESS));
        } elseif ($this->eco instanceof Plugin) {
            // BedrockEconomyAPI should be checked dynamically to avoid class not found error
            if (class_exists('\cooldogedev\BedrockEconomy\api\BedrockEconomyAPI')) {
                $api = \cooldogedev\BedrockEconomy\api\BedrockEconomyAPI::legacy();
                $api->addToPlayerBalance($player->getName(), (int) ceil($amount), \cooldogedev\BedrockEconomy\api\legacy\ClosureContext::create(static function(bool $success) use($callback) : void {
                    $callback($success);
                }));
            }
        }
    }
}
