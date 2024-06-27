# General
DeadLossMoney is a Pocketmine plug-in that functions to reduce player money when the player dies

## Features
- Reducing your money when you die
- Reducing your money using percentages
- Custom economy
  
## Configuration
```yaml
# DeadLossMoney Configuration By KumaDev

# The message to be displayed when the player dies and respawns
death_message: "§cYou Lost Money §e{MONEY_LOSSMONEY} §cWhen Dead."

# Set the player will lose money when dead
money_lost_percentage: 0.05

# Message the player's remaining money
money_message: "§aYour remaining money is §e{YOUR_MONEY}."

# Choose the economy you use ("economyapi"/"bedrockeconomy")
economy:
  provider: "bedrockeconomy"
```

## Depend
| Authors | Github | Lib |
|---------|--------|-----|
| Cooldogepm | [Cooldogepm](https://github.com/cooldogepm) | [BedrockEconomy](https://github.com/cooldogepm/BedrockEconomy) |
| Mathchat900 | [Mathchat900](https://github.com/mathchat900) | [EconomyAPI](https://github.com/mathchat900/EconomyAPI-PM5) |
