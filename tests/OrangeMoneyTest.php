<?php

namespace Ibracilinks\OrangeMoney\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Ibracilinks\OrangeMoney\Api;
use Ibracilinks\OrangeMoney\Exceptions\OrangeMoneyException;
use Ibracilinks\OrangeMoney\Facades\OrangeMoney as OrangeMoneyFacade;
use Ibracilinks\OrangeMoney\OrangeMoney;
use Ibracilinks\OrangeMoney\Providers\OrangeMoneyServiceProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;

class OrangeMoneyTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [OrangeMoneyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('orangemoney.merchant_key', 'merchant-key');
        $app['config']->set('orangemoney.auth_header', 'encoded-credentials');
        $app['config']->set('orangemoney.return_url', 'https://example.com/return');
        $app['config']->set('orangemoney.cancel_url', 'https://example.com/cancel');
        $app['config']->set('orangemoney.notif_url', 'https://example.com/notify');
    }

    public function test_provider_resolves_the_facade_and_class_to_the_same_singleton(): void
    {
        $service = $this->app->make(OrangeMoney::class);

        $this->assertInstanceOf(OrangeMoney::class, $service);
        $this->assertSame($service, $this->app->make('OrangeMoney'));
        $this->assertSame($service, OrangeMoneyFacade::getFacadeRoot());
        $this->assertSame($service, $this->app->make(OrangeMoney::class));
    }

    public function test_configuration_is_merged_and_publishable(): void
    {
        $this->assertSame('merchant-key', config('orangemoney.merchant_key'));
        $this->assertSame('orange-money-webpay/dev/v1', config('orangemoney.api_path'));
        $this->assertSame('OUV', config('orangemoney.currency'));
        $this->assertSame(30, config('orangemoney.timeout'));
        $this->assertSame(10, config('orangemoney.connect_timeout'));
        $this->assertSame(3600, config('orangemoney.token_ttl'));

        $paths = ServiceProvider::pathsToPublish(OrangeMoneyServiceProvider::class, 'orangemoney-config');
        $this->assertCount(1, $paths);
        $this->assertSame(config_path('orangemoney.php'), array_values($paths)[0]);
        $this->assertFileExists(array_keys($paths)[0]);
    }

    public function test_web_payment_authenticates_and_uses_configured_mali_endpoint(): void
    {
        $history = [];
        $result = ['status' => 201, 'message' => 'OK', 'pay_token' => 'payment-token',
            'payment_url' => 'https://example.com/pay', 'notif_token' => 'notification-token'];
        $client = $this->mockClient([
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(201, [], json_encode($result)),
        ], $history);
        $payment = new OrangeMoney([
            'api_path' => '/orange-money-webpay/ml/v1/',
            'currency' => 'XOF',
            'merchant_key' => 'override-key',
        ], $client);

        $this->assertSame($result + ['order_id' => 'order-123'],
            $payment->webPayment(['order_id' => 'order-123', 'amount' => 5000]));
        $this->assertCount(2, $history);
        $auth = $history[0]['request'];
        $this->assertSame('POST', $auth->getMethod());
        $this->assertSame('https://api.orange.com/oauth/v2/token', (string) $auth->getUri());
        $this->assertSame('Basic encoded-credentials', $auth->getHeaderLine('Authorization'));
        $this->assertSame('grant_type=client_credentials', (string) $auth->getBody());
        $request = $history[1]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/orange-money-webpay/ml/v1/webpayment', $request->getUri()->getPath());
        $this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $body = json_decode((string) $request->getBody(), true);
        ksort($body);
        $this->assertSame([
            'amount' => 5000, 'cancel_url' => 'https://example.com/cancel', 'currency' => 'XOF',
            'lang' => 'fr', 'merchant_key' => 'override-key', 'notif_url' => 'https://example.com/notify',
            'order_id' => 'order-123', 'return_url' => 'https://example.com/return',
        ], $body);
    }

    public function test_transaction_status_uses_the_configured_endpoint(): void
    {
        $history = [];
        $result = ['status' => 'SUCCESS', 'order_id' => 'order-123', 'txnid' => 'transaction-123'];
        $client = $this->mockClient([
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(201, [], json_encode($result)),
        ], $history);
        $payment = new OrangeMoney(['api_path' => 'orange-money-webpay/ml/v1'], $client);

        $this->assertSame($result, $payment->checkTransactionStatus('order-123', 5000, 'payment-token'));
        $request = $history[1]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/orange-money-webpay/ml/v1/transactionstatus', $request->getUri()->getPath());
        $this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        $this->assertSame(['order_id' => 'order-123', 'amount' => 5000, 'pay_token' => 'payment-token'],
            json_decode((string) $request->getBody(), true));
    }

    public function test_default_sandbox_and_per_payment_overrides_are_preserved(): void
    {
        $history = [];
        $client = $this->mockClient([
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(201, [], '{"status":201}'),
        ], $history);
        (new OrangeMoney([], $client))->webPayment(['amount' => 100, 'currency' => 'XOF']);

        $this->assertSame('/orange-money-webpay/dev/v1/webpayment', $history[1]['request']->getUri()->getPath());
        $body = json_decode((string) $history[1]['request']->getBody(), true);
        $this->assertSame('XOF', $body['currency']);
        $this->assertNotEmpty($body['order_id']);
    }

    public function test_order_id_is_generated_and_returned_when_none_is_given(): void
    {
        $history = [];
        $client = $this->mockClient([
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(201, [], '{"status":201,"pay_token":"payment-token"}'),
        ], $history);

        $result = (new OrangeMoney([], $client))->webPayment(['amount' => 100]);

        $body = json_decode((string) $history[1]['request']->getBody(), true);
        $this->assertMatchesRegularExpression('/^OM_[0-9a-f]{16}$/', $body['order_id']);
        $this->assertSame($body['order_id'], $result['order_id']);
        $this->assertSame('payment-token', $result['pay_token']);
    }

    public function test_generated_order_ids_are_unique(): void
    {
        $history = [];
        $client = $this->mockClient([
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(201, [], '{"status":201}'),
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(201, [], '{"status":201}'),
        ], $history);
        $payment = new OrangeMoney([], $client);

        $first = $payment->webPayment(['amount' => 100]);
        $second = $payment->webPayment(['amount' => 100]);

        $this->assertNotSame($first['order_id'], $second['order_id']);
    }

    #[DataProvider('invalidPayments')]
    public function test_invalid_amounts_are_rejected_before_any_request(array $data): void
    {
        $history = [];
        $client = $this->mockClient([], $history);

        try {
            (new OrangeMoney([], $client))->webPayment($data);
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $history);
        }
    }

    public static function invalidPayments(): array
    {
        return [
            'missing amount' => [['order_id' => 'order-123']],
            'null amount' => [['amount' => null]],
            'zero amount' => [['amount' => 0]],
            'negative amount' => [['amount' => -5]],
            'non numeric amount' => [['amount' => 'abc']],
        ];
    }

    public function test_authentication_failure_throws_an_exception_with_the_status_and_response(): void
    {
        $history = [];
        $client = $this->mockClient([new Response(401, [], '{"message":"Unauthorized"}')], $history);

        try {
            (new OrangeMoney([], $client))->webPayment(['amount' => 100]);
            $this->fail('Expected an OrangeMoneyException.');
        } catch (OrangeMoneyException $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception);
            $this->assertSame(401, $exception->getStatusCode());
            $this->assertSame(['message' => 'Unauthorized'], $exception->getResponseData());
            $this->assertSame('Orange Money request failed with HTTP 401. Unauthorized', $exception->getMessage());
        }
    }

    public function test_payment_rejection_throws_with_the_status_and_decoded_body(): void
    {
        $history = [];
        $client = $this->mockClient([
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(400, [], '{"code":40,"message":"Invalid merchant key"}'),
        ], $history);

        try {
            (new OrangeMoney([], $client))->webPayment(['amount' => 100]);
            $this->fail('Expected an OrangeMoneyException.');
        } catch (OrangeMoneyException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame(['code' => 40, 'message' => 'Invalid merchant key'], $exception->getResponseData());
            $this->assertSame('Orange Money request failed with HTTP 400. Invalid merchant key',
                $exception->getMessage());
        }
    }

    public function test_transaction_status_failure_with_a_non_json_body_throws_without_data(): void
    {
        $history = [];
        $client = $this->mockClient([
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(502, [], 'Bad Gateway'),
        ], $history);

        try {
            (new OrangeMoney([], $client))->checkTransactionStatus('order-123', 5000, 'payment-token');
            $this->fail('Expected an OrangeMoneyException.');
        } catch (OrangeMoneyException $exception) {
            $this->assertSame(502, $exception->getStatusCode());
            $this->assertNull($exception->getResponseData());
            $this->assertSame('Orange Money request failed with HTTP 502.', $exception->getMessage());
        }
    }

    public function test_network_failure_throws_without_a_status(): void
    {
        $history = [];
        $failure = new ConnectException('Connection timed out', new Request('POST', 'oauth/v2/token'));
        $client = $this->mockClient([$failure], $history);

        try {
            (new OrangeMoney([], $client))->getAccesToken();
            $this->fail('Expected an OrangeMoneyException.');
        } catch (OrangeMoneyException $exception) {
            $this->assertNull($exception->getStatusCode());
            $this->assertNull($exception->getResponseData());
            $this->assertSame($failure, $exception->getPrevious());
            $this->assertSame('Connection timed out', $exception->getMessage());
        }
    }

    public function test_a_successful_response_that_is_not_json_is_rejected(): void
    {
        $history = [];
        $client = $this->mockClient([
            new Response(200, [], '{"access_token":"test-token"}'),
            new Response(201, [], 'not json'),
        ], $history);

        $this->expectException(OrangeMoneyException::class);
        $this->expectExceptionMessage('invalid JSON response');
        (new OrangeMoney([], $client))->webPayment(['amount' => 100]);
    }

    public function test_missing_access_token_is_rejected(): void
    {
        $history = [];
        $client = $this->mockClient([new Response(200, [], '{}')], $history);

        $this->expectException(OrangeMoneyException::class);
        $this->expectExceptionMessage('valid access token');
        (new OrangeMoney([], $client))->getAccesToken();
    }

    public function test_access_tokens_are_cached_and_shared_between_service_instances(): void
    {
        $history = [];
        $first = new OrangeMoney([], $this->mockClient([
            $this->tokenResponse('cached-token'),
            $this->paymentResponse(),
        ], $history));
        $second = new OrangeMoney([], $this->mockClient([$this->paymentResponse()], $history));

        $first->webPayment(['amount' => 100]);
        $second->checkTransactionStatus('order-123', 100, 'payment-token');

        $this->assertCount(3, $history);
        $this->assertSame('/oauth/v2/token', $history[0]['request']->getUri()->getPath());
        $this->assertSame('Bearer cached-token', $history[1]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer cached-token', $history[2]['request']->getHeaderLine('Authorization'));
    }

    #[DataProvider('uncacheableTokens')]
    public function test_tokens_that_cannot_be_cached_are_requested_every_time(array $config, string $tokenBody): void
    {
        $history = [];
        $client = $this->mockClient([
            new Response(200, [], $tokenBody), $this->paymentResponse(),
            new Response(200, [], $tokenBody), $this->paymentResponse(),
        ], $history);
        $payment = new OrangeMoney($config, $client);

        $payment->webPayment(['amount' => 100]);
        $payment->webPayment(['amount' => 100]);

        $this->assertCount(4, $history);
    }

    public static function uncacheableTokens(): array
    {
        return [
            'no lifetime' => [[], '{"access_token":"token"}'],
            'lifetime shorter than the safety margin' => [[], '{"access_token":"token","expires_in":"30"}'],
            'caching disabled' => [['token_ttl' => 0], '{"access_token":"token","expires_in":"3600"}'],
        ];
    }

    #[DataProvider('tokenLifetimes')]
    public function test_cached_tokens_expire_when_they_should(int $expiresIn, int $tokenTtl): void
    {
        $history = [];
        $client = $this->mockClient([
            $this->tokenResponse('first-token', $expiresIn), $this->paymentResponse(),
            $this->paymentResponse(),
            $this->tokenResponse('second-token', $expiresIn), $this->paymentResponse(),
        ], $history);
        $payment = new OrangeMoney(['token_ttl' => $tokenTtl], $client);

        // The token is kept for 100 seconds in both cases.
        $payment->webPayment(['amount' => 100]);
        $this->travel(50)->seconds();
        $payment->webPayment(['amount' => 100]);
        $this->travel(51)->seconds();
        $payment->webPayment(['amount' => 100]);

        $this->assertCount(5, $history);
        $this->assertSame('Bearer first-token', $history[2]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer second-token', $history[4]['request']->getHeaderLine('Authorization'));
    }

    public static function tokenLifetimes(): array
    {
        return [
            'lifetime minus the safety margin' => [160, 3600],
            'configured token_ttl' => [3600, 100],
        ];
    }

    public function test_a_rejected_cached_token_is_replaced_and_the_request_is_sent_again_once(): void
    {
        $history = [];
        $client = $this->mockClient([
            $this->tokenResponse('old-token'), $this->paymentResponse(),
            new Response(401, [], '{"message":"Expired token"}'),
            $this->tokenResponse('new-token'), $this->paymentResponse(),
            $this->paymentResponse(),
        ], $history);
        $payment = new OrangeMoney([], $client);

        $payment->webPayment(['amount' => 100]);
        $payment->webPayment(['amount' => 100]);
        $payment->webPayment(['amount' => 100]);

        $this->assertCount(6, $history);
        $this->assertSame('Bearer old-token', $history[2]['request']->getHeaderLine('Authorization'));
        $this->assertSame('/oauth/v2/token', $history[3]['request']->getUri()->getPath());
        $this->assertSame('Bearer new-token', $history[4]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer new-token', $history[5]['request']->getHeaderLine('Authorization'));
    }

    public function test_a_401_with_a_token_that_was_just_requested_is_not_retried(): void
    {
        $history = [];
        $client = $this->mockClient([
            $this->tokenResponse(),
            new Response(401, [], '{"message":"Expired token"}'),
        ], $history);

        try {
            (new OrangeMoney([], $client))->webPayment(['amount' => 100]);
            $this->fail('Expected an OrangeMoneyException.');
        } catch (OrangeMoneyException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
            $this->assertCount(2, $history);
        }
    }

    public function test_other_failures_do_not_discard_the_cached_token(): void
    {
        $history = [];
        $client = $this->mockClient([
            $this->tokenResponse('cached-token'), $this->paymentResponse(),
            new Response(500, [], 'Internal Server Error'),
            $this->paymentResponse(),
        ], $history);
        $payment = new OrangeMoney([], $client);

        $payment->webPayment(['amount' => 100]);
        try {
            $payment->webPayment(['amount' => 100]);
            $this->fail('Expected an OrangeMoneyException.');
        } catch (OrangeMoneyException $exception) {
            $this->assertSame(500, $exception->getStatusCode());
        }
        $payment->webPayment(['amount' => 100]);

        $this->assertCount(4, $history);
        $this->assertSame('Bearer cached-token', $history[3]['request']->getHeaderLine('Authorization'));
    }

    public function test_cached_tokens_are_not_shared_between_credentials(): void
    {
        $history = [];
        $first = new OrangeMoney(['auth_header' => 'first-credentials'], $this->mockClient([
            $this->tokenResponse('first-token'), $this->paymentResponse(),
        ], $history));
        $second = new OrangeMoney(['auth_header' => 'second-credentials'], $this->mockClient([
            $this->tokenResponse('second-token'), $this->paymentResponse(),
        ], $history));

        $first->webPayment(['amount' => 100]);
        $second->webPayment(['amount' => 100]);

        $this->assertCount(4, $history);
        $this->assertSame('Basic second-credentials', $history[2]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer second-token', $history[3]['request']->getHeaderLine('Authorization'));
    }

    public function test_get_access_token_always_asks_orange_money_and_refreshes_the_cache(): void
    {
        $history = [];
        $client = $this->mockClient([
            $this->tokenResponse('first-token'), $this->paymentResponse(),
            $this->tokenResponse('second-token'), $this->paymentResponse(),
        ], $history);
        $payment = new OrangeMoney([], $client);

        $payment->webPayment(['amount' => 100]);
        $token = $payment->getAccesToken();
        $payment->webPayment(['amount' => 100]);

        $this->assertSame('second-token', $token['access_token']);
        $this->assertCount(4, $history);
        $this->assertSame('Bearer second-token', $history[3]['request']->getHeaderLine('Authorization'));
    }

    public function test_the_legacy_notif_url_environment_variable_is_still_used_but_does_not_take_precedence(): void
    {
        $config = fn () => require __DIR__.'/../src/config/orangemoney.php';

        try {
            putenv('OM_NOTIf_URL=https://legacy.example.com/notify');
            $this->assertSame('https://legacy.example.com/notify', $config()['notif_url']);

            putenv('OM_NOTIF_URL=https://example.com/notify');
            $this->assertSame('https://example.com/notify', $config()['notif_url']);
        } finally {
            putenv('OM_NOTIf_URL');
            putenv('OM_NOTIF_URL');
        }
    }

    public function test_the_default_client_has_configurable_timeouts(): void
    {
        $this->assertSame([30.0, 10.0], $this->timeoutsOf(new Api()));
        $this->assertSame([5.0, 2.0], $this->timeoutsOf(new Api(['timeout' => '5', 'connect_timeout' => 2])));
    }

    private function timeoutsOf(Api $api): array
    {
        $client = (new ReflectionProperty(Api::class, 'client'))->getValue($api);

        return [$client->getConfig('timeout'), $client->getConfig('connect_timeout')];
    }

    private function tokenResponse(string $token = 'test-token', int $lifetime = 3600): Response
    {
        return new Response(200, [], json_encode(['access_token' => $token, 'expires_in' => (string) $lifetime]));
    }

    private function paymentResponse(): Response
    {
        return new Response(201, [], '{"status":201}');
    }

    private function mockClient(array $responses, array &$history): Client
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));

        return new Client(['base_uri' => 'https://api.orange.com/', 'handler' => $handler]);
    }
}
