<?php

/**
 * Braintree Manager
 * A wrapper class for Braintree PHP library
 *
 * It assumes you are using user id as braintree customer id on any transaction
 *
 * @author Kiefer Waight <kiefer.waight@gmail.com>
 */

namespace kwaight\BraintreeManager;

use Log;
use Config;
use Exception;

// Laravel dependencies
use Illuminate\Support\MessageBag;
use Illuminate\Foundation\Application as Container;

// Braintree classes loaded at BraintreeManagerServiceProvider
use Braintree_Customer;
use Braintree_CreditCard;
use Braintree_Transaction;
use Braintree_Configuration;
use Braintree_ClientToken;

// Braintree exception classes
use Braintree_Exception_Authentication;
use Braintree_Exception_Authorization;
use Braintree_Exception_Configuration;
use Braintree_Exception_DownForMaintenance;
use Braintree_Exception_ForgedQueryString;
use Braintree_Exception_InvalidSignature;
use Braintree_Exception_NotFound;
use Braintree_Exception_ServerError;
use Braintree_Exception_SSLCaFileNotFound;
use Braintree_Exception_SSLCertificate;
use Braintree_Exception_Unexpected;
use Braintree_Exception_UpgradeRequired;
use Braintree_Exception_ValidationsFailed;

// Braintree Transaction model
use kwaight\BraintreeManager\Models\BraintreeTransaction;

class BraintreeManager {

	/**
	 * Class variables
	 */
	protected $_result;
	protected $_customerId;
	protected $_credentials;

	// --------------------------------------------------------------------

	/**
	 * Constructor
	 * 
	 * @param Container $app
	 */
	public function __construct()
	{
		$this->_customerId = NULL;
		$this->_credentials = Config::get('braintree-manager::credentials');

		// Array used for results
		$this->_result =  array(
			'success' => FALSE,			// Boolean
			'action_result' => NULL,	// BraintreeResult
			'error_messages' => NULL,	// Will be a MessageBag instace that can be passed to view through withErrors
			'general_message' => ''		// String
		);

		$this->_setEnvironment();
	}

	// --------------------------------------------------------------------

	/**
	 * If it not exits, creates a Braintree customer
	 * along with a credit card and verifies it
	 * 
	 * TO DO: Manage duplicated credit cards
	 *
	 * @param  array $creditCardData
	 * @param  array  $formInputsMapping
	 * @return array
	 */
	public function authorizeCreditCard($creditCardData, $formInputsMapping = array())
	{
		$this->_log('Secure Credit Card Storage...');

		$customerCreationResult = $this->createSimpleCustomer($creditCardData['customerId']);

		if ($customerCreationResult == NULL OR $this->_customerCreationHasErrors($customerCreationResult))
		{
			$this->_log('Customer creation error');
			$this->_result['general_message'] = 'Credit card authorization could not be done. Please, try again';

			return $this->_result;
		}

		$cardCreationResult = $this->createCreditCard($creditCardData);

		if($cardCreationResult != NULL)
		{		
			if ($cardCreationResult->success == TRUE)
			{
				$this->_log('Credit Card created');
				$transaction = new BraintreeTransaction;
				$transaction->credit_card_token = $cardCreationResult->creditCard->token;
				$transaction->save();

				$this->_result['success'] = TRUE;
				$this->_result['action_result'] = $transaction;
			}
			else
			{
				$this->_result['error_messages'] = $this->_createErrorsMessageBag($cardCreationResult, $formInputsMapping);

				if (count($this->_result['error_messages']) > 0)
				{
			    	$this->_log('Credit card form errors');
			    	$this->_result['general_message'] = 'There was an error with the credit card info. Please, review form.';
				}
				else
			    {
					$this->_log('Credit card error');
			    	$this->_result['general_message'] = 'There was an error with the credit card.';
			    }
			}
		}

		return $this->_result;
	}

	// --------------------------------------------------------------------

	/**
	 * Create simple Braintree customer setting customerId as customerId
	 *
	 * @param  integer $customerId
	 * @return BraintreeResult
	 */
	public function createSimpleCustomer($customerId)
	{
		try
		{
			return Braintree_Customer::create(array('id' => $customerId));
		}
		catch (Exception $e)
		{
			$this->_log('Customer creation exception: ' . $e);

			return NULL;
		}		
	}

	// --------------------------------------------------------------------

	/**
	 * Store credit card on Braintree Vault for a give user
	 *
	 * @param  array $creditCardData
	 * @return BraintreeResult
	 */
	public function createCreditCard($creditCardData)
	{
		try
		{
			if (isset($creditCardData['paymentMethodNonce'])) {
				// PaymentMethodNonce provided by client application
				// (for example iOS/Android app) can be used instead
				// of cc details
				return Braintree_CreditCard::create(array(
					'customerId' => $creditCardData['customerId'],
					'paymentMethodNonce' => $creditCardData['paymentMethodNonce'],
					'options' => array(
						'verifyCard' => true,
		      		)
				));
			} else {
				return Braintree_CreditCard::create(array(
				    'customerId' => $creditCardData['customerId'],
			        'number' => $creditCardData['number'],
			        'cardholderName' => $creditCardData['card_name'],
			        'expirationDate' => $creditCardData['expiration_month'] . '/' . substr($creditCardData['expiration_year'], -2),
			        'cvv' => $creditCardData['cvv'],
					'options' => array(
						'verifyCard' => true,
		      			//'failOnDuplicatePaymentMethod' => true
		      		)
				));
			}
		}
		catch (Exception $e)
		{
			$this->_log('Credit card creation exception: ' . $e);

			return NULL;
		}
	}

