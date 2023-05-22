<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRentOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rent_order', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('service')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('org_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('codes')->nullable();
            $table->string('status')->nullable();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('operator')->nullable();
            $table->string('price_final')->nullable();
            $table->string('price_start')->nullable();
            $table->timestamps();

            $table->index('country_id', 'user_country_idx');
            $table->foreign('country_id', 'user_country_fk')->on('country')->references('id')->nullOnDelete();

            $table->index('user_id', 'rent_order_user_idx');
            $table->foreign('user_id', 'rent_order_user_fk')->on('user')->references('id');

            $table->index('bot_id', 'rent_order_bot_idx');
            $table->foreign('bot_id', 'rent_order_bot_fk')->on('bot')->references('id');

            $table->index('country_id', 'rent_order_country_idx');
            $table->foreign('country_id', 'rent_order_country_fk')->on('country')->references('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
