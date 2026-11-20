<?php
/**
 * Offline demo data for TabCalorie.
 * Lets a fresh installation verify the UI and aggregation without any real
 * credentials or network requests to the unofficial TK API.
 *
 * @package tabcalorie
 * @license MIT
 */

class TabCalorieDemo
{
    /** Return a diary-shaped array for any date. */
    public static function diary($date)
    {
        $slot = function ($id, $title, $items) {
            return array('id' => (string)$id, 'title' => $title,
                'foodstuff' => $items, 'notes' => array(),
                'energyTotal' => '0');
        };
        $item = function ($guid, $title, $unit, $energy, $protein, $fat, $carbs, $fiber, $sugar) {
            return array('id' => 'demo-' . $guid, 'guidType' => $guid, 'title' => $title,
                'unit' => $unit, 'energy' => (string)$energy, 'energyUnit' => 'kcal',
                'protein' => $protein, 'fat' => $fat, 'carbohydrate' => $carbs,
                'fiber' => $fiber, 'sugar' => $sugar, 'url' => $guid);
        };
        return array(
            'date' => strtotime($date) * 1000,
            'energyUnit' => 'kcal',
            'energyTotal' => '560',
            'proteinTotal' => 17.5,
            'fatTotal' => 5.1,
            'carbohydrateTotal' => '100',
            'fiberTotal' => 7.3,
            'sugarTotal' => 8.2,
            'foodstuffCount' => 2,
            'times' => array(
                $slot(1, 'Сніданок', array($item('demo-oat', 'Вівсянка (демо)', '150 x 1 g', 300, 12.5, 4.2, 40, 6.1, 8))),
                $slot(2, 'Перекус', array()),
                $slot(3, 'Обід', array($item('demo-rice', 'Рис (демо)', '200 x 1 g', 260, 5, 0.9, 60, 1.2, 0.2))),
                $slot(4, 'Підвечірок', array()),
                $slot(5, 'Вечеря', array()),
                $slot(6, 'Друга вечеря', array()),
            ),
        );
    }
}
