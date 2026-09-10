<?php

/*
|--------------------------------------------------------------------------
| Tradução automática (DeepL)
|--------------------------------------------------------------------------
|
| Preenche os textos dos imóveis nos outros idiomas a partir do português. É
| um auxiliar do backoffice, nunca uma publicação automática: o botão escreve
| nos campos, e quem grava a ficha é a pessoa, depois de ler.
|
| A chave nunca vive aqui — vem do .env do servidor. Sem chave, os botões de
| tradução simplesmente não aparecem, e o resto do backoffice não dá por isso.
|
*/

return [

    // Chave da conta. As chaves terminadas em ":fx" são das contas gratuitas e
    // falam com outro endereço; isso é decidido sozinho, não é preciso configurar.
    'key' => env('DEEPL_KEY'),

    // Inglês do Reino Unido: é o que se espera de uma agência portuguesa a
    // falar para o mercado europeu. 'EN-US' também é aceite.
    'target' => env('DEEPL_TARGET', 'EN-GB'),

    // Quanto tempo esperar por cada pedido, em segundos.
    'timeout' => (int) env('DEEPL_TIMEOUT', 20),

    /*
    | Glossário: as palavras do ramo que a tradução automática engana. Sem isto,
    | o DeepL traduziu "moradia com terraço" como "house with a garden" e
    | "marquise" como "awning". Os pares abaixo carregam-se para a conta com
    | `php artisan deepl:glossario` e passam a valer em todas as traduções.
    |
    | A chave é o termo em português, em minúsculas; o valor é a tradução a impor.
    |
    | Uma ressalva medida, não suposta: o DeepL respeita o glossário quase
    | sempre, mas não sempre. "Logradouro" passou de "courtyard" a "yard" e
    | "marquise" de "awning" a "enclosed balcony", enquanto "terraço" continuou
    | a sair mal, com o glossário a dizer o contrário. É por isso que o botão do
    | backoffice escreve nos campos e pára aí: o texto é para ser lido antes de
    | ser gravado.
    */
    'glossary' => [
        'name' => env('DEEPL_GLOSSARY', 'multifuturo-imobiliario'),
        'terms' => [
            'terraço' => 'terrace',
            'terraços' => 'terraces',
            'moradia' => 'house',
            'moradias' => 'houses',
            'apartamento' => 'apartment',
            'apartamentos' => 'apartments',
            'arrecadação' => 'storage room',
            'lavandaria' => 'laundry room',
            'despensa' => 'pantry',
            'suite' => 'en-suite bedroom',
            'suíte' => 'en-suite bedroom',
            'logradouro' => 'yard',
            'anexo' => 'annex',
            'sótão' => 'attic',
            'cave' => 'basement',
            'rés do chão' => 'ground floor',
            'rés-do-chão' => 'ground floor',
            'freguesia' => 'parish',
            'concelho' => 'municipality',
            'distrito' => 'district',
            'mediação imobiliária' => 'real estate brokerage',
            'certificado energético' => 'energy certificate',
            'caderneta predial' => 'property tax register',
            'escritura' => 'deed',
            'penhora' => 'seizure',
            'usufruto' => 'usufruct',
            'vista mar' => 'sea view',
            'vista rio' => 'river view',
            'vista serra' => 'mountain view',
            'vista campo' => 'countryside view',
            'vista cidade' => 'city view',
            'vidros duplos' => 'double glazing',
            'video porteiro' => 'video intercom',
            'vídeo porteiro' => 'video intercom',
            'estores elétricos' => 'electric blinds',
            'aquecimento central' => 'central heating',
            'painéis solares' => 'solar panels',
            'recuperador de calor' => 'wood-burning stove',
            'churrasqueira' => 'barbecue',
            'marquise' => 'enclosed balcony',
            'kitchenette' => 'kitchenette',
            'mobilado' => 'furnished',
            'equipado' => 'equipped',
            'remodelado' => 'renovated',
            'para recuperar' => 'to renovate',
            'novo' => 'new',
            'usado' => 'previously owned',
            'em construção' => 'under construction',
            'prédio' => 'building',
            'condomínio' => 'condominium',
            'condomínio fechado' => 'gated community',
        ],
    ],

];
