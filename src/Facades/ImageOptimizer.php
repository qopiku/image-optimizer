<?php

namespace Joshembling\ImageOptimizer\Facades;

use Illuminate\Support\Facades\Facade;

class ImageOptimizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return self::class;
    }
}
