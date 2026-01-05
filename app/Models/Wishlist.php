<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wishlist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'product_id',
        'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
    ];

    /**
     * Get the user that owns the wishlist.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that is wishlisted.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
