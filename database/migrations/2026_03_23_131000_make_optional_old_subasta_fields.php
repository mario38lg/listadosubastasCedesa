<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subastas', function (Blueprint $table): void {
            $table->string('tipo_subasta')->nullable()->change();
            $table->string('cuenta_expediente')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subastas', function (Blueprint $table): void {
            $table->string('tipo_subasta')->nullable(false)->change();
            $table->string('cuenta_expediente')->nullable(false)->change();
        });
    }
};
