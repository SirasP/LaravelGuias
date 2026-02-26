<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryConfigService
{
    private string $table = 'gmail_inventory_settings';

    public function get(string $key, ?string $default = null): ?string
    {
        if (!$this->existsTable()) {
            return $default;
        }

        $value = DB::connection('fuelcontrol')
            ->table($this->table)
            ->where('key', $key)
            ->value('value');

        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    public function set(string $key, ?string $value): void
    {
        if (!$this->existsTable()) {
            return;
        }

        DB::connection('fuelcontrol')
            ->table($this->table)
            ->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
    }

    public function getLowStockEmails(): array
    {
        $raw = $this->get('low_stock_emails', 's.lopez.epple@gmail.com') ?? '';
        $parts = preg_split('/[,;\s]+/', $raw) ?: [];
        $emails = array_values(array_filter(array_map(
            static fn ($v) => filter_var(trim((string) $v), FILTER_VALIDATE_EMAIL) ?: null,
            $parts
        )));

        if (empty($emails)) {
            return ['s.lopez.epple@gmail.com'];
        }

        return $emails;
    }

    public function getDtePfxPassword(): ?string
    {
        $pwd = trim((string) ($this->get('dte_signature_pfx_password', '') ?? ''));
        return $pwd === '' ? null : $pwd;
    }

    private function existsTable(): bool
    {
        return Schema::connection('fuelcontrol')->hasTable($this->table);
    }
}

