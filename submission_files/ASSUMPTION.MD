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

(More assumptions will be added as we build.)