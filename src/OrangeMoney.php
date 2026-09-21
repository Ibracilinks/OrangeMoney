<?php

namespace Ibracilinks\OrangeMoney;

use GuzzleHttp\ClientInterface;
use Ibracilinks\OrangeMoney\Exceptions\OrangeMoneyException;
use InvalidArgumentException;

class OrangeMoney
{
    private $api;
    /**
     * @var string or null
     */
    private  $token;

    public function __construct(array $config = [], ?ClientInterface $client = null)
    {
       $this->api = new Api($config, $client);
    }

    /**
     * @throws OrangeMoneyException
     */
    public function getAccesToken(): array
    {
        $data = $this->api->getToken();
        if (!is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new OrangeMoneyException('Orange Money did not return a valid access token.');
        }
        $this->token=$data["access_token"];

        return $data;
    }

    /**
     * Create a web payment. A random order_id is generated when none is given;
     * the order_id used is always part of the returned array.
     *
     * @throws InvalidArgumentException when the amount is missing or not greater than zero
     * @throws OrangeMoneyException when Orange Money cannot be reached or rejects the request
     */
    public function webPayment(array $data): array
    {
        if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new InvalidArgumentException('The payment amount must be a number greater than zero.');
        }
        if (!isset($data['order_id']) || $data['order_id'] === '') {
            $data['order_id'] = $this->generateOrderId();
        }

        $this->getAccesToken();
        $rep = $this->api->Payment($this->token, $data);
        $rep['order_id'] ??= $data['order_id'];

        return $rep;
    }

    /**
     * @throws OrangeMoneyException
     */
    public function checkTransactionStatus($orderId, $amount, $pay_token): array
    {

        $data = [
            "order_id"  => $orderId,
            "amount"    => $amount,
            "pay_token" => $pay_token
        ];

        $this->getAccesToken();

        return $this->api->checkTransactionStatus($this->token, $data);
    }

    private function generateOrderId(): string
    {
        return 'OM_'.bin2hex(random_bytes(8));
    }
}
