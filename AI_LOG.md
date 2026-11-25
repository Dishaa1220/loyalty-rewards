# 1. Database Connection Issue

**Problem:**
While running the project, a database connection error appeared in the server terminal. The application was unable to connect to the database properly.


- I show the server error message while running the project.
- I understand the error and identify its possible cause.
- After checking the related code, the file where the mistake was present.
- The issue was related to the database connection configuration.


**Mistake Found:**
The database connection settings or connection code were not configured correctly.

**Solution:**
- Corrected the database connection code.
- Updated the required configuration.
- Checked the connection again by running the server.

**Result:**
The database connection issue was fixed, and the application started working properly.

**AI Tool Used:**  Claude

# 2: Snippet-style instruction broke the admin dashboard
- **What went wrong:** AI told me to "add this link inside the list-group" of
  `public/admin/index.php`. when i pasted it. The result was a broken "Coin settings" link: the
  dashboard showed a stray `<` at the top and `">Coin settings` text outside the list.
  Clicking the link opened
  `.../admin/settings.php%20%20<a%20class=` and Apache returned **403 Forbidden**.
- **How I noticed:** The 403 page, and the odd address in the browser's address bar, which
  showed a piece of HTML inside the URL.
- **How I fixed it:** I noticed that URL and understand and checked that where was exactly `<?  php`, and reloaded. All three dashboard links then worked.

# 3: The AI's first diagnostics missed a stray character in the page header
- **What went wrong:** Every page showed a stray `<` in the top-left corner, above the navbar. The AI's first two suggestions (a regex search for a line holding only `<`, and one for `<` just before `<?php`) found nothing, because the real cause was different.
- **How I noticed:** I saw it on screen on several pages (admin dashboard, cash-out page, wallet).
- **How I fixed it:** Since every page uses `app/header.php`, and  searching that file for `<<`. I searched with Ctrl+F, found `<<nav` in `app/header.php`, deleted the extra `<`, reloaded, and the character was gone on every page. I committed the fix. 
- **Why it mattered:** The AI explained that stray output before a redirect can cause "headers already sent" errors on servers that don't buffer output. XAMPP hides this, so it would only have shown up after deployment.
- **Lesson:** When something shows on every page, look in the shared file first.


## Key prompts and interactions

### 1. Starting the project and setting the direction
- **Prompt:** I shared the assignment PDF and my own idea: give every product box a unique id, turn it into a QR code stuck on the box, and when the customer scans it they get coins that convert to rupees. I said I wanted to learn the project properly, not only submit it.
- **AI response:** It kept my idea and added what it was missing: the QR placed inside the box as proof of purchase, signed random codes, one-time redemption enforced in the database, a coin ledger instead of a stored balance, an owner dashboard, and a step-by-step build order.
- **What I did:** I used my QR-and-coins idea as the base and followed the build order one step at a time. I recorded my choices in ASSUMPTIONS.md.

### 2. Repository and documentation skeleton
- **Prompt:** "ok start from 1st step"
- **AI response:** Folder structure, `.gitignore` (so `.env` is never committed), `.env.example` with placeholders, skeletons for the required documents, and the Git commands to push.
- **What I did:** I created the public GitHub repository and pushed. I checked in a private browser window that `.env` was not in the repo. 

### 3. Database design (Step 2)
- **Prompt:** I asked the AI to continue to the database schema.
- **AI response:** Seven tables, with the important rules enforced by the database itself: a unique code id, a unique ledger row per code, and a status column that makes a one-time `UPDATE ... WHERE status='unused'` possible.
- **What I did:** I imported the schema and ran the double-redeem test in phpMyAdmin myself. And i  saw: the first UPDATE changed 1 row and the second changed 0 rows, and a duplicate code id was refused.

### 4. Secure login (Step 3)
- **Prompt:** I asked for registration and login.
- **AI response:** Password hashing, prepared statements, CSRF tokens, output escaping and session regeneration, each explained.
- **What I did:** I tested duplicate email, wrong password, short password, and opening a protected page while logged out. 

### 5. The redemption logic (Step 6)
- **Prompt:** I asked for the redeem page.
- **AI response:** A confirm page that changes nothing (GET) and a claim button (POST) that runs one atomic `UPDATE` inside a transaction, adds the ledger row, and logs the attempt. It also added rate limiting.
- **What I did:** I ran `test_redeem.php` and tested with two browser tabs and two accounts to see that a code pays out only once. 


### 6. Cash-out design (Step 8)
- **Prompt:** The AI asked how customers should turn coins into money; I replied "go" to its proposal.
- **AI response:** The customer requests a cash-out, the coins are deducted at once, and the owner approves it (paid outside the app) or rejects it (coins refunded). It added a row lock against overdrawing and database keys against double refunds.
- **What I did:** I accepted this design instead of an instant payout, ran `test_payouts.php` (0 failed), and tested requests and approvals in the browser.

### 7. Owner monitoring and fraud flags (Step 9)
- **Prompt:** I asked to continue to the owner's monitoring page.
- **AI response:** A dashboard with headline numbers, a 14-day trend, per-product and per-batch results, a scan log, simple rule-based fraud flags with visible thresholds, and block/unblock for a batch. It said flags are alerts for a human and nothing is banned automatically.
- **What I did:** I accepted rule-based flags (easy to explain) and tested them by entering wrong codes as a customer and by blocking and unblocking a batch.