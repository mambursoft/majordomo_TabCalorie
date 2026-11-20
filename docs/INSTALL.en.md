---
title: "Installing TabCalorie"
aliases: [TabCalorie, INSTALL.en, Installation]
tags: [tabcalorie, majordomo, settings]
lang: en
plugin: tabcalorie
version: 1.2.0
---

# Installing TabCalorie

[[INSTALL|Українська]] · [[INSTALL.en|English]] · [[INSTALL.ru|Русский]]

Version 1.2.0 · 2026-11-20

This guide describes how to install the MajorDoMo plugin `tabcalorie` in two
ways: via Market and manually from a `.tgz` archive.

> [!WARNING]
> **Reminder:** TabCalorie is NOT a medical device and provides no medical
> advice. See [[README.en|TabCalorie]].

---

## 1. Before you start

### 1.1. Environment requirements

| Requirement | Value |
|---|---|
| MajorDoMo | installation with the module mechanism (`modules/`, `templates/`, `languages/`, `scripts/`, `img/modules/`) |
| PHP | **7.3–8.2** (minimum 7.3) |
| PHP extensions | `curl`, `json`, `mbstring` |
| DB | MySQL/MariaDB, accessed via MajorDoMo (`DBSafe`) |
| Network | HTTPS, valid TLS verification, outbound access to the TK portal |
| Rights | MajorDoMo administrator, `control_access` rights |

### 1.2. Prepare your TK account

1. Check the email you use in the "Calorie Table" app (menu **My profile**).
2. If you signed in via Google/Apple/Facebook and have no separate password:
   open `https://www.tablycjakalorijnosti.com.ua/login`, enter your email and
   click **"Forgot password?"**. The direct-login email will not interrupt the
   phone app.
3. For the Czech portal use `https://www.kaloricketabulky.cz`.

> [!WARNING]
> Never store a real password in the repository or text files — it is entered
> only in the protected module admin page and masked.

---

## 2. Option A — install via MajorDoMo Market

1. Log in to the MajorDoMo admin area as an administrator.
2. Open **Market / Module catalog** (or `/admin.php?p=market`).
3. Search for `TabCalorie`.
4. Click **Install** and confirm.
5. Market unpacks the files into the MajorDoMo structure and runs module registration.
6. Go to **Modules** and make sure `tabcalorie` is active. If not — activate it manually.
7. Open the module settings (section 4).

---

## 3. Option B — manual installation from `.tgz`

### 3.1. Get the package

Build the package from the source repository or download a ready release:

```bash
git clone https://github.com/mambursoft/majordomo_TabCalorie.git
cd majordomo_TabCalorie
python3 build_package.py
```

Result: `dist/tabcalorie.tgz`.

### 3.2. Extract into the MajorDoMo root

```bash
cd /var/www/html            # path to your MajorDoMo root
tar -tzf /path/to/tabcalorie.tgz   # preview before extracting
tar -xzf /path/to/tabcalorie.tgz
```

> [!NOTE]
> `/var/www/html` is a typical path; use the real root of your installation.
> The archive has no parent `majordomo_TabCalorie` folder — files go straight
> into `modules/`, `templates/`, etc.

### 3.3. Verify the structure

```text
modules/tabcalorie/          module PHP code (incl. tabcalorie.class.php)
templates/tabcalorie/        html templates
languages/tabcalorie_*.php   language files (uk default, en, ru)
scripts/cycle_tabcalorie.php background sync cycle
img/modules/tabcalorie.png   64×64 icon
modinfo.json                 Market metadata
README.md / README.en.md / README.ru.md
LICENSE / NOTICE.md / CHANGELOG.md
docs/                        documentation
HOME.md                      map of content (MOC)
```

### 3.4. Activate the module

1. Admin → **Modules** → find `tabcalorie` in the inactive list.
2. Click **Install / Activate**.
3. During installation the module:
   - registers in `project_modules`;
   - sets the `cms/modules_installed/tabcalorie.installed` flag;
   - adds a row to `plugins`;
   - creates DB tables (below);
   - creates the `TabCalorie` object of class `STabCalorie`;
   - adds the `img/modules/tabcalorie.png` icon.

