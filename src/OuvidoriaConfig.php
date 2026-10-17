<?php

/**
 * Pessoas+ - contatos, informativos e textos legais da Ouvidoria (BM.4-6).
 *
 * PESSOASPLUS_BUILD_BM4_6
 * PESSOASPLUS_BUILD_BM4_10 (D-91: endereco curto das paginas anonimas)
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
        // BM.4-10 (D-91): base do endereco curto das paginas anonimas, servida por proxy do Apache (vhost). Vazio = desligado.
        'ouv_canal_url'    => ['Endereço curto das páginas anônimas (ex.: https://empresa.com.br/canal; exige o trecho do vhost, D-91)', '', 'url'],
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
     * Base do endereco curto (BM.4-10, D-91), sem barra final; '' quando desligado.
     * Caminho relativo ("/canal") vira absoluto com a url_base do GLPI, porque o QR precisa de endereco completo.
     */
    public static function canalBase(): string
    {
        global $CFG_GLPI;

        $v = trim((string) (Config::getConfigurationValues(self::CONTEXTO, ['ouv_canal_url'])['ouv_canal_url'] ?? ''));
        if ($v === '' || !preg_match('#^(https?://|/)#i', $v)) {
            return '';
        }
        if (str_starts_with($v, '/')) {
            $v = rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/') . $v;
        }

        return rtrim($v, '/');
    }

    /**
     * A requisicao atual chegou pelo endereco curto? O proxy do vhost acrescenta canal=1 a query (D-91).
     * Nao da privilegio nenhum: so muda os enderecos que a pagina gera.
     */
    public static function pelaCanal(): bool
    {
        return (string) ($_GET['canal'] ?? '') === '1' && self::canalBase() !== '';
    }

    /**
     * Endereco de uma pagina anonima: curto quando configurado, senao o real.
     * $alvo: 'responder' (exige token) ou 'protocolo'.
     */
    public static function urlAnonima(string $alvo, string $token = ''): string
    {
        $base = self::canalBase();
        if ($alvo === 'protocolo') {
            return $base !== '' ? $base . '/protocolo' : \Html::getPrefixedUrl('/plugins/pessoasplus/front/protocolo.php');
        }

        return $base !== '' ? $base . '/' . $token : \Html::getPrefixedUrl('/plugins/pessoasplus/front/responder_publico.php') . '?t=' . $token;
    }

    /**
     * Endereco de quem envia, para o limite diario da pagina publica (D-05).
     * Atras do proxy de loopback (D-91) o REMOTE_ADDR e 127.0.0.1 para todo mundo:
     * nesse caso, e so nesse, vale o primeiro X-Forwarded-For, que o mod_proxy preenche.
     */
    public static function enderecoRemoto(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (in_array($ip, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $primeiro = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($primeiro, FILTER_VALIDATE_IP) !== false) {
                return $primeiro;
            }
        }

        return $ip;
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
