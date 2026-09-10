<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * As características vêm do CRM como uma lista corrida de texto livre
 * ("cozinha equipada", "terraço", "proximidade: escolas"…). Numa ficha com
 * trinta delas seguidas ninguém encontra nada, por isso a página arruma-as em
 * grupos: o que é do prédio, o que é de portas adentro, o que é lá fora e o que
 * é da zona.
 *
 * A arrumação é por palavra: o CRM não classifica nada e a lista é aberta — uma
 * característica nova cai no grupo geral em vez de desaparecer.
 *
 * O texto guardado é sempre o português: é ele que os filtros procuram e o que
 * fica na base de dados. Para mostrar noutro idioma há um dicionário em
 * lang/{idioma}/features.php, com a característica em chave-de-endereço
 * ("vista mar" → "vista-mar"). Uma característica que ainda não esteja lá sai
 * em português, que é melhor do que sair vazia — e o comando
 * `caracteristicas:traduzir` trata das que faltarem.
 */
final class Features
{
    /** Ordem em que os grupos aparecem na ficha. */
    private const GROUPS = ['general', 'interior', 'exterior', 'surroundings'];

    /**
     * O que decide o grupo, pela ordem em que se experimenta. "Vista" e
     * "localização" só contam no princípio da frase: "vista mar" é da zona,
     * "coisa nunca vista" não é nada.
     */
    private const PATTERNS = [
        'surroundings' => '/^vista\b|^localiza|proximidade|dist[âa]ncia|transportes/iu',
        'exterior' => '/jardim|terra[çc]o|varanda|p[áa]tio|piscina|barbecue|churrasqueira|garagem|estacionamento|\bfuro\b|po[çc]o\b|logradouro|exterior/iu',
        'interior' => '/cozinha|kitchenette|roupeiro|closet|despensa|lavandaria|arrecada|lareira|aquecimento|ar condicionado|estores|vidros|m[áa]quina|mobilad|equipad|su[íi]te|quarto|sala|banho|s[óo]t[ãa]o|\bcave\b|ch[ãa]o/iu',
    ];

    /**
     * @param  array<int, string>  $features
     * @return array<string, array<int, string>> grupo => características, sem grupos vazios
     */
    public static function grouped(array $features): array
    {
        $grupos = array_fill_keys(self::GROUPS, []);

        foreach ($features as $feature) {
            $feature = trim((string) $feature);

            if ($feature !== '') {
                $grupos[self::group($feature)][] = $feature;
            }
        }

        return array_filter($grupos);
    }

    /**
     * Como a característica se lê no idioma que está a ser servido. Sem
     * tradução, devolve o português tal como está guardado.
     */
    public static function label(string $feature, ?string $locale = null): string
    {
        $feature = trim($feature);
        $chave = 'features.'.self::key($feature);
        $traduzido = trans($chave, [], $locale);

        // O trans() devolve a própria chave quando não encontra nada.
        return is_string($traduzido) && $traduzido !== $chave ? $traduzido : $feature;
    }

    /** Chave de dicionário de uma característica: "Proximidade: praia" → "proximidade-praia". */
    public static function key(string $feature): string
    {
        return Str::slug($feature) ?: 'sem-nome';
    }

    private static function group(string $feature): string
    {
        foreach (self::PATTERNS as $grupo => $padrao) {
            if (preg_match($padrao, $feature) === 1) {
                return $grupo;
            }
        }

        return 'general';
    }
}
