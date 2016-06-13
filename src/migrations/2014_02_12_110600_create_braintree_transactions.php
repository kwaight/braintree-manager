<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBraintreeTransactions extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('braintree_transactions', function (Blueprint $table)
		{
			$table->increments('id');

				// App transaction_id field with FK to app transaction id field on main transactions table
				$table->integer('transaction_id');

				// Authorized credit card
				$table->string('credit_card_token')->nullable()->default(NULL);

				// Transaction token for related transaction id
				$table->string('transaction_token')->nullable()->default(NULL);

		    $table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('braintree_transactions');
	}

}