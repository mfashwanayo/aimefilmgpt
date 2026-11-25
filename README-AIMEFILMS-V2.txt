AIMEFILMS — MULTI-PLATFORM FRONTEND PACKAGE
==========================================

This package adds separate pages for:
- AIMEFILMS main homepage: public/index.php
- FilmsNyarwanda: public/filmsnyarwanda/index.php
- AIMEFILMS originals: public/aimefilms/index.php
- MusicSpart: public/musicspart/index.php
- Shared authentication: public/auth/register.php, login.php, forgot-password.php

INSTALLATION
1. Extract this ZIP.
2. Copy the contents of the extracted aimefilms_frontend_v2 folder into:
   C:\xampp2\htdocs\aimefilms\
3. Merge folders when Windows asks. Replace the previous frontend files if prompted.
4. Start Apache in XAMPP.
5. Open:
   http://localhost/aimefilms/public/

DIRECT PAGE URLS
http://localhost/aimefilms/public/filmsnyarwanda/
http://localhost/aimefilms/public/aimefilms/
http://localhost/aimefilms/public/musicspart/
http://localhost/aimefilms/public/auth/register.php
http://localhost/aimefilms/public/auth/login.php
http://localhost/aimefilms/public/auth/forgot-password.php

IMPORTANT
- These are frontend pages only. No account is created, no password is checked,
  and no password reset email is sent yet.
- The forms use browser-side validation and display a clear demo-only message.
- Next step: implement secure PHP PDO/MySQL registration, login, sessions,
  password_hash/password_verify, CSRF protection, rate limiting and password reset tokens.
- Google Fonts are loaded remotely. The pages remain usable without them.
- Update links and route handling later if your project uses a custom router.
