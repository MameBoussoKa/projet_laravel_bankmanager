<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Compte extends Model
{
    use HasFactory;
    protected $fillable = ['numero_compte', 'type', 'solde', 'statut', 'client_id', 'devise', 'motifBlocage', 'metadata'];

    protected $casts = [
        'solde' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the account holder's full name
     */
    public function getTitulaireAttribute()
    {
        return $this->client ? $this->client->nom . ' ' . $this->client->prenom : null;
    }

    /**
     * Get formatted creation date
     */
    public function getDateCreationAttribute()
    {
        return $this->created_at->toISOString();
    }

    /**
     * Get formatted last modification date
     */
    public function getDerniereModificationAttribute()
    {
        return $this->updated_at->toISOString();
    }
}