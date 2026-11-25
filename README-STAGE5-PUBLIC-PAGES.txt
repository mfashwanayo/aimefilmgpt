AIMEFILMS — STAGE 5: PUBLIC PAGES + ADMIN ACCOUNT
==================================================
Add-on. Install Stages 3 and 4 first. Extract into C:\xampp2\htdocs\aimefilms\
and let Windows MERGE folders and REPLACE files.

INSTALL
1. Extract the ZIP into C:\xampp2\htdocs\aimefilms\ (merge + replace).
2. phpMyAdmin -> aimefilms -> Import -> database/stage5_create_admin.sql
   This creates (or promotes) the administrator account:
       Email:    hybert1@gmail.com
       Password: the one you gave me (stored only as a bcrypt hash, not in plain text)
   Safe to run twice. The last line of the file shows the account with role = admin.
3. Log in at http://localhost/aimefilms/public/auth/login.php
   then open http://localhost/aimefilms/admin/index.php
4. Make sure the folder public/uploads/artists/ exists and Apache can write to it.

SECURITY: your password was typed into a chat, so treat it as exposed. After your
first login, change it. (There is no change-password page yet; tell me and I'll
build one in the next stage.) Use a longer, unique password before going live.

WHAT VISITORS NOW SEE
  /public/filmsnyarwanda/  /public/aimefilms/  /public/musicspart/
      "Most watched" row, "Latest releases" grid, type tabs (Movies / Series /
      Music videos, or Singles / Albums / Music videos), search by title, genre
      or artist, paging. Empty states when nothing is published yet.
  /public/watch.php?id=N
      Video player (MP4/WebM) or audio player with cover art (MP3/M4A/WAV),
      details, artist link, views, "More from this artist".
      Series show their episodes, albums show their tracks; episode/track pages
      have Previous / Next and an "Autoplay next" switch (remembered in the browser).
  /public/artists.php?id=N
      Public artist page: banner, avatar, bio, location, follower count, releases.

RULES
  - Only content that is "published" AND whose artist account is "active" is public.
  - Pending / rejected / archived / draft content returns "not available".
    Its owner and admins can open it as a "Preview only" page.
  - Episodes and album tracks are reached through their series/album, not listed alone.
  - A view is counted once per visitor session and never for previews.
  - All text from artists is HTML-escaped; artist website links must be http(s).

ALSO FIXED
  app/Services/AuthService.php   Suspended/banned accounts could still log in
                                 (the lookup ignored status). Now only "active" accounts can.
  public/uploads/.htaccess       Blocks script execution and folder listing in uploads.

TESTED
  php -l on every file. 44 page-render checks on a seeded SQLite copy of the site:
  all three platform pages, filters/search/injection strings, album/track/movie pages,
  hidden pending + banned-artist content, owner/admin preview, artist page escaping,
  logged-in vs guest buttons, view counting. The admin SQL's password hash was verified
  with password_verify. NOT tested on real MySQL/XAMPP or in a real browser here
  (so video/audio playback and the visual design are unverified).

KNOWN LIMITS
  - Media files are served straight from public/uploads. Their names are random, but a
    leaked URL works even for unpublished items. Private storage + a streaming script
    is the proper fix before a public launch.
  - No transcoding/thumbnails: large MP4s depend on the visitor's connection.
  - No follow button, comments or likes yet (tables exist; Stage 6).
  - Everything is free to watch (no subscriptions/payments yet).
