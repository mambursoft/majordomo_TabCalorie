---
title: "Тестування TabCalorie"
aliases: [TabCalorie, tests/README, Тести]
tags: [tabcalorie, majordomo, testing]
lang: uk
plugin: tabcalorie
version: 1.3.1
---

# Тести TabCalorie

> [!NOTE]
> Версія 1.3.1 · 2026-11-20. Тести не потребують реального облікового запису ТК
> і не роблять зовнішніх запитів.

## Юніт-тести

```bash
php tests/test_unit.php
```

Перевіряють парсинг чисел/одиниць (`"166 x 1 g"` → 166 g), розбір щоденника,
агрегацію 6 слотів і 3 груп, CSV (BOM, `;`, екранування формул), побудову
pChart-посилання та демо-режим.

## Інтеграційний тест API (локальний mock)

```bash
php -S 127.0.0.1:8765 tests/mock_server.php
# у другому терміналі:
php tests/test_api.php
```

Mock емулює логін (з `Set-Cookie`), щоденник і запис страви. Тест перевіряє,
що cookie повертається назад, тіло запиту містить правильний `diaryTimeGuid`
і грами, а пароль не потрапляє у запит/логи.

Для іншого порту: `TC_TEST_BASE_URL=http://127.0.0.1:9999 php tests/test_api.php`.

## Fixtures

`tests/fixtures/diary.json` — структура відповіді API з 6 слотами, двома стравами
і чеським форматом числа (`"1 234,5"`). Реальних персональних даних немає.

Див. також [[README|TabCalorie]], [[API|API та формати даних]] і [[HOME|HOME (MOC)]].
