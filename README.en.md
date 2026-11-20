---
title: "TabCalorie — Local nutrition tracking"
aliases: [TabCalorie, Home, Readme]
tags: [tabcalorie, majordomo, docs]
lang: en
plugin: tabcalorie
version: 1.2.1
---

# TabCalorie

> [!NOTE]
> A MajorDoMo plugin for local carbohydrate tracking based on data from the
> "Calorie Table" app (TK / Dine4Fit / `tablycjakalorijnosti.com.ua`).
> Default documentation language: Ukrainian. Version 1.2.0, 2026-11-20.

[[README|Українська]] · [[README.en|English]] · [[README.ru|Русский]]

---

## ⚠️ Important medical disclaimer

> [!WARNING]
> **TabCalorie is NOT a medical device and provides no medical advice.**
> The plugin does not diagnose, prescribe treatment, calculate or administer
> insulin, and does not replace the "Calorie Table" diary or your endocrinologist's
> advice. All data is for **informational carbohydrate tracking and reporting only**.
> Treatment decisions, insulin doses and nutrition are decided by a **doctor**.
>
> The user's personal data and nutrition data are stored **locally** in your
> MajorDoMo installation. Never publish real data in public repositories,
> screenshots or un-anonymised reports.

---

## Purpose

TabCalorie imports the food diary from the "Calorie Table" cloud portal into a
local MajorDoMo database and shows a convenient carbohydrate breakdown across
6 meal slots:

1. Breakfast
2. Snack (second breakfast)
3. Lunch
4. Afternoon snack
5. Dinner
6. Second dinner

The mobile app only syncs to its own cloud portal; standard integrations
(Health Connect, Google Fit, Garmin, Samsung Health) **do not export** consumed
carbs. The portal's internal JSON API returns the full day structure, which
TabCalorie uses for import (see [[perplexity_research|API research]]).

### Features

- Incremental sync of the last N days + date-range backfill.
- Idempotency: unique key `(date, slot, food guid, unit)` — repeated runs create no duplicates.
- Overview of the **last filled day** with empty slots, not data pulled from another day.
- Local food reference (per 100 g) with local GI and notes (the TK API has neither).
  Service fields (`FOOD_ID`, `FOOD_URL`, `UNIT_GUID`, `UNIT_OPTIONS`,
  `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT`, `SERVICE_JSON`) are used for write-back
  to TK instead of hardcoded values; the **Food reference** tab has an add/edit
  food form and TK autocomplete search.
- Activity from the diary's `data.activities` field (Health Connect data in TK):
  steps, burned kcal, a new **Activity** tab and CSV columns (`ACTIVITY_ENABLED`).
  Health Connect in TK is one-way — the plugin only reads data from there.
- Daily targets (calories, protein, fat, carbs) and progress bars for each of the
  6 meals on the **Overview** tab; exceeding a target is highlighted in color.
- MajorDoMo object `TabCalorie` (class `STabCalorie`) with ready-made aggregates,
  including `status`, `raw`, `ActivitySteps`, `ActivityKcal`, `ActivityCount`.
- `tabcalorie_sync_log` rotation (`LOG_RETENTION_DAYS`, `LOG_MAX_ROWS`) and write
  queue TTL (`WRITE_QUEUE_TTL_DAYS`).
- CSV export (UTF-8 BOM, `;` delimiter) for day / week / month / year.
- Connection diagnostics without changing data.
- Optional basic Nightscout push to `/api/v1/treatments.json`.
- Write-back to TK is **unofficial**, disabled by default and requires confirmation.
- Anti-blocking: interval ≥15 min, pauses, backoff, single-flight, timeouts,
  circuit breaker, no parallel requests.
- Interface in Ukrainian, English and Russian (uk is default).

---

## Requirements

- MajorDoMo with the module mechanism (`modules/`, `templates/`, `languages/`,
  `scripts/`, `img/modules/`).
- PHP **7.3–8.2** (tested; minimum 7.3), extensions `curl`, `json`, `mbstring`,
  access to its own DB (MySQL/MariaDB) via MajorDoMo.
- HTTPS enabled and valid TLS verification on the server.
- A "Calorie Table" account with a direct-login password (see FAQ if you signed
  in via Google/Apple).

