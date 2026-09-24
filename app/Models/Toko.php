<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Toko extends Model
{
    use HasFactory;

    protected $table = 'tokos';

    protected $fillable = [
        'name',
    ];

    public function resis()
    {
        return $this->hasMany(Resi::class, 'toko_id');
    }
}
