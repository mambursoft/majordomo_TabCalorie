---
title: "Using TabCalorie"
aliases: [TabCalorie, USAGE.en, Overview]
tags: [tabcalorie, majordomo, docs]
lang: en
plugin: tabcalorie
version: 1.3.0
---

# Using TabCalorie

[[USAGE|Українська]] · [[USAGE.en|English]] · [[USAGE.ru|Русский]]

Version 1.3.0 · 2026-11-20

This document describes the plugin interface after
[[INSTALL.en|installation]] and configuration.

> [!WARNING]
> TabCalorie is an informational tool, **not a medical device**. All insulin
> and treatment decisions are made by a doctor.

---

## 1. Overview

The public plugin page is built by `run()` and the template
`templates/tabcalorie/tabcalorie.html`. It is responsive (CSS media queries) and
works on mobile and desktop.

The interface language is set by the `LANGUAGE` key (`uk` default, `en`, `ru`).

The plugin has seven tabs:

| Tab | Purpose |
|---|---|
| **Overview** | main — current carbohydrate intake snapshot and target progress bars |
| **Activity** | steps and burned kcal per day plus a 7-day overview (see section 11) |
| **History** | dynamics and comparison over a period |
| **Food reference** | local food list, food form and TK autocomplete search |
| **Export** | CSV download (now with steps and kcal) |
| **Diagnostics** | system state and logs |
| **Instructions** | uk/en/ru help |

---

## 2. Overview tab

Overview shows data for the **last filled day**. This matters: if only breakfast
is filled today, lunch will be empty — not pulled from yesterday. The plugin
never "skips" to another day for a slot.

### 2.1. What is shown

- Date (`LastDate`) and partially-filled flag (`Partial`).
- Number of filled slots out of 6 (`FilledSlots`).
- Six meals with carbs (g) and calories:
  1. Breakfast — 2. Snack — 3. Lunch — 4. Afternoon snack — 5. Dinner — 6. Second dinner.
- Groups:
  - **Main meals** (1+3+5) — `GroupMainCarbs`;
  - **Snacks** (2+4+6) — `GroupSnacksCarbs`;
  - **Daily total** — `GroupDayCarbs`.
- Daily totals: carbs, calories, protein, fat, fiber, sugar, bread units, GI.
- Target (`CARBS_TARGET`), % achieved and remaining allowance.
- Target progress bars for each of the 6 meals (see section 2.5).

### 2.2. Bread units (XE)

`TotalXE` = `TotalCarbs / GRAMS_PER_XE`. By default 1 XE = 10 g carbs. If your
doctor counts 12 g — change `GRAMS_PER_XE` in settings.

### 2.3. Typical chart

The **"Create typical chart"** button generates a consumption chart (breakfast,
lunch, dinner, daily total) using the standard MajorDoMo mechanism (Highcharts
or pChart). It is built from the `TabCalorie` object and the `tabcalorie_days` table.

### 2.4. MajorDoMo object `TabCalorie`

Class `STabCalorie`. Read properties via `gg('TabCalorie.Property')`, e.g.:

```php
$carbs = gg('TabCalorie.TotalCarbs');
$xe    = gg('TabCalorie.TotalXE');
$date  = gg('TabCalorie.LastDate');
```

| Property | Description |
|---|---|
| `LastDate` | Date of the last filled day |
| `Partial` | Partially filled day flag |
| `FilledSlots` | Number of filled slots (0–6) |
| `Slot1Carbs` … `Slot6Carbs` | Carbs (g) per slot |
| `Slot1Kcal` … `Slot6Kcal` | Calories per slot |
| `GroupMainCarbs` | 1+3+5 |
| `GroupSnacksCarbs` | 2+4+6 |
| `GroupDayCarbs` | Daily carb total |
| `TotalCarbs`, `TotalKcal`, `TotalProtein`, `TotalFat`, `TotalFiber`, `TotalSugar`, `TotalXE`, `GI`, `Notes`, `Updated` | Totals and metadata |
| `status` | `1` when the integration is healthy; `0` on any integration problem (`LAST_ERROR` set, an open circuit breaker) or when the background cycle is disabled (`SYNC_ENABLED=0` or `cycle_tabcalorieRun` older than ~10 minutes). |
| `raw` | JSON with all data from the beginning of the last day that has at least one record: raw TK diary plus normalized entries. |
| `ActivitySteps`, `ActivityKcal`, `ActivityCount` | Activity aggregates for the last filled day (steps, burned kcal, count). |

> [!NOTE]
> See section 13 for `status`/`raw` and section 14 for log rotation and queue TTL.