---

## Installation

### Option A — MajorDoMo Market

1. In the MajorDoMo admin area open **Market / Module catalog**.
2. Find `TabCalorie` and click **Install**.
3. Confirm table creation and module registration.
4. Open the module settings (below).

### Option B — manually from `.tgz`

The package is structured relative to the MajorDoMo root. Extract into the site root:

```bash
cd /var/www/html
tar -xzf tabcalorie.tgz
```

Verify the extracted files:

```text
modules/tabcalorie/tabcalorie.class.php
templates/tabcalorie/*.html
languages/tabcalorie_*.php
scripts/cycle_tabcalorie.php
img/modules/tabcalorie.png
modinfo.json
```

Then go to admin → **Modules** → active list → **`tabcalorie` → Install**.
The module creates its tables, registers the
`cms/modules_installed/tabcalorie.installed` flag, a row in `plugins`, the
`TabCalorie` object and the icon.

> [!NOTE]
> Build the package with `python3 build_package.py` — output `dist/tabcalorie.tgz`.
> Details: [[INSTALL.en|Installation guide]].

---

## Configuration

Configuration is stored in `project_modules.DATA` (serialized array) and edited
in the module admin page. Passwords and secrets are masked in the UI and never
written to logs or the repository.

| Key | Type / value | Default | Purpose |
|---|---|---|---|
| `BASE_URL` | URL | `https://www.tablycjakalorijnosti.com.ua` | TK portal base URL. For CZ use `https://www.kaloricketabulky.cz`. |
| `PROVIDER` | `ua` \| `cz` \| `custom` | `ua` | Provider. `custom` uses `BASE_URL`. |
| `EMAIL` | string | — | TK account email. |
| `PASSWORD` | string (masked) | — | Direct-login password; sent as `md5` in requests. |
| `SYNC_ENABLED` | 0/1 | `1` | Enable the background cycle. |
| `SYNC_INTERVAL_MIN` | minutes | `60` | Sync interval (minimum **15 min**). |
| `SYNC_DAYS` | days | `2` | How many recent days to refresh per run. |
| `HOOK_ENABLED` | 0/1 | `0` | Force refresh when a MajorDoMo property changes. |
| `HOOK_PROPERTY` | `Obj.Prop` | — | Trigger property for the hook. |
| `HOOK_DEBOUNCE_SEC` | seconds | `60` | Debounce to avoid loops and extra calls. |
| `RETENTION_DAYS` | days | `1825` (5 years) | Data retention; expired rows are purged daily. |
| `ACTIVITY_ENABLED` | 0/1 | `1` | Import activity from the TK diary's `data.activities` (Health Connect data inside TK), enabled by default. |
| `LOG_RETENTION_DAYS` | days | `30` | `tabcalorie_sync_log` rotation: delete rows older than N days. |
| `LOG_MAX_ROWS` | rows | `2000` | `tabcalorie_sync_log` rotation: row cap (oldest rows are removed first). |
| `WRITE_QUEUE_TTL_DAYS` | days | `7` | `tabcalorie_write_queue` cleanup: delete expired rows during cleanup. |
| `LANGUAGE` | `uk` \| `en` \| `ru` | `uk` | Interface language. |
| `GRAMS_PER_XE` | number | `10` | Grams of carbs per bread unit (10 or 12 — per doctor). |
| `CALORIES_TARGET` | kcal | `1800` | Daily calorie target, kcal. |
| `PROTEIN_TARGET` | grams | `90` | Daily protein target, g. |
| `FAT_TARGET` | grams | `60` | Daily fat target, g. |
| `CARBS_TARGET` | grams | `120` | Daily carbohydrate target, g. |
| `MEAL_NORM_SHARE` | percent (10–50) | `25` | Share of the daily target for a main meal (breakfast/lunch/dinner); the rest of the target is split equally between the snacks (slots 2, 4, 6). |
| `CSV_DELIMITER` | char | `;` | CSV delimiter (Excel UA friendly). |
| `WRITEBACK_ENABLED` | 0/1 | `0` | Unofficial write-back to TK — **disabled**. |
| `WRITEBACK_CONFIRM` | 0/1 | `1` | Require explicit confirmation for each write. |
| `NS_ENABLED` | 0/1 | `0` | Nightscout push — **disabled**. |
| `NS_URL` | URL | — | Your Nightscout base (e.g. `https://ns.example.org`). |
| `NS_API_SECRET` | string (masked) | — | API_SECRET; sent as `sha1` in the header. |
| `LAST_SYNC` | timestamp | — | Time of last successful sync (service field). |
| `LAST_ERROR` | string | — | Last error (no secrets). |
| `CIRCUIT_UNTIL` | timestamp | — | Circuit-breaker pause after repeated errors. |

