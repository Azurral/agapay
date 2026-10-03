<?php

use App\Models\Role;

return [
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
            ['label' => 'Newly Registered', 'route' => 'rsbsa.register', 'icon' => 'newly-registered', 'permission' => 'rsbsa.process'],
            ['label' => 'Disaster Reports', 'route' => 'damage.index', 'icon' => 'disaster', 'permission' => 'damage.view'],
            ['label' => 'User Management', 'route' => 'users.index', 'icon' => 'user-mgmt', 'permission' => 'users.manage'],
            ['label' => 'Audit Trail', 'route' => 'audit.index', 'icon' => 'audit', 'permission' => 'audit.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
        ],
        Role::AGRITECH => [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
            ['label' => 'Beneficiary Validation', 'route' => 'validation.index', 'icon' => 'validation', 'permission' => 'interventions.validate'],
            ['label' => 'DA Intervention', 'route' => 'interventions.da', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'LGU Intervention', 'route' => 'interventions.lgu', 'icon' => 'report', 'permission' => 'interventions.view'],
            ['label' => 'Disaster Reports', 'route' => 'damage.index', 'icon' => 'disaster', 'permission' => 'damage.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
        ],
        Role::ENCODER => [
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
            ['label' => 'Beneficiary Profiles', 'route' => 'beneficiaries.index', 'icon' => 'profile', 'permission' => 'beneficiaries.manage'],
            ['label' => 'Intervention Records', 'route' => 'intervention-records.index', 'icon' => 'report', 'permission' => 'intervention_records.manage'],
            ['label' => 'Inventory', 'route' => 'inventory.index', 'icon' => 'inventory', 'permission' => 'inventory.view'],
            ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'audit', 'permission' => 'reports.generate'],
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
            ['label' => 'File Disaster Report', 'route' => 'damage.create', 'permission' => 'damage.create'],
            ['label' => 'Interventions', 'route' => 'interventions.index', 'permission' => 'interventions.view'],
        ]],
        Role::ENCODER => ['width' => 200, 'gap' => 22, 'items' => [
            ['label' => 'Upload Excel', 'route' => 'import.index', 'permission' => 'import.run'],
            ['label' => 'Add Beneficiary', 'route' => 'rsbsa.register', 'permission' => 'rsbsa.register'],
            ['label' => 'Disaster Reports', 'route' => 'damage.index', 'permission' => 'damage.view'],
        ]],
    ],

    // Sidebar counters per role; colors are assigned in order from 'stat_colors'.
    'stats' => [
        Role::ADMIN => [
            'total_beneficiaries' => 'Total Beneficiaries',
            'pending_rsbsa' => 'Pending RSBSA',
            'active_interventions' => 'Active Interventions',
            'active_users' => 'Active Users',
        ],
        Role::AGRITECH => [
            'pending_validation' => 'Pending Validation',
            'active_interventions' => 'Active Interventions',
            'reports_filed_this_month' => 'Reports Filed This Month',
        ],
        Role::ENCODER => [
            'encoded_this_month' => 'Encoded This Month',
            'records_to_update' => 'Records to be Updated',
            'low_stock_items' => 'Low Stock Items',
        ],
    ],

    'stat_colors' => ['#da37ff', '#8037ff', '#4671ff', '#0bcaff'],

    // Rows in the damage report PDF table (DomPDF needs ~0.4 MB per row); the Excel export has no cap.
    'damage_pdf_max_rows' => 1000,

    // Excel import limits (php.ini must allow uploads this large: upload_max_filesize 25M, post_max_size 30M, memory_limit 512M).
    'import' => [
        'max_kilobytes' => 25 * 1024,
        'max_rows' => 20000,
    ],
];
