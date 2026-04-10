<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar las observaciones antiguas a la tabla de notas
        // Si la subasta tiene observaciones, créemos una nota histórica
        $subastas = DB::table('subastas')
            ->whereNotNull('observaciones')
            ->where('observaciones', '<>', '')
            ->get();

        foreach ($subastas as $subasta) {
            DB::table('notas_subastas')->insertOrIgnore([
                'subasta_id' => $subasta->id,
                'user_id' => $subasta->user_id,
                'contenido' => $subasta->observaciones,
                'created_at' => $subasta->updated_at,
                'updated_at' => $subasta->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Al revertir, simplemente eliminamos todas las notas migradas
        // (Las nuevas notas creadas manualmente se perderán)
    }
};
