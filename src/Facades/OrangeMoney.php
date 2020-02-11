<?php

namespace Ibracilinks\OrangeMoney\Facades;

use Illuminate\Support\Facades\Facade;

class OrangeMoney extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'OrangeMoney';
    }
}
