<?php
/**
 * Generator for TabCalorie language dictionaries (uk / en / ru).
 * Run: php tools/gen_languages.php
 * Output: languages/tabcalorie_{uk,ua,en,ru,default}.php
 * Uk is the default language of the plugin.
 */

$strings = array(
    'TC_TITLE'              => array('TabCalorie — контроль вуглеводів', 'TabCalorie — carbohydrate control', 'TabCalorie — контроль углеводов'),
    'TC_SUBTITLE'           => array('Імпорт щоденника «Таблиця калорійності» в MajorDoMo', 'Food diary import from TabCalorie into MajorDoMo', 'Импорт дневника «Таблица калорийности» в MajorDoMo'),
    'TC_TAB_OVERVIEW'       => array('Огляд', 'Overview', 'Обзор'),
    'TC_TAB_HISTORY'        => array('Історія', 'History', 'История'),
    'TC_TAB_FOODS'          => array('Довідник страв', 'Food dictionary', 'Справочник блюд'),
    'TC_TAB_CHART'          => array('Графік', 'Chart', 'График'),
    'TC_TAB_EXPORT'         => array('Експорт', 'Export', 'Экспорт'),
    'TC_TAB_WRITEBACK'      => array('Запис у ТК', 'Write-back', 'Запись в ТК'),
    'TC_TAB_DIAGNOSTICS'    => array('Діагностика', 'Diagnostics', 'Диагностика'),
    'TC_TAB_SETTINGS'       => array('Налаштування', 'Settings', 'Настройки'),
    'TC_TAB_INSTRUCTIONS'   => array('Інструкції', 'Instructions', 'Инструкции'),
    'TC_LAST_SYNC'          => array('Останнє оновлення', 'Last sync', 'Последняя синхронизация'),
    'TC_LAST_FILLED_DAY'    => array('Остання заповнена доба', 'Last filled day', 'Последний заполненный день'),
    'TC_PARTIAL_DAY'        => array('Частково заповнено', 'Partially filled', 'Заполнено частично'),
    'TC_FULL_DAY'           => array('Повний день', 'Full day', 'Полный день'),
    'TC_TOTAL_CARBS'        => array('Вуглеводи за добу', 'Daily carbs', 'Углеводы за день'),
    'TC_TOTAL_KCAL'         => array('Калорії', 'Calories', 'Калории'),
    'TC_PROTEIN'            => array('Білки', 'Protein', 'Белки'),
    'TC_FAT'                => array('Жири', 'Fat', 'Жиры'),
    'TC_FIBER'              => array('Клітковина', 'Fiber', 'Клетчатка'),
    'TC_SUGAR'              => array('Цукри', 'Sugars', 'Сахара'),
    'TC_XE'                 => array('ХО', 'BU', 'ХЕ'),
    'TC_MEALS'              => array('Прийоми їжі', 'Meals', 'Приёмы пищи'),
    'TC_MEAL'               => array('Прийом їжі', 'Meal', 'Приём пищи'),
    'TC_CARBS'              => array('Вуглеводи, г', 'Carbs, g', 'Углеводы, г'),
    'TC_KCAL'               => array('Ккал', 'kcal', 'ккал'),
    'TC_MAIN_MEALS'         => array('Основні (сніданок/обід/вечеря)', 'Main meals', 'Основные приемы'),
    'TC_SNACKS'             => array('Перекуси', 'Snacks', 'Перекусы'),
    'TC_TOTAL'              => array('Разом', 'Total', 'Итого'),
    'TC_NOTES'              => array('Нотатки', 'Notes', 'Заметки'),
    'TC_BREAKFAST'          => array('Сніданок', 'Breakfast', 'Завтрак'),
    'TC_SNACK1'             => array('Перекус', 'Snack', 'Перекус'),
    'TC_LUNCH'              => array('Обід', 'Lunch', 'Обед'),
    'TC_SNACK2'             => array('Підвечірок', 'Afternoon snack', 'Полдник'),
    'TC_DINNER'             => array('Вечеря', 'Dinner', 'Ужин'),
    'TC_LATE_DINNER'        => array('Друга вечеря', 'Late dinner', 'Второй ужин'),
    'TC_HISTORY'            => array('Історія (30 днів)', 'History (30 days)', 'История (30 дней)'),
    'TC_DATE'               => array('Дата', 'Date', 'Дата'),
    'TC_FOODS'              => array('Довідник унікальних страв (на 100 г)', 'Unique food dictionary (per 100 g)', 'Справочник уникальных блюд (на 100 г)'),
    'TC_FOOD'               => array('Страва', 'Food', 'Блюдо'),
    'TC_GI'                 => array('ГІ', 'GI', 'ГИ'),
    'TC_STATUS'             => array('Стан', 'Status', 'Состояние'),
    'TC_CHART'              => array('Графік споживання', 'Intake chart', 'График потребления'),
    'TC_CHART_HINT'         => array('Сніданок, обід, вечеря та підсумок за добу.', 'Breakfast, lunch, dinner and daily total.', 'Завтрак, обед, ужин и суточная сумма.'),
    'TC_CREATE_CHART'       => array('Створити типовий chart', 'Create standard chart', 'Создать стандартный chart'),
    'TC_NO_DATA'            => array('Даних поки немає', 'No data yet', 'Данных пока нет'),
    'TC_EMPTY'              => array('Немає даних для графіка', 'No chart data', 'Нет данных для графика'),
    'TC_EXPORT'             => array('Експорт CSV', 'CSV export', 'Экспорт CSV'),
    'TC_PERIOD'             => array('Період', 'Period', 'Период'),
    'TC_PERIOD_DAY'         => array('День', 'Day', 'День'),
    'TC_PERIOD_WEEK'        => array('Тиждень', 'Week', 'Неделя'),
    'TC_PERIOD_MONTH'       => array('Місяць', 'Month', 'Месяц'),
    'TC_PERIOD_YEAR'        => array('Рік', 'Year', 'Год'),
    'TC_VIEW'               => array('Вид', 'View', 'Вид'),
    'TC_DAILY'              => array('Підсумки за дні', 'Daily totals', 'Суточные итоги'),
    'TC_DETAILED'           => array('Деталізація страв', 'Food details', 'Детализация блюд'),
    'TC_DOWNLOAD_CSV'       => array('Завантажити CSV', 'Download CSV', 'Скачать CSV'),
    'TC_CSV_HINT'           => array('Файл із BOM для Excel.', 'UTF-8 BOM for Excel.', 'Файл с BOM для Excel.'),
    'TC_WRITEBACK_ON'       => array('Запис у ТК увімкнено, але кожен запис потребує підтвердження.', 'Write-back enabled, every record still requires confirmation.', 'Запись в ТК включена, но каждая запись требует подтверждения.'),
    'TC_WRITEBACK_OFF'      => array('Запис у ТК вимкнено (безпечний режим за замовчуванням).', 'Write-back is disabled (safe default).', 'Запись в ТК отключена (безопасный режим по умолчанию).'),
    'TC_WRITEBACK_QUEUE'    => array('Черга записів', 'Write queue', 'Очередь записей'),
    'TC_CONFIRM_SEND'       => array('Підтвердити', 'Confirm', 'Подтвердить'),
    'TC_CANCEL'             => array('Скасувати', 'Cancel', 'Отменить'),
    'TC_DIAGNOSTICS'        => array('Діагностика підключення до ТК', 'TK connection diagnostics', 'Диагностика подключения к ТК'),
    'TC_CHECK'              => array('Перевірка', 'Check', 'Проверка'),
    'TC_RESULT'             => array('Результат', 'Result', 'Результат'),
    'TC_DETAILS'            => array('Деталі', 'Details', 'Подробности'),
    'TC_DIAG_HINT'          => array('Якщо вхід через Google/Apple — скористайтеся «Забули пароль?» на сайті ТК.', 'If you use Google/Apple sign-in, use "Forgot password?" on the TK site.', 'Если вход через Google/Apple — используйте «Забыли пароль?» на сайте ТК.'),
    'TC_SETTINGS'           => array('Налаштування плагіна', 'Plugin settings', 'Настройки плагина'),
    'TC_PROVIDER'           => array('Домен ТК', 'TK domain', 'Домен ТК'),
    'TC_CUSTOM'             => array('Власний HTTPS URL', 'Custom HTTPS URL', 'Свой HTTPS URL'),
    'TC_BASE_URL'           => array('Base URL', 'Base URL', 'Base URL'),
    'TC_EMAIL'              => array('Email', 'Email', 'Email'),
    'TC_PASSWORD'           => array('Пароль', 'Password', 'Пароль'),
    'TC_INTERVAL'           => array('Період оновлення, хв (мін. 15)', 'Update interval, min (min 15)', 'Период обновления, мин (мин. 15)'),
    'TC_SYNC_DAYS'          => array('Днів за один запуск', 'Days per run', 'Дней за один запуск'),
    'TC_RETENTION'          => array('Термін зберігання, днів', 'Retention, days', 'Срок хранения, дней'),
    'TC_G_PER_XE'           => array('Грамів на 1 ХО', 'Grams per BU', 'Граммов на 1 ХЕ'),
    'TC_TARGET'             => array('Ціль вуглеводів, г', 'Carb target, g', 'Цель по углеводам, г'),
    'TC_HOOK'               => array('Хук: об\'єкт.властивість', 'Hook: object.property', 'Хук: объект.свойство'),
    'TC_HOOK_DEBOUNCE'      => array('Затримка хука, сек', 'Hook debounce, sec', 'Задержка хука, сек'),
    'TC_LANGUAGE'           => array('Мова', 'Language', 'Язык'),
    'TC_CSV_DELIMITER'      => array('Розділювач CSV', 'CSV delimiter', 'Разделитель CSV'),
    'TC_SYNC_ENABLED'       => array('Увімкнути автооновлення', 'Enable automatic sync', 'Включить автообновление'),
    'TC_HOOK_ENABLED'       => array('Увімкнути хук', 'Enable hook', 'Включить хук'),
    'TC_WRITEBACK_ENABLED'  => array('Дозволити запис у ТК', 'Allow write-back to TK', 'Разрешить запись в ТК'),
    'TC_WRITEBACK_CONFIRM'  => array('Підтвердження кожного запису (обов\'язково)', 'Confirm every write (required)', 'Подтверждение каждой записи (обязательно)'),
    'TC_NS_ENABLED'         => array('Nightscout push', 'Nightscout push', 'Nightscout push'),
    'TC_DEMO_MODE'          => array('Демо-режим (без мережі та записів)', 'Demo mode (offline, no writes)', 'Демо-режим (без сети и записей)'),
    'TC_NS_TEST'            => array('Перевірити Nightscout', 'Test Nightscout', 'Проверить Nightscout'),
    'TC_SYNC_NOW'           => array('Оновити зараз', 'Sync now', 'Обновить сейчас'),
    'TC_SAVE'               => array('Зберегти', 'Save', 'Сохранить'),
    'TC_SAVED'              => array('Збережено', 'Saved', 'Сохранено'),
    'TC_SYNC_DONE'          => array('Оновлено рядків:', 'Rows synced:', 'Обновлено строк:'),
    'TC_CSRF_ERROR'         => array('Помилка безпеки: оновіть сторінку', 'Security error: reload the page', 'Ошибка безопасности: обновите страницу'),
    'TC_INSTRUCTIONS'       => array('Інструкція', 'Instructions', 'Инструкция'),
    'TC_STEP1'              => array('Вкажіть email і пароль сайту ТК у налаштуваннях. Пароль зберігається маскованим.', 'Enter your TK site email and password in settings. The password is stored masked.', 'Укажите email и пароль сайта ТК в настройках. Пароль хранится в скрытом виде.'),
    'TC_STEP2'              => array('Якщо вхід був через Google/Apple, отримайте пароль через «Забули пароль?» на сайті ТК.', 'If you signed in via Google/Apple, get a password with "Forgot password?" on the TK site.', 'Если вход был через Google/Apple, получите пароль через «Забыли пароль?» на сайте ТК.'),
    'TC_STEP3'              => array('Виберіть домен (UA/CZ), інтервал і запустіть «Оновити зараз».', 'Choose the domain (UA/CZ), interval and run "Sync now".', 'Выберите домен (UA/CZ), интервал и запустите «Обновить сейчас».'),
    'TC_STEP4'              => array('Перевірте вкладку «Огляд» — там остання заповнена доба.', 'Check the "Overview" tab for the last filled day.', 'Проверьте вкладку «Обзор» — там последний заполненный день.'),
    'TC_STEP5'              => array('Заповніть ГІ та нотатки у «Довіднику страв» (API ТК їх не надає).', 'Fill GI and notes in the food dictionary (TK API does not provide them).', 'Заполните ГИ и заметки в «Справочнике блюд» (API ТК их не предоставляет).'),
    'TC_STEP6'              => array('Експортуйте CSV або створіть типовий chart для контролю вуглеводів.', 'Export CSV or create a standard chart for carbohydrate control.', 'Экспортируйте CSV или создайте стандартный chart для контроля углеводов.'),
    'TC_DISCLAIMER'         => array('Плагін не є медичним пристроєм і не рекомендує дози інсуліну.', 'This plugin is not a medical device and does not recommend insulin doses.', 'Плагин не является медицинским устройством и не рекомендует дозы инсулина.'),
);

$langs = array('uk' => 0, 'ua' => 0, 'en' => 1, 'ru' => 2);
$root = dirname(__DIR__);
if (!is_dir($root . '/languages')) {
    mkdir($root . '/languages', 0775, true);
}
foreach ($langs as $code => $idx) {
    $out = "<?php\n/** TabCalorie language dictionary: " . $code . ". Generated by tools/gen_languages.php */\n\n";
    $out .= "\$dictionary = array(\n";
    foreach ($strings as $key => $values) {
        $out .= "    '" . $key . "' => '" . str_replace("'", "\\'", $values[$idx]) . "',\n";
    }
    $out .= ");\n\nforeach (\$dictionary as \$k => \$v) {\n";
    $out .= "    if (!defined('LANG_' . \$k)) {\n";
    $out .= "        @define('LANG_' . \$k, \$v);\n";
    $out .= "    }\n}\n";
    file_put_contents($root . '/languages/tabcalorie_' . $code . '.php', $out);
}
// Default fallback uses Ukrainian (plugin default language).
copy($root . '/languages/tabcalorie_uk.php', $root . '/languages/tabcalorie_default.php');
echo "Generated: uk, ua, en, ru, default\n";
