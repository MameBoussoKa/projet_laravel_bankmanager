<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    protected $fillable = ['type', 'montant', 'date', 'statut', 'compte_id'];

    protected $casts = [
        'montant' => 'decimal:2',
        'date' => 'datetime',
    ];

    public function compte()
    {
        return $this->belongsTo(Compte::class);
    }
}