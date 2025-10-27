<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Scopes\ActiveScope;

class Compte extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveScope);
    }

    protected $fillable = ['numero_compte', 'type', 'statut', 'client_id', 'devise', 'motifBlocage', 'metadata', 'dateFermeture'];

    protected $casts = [
        'solde' => 'decimal:2',
        'metadata' => 'array',
        'dateFermeture' => 'datetime',
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
     * Get calculated balance from transactions
     */
    public function getSoldeAttribute()
    {
        $deposits = $this->transactions()->where('type', 'depot')->sum('montant');
        $withdrawals = $this->transactions()->where('type', 'retrait')->sum('montant');
        return $deposits - $withdrawals;
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

    /**
     * Scope for filtering by account number
     */
    public function scopeNumero($query, $numero)
    {
        return $query->where('numero_compte', 'like', "%{$numero}%");
    }

    /**
     * Scope for filtering by client telephone
     */
    public function scopeClient($query, $telephone)
    {
        return $query->whereHas('client', function ($clientQuery) use ($telephone) {
            $clientQuery->where('telephone', $telephone);
        });
    }
}