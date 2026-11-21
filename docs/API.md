---
title: "TabCalorie: API та формати даних"
aliases: [TabCalorie, API, API ТК]
tags: [tabcalorie, majordomo, api]
lang: uk
plugin: tabcalorie
version: 1.4.2
---

# API та формати даних TabCalorie

[[API|Українська]] · [[API.en|English]]

Версія 1.4.2 · 2026-11-21

Цей документ фіксує технічні факти про API «Таблиці калорійності» (ТК),
Nightscout і формати даних плагіна. Він призначений для розробників і для
діагностики. Джерела — [[perplexity_research|Дослідження API]].

> [!WARNING]
> Внутрішній API ТК є **неофіційним**. Він може змінитися без попередження.
> Запис назад у ТК не є підтримуваною функцією.

---

## 1. Загальне

| Параметр | Значення |
|---|---|
| Базовий URL (UA) | `https://www.tablycjakalorijnosti.com.ua` |
| Базовий URL (CZ) | `https://www.kaloricketabulky.cz` |
| Ключ конфігу | `BASE_URL`, `PROVIDER` (`ua`/`cz`/`custom`) |
| Формат | JSON |
| Авторизація | cookies сесії |
| Пароль у запитах | `md5` від UTF-8 пароля |

Помилки повертаються **у JSON як `code != 0` навіть при HTTP 200** — завжди
перевіряйте тіло відповіді.

### Ключі конфігурації v1.2.0

| Ключ | Тип | За замовчуванням | Призначення |
|---|---|---|---|
| `ACTIVITY_ENABLED` | 0/1 | `1` | Імпорт активності з `data.activities` щоденника ТК. |
| `LOG_RETENTION_DAYS` | дні | `30` | Ротація `tabcalorie_sync_log` за віком. |
| `LOG_MAX_ROWS` | рядки | `2000` | Ротація `tabcalorie_sync_log` за кількістю рядків. |
| `WRITE_QUEUE_TTL_DAYS` | дні | `7` | Очищення `tabcalorie_write_queue` за TTL. |

---

## 2. Авторизація

### `POST /login/create?format=json&voucher=false`

Тіло запиту:

```json
{
  "email": "user@example.org",
  "password": "<md5 hash of utf-8 password>"
}
```

Успішна відповідь містить `code: 0`. Сесійні cookies повертаються у заголовку
`Set-Cookie`. Плагін зберігає **всі** пари (UA: будь-які; CZ: `JSESSIONID`;
UA SDK згадує `kaloricketabulky_token`) і надсилає їх назад у заголовку `Cookie`.

Приклад (псевдокод):

```php
$hash = md5($password); // UTF-8
$resp = httpPost($baseUrl . '/login/create?format=json&voucher=false',
    ['email' => $email, 'password' => $hash]);
$body = json_decode($resp['body'], true);
if ($body['code'] !== 0) { /* помилка входу */ }
$cookie = join('; ', $resp['setCookiePairs']);
```

---

## 3. Читання щоденника

### `GET /user/diary/ДД.ММ.РРРР/get?format=json`

Заголовки: `Accept: application/json`, `Cookie: <сесія>`.

Формат дати у шляху — **`ДД.ММ.РРРР`** (наприклад `20.11.2026`).

Структура успішної відповіді (спрощено):

```json
{
  "code": 0,
  "data": {
    "times": [
      {
        "id": "1",
        "title": "Сніданок",
        "notes": "",
        "energyTotal": 0,
        "foodstuff": [
          {
            "title": "Продукт",
            "unit": "166 x 1 g",
            "energy": 0,
            "energyUnit": "kcal",
            "protein": 0,
            "carbohydrate": 0,
            "fat": 0,
            "fiber": 0,
            "sugar": 0,
            "salt": 0,
            "saturatedFattyAcid": 0,
            "guidType": "...",
            "url": "...",
            "id": "..."
          }
        ]
      }
    ],
    "carbohydrateTotal": 0,
    "sugarTotal": 0,
    "fiberTotal": 0,
    "proteinTotal": 0,
    "fatTotal": 0,
    "energyTotal": 0,
    "foodstuffCount": 0,
    "activities": [
      {
        "id": "act_1",
        "date": "20.11.2026",
        "title": "Прогулянка",
        "type": "walking",
        "activityType": "walking",
        "steps": 3200,
        "energy": 140,
        "duration": 1800
      }
    ]
  }
}
```

### 6 слотів (`times[]`)

| id | Назва (UA) |
|---|---|
| 1 | Сніданок |
| 2 | Перекус (другий сніданок) |
| 3 | Обід |
| 4 | Підвечірок |
| 5 | Вечеря |
| 6 | Друга вечеря |

