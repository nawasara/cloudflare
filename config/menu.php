<?php

$prefix = 'nawasara-cloudflare';

return [
    [
        'label' => 'Cloudflare',
        'icon' => 'lucide-cloud',
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
                'label' => 'Analytics',
                'icon' => 'lucide-bar-chart-3',
                'url' => url($prefix.'/analytics'),
                'permission' => 'cloudflare.analytics.view',
                'navigate' => true,
            ],
        ],
    ],
];
