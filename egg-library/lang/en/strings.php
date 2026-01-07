<?php

return [
    'navigation' => [
        'egg_library' => 'Egg Library',
    ],

    'page' => [
        'title' => 'Egg Library',
        'description' => 'Browse and install eggs from the pelican-eggs GitHub repository',
    ],

    'sections' => [
        'categories' => 'Categories',
        'available_eggs' => 'Available Eggs',
        'browse_description' => 'Select a category or search to find eggs',
    ],

    'categories' => [
        'all' => 'All',
    ],

    'labels' => [
        'name' => 'Name',
        'author' => 'Author',
        'category' => 'Category',
        'tags' => 'Tags',
        'status' => 'Status',
        'import_mode' => 'Import Mode',
        'custom_name' => 'Custom Name (optional)',
    ],

    'status' => [
        'similar' => 'Similar Installed',
    ],

    'actions' => [
        'import' => 'Import',
        'view_source' => 'View Source',
        'refresh' => 'Refresh',
        'view_eggs' => 'Installed Eggs',
        'browse_library' => 'Browse Library',
    ],

    'options' => [
        'skip' => 'Cancel',
        'skip_desc' => 'Cancel the import and keep the existing egg',
        'update_existing' => 'Overwrite',
        'update_existing_desc' => 'Replace the existing egg with the new version from the library',
        'create_new' => 'Create with another name',
        'create_new_desc' => 'Import as a new egg with a fresh UUID and custom name',
    ],

    'modals' => [
        'import_heading' => 'Import :name',
    ],

    'warnings' => [
        'egg_exists' => 'An egg with this UUID already exists in your panel.',
        'egg_exists_uuid' => 'An egg with this exact UUID already exists. Choose an action below.',
        'egg_exists_name' => 'An egg named ":name" already exists. Choose an action below.',
    ],

    'notifications' => [
        'import_success' => 'Egg Imported Successfully',
        'import_success_body' => ':name has been :action successfully',
        'import_failed' => 'Import Failed',
        'import_skipped' => 'Import Skipped',
        'fetch_failed' => 'Failed to fetch egg content from GitHub',
        'cache_refreshed' => 'Cache refreshed successfully',
    ],
];
