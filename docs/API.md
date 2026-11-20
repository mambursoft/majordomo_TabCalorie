# API та формати даних TabCalorie

[Українська](API.md) · [English](API.en.md)

Версія 1.0.0 · 2026-11-20

Цей документ фіксує технічні факти про API «Таблиці калорійності» (ТК),
Nightscout і формати даних плагіна. Він призначений для розробників і для
діагностики. Джерела — [perplexity_research.md](../perplexity_research.md).

> ⚠️ Внутрішній API ТК є **неофіційним**. Він може змінитися без попередження.
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
    "foodstuffCount": 0
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

---

## 4. Додаткові ендпоїнти (не використовуються за замовчуванням)

| Ендпоїнт | Призначення |
|---|---|
| `/user/diary/summary/.../get` | зведення щоденника |
| `/statistic/summary/.../get` | статистика |
| `/autocomplete/foodstuff-activity-meal?query=` | пошук страв/активностей |
| `/user/foodstuff/add/form/{id}` | форма додавання страви |

---

## 5. Запис назад у ТК (неофіційний)

> ⚠️ Не є підтримуваною функцією. Використовуйте тільки з явним погодженням.

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
| `tabcalorie_foods` | довідник на 100 г; локальні GI/нотатки |
| `tabcalorie_days` | добові підсумки |
| `tabcalorie_sync_log` | час, тип, результат, HTTP-код, кількість рядків |
| `tabcalorie_write_queue` | черга записів із підтвердженням |

### 8.3. Об'єкт `TabCalorie` (клас `STabCalorie`)

`LastDate`, `Partial`, `FilledSlots`, `Slot1Carbs..Slot6Carbs`,
`Slot1Kcal..Slot6Kcal`, `GroupMainCarbs` (1+3+5), `GroupSnacksCarbs` (2+4+6),
`GroupDayCarbs`, `TotalCarbs`, `TotalKcal`, `TotalProtein`, `TotalFat`,
`TotalFiber`, `TotalSugar`, `TotalXE`, `GI`, `Notes`, `Updated`.

Читання: `gg('TabCalorie.TotalCarbs')`.

---

## 9. Мови

`uk` (дефолт), `en`, `ru`. Файли `languages/tabcalorie_{default,en,uk,ru}.php`
містять `$dictionary` і `define('LANG_'.$k, $v)`.

---

## 10. Посилання

- Репозиторій: https://github.com/mambursoft/majordomo_TabCalorie
- Дослідження: [perplexity_research.md](../perplexity_research.md)
- Мануал ТК: https://www.tablycjakalorijnosti.com.ua/manual
- Nightscout API: https://github.com/nightscout/documentation
- Неофіційні SDK: https://github.com/tomasvotava/kaloricketabulky ·
  https://github.com/francbohuslav/kaloricke-tabulky-api
