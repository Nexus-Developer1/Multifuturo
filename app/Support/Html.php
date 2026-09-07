<?php

namespace App\Support;

/**
 * HTML escrito no backoffice (texto "Website (HTML)" do imóvel) antes de ir
 * para a página pública. O editor já produz HTML limpo, mas o que sai para o
 * site nunca deve depender disso: fica só a formatação de texto, sem scripts,
 * sem atributos de evento e sem ligações javascript:.
 */
final class Html
{
    private const ALLOWED = '<p><br><strong><b><em><i><u><s><a><ul><ol><li><h2><h3><h4><blockquote>';

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Conteúdo de <script>/<style> vai fora inteiro, não só as etiquetas.
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, self::ALLOWED);

        // Todas as etiquetas perdem os atributos; <a> fica só com href seguro.
        $html = preg_replace_callback('#<a\b([^>]*)>#i', function (array $m): string {
            if (preg_match('#href\s*=\s*(["\'])(.*?)\1#i', $m[1], $h) === 1) {
                $href = trim(html_entity_decode($h[2]));
                if (preg_match('#^(https?://|mailto:|tel:|/)#i', $href) === 1) {
                    $seguro = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

                    return '<a href="'.$seguro.'" rel="noopener">';
                }
            }

            return '<a>';
        }, $html) ?? '';

        return preg_replace('#<(?!a\b|/)(\w+)\b[^>]*>#i', '<$1>', $html) ?? '';
    }

    /**
     * Texto simples (o campo "Descrição", escrito à mão ou vindo do CRM) em
     * parágrafos. Vem quase sempre com uma quebra de linha entre parágrafos e
     * nenhuma linha em branco: com <br> saía tudo colado, um bloco só. Cada
     * linha com texto passa a ser um parágrafo; as linhas que começam por
     * traço ou ponto viram uma lista.
     */
    public static function paragraphs(?string $texto): string
    {
        $linhas = preg_split('/\R/u', trim((string) $texto)) ?: [];
        $linhas = array_values(array_filter(array_map('trim', $linhas), fn (string $l): bool => $l !== ''));

        $html = '';
        $lista = false;

        foreach ($linhas as $linha) {
            $item = preg_match('/^[-–—•*]\s+(.+)$/u', $linha, $m) === 1;

            if ($item && ! $lista) {
                $html .= '<ul>';
                $lista = true;
            } elseif (! $item && $lista) {
                $html .= '</ul>';
                $lista = false;
            }

            $html .= $item ? '<li>'.e($m[1]).'</li>' : '<p>'.e($linha).'</p>';
        }

        return $lista ? $html.'</ul>' : $html;
    }
}
