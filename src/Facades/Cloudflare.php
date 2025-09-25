<?php

namespace Nawasara\Cloudflare\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Nawasara\Cloudflare\Cloudflare
 */
class Cloudflare extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Nawasara\Cloudflare\Cloudflare::class;
    }
}
