---
title: "Дослідження API: зведення даних харчування з «Таблиці калорійності»"
aliases: [TabCalorie, perplexity_research, Дослідження API]
tags: [tabcalorie, majordomo, api]
lang: uk
plugin: tabcalorie
version: 1.2.2
---

# Автоматичне зведення даних харчування з «Таблиці калорійності», ін'єкцій та глікемії для звітності

> [!NOTE]
> Дослідницький документ. Версія 1.1.0 · 2026-11-20. Джерело технічних фактів для
> [[API|API та формати даних]].

Цей посібник містить готову схему зведення даних харчування та перевірений код **Google Apps Script**, який автоматично забирає спожиті вуглеводи з Android-застосунку **«Таблиця калорійності»** ([Dine4Fit у Google Play](https://play.google.com/store/apps/details?id=cz.psc.android.kaloricketabulky&hl=uk)) через його офіційний хмарний портал [tablycjakalorijnosti.com.ua](https://www.tablycjakalorijnosti.com.ua/) безпосередньо у вашу Google Таблицю для контролю споживання та формування звітності.

---

## Чому стандартні інтеграції на Android не передають вуглеводи

1. **Одностороння робота Health Connect та Google Fit:** У налаштуваннях Android-версії «Таблиці калорійності» підключення до Health Connect, Google Fit, Garmin та Samsung Health передає лише фізичну активність і спалені калорії **у** щоденник харчування, але не експортує спожиті вуглеводи (КБЖВ) у зовнішні застосунки для контролю харчування ([Довідкова служба Dine4Fit про Health Connect](https://d4f.freshdesk.com/en/support/solutions/articles/22000292402-what-is-health-connect-), [Мануал tablycjakalorijnosti.com.ua](https://www.tablycjakalorijnosti.com.ua/manual)).
2. **Обмеження ручного експорту з сайту:** Вебверсія дозволяє вивантажити раціон у PDF або XLS ([Мануал Kalorické Tabulky](https://www.kaloricketabulky.cz/manual)), проте формує поденні дієтологічні файли без прив'язки до болюсів інсуліну та профілю цукрів ([Опис експорту kaloricketabulky-diary-export](https://github.com/rickmtdev/kaloricketabulky-diary-export)).
3. **Рішення через внутрішній JSON API вебпорталу:** Оскільки мобільний додаток миттєво синхронізує всі записи з вебсайтом `www.tablycjakalorijnosti.com.ua`, внутрішній веб-ендпоїнт `/user/diary/ДД.ММ.РРРР/get?format=json` віддає повну структуру дня у форматі JSON — включно з грамами вуглеводів, цукрів, клітковини, білків, жирів і переліком страв по кожному прийому їжі ([Документація неофіційного SDK kaloricketabulky](https://github.com/tomasvotava/kaloricketabulky), [Клієнт kaloricke-tabulky-api](https://github.com/francbohuslav/kaloricke-tabulky-api)).

---

## Підготовка облікового запису (один раз, 2 хвилини)

1. Перевірте, під якою електронною адресою ви авторизовані у застосунку «Таблиця калорійності» на смартфоні (меню **Мій профіль**).
2. Якщо ви реєструвалися через кнопку **«Увійти через Google»** (або Apple/Facebook) і не маєте окремого пароля, відкрийте сторінку входу [tablycjakalorijnosti.com.ua/login](https://www.tablycjakalorijnosti.com.ua/login), введіть вашу електронну пошту та натисніть **«Забули пароль?»**. На пошту надійде пароль для прямого входу — при цьому робота додатка на телефоні не перерветься ([Інструкція підтримки Dine4Fit щодо пароля для входу на сайт](https://d4f.freshdesk.com/uk/support/solutions/articles/22000292848-%D1%8F%D0%BA-%D1%83%D0%B2%D1%96%D0%B9%D1%82%D0%B8-%D0%BD%D0%B0-%D1%81%D0%B0%D0%B9%D1%82-%D1%8F%D0%BA%D1%89%D0%BE-%D0%B2%D0%B8-%D0%BA%D0%BE%D1%80%D0%B8%D1%81%D1%82%D0%BE%D0%B2%D1%83%D1%8E%D1%82%D1%8C-%D0%B2%D1%85%D1%96%D0%B4-%D1%87%D0%B5%D1%80%D0%B5%D0%B7-%D0%BF%D1%80%D0%B8%D1%85%D0%BE%D0%B2%D0%B0%D0%BD%D1%83-%D0%B5%D0%BB%D0%B5%D0%BA%D1%82%D1%80%D0%BE%D0%BD%D0%BD%D1%83-%D0%BF%D0%BE%D1%88%D1%82%D1%83-apple-)).

---

## Готовий скрипт для вашої Google Таблиці (Google Apps Script)

Відкрийте вашу Google Таблицю для зведення даних, у верхньому меню оберіть **Розширення → Apps Script**, видаліть порожній шаблон і вставте наведений нижче код, після чого натисніть **Зберегти**.

```javascript
/**
 * Автоматичне завантаження спожитих вуглеводів із tablycjakalorijnosti.com.ua
 * у Google Таблицю для зведення даних і формування звітності.
 */

const KT_BASE_URL = 'https://www.tablycjakalorijnosti.com.ua';
const TARGET_SHEET_NAME = 'Вуглеводи_Авто';
const GRAMS_PER_XE = 10; // Змініть на 12, якщо ваш лікар рахує 1 ХО = 12 г вуглеводів

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('TabCalorie-Звіт')
    .addItem('1. Зберегти логін та пароль Таблиці калорійності', 'setupCredentialsDialog')
    .addSeparator()
    .addItem('Оновити вуглеводи за сьогодні та вчора', 'syncLast2Days')
    .addItem('Завантажити вуглеводи за останні 14 днів', 'syncLast14Days')
    .addItem('Завантажити вуглеводи за останні 30 днів', 'syncLast30Days')
    .addSeparator()
    .addItem('Увімкнути щогодинне автооновлення', 'installHourlyTrigger')
    .addToUi();
}

/**
 * Безпечне збереження email і пароля у властивостях скрипта (не у клітинках таблиці).
 */
function setupCredentialsDialog() {
  const ui = SpreadsheetApp.getUi();
  const emailResp = ui.prompt(
    'Налаштування Таблиці калорійності',
    'Введіть email облікового запису tablycjakalorijnosti.com.ua:',
    ui.ButtonSet.OK_CANCEL
  );
  if (emailResp.getSelectedButton() !== ui.Button.OK) return;

  const passResp = ui.prompt(
    'Налаштування Таблиці калорійності',
    'Введіть пароль (він буде збережений у закритому сховищі PropertiesService):',
    ui.ButtonSet.OK_CANCEL
  );
  if (passResp.getSelectedButton() !== ui.Button.OK) return;

  const props = PropertiesService.getScriptProperties();
  props.setProperty('KT_EMAIL', emailResp.getResponseText().trim());
  props.setProperty('KT_PASSWORD', passResp.getResponseText());
  ui.alert('Дані збережено. Тепер оберіть у меню «TabCalorie-Звіт» завантаження за 14 днів для перевірки.');
}

function syncLast2Days() {
  syncCarbsForDays(2);
}

function syncLast14Days() {
  syncCarbsForDays(14);
}

function syncLast30Days() {
  syncCarbsForDays(30);
}

/**
 * Встановлює фоновий тригер для автоматичного підтягування вуглеводів щогодини.
 */
function installHourlyTrigger() {
  const triggers = ScriptApp.getProjectTriggers();
  triggers.forEach(t => {
    if (t.getHandlerFunction() === 'syncLast2Days') {
      ScriptApp.deleteTrigger(t);
    }
  });
  ScriptApp.newTrigger('syncLast2Days').timeBased().everyHours(1).create();
  SpreadsheetApp.getUi().alert('Автооновлення увімкнено (щогодини оновлюються сьогоднішній та вчорашній дні).');
}

/**
 * Обчислення MD5-хешу рядка UTF-8 у нижньому регістрі (формат входу Dine4Fit).
 */
function md5Hex(input) {
  const rawHash = Utilities.computeDigest(
    Utilities.DigestAlgorithm.MD5,
    input,
    Utilities.Charset.UTF_8
  );
  return rawHash
    .map(b => (b < 0 ? b + 256 : b).toString(16).padStart(2, '0'))
    .join('');
}

/**
 * Авторизація на tablycjakalorijnosti.com.ua та отримання заголовка Cookie.
 */
function loginToKalorijnosti(email, password) {
  const url = KT_BASE_URL + '/login/create?format=json&voucher=false';
  const payload = JSON.stringify({
    email: email,
    password: md5Hex(password)
  });

  const resp = UrlFetchApp.fetch(url, {
    method: 'post',
    contentType: 'application/json',
    payload: payload,
    followRedirects: false,
    muteHttpExceptions: true
  });

  const body = JSON.parse(resp.getContentText('UTF-8'));
  if (body.code !== 0) {
    throw new Error('Помилка входу в Таблицю калорійності: ' + (body.message || ('код ' + body.code)));
  }

  const allHeaders = resp.getAllHeaders();
  const rawSetCookie = allHeaders['Set-Cookie'] || allHeaders['set-cookie'];
  if (!rawSetCookie) {
    throw new Error('Сервер не повернув сесійні кукі під час входу.');
  }

  const cookieList = Array.isArray(rawSetCookie) ? rawSetCookie : [rawSetCookie];
  const cookiePairs = cookieList.map(c => c.split(';')[0].trim()).filter(Boolean);
  return cookiePairs.join('; ');
}

/**
 * Завантаження щоденника харчування за конкретну дату (об'єкт Date).
 */
function fetchDayDiary(dayDate, cookieHeader) {
  const tz = SpreadsheetApp.getActiveSpreadsheet().getSpreadsheetTimeZone() || 'Europe/Kyiv';
  const pathDate = Utilities.formatDate(dayDate, tz, 'dd.MM.yyyy');
  const isoDate = Utilities.formatDate(dayDate, tz, 'yyyy-MM-dd');

  const url = KT_BASE_URL + '/user/diary/' + pathDate + '/get?format=json';
  const resp = UrlFetchApp.fetch(url, {
    method: 'get',
    headers: {
      'Accept': 'application/json',
      'Cookie': cookieHeader
    },
    followRedirects: false,
    muteHttpExceptions: true
  });

  if (resp.getResponseCode() !== 200) {
    throw new Error('HTTP ' + resp.getResponseCode() + ' при читанні дати ' + pathDate);
  }

  const envelope = JSON.parse(resp.getContentText('UTF-8'));
  if (envelope.code !== 0 || !envelope.data) {
    throw new Error('Помилка API для дати ' + pathDate + ': ' + (envelope.message || envelope.code));
  }

  const d = envelope.data;
  const times = d.times || [];

  // У сервісі фіксовано 6 слотів:
  // 0: Сніданок, 1: Перекус (Другий сніданок), 2: Обід, 3: Підвечірок, 4: Вечеря, 5: Друга вечеря
  const slotCarbs = [0, 0, 0, 0, 0, 0];
  const slotMenus = [];

  for (let i = 0; i < 6; i++) {
    const slot = times[i];
    if (!slot || !slot.foodstuff || slot.foodstuff.length === 0) continue;

    let mealCarbSum = 0;
    const itemLabels = [];

    slot.foodstuff.forEach(item => {
      const c = Number(item.carbohydrate || 0);
      mealCarbSum += c;
      const itemTitle = item.title || 'продукт';
      const itemUnit = item.unit ? (' [' + item.unit + ']') : '';
      itemLabels.push(itemTitle + itemUnit + ': ' + round1(c) + 'г');
    });

    slotCarbs[i] = round1(mealCarbSum);
    if (itemLabels.length > 0) {
      const slotTitle = slot.title || ('Прийом ' + (i + 1));
      slotMenus.push(slotTitle + ' (' + round1(mealCarbSum) + 'г): ' + itemLabels.join(', '));
    }
  }

  const totalCarbs = round1(Number(d.carbohydrateTotal || 0));
  const totalXE = round1(totalCarbs / GRAMS_PER_XE);

  return [
    isoDate,
    totalCarbs,
    totalXE,
    slotCarbs[0], // Сніданок (г)
    slotCarbs[1], // Перекус 1 (г)
    slotCarbs[2], // Обід (г)
    slotCarbs[3], // Підвечірок (г)
    slotCarbs[4], // Вечеря (г)
    slotCarbs[5], // Друга вечеря (г)
    round1(Number(d.sugarTotal || 0)),
    round1(Number(d.fiberTotal || 0)),
    round1(Number(d.proteinTotal || 0)),
    round1(Number(d.fatTotal || 0)),
    round1(Number(d.energyTotal || 0)),
    slotMenus.join(' | ')
  ];
}

function round1(val) {
  return Math.round((val + Number.EPSILON) * 10) / 10;
}

/**
 * Основна функція синхронізації за N останніх днів (оновлює існуючі рядки за датою або додає нові).
 */
function syncCarbsForDays(daysCount) {
  const props = PropertiesService.getScriptProperties();
  const email = props.getProperty('KT_EMAIL');
  const password = props.getProperty('KT_PASSWORD');

  if (!email || !password) {
    throw new Error('Спочатку вкажіть логін і пароль через меню «TabCalorie-Звіт → 1. Зберегти логін та пароль».');
  }

  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName(TARGET_SHEET_NAME);
  const headers = [
    'Дата (YYYY-MM-DD)',
    'Вуглеводи за день (г)',
    'Усього ХО',
    'Сніданок (г)',
    'Перекус 1 (г)',
    'Обід (г)',
    'Підвечірок (г)',
    'Вечеря (г)',
    'Друга вечеря (г)',
    'Цукри (г)',
    'Клітковина (г)',
    'Білки (г)',
    'Жири (г)',
    'Енергія (ккал)',
    'Деталізація страв'
  ];

  if (!sheet) {
    sheet = ss.insertSheet(TARGET_SHEET_NAME);
    sheet.appendRow(headers);
    sheet.getRange(1, 1, 1, headers.length).setFontWeight('bold');
    sheet.setFrozenRows(1);
  }

  const cookieHeader = loginToKalorijnosti(email, password);

  // Зчитуємо наявні дати, щоб оновлювати їх без дублікатів
  const existingData = sheet.getDataRange().getValues();
  const tz = ss.getSpreadsheetTimeZone() || 'Europe/Kyiv';
  const rowIndexByIsoDate = {};

  for (let r = 1; r < existingData.length; r++) {
    const cellVal = existingData[r][0];
    if (!cellVal) continue;
    const key = cellVal instanceof Date
      ? Utilities.formatDate(cellVal, tz, 'yyyy-MM-dd')
      : String(cellVal).trim();
    rowIndexByIsoDate[key] = r + 1; // 1-based row number
  }

  const today = new Date();
  for (let offset = daysCount - 1; offset >= 0; offset--) {
    const d = new Date(today.getTime());
    d.setDate(today.getDate() - offset);

    const rowValues = fetchDayDiary(d, cookieHeader);
    const isoKey = rowValues[0];

    if (rowIndexByIsoDate[isoKey]) {
      sheet.getRange(rowIndexByIsoDate[isoKey], 1, 1, rowValues.length).setValues([rowValues]);
    } else {
      sheet.appendRow(rowValues);
      rowIndexByIsoDate[isoKey] = sheet.getLastRow();
    }

    if (offset > 0) {
      Utilities.sleep(400); // Пауза між запитами, щоб не навантажувати сервер
    }
  }
}
```

---

## Опціональне автоматичне вивантаження у Nightscout (єдиний графік для звітності)

Якщо ви використовуєте або плануєте підняти власний екземпляр **Nightscout** (куди сенсор через **xDrip+** або **Juggluco** вже передає криву глюкози), той самий скрипт у Google Таблиці може автоматично надсилати як спожиті вуглеводи з «Таблиці калорійності», так і дані інсуліну в ендпоінт `/api/v1/treatments.json` ([Технічна документація Nightscout API](https://github.com/nightscout/documentation/blob/master/Nightscout/EN/Technical%20info/api.rst)):

```javascript
/**
 * Приклад функції відправки комбінованого запису (вуглеводи + інсулін) у Nightscout.
 * apiSecretSha1 — SHA-1 хеш вашого API_SECRET у нижньому регістрі.
 */
function pushMealBolusToNightscout(nsBaseUrl, apiSecretSha1, isoTimestamp, carbsGrams, insulinUnits, mealNotes) {
  const url = nsBaseUrl.replace(/\/$/, '') + '/api/v1/treatments.json';
  const payload = [{
    eventType: carbsGrams > 0 ? 'Meal Bolus' : 'Correction Bolus',
    created_at: isoTimestamp, // у форматі ISO UTC, напр. new Date().toISOString()
    carbs: carbsGrams > 0 ? Number(carbsGrams) : null,
    insulin: insulinUnits > 0 ? Number(insulinUnits) : null,
    enteredBy: 'GoogleSheets-KT-Sync',
    notes: mealNotes || ''
  }];

  UrlFetchApp.fetch(url, {
    method: 'post',
    contentType: 'application/json',
    headers: { 'API-SECRET': apiSecretSha1 },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  });
}
```

Після цього через інструмент [Nightscout Reporter](https://nightscout.github.io/nightscout/reports/) або штатне меню **Reports → Day to day / Percentile Chart** генерується стандартний клінічний PDF, де на кожній добі суміщені лінія глюкози, грами вуглеводів, одиниці інсуліну та базальний інсулін ([Документація звітів Nightscout для клініцистів](https://nightscout.github.io/clinicians/reports/)).

---

## Рекомендована структура зведеного аркуша «Звіт» у Google Таблиці

Щоб фахівець міг за 2–3 хвилини оцінити адекватність доз і скоригувати вуглеводні коефіцієнти (УК / ICR), створіть у вашій таблиці окремий аркуш **«Звіт_14_днів»** із такою структурою стовпців (для друку в альбомній орієнтації А4 через **Файл → Завантажити → Документ PDF**):

| Група показників | Стовпці у зведеному рядку за 1 день | Як заповнюється |
|---|---|---|
| **Ідентифікація та базал** | `Дата`, `Базальний інсулін (од. та час)` | З вашого аркуша інсуліну |
| **Сніданок** | `Цукор до`, `Вугл. (г)`, `Інсулін (од.)`, `Факт. УК (г/од.)`, `Цукор через 2 год` | Вуглеводи з `Вуглеводи_Авто` (`XLOOKUP`), записи з аркуша інсуліну, `УК = Вугл / Інсулін` |
| **Обід** | `Цукор до`, `Вугл. (г)`, `Інсулін (од.)`, `Факт. УК (г/од.)`, `Цукор через 2 год` | Аналогічно за слотом «Обід» |
| **Вечеря** | `Цукор до`, `Вугл. (г)`, `Інсулін (од.)`, `Факт. УК (г/од.)`, `Цукор через 2 год` | Аналогічно за слотом «Вечеря» |
| **Перекуси та корекції (ДПС)** | `Перекуси (г вугл.)`, `Додаткові дози інсуліну / ДПС (од.)`, `Гіпо-купірування (г швидких вугл.)` | Сума слотів Перекус 1 + Підвечірок + Друга вечеря та окремі ДПС |
| **Добові підсумки** | `Усього вуглеводів (г / ХО)`, `Болюс за день (од.)`, `TDD — добовий інсулін (од.)`, `% Базал / % Болюс`, `Середній цукор / % TIR` | Автоматичні формули `SUM` та середні показники за 14 днів унизу таблиці |

### Приклад формули підтягування вуглеводів на зведений аркуш

Якщо у клітинці `A5` зведеного аркуша стоїть дата у форматі `YYYY-MM-DD` (або стандартна дата таблиці, перетворена через `TEXT(A5; "yyyy-mm-dd")`), формула для автоматичного підтягування вуглеводів сніданку виглядає так:

```text
=IFERROR(XLOOKUP(TEXT(A5; "yyyy-mm-dd"); 'Вуглеводи_Авто'!$A:$A; 'Вуглеводи_Авто'!$D:$D; 0); 0)
```

Де стовпець `$D:$D` — Сніданок, `$F:$F` — Обід, `$H:$H` — Вечеря, а `$B:$B` — добова сума вуглеводів у грамах.

---

## Посилання на перевірені джерела

- Офіційний український портал та довідник застосунку: [Таблиця Калорійності — мануал користувача](https://www.tablycjakalorijnosti.com.ua/manual)
- Довідковий центр розробника Dine4Fit щодо синхронізації: [Helpcentrum Dine4Fit — Підключені додатки та Health Connect](https://d4f.freshdesk.com/uk/support/solutions/articles/22000292744-%D1%8F%D0%BA%D1%96-%D0%B4%D0%BE%D0%B4%D0%B0%D1%82%D0%BA%D0%B8-%D0%BC%D0%BE%D0%B6%D0%BD%D0%B0-%D0%BF%D1%96%D0%B4%D0%BA%D0%BB%D1%8E%D1%87%D0%B8%D1%82%D0%B8-%D0%B4%D0%BE-%D1%82%D0%B0%D0%B1%D0%BB%D0%B8%D1%86%D1%96-%D0%BA%D0%B0%D0%BB%D0%BE%D1%80%D1%96%D0%B9%D0%BD%D0%BE%D1%81%D1%82%D1%96-)
- Опис внутрішніх JSON-моделей щоденника Dine4Fit / Kalorické Tabulky: [kaloricketabulky Python SDK (GitHub)](https://github.com/tomasvotava/kaloricketabulky) та [kaloricke-tabulky-api (GitHub)](https://github.com/francbohuslav/kaloricke-tabulky-api)
- Стандарти звітів глікемії та інсулінотерапії для ендокринологів: [LibreView Clinical Reports](https://www.freestyleprovider.abbott/us-en/libreview.html), [xDrip+ Endocrinologist Reports](https://navid200.github.io/xDrip/docs/Report.html), [Nightscout Clinician Reports](https://nightscout.github.io/clinicians/reports/), [Juggluco Export & Webserver](https://www.juggluco.nl/Jugglucohelp/helpexport.html)

---

Див. також [[API|API та формати даних]], [[README|TabCalorie]] і [[HOME|HOME (MOC)]].
