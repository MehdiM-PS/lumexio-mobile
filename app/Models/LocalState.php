<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class LocalState extends Model
{
    protected $table = 'local_state';

    protected $fillable = ['token', 'shop_id', 'active_shop_name', 'unread_alert_count', 'push_token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
        ];
    }

    protected function unreadAlertCount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?? 0,
        );
    }

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    public function clearToken(): void
    {
        $this->update([
            'token' => null,
            'shop_id' => null,
            'active_shop_name' => null,
            'unread_alert_count' => 0,
            'push_token' => null,
        ]);
    }
}
