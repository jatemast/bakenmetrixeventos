<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $primaryKey = 'tag_id';
    
    protected $fillable = [
        'nombre',
        'slug',
        'categoria'
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->slug = $m->slug ?? Str::slug($m->nombre));
    }

    public function personas()
    {
        return $this->belongsToMany(Persona::class, 'persona_tags', 'tag_id', 'persona_id');
    }
}
