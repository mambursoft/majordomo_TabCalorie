---
title: "Инструкция по установке TabCalorie"
aliases: [TabCalorie, INSTALL.ru, Установка]
tags: [tabcalorie, majordomo, settings]
lang: ru
plugin: tabcalorie
version: 1.3.0
---

# Установка TabCalorie

[[INSTALL|Українська]] · [[INSTALL.en|English]] · [[INSTALL.ru|Русский]]

Версия 1.3.0 · 2026-11-20

Это руководство описывает установку плагина MajorDoMo `tabcalorie` двумя
способами: через Market и вручную из архива `.tgz`.

> [!WARNING]
> **Напоминание:** TabCalorie НЕ является медицинским прибором и не даёт
> медицинских рекомендаций. См. [[README.ru|TabCalorie]].

---

## 1. Перед началом

### 1.1. Требования к окружению

| Требование | Значение |
|---|---|
| MajorDoMo | установка с модульным механизмом (`modules/`, `templates/`, `languages/`, `scripts/`, `img/modules/`) |
| PHP | **7.3–8.2** (минимум 7.3) |
| Расширения PHP | `curl`, `json`, `mbstring` |
| БД | MySQL/MariaDB, доступ через MajorDoMo (`DBSafe`) |
| Сеть | HTTPS, валидная проверка TLS, исходящий доступ к порталу ТК |
| Права | администратор MajorDoMo, права `control_access` |

### 1.2. Подготовьте учётную запись ТК

1. Проверьте email, под которым вы авторизованы в приложении «Таблица калорийности»
   (меню **Мой профиль**).
2. Если вы входили через Google/Apple/Facebook и не имеете отдельного пароля:
   откройте `https://www.tablycjakalorijnosti.com.ua/login`, введите email и
   нажмите **«Забыли пароль?»**. Письмо с паролем для прямого входа не прервёт
   работу приложения на телефоне.
3. Для чешского портала используйте `https://www.kaloricketabulky.cz`.

> [!WARNING]
> Не храните реальный пароль в репозитории или текстовых файлах — он вводится
> только в защищённой админке модуля и маскируется.

---

## 2. Вариант A — установка через Market MajorDoMo

1. Войдите в админку MajorDoMo как администратор.
2. Откройте **Market / Каталог модулей** (или `/admin.php?p=market`).
3. В поиске введите `TabCalorie`.
4. Нажмите **Установить** и подтвердите действие.
5. Market распакует файлы в структуру MajorDoMo и запустит регистрацию модуля.
6. Перейдите в раздел **Модули** и убедитесь, что `tabcalorie` активен. Если нет — активируйте вручную.
7. Откройте настройки модуля (раздел 4).

---

## 3. Вариант B — ручная установка из `.tgz`

### 3.1. Получите пакет

Соберите пакет из исходного репозитория или загрузите готовый релиз:

```bash
git clone https://github.com/mambursoft/majordomo_TabCalorie.git
cd majordomo_TabCalorie
python3 build_package.py
```

Результат — `dist/tabcalorie.tgz`.

### 3.2. Распакуйте в корень MajorDoMo

```bash
cd /var/www/html            # путь к корню вашего MajorDoMo
tar -tzf /path/to/tabcalorie.tgz   # просмотрите содержимое перед распаковкой
tar -xzf /path/to/tabcalorie.tgz
```

> [!NOTE]
> `/var/www/html` — типовой путь; используйте реальный корень вашей установки.
> Архив не содержит родительской папки `majordomo_TabCalorie` — файлы сразу
> попадают в `modules/`, `templates/` и т.д.

### 3.3. Проверьте структуру

```text
modules/tabcalorie/          PHP-код модуля (вкл. tabcalorie.class.php)
templates/tabcalorie/        html-шаблоны
languages/tabcalorie_*.php   языковые файлы (uk по умолчанию, en, ru)
scripts/cycle_tabcalorie.php фоновый цикл синхронизации
img/modules/tabcalorie.png   иконка 64×64
modinfo.json                 метаданные для Market
README.md / README.en.md / README.ru.md
LICENSE / NOTICE.md / CHANGELOG.md
docs/                        документация
HOME.md                      карта контента (MOC)
```

### 3.4. Активируйте модуль

1. Админка → **Модули** → найдите `tabcalorie` в списке неактивных.
2. Нажмите **Установить / Активировать**.
3. При установке модуль:
   - регистрируется в `project_modules`;
   - выставляет флаг `cms/modules_installed/tabcalorie.installed`;
   - добавляет строку в таблицу `plugins`;
   - создаёт таблицы БД (см. ниже);
   - создаёт объект `TabCalorie` класса `STabCalorie`;
   - добавляет иконку `img/modules/tabcalorie.png`.

