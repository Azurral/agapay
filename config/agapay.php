<?php

use App\Models\Role;

return [
    // The release shown on screen; bumped with every release (the commit is named after it, e.g. "v0.9.3").
    'version' => '0.11.0',

    // Development/demo password for seeded accounts. Change in .env for any real deployment.
    'seed_password' => env('AGAPAY_SEED_PASSWORD', 'Agapay@2026'),

    // Pins only the header date (e.g. the Figma "Wed, July 22") for `npm run visual`.
    // Never freeze the global clock: session cookie expiry is computed from it.
    'frozen_now' => env('APP_ENV') === 'production' ? null : env('AGAPAY_FROZEN_NOW'),

    // Sidebar items per role (Figma order). Each is also filtered by its permission.
    'nav' => [
        Role::ADMIN => [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
            ['label' => 'DA Intervention', 'route' => 'interventions.da', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'LGU Intervention', 'route' => 'interventions.lgu', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'Crisis Reports', 'route' => 'damage.index', 'icon' => 'disaster', 'permission' => 'damage.view'],
            ['label' => 'User Management', 'route' => 'users.index', 'icon' => 'user-mgmt', 'permission' => 'users.manage'],
            ['label' => 'Audit Trail', 'route' => 'audit.index', 'icon' => 'audit', 'permission' => 'audit.view'],
            ['label' => 'Download Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
        ],
        Role::AGRITECH => [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
            ['label' => 'DA Intervention', 'route' => 'interventions.da', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'LGU Intervention', 'route' => 'interventions.lgu', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'Crisis Reports', 'route' => 'damage.index', 'icon' => 'disaster', 'permission' => 'damage.view'],
            ['label' => 'Download Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
        ],
        Role::ENCODER => [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
            ['label' => 'Beneficiary Profiles', 'route' => 'beneficiaries.index', 'icon' => 'profile', 'permission' => 'beneficiaries.manage'],
            ['label' => 'Intervention Records', 'route' => 'intervention-records.index', 'icon' => 'report', 'permission' => 'intervention_records.manage'],
            ['label' => 'Inventory', 'route' => 'inventory.index', 'icon' => 'inventory', 'permission' => 'inventory.view'],
            ['label' => 'Download Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
        ],
    ],

    // "Select an action to get started:" pills. width/gap are the Figma pill sizes per role.
    'quick_actions' => [
        Role::ADMIN => ['width' => 176, 'gap' => 14, 'items' => [
            ['label' => 'Add User', 'route' => 'users.index', 'query' => ['add' => 1], 'permission' => 'users.manage'],
            ['label' => 'Export List', 'route' => 'export.index', 'permission' => 'export.run'],
            ['label' => 'Interventions', 'route' => 'interventions.index', 'permission' => 'interventions.view'],
            ['label' => 'Upload Excel', 'route' => 'import.index', 'permission' => 'import.run'],
            ['label' => 'Inventory', 'route' => 'inventory.index', 'permission' => 'inventory.view'],
        ]],
        Role::AGRITECH => ['width' => 226, 'gap' => 17, 'items' => [
            ['label' => 'File Crisis Report', 'route' => 'damage.create', 'permission' => 'damage.create'],
            ['label' => 'Interventions', 'route' => 'interventions.index', 'permission' => 'interventions.view'],
            ['label' => 'New Request', 'route' => 'assistance-requests.create', 'permission' => 'requests.create'],
        ]],
        Role::ENCODER => ['width' => 200, 'gap' => 22, 'items' => [
            ['label' => 'Upload Excel', 'route' => 'import.index', 'permission' => 'import.run'],
            ['label' => 'Add Beneficiary', 'route' => 'beneficiaries.create', 'permission' => 'rsbsa.register'],
            ['label' => 'Crisis Reports', 'route' => 'damage.index', 'permission' => 'damage.view'],
            ['label' => 'New Request', 'route' => 'assistance-requests.create', 'permission' => 'requests.create'],
        ]],
    ],

    // Beneficiary rows in the distribution report PDF (DomPDF memory); the Excel workbook lists everyone.
    'report_pdf_max_rows' => 1000,

    // Rows in the damage report PDF table (DomPDF needs ~0.4 MB per row); the Excel export has no cap.
    'damage_pdf_max_rows' => 1000,

    // Excel import limits (php.ini must allow uploads this large: upload_max_filesize 25M, post_max_size 30M, memory_limit 512M).
    'import' => [
        'max_kilobytes' => 25 * 1024,
        'max_rows' => 20000,
    ],
];