You can use these properties in MajorDoMo scenarios, e.g. to send a notification
when the target is exceeded.

### 2.5. Intake targets and progress bars

Daily targets are set with the following configuration keys:

| Key | Default | Purpose |
|---|---|---|
| `CALORIES_TARGET` | `1800` | Daily calorie target, kcal |
| `PROTEIN_TARGET` | `90` | Daily protein target, g |
| `FAT_TARGET` | `60` | Daily fat target, g |
| `CARBS_TARGET` | `120` | Daily carbohydrate target, g |
| `MEAL_NORM_SHARE` | `25` (range 10–33, %) | Share of the daily target for a main meal (breakfast/lunch/dinner); the rest of the target is split equally between the snacks (slots 2, 4, 6) |

Main-meal target = `daily target × MEAL_NORM_SHARE / 100`; snack target =
`daily target × (100 − MEAL_NORM_SHARE) / 300`. With `MEAL_NORM_SHARE=25`, main
meals get 25% of the daily target each and snacks get ~8.33% each.

> [!IMPORTANT]
> When the targets are filled in (non-zero), the **Overview** tab shows a progress
> bar for carbs, calories, protein and fat for each of the 6 meals relative to the
> calculated per-meal target; exceeding a target is highlighted in color.

---

## 3. History tab

- Daily totals for a selected period from `tabcalorie_days`.
- Comparison with previous days/weeks.
- Trend and deviation from target (`CARBS_TARGET`).
- Drill-down to a specific day (entries from `tabcalorie_entries`).

Data comes from the local DB, so history is available even without a TK connection.

---

## 4. Food reference tab

The TK API **does not return glycemic index (GI) or per-dish notes** (notes exist
only at slot level). Therefore the plugin keeps its own reference in
`tabcalorie_foods`.

- Unique foods with a `guid` field.
- Nutrition values **per 100 g**: protein, fat, carbs, fiber, sugar, calories.
- Editable `GI` and `Notes` fields.
- Local entries with `source=manual` can be added — they are **not** synced back to TK.

> [!NOTE]
> Local GI/note edits are preserved and are not overwritten by automatic sync
> (manual-edit protection).

### 4.1. Food form and service fields (v1.2.0)

The **Food reference** tab has an add/edit food form:

- [ ] name, ID, URL, unit, grams, slot (1..6);
- [ ] macros/kcal per 100 g, GI, notes;
- [ ] food search via TK autocomplete;
- [ ] service fields: `FOOD_ID` (guid for the API), `FOOD_URL` (`potraviny/...` path),
  `UNIT_GUID` (default `0000000000000001`), `UNIT_OPTIONS` (JSON of available
  units with multipliers), `DEFAULT_MULTIPLIER` (default grams), `DEFAULT_SLOT`
  (1..6), `SERVICE_JSON` (raw TK form response).

> [!TIP]
> Write-back to TK uses these food values instead of hardcoded ones.

---

## 5. Export tab

Produces a CSV with the user's personal data — handle with care.

| Parameter | Value |
|---|---|
| Period | day / week / month / year |
| Encoding | UTF-8 with BOM (opens correctly in Excel) |
| Delimiter | `;` (`CSV_DELIMITER`, Ukrainian Excel friendly) |
| Date format | `DD.MM.YYYY` |
| Content | daily totals, bread units, steps and burned kcal, optionally dish details |
| Security | no secrets; optional anonymisation |

Export is protected by `control_access` rights. Use the anonymised variant
(no name, email, etc.) when sharing the data.

---

## 6. Diagnostics tab

- Result of **"Check connection"**: DNS/TLS/HTTP, login, cookies, endpoint, JSON parsing.
- Time of last successful sync (`LAST_SYNC`).
- Last error (`LAST_ERROR`, no secrets).
- Circuit breaker state (`CIRCUIT_UNTIL`).
- `tabcalorie_sync_log` journal: time, type, result, HTTP code, row count.
- Explanation of common errors and a link to the "Forgot password?" instructions.

---

## 7. Instructions tab

Short instructions in Ukrainian, English and Russian: how to get a direct-login
password, how to configure the module, how to read the report, where the data is
stored.

---

## 8. How sync works

1. Background cycle `scripts/cycle_tabcalorie.php` runs on a schedule
   (recommended 15–60 min; `SYNC_INTERVAL_MIN`, minimum 15).
2. Login: `POST /login/create?format=json&voucher=false` with `md5(password)`;
   cookies from `Set-Cookie` are stored and returned in `Cookie`.
3. For each of the last `SYNC_DAYS` days:
   `GET /user/diary/DD.MM.YYYY/get?format=json`.