> [!TIP]
> The target for a main meal (breakfast, lunch, dinner) is
> `daily target × MEAL_NORM_SHARE / 100`. The remaining `(100 − MEAL_NORM_SHARE)%`
> of the daily target is split equally between the three snacks (slots 2, 4, 6).
> For example, with `MEAL_NORM_SHARE=25` main meals get 25% of the target each
> and snacks get ~8.33% each.

After saving, click **"Check connection"** — diagnostics without changing data:
DNS/TLS/HTTP, login, cookies, endpoint availability, JSON parsing.

---

## Usage

The public plugin page (`run()` + `templates/tabcalorie/tabcalorie.html`) has seven tabs:

| Tab | What it does |
|---|---|
| **Overview** | Last filled day: 6 slots, meal groups, daily totals, bread units, % of target, remaining allowance, target progress bars. Default tab when configured. |
| **Activity** | Steps and burned calories for the last filled day plus a 7-day overview; data from the TK diary's `data.activities` (Health Connect, read-only). |
| **History** | Daily totals for a period, comparison with previous days, trend and deviation from target. |
| **Food reference** | Local food list with per-100 g values; GI and notes; add/edit food form with service fields and TK autocomplete search. |
| **Export** | CSV for day / week / month / year, UTF-8 BOM, `;` delimiter, `DD.MM.YYYY` dates; now with steps and burned kcal; optional anonymisation. |
| **Diagnostics** | Last sync state, sync log, explanation of common errors, "Check connection" result. |
| **Instructions** | Short uk/en/ru instructions (including "Forgot password?"). |

> [!IMPORTANT]
> When the targets are filled in (non-zero), the **Overview** tab shows a progress
> bar for carbs, calories, protein and fat for each of the 6 meals relative to the
> calculated per-meal target; exceeding a target is highlighted in color.

On **Overview**, the **"Create typical chart"** button generates a consumption
chart using the standard MajorDoMo mechanism (Highcharts/pChart).

### Activity tab

With `ACTIVITY_ENABLED=1` (default), the plugin reads activity records from the
TK diary's `data.activities` field (`steps`, `energy`, `title`, `type`,
`activityType`, duration). Each activity is stored in `tabcalorie_activities`
with a unique key of **date + activity ID**, while the daily sums `STEPS`,
`ACTIVITY_KCAL`, `ACTIVITY_COUNT` go to `tabcalorie_days`.

The tab shows:

- [ ] a daily slice: steps, burned kcal and the list of activities for the last filled day;
- [ ] a 7-day overview: steps and kcal per day;
- [ ] the same steps and burned kcal are added to the CSV export.

> [!WARNING]
> Health Connect in TK is one-way: activity flows into the TK diary and the
> plugin only reads it there. The plugin **writes nothing** to Health Connect.

### Food form in the reference

The **Food reference** tab provides:

- [ ] an add/edit food form: name, ID, URL, unit, grams, slot, macros/kcal per 100 g, GI, notes;
- [ ] food search via TK autocomplete (`/autocomplete/foodstuff-activity-meal?query=`);
- [ ] service fields: `FOOD_ID` (guid for the API), `FOOD_URL` (`potraviny/...` path),
  `UNIT_GUID` (default `0000000000000001`), `UNIT_OPTIONS` (JSON of available
  units with multipliers), `DEFAULT_MULTIPLIER` (default grams), `DEFAULT_SLOT`
  (1..6), `SERVICE_JSON` (raw TK form response).

> [!TIP]
> Write-back to TK uses these food service fields instead of hardcoded values
> (`guid`, `url`, `unitGuid`, `unitOptions`, `multiplier`).

### MajorDoMo object `TabCalorie`

