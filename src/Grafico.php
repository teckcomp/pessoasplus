<?php

/**
 * Pessoas+ - graficos em SVG desenhados pelo plugin (BM.5).
 *
 * PESSOASPLUS_BUILD_BM5
 *
 * Sem biblioteca: o SVG sai pronto do servidor (imprime, nao depende do
 * bundle do core). Entrada: lista de fatias [rotulo, total, percentual].
 * Texto de usuario entra sempre por htmlspecialchars.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

final class Grafico
{
    /** Paleta da identidade (acento primeiro), 10 cores distinguiveis. */
    public const CORES = ['#1f8a8a', '#b86e00', '#2f8a52', '#a0284f', '#58626f', '#7b4bb0', '#2b6cb0', '#c0392b', '#8a7a1f', '#1f5f8a'];

    public static function cor(int $i): string
    {
        return self::CORES[$i % count(self::CORES)];
    }

    /**
     * Pizza ou donut (D-83: visual proprio). Fatias com total 0 ficam na legenda, fora do desenho.
     *
     * @param array<int, array{rotulo: string, total: int, percentual: int}> $fatias
     */
    public static function pizza(array $fatias, bool $donut = false, int $tamanho = 220, ?int $centroTotal = null): string
    {
        // Multipla escolha: a fatia e a parte das marcacoes, mas o rotulo mostra o % de respondentes (igual a legenda) e o centro mostra respondentes.
        $total = 0;
        foreach ($fatias as $f) {
            $total += max(0, (int) $f['total']);
        }
        $r   = $tamanho / 2;
        $cx  = $r;
        $cy  = $r;
        $ri  = $donut ? $r * 0.56 : 0;
        $svg = '<svg class="pp-graf pp-graf-' . ($donut ? 'donut' : 'pizza') . '" viewBox="0 0 ' . $tamanho . ' ' . $tamanho . '" width="' . $tamanho . '" height="' . $tamanho . '" role="img" aria-label="' . ($donut ? 'Gráfico de rosca' : 'Gráfico de pizza') . '">';
        if ($total === 0) {
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r - 2) . '" fill="#eef1f3"/>';
            if ($donut) {
                $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $ri . '" fill="#ffffff"/>';
            }
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 5) . '" text-anchor="middle" font-size="13" fill="#58626f">sem respostas</text></svg>';

            return $svg;
        }
        $ang = -M_PI / 2;
        $comValor = array_values(array_filter($fatias, static fn ($f) => (int) $f['total'] > 0));
        if (count($comValor) === 1) {
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r - 2) . '" fill="' . self::cor(array_search($comValor[0], $fatias, true) ?: 0) . '"><title>' . htmlspecialchars((string) $comValor[0]['rotulo'], ENT_QUOTES) . ': ' . (int) $comValor[0]['total'] . ' (100%)</title></circle>';
        } else {
            foreach ($fatias as $i => $f) {
                $n = max(0, (int) $f['total']);
                if ($n === 0) {
                    continue;
                }
                $frac = $n / $total;
                $fim  = $ang + $frac * 2 * M_PI;
                $x1 = $cx + ($r - 2) * cos($ang);
                $y1 = $cy + ($r - 2) * sin($ang);
                $x2 = $cx + ($r - 2) * cos($fim);
                $y2 = $cy + ($r - 2) * sin($fim);
                $grande = $frac > 0.5 ? 1 : 0;
                $d = sprintf('M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f Z', $x1, $y1, $r - 2, $r - 2, $grande, $x2, $y2, $cx, $cy);
                $svg .= '<path d="' . $d . '" fill="' . self::cor((int) $i) . '" stroke="#ffffff" stroke-width="2"><title>' . htmlspecialchars((string) $f['rotulo'], ENT_QUOTES) . ': ' . $n . ' (' . (int) $f['percentual'] . '%)</title></path>';
                // Rotulo de percentual dentro da fatia quando ela tem espaco.
                if ($frac >= 0.08) {
                    $meio = ($ang + $fim) / 2;
                    $rr   = $donut ? ($r + $ri) / 2 : $r * 0.62;
                    $svg .= '<text x="' . sprintf('%.1f', $cx + $rr * cos($meio)) . '" y="' . sprintf('%.1f', $cy + $rr * sin($meio) + 4) . '" text-anchor="middle" font-size="12" font-weight="700" fill="#ffffff">' . (int) $f['percentual'] . '%</text>';
                }
                $ang = $fim;
            }
        }
        if ($donut) {
            $n = $centroTotal ?? $total;
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $ri . '" fill="#ffffff"/>';
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 2) . '" text-anchor="middle" font-size="22" font-weight="700" fill="#1a1f24">' . $n . '</text>';
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 18) . '" text-anchor="middle" font-size="10" fill="#58626f">' . ($n === 1 ? 'resposta' : 'respostas') . '</text>';
        }

        return $svg . '</svg>';
    }

    /**
     * Legenda HTML (cor, rotulo, total, %), na mesma ordem e cores da pizza.
     *
     * @param array<int, array{rotulo: string, total: int, percentual: int}> $fatias
     */
    public static function legenda(array $fatias): string
    {
        $h = '<ul class="pp-graf-legenda">';
        foreach ($fatias as $i => $f) {
            $h .= '<li><span class="pp-graf-cor" style="background:' . self::cor((int) $i) . '"></span><span class="pp-graf-rotulo">' . htmlspecialchars((string) $f['rotulo'], ENT_QUOTES) . '</span><strong>' . (int) $f['total'] . '</strong><small>' . (int) $f['percentual'] . '%</small></li>';
        }

        return $h . '</ul>';
    }

    /**
     * Barras horizontais em SVG (para impressao e para o seletor), com a cor
     * de cada barra igual a da pizza. Largura 100% (viewBox fixo em 600).
     *
     * @param array<int, array{rotulo: string, total: int, percentual: int}> $fatias
     */
    public static function barras(array $fatias, bool $destacarTop = true): string
    {
        $n = count($fatias);
        if ($n === 0) {
            return '';
        }
        $alt = 30;
        $h   = $n * $alt + 6;
        $max = 0;
        foreach ($fatias as $f) {
            $max = max($max, (int) $f['total']);
        }
        $maior = -1;
        if ($destacarTop && $max > 0) {
            foreach ($fatias as $i => $f) {
                if ((int) $f['total'] === $max) {
                    $maior = (int) $i;
                    break;
                }
            }
        }
        $svg = '<svg class="pp-graf pp-graf-barras" viewBox="0 0 600 ' . $h . '" width="100%" height="' . $h . '" role="img" aria-label="Gráfico de barras" preserveAspectRatio="none">';
        $x0 = 190;
        $w  = 600 - $x0 - 60;
        foreach ($fatias as $i => $f) {
            $y    = (int) $i * $alt + 3;
            $larg = $max > 0 ? max(2, (int) round($w * (int) $f['total'] / $max)) : 2;
            $rot  = htmlspecialchars(mb_strimwidth((string) $f['rotulo'], 0, 26, '…'), ENT_QUOTES);
            $svg .= '<text x="' . ($x0 - 10) . '" y="' . ($y + 18) . '" text-anchor="end" font-size="13" fill="#1a1f24"' . ((int) $i === $maior ? ' font-weight="700"' : '') . '>' . $rot . '</text>';
            $svg .= '<rect x="' . $x0 . '" y="' . ($y + 5) . '" width="' . $w . '" height="18" rx="4" fill="#eef1f3"/>';
            $svg .= '<rect x="' . $x0 . '" y="' . ($y + 5) . '" width="' . $larg . '" height="18" rx="4" fill="' . self::cor((int) $i) . '"><title>' . htmlspecialchars((string) $f['rotulo'], ENT_QUOTES) . ': ' . (int) $f['total'] . ' (' . (int) $f['percentual'] . '%)</title></rect>';
            $svg .= '<text x="' . ($x0 + $w + 8) . '" y="' . ($y + 18) . '" font-size="13" font-weight="700" fill="#1a1f24">' . (int) $f['total'] . '</text>';
            $svg .= '<text x="' . ($x0 + $w + 34) . '" y="' . ($y + 18) . '" font-size="11" fill="#58626f">' . (int) $f['percentual'] . '%</text>';
        }

        return $svg . '</svg>';
    }
}
