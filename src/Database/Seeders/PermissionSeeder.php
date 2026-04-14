<?php

namespace Nawasara\Cloudflare\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'cloudflare.zone.view',
            'cloudflare.dns.view',
            'cloudflare.dns.create',
            'cloudflare.dns.edit',
            'cloudflare.dns.delete',
            'cloudflare.waf.view',
            'cloudflare.waf.create',
            'cloudflare.waf.edit',
            'cloudflare.waf.delete',
            'cloudflare.ssl.view',
            'cloudflare.ssl.manage',
            'cloudflare.analytics.view',
            'cloudflare.cache.purge',
            'cloudflare.ddos.view',
            'cloudflare.ddos.manage',
            'cloudflare.health.view',
            'cloudflare.audit.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::where('name', 'developer')->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