### 3.5. Создаваемые таблицы

| Таблица | Назначение |
|---|---|
| `tabcalorie_entries` | записи блюд (дата, слот, блюдо, количество × единица, БЖУ, GI, заметки, `source`, `synced_at`); уникальный ключ `date + slot + food_guid + unit_raw` |
| `tabcalorie_foods` | справочник блюд на 100 г + служебные поля для записи в ТК (`FOOD_ID`, `FOOD_URL`, `UNIT_GUID`, `UNIT_OPTIONS`, `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT`, `SERVICE_JSON`) |
| `tabcalorie_days` | суточные суммы, включая `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT` |
| `tabcalorie_activities` | активность из `data.activities` ТК; уникальный ключ — дата + ID активности |
| `tabcalorie_sync_log` | журнал синхронизаций без секретов; ротация `LOG_RETENTION_DAYS` / `LOG_MAX_ROWS` |
| `tabcalorie_write_queue` | очередь записей обратно в ТК с подтверждением; TTL `WRITE_QUEUE_TTL_DAYS` |

### 3.6. Настройте фоновый цикл

`scripts/cycle_tabcalorie.php` предназначен для запуска по расписанию MajorDoMo
или системному cron. Минимальный рекомендуемый интервал — **15 минут**,
типичный — 60 минут (`SYNC_INTERVAL_MIN=60`).

Пример записи в cron (подставьте реальный путь):

```cron
*/15 * * * * php /var/www/html/scripts/cycle_tabcalorie.php >/dev/null 2>&1
```

> [!TIP]
> Цикл защищён single-flight блокировкой: параллельные запуски не создают
> одновременных запросов к ТК.

---

## 4. Первоначальная настройка

Откройте админку модуля и заполните ключи конфигурации (хранятся в
`project_modules.DATA`):

| Ключ | Что указать | По умолчанию |
|---|---|---|
| `BASE_URL` | `https://www.tablycjakalorijnosti.com.ua` (или CZ / `custom`) | `https://www.tablycjakalorijnosti.com.ua` |
| `PROVIDER` | `ua`, `cz` или `custom` | `ua` |
| `EMAIL` | email учётной записи ТК | — |
| `PASSWORD` | пароль прямого входа (маскируется) | — |
| `SYNC_ENABLED` | `1` — включить цикл | `1` |
| `SYNC_INTERVAL_MIN` | интервал, мин (≥15) | `60` |
| `SYNC_DAYS` | глубина инкремента, дни | `2` |
| `HOOK_ENABLED` | `0` — хук отключён | `0` |
| `HOOK_PROPERTY` | `Obj.Prop` триггера | — |
| `HOOK_DEBOUNCE_SEC` | дебаунс, сек | `60` |
| `RETENTION_DAYS` | хранение данных | `1825` (5 лет) |
| `ACTIVITY_ENABLED` | `1` — импорт активности из ТК; `0` — отключить | `1` |
| `LOG_RETENTION_DAYS` | ротация журнала по возрасту, дни | `30` |
| `LOG_MAX_ROWS` | ротация журнала по количеству строк | `2000` |
| `WRITE_QUEUE_TTL_DAYS` | TTL очереди записи, дни | `7` |
| `LANGUAGE` | `uk` / `en` / `ru` | `uk` |
| `GRAMS_PER_XE` | г на 1 ХЕ | `10` |
| `CALORIES_TARGET` | суточная норма калорий, ккал | `1800` |
| `PROTEIN_TARGET` | суточная норма белков, г | `90` |
| `FAT_TARGET` | суточная норма жиров, г | `60` |
| `CARBS_TARGET` | суточная норма углеводов, г | `120` |
| `MEAL_NORM_SHARE` | доля основного приёма (10–33, %) | `25` |
| `CSV_DELIMITER` | разделитель CSV | `;` |
| `WRITEBACK_ENABLED` | запись в ТК | `0` (отключена) |
| `WRITEBACK_CONFIRM` | подтверждение записи | `1` |
| `NS_ENABLED` | Nightscout | `0` (отключён) |
| `NS_URL` | база Nightscout | — |
| `NS_API_SECRET` | секрет Nightscout (маскируется) | — |

