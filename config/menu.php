<?php

$prefix = 'nawasara-cloudflare';

return [
    [
        'label' => 'Cloudflare',
        'icon' => 'lucide-cloud',
        'group' => 'Observability',
        'url' => '',
        'permission' => 'cloudflare.zone.view',
        'submenu' => [
            [
                'label' => 'Zones',
                'icon' => 'lucide-globe',
                'url' => url($prefix.'/zones'),
                'permission' => 'cloudflare.zone.view',
                'navigate' => true,
            ],
            [
                'label' => 'DNS Records',
                'icon' => 'lucide-list',
                'url' => url($prefix.'/dns'),
                'permission' => 'cloudflare.dns.view',
                'navigate' => true,
            ],
            [
                'label' => 'Firewall',
                'icon' => 'lucide-shield',
                'url' => url($prefix.'/firewall'),
                'permission' => 'cloudflare.waf.view',
                'navigate' => true,
            ],
            [
                'label' => 'Page Rules',
                'icon' => 'lucide-file-cog',
                'url' => url($prefix.'/page-rules'),
                'permission' => 'cloudflare.pagerule.view',
                'navigate' => true,
            ],
            [
                'label' => 'Analytics',
                'icon' => 'lucide-bar-chart-3',
                'url' => url($prefix.'/analytics'),
                'permission' => 'cloudflare.analytics.view',
                'navigate' => true,
            ],
            [
                'label' => 'Health',
                'icon' => 'lucide-activity',
                'url' => url($prefix.'/health'),
                'permission' => 'cloudflare.health.view',
                'navigate' => true,
            ],
            [
                'label' => 'Audit Log',
                'icon' => 'lucide-scroll-text',
                'url' => url($prefix.'/audit'),
                'permission' => 'cloudflare.audit.view',
                'navigate' => true,
            ],
        ],
    ],
];
