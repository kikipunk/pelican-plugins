# Egg Library

A Pelican Panel plugin that allows you to browse and install eggs directly from the [pelican-eggs](https://github.com/pelican-eggs) GitHub organization.

## Features

- **Browse Library Button**: Adds a globe icon button on *Eggs* page header
- **Dynamic Category Discovery**: Automatically loads all categories from the official pelican-eggs catalog
- **Search & Filter**: Search eggs by name and filter by category
- **One-Click Import**: Import eggs directly into your Pelican Panel database
- **Duplicate Detection**: Detects existing eggs by name and UUID, offers overwrite or rename options
- **Smart Caching**: Caches catalog data to minimize HTTP requests

## Categories

Categories are loaded from the official [pelican-eggs catalog](https://github.com/pelican-eggs):

- Chatbots (Discord, Twitch, etc.)
- Database (MongoDB, Redis, PostgreSQL, MariaDB)
- Games Standalone
- Games SteamCMD
- Generic (Language runtimes)
- Minecraft
- Monitoring
- Etc.
