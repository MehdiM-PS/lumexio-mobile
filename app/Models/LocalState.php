<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalState extends Model
{
    protected $table = 'local_state';

    protected $fillable = ['token', 'shop_id'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    public function clearToken(): void
    {
        $this->update(['token' => null, 'shop_id' => null]);
    }
}
