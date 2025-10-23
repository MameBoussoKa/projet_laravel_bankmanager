<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Client extends Model
{
    use HasApiTokens, HasFactory;

    protected $fillable = ['nom', 'prenom', 'email', 'telephone', 'adresse', 'password', 'nci'];

    protected $hidden = ['password'];

    public function comptes()
    {
        return $this->hasMany(Compte::class);
    }
}