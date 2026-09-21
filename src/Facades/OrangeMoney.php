<?php

namespace Ibracilinks\OrangeMoney\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getAccesToken()
 * @method static array webPayment(array $data)
 * @method static array checkTransactionStatus(string $orderId, int|float|string $amount, string $pay_token)
 *
 * @see \Ibracilinks\OrangeMoney\OrangeMoney
 */
class OrangeMoney extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'OrangeMoney';
    }
}