У внутрішніх структурах MajorDoMo слоти нумеруються 1..6
(`Slot1Carbs` … `Slot6Carbs`). У сирому JSON масив `times` може бути
індексований з 0 — плагін зіставляє за порядком.

### Важливі обмеження API

- **Глікемічного індексу (GI) у відповіді немає.**
- **Нотаток на рівні страви немає** — `notes` існує лише на рівні слота
  (`times[].notes`).

Тому GI і нотатки зберігаються **локально** у таблиці `tabcalorie_foods` і не
перезаписуються синхронізацією.

### Активність (`data.activities`, Health Connect)

> [!NOTE]
> У застосунку ТК увімкнено інтеграцію з **Health Connect**. ТК повертає дані
> активності у полі `data.activities` щоденника: `steps` (кроки), `energy`
> (спалені калорії), `title`, `type`, `activityType`, тривалість тощо. Плагін
> лише **зчитує** ці дані з ТК і нічого не пише в Health Connect — інтеграція
> в ТК одностороння: активність потрапляє в щоденник ТК, а плагін бере її звідти.

За `ACTIVITY_ENABLED=1` (за замовчуванням) активність зберігається у таблиці
`tabcalorie_activities` з унікальним ключем **дата + ID активності**, а добові
суми `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT` агрегуються у `tabcalorie_days`.

---

## 4. Додаткові ендпоїнти (не використовуються за замовчуванням)

| Ендпоїнт | Призначення |
|---|---|
| `/user/diary/summary/.../get` | зведення щоденника |
| `/statistic/summary/.../get` | статистика |
| `/autocomplete/foodstuff-activity-meal?query=` | пошук страв/активностей |
| `/user/foodstuff/add/form/{id}` | форма додавання страви |

> [!TIP]
> З v1.2.0 ці ендпоїнти використовує форма довідника страв: autocomplete шукає
> страву в ТК, а форма додавання повертає сиру відповідь, яка зберігається як
> `SERVICE_JSON` разом зі службовими полями страви.

---

## 5. Запис назад у ТК (неофіційний)

> [!WARNING]
> Не є підтримуваною функцією. Використовуйте тільки з явним погодженням.

### `POST /user/foodstuff/add?format=json`

Тіло (приклад):

```json
{
  "guid": "...",
  "title": "...",
  "url": "...",
  "date": "20.11.2026",
  "multiplier": 1,
  "diaryTimeGuid": "...",
  "unitGuid": "0000000000000001",
  "unitOptions": []
}
```

Видалення: `GET /user/diary/foodstuff/delete/{id}`.

З v1.2.0 значення `guid`, `url`, `unitGuid`, `unitOptions` і `multiplier` у тілі
запиту беруться зі службових полів страви у `tabcalorie_foods` — `FOOD_ID`,
`FOOD_URL`, `UNIT_GUID`, `UNIT_OPTIONS`, `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT`,
`SERVICE_JSON` — а не з жорстко заданих констант.

Умови плагіна:

- `WRITEBACK_ENABLED=1` (за замовчуванням `0`);
- `WRITEBACK_CONFIRM=1` — явне підтвердження;
- черга `tabcalorie_write_queue`;
- повільний темп, паузи, backoff, без паралельних запитів.

Бібліотеки явно позначають такий доступ як **read-only** і застерігають:
«don't hammer the site… keep polling slow and infrequent».

---

## 6. Захист від блокування

| Механізм | Реалізація |
|---|---|
| Інтервал | `SYNC_INTERVAL_MIN`, мінімум **15 хв** (за замовчуванням 60) |
| Паузи | ~300–500 мс між записами/днями |
| Backoff | exponential при помилках |
| Single-flight | блокування, що забороняє паралельні цикли |
| Таймаути | cURL connect/response timeout |
| Circuit breaker | `CIRCUIT_UNTIL` після серії помилок |
| User-Agent | коректний ідентифікатор клієнта |
| Паралельність | **жодних** одночасних запитів |

---

## 7. Nightscout

### `POST /api/v1/treatments.json`

Базовий push (опційний, `NS_ENABLED=0` за замовчуванням).

Заголовок: `API-SECRET: sha1(NS_API_SECRET)` (у нижньому регістрі).

Тіло (масив записів):

```json
[
  {
    "eventType": "Meal Bolus",
    "created_at": "2026-11-20T10:00:00.000Z",
    "carbs": 35,
    "insulin": 3,
    "enteredBy": "TabCalorie-MajorDoMo",
    "notes": "..."
  }
]
```

