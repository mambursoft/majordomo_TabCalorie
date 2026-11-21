---
title: "TabCalorie: API and data formats"
aliases: [TabCalorie, API.en, TK API]
tags: [tabcalorie, majordomo, api]
lang: en
plugin: tabcalorie
version: 1.4.2
---

# TabCalorie API and data formats

[[API|Українська]] · [[API.en|English]]

Version 1.4.2 · 2026-11-21

This document records the technical facts about the "Calorie Table" (TK) API,
Nightscout and the plugin's data formats. It is intended for developers and
diagnostics. Sources — [[perplexity_research|API research]].

> [!WARNING]
> The internal TK API is **unofficial**. It may change without notice.
> Write-back to TK is not a supported feature.

---

## 1. General

| Parameter | Value |
|---|---|
| Base URL (UA) | `https://www.tablycjakalorijnosti.com.ua` |
| Base URL (CZ) | `https://www.kaloricketabulky.cz` |
| Config keys | `BASE_URL`, `PROVIDER` (`ua`/`cz`/`custom`) |
| Format | JSON |
| Authorization | session cookies |
| Password in requests | `md5` of the UTF-8 password |

Errors are returned **in JSON as `code != 0` even with HTTP 200** — always check
the response body.

### Configuration keys added in v1.2.0

| Key | Type | Default | Purpose |
|---|---|---|---|
| `ACTIVITY_ENABLED` | 0/1 | `1` | Import activity from the diary's `data.activities`. |
| `LOG_RETENTION_DAYS` | days | `30` | `tabcalorie_sync_log` rotation by age. |
| `LOG_MAX_ROWS` | rows | `2000` | `tabcalorie_sync_log` rotation by row count. |
| `WRITE_QUEUE_TTL_DAYS` | days | `7` | `tabcalorie_write_queue` cleanup by TTL. |

---

## 2. Authentication

### `POST /login/create?format=json&voucher=false`

Request body:

```json
{
  "email": "user@example.org",
  "password": "<md5 hash of utf-8 password>"
}
```

A successful response contains `code: 0`. Session cookies are returned in the
`Set-Cookie` header. The plugin stores **all** pairs (UA: any; CZ: `JSESSIONID`;
UA SDK mentions `kaloricketabulky_token`) and sends them back in the `Cookie` header.

Example (pseudocode):

```php
$hash = md5($password); // UTF-8
$resp = httpPost($baseUrl . '/login/create?format=json&voucher=false',
    ['email' => $email, 'password' => $hash]);
$body = json_decode($resp['body'], true);
if ($body['code'] !== 0) { /* login error */ }
$cookie = join('; ', $resp['setCookiePairs']);
```

---

## 3. Reading the diary

### `GET /user/diary/DD.MM.YYYY/get?format=json`

Headers: `Accept: application/json`, `Cookie: <session>`.

Path date format — **`DD.MM.YYYY`** (e.g. `20.11.2026`).

Simplified successful response:

