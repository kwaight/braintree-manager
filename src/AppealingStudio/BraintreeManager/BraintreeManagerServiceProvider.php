<?php

namespace kwaight\BraintreeManager;

use Log;
use Illuminate\Support\ServiceProvider;

// Require Braintree library
require_once base_path() . '/vendor/braintree/braintree_php/lib/Braintree.php';

class BraintreeManagerServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('kwaight/braintree-manager');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('braintree', function($app)
		{
			return new \kwaight\BraintreeManager\BraintreeManager;
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}
}