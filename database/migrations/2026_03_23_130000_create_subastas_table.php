<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subastas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('identificador');
            $table->string('estado');
            $table->string('tipo_subasta');
            $table->string('cuenta_expediente');
            $table->string('anuncio_boe')->nullable();
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_conclusion')->nullable();
            $table->decimal('cantidad_reclamada', 12, 2)->nullable();
            $table->decimal('valor_subasta', 12, 2)->nullable();
            $table->decimal('tasacion', 12, 2)->nullable();
            $table->decimal('puja_minima', 12, 2)->nullable();
            $table->decimal('importe_deposito', 12, 2)->nullable();
            $table->text('datos_bien_subastado')->nullable();
            $table->string('lotes')->nullable();
            $table->string('tramos_entre_pujas')->nullable();
            $table->text('observaciones')->nullable();
            $table->json('documentos_adjuntos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subastas');
    }
};