Class `STabCalorie`. Read properties via `gg('TabCalorie.Property')`.

| Property | Description |
|---|---|
| `LastDate` | Date of the last filled day. |
| `Partial` | Flag for a partially filled day. |
| `FilledSlots` | Number of filled slots out of 6. |
| `Slot1Carbs` … `Slot6Carbs` | Carbs (g) for each of the 6 meals. |
| `Slot1Kcal` … `Slot6Kcal` | Calories for each of the 6 meals. |
| `GroupMainCarbs` | Slots 1+3+5 (main meals). |
| `GroupSnacksCarbs` | Slots 2+4+6 (snacks). |
| `GroupDayCarbs` | Daily carb total. |
| `TotalCarbs`, `TotalKcal`, `TotalProtein`, `TotalFat`, `TotalFiber`, `TotalSugar`, `TotalXE`, `GI`, `Notes`, `Updated` | Daily totals, bread units, GI, notes, update time. |
| `status` | `1` when the integration is healthy; `0` on any integration problem (`LAST_ERROR` set, an open circuit breaker) or when the background cycle is disabled (`SYNC_ENABLED=0` or `cycle_tabcalorieRun` older than ~10 minutes). |
| `raw` | JSON with all data from the beginning of the last day that has at least one record: raw TK diary plus normalized entries. |
| `ActivitySteps`, `ActivityKcal`, `ActivityCount` | Activity aggregates (steps, burned kcal, activity count) for the last filled day. |

> [!NOTE]
> `status=0` means any integration problem or a stopped background cycle — check
> `LAST_ERROR`, `CIRCUIT_UNTIL`, `SYNC_ENABLED` and the last `cycle_tabcalorieRun`
> time (no older than ~10 minutes). `raw` is intended for diagnostics and holds
> raw TK data together with normalized entries.

### Database maintenance (v1.2.0)

- [ ] `tabcalorie_sync_log`: rotation by `LOG_RETENTION_DAYS` (30 days) and
  `LOG_MAX_ROWS` (2000 rows); oldest rows are removed first.
- [ ] `tabcalorie_write_queue`: rows older than `WRITE_QUEUE_TTL_DAYS` (7 days)
  are deleted automatically during cleanup.

---

## Data model (own tables)

| Table | Purpose |
|---|---|
| `tabcalorie_entries` | Individual food entries: date, slot, food, quantity × unit, macros/fiber/sugar/kcal, GI, notes, `source` (`tk`/`manual`), `synced_at`. Unique key `date + slot + food_guid + unit_raw`. |
| `tabcalorie_foods` | Unique food reference with values **per 100 g** and service fields `FOOD_ID`, `FOOD_URL`, `UNIT_GUID` (`0000000000000001`), `UNIT_OPTIONS`, `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT` (1..6), `SERVICE_JSON`. |
| `tabcalorie_days` | Daily totals for fast Overview, including `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT`. |
| `tabcalorie_activities` | Activity from TK's `data.activities`; unique key — date + activity ID. |
| `tabcalorie_sync_log` | Time, type, result, HTTP code, row count — **no secrets**; rotation by `LOG_RETENTION_DAYS` / `LOG_MAX_ROWS`. |
| `tabcalorie_write_queue` | Queue of write-backs to TK with confirmation (`WRITEBACK_*`); TTL `WRITE_QUEUE_TTL_DAYS`. |

---

## TK API and limits

- Login: `POST /login/create?format=json&voucher=false`, body
  `{"email":"…","password":"<md5 utf8>"}`. Errors are returned in JSON as
  `code != 0` **even with HTTP 200**. All `Set-Cookie` pairs are stored and sent
  back in the `Cookie` header.
- Diary: `GET /user/diary/DD.MM.YYYY/get?format=json` →
  `{code:0, data:{times:[6 slots], carbohydrateTotal, sugarTotal, fiberTotal, proteinTotal, fatTotal, energyTotal, …}}`.
- Activity (v1.2.0): TK returns `data.activities` (`steps`, `energy`, `title`,
  `type`, `activityType`, duration) — data from TK's enabled Health Connect
  integration. With `ACTIVITY_ENABLED=1` it is imported into
  `tabcalorie_activities`. Health Connect is one-way: the plugin writes nothing to it.
