<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subastas', function (Blueprint $table): void {
            if (!Schema::hasColumn('subastas', 'clasificacion')) {
                $table->string('clasificacion')->default('nueva');
            }

            if (!Schema::hasColumn('subastas', 'fecha_conclusion')) {
                $table->timestamp('fecha_conclusion')->nullable();
            }

            if (!Schema::hasColumn('subastas', 'valor_tasacion')) {
                $table->decimal('valor_tasacion', 15, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subastas', function (Blueprint $table): void {
            if (Schema::hasColumn('subastas', 'clasificacion')) {
                $table->dropColumn('clasificacion');
            }

            if (Schema::hasColumn('subastas', 'fecha_conclusion')) {
                $table->dropColumn('fecha_conclusion');
            }

            if (Schema::hasColumn('subastas', 'valor_tasacion')) {
                $table->dropColumn('valor_tasacion');
            }
        });
    }
};
