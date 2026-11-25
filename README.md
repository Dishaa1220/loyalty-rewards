# Customer Loyalty Rewards System

A reward system for a factory that sells products in boxes. Every box carries its own unique QR code.
After buying, the customer scans the code, logs in, and earns coins. Coins convert to Indian rupees
and can be cashed out once the factory owner approves the request. The owner manages products, code
batches, the coin rate and cash-outs, and monitors redemptions and fraud from an admin area.

Deployed link: not deployed (runs locally on XAMPP).

## How the three requirements are answered

**1. How a customer proves they bought a product.**
The QR code is printed inside the box (under the seal), so it can only be seen after the product has been
bought and opened. Scanning it and logging in claims the coins for that customer's account.

**2. How fraud and code reuse are prevented.**
- Each code is 12 random characters plus an HMAC signature, so codes cannot be guessed or invented.
- A code can be redeemed only once: a single atomic `UPDATE ... WHERE status = 'unused'` inside a database transaction. Two people pressing the button at the same moment cannot both win.
- A UNIQUE key on the coin ledger is a last safety net.
- Scanning a QR only shows a confirm page; coins are given only on a button press (POST with a CSRF token), so link previews cannot burn a code.
- Rate limiting by account and IP address; every scan attempt is logged.
- Cash-outs lock the customer's row (no overdraw) and use atomic decisions (no double payout or double refund).

**3. How the factory owner views, manages and monitors rewards.**
- Admin dashboard, products and coin values, coin-to-rupee rate, minimum cash-out.
- Code batch generator with a printable QR sheet.
- Cash-out review: approve or reject with a note.
- Monitoring page: headline numbers, 14-day trend, per-product and per-batch results, rule-based fraud flags, a filterable scan log, and a button to block (or unblock) all unused codes of a batch.

## Features

**Customer:** register and log in, claim a code (scan or type), wallet with balance, rupee value and history, cash-out requests.
**Owner (admin):** everything listed under requirement 3.

## Tech stack

PHP 8 (plain, no framework), MySQL (InnoDB), PDO, Bootstrap 5 (CDN), qrcode.js (CDN), XAMPP for local development.
See [ARCHITECTURE.md](ARCHITECTURE.md) for the reasons behind each choice, the data model and the diagrams.

## Setup and run (Windows with XAMPP)

1. **Get the code.** Clone into XAMPP's web folder:
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/Dishaa1220/loyalty-rewards.git
   ```
2. **Start XAMPP.** Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. **Create your `.env`.** In the project folder, copy `.env.example` to a new file named `.env` and fill it in:
   ```
   DB_HOST=localhost
   DB_NAME=loyalty_db
   DB_USER=root
   DB_PASS=
   QR_SECRET_KEY=<a long random string, at least 16 characters>
   BASE_URL=/loyalty-rewards/public
   APP_URL=http://localhost/loyalty-rewards/public
   ```
   To generate a random `QR_SECRET_KEY`:
   ```
   C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(24));"
   ```
   `.env` is excluded from Git. Never commit it.
4. **Create the database.** Open `http://localhost/phpmyadmin`, go to the **Import** tab, and import these two files in order:
   1. `database/schema.sql` (creates the `loyalty_db` database and its tables)
   2. `database/002_payouts.sql` (adds cash-out requests)
5. **Open the app:** `http://localhost/loyalty-rewards/public/register.php` and register your account.
6. **Make yourself the owner (admin).** Public registration only creates customers. In phpMyAdmin, open the **SQL** tab of `loyalty_db` and run:
   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'your_email@example.com';
   ```
   Log out and log in again. An **Admin** link appears in the navbar.

## Try it (walkthrough)

1. As admin: **Admin → Manage products** and add a product (for example 10 coins per box). Optionally set the coin rate and minimum cash-out under **Coin settings**.
2. **Code batches and QR codes**: generate a batch and open its sheet. Each QR also has its code printed under it.
3. As a customer (a second account, ideally in a private window): copy a code from the sheet into **Claim a code**, press Check, then **Claim coins**.
4. The customer's wallet shows the coins. Try the same code again: it is refused.
5. Customer: **Cash out**, enter coins and a UPI ID. Admin: **Cash-out requests**, approve or reject.
6. Admin: **Monitoring** shows the numbers, flags and scan log.

To scan with a real phone, set `APP_URL` to your computer's address on the same Wi-Fi (for example `http://192.168.1.5/loyalty-rewards/public`), reload the sheet, and scan from the phone. Windows Firewall may need to allow Apache.

## Demo data (optional)

```
C:\xampp\php\php.exe database\seed_demo.php
```
Creates two demo customers (`ravi@demo.test` and `priya@demo.test`, password `Demo@123`), three products and ten codes, and prints a ready-to-open link for each code. It needs an admin account to exist first. Remove everything again with:
```
C:\xampp\php\php.exe database\seed_demo.php reset
```
Always run `reset` before any real deployment, because the demo accounts have a known password.

## Tests

Automated tests (run from the project folder). Each prints `PASS` or `FAIL` for every check and ends with `N passed, 0 failed`:
```
C:\xampp\php\php.exe tests\test_codes.php
C:\xampp\php\php.exe tests\test_redeem.php
C:\xampp\php\php.exe tests\test_wallet.php
C:\xampp\php\php.exe tests\test_payouts.php
C:\xampp\php\php.exe tests\test_monitor.php
```
Most tests use the real local database: they create temporary rows (with a random tag) and delete them afterwards, so run them only against a development database.

Manual test plan with results (including the two-tab and two-account tests for double redemption): [tests/MANUAL_TESTS.md](tests/MANUAL_TESTS.md).

## Documentation

- [ASSUMPTIONS.md](ASSUMPTIONS.md): every gap in the requirements and the option chosen
- [ARCHITECTURE.md](ARCHITECTURE.md): stack, data model, diagrams, security, limitations
- [AI_LOG.md](AI_LOG.md): key prompts, and cases where the AI was wrong and how I fixed them

## Known limitations

Payouts are paid manually by the owner outside the app; the QR print sheet needs internet access for qrcode.js;
rate limiting uses the visitor's IP address as reported by the server. The full list is in section 12 of
[ARCHITECTURE.md](ARCHITECTURE.md).