4. 6 slots, daily totals (`carbohydrateTotal`, `sugarTotal`, `fiberTotal`,
   `proteinTotal`, `fatTotal`, `energyTotal`) and `data.activities` are parsed
   (with `ACTIVITY_ENABLED=1`).
5. Entries are stored idempotently by unique key
   `date + slot + food_guid + unit_raw` (`INSERT ... ON DUPLICATE KEY UPDATE`).
6. `tabcalorie_days` (including `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT`),
   `tabcalorie_activities`, the `TabCalorie` object and `tabcalorie_sync_log`
   are updated.

### Anti-blocking

- one request at a time, no parallel calls;
- ~300–500 ms pauses between records;
- exponential backoff on errors;
- single-flight lock (never two cycles at once);
- timeouts and response size limits;
- circuit breaker (`CIRCUIT_UNTIL`) after repeated errors;
- proper `User-Agent`.

---

## 9. Extra features

- **Hook**: when `HOOK_PROPERTY` changes, a forced refresh runs with
  `HOOK_DEBOUNCE_SEC` debounce (loop protection).
- **Nightscout**: with `NS_ENABLED=1` a basic push to `/api/v1/treatments.json`
  (`API-SECRET` header = `sha1` of `NS_API_SECRET`). Disabled by default.
- **Write-back to TK**: with `WRITEBACK_ENABLED=1` and `WRITEBACK_CONFIRM=1`
  entries go to the `tabcalorie_write_queue`. Unofficial API, disabled by default.
- **Retention**: rows older than `RETENTION_DAYS` (1825 days) are purged daily.
- **Activity**: `ACTIVITY_ENABLED=1` — import `data.activities` (see section 11).
- **Log rotation**: `LOG_RETENTION_DAYS=30` and `LOG_MAX_ROWS=2000` for
  `tabcalorie_sync_log`.
- **Queue TTL**: `WRITE_QUEUE_TTL_DAYS=7` for `tabcalorie_write_queue`.

---

## 10. Tips

- Do not rely on the plugin as the only data source — TK remains the primary diary.
- Check **Diagnostics** after changing your TK password.
- Generate an anonymised CSV when sharing data.
- If the API is temporarily down, data stays in the local DB.

---

## 11. Activity tab (v1.2.0)

The TK app has Health Connect integration enabled, so the TK diary returns a
`data.activities` field with activity records: `steps`, `energy` (burned kcal),
`title`, `type`, `activityType`, duration, etc.

With `ACTIVITY_ENABLED=1` (default), the plugin:

- [ ] stores activities in `tabcalorie_activities` (unique key — date + activity ID);
- [ ] aggregates `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT` in `tabcalorie_days`;
- [ ] shows the **Activity** tab: a daily slice and a 7-day overview (steps, kcal);
- [ ] adds steps and burned kcal to the CSV export.

> [!WARNING]
> Health Connect in TK is one-way: activity flows into the TK diary and the
> plugin only reads it there. The plugin writes nothing to Health Connect.

## 12. Food service fields for write-back (v1.2.0)

See section 4.1. The fields `FOOD_ID`, `FOOD_URL`, `UNIT_GUID`, `UNIT_OPTIONS`,
`DEFAULT_MULTIPLIER`, `DEFAULT_SLOT`, `SERVICE_JSON` are stored in
`tabcalorie_foods`; write-back to TK takes the guid, URL, unit, multiplier and
slot from them instead of hardcoded values.

## 13. Status and raw data: `status`, `raw` (v1.2.0)

| Property | Description |
|---|---|
| `status` | `1` when the integration is healthy; `0` on any integration problem (`LAST_ERROR` set, an open circuit breaker) or when the background cycle is disabled (`SYNC_ENABLED=0` or `cycle_tabcalorieRun` older than ~10 minutes). |
| `raw` | JSON with all data from the beginning of the last day that has at least one record: raw TK diary plus normalized entries. |
| `ActivitySteps`, `ActivityKcal`, `ActivityCount` | Activity aggregates for the last filled day. |

## 14. Log rotation and queue TTL (v1.2.0)

- [ ] `LOG_RETENTION_DAYS` (30) — delete `tabcalorie_sync_log` rows older than N days.
- [ ] `LOG_MAX_ROWS` (2000) — cap on `tabcalorie_sync_log` rows; oldest extra rows are removed.
- [ ] `WRITE_QUEUE_TTL_DAYS` (7) — `tabcalorie_write_queue` rows older than the
  TTL are deleted automatically during cleanup.

---

See also [[TROUBLESHOOTING.en|Troubleshooting]] and
[[API.en|API and data formats]] and [[HOME|HOME (MOC)]].
