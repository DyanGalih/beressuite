<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Per-Token Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum requests per minute for each persistent Bearer API token on the
    | authenticated /api/v1/* group (named "api-token" limiter). Browser SPA
    | sessions are not affected by this limiter.
    |
    */
    'rate_limit_per_minute' => (int) env('API_TOKEN_RATE_LIMIT_PER_MINUTE', 120),

    /*
    |--------------------------------------------------------------------------
    | Allowed 3rd-Party Integration Token Abilities
    |--------------------------------------------------------------------------
    |
    | Wildcard ("*") and high-privilege admin/secret management routes are
    | intentionally excluded from this allowlist so integration credentials
    | follow least-privilege access.
    |
    */
    'abilities' => [
        'invoices:read' => 'Read invoices, templates, and exports',
        'invoices:write' => 'Create, update, clone, send, export, and delete invoices',
        'estimates:read' => 'Read estimates and templates',
        'estimates:write' => 'Create, update, send, convert, and delete estimates',
        'customers:read' => 'Read customers and customer stats',
        'customers:write' => 'Create, update, and delete customers',
        'items:read' => 'Read items, units, and tax types',
        'items:write' => 'Create, update, and delete items and units',
        'payments:read' => 'Read payments and payment methods',
        'payments:write' => 'Create, update, send, and delete payments',
        'expenses:read' => 'Read expenses, receipts, and expense categories',
        'expenses:write' => 'Create, update, upload receipts, and delete expenses',
        'reports:read' => 'Read dashboard metrics and reports',
        'company:read' => 'Read current company profile, currencies, and reference data',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exact /api/v1/* Relative Path -> Required Ability Map
    |--------------------------------------------------------------------------
    |
    | Checked before the prefix route_map so multi-segment endpoints such as
    | GET /api/v1/date/formats match precisely without opening sibling paths.
    |
    */
    'exact_route_map' => [
        'date/formats' => ['read' => 'company:read'],
        'openapi.yaml' => ['read' => 'company:read'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Declarative /api/v1/* Route Segment -> Required Ability Map
    |--------------------------------------------------------------------------
    |
    | Scoped integration tokens are fail-closed: any /api/v1/* route segment
    | not listed here (such as api-tokens, users, roles, abilities, backups,
    | mail, disks, settings, company/settings, companies, search) is denied 403.
    |
    */
    'route_map' => [
        'invoices' => ['read' => 'invoices:read', 'write' => 'invoices:write'],
        'recurring-invoices' => ['read' => 'invoices:read', 'write' => 'invoices:write'],
        'estimates' => ['read' => 'estimates:read', 'write' => 'estimates:write'],
        'customers' => ['read' => 'customers:read', 'write' => 'customers:write'],
        'items' => ['read' => 'items:read', 'write' => 'items:write'],
        'units' => ['read' => 'items:read', 'write' => 'items:write'],
        'tax-types' => ['read' => 'items:read', 'write' => 'items:write'],
        'payments' => ['read' => 'payments:read', 'write' => 'payments:write'],
        'payment-methods' => ['read' => 'payments:read', 'write' => 'payments:write'],
        'expenses' => ['read' => 'expenses:read', 'write' => 'expenses:write'],
        'categories' => ['read' => 'expenses:read', 'write' => 'expenses:write'],
        'dashboard' => ['read' => 'reports:read'],
        'current-company' => ['read' => 'company:read'],
        'currencies' => ['read' => 'company:read'],
        'countries' => ['read' => 'company:read'],
        'timezones' => ['read' => 'company:read'],
        'next-number' => ['read' => 'company:read'],
        'notes' => ['read' => 'company:read'],
        'custom-fields' => ['read' => 'company:read'],
    ],
];