```json
{
  "code": 0,
  "data": {
    "times": [
      {
        "id": "1",
        "title": "Breakfast",
        "notes": "",
        "energyTotal": 0,
        "foodstuff": [
          {
            "title": "Food",
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
        "title": "Walking",
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

### 6 slots (`times[]`)

| id | Name (UA) |
|---|---|
| 1 | Сніданок (Breakfast) |
| 2 | Перекус / другий сніданок (Snack) |
| 3 | Обід (Lunch) |
| 4 | Підвечірок (Afternoon snack) |
| 5 | Вечеря (Dinner) |
| 6 | Друга вечеря (Second dinner) |

In MajorDoMo internal structures slots are numbered 1..6
(`Slot1Carbs` … `Slot6Carbs`). In raw JSON the `times` array may be 0-indexed —
the plugin maps by order.

### Important API limitations

- **No glycemic index (GI) in the response.**
- **No per-dish notes** — `notes` exists only at slot level (`times[].notes`).

Therefore GI and notes are stored **locally** in `tabcalorie_foods` and are not
overwritten by sync.

### Activity (`data.activities`, Health Connect)

> [!NOTE]
> The TK app has **Health Connect** integration enabled. TK returns activity
> data in the diary's `data.activities` field: `steps`, `energy` (burned kcal),
> `title`, `type`, `activityType`, duration, etc. The plugin only **reads** these
> data from TK and writes nothing to Health Connect — the TK integration is
> one-way: activity flows into the TK diary and the plugin picks it up there.

With `ACTIVITY_ENABLED=1` (default), activities are stored in
`tabcalorie_activities` with a unique key of **date + activity ID**, and the
daily sums `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT` are aggregated in
`tabcalorie_days`.

---

## 4. Additional endpoints (not used by default)

| Endpoint | Purpose |
|---|---|
| `/user/diary/summary/.../get` | diary summary |
| `/statistic/summary/.../get` | statistics |
| `/autocomplete/foodstuff-activity-meal?query=` | food/activity search |
| `/user/foodstuff/add/form/{id}` | food add form |

> [!TIP]
> Since v1.2.0 these endpoints are used by the food-reference form: autocomplete
> searches for a food in TK, and the add form returns a raw response stored as
> `SERVICE_JSON` along with the food's service fields.

---

## 5. Write-back to TK (unofficial)

> [!WARNING]
> Not a supported feature. Use only with explicit consent.

### `POST /user/foodstuff/add?format=json`

Body (example):

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

Delete: `GET /user/diary/foodstuff/delete/{id}`.

Since v1.2.0 the `guid`, `url`, `unitGuid`, `unitOptions` and `multiplier` values
in the request body come from the food's service fields in `tabcalorie_foods` —
`FOOD_ID`, `FOOD_URL`, `UNIT_GUID`, `UNIT_OPTIONS`, `DEFAULT_MULTIPLIER`,
`DEFAULT_SLOT`, `SERVICE_JSON` — not from hardcoded constants.

Plugin conditions:

- `WRITEBACK_ENABLED=1` (default `0`);
- `WRITEBACK_CONFIRM=1` — explicit confirmation;
- queue `tabcalorie_write_queue`;
- slow pace, pauses, backoff, no parallel requests.

Libraries explicitly label such access as **read-only** and warn:
"don't hammer the site… keep polling slow and infrequent".

---

## 6. Anti-blocking

| Mechanism | Implementation |
|---|---|
| Interval | `SYNC_INTERVAL_MIN`, minimum **15 min** (default 60) |
| Pauses | ~300–500 ms between records/days |
| Backoff | exponential on errors |
| Single-flight | lock preventing parallel cycles |
| Timeouts | cURL connect/response timeout |
| Circuit breaker | `CIRCUIT_UNTIL` after repeated errors |
| User-Agent | proper client identifier |
| Parallelism | **no** concurrent requests |

---

## 7. Nightscout

### `POST /api/v1/treatments.json`

Basic push (optional, `NS_ENABLED=0` by default).

Header: `API-SECRET: sha1(NS_API_SECRET)` (lowercase).

Body (array of records):

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

`eventType` is `Meal Bolus` when carbs > 0, otherwise `Correction Bolus`.

---

## 8. Plugin formats

### 8.1. CSV

| Parameter | Value |
|---|---|
| Encoding | UTF-8 with BOM (`\xEF\xBB\xBF`) |
| Delimiter | `;` (`CSV_DELIMITER`) |
| Date | `DD.MM.YYYY` |
| Period | day / week / month / year |
| Content | daily totals, bread units, optionally dish details |
| Secrets | none; optional anonymisation |

### 8.2. Local tables

| Table | Key / purpose |
|---|---|
| `tabcalorie_entries` | unique key `date + slot + food_guid + unit_raw`; `source` = `tk`/`manual` |
| `tabcalorie_foods` | reference per 100 g; local GI/notes + service fields `FOOD_ID`, `FOOD_URL`, `UNIT_GUID` (`0000000000000001`), `UNIT_OPTIONS`, `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT` (1..6), `SERVICE_JSON` |
| `tabcalorie_days` | daily totals; since v1.2.0 also `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT` |
| `tabcalorie_activities` | activity from `data.activities`; unique key `date + activity ID` |
| `tabcalorie_sync_log` | time, type, result, HTTP code, row count; rotation `LOG_RETENTION_DAYS` (30) / `LOG_MAX_ROWS` (2000) |
| `tabcalorie_write_queue` | queue of writes with confirmation; TTL `WRITE_QUEUE_TTL_DAYS` (7) |

### 8.3. Object `TabCalorie` (class `STabCalorie`)

`LastDate`, `Partial`, `FilledSlots`, `Slot1Carbs..Slot6Carbs`,
`Slot1Kcal..Slot6Kcal`, `GroupMainCarbs` (1+3+5), `GroupSnacksCarbs` (2+4+6),
`GroupDayCarbs`, `TotalCarbs`, `TotalKcal`, `TotalProtein`, `TotalFat`,
`TotalFiber`, `TotalSugar`, `TotalXE`, `GI`, `Notes`, `Updated`.

Properties added in v1.2.0:

| Property | Description |
|---|---|
| `status` | `1` when the integration is healthy; `0` on any integration problem (`LAST_ERROR` set, an open circuit breaker) or when the background cycle is disabled (`SYNC_ENABLED=0` or `cycle_tabcalorieRun` older than ~10 minutes). |
| `raw` | JSON with all data from the beginning of the last day that has at least one record: raw TK diary plus normalized entries. |
| `ActivitySteps`, `ActivityKcal`, `ActivityCount` | Activity aggregates (steps, burned kcal, activity count) for the last filled day. |

Read: `gg('TabCalorie.TotalCarbs')`, `gg('TabCalorie.status')`.

### 8.4. Log rotation and queue TTL (v1.2.0)

- `tabcalorie_sync_log`: rows older than `LOG_RETENTION_DAYS` (30) or exceeding
  `LOG_MAX_ROWS` (2000) are deleted during cleanup (oldest first).
- `tabcalorie_write_queue`: rows older than `WRITE_QUEUE_TTL_DAYS` (7 days) are
  deleted automatically during cleanup.

> [!NOTE]
> The daily targets `CALORIES_TARGET`, `PROTEIN_TARGET`, `FAT_TARGET`,
> `CARBS_TARGET` and `MEAL_NORM_SHARE` are used to calculate each meal's target
> and the progress bars on the **Overview** tab (see [[USAGE.en#2.5. Intake targets and progress bars|Usage]]).

---

## 9. Languages

`uk` (default), `en`, `ru`. Files `languages/tabcalorie_{default,en,uk,ru}.php`
contain `$dictionary` and `define('LANG_'.$k, $v)`.

---

## 10. Links

- Repository: https://github.com/mambursoft/majordomo_TabCalorie
- Research: [[perplexity_research|API research]]
- TK manual: https://www.tablycjakalorijnosti.com.ua/manual
- Nightscout API: https://github.com/nightscout/documentation
- Unofficial SDKs: https://github.com/tomasvotava/kaloricketabulky ·
  https://github.com/francbohuslav/kaloricke-tabulky-api
- Map of content: [[HOME|HOME (MOC)]]
