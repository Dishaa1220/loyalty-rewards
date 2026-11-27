## Test environment

| Item | Value |
|---|---|
| Operating system | Windows 11 |
| Server | Apache 2.4.58, PHP 8.2.12 (XAMPP) |
| Database | MySQL / MariaDB from XAMPP |
| Browser | Chrome [Version 157.0.8103.79 (Official Build) (64-bit)] |
| Coin rate and minimum cash-out | 1.00 and 30 (set in Admin, Coin settings) |

## Automated tests

Run from the project folder: `C:\xampp\php\php.exe tests\<file>`. Each prints PASS or FAIL per check.

| Script | What it covers | Result (for example "11 passed, 0 failed") |
|---|---|---|---|
| test_codes.php | Code generation, signature accepted and rejected, tampering, garbage input | 11 passed, 0 failed |
| test_redeem.php | One-time redemption, blocked and unknown codes, database safety net, rate limit | 15 passed, 0 failed | 
| test_wallet.php | History order, pagination, summary, isolation between users | 11 passed, 0 failed  | 
| test_payouts.php | Cash-out request, approve, reject, refunds, no double refund | 30 passed, 0 failed  | 
| test_monitor.php | Batch block and unblock, fraud flags, scan log filter, trend, headline numbers |  19 passed, 0 failed   | 

## A. Accounts and access

| ID | What I did | Expected result | Result | notes |

| A1 | Registered with a valid name, email and 8+ character password | Redirected to login with an "Account created" message | Pass | registered test account |
| A2 | Looked at `users.password_hash` in phpMyAdmin | Long string starting with `$2y$`, not the password | Pass  |  hash starts with $2y$10$ |
| A3 | Logged in with a wrong password | "Invalid email or password." | Pass  |  same message as for an unknown email |
| A4 | Logged in with the correct password | Wallet page opens | Pass | "Hello, Ravi Demo" shown |
| A5 | Registered again with the same email | "That email is already registered." | Pass  | "That email is already registered." shown in red color  |
| A6 | Registered with a 5-character password | Error about the minimum length | Pass  |"Password must be at least 8 characters." message shown |
| A7 | Opened `public/index.php` while logged out | Redirected to the login page | Pass | Redirect to login page |
| A8 | Logged in as a customer and opened `public/admin/index.php` | "Access denied." | Pass | "Access denied. Admins only." message shown |

## B. Admin: products and settings

| ID | What I did | Expected result | Result |  notes |
|---|---|---|---|---|
| B1 | Added a product with 10 coins per box | Appears in the list with its rupee worth | Pass | 
| B2 | Refreshed the page right after adding | The product is not added a second time | Pass |
| B3 | Changed the coins to 15 and pressed Save | New value is saved and shown | Pass  |
| B4 | Pressed Deactivate, then Activate | The status badge changes each time | Pass  |
| B5 | Tried 0 coins, an empty name, and a very long name | Each is refused with an error message | Pass |
| B6 | Set the coin rate to 0.50, then back to 1.00 | The rupee worth in the list changes with the rate | Pass  | 
| B7 | Set the minimum cash-out to 30 | "Settings saved." and the cash-out page shows 30 | Pass | 

## C. Codes and QR sheets

