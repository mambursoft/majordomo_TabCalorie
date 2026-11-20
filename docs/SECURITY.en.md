---
title: "TabCalorie Security"
aliases: [TabCalorie, SECURITY.en, Security model]
tags: [tabcalorie, majordomo, security]
lang: en
plugin: tabcalorie
version: 1.3.0
---

# TabCalorie Security

[[SECURITY|Українська]] · [[SECURITY.en|English]]

Version 1.3.0 · 2026-11-20

TabCalorie handles sensitive data: "Calorie Table" credentials, a Nightscout
secret and **the user's personal data**. This document describes the security
model and requirements for the public repository.

---

## 1. Principles

1. **No secrets in the repository.** Real logins, passwords, tokens, cookies,
   emails and the user's nutrition data never enter git, tests, logs or screenshots.
2. **Data minimisation.** Only what is needed for carbohydrate tracking and
   reporting is stored.
3. **Locality.** All data stays in your MajorDoMo installation. The TK cloud is
   read-only for the diary.
4. **Secure by default.** Dangerous features (write-back to TK, Nightscout) are disabled.
5. **Access control.** Reading data and export require `control_access` rights.

---

## 2. Secret storage

| Secret | Stored in | Transmitted as | Masking |
|---|---|---|---|
| `PASSWORD` (TK) | `project_modules.DATA` (serialized module config) | in login body as `md5(utf8)` | `***` in UI and logs |
| `NS_API_SECRET` | `project_modules.DATA` | in `API-SECRET` header as `sha1` | `***` in UI and logs |
| TK session cookies | in memory during the cycle (not stored in plain text) | in `Cookie` header | never logged |

Rules:

- config is read/written via `getConfig()/saveConfig()`;
- passwords are not duplicated in templates, JS, JSON responses or URLs;
- `tabcalorie_sync_log` contains only time, type, result, HTTP code and row
  count — **no secrets**;
- `LAST_ERROR` is scrubbed of any accidental secret fragments.

---

## 3. Protecting the user's personal data

- Personal data and nutrition data are stored locally in `tabcalorie_*` tables.
- The public page and export are protected by `control_access` rights.
- CSV export supports **anonymisation** (no name, email, etc.).
- Never publish real data in public issues, screenshots or demo fixtures.
- Use `demo`/fixtures mode without a real account for development and tests.

---

## 4. Web security

| Threat | Protection |
|---|---|
| SQL injection | all queries via `DBSafe`/placeholders; no concatenation of user data into SQL |
| XSS | HTML escaping in jTemplates; safe output of variables |
| CSRF | CSRF token check for all admin actions (config changes, sync run, export) |
| Unauthorised access | `control_access` check on the public page and in admin |
| Traffic interception | HTTPS only, TLS certificate validation, no downgrade |
| Log leakage | secrets masked; request bodies with data are not logged |
| File injection | fixed template paths; no user-supplied paths accepted |

---

## 5. API integration security

### 5.1. Calorie Table (TK)

- Login and diary reads go through the official web portal.
- The password is sent **only** as `md5(utf8)` in the login body.
- Cookies live in cycle memory and are never logged.
- cURL timeouts and response size limits protect against hangs.
- **Write-back to TK is an unofficial API**, therefore:
  - `WRITEBACK_ENABLED=0` by default;
  - `WRITEBACK_CONFIRM=1` requires explicit confirmation;
  - writes go through a separate `tabcalorie_write_queue`;
  - the plugin keeps a slow pace (see anti-blocking).

### 5.2. Nightscout

- `NS_ENABLED=0` by default.
- Push is basic, to `/api/v1/treatments.json`.
- The `API-SECRET` header carries `sha1` of `NS_API_SECRET`, not the secret itself.
- Use a dedicated API key with minimal privileges where possible.

### 5.3. Anti-blocking and abuse protection

- sync interval **≥15 minutes**;
- one request at a time, **no parallel requests**;
- ~300–500 ms pauses between records;
- exponential backoff and circuit breaker (`CIRCUIT_UNTIL`);
- single-flight lock (never two cycles at once);
- proper `User-Agent`;
- local `demo`/fixtures mode for tests without real requests.

---

## 6. Public repository

Before publishing, check:

```bash
# search for possible secrets
grep -RInE "password|api[_-]?secret|token|cookie|@gmail|@ukr|@meta" \
  --exclude-dir=.git --exclude="*.png" .

# PHP syntax check
find . -name "*.php" -exec php -l {} \;
```

Checklist:

- [ ] no real emails/passwords/secrets;
- [ ] examples use placeholders (`user@example.org`, `changeme`);
- [ ] no personal data in screenshots;
- [ ] logs contain only `***` instead of secrets;
- [ ] `.env` and `config.local.json` are excluded from the package (`build_package.py`);
- [ ] dependencies and security updates are reviewed periodically.

---

## 7. Responsibility

TabCalorie is an informational tool. The installation administrator is responsible for:

- securing the server and the MajorDoMo account;
- safe storage of config and backups;
- access control to the user's personal data;
- compliance with personal-data legislation;
- understanding that the plugin **is not a medical device**.

---

## 8. Reporting vulnerabilities

Do not post vulnerabilities in public issues. Contact the repository owner
privately via https://github.com/mambursoft/majordomo_TabCalorie
(Security advisory). Include a description, reproduction steps and version
(1.1.0), **without real secrets or data**.

See also [[API.en|API and data formats]], [[README.en#Security|README.en]] and [[HOME|HOME (MOC)]].
