<?php

return [
    // Only reads Odoo. Enabling requires an explicit operator decision after local QA.
    'odoo_sync_enabled' => (bool) env('DTE_PURCHASE_REFERENCES_ODOO_SYNC', false),
];
