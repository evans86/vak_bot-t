<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DeleteColumnInUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropForeign('user_country_fk');
            $table->dropIndex('user_country_idx');
            $table->dropColumn('country_id');
            $table->dropColumn('service_id');
            $table->unique(['telegram_id']);
            $table->index('telegram_id', 'idx_user_telegram_id');
        });
        Schema::table('order', function (Blueprint $table) {
            $table->dropColumn('service_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user', function (Blueprint $table) {
            //
        });
    }
}
