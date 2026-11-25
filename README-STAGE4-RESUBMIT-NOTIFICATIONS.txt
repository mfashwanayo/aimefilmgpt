AIMEFILMS — STAGE 4: EDIT & RESUBMIT + NOTIFICATIONS
=====================================================
Add-on. REQUIRES Stage 3 to be installed first (uses moderation_log and
app/Helpers/admin.php). Extract into C:\xampp2\htdocs\aimefilms\ and let
Windows MERGE folders and REPLACE files.

INSTALL
1. Extract the ZIP into C:\xampp2\htdocs\aimefilms\ (merge + replace).
2. phpMyAdmin -> aimefilms -> Import -> database/stage4_notifications_schema.sql
   (adds ONE table, notifications. No existing table is changed.)
3. Log in as an artist -> http://localhost/aimefilms/public/artist/content.php

WHAT YOU GET
1. Edit & resubmit  (public/artist/content-edit.php)
   - Manage content now has Edit / "Fix & resubmit" buttons.
   - On a rejected item the admin's note is shown in a red box at the top.
   - Artists can change title, description, date, language, genre, duration and
     replace the media/poster/thumbnail/cover (leave a file empty to keep it).
   - Platform and content type can't be changed.
   - A resubmitted item goes to "pending"; the old rejection note is cleared
     (it stays in the history on the admin review page).
   - Replaced files are deleted from disk; files from a failed save are cleaned up.
   - A movie/episode/video/song can't be resubmitted without a media file.
2. Notifications  (public/notifications.php + bell in the header)
   - Artist is notified when content is approved, rejected (with the reason),
     sent back to review, archived or republished.
   - Every active admin is notified when new content is submitted, when rejected
     content is resubmitted, and when published content is edited.
   - Bell shows the unread count; clicking a notification marks it read and opens
     the relevant page; "Mark all as read" button. Only same-site links are followed.
   - Notifications are best-effort: if one fails to save, the upload/approval still works.
3. Edit while published
   - Saving any edit on a published item sets it back to "pending" (it is no
     longer public until an admin approves it again) and asks the artist to
     confirm first. Archived items can't be edited by artists.

CHANGED FILES
  app/Services/ContentService.php   (rewritten: update(), findOwn(), validation, notifications)
  app/Services/ModerationService.php (now notifies the artist after each decision)
  app/Services/NotificationService.php (new)
  app/Helpers/artist_uploads.php    (+ artist_upload_delete)
  public/partials/header.php        (bell + unread count)
  public/artist/content.php, content-edit.php (new), public/notifications.php (new)
  public/assets/css/admin.css

TESTED
  php -l passes on every file. 59 automated checks (29 new for Stage 4 + the 30
  from Stage 3 re-run) against an in-memory SQLite database: resubmit flow,
  edit-while-published, archived blocked, another artist can't edit your content,
  missing-media rule, notification delivery and recipients, link safety, and that
  a missing notifications table never breaks approvals. The header bell was rendered
  with a real unread count. NOT tested on real MySQL/XAMPP or in a browser here.

KNOWN LIMITS
  - A published item goes fully offline while it is re-reviewed (no "live version
    stays up" mode). That is simple and safe; tell me if you want the live version
    kept until approval.
  - Notifications are in-app only (no email yet).
  - Bell count is read on page load (no live refresh).
