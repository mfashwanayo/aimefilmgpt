AIMEFILMS — STAGE 6: FOLLOW, COMMENTS, REPORTS, LIKES, LISTS, PASSWORD
=======================================================================
Add-on. Install Stages 3, 4 and 5 first. Extract into C:\xampp2\htdocs\aimefilms\
and let Windows MERGE folders and REPLACE files.

INSTALL
1. Extract the ZIP into C:\xampp2\htdocs\aimefilms\ (merge + replace).
2. phpMyAdmin -> aimefilms -> Import -> database/stage6_engagement_schema.sql
   (adds 3 tables: content_likes, user_lists, comment_reports. Nothing existing is changed.)
3. Log in as any user and open a title:  /public/watch.php?id=N

WHAT'S NEW
Follow / unfollow      Button on every artist page (+ Follow / ✓ Following). You can't follow
                       yourself or an unapproved/suspended artist. The artist gets a notification.
Likes                  "♥ Like (n)" on every title. One like per person; the counter is recounted
                       from the likes table so it can't drift.
Watchlist & Favourites "+ Watchlist" and "☆ Favourite" buttons. Titles that are later unpublished
                       vanish from your library automatically (you can still remove them).
My library             /public/library.php  Tabs: Watchlist, Favourites, Liked, Following. Link in the main menu.
Comments               Logged-in users can comment under any published title (2-1000 characters,
                       max 5 per minute, exact duplicates blocked). The artist is notified.
                       Authors can delete their own comments. Newest first, paged.
Report a comment       "Report" under other people's comments: choose a reason (spam, abusive, hate,
                       spoiler, other). One report per person per comment. The comment is hidden from
                       the public at once and shows on admin/comments (status "Reported") with the number
                       of reports and reasons. Admins get ONE notification per reported comment.
                       Admin "Dismiss report" restores it and clears its reports; "Hide" keeps it hidden.
Change password        /public/account/password.php  (link on My library). Needs the current password,
                       8-200 characters, must differ from the old one, confirmation must match. 5 wrong
                       current-password guesses lock the form for 15 minutes. Logged in auth_logs.

SECURITY NOTES
  - Every action is a POST with a CSRF token; one endpoint (public/interact.php) handles them all.
  - After an action you are always redirected to a page built on the server from validated ids,
    never to an address in the request (no open redirect).
  - Comment text is stored as typed and HTML-escaped on display (script tags show as text).
  - Nobody can like/save/comment on content that is not published or whose artist is suspended.

CHANGED FILES (besides new ones)
  app/Services/CatalogService.php   (CARD/FROM constants made public for reuse)
  app/Services/AuthService.php      (+ changePassword)
  app/Services/ModerationService.php (comments list includes report reasons; dismissing clears reports)
  admin/comments/index.php          (shows "N report(s): reasons")
  public/watch.php, public/artists.php, public/partials/header.php, public/assets/css/catalog.css

TESTED
  php -l on every file. 57 service checks (likes, lists, follows, comments, rate limit, reports,
  moderation, password) on in-memory SQLite, plus 70 page/endpoint checks on a seeded site copy:
  guest vs member views, CSRF, GET blocked, guest POST sent to login, pending-title blocked,
  XSS escaping, report -> admin page, author vs non-author delete, follow button states, open-redirect
  attempt ignored, password change end to end. NOT tested on real MySQL/XAMPP or in a real browser.

KNOWN LIMITS
  - Comments appear instantly (no pre-approval); the report button + admin queue is the safety net.
  - Changing a password does not sign out other browsers (there is no session table yet).
  - There is no "forgot password" flow yet (the page exists but is not wired to email).
  - Likes/comments aren't counted per-artist in the studio dashboard yet.
  - All of this is free: subscriptions and payments are the next stage.
