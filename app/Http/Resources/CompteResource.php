<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numeroCompte' => $this->numero_compte,
            'titulaire' => $this->titulaire,
            'type' => $this->type,
            'solde' => $this->solde,
            'devise' => $this->devise ?? 'FCFA',
            'dateCreation' => $this->date_creation,
            'statut' => $this->statut,
            'motifBlocage' => $this->motifBlocage,
            'metadata' => [
                'derniereModification' => $this->derniere_modification,
                'version' => $this->metadata['version'] ?? 1,
            ],
        ];
    }
}
