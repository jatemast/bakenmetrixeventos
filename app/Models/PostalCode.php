<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostalCode extends Model
{
    protected $fillable = [
        'cp',
        'colonia',
        'municipio',
        'estado',
        'entidad_id',
        'municipio_id'
    ];
}