`eventType` — `Meal Bolus`, якщо вуглеводи > 0, інакше `Correction Bolus`.

---

## 8. Формати плагіна

### 8.1. CSV

| Параметр | Значення |
|---|---|
| Кодування | UTF-8 із BOM (`\xEF\xBB\xBF`) |
| Розділювач | `;` (`CSV_DELIMITER`) |
| Дата | `ДД.ММ.РРРР` |
| Період | день / тиждень / місяць / рік |
| Вміст | добові суми, ХО, опційно деталі страв |
| Секрети | відсутні; можлива анонімізація |

### 8.2. Локальні таблиці

| Таблиця | Ключ / призначення |
|---|---|
| `tabcalorie_entries` | унікальний ключ `date + slot + food_guid + unit_raw`; `source` = `tk`/`manual` |
| `tabcalorie_foods` | довідник на 100 г; локальні GI/нотатки + службові поля `FOOD_ID`, `FOOD_URL`, `UNIT_GUID` (`0000000000000001`), `UNIT_OPTIONS`, `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT` (1..6), `SERVICE_JSON` |
| `tabcalorie_days` | добові підсумки; з v1.2.0 також `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT` |
| `tabcalorie_activities` | активність із `data.activities`; унікальний ключ `дата + ID активності` |
| `tabcalorie_sync_log` | час, тип, результат, HTTP-код, кількість рядків; ротація `LOG_RETENTION_DAYS` (30) / `LOG_MAX_ROWS` (2000) |
| `tabcalorie_write_queue` | черга записів із підтвердженням; TTL `WRITE_QUEUE_TTL_DAYS` (7) |

### 8.3. Об'єкт `TabCalorie` (клас `STabCalorie`)

`LastDate`, `Partial`, `FilledSlots`, `Slot1Carbs..Slot6Carbs`,
`Slot1Kcal..Slot6Kcal`, `GroupMainCarbs` (1+3+5), `GroupSnacksCarbs` (2+4+6),
`GroupDayCarbs`, `TotalCarbs`, `TotalKcal`, `TotalProtein`, `TotalFat`,
`TotalFiber`, `TotalSugar`, `TotalXE`, `GI`, `Notes`, `Updated`.

Властивості, додані у v1.2.0:

| Властивість | Опис |
|---|---|
| `status` | `1`, коли інтеграція здорова; `0` при будь-якій інтеграційній проблемі (наявний `LAST_ERROR`, відкритий circuit breaker) або коли вимкнено фоновий цикл (`SYNC_ENABLED=0` чи `cycle_tabcalorieRun` старший за ~10 хв). |
| `raw` | JSON із усіма даними від початку останньої доби, за яку є хоча б один запис: сирий щоденник ТК + нормалізовані записи. |
| `ActivitySteps`, `ActivityKcal`, `ActivityCount` | Агрегат активності (кроки, спалені ккал, кількість активностей) за останню заповнену добу. |

Читання: `gg('TabCalorie.TotalCarbs')`, `gg('TabCalorie.status')`.

### 8.4. Ротація логів і TTL черги (v1.2.0)

- `tabcalorie_sync_log`: рядки старші за `LOG_RETENTION_DAYS` (30) або ті, що
  перевищують `LOG_MAX_ROWS` (2000), видаляються під час cleanup (спочатку
  найстаріші).
- `tabcalorie_write_queue`: рядки старші за `WRITE_QUEUE_TTL_DAYS` (7 днів)
  видаляються автоматично під час cleanup.

> [!NOTE]
> Добові норми `CALORIES_TARGET`, `PROTEIN_TARGET`, `FAT_TARGET`, `CARBS_TARGET`
> і `MEAL_NORM_SHARE` використовуються для розрахунку норми кожного прийому їжі
> та прогресбарів у вкладці **Огляд** (див. [[USAGE#2.5. Норми споживання та прогресбари|Використання]]).

---

## 9. Мови

`uk` (дефолт), `en`, `ru`. Файли `languages/tabcalorie_{default,en,uk,ru}.php`
містять `$dictionary` і `define('LANG_'.$k, $v)`.

---

## 10. Посилання

- Репозиторій: https://github.com/mambursoft/majordomo_TabCalorie
- Дослідження: [[perplexity_research|Дослідження API]]
- Мануал ТК: https://www.tablycjakalorijnosti.com.ua/manual
- Nightscout API: https://github.com/nightscout/documentation
- Неофіційні SDK: https://github.com/tomasvotava/kaloricketabulky ·
  https://github.com/francbohuslav/kaloricke-tabulky-api
- Карта контенту: [[HOME|HOME (MOC)]]
