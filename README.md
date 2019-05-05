Ibraci Links - Orange Money Laravel Package
==============================================


## Installation

* Use following command to install:

```bash
composer require ibracilinks/orangemoney
```

* Add the service provider to your `$providers` array in `config/app.php` file like:

```php
Ibracilinks\OrangeMoney\Providers\OrangeMoneyServiceProvider::class
```

* Add the alias to your `$aliases` array in `config/app.php` file like:

```php
'OrangeMoney' => Ibracilinks\OrangeMoney\Facades\Oran::class
```

* Run the following command to publish configuration:

```bash
php artisan vendor:publish --provider "Ibracilinks\OrangeMoney\Providers\OrangeMoneyServiceProvider"
```
