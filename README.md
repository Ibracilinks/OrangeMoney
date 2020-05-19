A Laravel Package for Orange Money Web Payment API
====================================================


## Installation

* Use following command to install:

```bash
composer require  ibracilinks/orange-money 
```

* Add the service provider to your `$providers` array in `config/app.php` file like:

```php
Ibracilinks\OrangeMoney\Providers\OrangeMoneyServiceProvider::class,
```

* Add the alias to your `$aliases` array in `config/app.php` file like:

```php
'OrangeMoney' => Ibracilinks\OrangeMoney\Facades\OrangeMoney::class,
```

* Run the following command to publish configuration:

```bash
php artisan vendor:publish --provider "Ibracilinks\OrangeMoney\Providers\OrangeMoneyServiceProvider"
```

## Configuration

* After installation, you will need to add your orangemoney settings. Following is the code you will find in **config/orangemoney.php**, which you should update accordingly.

```php
return [
    'auth_header'  => env('OM_AUTH_HEADER', ''),
    'merchant_key' => env('OM_MERCHANT_KEY', ''),
    'return_url'   => env('OM_RETURN_URL', ''),
    'cancel_url'   => env('OM_CANCEL_URL', ''),
    'notif_url'    => env('OM_NOTIf_URL', '')
];
```
* Add this to `.env.example` and `.env`

```php
OM_AUTH_HEADER=  
OM_MERCHANT_KEY=
OM_RETURN_URL=   
OM_CANCEL_URL=   
OM_NOTIf_URL=
```

## Basic Usage

Following are some ways through which you can access the **OrangeMoney** provider:

```php
use Ibracilinks\OrangeMoney\OrangeMoney;

$payment = new OrangeMoney();

$data = [
    "merchant_key"=> '*********',
    "currency"=> "OUV",
    "order_id"=> "".time()."",
    "amount" => 5000,
    "return_url"=> 'http://www.your-website.com/callback/return',
    "cancel_url"=> 'http://www.your-website.com/callback/cancel',
    "notif_url"=>'http://www.your-website.com/callback/notif',
    "lang"=> "fr",
    "reference"=> "Your Website"
];

$payment->webPayment($data);
```

## License

The MIT License (MIT). Please see [License](https://github.com/Ibracilinks/OrangeMoney/blob/master/LICENSE) for more information.

## Contributing

Read [here](https://github.com/Ibracilinks/OrangeMoney/blob/master/CONTRIBUTING.md) for more information.
