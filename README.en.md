---
title: "TabCalorie — Local nutrition tracking"
aliases: [TabCalorie, Home, Readme]
tags: [tabcalorie, majordomo, docs]
lang: en
plugin: tabcalorie
version: 1.1.0
---

# TabCalorie

> [!NOTE]
> A MajorDoMo plugin for local carbohydrate tracking based on data from the
> "Calorie Table" app (TK / Dine4Fit / `tablycjakalorijnosti.com.ua`).
> Default documentation language: Ukrainian. Version 1.1.0, 2026-11-20.

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
- Daily targets (calories, protein, fat, carbs) and progress bars for each of the
  6 meals on the **Overview** tab; exceeding a target is highlighted in color.
- MajorDoMo object `TabCalorie` (class `STabCalorie`) with ready-made aggregates.
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

The public plugin page (`run()` + `templates/tabcalorie/tabcalorie.html`) has six tabs:

| Tab | What it does |
|---|---|
| **Overview** | Last filled day: 6 slots, meal groups, daily totals, bread units, % of target, remaining allowance, target progress bars. Default tab when configured. |
| **History** | Daily totals for a period, comparison with previous days, trend and deviation from target. |
| **Food reference** | Local food list with per-100 g values; GI and notes are edited here (not in the API). |
| **Export** | CSV for day / week / month / year, UTF-8 BOM, `;` delimiter, `DD.MM.YYYY` dates; optional anonymisation. |
| **Diagnostics** | Last sync state, sync log, explanation of common errors, "Check connection" result. |
| **Instructions** | Short uk/en/ru instructions (including "Forgot password?"). |

> [!IMPORTANT]
> When the targets are filled in (non-zero), the **Overview** tab shows a progress
> bar for carbs, calories, protein and fat for each of the 6 meals relative to the
> calculated per-meal target; exceeding a target is highlighted in color.

On **Overview**, the **"Create typical chart"** button generates a consumption
chart using the standard MajorDoMo mechanism (Highcharts/pChart).

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

---

## Data model (own tables)

| Table | Purpose |
|---|---|
| `tabcalorie_entries` | Individual food entries: date, slot, food, quantity × unit, macros/fiber/sugar/kcal, GI, notes, `source` (`tk`/`manual`), `synced_at`. Unique key `date + slot + food_guid + unit_raw`. |
| `tabcalorie_foods` | Unique food reference with values **per 100 g**. |
| `tabcalorie_days` | Daily totals for fast Overview. |
| `tabcalorie_sync_log` | Time, type, result, HTTP code, row count — **no secrets**. |
| `tabcalorie_write_queue` | Queue of write-backs to TK with confirmation (`WRITEBACK_*`). |

---

## TK API and limits

- Login: `POST /login/create?format=json&voucher=false`, body
  `{"email":"…","password":"<md5 utf8>"}`. Errors are returned in JSON as
  `code != 0` **even with HTTP 200**. All `Set-Cookie` pairs are stored and sent
  back in the `Cookie` header.
- Diary: `GET /user/diary/DD.MM.YYYY/get?format=json` →
  `{code:0, data:{times:[6 slots], carbohydrateTotal, sugarTotal, fiberTotal, proteinTotal, fatTotal, energyTotal, …}}`.
- **GI and per-dish notes are not in the API** — they are kept locally in the reference.
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
Because these TK integrations are one-way and do not export macros. This plugin is needed.

**How many grams per bread unit?**
10 by default. If your doctor counts 12 — change `GRAMS_PER_XE`.

**Can write-back to TK be fully disabled?**
Yes, by default: `WRITEBACK_ENABLED=0`. Even when enabled it requires confirmation
(`WRITEBACK_CONFIRM=1`).

**Is data stored in the cloud?**
No. All data stays local in your MajorDoMo DB. The TK cloud is read-only for the diary.

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
