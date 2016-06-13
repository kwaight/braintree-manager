<?php

/**
 * Braintree Manager configuration
 *
 * @author  Kiefer Waight <kiefer.waight@gmail.com>
 */
return array(

	// Braintree credentials
	'credentials' => array(
		'environment' => 'sandbox',
		'merchantId' => 'your_merchant_id',
		'publicKey' => 'your_public_key',
		'privateKey' => 'your_private_key',
		'cseKey' => 'your_cse_key'
	),

	// Model to which Braintree transactions is attached
	'transactions-model' => '',

);