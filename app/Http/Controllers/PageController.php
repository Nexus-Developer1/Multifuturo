<?php

namespace App\Http\Controllers;

use App\Enums\BusinessType;
use App\Models\Property;
use App\Support\Locales;
use App\Support\PropertyCache;
use App\Support\Zones;
use Illuminate\Contracts\View\View;

/**
 * Páginas server-rendered. As institucionais/legais são esqueletos com o
 * layout final até a Fase 6 lhes dar conteúdo.
 */
class PageController extends Controller
{
    public function home(): View
    {
        /*
         * Destaques: os marcados no backoffice primeiro; se forem menos do que
         * a fila leva, completa-se com os mais recentes, para a página inicial
         * nunca aparecer com buracos. Nunca mais do que MAX_FEATURED — é o que
         * a fila mostra, e marcar mais no backoffice já não é permitido.
         */
        $featured = PropertyCache::remember('home:featured', function () {
            $max = Property::MAX_FEATURED;
            $featured = Property::query()->active()->featured()->orderByRaw('crm_updated_at IS NULL, crm_updated_at DESC')->limit($max)->get();

            if ($featured->count() < $max) {
                $featured = $featured->concat(
                    Property::query()->active()->whereKeyNot($featured->modelKeys())->orderByRaw('crm_updated_at IS NULL, crm_updated_at DESC')->limit($max - $featured->count())->get()
                );
            }

            return $featured;
        });

        /*
         * Fotografias da abertura, a alternar. Vêm de config/agency.php e a
         * ordem é sorteada em cada visita, para quem volta não ver sempre a
         * mesma primeira imagem. Sem lista configurada, usam-se as capas dos
         * destaques — a carteira real.
         */
        $heroImages = collect(config('agency.hero_images', []))
            ->filter()
            ->shuffle()
            ->when(
                blank(config('agency.hero_images')),
                fn ($lista) => collect([config('agency.hero_image')])->concat($featured->pluck('cover_photo.url'))->filter()->unique()->take(5)
            )
            ->values()
            ->all();

        $heroImage = $heroImages[0] ?? null;

        return view('pages.home', [
            'jsonLd' => $this->siteJsonLd(),
            'featured' => $featured,
            'heroImage' => $heroImage,
            'heroImages' => $heroImages,
            'cities' => Zones::cities(),
            // Números da carteira publicada — contam no ecrã, e são verdade.
            'stats' => PropertyCache::remember('home:stats', fn () => [
                'properties' => Property::query()->active()->count(),
                'cities' => Property::query()->active()->whereNotNull('city')->distinct()->count('city'),
                'localities' => Property::query()->active()->whereNotNull('locality')->distinct()->count('locality'),
            ]),
        ]);
    }

    /**
     * Dados estruturados do site, na página inicial.
     *
     * O Google tira daqui o nome que mostra por cima do endereço nos resultados
     * (WebSite.name — sem isto mostra só "multifuturo.pt") e liga o site à
     * agência, com o logótipo, os contactos e as redes sociais. O url do WebSite
     * é a raiz do domínio, como o Google pede, mesmo que a raiz reencaminhe
     * para /pt.
     *
     * @return array<string, mixed>
     */
    private function siteJsonLd(): array
    {
        $raiz = rtrim((string) config('app.url'), '/').'/';

        $agencia = array_filter([
            '@type' => 'RealEstateAgent',
            '@id' => $raiz.'#agencia',
            'name' => config('agency.name'),
            'url' => $raiz,
            'logo' => asset('images/marca/favicon-512.png'),
            'image' => asset('images/marca/favicon-512.png'),
            'telephone' => config('agency.whatsapp') ?: config('agency.phone'),
            'email' => config('agency.email'),
            'address' => config('agency.address'),
            'geo' => config('agency.lat') && config('agency.lon')
                ? ['@type' => 'GeoCoordinates', 'latitude' => (float) config('agency.lat'), 'longitude' => (float) config('agency.lon')]
                : null,
            'sameAs' => array_values(array_filter(config('agency.social', []))) ?: null,
        ]);

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $raiz.'#site',
                    'name' => config('agency.name'),
                    'alternateName' => 'Multifuturo',
                    'url' => $raiz,
                    'inLanguage' => Locales::htmlLang(),
                    'publisher' => ['@id' => $raiz.'#agencia'],
                ],
                $agencia,
            ],
        ];
    }

    public function buy(): View
    {
        return view('pages.listing', [
            'businessType' => BusinessType::Sale,
            'title' => __('ui.listing.buy_title'),
            'description' => __('ui.listing.buy_description'),
        ]);
    }

    public function rent(): View
    {
        return view('pages.listing', [
            'businessType' => BusinessType::Rent,
            'title' => __('ui.listing.rent_title'),
            'description' => __('ui.listing.rent_description'),
        ]);
    }

    /**
     * A agência tem o mesmo conteúdo das páginas legais (lang/…/legal.php) mas
     * molde próprio: é uma página de apresentação, não um documento.
     */
    public function about(): View
    {
        return $this->legal('about', 'pages.about');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function privacy(): View
    {
        return $this->legal('privacy');
    }

    public function terms(): View
    {
        return $this->legal('terms');
    }

    public function cookies(): View
    {
        return $this->legal('cookies');
    }

    /**
     * Documento legal/institucional a partir de lang/pt/legal.php, com os dados
     * da agência substituídos nos textos.
     */
    private function legal(string $key, string $view = 'pages.legal'): View
    {
        $agency = config('agency');

        return view($view, [
            'key' => $key,
            'replacements' => [
                'name' => $agency['name'],
                // Cada dado em falta produz a frase certa, não um buraco ("AMI n.º ", "com sede em .").
                'ami' => filled($agency['ami']) ? 'n.º '.$agency['ami'] : '(número por atribuir)',
                'address' => (string) $agency['address'],
                'seat' => filled($agency['address']) ? ', com sede em '.$agency['address'] : '',
                'email' => (string) $agency['email'],
                'phone' => (string) $agency['phone'],
                'contact_line' => implode(' · ', array_filter([
                    filled($agency['phone']) ? 'Telefone: '.$agency['phone'] : null,
                    filled($agency['email']) ? 'Email: '.$agency['email'] : null,
                ])),
                'version' => $agency['privacy_policy_version'],
                'consent_cookie' => config('consent.cookie'),
            ],
        ]);
    }
}
