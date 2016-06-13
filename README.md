# Braintree Manager

A Laravel package to wrap Braintree common processes.


## Installation

Include it on your composer.json file:

```
"require": {
    "laravel/framework": "4.0.*",
    ...
    "appealing-studio/braintree-manager": "dev-master",
    ...
},

```


## Migration

In order to use the BraintreeManager class you will need to create the braintree_transactions table on the database:

```
php artisan migrate --package="appealing-studio/braintree-manager"
```


## Configuration

Publish the existing configuration template and complete it with your Braintree credentials and/or prefered configuration.

```
php artisan config:publish appealing-studio/braintree-manager
```

You can then complete it at /app/config/packages/appealing-studio/braintree-manager.
If you wish to use per environment configuration, create a folder with the environment under that folder.