### 3.5. Tables created

| Table | Purpose |
|---|---|
| `tabcalorie_entries` | food entries (date, slot, food, quantity × unit, macros, GI, notes, `source`, `synced_at`); unique key `date + slot + food_guid + unit_raw` |
| `tabcalorie_foods` | food reference per 100 g + service fields for write-back (`FOOD_ID`, `FOOD_URL`, `UNIT_GUID`, `UNIT_OPTIONS`, `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT`, `SERVICE_JSON`) |
| `tabcalorie_days` | daily totals, including `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT` |
| `tabcalorie_activities` | activity from TK's `data.activities`; unique key — date + activity ID |
| `tabcalorie_sync_log` | sync log without secrets; rotation `LOG_RETENTION_DAYS` / `LOG_MAX_ROWS` |
| `tabcalorie_write_queue` | queue of write-backs to TK with confirmation; TTL `WRITE_QUEUE_TTL_DAYS` |

### 3.6. Set up the background cycle

`scripts/cycle_tabcalorie.php` is meant to run on a MajorDoMo or system cron
schedule. Minimum recommended interval is **15 minutes**, typical is 60 minutes
(`SYNC_INTERVAL_MIN=60`).

Example cron entry (substitute the real path):

```cron
*/15 * * * * php /var/www/html/scripts/cycle_tabcalorie.php >/dev/null 2>&1
```

> [!TIP]
> The cycle uses a single-flight lock: parallel runs do not create concurrent
> requests to TK.

---

## 4. Initial configuration

Open the module admin page and fill in the configuration keys (stored in
`project_modules.DATA`):

| Key | What to enter | Default |
|---|---|---|
| `BASE_URL` | `https://www.tablycjakalorijnosti.com.ua` (or CZ / `custom`) | `https://www.tablycjakalorijnosti.com.ua` |
| `PROVIDER` | `ua`, `cz` or `custom` | `ua` |
| `EMAIL` | TK account email | — |
| `PASSWORD` | direct-login password (masked) | — |
| `SYNC_ENABLED` | `1` — enable the cycle | `1` |
| `SYNC_INTERVAL_MIN` | interval, min (≥15) | `60` |
| `SYNC_DAYS` | incremental depth, days | `2` |
| `HOOK_ENABLED` | `0` — hook disabled | `0` |
| `HOOK_PROPERTY` | trigger `Obj.Prop` | — |
| `HOOK_DEBOUNCE_SEC` | debounce, sec | `60` |
| `RETENTION_DAYS` | data retention | `1825` (5 years) |
| `ACTIVITY_ENABLED` | `1` — import activity from TK; `0` — disable | `1` |
| `LOG_RETENTION_DAYS` | log rotation by age, days | `30` |
| `LOG_MAX_ROWS` | log rotation by row count | `2000` |
| `WRITE_QUEUE_TTL_DAYS` | write queue TTL, days | `7` |
| `LANGUAGE` | `uk` / `en` / `ru` | `uk` |
| `GRAMS_PER_XE` | grams per bread unit | `10` |
| `CALORIES_TARGET` | daily calorie target, kcal | `1800` |
| `PROTEIN_TARGET` | daily protein target, g | `90` |
| `FAT_TARGET` | daily fat target, g | `60` |
| `CARBS_TARGET` | daily carbohydrate target, g | `120` |
| `MEAL_NORM_SHARE` | share of a main meal (10–50, %) | `25` |
| `CSV_DELIMITER` | CSV delimiter | `;` |
| `WRITEBACK_ENABLED` | write-back to TK | `0` (disabled) |
| `WRITEBACK_CONFIRM` | write confirmation | `1` |
| `NS_ENABLED` | Nightscout | `0` (disabled) |
| `NS_URL` | Nightscout base | — |
| `NS_API_SECRET` | Nightscout secret (masked) | — |

