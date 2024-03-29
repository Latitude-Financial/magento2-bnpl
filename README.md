# magento2-bnpl

## Description

LatitudePay & Genoapay Payment Modules for Magento 2.

Compatible with Magento 2.3.5 onwards.

Tested up to: Magento 2.4.4.

## Table of contents

* [Description](#description)
* [Changelog](#changelog)
* [How to install module](#how-to-install-module)
    + [Option 1: Composer (Recommended)](#option-1--composer--recommended-)
    + [Option 2: Manual](#option-2--manual)
* [How to upgrade module](#how-to-upgrade-module)
    + [Option 1: Composer](#option-1--composer)
    + [Option 2: Manual](#option-2--manual-1)

## Changelog

### 2.0.8
- 28 Oct 2022
- Sanitize DOB to always be yyyy-mm-dd format

<details>
<summary>Older versions</summary>

### 2.0.7
- 19 July 2022
- Compatibility with Magento 2.4.4
- Adjusted module to not clear session on checkout
- Update status key to pending_latitude_approval to avoid clash with other modules
- Address base url information source to window.BASE_URL
- Updated payment group from offline to installment
- Add log to install module

### 2.0.6
- 31 March 2022
- Ensure backward compatibility with PHP 7.4 and lower

### 2.0.5
- 24 March 2022
- Adjusted composer requirement to allow smoother installation using `composer require`
- Logo renderer adjusted to cater to sites using onepage checkout

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
</details>

## How to install module

### Option 1: Composer (Recommended)

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

### Option 2: Manual

1. Download the zip file from the [latest release on GitHub](https://github.com/Latitude-Financial/magento2-bnpl/releases)

2. Open terminal and navigate to Magento 2 root directory, for example:
```
cd /var/www/html
```
3. Copy the content of the unzipped folder and paste it into `app/code/LatitudeNew/Payment` folder in the Magento 2 root directory:
```
<Magento 2 root>
    └── app
        └── code
            └── LatitudeNew
                └── Payment
                    ├── Block
                    ├── Controller
                    ├── Cron
                    ├── Helper
                    ├── Logger
                    ├── Model
                    ├── Observer
                    ├── README.md
                    ├── Setup
                    ├── composer.json
                    ├── etc
                    ├── registration.php
                    └── view
```
4. Run:
```
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:clean
```
5. Configure the module on Magento 2 admin backend:
```
Stores > Configuration > Sales > Payment Methods
```

## How to upgrade module

### If you installed the module via Composer

Follow the same installation instructions via Composer above, except replace `composer require` with `composer update`.

### If you installed the module manually

Follow the same manual installation instructions above and replace the content of `app/code/LatitudeNew/Payment` completely.
