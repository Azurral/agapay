<?php

namespace App\Support;

use App\Models\Role;

final class PermissionCatalog
{
    /** slug => [label, group] */
    public const PERMISSIONS = [
        'dashboard.view' => ['View home dashboard', 'General'],
        'users.manage' => ['Manage user accounts', 'Administration'],
        'roles.configure' => ['Configure role permissions', 'Administration'],
        'audit.view' => ['View audit trail', 'Administration'],
        'beneficiaries.view' => ['View beneficiary profiles', 'Beneficiaries'],
        'beneficiaries.manage' => ['Add and edit beneficiary profiles', 'Beneficiaries'],
        'rsbsa.register' => ['Encode RSBSA registrations', 'Beneficiaries'],
        'rsbsa.process' => ['Validate and endorse RSBSA registrations', 'Beneficiaries'],
        'interventions.view' => ['View DA and LGU intervention lists', 'Interventions'],
        'interventions.validate' => ['Validate beneficiary eligibility', 'Interventions'],
        'interventions.claim' => ['Process intervention claims', 'Interventions'],
        'interventions.archive' => ['Archive and restore intervention records', 'Interventions'],
        'intervention_records.manage' => ['Encode intervention records', 'Interventions'],
        'inventory.view' => ['View inventory', 'Inventory'],
        'inventory.manage' => ['Record stock movements', 'Inventory'],
        'damage.view' => ['View agricultural damage reports', 'Damage Recording'],
        'damage.create' => ['File agricultural damage reports', 'Damage Recording'],
        'damage.validate' => ['Validate agricultural damage reports', 'Damage Recording'],
        'import.run' => ['Import Excel files', 'Data'],
        'export.run' => ['Export beneficiary lists', 'Data'],
        'reports.generate' => ['Generate reports', 'Reports'],
    ];

    /** Permissions the Administrator role can never lose (prevents lock-out). */
    public const LOCKED_FOR_ADMIN = ['users.manage', 'roles.configure'];

    /** @return array<string, list<string>> role slug => permission slugs */
    public static function defaults(): array
    {
        return [
            Role::ADMIN => array_keys(self::PERMISSIONS),
            Role::AGRITECH => [
                'dashboard.view', 'beneficiaries.view', 'rsbsa.process',
                'interventions.view', 'interventions.validate', 'interventions.claim',
                'damage.view', 'damage.create', 'damage.validate', 'reports.generate',
            ],
            Role::ENCODER => [
                'dashboard.view', 'beneficiaries.view', 'beneficiaries.manage', 'rsbsa.register',
                'intervention_records.manage', 'interventions.claim',
                'inventory.view', 'inventory.manage', 'import.run',
                'damage.view', 'damage.create', 'reports.generate',
            ],
        ];
    }
}
