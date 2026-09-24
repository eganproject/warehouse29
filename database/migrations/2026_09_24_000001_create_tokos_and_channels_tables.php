<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tokos')) {
            Schema::create('tokos', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('channels')) {
            Schema::create('channels', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
        Schema::dropIfExists('tokos');
    }
};
