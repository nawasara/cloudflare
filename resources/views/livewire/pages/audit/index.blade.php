<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Cloudflare Audit Log</x-nawasara-ui::page.title>

        <p class="text-sm text-gray-500 dark:text-neutral-400 mb-6">
            Audit log dari API Cloudflare (account-level). Untuk audit log aktivitas internal Nawasara,
            buka <a href="{{ url('nawasara-audit/activity-log') }}" wire:navigate
                class="text-blue-600 dark:text-blue-400 hover:underline font-medium">Audit / Activity Log</a>.
        </p>

        <livewire:nawasara-cloudflare.audit.section.cloudflare-logs />
    </x-nawasara-ui::page.container>
</div>
