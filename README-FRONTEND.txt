AIMEFILMS FRONTEND STARTER
==========================

INSTALL
1. Extract the aimefilms_frontend.zip file.
2. Copy the contents of the "aimefilms" folder into:
   C:\xampp2\htdocs\aimefilms
3. Allow the files to merge/overwrite the empty placeholder files.
4. Start Apache in XAMPP.
5. Open:
   http://localhost/aimefilms/public/

FILES
- public/index.php                 Main AIMEFILMS landing page
- public/assets/css/variables.css  Brand colours and design tokens
- public/assets/css/components.css Reusable buttons and UI components
- public/assets/css/main.css       Main homepage styling
- public/assets/css/responsive.css Mobile and tablet styling
- public/assets/js/main.js         Navigation and search interactions
- config/app.php                   Basic application configuration

NOTES
- The Unsplash images and Google Fonts require an internet connection.
- The search and platform links are visual placeholders for now.
- Authentication, database content, streaming and payments are not connected yet.
- This is a development starter. Before production, configure Apache DocumentRoot
  to point to the public directory and protect private files and directories.
