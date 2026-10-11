<?php

/**
 * Pessoas+ - contatos, informativos e textos legais da Ouvidoria (BM.4-6).
 *
 * PESSOASPLUS_BUILD_BM4_6
 *
 * Guardado em glpi_configs (contexto plugin:pessoasplus), editavel pelo RH
 * em front/ouvidoria_config.php sem esperar o modulo Configuracao.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use Config;

final class OuvidoriaConfig
{
    public const CONTEXTO = 'plugin:pessoasplus';

    /** chave => [rotulo, padrao, tipo] */
    public const CAMPOS = [
        'ouv_email'        => ['E-mail do RH', 'rh@[EMPRESA].com.br', 'texto'],
        'ouv_telefone'     => ['Telefone e WhatsApp', '[(41) 0000-0000] · seg. a sex., 8h às 18h', 'texto'],
        'ouv_presencial'   => ['Atendimento presencial', 'Sala do RH, sede · [horário]', 'texto'],
        'ouv_responsavel'  => ['Responsável pela Ouvidoria', '[nome]', 'texto'],
        'ouv_externos'     => ['Canais externos', '[Ministério Público do Trabalho, sindicato], se a empresa decidir listar.', 'texto'],
        'ouv_politica_url' => ['Endereço da política interna (Codex+)', '', 'url'],
        'ouv_informativos' => ['Informativos (rótulo | endereço, um por linha)', "Política da Ouvidoria e garantia de não retaliação | \nCódigo de conduta | \nPerguntas frequentes sobre o canal anônimo | ", 'linhas'],
    ];

    /** Cartoes "Seu respaldo na lei" (texto a validar com o juridico, D-74). */
    public const LEIS = [
        ['titulo' => 'Canal de denúncias com anonimato', 'texto' => 'Empresas com CIPA devem manter um canal para receber e acompanhar denúncias de assédio sexual e outras formas de violência, com o anonimato de quem denuncia garantido.', 'fonte' => 'Lei 14.457/2022, art. 23'],
        ['titulo' => 'Seus dados só para apurar', 'texto' => 'O que você informa é usado apenas para apurar o relato, pelo mínimo de pessoas necessário, e não para outra finalidade. O envio anônimo não trata dados pessoais do remetente.', 'fonte' => 'Lei 13.709/2018 (LGPD), arts. 6º e 7º'],
        ['titulo' => 'Proteção contra retaliação', 'texto' => 'Programas de integridade preveem canal de denúncias aberto e a proteção de quem relata de boa-fé.', 'fonte' => 'Lei 12.846/2013 e Decreto 11.129/2022'],
        ['titulo' => 'Igualdade e não discriminação', 'texto' => 'Discriminação de salário ou de tratamento por sexo, raça, origem ou outra condição pode ser relatada por este canal.', 'fonte' => 'Constituição, art. 5º e art. 7º, XXX; Lei 14.611/2023'],
    ];

    /**
     * @return array<string, string>
     */
    public static function ler(): array
    {
        $valores = Config::getConfigurationValues(self::CONTEXTO, array_keys(self::CAMPOS));
        $saida = [];
        foreach (self::CAMPOS as $chave => $def) {
            $saida[$chave] = (string) ($valores[$chave] ?? $def[1]);
        }

        return $saida;
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function gravar(array $post): void
    {
        $valores = [];
        foreach (self::CAMPOS as $chave => $def) {
            $v = trim((string) ($post[$chave] ?? ''));
            if ($def[2] === 'url' && $v !== '' && !preg_match('#^(https?://|/)#i', $v)) {
                $v = '';
            }
            $valores[$chave] = mb_substr($v, 0, 2000);
        }
        Config::setConfigurationValues(self::CONTEXTO, $valores);
    }

    /**
     * Informativos como lista [{rotulo, url}], so os que tem endereco valido.
     *
     * @return array<int, array{rotulo: string, url: string}>
     */
    public static function informativos(string $linhas): array
    {
        $saida = [];
        foreach (preg_split('/\r?\n/', $linhas) ?: [] as $linha) {
            $partes = array_map('trim', explode('|', $linha, 2));
            $rotulo = $partes[0] ?? '';
            $url    = $partes[1] ?? '';
            if ($rotulo === '' || !preg_match('#^(https?://|/)#i', $url)) {
                continue;
            }
            if (str_starts_with($url, '/')) {
                $url = \Html::getPrefixedUrl($url);
            }
            $saida[] = ['rotulo' => $rotulo, 'url' => $url];
        }

        return $saida;
    }

    /**
     * Pronto para a tela.
     *
     * @return array<string, mixed>
     */
    public static function paraTela(): array
    {
        $c = self::ler();
        $politica = $c['ouv_politica_url'];
        if (str_starts_with($politica, '/')) {
            $politica = \Html::getPrefixedUrl($politica);
        }

        return [
            'contatos'     => [
                ['rotulo' => 'E-mail', 'valor' => $c['ouv_email']],
                ['rotulo' => 'Telefone e WhatsApp', 'valor' => $c['ouv_telefone']],
                ['rotulo' => 'Atendimento presencial', 'valor' => $c['ouv_presencial']],
                ['rotulo' => 'Responsável pela Ouvidoria', 'valor' => $c['ouv_responsavel']],
            ],
            'externos'     => $c['ouv_externos'],
            'politica_url' => $politica,
            'informativos' => self::informativos($c['ouv_informativos']),
            'leis'         => self::LEIS,
            'placeholder'  => str_contains(implode(' ', $c), '['),
        ];
    }
}
