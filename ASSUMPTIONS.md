# Assumptions

Every gap in the requirements and the option I chose, with the reason.

# How rewards work
Each physical product box gets its own unique code (uid), shown as a QR code
stuck on the box. After buying, the customer scans the QR and earns coins.
**Why:** one code per box makes every purchase individually trackable.

# Coins and rupees
Coins convert to Indian rupees at a fixed rate set by the factory owner
(1 coin = Rs. X, stored in settings so the owner can change it).
**Why:** the owner controls the cost of the program without code changes.

# Proof of purchase
The QR code is placed inside the box (under the seal), so it can only be seen
after the product is bought and opened.
**Why:** a code printed on the outside could be photographed in a shop
without buying.

# Customers need an account
Customers must register and log in to redeem a code.
**Why:** coins must belong to a real account, and it lets us trace misuse.


# Coin value is fixed at code generation
Each code stores its own coin value, copied from the product when the batch is
generated. Changing a product's coin value later does not change boxes already printed.
**Why:** printed boxes can't be updated, so their reward must stay stable.

# Balance is calculated, not stored
A customer's coin balance is the sum of their ledger transactions.
**Why:** a stored balance can drift out of sync; a ledger is always auditable.

## Admin accounts
Public registration only creates customers. The factory owner's admin account
is created by changing the role directly in the database.
**Why:** an admin-signup form would let anyone become admin.

#  Products are deactivated, not deleted
**Why:** codes and batches reference products, and history must stay intact.

# Code format
Each code is 12 random characters (no look-alike characters) plus a 10-character
HMAC signature, printed in the QR as a link.
**Why:** random codes can't be guessed, and the signature lets the server reject
invented codes without a database lookup.

# Batch size limit
The owner can generate at most 500 codes per batch.
**Why:** the QR sheet is drawn in the browser; very large pages become slow.

# QR links are fixed once printed
The QR contains the full link built from APP_URL at the time the sheet is viewed.
Boxes already printed keep the domain they were printed with.
**Why:** physical boxes can't be updated, so the production domain must be
chosen before the real print run.

#  The wallet shows value at today's rate
The rupee value shown is balance x current rate, so it changes if the owner changes the rate .
History shows 10 entries per page, newest first.

# Payouts are paid manually
The app records and tracks cash-out requests, but does not send money. The owner pays
the customer outside the system (for example by UPI) and then marks the request approved.
**Why:** a payment gateway is outside the scope; the owner still gets full control and an audit trail.

## Coins are deducted at request time and refunded on rejection
**Why:** it makes it impossible to spend the same coins twice while a request is waiting.

# The rupee amount is locked when the request is made 
The wallet shows value at the current rate, but a cash-out uses the rate at the moment of the request.
**Why:** the customer sees an exact amount, and later rate changes cannot surprise anyone.

# Minimum cash-out and one pending request at a time
The minimum is an owner-editable setting (default 100 coins).
**Why:** it limits the owner's workload and abuse.

# UPI ID is the only payment detail collected
It is visible only to the customer and the admin.
**Why:** it is the minimum the owner needs to pay; no bank account numbers are stored.

# Fraud-flag rules and thresholds
Flags look at the last 24 hours: 5 or more failed scans by one account, 5 or more failed scans
from one IP address, 3 or more accounts on one IP address, 10 or more redemptions by one account.
**Why:** simple, explainable rules; the thresholds are constants in app/monitor.php and easy to tune.

# Flags are alerts for a human; nothing is blocked automatically
**Why:** a shared IP or a fast redeemer can be innocent (a family, a shop); the owner decides.

# Blocking a batch only affects unused codes
Unblocking restores only blocked codes that were never redeemed.
**Why:** a used code must never become usable again.

# The 14-day trend uses the database server's date
**Why:** it keeps the dates consistent with the timestamps stored in the database.