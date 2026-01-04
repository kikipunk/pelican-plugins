<?php

namespace Kikipunk\MinecraftProperties\Filament\Server\Pages;

use Filament\Actions\Action;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use App\Filament\Server\Pages\ServerFormPage;
use Filament\Notifications\Notification;

final class ServerProperties extends ServerFormPage
{
    protected static ?string $navigationLabel = 'Minecraft Properties';
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-file-description';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = '';
    protected string $view = 'minecraft-properties::filament.server-properties';

    public static function canAccess(): bool
    {
        /** @var Server|null $server */
        $server = Filament::getTenant();
        if (! $server instanceof Server) {
            return false;
        }
        try {
            $repo = app(DaemonFileRepository::class)->setServer($server);
            $repo->getContent('server.properties');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public $motd;
    public $max_players;
    public $online_mode;
    public $pvp;
    public $difficulty;
    public $gamemode;
    public $view_distance;
    public $spawn_protection;
    public $accepts_transfers;
    public $broadcast_console_to_ops;
    public $broadcast_rcon_to_ops;
    public $debug;
    public $op_permission_level;
    public $simulation_distance;
    public $sync_chunk_writes;
    public $whitelist;
    public $allow_nether;
    public $enable_command_block;
    public $enable_query;
    public $enable_rcon;
    public $force_gamemode;
    public $hardcore;
    public $level_name;
    public $level_seed;
    public $level_type;
    public $max_tick_time;
    public $network_compression_threshold;
    public $rcon_password;
    public $server_port;
    public $spawn_monsters;
    public $query_port;
    public $enable_jmx_monitoring;
    public $enable_status;
    public $enforce_secure_profile;
    public $enforce_whitelist;
    public $entity_broadcast_range_percentage;
    public $function_permission_level;
    public $generate_structures;
    public $generator_settings;
    public $hide_online_players;
    public $initial_disabled_packs;
    public $initial_enabled_packs;
    public $log_ips;
    public $max_chained_neighbor_updates;
    public $max_world_size;
    public $player_idle_timeout;
    public $prevent_proxy_connections;
    public $rate_limit;
    public $rcon_port;
    public $resource_pack;
    public $resource_pack_id;
    public $resource_pack_prompt;
    public $resource_pack_sha1;
    public $require_resource_pack;
    public $server_ip;
    public $spawn_animals;
    public $spawn_npcs;
    public $text_filtering_config;
    public $use_native_transport;
    public $bug_report_link;
    public $pause_when_empty_seconds;
    public $region_file_compression;

    public string $search = '';

    /** @var array<string,mixed> */
    private array $originalData = [];
    private string $originalRaw = '';
    private array $availableProperties = [];
    private array $originalProps = [];

    // Category field constants - reorganized for better UX
    private const GENERAL_FIELDS = ['motd', 'max_players', 'online_mode', 'enable_status', 'whitelist', 'enforce_whitelist'];
    private const GAMEPLAY_FIELDS = ['difficulty', 'gamemode', 'force_gamemode', 'hardcore', 'pvp', 'spawn_monsters', 'spawn_animals', 'spawn_npcs', 'allow_flight'];
    private const WORLD_FIELDS = ['level_name', 'level_seed', 'level_type', 'generate_structures', 'generator_settings', 'spawn_protection', 'max_world_size', 'view_distance', 'simulation_distance'];
    private const NETWORK_FIELDS = ['server_ip', 'server_port', 'network_compression_threshold', 'rate_limit', 'prevent_proxy_connections'];
    private const QUERY_RCON_FIELDS = ['enable_query', 'query_port', 'enable_rcon', 'rcon_port', 'rcon_password', 'broadcast_console_to_ops', 'broadcast_rcon_to_ops'];
    private const RESOURCE_PACK_FIELDS = ['resource_pack', 'resource_pack_sha1', 'resource_pack_prompt', 'resource_pack_id', 'require_resource_pack'];
    private const SECURITY_FIELDS = ['enforce_secure_profile', 'log_ips', 'text_filtering_config', 'hide_online_players'];
    private const ADVANCED_FIELDS = ['max_tick_time', 'op_permission_level', 'function_permission_level', 'entity_broadcast_range_percentage', 'max_chained_neighbor_updates', 'sync_chunk_writes', 'use_native_transport', 'enable_jmx_monitoring', 'enable_command_block', 'allow_nether', 'accepts_transfers', 'player_idle_timeout', 'pause_when_empty_seconds', 'region_file_compression', 'bug_report_link', 'debug'];
    private const DATAPACK_FIELDS = ['initial_enabled_packs', 'initial_disabled_packs'];

    // Version requirements for properties (min = added in, max = removed in)
    private const PROPERTY_VERSIONS = [
        // Properties ADDED in recent versions
        'force_gamemode' => ['min' => '1.14'],
        'require_resource_pack' => ['min' => '1.15'],
        'enable_jmx_monitoring' => ['min' => '1.16'],
        'enable_status' => ['min' => '1.16'],
        'entity_broadcast_range_percentage' => ['min' => '1.16'],
        'sync_chunk_writes' => ['min' => '1.16'],
        'use_native_transport' => ['min' => '1.16'],
        'rate_limit' => ['min' => '1.16.2'],
        'resource_pack_prompt' => ['min' => '1.17'],
        'simulation_distance' => ['min' => '1.18'],
        'hide_online_players' => ['min' => '1.18'],
        'enforce_secure_profile' => ['min' => '1.19'],
        'max_chained_neighbor_updates' => ['min' => '1.19'],
        'initial_enabled_packs' => ['min' => '1.19.3'],
        'initial_disabled_packs' => ['min' => '1.19.3'],
        'accepts_transfers' => ['min' => '1.20.5'],
        'region_file_compression' => ['min' => '1.20.5'],
        'log_ips' => ['min' => '1.20.2'],
        'bug_report_link' => ['min' => '1.21'],
        'pause_when_empty_seconds' => ['min' => '1.21.2'],
        // Properties REMOVED in recent versions
        'announce_player_achievements' => ['max' => '1.12'],
        'max_build_height' => ['max' => '1.17'],
        'snooper_enabled' => ['max' => '1.18'],
        'spawn_animals' => ['max' => '1.21.2'],
        'spawn_npcs' => ['max' => '1.21.2'],
        'spawn_monsters' => ['max' => '1.21.9'],
        'allow_nether' => ['max' => '1.21.9'],
        'enable_command_block' => ['max' => '1.21.9'],
        'pvp' => ['max' => '1.21.9'],
    ];
    private const ALL_FIELDS = [
        'motd', 'max_players', 'online_mode', 'pvp', 'difficulty', 'gamemode', 'view_distance', 'spawn_protection',
        'accepts_transfers', 'allow_flight', 'broadcast_console_to_ops', 'broadcast_rcon_to_ops', 'debug', 'allow_nether', 'enable_command_block',
        'enable_query', 'enable_rcon', 'force_gamemode', 'hardcore', 'level_name', 'level_seed', 'level_type',
        'max_tick_time', 'network_compression_threshold', 'op_permission_level', 'rcon_password', 'server_port',
        'simulation_distance', 'spawn_monsters', 'sync_chunk_writes', 'query_port', 'whitelist',
        'enable_jmx_monitoring', 'enable_status', 'enforce_secure_profile', 'enforce_whitelist',
        'entity_broadcast_range_percentage', 'function_permission_level', 'generate_structures', 'generator_settings',
        'hide_online_players', 'initial_disabled_packs', 'initial_enabled_packs', 'log_ips', 'max_chained_neighbor_updates',
        'max_world_size', 'player_idle_timeout', 'prevent_proxy_connections', 'rate_limit', 'rcon_port',
        'resource_pack', 'resource_pack_id', 'resource_pack_prompt', 'resource_pack_sha1', 'require_resource_pack', 'server_ip',
        'spawn_animals', 'spawn_npcs', 'text_filtering_config', 'use_native_transport',
        'bug_report_link', 'pause_when_empty_seconds', 'region_file_compression'
    ];

    private array $fieldTypes = [
        'motd' => 'string',
        'max_players' => 'string',
        'online_mode' => 'bool',
        'pvp' => 'bool',
        'difficulty' => 'string',
        'gamemode' => 'string',
        'view_distance' => 'string',
        'spawn_protection' => 'string',
        'accepts_transfers' => 'bool',
        'allow_flight' => 'bool',
        'broadcast_console_to_ops' => 'bool',
        'broadcast_rcon_to_ops' => 'bool',
        'debug' => 'bool',
        'allow_nether' => 'bool',
        'enable_command_block' => 'bool',
        'enable_query' => 'bool',
        'enable_rcon' => 'bool',
        'force_gamemode' => 'bool',
        'hardcore' => 'bool',
        'level_name' => 'string',
        'level_seed' => 'string',
        'level_type' => 'string',
        'max_tick_time' => 'string',
        'network_compression_threshold' => 'string',
        'op_permission_level' => 'string',
        'rcon_password' => 'string',
        'server_port' => 'string',
        'simulation_distance' => 'string',
        'spawn_monsters' => 'bool',
        'sync_chunk_writes' => 'bool',
        'query_port' => 'string',
        'whitelist' => 'bool',
        'enable_jmx_monitoring' => 'bool',
        'enable_status' => 'bool',
        'enforce_secure_profile' => 'bool',
        'enforce_whitelist' => 'bool',
        'entity_broadcast_range_percentage' => 'string',
        'function_permission_level' => 'string',
        'generate_structures' => 'bool',
        'generator_settings' => 'string',
        'hide_online_players' => 'bool',
        'initial_disabled_packs' => 'string',
        'initial_enabled_packs' => 'string',
        'log_ips' => 'bool',
        'max_chained_neighbor_updates' => 'string',
        'max_world_size' => 'string',
        'player_idle_timeout' => 'string',
        'prevent_proxy_connections' => 'bool',
        'rate_limit' => 'string',
        'rcon_port' => 'string',
        'resource_pack' => 'string',
        'resource_pack_id' => 'string',
        'resource_pack_prompt' => 'string',
        'resource_pack_sha1' => 'string',
        'require_resource_pack' => 'bool',
        'server_ip' => 'string',
        'spawn_animals' => 'bool',
        'spawn_npcs' => 'bool',
        'text_filtering_config' => 'string',
        'use_native_transport' => 'bool',
        'bug_report_link' => 'string',
        'pause_when_empty_seconds' => 'string',
        'region_file_compression' => 'string',
    ];

    private array $defaultValues = [
        'motd' => 'A Minecraft Server',
        'max-players' => 20,
        'gamemode' => 'survival',
        'online-mode' => 'true',
        'pvp' => 'true',
        'difficulty' => 'normal',
        'view-distance' => 10,
        'spawn-protection' => 0,
        'network-compression-threshold' => 256,
        'max-tick-time' => 60000,
        'op-permission-level' => 4,
        'simulation-distance' => 10,
        'query.port' => 25565,
        'rcon.password' => '',
        'server-port' => 25565,
        'generate-structures' => 'true',
        'max-world-size' => 29999984,
        'player-idle-timeout' => 0,
        'rate-limit' => 0,
        'rcon.port' => 25575,
        'resource-pack' => '',
        'resource-pack-id' => '',
        'resource-pack-prompt' => '',
        'resource-pack-sha1' => '',
        'server-ip' => '',
        'text-filtering-config' => '',
        'entity-broadcast-range-percentage' => 100,
        'function-permission-level' => 2,
        'generator-settings' => '',
        'initial-disabled-packs' => '',
        'initial-enabled-packs' => '',
        'max-chained-neighbor-updates' => 1000000,
        'bug-report-link' => '',
        'pause-when-empty-seconds' => 60,
        'region-file-compression' => 'deflate',
    ];

    private array $componentMapping = [
        // General
        'motd' => [TextInput::class, ['inlineLabel' => 'motd', 'tooltip' => 'Message displayed below server name in server lists']],
        'max_players' => [TextInput::class, ['inlineLabel' => 'max-players', 'numeric' => true, 'minValue' => 0, 'tooltip' => 'Maximum simultaneous players allowed']],
        'online_mode' => [Toggle::class, ['inlineLabel' => 'online-mode', 'tooltip' => 'Only allow players verified with Minecraft account database']],
        'enable_status' => [Toggle::class, ['inlineLabel' => 'enable-status', 'tooltip' => 'Controls whether server appears online in server lists']],
        'whitelist' => [Toggle::class, ['inlineLabel' => 'white-list', 'tooltip' => 'Enables whitelist.json for player access control']],
        'enforce_whitelist' => [Toggle::class, ['inlineLabel' => 'enforce-whitelist', 'tooltip' => 'Kicks online players not on the whitelist when enabled']],

        // Gameplay
        'difficulty' => [Select::class, ['inlineLabel' => 'difficulty', 'options' => [
            'peaceful' => 'Peaceful',
            'easy' => 'Easy',
            'normal' => 'Normal',
            'hard' => 'Hard',
        ], 'default' => 'normal', 'tooltip' => 'Sets world difficulty: peaceful, easy, normal, or hard']],
        'gamemode' => [Select::class, ['inlineLabel' => 'gamemode', 'options' => [
            'survival' => 'Survival',
            'creative' => 'Creative',
            'adventure' => 'Adventure',
            'spectator' => 'Spectator',
        ], 'default' => 'survival', 'tooltip' => 'Default mode for new players: survival, creative, adventure, spectator']],
        'force_gamemode' => [Toggle::class, ['inlineLabel' => 'force-gamemode', 'tooltip' => 'Switches players to default game mode upon joining']],
        'hardcore' => [Toggle::class, ['inlineLabel' => 'hardcore', 'tooltip' => 'Enables hardcore mode for created worlds']],
        'pvp' => [Toggle::class, ['inlineLabel' => 'pvp', 'tooltip' => 'Allow player versus player combat']],
        'spawn_monsters' => [Toggle::class, ['inlineLabel' => 'spawn-monsters', 'tooltip' => 'Enable monster spawning']],
        'spawn_animals' => [Toggle::class, ['inlineLabel' => 'spawn-animals', 'tooltip' => 'Enable animal spawning']],
        'spawn_npcs' => [Toggle::class, ['inlineLabel' => 'spawn-npcs', 'tooltip' => 'Enable villager spawning']],
        'allow_flight' => [Toggle::class, ['inlineLabel' => 'allow-flight', 'tooltip' => 'Permits players to use flight in Survival mode via mods']],

        // World
        'level_name' => [TextInput::class, ['inlineLabel' => 'level-name', 'tooltip' => 'World name and directory path']],
        'level_seed' => [TextInput::class, ['inlineLabel' => 'level-seed', 'tooltip' => 'Seed value for world generation']],
        'level_type' => [TextInput::class, ['inlineLabel' => 'level-type', 'tooltip' => 'World preset: normal, flat, large_biomes, amplified, single_biome_surface']],
        'generate_structures' => [Toggle::class, ['inlineLabel' => 'generate-structures', 'tooltip' => 'Enables or disables structure generation in new chunks']],
        'generator_settings' => [TextInput::class, ['inlineLabel' => 'generator-settings', 'tooltip' => 'Customization settings for world generation']],
        'spawn_protection' => [TextInput::class, ['inlineLabel' => 'spawn-protection', 'numeric' => true, 'minValue' => 0, 'tooltip' => 'Side length of spawn protection area']],
        'max_world_size' => [TextInput::class, ['inlineLabel' => 'max-world-size', 'numeric' => true, 'tooltip' => 'Distance from world center where world border appears']],
        'view_distance' => [TextInput::class, ['inlineLabel' => 'view-distance', 'numeric' => true, 'minValue' => 2, 'tooltip' => 'Server-side chunk rendering distance']],
        'simulation_distance' => [TextInput::class, ['inlineLabel' => 'simulation-distance', 'numeric' => true, 'tooltip' => 'Chunk radius for entity updates around players']],

        // Network
        'server_ip' => [TextInput::class, ['inlineLabel' => 'server-ip', 'tooltip' => 'IP address to listen on (empty = all addresses)']],
        'server_port' => [TextInput::class, ['inlineLabel' => 'server-port', 'numeric' => true, 'minValue' => 0, 'maxValue' => 65535, 'tooltip' => 'TCP port for player connections']],
        'network_compression_threshold' => [TextInput::class, ['inlineLabel' => 'network-compression-threshold', 'numeric' => true, 'tooltip' => 'Packet size threshold for compression in bytes']],
        'rate_limit' => [TextInput::class, ['inlineLabel' => 'rate-limit', 'numeric' => true, 'tooltip' => 'Maximum packets per player before kick (0 disables)']],
        'prevent_proxy_connections' => [Toggle::class, ['inlineLabel' => 'prevent-proxy-connections', 'tooltip' => 'Kicks players if ISP/AS differs from auth server']],

        // Query & RCON
        'enable_query' => [Toggle::class, ['inlineLabel' => 'enable-query', 'tooltip' => 'Enable query, which provides information about the server']],
        'query_port' => [TextInput::class, ['inlineLabel' => 'query.port', 'numeric' => true, 'minValue' => 0, 'maxValue' => 65535, 'tooltip' => 'UDP port for query protocol']],
        'enable_rcon' => [Toggle::class, ['inlineLabel' => 'enable-rcon', 'tooltip' => 'Allows remote console access over network connections']],
        'rcon_port' => [TextInput::class, ['inlineLabel' => 'rcon.port', 'numeric' => true, 'minValue' => 0, 'maxValue' => 65535, 'tooltip' => 'TCP port for RCON service']],
        'rcon_password' => [TextInput::class, ['inlineLabel' => 'rcon.password', 'tooltip' => 'Password for remote console access']],
        'broadcast_console_to_ops' => [Toggle::class, ['inlineLabel' => 'broadcast-console-to-ops', 'tooltip' => 'Send console command outputs to all online operators']],
        'broadcast_rcon_to_ops' => [Toggle::class, ['inlineLabel' => 'broadcast-rcon-to-ops', 'tooltip' => 'Sends RCON console outputs to all operators']],

        // Resource Pack
        'resource_pack' => [TextInput::class, ['inlineLabel' => 'resource-pack', 'tooltip' => 'Download URL for optional resource pack']],
        'resource_pack_sha1' => [TextInput::class, ['inlineLabel' => 'resource-pack-sha1', 'tooltip' => 'SHA-1 digest for resource pack integrity verification']],
        'resource_pack_prompt' => [TextInput::class, ['inlineLabel' => 'resource-pack-prompt', 'tooltip' => 'Custom message for resource pack prompt']],
        'resource_pack_id' => [TextInput::class, ['inlineLabel' => 'resource-pack-id', 'tooltip' => 'UUID identifier for the resource pack']],
        'require_resource_pack' => [Toggle::class, ['inlineLabel' => 'require-resource-pack', 'tooltip' => 'Disconnects players who decline resource pack']],

        // Security
        'enforce_secure_profile' => [Toggle::class, ['inlineLabel' => 'enforce-secure-profile', 'tooltip' => 'Only allow players with a Mojang-signed public key']],
        'log_ips' => [Toggle::class, ['inlineLabel' => 'log-ips', 'tooltip' => 'Show client IP addresses in console messages']],
        'text_filtering_config' => [TextInput::class, ['inlineLabel' => 'text-filtering-config', 'tooltip' => 'Chat filtering mechanism configuration']],
        'hide_online_players' => [Toggle::class, ['inlineLabel' => 'hide-online-players', 'tooltip' => 'Disables sending player list on status requests']],

        // Advanced
        'max_tick_time' => [TextInput::class, ['inlineLabel' => 'max-tick-time', 'numeric' => true, 'tooltip' => 'Maximum milliseconds per server tick before watchdog stops server']],
        'op_permission_level' => [TextInput::class, ['inlineLabel' => 'op-permission-level', 'numeric' => true, 'tooltip' => 'Default permission level for operators (0-4)']],
        'function_permission_level' => [TextInput::class, ['inlineLabel' => 'function-permission-level', 'numeric' => true, 'tooltip' => 'Sets default permission level for functions (1-4)']],
        'entity_broadcast_range_percentage' => [TextInput::class, ['inlineLabel' => 'entity-broadcast-range-percentage', 'numeric' => true, 'tooltip' => 'Controls entity rendering distance as percentage']],
        'max_chained_neighbor_updates' => [TextInput::class, ['inlineLabel' => 'max-chained-neighbor-updates', 'numeric' => true, 'tooltip' => 'Limits consecutive neighbor updates before skipping']],
        'sync_chunk_writes' => [Toggle::class, ['inlineLabel' => 'sync-chunk-writes', 'tooltip' => 'Enable synchronous chunk writes']],
        'use_native_transport' => [Toggle::class, ['inlineLabel' => 'use-native-transport', 'tooltip' => 'Enables Linux packet optimization']],
        'enable_jmx_monitoring' => [Toggle::class, ['inlineLabel' => 'enable-jmx-monitoring', 'tooltip' => 'Exposes server metrics like tick times via MBean']],
        'enable_command_block' => [Toggle::class, ['inlineLabel' => 'enable-command-block', 'tooltip' => 'Enable command blocks']],
        'allow_nether' => [Toggle::class, ['inlineLabel' => 'allow-nether', 'tooltip' => 'Allow travel to the Nether']],
        'accepts_transfers' => [Toggle::class, ['inlineLabel' => 'accepts-transfers', 'tooltip' => 'Whether to accept incoming transfers via a transfer packet']],
        'player_idle_timeout' => [TextInput::class, ['inlineLabel' => 'player-idle-timeout', 'numeric' => true, 'tooltip' => 'Minutes before idle players are kicked (0 disables)']],
        'pause_when_empty_seconds' => [TextInput::class, ['inlineLabel' => 'pause-when-empty-seconds', 'numeric' => true, 'tooltip' => 'Seconds before server pauses after all players leave']],
        'region_file_compression' => [Select::class, ['inlineLabel' => 'region-file-compression', 'options' => [
            'deflate' => 'Deflate',
            'lz4' => 'LZ4',
            'none' => 'None',
        ], 'default' => 'deflate', 'tooltip' => 'Chunk compression algorithm: deflate, lz4, or none']],
        'bug_report_link' => [TextInput::class, ['inlineLabel' => 'bug-report-link', 'tooltip' => 'URL for the server bug report link']],
        'debug' => [Toggle::class, ['inlineLabel' => 'debug', 'tooltip' => 'Enable debug mode']],

        // Data Packs
        'initial_disabled_packs' => [TextInput::class, ['inlineLabel' => 'initial-disabled-packs', 'tooltip' => 'Datapacks to exclude from auto-enable at world creation']],
        'initial_enabled_packs' => [TextInput::class, ['inlineLabel' => 'initial-enabled-packs', 'tooltip' => 'Datapacks to enable at world creation']],
    ];

    private array $propertyMapping = [
        'motd' => 'motd',
        'max_players' => 'max-players',
        'online_mode' => 'online-mode',
        'pvp' => 'pvp',
        'difficulty' => 'difficulty',
        'gamemode' => 'gamemode',
        'view_distance' => 'view-distance',
        'spawn_protection' => 'spawn-protection',
        'accepts_transfers' => 'accepts-transfers',
        'allow_flight' => 'allow-flight',
        'broadcast_console_to_ops' => 'broadcast-console-to-ops',
        'broadcast_rcon_to_ops' => 'broadcast-rcon-to-ops',
        'debug' => 'debug',
        'allow_nether' => 'allow-nether',
        'enable_command_block' => 'enable-command-block',
        'enable_query' => 'enable-query',
        'enable_rcon' => 'enable-rcon',
        'force_gamemode' => 'force-gamemode',
        'hardcore' => 'hardcore',
        'level_name' => 'level-name',
        'level_seed' => 'level-seed',
        'level_type' => 'level-type',
        'max_tick_time' => 'max-tick-time',
        'network_compression_threshold' => 'network-compression-threshold',
        'op_permission_level' => 'op-permission-level',
        'rcon_password' => 'rcon.password',
        'server_port' => 'server-port',
        'simulation_distance' => 'simulation-distance',
        'spawn_monsters' => 'spawn-monsters',
        'sync_chunk_writes' => 'sync-chunk-writes',
        'query_port' => 'query.port',
        'whitelist' => 'white-list',
        'enable_jmx_monitoring' => 'enable-jmx-monitoring',
        'enable_status' => 'enable-status',
        'enforce_secure_profile' => 'enforce-secure-profile',
        'enforce_whitelist' => 'enforce-whitelist',
        'entity_broadcast_range_percentage' => 'entity-broadcast-range-percentage',
        'function_permission_level' => 'function-permission-level',
        'generate_structures' => 'generate-structures',
        'generator_settings' => 'generator-settings',
        'hide_online_players' => 'hide-online-players',
        'initial_disabled_packs' => 'initial-disabled-packs',
        'initial_enabled_packs' => 'initial-enabled-packs',
        'log_ips' => 'log-ips',
        'max_chained_neighbor_updates' => 'max-chained-neighbor-updates',
        'max_world_size' => 'max-world-size',
        'player_idle_timeout' => 'player-idle-timeout',
        'prevent_proxy_connections' => 'prevent-proxy-connections',
        'rate_limit' => 'rate-limit',
        'rcon_port' => 'rcon.port',
        'resource_pack' => 'resource-pack',
        'resource_pack_id' => 'resource-pack-id',
        'resource_pack_prompt' => 'resource-pack-prompt',
        'resource_pack_sha1' => 'resource-pack-sha1',
        'require_resource_pack' => 'require-resource-pack',
        'server_ip' => 'server-ip',
        'spawn_animals' => 'spawn-animals',
        'spawn_npcs' => 'spawn-npcs',
        'text_filtering_config' => 'text-filtering-config',
        'use_native_transport' => 'use-native-transport',
        'bug_report_link' => 'bug-report-link',
        'pause_when_empty_seconds' => 'pause-when-empty-seconds',
        'region_file_compression' => 'region-file-compression',
    ];

    private function toBool(?string $value, bool $default = false): bool
    {
        return is_null($value) ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function getMinecraftVersion(): ?string
    {
        /** @var Server|null $server */
        $server = Filament::getTenant();
        if (!$server instanceof Server) {
            return null;
        }

        return $server->variables()
            ->where(fn ($builder) => $builder
                ->where('env_variable', 'MINECRAFT_VERSION')
                ->orWhere('env_variable', 'MC_VERSION'))
            ->first()?->server_value;
    }

    private function isPropertyAvailableForVersion(string $field): bool
    {
        $serverVersion = $this->getMinecraftVersion();
        if (!$serverVersion) {
            return true; // Show all if version unknown
        }

        $versionInfo = self::PROPERTY_VERSIONS[$field] ?? null;
        if (!$versionInfo) {
            return true; // Property exists since early versions
        }

        $minVersion = $versionInfo['min'] ?? null;
        $maxVersion = $versionInfo['max'] ?? null;

        // Check minimum version (property not yet added)
        if ($minVersion && version_compare($serverVersion, $minVersion, '<')) {
            return false;
        }

        // Check maximum version (property removed)
        if ($maxVersion && version_compare($serverVersion, $maxVersion, '>=')) {
            return false;
        }

        return true;
    }

    private function translateFieldKey(string $field, ?string $suffix, string $fallback): string
    {
        // Prefer plugin-provided translations in resources/lang/{locale}.php
        $locale = app()->getLocale() ?: config('app.locale');
        $candidates = [$locale];
        if (str_contains($locale, '-')) {
            $candidates[] = explode('-', $locale)[0];
        }
        $candidates[] = 'en';

        $key = $suffix ? "{$field}_{$suffix}" : $field;

        foreach ($candidates as $loc) {
            $path = __DIR__ . '/../../../../resources/lang/' . $loc . '.php';
            if (!file_exists($path)) continue;
            try {
                $translations = include $path;
                if (is_array($translations) && array_key_exists($key, $translations)) {
                    return $translations[$key];
                }
            } catch (\Throwable $e) {
                // ignore and try next candidate
            }
        }

        // fall back to Laravel translation if available
        $laravelKey = $suffix ? "minecraft-properties::{$field}_{$suffix}" : "minecraft-properties::{$field}";
        $translated = __($laravelKey);
        if ($translated !== $laravelKey) return $translated;

        return $fallback;
    }

    private function createComponent(string $field)
    {
        if (!isset($this->componentMapping[$field])) {
            throw new \InvalidArgumentException("Unknown field: $field");
        }

        [$class, $options] = $this->componentMapping[$field];

        $component = $class::make($field);

        // Get tooltip text for hint
        $tooltip = $options['tooltip'] ?? null;
        $inlineLabel = $options['inlineLabel'] ?? null;

        foreach ($options as $key => $value) {
            // Handle inline label
            if ($key === 'inlineLabel') {
                $translatedTooltip = $this->translateFieldKey($field, 'tooltip', $options['tooltip'] ?? '');

                // Label with dotted underline and instant tooltip (no icon)
                $labelStyle = 'border-bottom: 1px dotted currentColor; cursor: help;';

                if ($class === TextInput::class || $class === Textarea::class) {
                    // TextInput: [prefix with name] [input]
                    $prefixHtml = '<span title="' . e($translatedTooltip) . '" class="font-medium text-gray-700 dark:text-gray-300" style="' . $labelStyle . '">' . e($value) . '</span>';
                    $component->prefix(new \Illuminate\Support\HtmlString($prefixHtml))->hiddenLabel();
                } elseif ($class === Toggle::class) {
                    // Toggle: [name] [switch]
                    $labelHtml = '<span title="' . e($translatedTooltip) . '" class="font-medium text-gray-700 dark:text-gray-300" style="' . $labelStyle . '">' . e($value) . '</span>';
                    $component->label(new \Illuminate\Support\HtmlString($labelHtml))
                        ->inline()
                        ->extraAttributes(['class' => 'gap-3']);
                } elseif ($class === Select::class) {
                    // Select: [prefix with name] [select]
                    $prefixHtml = '<span title="' . e($translatedTooltip) . '" class="font-medium text-gray-700 dark:text-gray-300" style="' . $labelStyle . '">' . e($value) . '</span>';
                    $component->prefix(new \Illuminate\Support\HtmlString($prefixHtml))->hiddenLabel();
                }
                continue;
            }

            // Skip tooltip key as it's handled in inlineLabel
            if ($key === 'tooltip') {
                continue;
            }

            if ($key === 'options' && is_array($value)) {
                // translate select option labels, e.g. difficulty_peaceful
                $translatedOptions = [];
                foreach ($value as $optKey => $optLabel) {
                    $translatedOptions[$optKey] = $this->translateFieldKey($field . '_' . $optKey, null, $optLabel);
                }
                if (method_exists($component, 'options')) {
                    $component->options($translatedOptions);
                }
                continue;
            }

            if (method_exists($component, $key)) {
                $component->$key($value);
            }
        }

        return $component;
    }

    private function mapStateToProperties(array $state): array
    {
        $props = $this->originalProps;

        foreach ($this->propertyMapping as $field => $property) {
            if (!$this->isPropertyAvailable($field)) continue;

            $value = $state[$field] ?? $this->defaultValues[$property] ?? null;

            if (is_bool($value)) {
                $props[$property] = $value ? 'true' : 'false';
            } elseif (!is_null($value)) {
                $props[$property] = (string) $value;
            }
        }

        return $props;
    }

    public function mount(): void
    {
        parent::mount();

        $this->loadProperties();

        $this->data = array_combine(self::ALL_FIELDS, array_map(fn($field) => $this->{$field}, self::ALL_FIELDS));
        $this->data['raw'] = $this->raw;

        if (isset($this->form)) {
            $this->form->fill($this->data);
        }

        $this->originalData = $this->data;
        $this->originalRaw = $this->raw ?? '';
    }

    private function isPropertyAvailable(string $field): bool
    {
        // Check if property exists in the server.properties file
        $property = $this->propertyMapping[$field] ?? $field;
        $existsInFile = in_array($property, $this->availableProperties);

        // Also check version compatibility
        $versionCompatible = $this->isPropertyAvailableForVersion($field);

        return $existsInFile && $versionCompatible;
    }

    private function matchesSearch(string $field): bool
    {
        if (empty($this->search)) {
            return true;
        }

        $searchLower = strtolower($this->search);
        $property = $this->propertyMapping[$field] ?? $field;

        // Search in property name
        if (str_contains(strtolower($property), $searchLower)) {
            return true;
        }

        // Search in field name
        if (str_contains(strtolower($field), $searchLower)) {
            return true;
        }

        // Search in tooltip
        $tooltip = $this->componentMapping[$field][1]['tooltip'] ?? '';
        if (str_contains(strtolower($tooltip), $searchLower)) {
            return true;
        }

        return false;
    }

    public function updatedSearch(): void
    {
        // Trigger form re-render when search changes
    }

    public function form(Schema $schema): Schema
    {
        if (empty($this->availableProperties)) {
            $this->loadProperties();
        }

        // Filter function combining availability and search
        $filterField = fn($field) => $this->isPropertyAvailable($field) && $this->matchesSearch($field);

        // Build components for each category
        $generalComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::GENERAL_FIELDS, $filterField)));
        $gameplayComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::GAMEPLAY_FIELDS, $filterField)));
        $worldComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::WORLD_FIELDS, $filterField)));
        $networkComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::NETWORK_FIELDS, $filterField)));
        $queryRconComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::QUERY_RCON_FIELDS, $filterField)));
        $resourcePackComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::RESOURCE_PACK_FIELDS, $filterField)));
        $securityComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::SECURITY_FIELDS, $filterField)));
        $advancedComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::ADVANCED_FIELDS, $filterField)));
        $datapackComponents = array_values(array_map(fn($field) => $this->createComponent($field), array_filter(self::DATAPACK_FIELDS, $filterField)));

        // Search bar - using wire:model.live directly on the Livewire property
        $searchBar = TextInput::make('searchInput')
            ->placeholder($this->translateFieldKey('search_placeholder', null, 'Search properties...'))
            ->prefixIcon('tabler-search')
            ->hiddenLabel()
            ->extraInputAttributes(['wire:model.live.debounce.250ms' => 'search'])
            ->extraAttributes(['class' => 'mb-4'])
            ->columnSpanFull();

        // Properties tab content
        $propertiesSections = [$searchBar];

        if (!empty($generalComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_general', null, 'General'))
                ->icon('tabler-settings')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(3)->schema($generalComponents)]);
        }

        if (!empty($gameplayComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_gameplay', null, 'Gameplay'))
                ->icon('tabler-sword')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(3)->schema($gameplayComponents)]);
        }

        if (!empty($worldComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_world', null, 'World'))
                ->icon('tabler-world')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(3)->schema($worldComponents)]);
        }

        if (!empty($networkComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_network', null, 'Network'))
                ->icon('tabler-network')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(3)->schema($networkComponents)]);
        }

        if (!empty($queryRconComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_query_rcon', null, 'Query & RCON'))
                ->icon('tabler-terminal-2')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(3)->schema($queryRconComponents)]);
        }

        if (!empty($resourcePackComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_resource_pack', null, 'Resource Pack'))
                ->icon('tabler-package')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(2)->schema($resourcePackComponents)]);
        }

        if (!empty($securityComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_security', null, 'Security'))
                ->icon('tabler-shield-lock')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(3)->schema($securityComponents)]);
        }

        if (!empty($advancedComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_advanced', null, 'Advanced'))
                ->icon('tabler-adjustments')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(3)->schema($advancedComponents)]);
        }

        if (!empty($datapackComponents)) {
            $propertiesSections[] = Section::make($this->translateFieldKey('section_datapacks', null, 'Data Packs'))
                ->icon('tabler-database')
                ->columnSpanFull()
                ->schema([Grid::make()->columns(2)->schema($datapackComponents)]);
        }

        // Raw editor content
        $rawEditor = Textarea::make('raw')
            ->label($this->translateFieldKey('raw', 'label', 'Raw server.properties'))
            ->rows(25)
            ->helperText($this->translateFieldKey('raw', 'helper', 'Edit the raw server.properties file directly'))
            ->columnSpanFull()
            ->reactive()
            ->debounce(500)
            ->afterStateUpdated(function ($state) {
                $this->syncFromRaw($state);
            });

        return parent::form($schema)
            ->components([
                Tabs::make('view_tabs')
                    ->tabs([
                        Tab::make('properties')
                            ->label($this->translateFieldKey('tab_properties', null, 'Properties'))
                            ->icon('tabler-list')
                            ->schema($propertiesSections),
                        Tab::make('raw')
                            ->label($this->translateFieldKey('tab_raw', null, 'Raw Editor'))
                            ->icon('tabler-code')
                            ->schema([
                                Section::make()
                                    ->columnSpanFull()
                                    ->schema([$rawEditor]),
                            ]),
                    ])
                    ->contained(false),
            ]);
    }

    public function getHeading(): ?string
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('wiki')
                ->label('Wiki')
                ->color('gray')
                ->icon('tabler-external-link')
                ->url('https://minecraft.wiki/w/Server.properties', shouldOpenInNewTab: true),
            Action::make('save')
                ->label($this->translateFieldKey('action_save', null, 'Save'))
                ->color('primary')
                ->icon('tabler-device-floppy')
                ->action('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function loadProperties(): void
    {
        /** @var Server|null $server */
        $server = Filament::getTenant();
        if (! $server instanceof Server) {
            return;
        }
        try {
            $repo = app(DaemonFileRepository::class)->setServer($server);
            $content = $repo->getContent('server.properties');
        } catch (\Throwable $e) {
            $this->resetForm();
            return;
        }

        $props = $this->parseProperties($content);

        $this->availableProperties = array_keys($props);
        $this->originalProps = $props;

        foreach (self::ALL_FIELDS as $field) {
            $property = $this->propertyMapping[$field] ?? $field;
            $value = $props[$property] ?? null;

            if ($this->fieldTypes[$field] === 'bool') {
                $default = in_array($field, ['online_mode', 'pvp']) ? true : false;
                $this->{$field} = $this->toBool($value, $default);
            } else {
                $this->{$field} = $value;
            }
        }

        $this->raw = $content;
    }

    public function save(): void
    {
        /** @var Server|null $server */
        $server = Filament::getTenant();
        if (! $server instanceof Server) {
            Notification::make()
                ->danger()
                ->title('Invalid server.')
                ->send();
            return;
        }

        $currentState = $this->form->getState();
        $props = $this->mapStateToProperties($currentState);

        $lines = [];
        $lines[] = '#Minecraft server properties';
        $lines[] = '#' . now()->toDateTimeString();

        foreach ($props as $key => $value) {
            $lines[] = $key . '=' . $value;
        }

        $content = implode("\n", $lines) . "\n";

        try {
            $repo = app(DaemonFileRepository::class)->setServer($server);
            $repo->putContent('server.properties', $content);

            $this->raw = $content;
            $this->originalRaw = $content;
            $this->originalData = $currentState;

            Notification::make()
                ->success()
                ->title('Saved server.properties successfully.')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Failed to save server.properties: ' . $e->getMessage())
                ->send();
        }
    }

    private function parseProperties(string $content): array
    {
        return array_reduce(preg_split('/\r\n|\r|\n/', $content) ?? [], function($carry, $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) return $carry;
            [$key, $value] = array_map('trim', explode('=', $line, 2) + [null, null]);
            if ($key && $value !== null) $carry[$key] = $value;
            return $carry;
        }, []);
    }

    private function syncFromRaw(string $rawContent): void
    {
        $parsed = $this->parseProperties($rawContent);
        $reverseMapping = array_flip($this->propertyMapping);
        $formData = [];
        foreach ($parsed as $prop => $value) {
            if (isset($reverseMapping[$prop])) {
                $field = $reverseMapping[$prop];
                $type = $this->fieldTypes[$field] ?? 'string';
                $formData[$field] = $type === 'bool' ? $this->toBool($value) : $value;
            }
        }
        $currentState = $this->form->getState();
        $merged = array_merge($currentState, $formData);
        $this->form->fill($merged);
    }
}