- **GI and per-dish notes are not in the API** — they are kept locally in the
  reference, which now also stores the food's service fields for write-back.
- Write-back to TK is **unofficial**: only with `WRITEBACK_ENABLED=1` and
  `WRITEBACK_CONFIRM=1`, via a separate queue. Libraries label such access
  read-only and advise "don't hammer the site".
- **Anti-blocking:** interval ≥15 min, ~300–500 ms pauses between records,
  exponential backoff, single-flight lock, timeouts, circuit breaker
  (`CIRCUIT_UNTIL`), proper `User-Agent`, no parallel requests.

Details: [[API.en|API and data formats]].

### Nightscout (optional)

With `NS_ENABLED=1` the plugin performs a basic push to `/api/v1/treatments.json`
on your Nightscout. The `API-SECRET` header carries `sha1` of your `NS_API_SECRET`.
Disabled by default; use the standard Nightscout Reports for clinical charts.

---

## Testing

```bash
php tests/test_unit.php                       # 37 checks, no network
php -S 127.0.0.1:8765 tests/mock_server.php   # local TK mock
php tests/test_api.php                        # in a second terminal
```

See [[tests/README|Tests]]. For a first run without an account,
enable `DEMO_MODE`: the plugin uses sample data, makes no network requests and
cannot write to TK or Nightscout.

---

## Security

- No real logins/passwords/tokens/personal data in the repository, tests, logs or screenshots.
- `PASSWORD` and `NS_API_SECRET` are masked (`***`) in the UI and logs.
- All SQL uses `DBSafe`/placeholders; HTML is escaped in templates; admin actions
  are CSRF-protected and check `control_access` rights.
- HTTPS only, TLS validation, timeouts, response size limits.
- Access control for export (user's personal data) and anonymisation option.
- First run in demo/fixtures mode without a real account.

Details: [[SECURITY.en|Security]].

---

## FAQ

**Do I need a separate password if I sign in via Google/Apple?**
Yes. Open `/login`, enter your email and click "Forgot password?" — a direct-login
password will be emailed to you. The phone app keeps working.

**Why does Overview show an empty lunch although it was filled yesterday?**
Because the plugin shows the **last filled day** and does not pull slots from other days.

**Why aren't carbs pulled automatically from Health Connect / Google Fit?**
Because these TK integrations are one-way and do not export macros. Since v1.2.0
the plugin reads only activity from TK (`data.activities`: steps and burned kcal,
`ACTIVITY_ENABLED`); carbs are still imported by this plugin from the TK diary.

**How many grams per bread unit?**
10 by default. If your doctor counts 12 — change `GRAMS_PER_XE`.

**Can write-back to TK be fully disabled?**
Yes, by default: `WRITEBACK_ENABLED=0`. Even when enabled it requires confirmation
(`WRITEBACK_CONFIRM=1`).

**Is data stored in the cloud?**
No. All data stays local in your MajorDoMo DB. The TK cloud is read-only for the diary.

**What does `TabCalorie.status = 0` mean?**
An integration problem or a stopped background cycle: check `LAST_ERROR`,
`CIRCUIT_UNTIL`, `SYNC_ENABLED` and the `cycle_tabcalorieRun` time (no older than
~10 minutes). The `raw` property holds raw TK data and normalized entries for diagnostics.

---

## Update and removal

- **Update:** install the new `.tgz` over the current one (keep your config),
  then verify tables via the module migration.
- **Removal:** admin → **Modules** → deactivate `tabcalorie`. Data tables are
  removed by an explicit action; the config in `project_modules.DATA` remains
  until manually cleared.

---

## Links

- Repository: https://github.com/mambursoft/majordomo_TabCalorie
- API research and sources: [[perplexity_research|API research]]
- TK portal manual: https://www.tablycjakalorijnosti.com.ua/manual
- Docs: [[INSTALL.en|Installation]] ·
  [[USAGE.en|Usage]] · [[TROUBLESHOOTING.en|Troubleshooting]] ·
  [[SECURITY.en|Security]] · [[API.en|API and data formats]]
- Map of content: [[HOME|HOME (MOC)]]

## License

MIT. See [[LICENSE]] and [[NOTICE|NOTICE]].
