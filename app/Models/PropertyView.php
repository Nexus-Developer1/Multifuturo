<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contagem diária de visualizações de uma ficha de imóvel.
 *
 * Privacidade: guarda-se apenas um contador por imóvel e por dia — sem IP,
 * sem identificador de visitante, sem cookies. Não é rastreio de pessoas, é
 * uma métrica agregada; por isso não depende do consentimento de cookies.
 *
 * @property int $views
 * @property Carbon $viewed_on
 */
class PropertyView extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['viewed_on' => 'date'];
    }

    /** Regista mais uma visualização de hoje — uma query, sem condições de corrida. */
    public static function record(int $propertyId): void
    {
        static::query()->upsert(
            [[
                'property_id' => $propertyId,
                'viewed_on' => now()->toDateString(),
                'views' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['property_id', 'viewed_on'],
            // No "ON DUPLICATE KEY UPDATE" do MySQL, "views" é a linha que já lá
            // está; o updated_at vai como valor, sem o EXCLUDED do PostgreSQL.
            ['views' => DB::raw('views + 1'), 'updated_at' => now()]
        );
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
