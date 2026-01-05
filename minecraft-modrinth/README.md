# Minecraft Modrinth (by KikiPunk, based on Boy132's plugin)

Easily download and install Minecraft mods, plugins, and datapacks directly from Modrinth within the server panel.

## Features

- Browse and search Modrinth's extensive library
- Download mods, plugins, and datapacks directly to your server
- Automatic version compatibility checking
- Seamless installation to the correct server directory
- View installed content with delete functionality
- Server-side only filtering (shows only server-compatible content)

## Setup

### Mods

Add `modrinth_mods` to the _features_ of your egg.

**Requirements:**
- `minecraft` tag
- One of the following mod loader tags:

| Loader | Tag |
|--------|-----|
| NeoForge | `neoforge` |
| Forge | `forge` |
| Fabric | `fabric` |
| Quilt | `quilt` |

**Install directory:** `mods/`

---

### Plugins

Add `modrinth_plugins` to the _features_ of your egg.

**Requirements:**
- `minecraft` tag
- One of the following server software tags:

| Server | Tag |
|--------|-----|
| Paper | `paper` |
| Velocity | `velocity` |
| BungeeCord | `bungeecord` |

**Install directory:** `plugins/`

---

### Datapacks

Add `modrinth_datapacks` to the _features_ of your egg.

**Requirements:**
- `minecraft` tag
- `datapacks` tag

**World folder detection:**
The plugin reads `level-name` from `server.properties` to detect the world folder, falling back to `world` if the file doesn't exist or the property is not set.

**Install directory:** `{world_folder}/datapacks/`