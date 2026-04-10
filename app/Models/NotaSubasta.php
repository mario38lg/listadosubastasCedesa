<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaSubasta extends Model
{
    use HasFactory;

    protected $table = 'notas_subastas';

    protected $fillable = [
        'subasta_id',
        'user_id',
        'contenido',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function subasta(): BelongsTo
    {
        return $this->belongsTo(Subasta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
