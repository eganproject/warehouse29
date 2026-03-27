<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resi extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pesanan',
        'tanggal_pesanan',
        'tanggal_upload',
        'no_resi',
        'kurir_id',
        'uploader_id',
    ];

    protected $casts = [
        'tanggal_pesanan' => 'date',
        'tanggal_upload' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(ResiDetail::class, 'resi_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function kurir()
    {
        return $this->belongsTo(Kurir::class, 'kurir_id');
    }
}
