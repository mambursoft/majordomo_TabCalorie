---
title: "HOME — карта контенту TabCalorie"
aliases: [TabCalorie, HOME, MOC, Map of Content]
tags: [tabcalorie, majordomo, docs]
lang: uk
plugin: tabcalorie
version: 1.3.0
---

# HOME — карта контенту TabCalorie

> [!NOTE]
> MOC (Map of Content) для документації плагіна MajorDoMo **TabCalorie** версії 1.2.0.
> Мови: [[README|Українська]] · [[README.en|English]] · [[README.ru|Русский]].
> Усі сторінки містять YAML frontmatter і wiki-посилання формату Obsidian.

> [!WARNING]
> TabCalorie — інформаційний інструмент, **не медичний пристрій**. Див.
> [[README#⚠️ Важливе медичне попередження|медичне попередження]] (uk/en/ru).

---

## Швидкий старт

| Мова | Огляд плагіна | Встановлення | Використання |
|---|---|---|---|
| Українська | [[README|TabCalorie — локальний контроль харчування]] | [[INSTALL|Інструкція встановлення]] | [[USAGE|Використання TabCalorie]] |
| English | [[README.en|TabCalorie — Local nutrition tracking]] | [[INSTALL.en|Installing TabCalorie]] | [[USAGE.en|Using TabCalorie]] |
| Русский | [[README.ru|TabCalorie — локальный контроль питания]] | [[INSTALL.ru|Инструкция по установке]] | [[USAGE.ru|Использование TabCalorie]] |

- [ ] Встановити плагін ([[INSTALL|uk]] · [[INSTALL.en|en]] · [[INSTALL.ru|ru]]).
- [ ] Заповнити конфігурацію в адмінці модуля.
- [ ] Натиснути «Перевірити підключення».
- [ ] Відкрити вкладку **Огляд** і перевірити прогресбари норм.
- [ ] Перевірити вкладку **Активність** (за `ACTIVITY_ENABLED=1`).

---

## Налаштування

- [[README#Налаштування|Таблиця всіх ключів конфігурації]] (uk) ·
  [[README.en#Configuration|Configuration]] (en) ·
  [[README.ru#Настройки|Настройки]] (ru).
- [[INSTALL#4. Початкове налаштування|Початкове налаштування]] (uk) ·
  [[INSTALL.en#4. Initial configuration|Initial configuration]] (en) ·
  [[INSTALL.ru#4. Первоначальная настройка|Первоначальная настройка]] (ru).
- [[USAGE#2.5. Норми споживання та прогресбари|Норми споживання та прогресбари]] (uk) ·
  [[USAGE.en#2.5. Intake targets and progress bars|Intake targets and progress bars]] (en) ·
  [[USAGE.ru#2.5. Нормы потребления и прогрессбары|Нормы потребления и прогрессбары]] (ru).

**Нові ключі версії 1.2.0:** `ACTIVITY_ENABLED` (1), `LOG_RETENTION_DAYS` (30),
`LOG_MAX_ROWS` (2000), `WRITE_QUEUE_TTL_DAYS` (7).

**Ключі версії 1.1.0:** `CALORIES_TARGET` (1800), `PROTEIN_TARGET` (90),
`FAT_TARGET` (60), `CARBS_TARGET` (120), `MEAL_NORM_SHARE` (25, діапазон 10–33, %).

- [[PROMPT|Промпт створення плагіна]] — вимоги до реалізації та зафіксовані рішення.
- [[CHANGELOG|Журнал змін]] — історія версій 1.0.0 → 1.1.0 → 1.2.0.
- [[NOTICE|NOTICE]] — ліцензія та відмова від відповідальності.

---

## Можливості 1.2.0

- [ ] [[README#Вкладка «Активність»|Вкладка «Активність»]] (uk) ·
  [[USAGE.en#11. Activity tab (v1.2.0)|Activity tab]] (en) ·
  [[USAGE.ru#11. Вкладка «Активность» (v1.2.0)|Вкладка «Активность»]] (ru) —
  кроки й спалені ккал із `data.activities`, добовий зріз і 7-денний огляд.
- [ ] [[README#Форма страви у довіднику|Форма страви у довіднику]] (uk) ·
  [[USAGE.en#4.1. Food form and service fields (v1.2.0)|Food form]] (en) —
  службові поля `FOOD_ID`, `FOOD_URL`, `UNIT_GUID`, `UNIT_OPTIONS`,
  `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT`, `SERVICE_JSON` та autocomplete ТК.
- [ ] [[API|Властивості `status`, `raw`, `ActivitySteps`, `ActivityKcal`, `ActivityCount`]] —
  стан інтеграції, сирі дані й агрегат активності.
- [ ] [[USAGE#14. Ротація логів і TTL черги (v1.2.0)|Ротація логів і TTL черги]] (uk) ·
  [[API.en#8.4. Log rotation and queue TTL (v1.2.0)|Log rotation and queue TTL]] (en).
- [ ] [[CHANGELOG#1.2.0|Журнал змін 1.2.0]].

> [!WARNING]
> Health Connect у ТК односторонній: плагін лише зчитує активність із щоденника
> ТК і не пише в Health Connect.

---

## API

- [[API|API та формати даних]] (uk) · [[API.en|API and data formats]] (en).
- [[perplexity_research|Дослідження API «Таблиці калорійності»]] — джерело технічних фактів,
  Google Apps Script, ендпоїнти, MD5, cookies, 6 слотів, Nightscout.
- [[USAGE#8. Синхронізація: як це працює|Як працює синхронізація]] (uk) ·
  [[USAGE.en#8. How sync works|How sync works]] (en) ·
  [[USAGE.ru#8. Как работает синхронизация|Как работает синхронизация]] (ru).

---

## Безпека

- [[SECURITY|Безпека TabCalorie]] (uk) · [[SECURITY.en|TabCalorie Security]] (en).
- [[README#Безпека|Безпека в README]] (uk) ·
  [[README.en#Security|Security in README]] (en) ·
  [[README.ru#Безопасность|Безопасность в README]] (ru).
- [[NOTICE#⚠️ Відмова від медичної відповідальності|Відмова від медичної відповідальності]].

---

## Діагностика

- [[TROUBLESHOOTING|Усунення несправностей]] (uk) ·
  [[TROUBLESHOOTING.en|Troubleshooting]] (en) ·
  [[TROUBLESHOOTING.ru|Устранение неисправностей]] (ru).
- [[USAGE#6. Вкладка «Діагностика»|Вкладка «Діагностика»]] (uk) ·
  [[USAGE.en#6. Diagnostics tab|Diagnostics tab]] (en) ·
  [[USAGE.ru#6. Вкладка «Диагностика»|Вкладка «Диагностика»]] (ru).

---

## Тести

- [[tests/README|Тести TabCalorie]] — юніт-тести, локальний mock API, fixtures.
- [[README#Тестування|Тестування в README]] (uk) ·
  [[README.en#Testing|Testing in README]] (en) ·
  [[README.ru#Тестирование|Тестирование в README]] (ru).

---

## Усі документи

- [[HOME|HOME (MOC)]]
- [[README|README (uk)]] · [[README.en|README (en)]] · [[README.ru|README (ru)]]
- [[INSTALL|INSTALL (uk)]] · [[INSTALL.en|INSTALL (en)]] · [[INSTALL.ru|INSTALL (ru)]]
- [[USAGE|USAGE (uk)]] · [[USAGE.en|USAGE (en)]] · [[USAGE.ru|USAGE (ru)]]
- [[TROUBLESHOOTING|TROUBLESHOOTING (uk)]] · [[TROUBLESHOOTING.en|TROUBLESHOOTING (en)]] · [[TROUBLESHOOTING.ru|TROUBLESHOOTING (ru)]]
- [[API|API (uk)]] · [[API.en|API (en)]]
- [[SECURITY|SECURITY (uk)]] · [[SECURITY.en|SECURITY (en)]]
- [[perplexity_research|perplexity_research]] · [[PROMPT|PROMPT]] ·
  [[CHANGELOG|CHANGELOG]] · [[NOTICE|NOTICE]] · [[tests/README|tests/README]]
- [[LICENSE|LICENSE]]
