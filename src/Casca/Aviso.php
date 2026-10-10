<?php

/**
 * Pessoas+ - aviso ao entrar e lembretes durante a sessao (D-49).
 *
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2D
 * PESSOASPLUS_BUILD_BP3B
 * PESSOASPLUS_BUILD_BP3C
 * PESSOASPLUS_BUILD_BM2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

/**
 * Decide se o aviso aparece, quando volta e o que o contador mostra.
 *
 * Regra da D-49: o aviso aparece ao entrar e volta durante a sessao
 * enquanto houver pendencia. Cada exibicao inicia um intervalo; so depois
 * dele o aviso aparece de novo, mesmo que a pessoa navegue. Com item em
 * atraso o intervalo e menor (escalada). O contador aparece sempre que ha
 * pendencia. Nunca bloqueia nada.
 *
 * Na versao final: 4 h (configuravel de 1 a 8 h) e 1 h com atraso.
 *
 * BP.3b: a volta do aviso durante a sessao ja foi demonstrada (BP.2d) e
 * atrapalhava o uso da casca. Ate a mobilia (B1.11) o aviso aparece uma
 * vez por sessao; o contador continua enquanto houver pendencia.
 *
 * BP.3c: o Mural tem prioridade na entrada (D-54). Na pagina do Mural do
 * colaborador o aviso nao abre nem e gasto: abre uma vez, na primeira
 * pagina fora dele. O Mural ja mostra as pendencias no proprio resumo.
 */
/* BM.2: as pendencias passaram a vir do banco (Comunicado::pendenciasDe). */
final class Aviso
{
    public const CHAVE_PROXIMO   = 'plugin_pessoasplus_aviso_proximo';
    public const CHAVE_EXIBICOES = 'plugin_pessoasplus_aviso_exibicoes';

    /** Intervalo normal na casca, em segundos (versao final: 4 h). */
    public const INTERVALO_DEMO = 120;

    /** Intervalo com item em atraso na casca, em segundos (versao final: 1 h). */
    public const INTERVALO_ATRASO_DEMO = 60;

    /**
     * A consulta veio da pagina do Mural do colaborador? O aviso.js novo
     * nem consulta ali; isto cobre o aviso.js antigo ainda no cache do
     * navegador (T-35), que consulta em toda pagina. O fetch de mesma
     * origem manda o endereco da pagina no Referer.
     */
    public static function vemDoMural(string $referer): bool
    {
        $caminho = parse_url($referer, PHP_URL_PATH);

        return is_string($caminho) && str_ends_with($caminho, MenuSimplificado::PAGINA_MURAL);
    }

    /**
     * @param array<string, mixed> $sessao sessao do PHP, por referencia
     *
     * @return array<string, mixed>
     */
    public static function consumir(array &$sessao, bool $tem_direito, int $agora, bool $no_mural = false): array
    {
        if (!$tem_direito) {
            return ['mostrar' => false, 'pendentes' => 0];
        }

        $itens = [];
        foreach (\GlpiPlugin\Pessoasplus\Comunicado::pendenciasDe((int) \Session::getLoginUserID()) as $p) {
            $itens[] = ['id' => $p['id'], 'titulo' => $p['titulo'], 'detalhe' => $p['detalhe'], 'selo' => $p['selo'], 'tom' => $p['tom']];
        }
        $conteudo = [
            'titulo'    => count($itens) . (count($itens) === 1 ? ' comunicado aguarda' : ' comunicados aguardam') . ' sua ciência',
            'subtitulo' => 'Confirme para manter seus registros em dia',
        ];
        $pendentes = count($itens);
        if ($pendentes === 0) {
            return ['mostrar' => false, 'pendentes' => 0];
        }

        $atrasados = count(array_filter($itens, static fn (array $i): bool => $i['tom'] === 'atraso'));
        $intervalo = $atrasados > 0 ? self::INTERVALO_ATRASO_DEMO : self::INTERVALO_DEMO;
        $url_ler   = Pagina::url(MenuSimplificado::PAGINA);
        $proximo   = (int) ($sessao[self::CHAVE_PROXIMO] ?? 0);

        $base = [
            'pendentes' => $pendentes,
            'atrasados' => $atrasados,
            'url_ler'   => $url_ler,
        ];

        // Uma vez por sessao na casca (BP.3b): 'volta_em' 0 faz o JS nao
        // agendar nova consulta. Os intervalos ficam para a mobilia.
        // No Mural do colaborador, nada se gasta (BP.3c).
        if ($no_mural || $agora < $proximo || (int) ($sessao[self::CHAVE_EXIBICOES] ?? 0) > 0) {
            return $base + ['mostrar' => false, 'volta_em' => 0];
        }

        $sessao[self::CHAVE_PROXIMO]   = $agora + $intervalo;
        $sessao[self::CHAVE_EXIBICOES] = 1;
        $identidade = Identidade::atual();

        return $base + [
            'mostrar'    => true,
            'volta_em'   => 0,
            'demo'       => false,
            'titulo'     => $conteudo['titulo'],
            'subtitulo'  => $conteudo['subtitulo'],
            'itens'      => $itens,
            'cor'        => $identidade['cor'],
            'cor_escura' => $identidade['cor_escura'],
            'cor_clara'  => $identidade['cor_clara'],
        ];
    }
}
