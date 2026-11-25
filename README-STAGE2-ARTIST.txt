AIMEFILMS — STAGE 2: ARTIST DASHBOARD ADD-ON

IMPORTANT
This is an add-on. Merge these files into your existing project:
C:\xampp2\htdocs\aimefilms\
Do not replace the existing project folder.

INSTALL
1. Extract this ZIP into C:\xampp2\htdocs\aimefilms\ and allow files to merge.
2. Open phpMyAdmin: http://localhost/phpmyadmin/
3. Select the aimefilms database, open Import, and import:
   database/stage2_artist_schema.sql
4. Confirm the existing app/bootstrap.php exposes $base, current_user(),
   require_auth(), require_role(), verify_csrf_or_abort(), csrf_field(),
   flash(), and e(), and that Database::connection() returns PDO.
5. Ensure the existing roles table contains the 'artist' role.
6. Create this directory and make it writable by Apache:
   public/uploads/artists/
7. Visit:
   http://localhost/aimefilms/public/artist/index.php
   Artist application:
   http://localhost/aimefilms/public/artist/apply.php
   Admin review:
   http://localhost/aimefilms/admin/artists/

FEATURES INCLUDED
- Artist application form and pending/rejected/approved states.
- Admin approval/rejection workflow.
- Approval creates artist profile and assigns the artist role.
- Artist profile editing (name, bio, location, website, avatar, banner).
- Content submission for the three platforms.
- Movies, series, episodes, music videos, albums and singles.
- Poster, thumbnail, cover and media uploads.
- Uploads enter pending moderation; they are not automatically public.
- Artist content list, status, view counts and safe draft/rejected deletion.
- Dashboard counts: content, published, pending, views, followers and comments.
- Followers list and comment hiding.
- Earnings summary placeholder in RWF.

IMPORTANT COMPATIBILITY NOTES
- Existing backend helper names may differ. If Database::connection() is not the
  method in your current config/database.php, change that call to your PDO getter.
- This add-on assumes current_user() returns an array containing id and name.
- It assumes require_role('admin') is supported by app/Helpers/auth.php.
- Add <link rel="stylesheet" href="<?= e($base) ?>/assets/css/artist-dashboard.css">
  to your shared header if you want the included dashboard styles.
- PHP upload limits must be configured in php.ini. For video files, review:
  upload_max_filesize, post_max_size, max_execution_time and max_input_time.
  The app currently caps video uploads at 500 MB. Hosting should use object storage
  or a dedicated media pipeline before production scale.
- For production, store uploads outside the public web root, scan files, transcode
  media, generate thumbnails, and serve through a CDN. MIME checks are not a malware
  scanner and browser MIME checks alone are not sufficient.
- Monetization is only a reporting table and display placeholder. Do not pay out
  from these records until payment reconciliation, fraud checks, tax/legal rules,
  creator contracts and audited payout workflows exist.
- Add artist notification emails and admin audit logs before public launch.
- The current schema does not implement public follow/unfollow or public comment
  creation endpoints; it provides the tables and artist-side moderation view.
