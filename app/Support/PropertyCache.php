<?php

namespace App\Support;

use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Cache das leituras de imóveis (listagens, filtros, destaques, zonas, sitemap),
 * invalidada de uma vez sempre que o backoffice grava (flush()).
 *
 * Com Redis (servidor em contentores) usa-se uma tag. No alojamento cPanel a
 * cache é a base de dados, que não tem tags: aí as chaves levam uma versão, e o
 * flush() muda de versão — as cópias antigas deixam de ser lidas e expiram
 * sozinhas pelo TTL. Antes disto, sem tags, o flush() não fazia nada, e um
 * imóvel posto em "Reservado" continuava com o preço na listagem durante uma hora.
 */
final class PropertyCache
{
    public const TAG = 'properties';

    /** TTL por defeito: 1 h — na prática o backoffice limpa a cache a cada gravação. */
    public const TTL = 3600;

    /** Onde fica a versão das chaves, nas caches sem tags. */
    private const VERSION_KEY = 'props:versao';

    public static function store(): TaggedCache|Repository
    {
        $repo = Cache::store();

        return $repo->supportsTags() ? $repo->tags([self::TAG]) : $repo;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, \Closure $callback, ?int $ttl = null)
    {
        return self::store()->remember(self::prefix().$key, $ttl ?? self::TTL, $callback);
    }

    public static function flush(): void
    {
        $repo = Cache::store();

        if ($repo->supportsTags()) {
            $repo->tags([self::TAG])->flush();

            return;
        }

        $repo->forever(self::VERSION_KEY, self::version($repo) + 1);
    }

    private static function prefix(): string
    {
        $repo = Cache::store();

        return $repo->supportsTags() ? 'props:' : 'props:v'.self::version($repo).':';
    }

    private static function version(Repository $repo): int
    {
        return (int) $repo->rememberForever(self::VERSION_KEY, fn () => 1);
    }
}
