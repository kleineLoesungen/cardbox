<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Schlüssel-Wert-Speicher für Werte, die zur Laufzeit entstehen — etwa das
 * Ergebnis der Update-Prüfung (siehe App\Services\UpdateCheck).
 *
 * @property string $key
 * @property string $value
 * @property Carbon $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** Es gibt nur `updated_at`; wann ein Wert zuerst entstand, interessiert nicht. */
    const CREATED_AT = null;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /** Gespeicherter Wert, oder `null`. */
    public static function read(string $key): ?string
    {
        return self::find($key)?->value;
    }

    public static function write(string $key, string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