| ID | What I did | Expected result | Result | notes |
|---|---|---|---|---|
| C1 | Generated a batch of 5 codes | QR sheet with 5 QR codes; the `codes` table has 5 rows, status `unused`, 12-character uid | Pass ( | show  5 rows, all unused |
| C2 | Changed the product's coins, then generated another batch | Old codes keep their old `coins_value`; the new batch uses the new value | Pass  | show both old codes & new codes |
| C3 | Tried quantity 0 and 501; deactivated the product | Both quantities refused; the inactive product is not in the dropdown | Pass |
| C4 | Pressed Ctrl+P on a QR sheet | Navbar hidden, QR cards not split across pages | Pass ( ) | |
| C5 | Opened `admin/batches.php` as a customer | "Access denied." | Pass  | show "Access denied . Admins only " |

## D. Redemption and reuse prevention

| ID | What I did | Expected result | Result | notes |
|---|---|---|---|---|
| D1 | Logged out, opened the link for code T3, logged in as Ravi, pressed Claim | Login page with a "please log in" message, then the confirm page; after Claim: "You earned 10 coins" and the balance rises by 10 | Pass | add coins into balance 10 to 20 |
| D2 | Opened the T3 link again as Ravi | "You already claimed this code on " | Pass ( ) | shows message like you already claim this coin |
| D3 | Priya claimed S1; then Ravi opened the S1 link | Ravi sees "This code has already been used." (it does not say who used it) | Pass | shows message  " coin is already claimed" |
| D4 | Two tabs, same account: opened the T4 link in both, pressed Claim in tab 1, then in tab 2 | Tab 2 says already claimed; the balance rises only once | Pass | shows message in Tab 2 that coin is already claimed. |
| D5 | Two accounts: Ravi and Priya both opened the T5 link; Ravi claimed, then Priya | Priya sees "already been used"; her balance does not change | Pass | shows message to Priya that coin is already claimed.|
| D6 | Changed one character in the signature of the B1 link | "This code is not valid."; the scan log shows `invalid` | Pass | |
| D7 | Opened the B3 link (blocked code) | "This code cannot be redeemed. Please contact the company." | Pass| shows a message |
| D8 | Typed the B2 token into the box on `redeem.php`, then Claim | Confirm page, then the coins are added | Pass | Coins are added to Coin balance.|
| D9 | Entered wrong codes repeatedly, then opened the valid S2 link | After several tries: "Too many failed attempts"; even the valid link is refused. After clearing `scan_attempts` in the test database, S2 can be claimed | Pass | e.g. locked after 5 wrong codes; worked again after clearing the log |
| D10 | Checked the database after the tests | Every claimed code is `used` with `redeemed_by`; one `earn` ledger row per claimed code; `success` rows in `scan_attempts` | Pass | Database shows the records. |

## E. Wallet

| ID | What I did | Expected result | Result | notes |
|---|---|---|---|---|
| E1 | Logged in as Ravi after the redemption tests | Balance equals the sum of his ledger rows; history newest first; product names shown; claims in green with + | Pass ( ) |  130 coins earned, cash-outs -50 and -30, balance 50, 7 boxes claimed  |
| E2 | Logged in as Priya | Only Priya's rows appear, none of Ravi's | Pass | Priya's history and details are shown |
| E3 | Logged in as the admin (no coins) | Balance 0 and the "No coins yet" message, no errors | Pass | show message "No coins yet. Scan the QR code inside your next box to earn your first coins".|
| E4 | Set the rate to 0.50 and looked at Ravi's wallet; set it back to 1.00 | Worth halves; the coin balance does not change | Pass | 50 coins showed 25.00 rupees at rate 0.50 |
| E5 | Opened `index.php?user=2` and `index.php?userId=2` as Ravi | Still Ravi's own wallet | Pass |opens the ravi's dashboard |
| E6 | Opened `index.php` while logged out | Redirected to login | Pass |Ask for login first  |
| E8 | Claimed a fresh code as Ravi | New row at the top; balance and "Boxes claimed" rise by one claim | Pass  | Coins added to coin balance and new row is shown in history. |

## F. Cash-out

| ID | What I did | Expected result | Result | notes |
|---|---|---|---|---|
| F1 | Admin set the rate to 1.00 and the minimum to 30 | "Settings saved."; dashboard shows "Pending cash-outs" | Pass  | show mesasage "Setting saved." |
| F2 | Ravi opened Cash out | Shows his balance, the rupee value and "Minimum cash-out: 30 coins" | Pass  | Ravvi shows the message "Your balance: 50 coins (₹25.00)
1 coin = ₹0.50. Minimum cash-out: 30 coins. The coins are taken from your balance now, and the owner pays you by UPI after reviewing. If a request is rejected, the coins are returned. "|
| F3 | Ravi requested coins with a UPI ID | Success message; the list shows the request, the coins, the rupee amount, Pending | Pass  |  requested 50 coins |
| F4 | Looked at Ravi's wallet | Balance dropped by the same number of coins; a red negative row "Cash-out request " | Pass  |  -50 row shown |
| F5 | Ravi tried a second request while one was pending | Error: a request is already pending; balance unchanged | Pass  | show error mesage. |
| F6 | Admin changed the rate to 0.50 and opened Cash-out requests | The pending request still shows the original rupee amount (rate is locked) | Pass  | The amount is not changed of cash out. |
| F7 | Admin approved it with a note; then set the rate back to 1.00 | Moves to Reviewed as Approved; Ravi's balance unchanged; Ravi sees Approved and the note | Pass | Ravi sees approved and note if is written by admin. |
| F8 | Ravi tried: coins below the minimum, more than his balance, UPI without @, coins "abc" | Each shows its own error; the balance never changes | Pass | showss error message . |
| F9 | Ravi made a new request (allowed after the first was handled) | New request, Pending | Pass |  request for 30 coins |
| F10 | Admin rejected it with a note | Ravi's wallet gets a green + refund row "Refund: cash-out request was rejected"; balance restored | Pass | showss reject status and amount is return added to coin balanace. |
| F11 | Two tabs: Ravi made another request; admin opened Cash-out requests in two tabs, approved in tab 1, then rejected in tab 2 | Tab 2 says "already processed. Nothing was changed."; no refund row was added | Pass  | Ravi show approved and tab 2 shows already process
 |
| F12 | As a customer opened `admin/payouts.php`; logged out and opened `payout.php` | "Access denied." and a redirect to login | Pass | first ask for for log in. |


Database integrity checks after the cash-out tests:

```sql
-- Nobody should be below zero. Expected: no rows.
SELECT u.name, SUM(t.coins) AS balance
FROM transactions t JOIN users u ON u.id = t.user_id
GROUP BY u.id HAVING SUM(t.coins) < 0;

-- Approved requests: 1 deduction row and 0 refund rows. Rejected requests: 1 and 1.
SELECT r.id, r.status, SUM(t.type = 'redeem') AS redeem_rows, SUM(t.type = 'adjust') AS refund_rows
FROM payout_requests r LEFT JOIN transactions t ON t.payout_request_id = r.id
GROUP BY r.id;
```

| ID | Check | Expected result | Result |
|---|---|---|---|---|
| F13 | Ran both queries above | First returns no rows; second matches the expected counts | First quesry sreturnes 0 rows , second quesry shows the number id, status , rededm_rows , refund_rows| |

## G. Monitoring and fraud flags

| ID | What I did | Expected result | Result |
|---|---|---|---|---|
| G1 | Opened the admin dashboard | Five cards including "Fraud flags (24 h)"; Monitoring is the first link | Pass | 
| G2 | Opened Monitoring | Eight number cards, flags, 14-day trend, product table, batch table, scan log | Pass |
| G3 | Compared the cards with `SELECT status, COUNT(*) FROM codes GROUP BY status;` and `SELECT SUM(coins) FROM transactions WHERE type='earn';` | "Codes generated" and "Coins issued" match the queries | Pass |
| G4 | Looked at the 14-day trend | Bars on days with redemptions, empty bars on other days; today is the last row | Pass|
| G5 | As Priya, entered a wrong code 5 times; reloaded Monitoring as admin | A High flag "Many failed scans (account)" for Priya, and a failed-IP flag | Pass |
| G6 | Looked at the flags after using three accounts on one computer | A Medium flag "Several accounts on one IP address" (expected on a single test machine) | Pass |
| G7 | Used the scan log filters (invalid, success) | Only rows with the chosen result are listed | Pass |
| G8 | Generated 3 new codes; pressed Block unused on that batch | "3 unused code(s) blocked."; the row shows Unused 0, Blocked 3 | Pass |
| G9 | As Ravi, tried to claim a blocked code | "This code cannot be redeemed."; the scan log shows `blocked` | Pass |
| G10 | Pressed Unblock; Ravi claimed the code | "3 code(s) unblocked."; the claim succeeds; the row shows Used 1, Unused 2 | Pass |
| G11 | Looked at a batch whose codes are all used | The Block unused button is disabled | Pass |
| G12 | As Ravi, opened `admin/monitor.php` | "Access denied." | Pass |

## Bugs found while testing

| What I saw | Cause | Fix |
|---|---|---|
| Clicking "Coin settings" on the admin dashboard gave "403 Forbidden"; a stray `<` and `">Coin settings` text showed on the page | A new link had been pasted inside another link in `public/admin/index.php`, so the address became `settings.php%20%20<a%20class=` | Replaced the whole file with a clean version and checked line 1 |
| After login, a fatal error: failed opening `public/../../app/auth.php` | `public/index.php` held the admin folder's path (`../../`); a file directly in `public/` needs `../` | Replaced the file with the correct version |
| A stray `<` in the top-left corner of every page | An extra `<` in front of `<nav` in `app/header.php` (`<<nav`), found by searching for `<<` | Deleted the extra `<` |

## Known gaps in testing

- There are no automated browser tests; pages were tested by hand.
- The PHP tests run requests one after another. True simultaneous requests were tested only by hand with two browser tabs and two accounts (D4, D5, F11); the database design (atomic UP  , row locks, unique keys) is what protects against real simultaneity.
- No load testing, and no test of a real payment, because payouts are paid manually outside the app.