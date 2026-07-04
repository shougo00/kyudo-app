<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Avatar extends Model
{
    protected $fillable = [
        'user_id', 'hair_id', 'face_id', 'top_id', 'bottom_id', 'shoes_id', 'accessory_id'
    ];

    public function hair() { return $this->belongsTo(Item::class,'hair_id'); }
    public function face() { return $this->belongsTo(Item::class,'face_id'); }
    public function top() { return $this->belongsTo(Item::class,'top_id'); }
    public function bottom() { return $this->belongsTo(Item::class,'bottom_id'); }
    public function shoes() { return $this->belongsTo(Item::class,'shoes_id'); }
    public function accessory() { return $this->belongsTo(Item::class,'accessory_id'); }
}
