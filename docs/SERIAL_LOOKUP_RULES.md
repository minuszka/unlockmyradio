# Serial Lookup Rules

Last update: 2026-04-13

## Goal

User enters full serial from radio label.  
System auto-detects whether direct lookup is possible or fallback to trailing digits is needed.

## Lookup Flow

1. Try exact serial match.
2. If no exact match, apply family-specific fallback:
   - Becker: last 4 digits.
   - Continental (A2C/A3C, VP1/VP2): last 4 digits.
   - Chrysler (T... serials): last 5 first, then last 4.
3. If multiple variants match, show model selector.

## Imported Variant Tags

Importer sets explicit variants to prevent data loss from overlapping serial spaces.
No TXT content changes are required; variant detection is filename-based.

- Becker:
  - `B4BTN` -> `Mercedes-Benz (4 buttons)`
  - `B6BTN` -> `Mercedes-Benz (6 buttons)`
  - `B8BTN` -> `Mercedes-Benz (8 buttons)`
- Chrysler:
  - `CHR4` -> `Chrysler/Dodge/Jeep (4-digit lookup)`
  - `CHR55` -> `Chrysler/Dodge/Jeep (5 buttons)`
  - `CHR56` -> `Chrysler/Dodge/Jeep (6 buttons)`
- Continental:
  - `CONT4` -> `Fiat/Alfa/VAG (VP1/VP2)`

## Why Selection Is Required

- Becker 4/6/8 button tables can produce different PINs for same short key.
- Chrysler 5-button and 6-button tables overlap on keyspace (`10000-99999`) but differ in codes.
- Chrysler 4-digit and Continental 4-digit overlap on keyspace (`0000-9999`) and differ in codes.

## Rebuild / Reimport Checklist

Run after deploying importer changes:

1. Backup database.
2. Truncate and reimport `radio_codes`.
3. Recreate indexes (if needed).

Example SQL:

```sql
TRUNCATE TABLE radio_codes;
```

Then import each folder:

```bash
php artisan import:radiocodes database_codes/Becker
php artisan import:radiocodes database_codes/Chrysler
php artisan import:radiocodes database_codes/Continental
php artisan import:radiocodes database_codes/FORD
php artisan import:radiocodes database_codes/GM
php artisan import:radiocodes database_codes/Grundig
php artisan import:radiocodes database_codes/Philips
php artisan import:radiocodes database_codes/VAG
```
