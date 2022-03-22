# magento2-bnpl

## Description

LatitudePay & Genoapay Payment Modules for Magento 2.

Tested up to: Magento 2.4.3-p1.

## Changelog

### 2.0.4
- 22 March 2022
- Show module version on configuration page

### 2.0.3
- 22 March 2022
- Fix path for packagist

### 2.0.2
- 18 March 2022
- Replace Zend Logger removed in Magento 2.4.3 with a custom logger
- Improve logging information

### 2.0.1
- 17 March 2022
- Change callback redirect from cart page to checkout page

### 2.0.0
- 16 March 2022
- Initial release for Magento 2 plugin rebuild

## How to install module

1. Open terminal and navigate to Magento 2 root directory, for example:
```
cd /var/www/html
```
2. Run:
```
composer require latitude-bnpl/payment
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:clean
```
3. Configure the module on Magento 2 admin backend:
```
Stores > Configuration > Sales > Payment Methods
```

## How to upgrade module

1. Open terminal and navigate to Magento 2 root directory, for example:
```
cd /var/www/html
```
2. Run:
```
composer update latitude-bnpl/payment
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:clean
```
