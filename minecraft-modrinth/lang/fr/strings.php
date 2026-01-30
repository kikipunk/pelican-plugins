<?php

return [
    'plugin_name' => 'Modrinth',
    'minecraft_mods' => 'Mods Minecraft',
    'minecraft_plugins' => 'Plugins Minecraft',
    'minecraft_datapacks' => 'Datapacks Minecraft',

    'page' => [
        'open_folder' => 'Ouvrir le dossier :folder',
        'minecraft_version' => 'Version Minecraft',
        'loader' => 'Plateforme',
        'installed' => ':type installés',
        'unknown' => 'Inconnu',
        'loading_mods' => 'Chargement des infos... :loaded/:total',
    ],

    'tabs' => [
        'modrinth' => 'Modrinth',
        'installed' => 'Installés',
    ],

    'table' => [
        'columns' => [
            'title' => 'Titre',
            'author' => 'Auteur',
            'downloads' => 'Téléchargements',
            'date_modified' => 'Modifié',
            'status' => 'Statut',
            'version' => 'Version',
            'filename' => 'Nom du fichier',
            'size' => 'Taille',
        ],
    ],

    'status' => [
        'installed' => 'Installé',
        'not_installed' => 'Non installé',
        'update_available' => 'Mise à jour disponible',
        'up_to_date' => 'À jour',
    ],

    'version' => [
        'type' => 'Type',
        'downloads' => 'Téléchargements',
        'published' => 'Publié',
        'changelog' => 'Changelog',
        'no_file_found' => 'Aucun fichier trouvé',
    ],

    'actions' => [
        'download' => 'Télécharger',
        'update' => 'Mettre à jour',
        'change_version' => 'Changer de version',
        'delete' => 'Supprimer',
    ],

    'notifications' => [
        'download_started' => 'Téléchargement démarré',
        'download_failed' => 'Le téléchargement n\'a pas pu démarrer',
        'delete_success' => 'Fichier supprimé',
        'delete_failed' => 'Le fichier n\'a pas pu être supprimé',
    ],
];
