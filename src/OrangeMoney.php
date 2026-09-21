<?php

namespace Ibracilinks\OrangeMoney;

use GuzzleHttp\ClientInterface;
use Ibracilinks\OrangeMoney\Exceptions\OrangeMoneyException;
use InvalidArgumentException;

class OrangeMoney
{
    private $api;

    public function __construct(array $config = [], ?ClientInterface $client = null)
    {
       $this->api = new Api($config, $client);
    }

    /**
     * Request a new access token from Orange Money. The token is also stored in the cache
     * used by webPayment() and checkTransactionStatus(), replacing the one kept there.
     *
     * @throws OrangeMoneyException
     */
    public function getAccesToken(): array
    {
        $data = $this->api->getToken();
        if (!is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new OrangeMoneyException('Orange Money did not return a valid access token.');
        }
        $this->api->cacheToken($data);

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

        $rep = $this->authenticated(fn (string $token) => $this->api->Payment($token, $data));
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

        return $this->authenticated(fn (string $token) => $this->api->checkTransactionStatus($token, $data));
    }

    /**
     * Run a request with the cached access token, or with a new one when there is none.
     *
     * @param callable(string): array $request Receives the access token
     * @throws OrangeMoneyException
     */
    private function authenticated(callable $request): array
    {
        $token = $this->api->cachedToken();

        if ($token !== null) {
            try {
                return $request($token);
            } catch (OrangeMoneyException $exception) {
                // A 401 means the request was not processed, so it is safe to send it again
                // with a new token. Any other failure is not about the cached token.
                if ($exception->getStatusCode() !== 401) {
                    throw $exception;
                }
                $this->api->forgetToken();
            }
        }

        return $request($this->getAccesToken()['access_token']);
    }

    private function generateOrderId(): string
    {
        return 'OM_'.bin2hex(random_bytes(8));
    }
}