> [!TIP]
> `MEAL_NORM_SHARE` задаёт долю суточной нормы для основного приёма пищи
> (завтрак/обед/ужин — слоты 1, 3, 5). Остаток нормы делится поровну между
> перекусами (2, 4, 6). Диапазон — 10–33 %, по умолчанию 25%. Если все нормы
> заполнены (не 0), на вкладке **Обзор** для каждого из 6 приёмов пищи
> отображается прогрессбар по углеводам, калориям, белкам и жирам относительно
> рассчитанной нормы приёма; превышение выделяется цветом.

Полное описание с пояснениями — в [[README.ru#Настройки|README.ru]].

### 4.1. Проверка подключения

Нажмите **«Проверить подключение»** в админке. Диагностика выполняет:

- проверку DNS/TLS/HTTP;
- логин `POST /login/create?format=json&voucher=false` (пароль как `md5`);
- получение и проверку cookie;
- пробный `GET /user/diary/ДД.ММ.ГГГГ/get?format=json`;
- парсинг JSON и проверку 6 слотов.

Диагностика **не меняет данные**. Если что-то не так — см.
[[TROUBLESHOOTING.ru|Устранение неисправностей]].

### 4.2. Первый запуск

- [ ] Включите `SYNC_ENABLED=1` (если ещё не включён).
- [ ] Запустите цикл вручную или дождитесь срабатывания расписания.
- [ ] Откройте публичную страницу плагина → вкладка **Обзор**.
- [ ] Убедитесь, что отображается последний заполненный день и корректные суммы.
- [ ] Проверьте вкладку **Диагностика** → последняя успешная синхронизация и журнал.
- [ ] Убедитесь, что прогрессбары норм на вкладке **Обзор** отображаются корректно.

### 4.3. Что нового в v1.2.0

- [ ] Вкладка **Активность**: шаги и сожжённые ккал из `data.activities` ТК,
  `ACTIVITY_ENABLED=1` (см. [[USAGE.ru|Использование]]).
- [ ] Форма блюда во вкладке **Справочник блюд** со служебными полями для записи
  в ТК (см. [[USAGE.ru|Использование]]).
- [ ] Свойства объекта `status`, `raw`, `ActivitySteps`, `ActivityKcal`,
  `ActivityCount` (см. [[API|API и форматы данных]]).
- [ ] Ротация `tabcalorie_sync_log` (`LOG_RETENTION_DAYS=30`, `LOG_MAX_ROWS=2000`)
  и TTL очереди `tabcalorie_write_queue` (`WRITE_QUEUE_TTL_DAYS=7`).

> [!NOTE]
> Health Connect в ТК односторонний: плагин только считывает активность из
> дневника ТК и ничего не пишет в Health Connect.

---

## 5. Обновление

1. Сделайте резервную копию БД (таблиц `tabcalorie_*`) и конфигурации.
2. Соберите новый `.tgz` и распакуйте поверх текущей установки:
   ```bash
   tar -xzf tabcalorie.tgz -C /var/www/html
   ```
3. Откройте админку — модуль применит миграции таблиц.
4. Проверьте **Диагностику** и вкладку **Обзор**.
5. Конфигурация в `project_modules.DATA` сохраняется.

---

## 6. Удаление

1. Админка → **Модули** → деактивируйте `tabcalorie`.
2. При желании удалите таблицы `tabcalorie_entries`, `tabcalorie_foods`,
   `tabcalorie_days`, `tabcalorie_activities`, `tabcalorie_sync_log`,
   `tabcalorie_write_queue`.
3. Удалите файлы `modules/tabcalorie/`, `templates/tabcalorie/`,
   `languages/tabcalorie_*.php`, `scripts/cycle_tabcalorie.php`,
   `img/modules/tabcalorie.png`.
4. Конфиг в `project_modules.DATA` очистите вручную, если он больше не нужен.
5. Уберите запись cron для `cycle_tabcalorie.php`.

---

## 7. Частые вопросы по установке

**Нужно ли создавать таблицы вручную?**
Нет. Модуль создаёт их автоматически при установке (формат heredoc
`table: FIELD type...`, зеркало в `modules/tabcalorie/db_install.php`).

**Почему после распаковки модуль не виден?**
Убедитесь, что распаковали в корень MajorDoMo, а не в подпапку, и что
`modules/tabcalorie/tabcalorie.class.php` существует. Обновите кеш модулей в админке.

**Можно ли установить на PHP 7.3?**
Да, 7.3 — минимальная поддерживаемая версия; проверено до 8.2.

**Где взять релиз?**
https://github.com/mambursoft/majordomo_TabCalorie/releases

См. также [[HOME|HOME (MOC)]].
