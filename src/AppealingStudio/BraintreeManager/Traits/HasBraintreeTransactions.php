<?php

namespace kwaight\BraintreeManager\Traits;

use kwaight\BraintreeManager\Models\BraintreeTransaction;

/**
 * Extend models that will have Braintree transactions
 */
trait HasBraintreeTransactions
{
	/**
	 * Get the wepay activity bound to the transaction
	 *
	 * @return BraintreeTransaction
	 */
	public function braintree()
	{
		return $this->hasOne('kwaight\BraintreeManager\Models\BraintreeTransaction');
	}
}