> [!TIP]
> `MEAL_NORM_SHARE` sets the share of the daily target for a main meal
> (breakfast/lunch/dinner — slots 1, 3, 5). The rest of the target is split
> equally between the snacks (slots 2, 4, 6). Range: 10–50%, default 25%.
> When the targets are filled in (non-zero), the **Overview** tab shows a progress
> bar for carbs, calories, protein and fat for each of the 6 meals relative to
> the calculated per-meal target; exceeding a target is highlighted in color.

Full descriptions are in [[README.en#Configuration|README.en]].

### 4.1. Check connection

Click **"Check connection"** in the admin page. Diagnostics perform:

- DNS/TLS/HTTP check;
- login `POST /login/create?format=json&voucher=false` (password as `md5`);
- cookie retrieval and validation;
- a trial `GET /user/diary/DD.MM.YYYY/get?format=json`;
- JSON parsing and 6-slot verification.

Diagnostics **do not change data**. If something fails — see
[[TROUBLESHOOTING.en|Troubleshooting]].

### 4.2. First run

- [ ] Enable `SYNC_ENABLED=1` (if not already).
- [ ] Run the cycle manually or wait for the schedule.
- [ ] Open the public plugin page → **Overview** tab.
- [ ] Make sure the last filled day and correct totals are shown.
- [ ] Check **Diagnostics** → last successful sync and log.
- [ ] Make sure the target progress bars on **Overview** are displayed correctly.

### 4.3. What's new in v1.2.0

- [ ] **Activity** tab: steps and burned kcal from TK's `data.activities`,
  `ACTIVITY_ENABLED=1` (see [[USAGE.en|Usage]]).
- [ ] Food form on the **Food reference** tab with service fields for write-back
  to TK (see [[USAGE.en|Usage]]).
- [ ] Object properties `status`, `raw`, `ActivitySteps`, `ActivityKcal`,
  `ActivityCount` (see [[API.en|API and data formats]]).
- [ ] `tabcalorie_sync_log` rotation (`LOG_RETENTION_DAYS=30`, `LOG_MAX_ROWS=2000`)
  and `tabcalorie_write_queue` TTL (`WRITE_QUEUE_TTL_DAYS=7`).

> [!NOTE]
> Health Connect in TK is one-way: the plugin only reads activity from the TK
> diary and writes nothing to Health Connect.

---

## 5. Update

1. Back up the DB (`tabcalorie_*` tables) and configuration.
2. Build the new `.tgz` and extract over the current installation:
   ```bash
   tar -xzf tabcalorie.tgz -C /var/www/html
   ```
3. Open the admin page — the module applies table migrations.
4. Verify **Diagnostics** and **Overview**.
5. Configuration in `project_modules.DATA` is preserved.

---

## 6. Removal

1. Admin → **Modules** → deactivate `tabcalorie`.
2. Optionally drop tables `tabcalorie_entries`, `tabcalorie_foods`,
   `tabcalorie_days`, `tabcalorie_activities`, `tabcalorie_sync_log`,
   `tabcalorie_write_queue`.
3. Remove `modules/tabcalorie/`, `templates/tabcalorie/`,
   `languages/tabcalorie_*.php`, `scripts/cycle_tabcalorie.php`,
   `img/modules/tabcalorie.png`.
4. Clear the config in `project_modules.DATA` manually if no longer needed.
5. Remove the cron entry for `cycle_tabcalorie.php`.

---

## 7. Installation FAQ

**Do I need to create tables manually?**
No. The module creates them at installation time (heredoc format
`table: FIELD type...`, mirrored in `modules/tabcalorie/db_install.php`).

**Why isn't the module visible after extraction?**
Make sure you extracted into the MajorDoMo root, not a subfolder, and that
`modules/tabcalorie/tabcalorie.class.php` exists. Refresh the module cache in admin.

**Can I install on PHP 7.3?**
Yes, 7.3 is the minimum supported version; tested up to 8.2.

**Where do I get a release?**
https://github.com/mambursoft/majordomo_TabCalorie/releases

See also [[HOME|HOME (MOC)]].