	// --------------------------------------------------------------------

	public function chargeCreditCard($braintreeTransaction, $amount)
	{
		$this->_log('Charging credit card...');

		$transaction = NULL;
		$this->_result['general_message'] = 'Transaction could not be completed';

		try
		{	
			// Create transaction on Braintree
			$transaction = Braintree_Transaction::sale(array(
			  'amount' => $amount,
			  'paymentMethodToken' => $braintreeTransaction->credit_card_token
			));

			$this->_log($transaction);
			$this->_result['action_result'] = $transaction;
		}
		catch (Exception $e)
		{
			$this->_log('Transaction exception: ' . $e);

			return $this->_result;
		}

		$_isDuplicated = (isset($this->_result['action_result']->transaction->gatewayRejectionReason) &&
				$this->_result['action_result']->transaction->gatewayRejectionReason == 'duplicate');

		// Aprove on success and on duplicate detected (multiple button clicks?)
		if ($this->_result['action_result']->success == TRUE OR $_isDuplicated)
		{
			$this->_result['success'] = TRUE;
			$this->_result['general_message'] = 'Transaction completed succesfully';			

			// If not is a duplicated transaction, let's store it
			if (!$_isDuplicated)
			{
				$braintreeTransaction->transaction_token = $this->_result['action_result']->transaction->id;

				// Currently we do not manage user credit cards,
				// one transactioin = one credit card
				// so let's remove the credit card if transaction was completed
				$braintreeTransaction->credit_card_token = NULL;

				// Create transaction on database
				$braintreeTransaction->save();
			}
		}

		$this->_log('Done');

		return $this->_result;
	}

	// --------------------------------------------------------------------

	public function deleteCreditCard($braintreeTransaction)
	{
		$this->_log('Deleting credit card...');

		$deleteCreditCard = NULL;
		$this->_result['general_message'] = 'Credit card could not be deleted';

		// Delete from database
		$braintreeTransaction->delete();

		// Delete form Braintree
		try
		{
			$deleteCreditCard = Braintree_CreditCard::delete($braintreeTransaction->credit_card_token);
			$this->_result['action_result'] = $deleteCreditCard;
		}
		catch (Exception $e)
		{
			if (!$e instanceof Braintree_Exception_NotFound)
			{
				$this->_log('Delete credit card exception: ' . $e);

				return $this->_result;
			}
		}

		$this->_result['success'] = TRUE;
		$this->_result['general_message'] = 'Credit card deleted succesfully';

		$this->_log('Done');

		return $this->_result;
	}

	// --------------------------------------------------------------------
	 
	/**
	 * Generate client_token that can be used by mobile application to 
	 * to generate credit card token in mobile app
	 *
	 * More information:
	 * https://developers.braintreepayments.com/ios+php/sdk/overview/generate-client-token
	 * 
	 * @param  integer $customerId
	 * @return string
	 */
	public function generateClientToken($customerId)
	{
		$customerCreationResult = $this->createSimpleCustomer($customerId);

		if ($customerCreationResult == null or $this->_customerCreationHasErrors($customerCreationResult))
		{
			$this->_log('Customer creation error');
			return null;
		}
		try {
			
			return Braintree_ClientToken::generate(array(
			    "customerId" => $customerId
			));

		} catch (Exception $e) {
			$this->_log('Client token creation exception: ' . $e);
			return null;
		}
		
	}

	// --------------------------------------------------------------------

	/**
	 * Check if customer creation has errors different from duplicate user
	 * 
	 * @param  BraintreeResult $result
	 * @return boolean
	 */
	private function _customerCreationHasErrors($result)
	{
		if ($result->success)
		{
			return FALSE;
		}

		foreach ($result->errors->forKey('customer')->onAttribute('id') as $error)
		{
			// Does the user exist on Braintree Vault?
			if ($error->code == 91609)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * Create errors MessageBag instace
	 * To be used with form view as withErrors param
	 * 
	 * @param  BraintreeResults $result
	 * @param  array $formInputsMapping
	 * @return MessageBag
	 */
	private function _createErrorsMessageBag($result, $formInputsMapping)
	{
		$messageBag = new MessageBag;
		
	    foreach (($result->errors->deepAll()) as $error)
	    {
			$input = FALSE;

			if (preg_match('/CVV/', $error->message))	// CVV error
			{
				$input = 'cvv';
			}
			elseif (preg_match('/Credit card number/', $error->message))
			{
				$input = 'number';
			}

			if ($input != FALSE)
			{
				// Form input has another name?
				$input = isset($formInputsMapping[$input]) ? $formInputsMapping[$input] : $input;

				$messageBag->add($input,$error->message);
			}
	    }

		return $messageBag;
	}

	// --------------------------------------------------------------------

	/**
	 * Configure Braintree connection 
	 *
	 * @return  void
	 */
	private function _setEnvironment()
	{
		$this->_log('Setting Braintree credentials...');

		Braintree_Configuration::environment($this->_credentials['environment']);
		Braintree_Configuration::merchantId($this->_credentials['merchantId']);
		Braintree_Configuration::publicKey($this->_credentials['publicKey']);
		Braintree_Configuration::privateKey($this->_credentials['privateKey']);

		$this->_log('Done');
	}

	// --------------------------------------------------------------------

	/**
	 * Write Braintree log message
	 * 
	 * @param  string $message
	 * @return void
	 */
	private function _log($message)
	{
		Log::info('Braintree: ' . $message);
	}
}