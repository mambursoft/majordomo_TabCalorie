---
title: "Інструкція встановлення TabCalorie"
aliases: [TabCalorie, INSTALL, Встановлення]
tags: [tabcalorie, majordomo, settings]
lang: uk
plugin: tabcalorie
version: 1.3.0
---

# Встановлення TabCalorie

[[INSTALL|Українська]] · [[INSTALL.en|English]] · [[INSTALL.ru|Русский]]

Версія 1.3.0 · 2026-11-20

Ця інструкція описує встановлення плагіна MajorDoMo `tabcalorie` у двох
способах: через Market та вручну з архіву `.tgz`.

> [!WARNING]
> **Нагадування:** TabCalorie не є медичним пристроєм і не надає медичних
> рекомендацій. Див. [[README|TabCalorie]].

---

## 1. Перед початком

### 1.1. Вимоги до середовища

| Вимога | Значення |
|---|---|
| MajorDoMo | інсталяція з механізмом модулів (`modules/`, `templates/`, `languages/`, `scripts/`, `img/modules/`) |
| PHP | **7.3–8.2** (мінімум 7.3) |
| Розширення PHP | `curl`, `json`, `mbstring` |
| БД | MySQL/MariaDB, доступ через MajorDoMo (`DBSafe`) |
| Мережа | HTTPS, валідна перевірка TLS, вихідний доступ до порталу ТК |
| Права | адміністратор MajorDoMo, права `control_access` |

### 1.2. Підготуйте обліковий запис ТК

1. Перевірте email, під яким ви авторизовані у застосунку «Таблиця калорійності»
   (меню **Мій профіль**).
2. Якщо ви входили через Google/Apple/Facebook і не маєте окремого пароля:
   відкрийте `https://www.tablycjakalorijnosti.com.ua/login`, введіть email,
   натисніть **«Забули пароль?»**. Лист із паролем для прямого входу не перерве
   роботу додатка на телефоні.
3. Для чеського порталу використовуйте `https://www.kaloricketabulky.cz`.

> [!WARNING]
> Не зберігайте реальний пароль у репозиторії чи текстових файлах — він
> вводиться лише у захищеній адмінці модуля й маскується.

---

## 2. Варіант A — встановлення через Market MajorDoMo

1. Увійдіть в адмінку MajorDoMo як адміністратор.
2. Відкрийте **Market / Каталог модулів** (або `/admin.php?p=market`).
3. У пошуку введіть `TabCalorie`.
4. Натисніть **Встановити** і підтвердьте дію.
5. Market розпакує файли у структуру MajorDoMo і запустить реєстрацію модуля.
6. Перейдіть до розділу **Модули / Модулі** і переконайтеся, що `tabcalorie`
   активний. Якщо ні — активуйте вручну.
7. Відкрийте налаштування модуля (див. розділ 4).

---

## 3. Варіант B — ручне встановлення з `.tgz`

### 3.1. Отримайте пакет

Зберіть пакет із вихідного репозиторію або завантажте готовий реліз:

```bash
git clone https://github.com/mambursoft/majordomo_TabCalorie.git
cd majordomo_TabCalorie
python3 build_package.py
```

Результат — `dist/tabcalorie.tgz`.

### 3.2. Розпакуйте у корінь MajorDoMo

```bash
cd /var/www/html            # шлях до кореня вашого MajorDoMo
tar -tzf /path/to/tabcalorie.tgz   # перегляньте вміст перед розпакуванням
tar -xzf /path/to/tabcalorie.tgz
```

> [!NOTE]
> Шлях `/var/www/html` є типовим; використовуйте реальний корінь вашої
> інсталяції. Архів не містить батьківської теки `majordomo_TabCalorie` —
> файли одразу лягають у `modules/`, `templates/`, тощо.

### 3.3. Перевірте структуру

```text
modules/tabcalorie/          php-код модуля (у тому числі tabcalorie.class.php)
templates/tabcalorie/        html-шаблони
languages/tabcalorie_*.php   мовні файли (uk — дефолт, en, ru)
scripts/cycle_tabcalorie.php фоновий цикл синхронізації
img/modules/tabcalorie.png   іконка 64×64
modinfo.json                 метадані для Market
README.md / README.en.md / README.ru.md
LICENSE / NOTICE.md / CHANGELOG.md
docs/                        документація
HOME.md                      карта контенту (MOC)
```

### 3.4. Активуйте модуль

1. Адмінка → **Модулі** → знайдіть `tabcalorie` у списку неактивних.
2. Натисніть **Встановити / Активувати**.
3. Під час встановлення модуль:
   - реєструється в `project_modules`;
   - виставляє прапорець `cms/modules_installed/tabcalorie.installed`;
   - додає рядок у таблицю `plugins`;
   - створює таблиці БД (див. нижче);
   - створює об'єкт `TabCalorie` класу `STabCalorie`;
   - додає іконку `img/modules/tabcalorie.png`.

