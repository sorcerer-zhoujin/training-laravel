<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerItem extends Model
{
    public $timestamps = false;
    use HasFactory;

    // 複合キー
    protected $primaryKey = ['player_id', 'item_id'];
    protected $fillable = ['player_id', 'item_id'];
    public $incrementing = false;
}
