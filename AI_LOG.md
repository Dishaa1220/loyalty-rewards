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