### 3.5. Таблиці, які створюються

| Таблиця | Призначення |
|---|---|
| `tabcalorie_entries` | записи страв (дата, слот, страва, кількість×одиниця, БЖУ, GI, нотатки, `source`, `synced_at`); унікальний ключ `date + slot + food_guid + unit_raw` |
| `tabcalorie_foods` | довідник страв на 100 г + службові поля для write-back (`FOOD_ID`, `FOOD_URL`, `UNIT_GUID`, `UNIT_OPTIONS`, `DEFAULT_MULTIPLIER`, `DEFAULT_SLOT`, `SERVICE_JSON`) |
| `tabcalorie_days` | добові підсумки, включно з `STEPS`, `ACTIVITY_KCAL`, `ACTIVITY_COUNT` |
| `tabcalorie_activities` | активність із `data.activities` ТК; унікальний ключ — дата + ID активності |
| `tabcalorie_sync_log` | журнал синхронізацій без секретів; ротація `LOG_RETENTION_DAYS` / `LOG_MAX_ROWS` |
| `tabcalorie_write_queue` | черга записів назад у ТК із підтвердженням; TTL `WRITE_QUEUE_TTL_DAYS` |

### 3.6. Налаштуйте фоновий цикл

`scripts/cycle_tabcalorie.php` призначений для запуску за розкладом MajorDoMo
або системним cron. Мінімальний рекомендований інтервал — **15 хвилин**,
типовий — 60 хвилин (`SYNC_INTERVAL_MIN=60`).

Приклад запису в cron (підставте реальний шлях):

```cron
*/15 * * * * php /var/www/html/scripts/cycle_tabcalorie.php >/dev/null 2>&1
```

> [!TIP]
> Цикл захищений single-flight блокуванням: паралельні запуски не створюють
> одночасних запитів до ТК.

---

## 4. Початкове налаштування

Відкрийте адмінку модуля й заповніть ключі конфігурації (зберігаються в
`project_modules.DATA`):

| Ключ | Що вказати | За замовчуванням |
|---|---|---|
| `BASE_URL` | `https://www.tablycjakalorijnosti.com.ua` (або CZ / `custom`) | `https://www.tablycjakalorijnosti.com.ua` |
| `PROVIDER` | `ua`, `cz` або `custom` | `ua` |
| `EMAIL` | email облікового запису ТК | — |
| `PASSWORD` | пароль прямого входу (маскується) | — |
| `SYNC_ENABLED` | `1` — увімкнути цикл | `1` |
| `SYNC_INTERVAL_MIN` | інтервал, хв (≥15) | `60` |
| `SYNC_DAYS` | глибина інкремента, днів | `2` |
| `HOOK_ENABLED` | `0` — хук вимкнено | `0` |
| `HOOK_PROPERTY` | `Obj.Prop` тригера | — |
| `HOOK_DEBOUNCE_SEC` | дебаунс, сек | `60` |
| `RETENTION_DAYS` | зберігання даних | `1825` (5 років) |
| `ACTIVITY_ENABLED` | `1` — імпорт активності з ТК; `0` — вимкнути | `1` |
| `LOG_RETENTION_DAYS` | ротація логу за віком, днів | `30` |
| `LOG_MAX_ROWS` | ротація логу за кількістю рядків | `2000` |
| `WRITE_QUEUE_TTL_DAYS` | TTL черги запису, днів | `7` |
| `LANGUAGE` | `uk` / `en` / `ru` | `uk` |
| `GRAMS_PER_XE` | г на 1 ХО | `10` |
| `CALORIES_TARGET` | добова норма калорій, ккал | `1800` |
| `PROTEIN_TARGET` | добова норма білків, г | `90` |
| `FAT_TARGET` | добова норма жирів, г | `60` |
| `CARBS_TARGET` | добова норма вуглеводів, г | `120` |
| `MEAL_NORM_SHARE` | частка основного прийому (10–33, %) | `25` |
| `CSV_DELIMITER` | розділювач CSV | `;` |
| `WRITEBACK_ENABLED` | запис у ТК | `0` (вимкнено) |
| `WRITEBACK_CONFIRM` | підтвердження запису | `1` |
| `NS_ENABLED` | Nightscout | `0` (вимкнено) |
| `NS_URL` | база Nightscout | — |
| `NS_API_SECRET` | секрет Nightscout (маскується) | — |

