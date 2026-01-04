# Minecraft Server Properties (by KikiPunk, based on Fritz's plugin)

Edit the Minecraft `server.properties` file directly from the server panel with a user-friendly graphical interface.

## Features

- Organized properties by categories (General, Gameplay, World, Network, Query & RCON, Resource Pack, Security, Advanced, Data Packs)
- Version-aware filtering (shows only properties compatible with your Minecraft version)
- Search bar to quickly find properties
- Tooltips with official Minecraft wiki descriptions
- Raw editor tab for direct file editing

### Version Detection

The plugin uses environment variables to detect the Minecraft version in this order:
1. `MINECRAFT_VERSION`
2. `MC_VERSION`
3. Falls back to showing all properties
