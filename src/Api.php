<?php

namespace Ibracilinks\OrangeMoney;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Ibracilinks\OrangeMoney\Exceptions\OrangeMoneyException;
use Psr\Http\Message\ResponseInterface;


/**
 * Laravel Package for Orange Money Web Payment API
 *
 * @author Ibraci Links @ibracilinks
 * GitHub: https://github.com/Ibracilinks/OrangeMoney
 */
class Api
{
    /**
     * Orange Money  API Base url
     */
    const BASE_URL = "https://api.orange.com/";
    /**
     * @var ClientInterface
     */
    private $client ;
    /**
     * @var string or null
     */
    private  $auth_header;
    /**
     * @var string or null
     */
    private  $merchant_key;

     /**
     * @var string or null
     */
    private  $return_url;
     /**
     * @var string or null
     */
    private  $cancel_url;
     /**
     * @var string or null
     */
    private  $notif_url;

    private string $api_path;

    private string $currency;
    /**
     * Constructor
     * @param array $config Configuration overrides
     */
    public function __construct(array $config = [], ?ClientInterface $client = null)
    {
        $config = array_merge(config('orangemoney', []), $config);
        // Credentials: <Base64 value of UTF-8 encoded “username:password”>
        // Guzzle waits forever by default, so the built-in client sets its own timeouts.
        $this->client = $client ?? new Client([
            'base_uri'        => self::BASE_URL,
            'timeout'         => (float) ($config['timeout'] ?? 30),
            'connect_timeout' => (float) ($config['connect_timeout'] ?? 10),
        ]);
        $this->auth_header  = $config['auth_header'] ?? '';
        $this->merchant_key = $config['merchant_key'] ?? '';
        $this->return_url   = $config['return_url'] ?? '';
        $this->cancel_url   = $config['cancel_url'] ?? '';
        $this->notif_url    = $config['notif_url'] ?? '';
        $this->api_path     = trim($config['api_path'] ?? 'orange-money-webpay/dev/v1', '/');
        $this->currency     = $config['currency'] ?? 'OUV';

    }

    /**
     * Send a POST request and return the decoded JSON body
     * @param string $endpoint
     * @param array $options Guzzle request options
     * @throws OrangeMoneyException
     */
    private function post(string $endpoint, array $options): array
    {
        try {
            $response = $this->client->request('POST', $endpoint, $options);
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $status   = $response?->getStatusCode();
            $data     = $response ? $this->decode($response) : null;

            throw new OrangeMoneyException(
                $status === null ? $exception->getMessage() : $this->errorMessage($status, $data),
                $status,
                $data,
                $exception
            );
        } catch (GuzzleException $exception) {
            throw new OrangeMoneyException($exception->getMessage(), null, null, $exception);
        }

        $data = $this->decode($response);
        if ($data === null) {
            throw new OrangeMoneyException(
                'Orange Money returned an invalid JSON response.',
                $response->getStatusCode()
            );
        }

        return $data;
    }

    /**
     * Decode a JSON object body, or null when it is not one
     */
    private function decode(ResponseInterface $response): ?array
    {
        $data = json_decode((string) $response->getBody(), true);

        return is_array($data) ? $data : null;
    }

    private function errorMessage(int $status, ?array $data): string
    {
        $message = sprintf('Orange Money request failed with HTTP %d.', $status);

        foreach (['description', 'message', 'error_description'] as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                return $message.' '.$data[$key];
            }
        }

        return $message;
    }
    /**
     * Get Token
     * @throws OrangeMoneyException
     */
    public function getToken(): array
    {

        $options = [
            'headers'=> [
                'Authorization' => 'Basic '.$this->auth_header,
                'Accept'        =>'application/json'
            ],
            'form_params' => [
                 'grant_type'=>'client_credentials',
            ]
        ];

        return $this->post('oauth/v2/token',$options);
    }

    /**
     * Create a web payment. The body must at least hold the order_id and the amount.
     * @throws OrangeMoneyException
     */
    public function Payment(string $token, array $body): array
    {

        $b = [
            "merchant_key"  => $this->merchant_key,
            "currency"      => $this->currency,
            "return_url"    => $this->return_url,
            "cancel_url"    => $this->cancel_url,
            "notif_url"     => $this->notif_url,
            "lang"          => "fr"
        ];
        $b = array_merge($b,$body);
        $b = json_encode($b);

        $options = [
            'headers'=> [
                'Authorization' => 'Bearer '.$token,
                'Accept'        =>'application/json',
                'Content-Type'  =>'application/json'
            ],
            'body' => $b
        ];

        return $this->post($this->api_path.'/webpayment',$options);
    }

    /**
     * @throws OrangeMoneyException
     */
    public function checkTransactionStatus(string $token, array $data): array
    {

        $b = [
            "order_id"  => $data["order_id"],
            "amount"    => $data["amount"],
            "pay_token" => $data["pay_token"]
        ];

        $b = json_encode($b);

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json'
            ],
            'body' => $b
        ];

        return $this->post($this->api_path.'/transactionstatus', $options);
    }
}
