<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subasta extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'identificador',
        'estado',
        'tipo_subasta',
        'cuenta_expediente',
        'anuncio_boe',
        'fecha_inicio',
        'fecha_conclusion',
        'clasificacion',
        'cantidad_reclamada',
        'valor_subasta',
        'valor_tasacion',
        'tasacion',
        'puja_minima',
        'importe_deposito',
        'datos_bien_subastado',
        'lotes',
        'tramos_entre_pujas',
        'observaciones',
        'documentos_adjuntos',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_conclusion' => 'datetime',
            'valor_tasacion' => 'decimal:2',
            'documentos_adjuntos' => 'array',
        ];
    }

    public function notas()
    {
        return $this->hasMany(NotaSubasta::class);
    }
}
