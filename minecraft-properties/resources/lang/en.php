<?php

return [
    // Page
    'heading' => 'Minecraft Server Properties',
    'action_save' => 'Save',
    'search_placeholder' => 'Search properties...',

    // Tabs
    'tab_properties' => 'Properties',
    'tab_raw' => 'Raw Editor',

    // Sections
    'section_general' => 'General',
    'section_gameplay' => 'Gameplay',
    'section_world' => 'World',
    'section_network' => 'Network',
    'section_query_rcon' => 'Query & RCON',
    'section_resource_pack' => 'Resource Pack',
    'section_security' => 'Security',
    'section_advanced' => 'Advanced',
    'section_datapacks' => 'Data Packs',

    // Raw editor
    'raw_label' => 'Raw server.properties',
    'raw_helper' => 'Edit the raw server.properties file directly',

    // General tooltips
    'motd_tooltip' => 'Message of the Day shown in the server list',
    'max_players_tooltip' => 'Maximum players that can join the server',
    'online_mode_tooltip' => 'Verify players with Minecraft account database',
    'enable_status_tooltip' => 'Show server in the server list',
    'whitelist_tooltip' => 'Only whitelisted players can join',
    'enforce_whitelist_tooltip' => 'Kick non-whitelisted players on reload',

    // Gameplay tooltips
    'difficulty_tooltip' => 'Server difficulty level',
    'difficulty_peaceful' => 'Peaceful',
    'difficulty_easy' => 'Easy',
    'difficulty_normal' => 'Normal',
    'difficulty_hard' => 'Hard',
    'gamemode_tooltip' => 'Default game mode for new players',
    'gamemode_survival' => 'Survival',
    'gamemode_creative' => 'Creative',
    'gamemode_adventure' => 'Adventure',
    'gamemode_spectator' => 'Spectator',
    'force_gamemode_tooltip' => 'Force players into default gamemode on join',
    'hardcore_tooltip' => 'Permanent death, difficulty locked to Hard',
    'pvp_tooltip' => 'Allow player versus player combat',
    'spawn_monsters_tooltip' => 'Enable monster spawning',
    'spawn_animals_tooltip' => 'Enable animal spawning',
    'spawn_npcs_tooltip' => 'Enable villager spawning',
    'allow_flight_tooltip' => 'Allow flight in survival mode',

    // World tooltips
    'level_name_tooltip' => 'World folder name',
    'level_seed_tooltip' => 'Seed for world generation',
    'level_type_tooltip' => 'World type (normal, flat, etc.)',
    'generate_structures_tooltip' => 'Generate villages, temples, etc.',
    'generator_settings_tooltip' => 'Custom generation settings (JSON)',
    'spawn_protection_tooltip' => 'Radius of spawn protection in blocks',
    'max_world_size_tooltip' => 'Maximum world border size in blocks',
    'view_distance_tooltip' => 'Chunks sent to players (2-32)',
    'simulation_distance_tooltip' => 'Distance for entity updates (2-32)',

    // Network tooltips
    'server_ip_tooltip' => 'IP address to bind to (leave blank for all)',
    'server_port_tooltip' => 'Port the server listens on',
    'network_compression_threshold_tooltip' => 'Packet size for compression (-1 to disable)',
    'rate_limit_tooltip' => 'Max packets per second (0 = disabled)',
    'prevent_proxy_connections_tooltip' => 'Block connections through proxies/VPNs',

    // Query & RCON tooltips
    'enable_query_tooltip' => 'Allow GameSpy4 protocol queries',
    'query_port_tooltip' => 'Port for query protocol',
    'enable_rcon_tooltip' => 'Enable remote console access',
    'rcon_port_tooltip' => 'Port for RCON connections',
    'rcon_password_tooltip' => 'Password for RCON access',
    'broadcast_console_to_ops_tooltip' => 'Send console output to online operators',
    'broadcast_rcon_to_ops_tooltip' => 'Send RCON output to online operators',

    // Resource Pack tooltips
    'resource_pack_tooltip' => 'URL to optional resource pack',
    'resource_pack_sha1_tooltip' => 'SHA1 hash for pack verification',
    'resource_pack_prompt_tooltip' => 'Custom message when prompting for pack',
    'resource_pack_id_tooltip' => 'UUID for the resource pack',
    'require_resource_pack_tooltip' => 'Disconnect players who decline the pack',

    // Security tooltips
    'enforce_secure_profile_tooltip' => 'Require Mojang-signed public key',
    'log_ips_tooltip' => 'Include player IPs in server logs',
    'text_filtering_config_tooltip' => 'Text filtering configuration',
    'hide_online_players_tooltip' => 'Hide player list from status requests',

    // Advanced tooltips
    'max_tick_time_tooltip' => 'Max ms per tick before watchdog shutdown (-1 to disable)',
    'op_permission_level_tooltip' => 'Default permission level for operators (1-4)',
    'function_permission_level_tooltip' => 'Permission level for function commands',
    'entity_broadcast_range_percentage_tooltip' => 'Entity render distance percentage (10-1000)',
    'max_chained_neighbor_updates_tooltip' => 'Limit consecutive neighbor updates (-1 = no limit)',
    'sync_chunk_writes_tooltip' => 'Enable synchronous chunk writes',
    'use_native_transport_tooltip' => 'Linux-specific network optimization',
    'enable_jmx_monitoring_tooltip' => 'Expose tick times via JMX',
    'enable_command_block_tooltip' => 'Enable command blocks',
    'allow_nether_tooltip' => 'Allow travel to the Nether',
    'accepts_transfers_tooltip' => 'Accept player transfers from other servers',
    'player_idle_timeout_tooltip' => 'Minutes before idle kick (0 = disabled)',
    'pause_when_empty_seconds_tooltip' => 'Seconds to pause when server is empty',
    'region_file_compression_tooltip' => 'Compression algorithm for region files',
    'region_file_compression_deflate' => 'Deflate',
    'region_file_compression_lz4' => 'LZ4',
    'region_file_compression_none' => 'None',
    'bug_report_link_tooltip' => 'URL for bug reports',
    'debug_tooltip' => 'Enable debug mode',

    // Data Packs tooltips
    'initial_enabled_packs_tooltip' => 'Data packs to enable on world creation',
    'initial_disabled_packs_tooltip' => 'Data packs to disable on world creation',
];
