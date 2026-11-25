AIMEFILMS — STAGE 7: FREE TO WATCH, LOGIN TO DOWNLOAD
======================================================
Add-on. Install Stages 3-6 first. Extract into C:\xampp2\htdocs\aimefilms\ and
let Windows MERGE folders and REPLACE files.

THE RULE
  Watching / listening: FREE, no account needed.
  Downloading:          needs a (free) login.
  Guests who click "Log in to download" go to the login page and are brought back
  to the same title afterwards (also works through "Create an account").

INSTALL
1. Extract the ZIP into C:\xampp2\htdocs\aimefilms\ (merge + replace).
2. phpMyAdmin -> aimefilms -> Import -> database/stage7_downloads_schema.sql
   (adds 2 tables: content_downloads, content_download_blocks. Nothing existing changes.)
3. IMPORTANT CHECK (Apache .htaccess): open a media file's direct address, e.g.
      http://localhost/aimefilms/public/uploads/artists/2/<some-file>.mp3
   It must show "403 Forbidden". If it plays or downloads, Apache is ignoring .htaccess:
   in C:\xampp2\apache\conf\httpd.conf set  AllowOverride All  for your htdocs folder,
   restart Apache, and test again. Without this, guests could still fetch files directly.

HOW IT WORKS
  stream.php?id=N     Playback for the video/audio players. Free for everyone for PUBLISHED titles
                      (artist account active). The owner and admins can also play titles still in
                      review. Supports seeking (HTTP Range). The real file location is never in the page.
  download.php?id=N   Login required. Sends the file as an attachment named "Title - Artist.ext"
                      (filename cleaned so it can't contain paths or header tricks). Resumable.
                      A resumed download isn't counted twice.
  public/uploads/.htaccess   Blocks direct requests for .mp4 .webm .mp3 .m4a .wav (images stay public).

LIMITS & CONTROLS
  - 20 downloads per account per 24 hours (DownloadService::DAILY_LIMIT, change if you like).
  - Artists: Manage content now shows a "Downloads" count per title and a button
    "Downloads: on / off". Switching it off hides the Download button and blocks the endpoint;
    people can still watch for free. This does NOT send the title back to review.
  - The browser's own download button is hidden on the players (controlsList="nodownload").
  - Titles that are pending / rejected / archived / from suspended artists can't be downloaded.
  - Login/Register pages accept ?next=... but only for addresses inside this site
    (no open redirects).

CHANGED FILES
  public/watch.php (Download button, stream player), admin/content/review.php (admin preview
  uses stream.php), public/artist/content.php (counts + switch), public/auth/login.php and
  register.php (return to where you were), public/assets/css/catalog.css.
  NEW: stream.php, download.php, app/Helpers/media.php, redirect.php, app/Services/DownloadService.php.

TESTED
  Real HTTP tests against a running copy of the merged site (curl): full/partial/suffix/open-ended/
  invalid Range, HEAD, byte-for-byte identical files, guest vs member vs owner vs admin vs other user,
  pending / suspended-artist / album / path-traversal / missing ids, guest redirect to login with next,
  filename headers, download logging, resume not double-counted, artist switch (and that another
  artist or a request without a CSRF token can't use it), the 20/day limit and 24h expiry, login
  returning to the title, and 6 malicious "next" values rejected. php -l on every file.
  NOT tested: Apache itself (.htaccess is ignored by the test server — see step 3), a real browser,
  or MySQL/XAMPP.

HONEST LIMITS
  - "Free to watch" means a technical visitor can still save the stream (their browser must
    receive the bytes to play them). The login gate stops normal downloading and direct file links,
    not a determined copier. Real protection needs encrypted streaming (DRM) — a big separate job.
  - Files still live under public/uploads (protected by .htaccess). Moving them outside the web
    folder entirely is the stronger setup for a public launch.
  - Large files are sent straight from PHP: fine for a small site; use a CDN or X-Sendfile later.