> [!TIP]
> `MEAL_NORM_SHARE` задає частку добової норми для основного прийому їжі
> (сніданок/обід/вечеря — слоти 1, 3, 5). Решта норми ділиться порівну між
> перекусами (2, 4, 6). Діапазон — 10–33 %, за замовчуванням 25%. Якщо всі норми
> заповнені (не 0), у вкладці **Огляд** для кожного з 6 прийомів їжі
> показується прогресбар по вуглеводах, калоріях, білках і жирах відносно
> розрахованої норми прийому; перевищення виділяється кольором.

Повний опис із поясненнями — у [[README#Налаштування|README]].

### 4.1. Перевірка підключення

Натисніть **«Перевірити підключення»** в адмінці. Діагностика виконує:

- перевірку DNS/TLS/HTTP;
- логін `POST /login/create?format=json&voucher=false` (пароль як `md5`);
- отримання та перевірку cookies;
- пробний `GET /user/diary/ДД.ММ.РРРР/get?format=json`;
- парсинг JSON і перевірку 6 слотів.

Діагностика **не змінює дані**. Якщо щось не так — див.
[[TROUBLESHOOTING|Усунення несправностей]].

### 4.2. Перший запуск

- [ ] Увімкніть `SYNC_ENABLED=1` (якщо ще не увімкнено).
- [ ] Запустіть цикл вручну або дочекайтеся спрацьовування розкладу.
- [ ] Відкрийте публічну сторінку плагіна → вкладка **Огляд**.
- [ ] Переконайтеся, що відображається остання заповнена доба й коректні суми.
- [ ] Перевірте вкладку **Діагностика** → останній успішний sync і журнал.
- [ ] Переконайтеся, що прогресбари норм у вкладці **Огляд** відображаються коректно.

### 4.3. Що нового у v1.2.0

- [ ] Вкладка **Активність**: кроки й спалені ккал з `data.activities` ТК,
  `ACTIVITY_ENABLED=1` (див. [[USAGE|Використання]]).
- [ ] Форма страви у вкладці **Довідник страв** зі службовими полями для
  write-back у ТК (див. [[USAGE|Використання]]).
- [ ] Властивості об'єкта `status`, `raw`, `ActivitySteps`, `ActivityKcal`,
  `ActivityCount` (див. [[API|API та формати даних]]).
- [ ] Ротація `tabcalorie_sync_log` (`LOG_RETENTION_DAYS=30`, `LOG_MAX_ROWS=2000`)
  і TTL черги `tabcalorie_write_queue` (`WRITE_QUEUE_TTL_DAYS=7`).

> [!NOTE]
> Health Connect у ТК односторонній: плагін лише зчитує активність із щоденника
> ТК і не пише в Health Connect.

---

## 5. Оновлення

1. Зробіть резервну копію БД (таблиць `tabcalorie_*`) і конфігурації.
2. Зберіть новий `.tgz` і розпакуйте поверх поточної інсталяції:
   ```bash
   tar -xzf tabcalorie.tgz -C /var/www/html
   ```
3. Відкрийте адмінку — модуль застосує міграції таблиць.
4. Перевірте **Діагностику** та вкладку **Огляд**.
5. Конфігурація в `project_modules.DATA` зберігається.

---

## 6. Видалення

1. Адмінка → **Модулі** → деактивуйте `tabcalorie`.
2. За бажанням видаліть таблиці `tabcalorie_entries`, `tabcalorie_foods`,
   `tabcalorie_days`, `tabcalorie_activities`, `tabcalorie_sync_log`,
   `tabcalorie_write_queue`.
3. Видаліть файли `modules/tabcalorie/`, `templates/tabcalorie/`,
   `languages/tabcalorie_*.php`, `scripts/cycle_tabcalorie.php`,
   `img/modules/tabcalorie.png`.
4. Конфіг у `project_modules.DATA` очистіть вручну, якщо він більше не потрібен.
5. Зніміть запис cron для `cycle_tabcalorie.php`.

---

## 7. Часті питання з встановлення

**Чи потрібно створювати таблиці вручну?**
Ні. Модуль створює їх автоматично під час встановлення (heredoc-формат
`table: FIELD type...`, дзеркало в `modules/tabcalorie/db_install.php`).

**Чому після розпакування модуль не видно?**
Переконайтеся, що розпакували у корінь MajorDoMo, а не в підпапку, і що
`modules/tabcalorie/tabcalorie.class.php` існує. Оновіть кеш модулів в адмінці.

**Чи можна встановити на PHP 7.3?**
Так, 7.3 — мінімальна підтримувана версія; перевірено до 8.2.

**Де взяти реліз?**
https://github.com/mambursoft/majordomo_TabCalorie/releases

Див. також [[HOME|HOME (MOC)]].
