<?php

/**
 * Pessoas+ - identidade visual (nome, logo e cor).
 *
 * PESSOASPLUS_BUILD_BP0_3
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

/**
 * Identidade visual aplicada a todas as telas do Pessoas+.
 *
 * Na casca vale o padrao abaixo. No BP.10 (casca) e na mobilia da
 * configuracao, estes valores passam a vir da tela "Identidade visual",
 * com logo enviado pelo RH. As telas ja leem tudo daqui, entao nada
 * nelas muda quando a personalizacao chegar.
 */
final class Identidade
{
    /** Cor de acento padrao (teal da Resolutto, escurecido para contraste AA). */
    public const COR_PADRAO = '#23767e';

    /**
     * @return array{nome: string, nome_base: string, nome_sufixo: string, rotulo: string, organizacao: string, logo: string, sigla: string, cor: string, cor_escura: string, cor_clara: string, cor_suave: string}
     */
    public static function atual(): array
    {
        return self::montar([
            'nome'        => 'Pessoas+',
            'rotulo'      => 'Gestão de pessoas',
            'organizacao' => 'Resolutto',
            'logo'   => '',
            'cor'    => self::COR_PADRAO,
        ]);
    }

    /**
     * Normaliza uma configuracao de identidade. Tudo que vier invalido
     * cai no padrao, para que um valor errado nunca quebre a tela.
     *
     * @param array{nome?: mixed, rotulo?: mixed, organizacao?: mixed, logo?: mixed, cor?: mixed} $config
     *
     * @return array{nome: string, nome_base: string, nome_sufixo: string, rotulo: string, organizacao: string, logo: string, sigla: string, cor: string, cor_escura: string, cor_clara: string, cor_suave: string}
     */
    public static function montar(array $config): array
    {
        $nome = trim((string) ($config['nome'] ?? ''));
        if ($nome === '') {
            $nome = 'Pessoas+';
        }

        $cor = strtolower(trim((string) ($config['cor'] ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $cor) !== 1) {
            $cor = self::COR_PADRAO;
        }

        // Um "+" no fim do nome ganha a cor de acento, como na proposta.
        $sufixo = str_ends_with($nome, '+') ? '+' : '';
        $base   = $sufixo === '' ? $nome : mb_substr($nome, 0, mb_strlen($nome) - 1);

        return [
            'nome'        => $nome,
            'nome_base'   => $base,
            'nome_sufixo' => $sufixo,
            'rotulo'      => trim((string) ($config['rotulo'] ?? '')),
            'organizacao' => trim((string) ($config['organizacao'] ?? '')),
            'logo'        => trim((string) ($config['logo'] ?? '')),
            'sigla'       => mb_strtoupper(mb_substr($base, 0, 1)),
            'cor'         => $cor,
            'cor_escura'  => self::misturar($cor, '#000000', 0.2),
            'cor_clara'   => self::misturar($cor, '#ffffff', 0.88),
            'cor_suave'   => self::misturar($cor, '#ffffff', 0.55),
        ];
    }

    /**
     * Mistura duas cores hexadecimais. $peso e a fracao da segunda cor.
     */
    public static function misturar(string $cor, string $com, float $peso): string
    {
        $a = sscanf($cor, '#%02x%02x%02x');
        $b = sscanf($com, '#%02x%02x%02x');
        $saida = '#';
        for ($i = 0; $i < 3; $i++) {
            $saida .= sprintf('%02x', (int) round($a[$i] * (1 - $peso) + $b[$i] * $peso));
        }

        return $saida;
    }
}
