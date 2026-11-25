AIMEFILMS — STAGE 3: ADMIN CONTENT MODERATION
==============================================
This is an add-on. Extract it into C:\xampp2\htdocs\aimefilms\ and let Windows
MERGE folders and REPLACE files. Nothing else needs to be deleted.

INSTALL
1. Extract the ZIP into C:\xampp2\htdocs\aimefilms\ (merge + replace).
2. phpMyAdmin -> aimefilms database -> Import -> database/stage3_moderation_schema.sql
   (adds ONE new table, moderation_log. No existing table is changed.)
3. Log in as an admin and open:  http://localhost/aimefilms/admin/index.php

NEW PAGES
  admin/index.php                Dashboard: queues + platform counts
  admin/content/index.php        Moderation queue (tabs, search, platform/type filters, paging)
  admin/content/review.php       Preview media, details, approve / reject / archive / restore, history
  admin/comments/index.php       Reported / visible / hidden comments: hide, restore, dismiss report

STATUS RULES (existing values: draft, pending, published, rejected, archived)
  Approve    pending            -> published
  Reject     pending/published  -> rejected   (note to artist REQUIRED)
  Send back  published          -> pending
  Archive    published          -> archived
  Restore    archived           -> published
  Drafts are private to the artist and never appear in the admin queue.
  Approval is blocked if the artist account is not active, or a movie/episode/
  music video/single has no media file (series and albums need none).
  Every decision is saved in moderation_log with admin, old/new status, note, time.

FIXES TO STAGE 2 INCLUDED (these were why parts of the artist/admin pages failed)
  config/database.php          Added Database::connection() (Stage 2 pages call it; db() still works)
  app/Helpers/flash.php        Added flash() alias of set_flash() (Stage 2 pages call it)
  public/partials/header.php   Absolute asset links when $base isn't set (artist/admin pages in
                               sub-folders had broken CSS/links); shows flash messages; loads
                               artist-dashboard.css + admin.css; Admin / Studio link by role
  config/app.php               Added APP_ADMIN_URL
  app/Helpers/artist_uploads.php  Fixed: .webp images were rejected; MP3/M4A/WAV can now be
                               uploaded as the main media file (before, only video was accepted,
                               so MusicSpart songs could not be uploaded)
  public/artist/content-create.php  uses the new 'media' upload type
  public/artist/apply.php      Fixed redirect after applying
  public/artist/content.php    Artists now see the admin's note on rejected content

TESTED
  All PHP files pass php -l. The moderation service was tested with 30 checks (transitions,
  rejection note rule, suspended artist, missing media, double-approve, note clearing, comments,
  search, paging) against an in-memory SQLite database. NOT tested against real MySQL, XAMPP or a
  browser in this environment — please try the pages and send me any error text or screenshot.

NOT BUILT YET (honest limits)
  - Artists can't edit and resubmit rejected content (they can only delete it). Next step.
  - Artists aren't notified by email/in-app when content is approved or rejected.
  - Public pages don't list published content yet (FilmsNyarwanda/AIMEFILMS/MusicSpart still static).
  - Uploads still sit under public/uploads (see Stage 2 notes about private storage for production).
