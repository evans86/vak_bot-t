<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimeCodesToRentOrder extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rent_order', function (Blueprint $table) {
            $table->string('codes_date')->nullable()->after('codes');
            $table->string('codes_id')->nullable()->after('codes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rent_order', function (Blueprint $table) {
            //
        });
    }
}
