<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('species', function (Blueprint $table) {
            $table->unsignedBigInteger('inaturalist_id')
                  ->nullable()
                  ->unique()
                  ->change();
        });
    }

    public function down()
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropUnique(['inaturalist_id']);
            $table->unsignedBigInteger('inaturalist_id')
                  ->nullable(false)
                  ->change();
        });
    }
};