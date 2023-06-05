<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MollieOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mollie_orders', function (Blueprint $table) {
            $table->bigIncrements('table_id');
            $table->string('mollie_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('createdAt')->nullable();
            $table->string('shipping_id')->nullable();
            $table->string('given_name')->nullable();
            $table->string('email')->nullable();
            $table->string('mollie_config_id')->nullable();
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
        Schema::dropIfExists('mollie_orders');
    }
}
