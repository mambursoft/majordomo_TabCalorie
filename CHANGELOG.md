---
title: "Журнал змін TabCalorie"
aliases: [TabCalorie, CHANGELOG, Журнал змін]
tags: [tabcalorie, majordomo, docs]
lang: uk
plugin: tabcalorie
version: 1.1.0
---

# Журнал змін

Усі помітні зміни проєкту фіксуються у цьому файлі.
Формат базується на [Keep a Changelog](https://keepachangelog.com/uk-UA/1.1.0/),
версіювання — [Semantic Versioning](https://semver.org/lang/uk/).

> [!NOTE]
> Документація проєкту адаптована до Obsidian: YAML frontmatter, wiki-посилання,
> callouts та інтерактивні чекбокси. Карта контенту — [[HOME|HOME (MOC)]].

---

## [1.1.0] — 2026-11-20

### Додано

- Нові ключі конфігурації для добових норм:
  - `CALORIES_TARGET` (за замовчуванням `1800`) — добова норма калорій, ккал;
  - `PROTEIN_TARGET` (за замовчуванням `90`) — добова норма білків, г;
  - `FAT_TARGET` (за замовчуванням `60`) — добова норма жирів, г;
  - `MEAL_NORM_SHARE` (за замовчуванням `25`, діапазон 10–50, %) — частка
    основного прийому їжі (сніданок/обід/вечеря) від добової норми; решта норми
    ділиться порівну між перекусами (2, 4, 6).
- Якщо норми заповнені (не 0), у вкладці **Огляд** для кожного з 6 прийомів їжі
  показується прогресбар по вуглеводах, калоріях, білках і жирах відносно
  розрахованої норми прийому; перевищення виділяється кольором.
- `HOME.md` — MOC (Map of Content) з wiki-посиланнями на всі документи трьома мовами
  з розділами «Швидкий старт», «Налаштування», «API», «Безпека», «Діагностика», «Тести».

### Змінено

- `CARBS_TARGET` (`120` г) тепер описується як добова норма вуглеводів разом з
  іншими нормами у таблицях налаштувань README та docs/INSTALL, docs/USAGE (uk/en/ru).
- Усі Markdown-документи адаптовано до формату Obsidian: додано YAML frontmatter,
  внутрішні посилання перетворено на wiki-посилання, попередження та примітки —
  на callouts, контрольні списки — на інтерактивні чекбокси.
- Версію документації оновлено до 1.1.0.

### Видалено

- Персональні згадки з усіх .md-документів: рік народження, згадки про дитину,
  цільову аудиторію та персональний медичний контекст. Залишено загальне медичне
  попередження без персональних згадок; технічні деталі API (ендпоїнти, MD5,
  cookie, 6 слотів, «Забули пароль?») збережено.

---

## [1.0.0] — 2026-11-20

Перший публічний реліз плагіна MajorDoMo «TabCalorie».

### Додано

- Модуль `modules/tabcalorie/tabcalorie.class.php` (клас `tabcalorie extends module`,
  назва `tabcalorie`) із реєстрацією в `project_modules`, прапорцем
  `cms/modules_installed/tabcalorie.installed`, рядком у `plugins` та іконкою
  `img/modules/tabcalorie.png`.
- Конфігурація в `project_modules.DATA` із усіма ключами: `BASE_URL`, `PROVIDER`,
  `EMAIL`, `PASSWORD` (маскується), `SYNC_ENABLED`, `SYNC_INTERVAL_MIN`,
  `SYNC_DAYS`, `HOOK_ENABLED`, `HOOK_PROPERTY`, `HOOK_DEBOUNCE_SEC`,
  `RETENTION_DAYS`, `LANGUAGE`, `GRAMS_PER_XE`, `CARBS_TARGET`, `CSV_DELIMITER`,
  `WRITEBACK_ENABLED`, `WRITEBACK_CONFIRM`, `NS_ENABLED`, `NS_URL`,
  `NS_API_SECRET` (маскується), `LAST_SYNC`, `LAST_ERROR`, `CIRCUIT_UNTIL`.
- Власні таблиці БД: `tabcalorie_entries` (унікальний ключ
  `date + slot + food_guid + unit_raw`), `tabcalorie_foods`, `tabcalorie_days`,
  `tabcalorie_sync_log`, `tabcalorie_write_queue`.
- Імпорт щоденника ТК через `POST /login/create?format=json&voucher=false`
  (пароль як `md5`) і `GET /user/diary/ДД.ММ.РРРР/get?format=json` (6 слотів).
- Ідемпотентна інкрементальна синхронізація останніх `SYNC_DAYS` днів та бекфілл
  за діапазоном.
- Локальний довідник страв із значеннями на 100 г, GI та нотатками (API ТК їх
  не віддає).
- Об'єкт MajorDoMo `TabCalorie` класу `STabCalorie` із властивостями `LastDate`,
  `Partial`, `FilledSlots`, `Slot1Carbs..Slot6Carbs`, `Slot1Kcal..Slot6Kcal`,
  `GroupMainCarbs` (1+3+5), `GroupSnacksCarbs` (2+4+6), `GroupDayCarbs`,
  `TotalCarbs`, `TotalKcal`, `TotalProtein`, `TotalFat`, `TotalFiber`,
  `TotalSugar`, `TotalXE`, `GI`, `Notes`, `Updated`.
- Адаптивний інтерфейс із вкладками «Огляд», «Історія», «Довідник», «Експорт»,
  «Діагностика», «Інструкції».
- Огляд останньої заповненої доби без підтягування порожніх слотів з інших днів.
- Кнопка «Створити типовий chart» через стандартний механізм MajorDoMo.
- Експорт CSV: BOM UTF-8, розділювач `;`, дати `ДД.ММ.РРРР`, період
  день/тиждень/місяць/рік, можлива анонімізація.
- Діагностика підключення без зміни даних (DNS/TLS/HTTP, логін, cookies,
  ендпоїнт, JSON).
- Фоновий цикл `scripts/cycle_tabcalorie.php` із захистом від блокування:
  інтервал ≥15 хв, паузи 300–500 мс, backoff, single-flight, таймаути,
  circuit breaker (`CIRCUIT_UNTIL`), без паралельних запитів.
- Хук на зміну властивості `HOOK_PROPERTY` із дебаунсом `HOOK_DEBOUNCE_SEC`.
- Опційний базовий push у Nightscout `/api/v1/treatments.json`
  (`API-SECRET` = `sha1`, вимкнено за замовчуванням).
- Неофіційний запис назад у ТК через `tabcalorie_write_queue`, вимкнений за
  замовчуванням і з обов'язковим підтвердженням.
- Мови інтерфейсу: `uk` (дефолт), `en`, `ru`.
- Документація: README (uk/en/ru), docs/INSTALL, USAGE, TROUBLESHOOTING (uk/en/ru),
  SECURITY та API (uk/en), CHANGELOG, LICENSE, NOTICE.
- Скрипт пакування `build_package.py` → `dist/tabcalorie.tgz` і оригінальна
  іконка `img/tabcalorie.svg` / `img/modules/tabcalorie.png` (64×64).

### Безпека

- Секрети (`PASSWORD`, `NS_API_SECRET`) маскуються в UI та логах.
- SQL через `DBSafe`/плейсхедери, екранування HTML, CSRF-захист адмін-дій,
  перевірка прав `control_access`.
- HTTPS only, валідація TLS, таймаути, обмеження розміру відповіді.
- Контроль доступу до експорту персональних даних користувача та можливість анонімізації.
- Жодних реальних персональних даних чи секретів у репозиторії.

### Примітка

- TabCalorie **не є медичним пристроєм** і не надає медичних рекомендацій.
- Усі дані користувача зберігаються локально.

---

[1.1.0]: https://github.com/mambursoft/majordomo_TabCalorie/releases/tag/v1.1.0
[1.0.0]: https://github.com/mambursoft/majordomo_TabCalorie/releases/tag/v1.0.0

Див. також [[README|TabCalorie]] і [[HOME|HOME (MOC)]].
