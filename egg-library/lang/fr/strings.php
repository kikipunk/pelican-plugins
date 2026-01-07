<?php

return [
    'navigation' => [
        'egg_library' => 'Bibliothèque d\'Eggs',
    ],

    'page' => [
        'title' => 'Bibliothèque d\'Eggs',
        'description' => 'Parcourir et installer des eggs depuis le dépôt GitHub pelican-eggs',
    ],

    'sections' => [
        'categories' => 'Catégories',
        'available_eggs' => 'Eggs Disponibles',
        'browse_description' => 'Sélectionnez une catégorie ou recherchez des eggs',
    ],

    'categories' => [
        'all' => 'Tous',
    ],

    'labels' => [
        'name' => 'Nom',
        'author' => 'Auteur',
        'category' => 'Catégorie',
        'tags' => 'Tags',
        'status' => 'Statut',
        'import_mode' => 'Mode d\'import',
        'custom_name' => 'Nom personnalisé (optionnel)',
    ],

    'status' => [
        'similar' => 'Similaire Installé',
    ],

    'actions' => [
        'import' => 'Importer',
        'view_source' => 'Voir la Source',
        'refresh' => 'Rafraîchir',
        'view_eggs' => 'Eggs Installés',
        'browse_library' => 'Parcourir la Bibliothèque',
    ],

    'options' => [
        'skip' => 'Annuler',
        'skip_desc' => 'Annuler l\'import et garder l\'egg existant',
        'update_existing' => 'Écraser',
        'update_existing_desc' => 'Remplacer l\'egg existant par la nouvelle version de la bibliothèque',
        'create_new' => 'Créer avec un autre nom',
        'create_new_desc' => 'Importer comme nouvel egg avec un UUID généré et un nom personnalisé',
    ],

    'modals' => [
        'import_heading' => 'Importer :name',
    ],

    'warnings' => [
        'egg_exists' => 'Un egg avec cet UUID existe déjà dans votre panel.',
        'egg_exists_uuid' => '⚠️ Un egg avec cet UUID exact existe déjà. Choisissez une action ci-dessous.',
        'egg_exists_name' => '⚠️ Un egg nommé ":name" existe déjà. Choisissez une action ci-dessous.',
    ],

    'notifications' => [
        'import_success' => 'Egg Importé avec Succès',
        'import_success_body' => ':name a été :action avec succès',
        'import_failed' => 'Échec de l\'Import',
        'import_skipped' => 'Import Ignoré',
        'fetch_failed' => 'Impossible de récupérer le contenu de l\'egg depuis GitHub',
        'cache_refreshed' => 'Cache rafraîchi avec succès',
    ],
];
