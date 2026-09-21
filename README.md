# Orange Money for Laravel

A Laravel package for the Orange Money Web Payment API.

## Requirements

- PHP 8.3 or later (PHP 8.x)
- Laravel 13
- Orange Money Web Payment API credentials and a merchant key

## Installation

```bash
composer require ibracilinks/orangemoney
```

Laravel automatically discovers the service provider and the `OrangeMoney` facade.

Publish the configuration:

```bash
php artisan vendor:publish --tag=orangemoney-config
```

## Configuration

Add your settings to `.env`:

```dotenv
OM_AUTH_HEADER=
OM_MERCHANT_KEY=
OM_RETURN_URL=https://your-website.com/payment/return
OM_CANCEL_URL=https://your-website.com/payment/cancel
OM_NOTIF_URL=https://your-website.com/payment/notify
OM_API_PATH=orange-money-webpay/dev/v1
OM_CURRENCY=OUV
OM_TIMEOUT=30
OM_CONNECT_TIMEOUT=10
```

`OM_AUTH_HEADER` is the Base64-encoded `client_id:client_secret` value, without the `Basic ` prefix.

The defaults use Orange's development endpoint and test currency. Both payment creation and transaction status use the configured `OM_API_PATH`.

`OM_TIMEOUT` and `OM_CONNECT_TIMEOUT` are in seconds (defaults: 30 and 10). They apply to the built-in HTTP client, so a slow Orange Money response cannot block a PHP worker forever.

After changing settings in an application that caches configuration, run `php artisan config:cache`.

## Going to production in Mali

Once you have created and validated your test application with the Orange Money WebPay Dev API and received approval from the Orange teams to go to production, follow the steps in Orange's [Getting started document](https://developer.orange.com/apis/om-webpay-dev/getting-started) to create your production application.

The differences from the development setup are:

- Create a **new application** to use the Orange Money WebPay ML API.
- Use merchant and subscriber accounts for **Mali production**.
- Use the production payment URL: [https://api.orange.com/orange-money-webpay/ml/v1/webpayment](https://api.orange.com/orange-money-webpay/ml/v1/webpayment).

Configure the package to use the Mali production API:

```dotenv
OM_API_PATH=orange-money-webpay/ml/v1
```

Update `OM_AUTH_HEADER` with the new application's Base64-encoded `client_id:client_secret`, set `OM_MERCHANT_KEY` to your Mali production merchant key, and set `OM_CURRENCY` to the currency enabled for your production merchant account. Set your callback URLs to the corresponding routes in your production application.

If your application caches configuration, run `php artisan config:cache` after updating these settings.

## Usage

Use the facade:

```php
use Ibracilinks\OrangeMoney\Exceptions\OrangeMoneyException;
use Ibracilinks\OrangeMoney\Facades\OrangeMoney;

try {
    $payment = OrangeMoney::webPayment([
        'order_id' => 'order-123', // optional, generated when omitted
        'amount' => 5000,
        'lang' => 'fr',
        'reference' => 'Your Website',
    ]);
} catch (OrangeMoneyException $e) {
    report($e);

    return back()->withErrors('The payment could not be started.');
}

// Save order_id, pay_token and notif_token with your order before redirecting.
return redirect()->away($payment['payment_url']);
```

The merchant key, currency, and callback URLs come from configuration. You can override them in the payment data. Your application must implement the callback routes.

The `amount` is required and must be greater than zero, otherwise an `InvalidArgumentException` is thrown before any request is sent. If you do not pass an `order_id`, a random one (`OM_` followed by 16 hexadecimal characters) is generated. The `order_id` that was sent is always part of the returned array, so you can store it and check the transaction later.

Check a transaction using the original order ID, amount, and payment token:

```php
$status = OrangeMoney::checkTransactionStatus('order-123', 5000, $payToken);
```

You can also resolve the service through dependency injection or construct it directly:

```php
use Ibracilinks\OrangeMoney\OrangeMoney;

$service = app(OrangeMoney::class);
$service = new OrangeMoney(['currency' => 'OUV']);
```

A custom Guzzle client can be passed as the second constructor argument. The timeout settings above then no longer apply: configure them on your own client.

### Errors

Successful calls return the decoded response as an array. Any failure throws `Ibracilinks\OrangeMoney\Exceptions\OrangeMoneyException`, which extends `RuntimeException`. This covers authentication failures, HTTP errors from Orange Money (4xx and 5xx), network failures and timeouts, and responses that are not valid JSON. The exception gives you:

- `getMessage()`: a readable message, including the HTTP status and Orange's error description when there is one.
- `getStatusCode()`: the HTTP status, or `null` when no response was received (network failure, timeout).
- `getResponseData()`: the decoded JSON body of the error response, or `null`.
- `getPrevious()`: the underlying Guzzle exception, if any.

Verify the payment status with Orange Money before fulfilling an order; a browser return alone is not proof of payment.

## Upgrading

This version requires Laravel 13 and PHP 8.3+. Upgrade the consuming application before installing it. Remove manual provider and alias entries if you previously added them; package discovery handles registration.

The existing `webPayment`, `checkTransactionStatus`, and `getAccesToken` methods remain available. The legacy `OM_NOTIf_URL` environment variable remains a fallback, but new installations should use `OM_NOTIF_URL`. Constructor configuration overrides are now applied.

Breaking changes to handle when upgrading:

- `webPayment()` and `checkTransactionStatus()` no longer return an error string when a request fails: they throw `OrangeMoneyException`. Replace any `is_string($result)` or `is_array($result)` check with a `try`/`catch`. Both methods now always return an array.
- `webPayment()` throws an `InvalidArgumentException` when `amount` is missing or not greater than zero. Before, a missing amount was sent to Orange Money as `0`.
- The generated `order_id` now looks like `OM_` followed by 16 hexadecimal characters, and it is returned in the result.
- The `Api` class methods (`getToken()`, `Payment()`, `checkTransactionStatus()`) now return decoded arrays and throw `OrangeMoneyException`, instead of returning a response object or an error string.
- The built-in HTTP client now has a 30 second timeout and a 10 second connection timeout. It used to wait indefinitely.

If you previously published `config/orangemoney.php`, merge the new `api_path`, `currency`, `timeout`, `connect_timeout`, and `notif_url` settings from `src/config/orangemoney.php` into your copy.

## Development

```bash
composer install
composer test
```

The tests boot Laravel using Orchestra Testbench and mock Orange API responses; no credentials or real payments are needed. CI runs on PHP 8.3, 8.4, and 8.5 with the latest dependencies, and on PHP 8.3 with the lowest ones.

## License

[MIT](LICENSE)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).
