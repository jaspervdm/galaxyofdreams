# GalaxyOfDreams - Bot for Liquicity Discord server

## Installation
Install with composer

## Usage
Create a configuration file called `config.json`. Example configuration:
```
{
  "token": "ENTER_DISCORD_APP_TOKEN_HERE",
  "guilds": {
    "liquicity": {
      "guild_id": "152543466491084811",
      "channels": {
        "music": {
          "channel_id": "434739172503191562"
        },
        "bot_commands": {
          "channel_id": "434739067905769473"
        }
      }
    }
  },
  "modules": []
}
```

Specific modules can be loaded by adding them to the `modules` array in the configuration. As an example, loading the `LinkOnlyChannel` module for channel #music in the liquicity server, add the following:
```
{
  "name": "LinkOnlyChannel",
  "config": {
    "channels": [
      "liquicity/music"
    ]
  }
}
```
Channel names are in the format `GUILD/CHANNEL` and are converted to their appropriate ID by using the specified `guilds` array.
Optionally, the bot can send a message to a seperate channel (specified by `log_channel`) for every message that it deletes.