<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'color'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($tag) {
            $tag->slug = $tag->slug ?? Str::slug($tag->name);
        });
    }

    /**
     * Get all personas that are assigned this tag.
     */
    public function personas(): MorphToMany
    {
        return $this->morphedByMany(Persona::class, 'taggable');
    }

    /**
     * Get all events that are assigned this tag.
     */
    public function events(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'taggable');
    }
}
