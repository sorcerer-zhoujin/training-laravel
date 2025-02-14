<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlayerItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('player_items', function (Blueprint $table) {
            //$table->id();
            
            // 複合キー
            $table->primary(['player_id', 'item_id']);

            $table->unsignedBigInteger('player_id')->comment("プレイヤーID");
            $table->unsignedBigInteger('item_id')->comment("アイテムID");
            $table->integer('count')->comment("アイテム数");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('player_items');
    }
}
