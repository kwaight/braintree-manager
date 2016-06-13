<?php

namespace kwaight\BraintreeManager\Models;

use Illuminate\Database\Eloquent\Model;

class BraintreeTransaction extends Model
{
	/**
	 * All attributes guarded by default.
	 *
	 * @var array
	 */
	protected $guarded = array('*');

	/**
	 * Fillable attributes
	 * 
	 * @var array
	 */
	protected $fillable = array(
		'credit_card_token',
	);

	////////////////////////////////////////////////////////////////////
	/////////////////////////// RELATIONSHIPS //////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Get the Transaction related to the Wepay account activity
	 *
	 * @return Tasting
	 */
	public function transaction()
	{
		return $this->belongsTo(Config::get('braintree-manager::transactions-model'));
	}

}
