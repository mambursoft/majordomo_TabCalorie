<?php
/**
 * Chart helpers for TabCalorie.
 * - standard MajorDoMo pChart URL (breakfast/lunch/dinner/day total properties),
 * - lightweight inline SVG preview built from tabcalorie_days.
 *
 * @package tabcalorie
 * @license MIT
 */

class TabCalorieChart
{
    /**
     * Standard MajorDoMo chart link using object property history.
     * Properties are written with KEEP_HISTORY by TabCalorieStore::updateObject().
     */
    public static function standardUrl($days = 7)
    {
        $days = max(1, min(365, (int)$days));
        $p = array('TabCalorie.Slot1Carbs', 'TabCalorie.Slot3Carbs',
            'TabCalorie.Slot5Carbs', 'TabCalorie.TotalCarbs');
        $parts = array();
        foreach ($p as $name) {
            $parts[] = 'p[]=' . rawurlencode($name);
        }
        $parts[] = 'op=log';
        $parts[] = 'type=' . $days . 'd';
        $parts[] = 'title=' . rawurlencode('TabCalorie carbs');
        return 'pChart/?' . implode('&', $parts);
    }

    /** Build a compact inline SVG bar chart for the last N filled days. */
    public static function svg($days = 7, $width = 720, $height = 240)
    {
        $days = max(1, min(60, (int)$days));
        $rows = array_reverse(TabCalorieStore::dayRows(date('Y-m-d', time() - $days * 86400), date('Y-m-d')));
        if (!$rows) {
            return '<div class="tc-empty">' . htmlspecialchars(TabCalorieChart::tr('TC_NO_DATA'), ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $max = 1.0;
        foreach ($rows as $r) {
            $max = max($max, TabCalorieStore::num($r['CARBOHYDRATE']));
        }
        $padL = 44;
        $padB = 28;
        $padT = 14;
        $padR = 10;
        $plotW = max(10, $width - $padL - $padR);
        $plotH = max(10, $height - $padB - $padT);
        $n = count($rows);
        $slot = $plotW / $n;
        $barW = max(4, $slot * 0.55);
        $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="tc-chart-svg" role="img" aria-label="carbs">';
        $svg .= '<rect x="0" y="0" width="' . $width . '" height="' . $height . '" fill="#fff"/>';
        for ($g = 0; $g <= 4; $g++) {
            $y = $padT + $plotH * $g / 4;
            $val = $max * (4 - $g) / 4;
            $svg .= '<line x1="' . $padL . '" y1="' . $y . '" x2="' . ($width - $padR) . '" y2="' . $y . '" stroke="#e6e9ee"/>';
            $svg .= '<text x="' . ($padL - 6) . '" y="' . ($y + 4) . '" font-size="10" fill="#7a869a" text-anchor="end">' . TabCalorieStore::round1($val) . '</text>';
        }
        foreach ($rows as $i => $r) {
            $v = TabCalorieStore::num($r['CARBOHYDRATE']);
            $h = $plotH * $v / $max;
            $x = $padL + $i * $slot + ($slot - $barW) / 2;
            $y = $padT + $plotH - $h;
            $color = $v > 0 ? '#2fa36b' : '#cfd6df';
            $svg .= '<rect x="' . TabCalorieStore::round1($x) . '" y="' . TabCalorieStore::round1($y) .
                '" width="' . TabCalorieStore::round1($barW) . '" height="' . TabCalorieStore::round1($h) .
                '" rx="2" fill="' . $color . '"><title>' . htmlspecialchars(TabCalorieExporter::dateUa($r['DAY_DATE']) . ': ' . $v . ' g', ENT_QUOTES, 'UTF-8') . '</title></rect>';
            if ($n <= 12 || $i % 2 === 0) {
                $svg .= '<text x="' . TabCalorieStore::round1($x + $barW / 2) . '" y="' . ($height - 10) .
                    '" font-size="9" fill="#7a869a" text-anchor="middle">' .
                    htmlspecialchars(substr(TabCalorieExporter::dateUa($r['DAY_DATE']), 0, 5), ENT_QUOTES, 'UTF-8') . '</text>';
            }
        }
        $svg .= '</svg>';
        return $svg;
    }

    private static function tr($key)
    {
        $const = 'LANG_' . $key;
        return defined($const) ? constant($const) : $key;
    }
}
