<?php

/**
 * Pessoas+ - aviso ao entrar e lembretes durante a sessao (D-49).
 *
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2D
 * PESSOASPLUS_BUILD_BP3B
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
 */
final class Aviso
{
    public const CHAVE_PROXIMO   = 'plugin_pessoasplus_aviso_proximo';
    public const CHAVE_EXIBICOES = 'plugin_pessoasplus_aviso_exibicoes';

    /** Intervalo normal na casca, em segundos (versao final: 4 h). */
    public const INTERVALO_DEMO = 120;

    /** Intervalo com item em atraso na casca, em segundos (versao final: 1 h). */
    public const INTERVALO_ATRASO_DEMO = 60;

    /**
     * @param array<string, mixed> $sessao sessao do PHP, por referencia
     *
     * @return array<string, mixed>
     */
    public static function consumir(array &$sessao, bool $tem_direito, int $agora): array
    {
        if (!$tem_direito) {
            return ['mostrar' => false, 'pendentes' => 0];
        }

        $conteudo  = Fontes::avisoEntrada();
        $itens     = $conteudo['itens'];
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
        if ($agora < $proximo || (int) ($sessao[self::CHAVE_EXIBICOES] ?? 0) > 0) {
            return $base + ['mostrar' => false, 'volta_em' => 0];
        }

        $sessao[self::CHAVE_PROXIMO]   = $agora + $intervalo;
        $sessao[self::CHAVE_EXIBICOES] = 1;
        $identidade = Identidade::atual();

        return $base + [
            'mostrar'    => true,
            'volta_em'   => 0,
            'demo'       => Fontes::emDemonstracao('minha_area'),
            'titulo'     => $conteudo['titulo'],
            'subtitulo'  => $conteudo['subtitulo'],
            'itens'      => $itens,
            'cor'        => $identidade['cor'],
            'cor_escura' => $identidade['cor_escura'],
            'cor_clara'  => $identidade['cor_clara'],
        ];
    }
}
