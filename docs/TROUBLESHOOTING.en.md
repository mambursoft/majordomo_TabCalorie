---
title: "Troubleshooting TabCalorie"
aliases: [TabCalorie, TROUBLESHOOTING.en, Diagnostics]
tags: [tabcalorie, majordomo, docs]
lang: en
plugin: tabcalorie
version: 1.4.1
---

# Troubleshooting TabCalorie

[[TROUBLESHOOTING|Українська]] · [[TROUBLESHOOTING.en|English]] · [[TROUBLESHOOTING.ru|Русский]]

Version 1.4.1 · 2026-11-20

This document helps diagnose common issues. Start with the **Diagnostics** tab
and the **"Check connection"** button — it checks DNS/TLS/HTTP, login, cookies,
endpoint and JSON parsing without changing data.

> [!WARNING]
> Never send public issues with real logs containing passwords, tokens or the
> user's personal data. Secrets are masked as `***` in logs — verify this before
> publishing.

---

## Quick checklist

- [ ] Is the module active? Admin → **Modules** → `tabcalorie`.
- [ ] Are `EMAIL` and `PASSWORD` filled in?
- [ ] Does `PROVIDER` match the portal (`ua` / `cz` / `custom`)?
- [ ] Is `SYNC_ENABLED=1`?
- [ ] Does the server have outbound HTTPS access to the TK portal?
- [ ] Are PHP extensions `curl`, `json`, `mbstring` enabled?
- [ ] What do `LAST_ERROR`, `CIRCUIT_UNTIL` and `tabcalorie_sync_log` say?
- [ ] Are the target progress bars on **Overview** displayed as expected?

---

## 1. Login errors

### "Login error: code != 0"

The TK API returns errors in JSON **`code != 0` even with HTTP 200** — check the
response body, not just the status code.

Causes:

- wrong email or password;
- account created via Google/Apple with no direct password;
- password sent in plain text instead of `md5(utf8)`.

**Solution:**

1. Open `https://www.tablycjakalorijnosti.com.ua/login`.
2. Enter your email and click **"Forgot password?"**.
3. Receive the direct-login password email (the phone app keeps working).
4. Enter the new password in the module settings and click "Check connection" again.

### "Server did not return session cookies"

- Check that `BASE_URL` points to the correct portal and the response has `Set-Cookie`.
- The plugin stores **all** cookie pairs (UA: any; CZ: `JSESSIONID`;
  UA SDK mentions `kaloricketabulky_token`) and sends them back in `Cookie`.
- The portal may have changed its auth scheme — check for a plugin update.

---

## 2. Diary read errors

### HTTP 403 / 401 on `GET /user/diary/…`

- Session expired — log in again (above).
- Cookies not passed — check config and proxy servers.
- The site's anti-abuse protection may have triggered (section 4).

### "API error for date …" / empty JSON

- Check the path date format: **`DD.MM.YYYY`**, e.g. `20.11.2026`.
- Check that `code == 0` and `data` is present.
- Future or very old dates may have no data — this is normal.

### Overview shows empty slots / data does not appear

- The plugin shows the **last filled day**. If only breakfast is filled today,
  lunch will be empty — not from another day.
- Make sure TK actually has entries for that date.
- Check `SYNC_DAYS` — increase depth or run a backfill.
- Check `tabcalorie_entries` for rows.

---

## 3. Installation and configuration errors

### Module not visible after extraction

- Extract into the **MajorDoMo root**, not a subfolder.
- Check that `modules/tabcalorie/tabcalorie.class.php` exists.
- Refresh the module cache in admin and check file permissions (readable by web server).

### Tables not created

- Activate the module via admin — `dbInstall()` runs at installation.
- Check the DB user's `CREATE TABLE` permission.
- Compare with the mirror `modules/tabcalorie/db_install.php`
  (heredoc format `table: FIELD type...`).

### Password not saved / resets

- Config is stored in `project_modules.DATA` via `getConfig()/saveConfig()`.
- Check DB write permissions and that you are editing the `tabcalorie` module config.

---

## 4. Blocking, timeouts, circuit breaker

### "Too many requests" / temporary block by TK

The TK site dislikes frequent polling. The plugin already implements protection,
but verify:

- `SYNC_INTERVAL_MIN` **≥ 15** (60 recommended);
- do not run the background cycle and a manual run at the same time (single-flight);
- do not add your own parallel cron jobs;
- ~300–500 ms pauses between records are already built in.

### `CIRCUIT_UNTIL` in the future

This means the plugin temporarily stopped requests after a series of errors.

**Solution:** fix the root cause (above), wait for the pause to end, or reset
`CIRCUIT_UNTIL` in admin. Check `LAST_ERROR`.

### Slow sync

- Reduce `SYNC_DAYS` (e.g. 2).
- Check speed and stability of the server connection to the portal.
- Check cURL timeouts — overly large values prolong waiting.

---

## 5. Export and data

### CSV opens with garbled characters in Excel

- The file has a **UTF-8 BOM** — make sure your mail client did not strip it.
- The delimiter is `;` (`CSV_DELIMITER`). Ukrainian Excel uses `;` by default.
  Change the key if your locale differs.
- Dates are in `DD.MM.YYYY` format.

### Empty totals in CSV

- Check the selected period (day/week/month/year).
- Check data in `tabcalorie_days`.
- For dish details check `tabcalorie_entries`.

### Data disappeared after a year

`RETENTION_DAYS=1825` (5 years) — rows older than that are purged daily.
Increase the value if you need a longer history.

---

## 6. GI, notes and write-back to TK

### GI or notes are empty

Expected: **the TK API does not return GI or per-dish notes**. Fill them in the
**Food reference** tab (table `tabcalorie_foods`) — they are stored locally and
not overwritten by sync.

### Write-back to TK does not work

- Write-back is **unofficial** and disabled by default (`WRITEBACK_ENABLED=0`).
- Enable `WRITEBACK_ENABLED=1` and confirm `WRITEBACK_CONFIRM=1`.
- Check the `tabcalorie_write_queue` queue for entries and their state.
- Remember: libraries label such access read-only and advise not to "hammer"
  the site. Use with care.

---

## 7. Nightscout

### Push to Nightscout does not arrive

- Check `NS_ENABLED=1`.
- Check `NS_URL` (base without trailing slash), e.g. `https://ns.example.org`.
- The `API-SECRET` header carries **`sha1`** of `NS_API_SECRET`, not the secret
  itself. Make sure you did not enter an already-hashed value.
- Check the `/api/v1/treatments.json` endpoint and server availability.

---

## 8. Reporting a problem

Open an issue at https://github.com/mambursoft/majordomo_TabCalorie/issues and include:

- plugin version (1.1.0), PHP and MajorDoMo versions;
- `PROVIDER` (without secrets!) and BASE_URL (without credentials);
- `LAST_ERROR` text (verify it contains no password);
- recent `tabcalorie_sync_log` rows without personal data;
- steps to reproduce.

**Do not include:** real email/passwords, `NS_API_SECRET`, the user's personal data, cookies.

See also [[API.en|API and data formats]], [[SECURITY.en|Security]],
[[README.en#FAQ|FAQ]] and [[HOME|HOME (MOC)]].
