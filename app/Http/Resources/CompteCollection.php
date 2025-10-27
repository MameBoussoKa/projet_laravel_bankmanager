<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CompteCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'pagination' => [
                'currentPage' => $this->resource->currentPage(),
                'totalPages' => $this->resource->lastPage(),
                'totalItems' => $this->resource->total(),
                'itemsPerPage' => $this->resource->perPage(),
                'hasNext' => $this->resource->hasMorePages(),
                'hasPrevious' => $this->resource->currentPage() > 1,
            ],
            'links' => [
                'self' => $request->url() . '?' . $request->getQueryString(),
                'next' => $this->resource->nextPageUrl(),
                'first' => $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => 1])),
                'last' => $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $this->resource->lastPage()])),
            ],
        ];
    }
}
