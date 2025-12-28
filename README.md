## OpenRouter (LLM) Integration

This project can call OpenRouter chat completions from the server (PHP), keeping your API key secure.

### 1) Configure `.env`

```
OPENROUTER_API_KEY=sk-or-...
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_MODEL=openai/gpt-4o
OPENROUTER_SITE_URL=http://localhost:8088
OPENROUTER_SITE_TITLE=Rajbhasha Portal
```

### 2) Server helper

- `lib/openrouter.php` provides `openrouter_chat($messages, $options=[])`.
- Do not expose your key to the browser; call this from PHP.

### 3) Test endpoint

- `POST /assistant/openrouter_chat.php` (requires login + CSRF)
   - Body: `prompt=Hello!` and `_csrf=...`

Example (PowerShell):

```powershell
$body = @{ prompt = 'Hello OpenRouter!'; _csrf = '<copy from meta[name=csrf-token]>' }
Invoke-WebRequest -UseBasicParsing -Method POST -Uri http://127.0.0.1:8088/assistant/openrouter_chat.php -Body $body
```

Notes:
- The endpoint includes optional ranking headers (HTTP-Referer/X-Title) via `.env`.
- See https://openrouter.ai/models for available models.

# WCL Rajbhasha Portal (Local XAMPP)

A secure, bilingual (Hindi + English) portal for Western Coalfields Limited to manage Rajbhasha progress reports, with role-based access, attachments, PDF export, analytics, and a Smart Rajbhasha Assistant (translate, OCR, suggestions) that works locally.

## Requirements
- Windows with XAMPP (Apache + MySQL/MariaDB)
- PHP 8.0+
- Composer (optional, required for PDF export and .env loading)

## Quick start (XAMPP)
1. Start XAMPP Control Panel and click Start for Apache and MySQL.
2. Copy this project folder to:
   - C:\\xampp\\htdocs\\rajbhasha_portal (recommended)
   - Or keep your current path and adjust BASE_URL in `.env`.
3. Create database `rajbhasha_db` using phpMyAdmin:
   - Open http://localhost/phpmyadmin/
   - Create a new database named `rajbhasha_db` with utf8mb4 collation.
4. Import schema and seeds:
   - Import `scripts/db_init.sql`
   - Import `scripts/seed.sql`
   - (Optional) Import `scripts/test_data.sql` for sample charts/listing
5. Configure environment:
   - Copy `.env.example` to `.env` and update if needed:
     ```
     DB_HOST=localhost
     DB_USER=root
     DB_PASS=
     DB_NAME=rajbhasha_db
     BASE_URL=http://localhost/rajbhasha_portal/
     ```
6. Install Composer packages (for PDF export and .env):
   - Open PowerShell in the project folder and run:
     ```powershell
     composer install
     ```
7. Access the portal:
   - http://localhost/rajbhasha_portal/

### Alternative: Run with PHP built-in server (no Apache vhost needed)
If your folder isn’t under `htdocs`, you can run a temporary server:

```powershell
# From the project root
php -S 127.0.0.1:8088 -t .\public
```

Then set `BASE_URL` in `.env` to:

```
BASE_URL=http://localhost:8088/
```

## Default credentials
- Super Admin: admin@example.com / Admin@123
- Officer: officer@example.com / Officer@123

Note: If you didn’t import user rows, the application will auto-create the above users and a default unit on first run when it detects an empty `users` table.

## Features
- Login/Logout with secure sessions, CSRF protection, password hashing
- Roles: Super Admin, Officer, Reviewer, Viewer
- Dashboard with summary cards, 4-quarter Hindi usage trend for your unit, and top-performing units (Chart.js)
- Report module: multi-section form with a 5-step wizard, draft/final submit, auto-save (every 30s), attachments (PDF/JPG/PNG up to 10MB)
- Live Hindi% indicator and cross-field validations (e.g., Hindi + English-only ≤ Total)
- Warning if Hindi% drops ≥10 points vs previous period (non-blocking flash)
- Admin panel: user management, units management (Super Admin), report approvals with reviewer comments and overdue highlighting
- Export to PDF (Dompdf) with UTF-8 Devanagari support
- Smart Rajbhasha Assistant: local endpoints for translate, OCR, and suggestions
   - Floating widget on every page; quick translate (Hi↔En) and drag-drop OCR
   - OCR returns word/char/line counts along with extracted text
- Analytics page with filterable charts and PDF export
- CSV export of reports list

## Smart Rajbhasha Assistant
A sidebar called “Smart Rajbhasha Sahayak (स्मार्ट राजभाषा सहायक)” on the report form provides:
- Smart Suggestions based on averages of last reports (local SQL analysis)
- Translate Hindi ↔ English using a simple dictionary stub
- OCR extraction using local Tesseract if installed (fallback to mock)
- Floating assistant widget is also available globally (bottom-right 💬)

### OCR Setup (Windows)
- Install: Tesseract OCR, ImageMagick, and Ghostscript. Optional: Poppler for `pdftotext` (improves digital PDF extraction).
- If executables are not on PATH, set absolute paths in `.env`:
   ```
   OCR_TESSERACT_EXE="C:\\Program Files\\Tesseract-OCR\\tesseract.exe"
   OCR_PDFTOTEXT_EXE="C:\\Program Files\\Git\\mingw64\\bin\\pdftotext.exe"
   OCR_MAGICK_EXE="C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe"
   OCR_TESSDATA_PREFIX="C:\\Program Files\\Tesseract-OCR\\tessdata"
   OCR_MAX_PAGES=5
   OCR_DENSITY_DPI=300
   ```
- Hindi language: ensure `hin.traineddata` exists under the `tessdata` folder above. Download from the official tessdata repo if missing.
- Endpoint: `POST /assistant/ocr.php` (requires login + CSRF). The UI is exposed via the bottom-right 💬 widget → OCR tab.
- Behavior: Tries `pdftotext` for digital PDFs; falls back to ImageMagick → Tesseract for scanned PDFs; images go directly to Tesseract.

## Project structure
```
rajbhasha_portal/
├── public/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── analytics.php
│   ├── report/
│   │   ├── new.php
│   │   ├── edit.php
│   │   ├── view.php
│   │   └── list.php
│   ├── admin/
│   │   ├── users.php
│   │   ├── units.php
│   │   ├── settings.php
│   │   └── backup_db.php
│   └── assistant/
│       ├── translate.php
│       ├── ocr.php
│       └── suggest.php
│   ├── toggle_lang.php
│   └── uploads/
├── lib/
│   ├── db.php
│   ├── auth.php
│   ├── csrf.php
│   ├── helpers.php
│   └── config.php
├── templates/
│   ├── header.php
│   └── footer.php
├── assets/
│   ├── css/style.css
│   ├── css/print.css
│   └── js/main.js
├── scripts/
│   ├── db_init.sql
│   ├── seed.sql
│   └── test_data.sql
├── composer.json
├── .env.example
└── README.md
```

## Security notes
- Uses `password_hash()` and prepared statements (PDO)
- CSRF tokens on all forms and AJAX endpoints
- Session cookie hardened with HttpOnly and SameSite=Lax

## PDF export
- Requires Composer packages to be installed. If Dompdf isn’t available, the “Download PDF” action will show an error.

## Printing
- Clean print styles are included; use the “Print Preview” button on the report wizard or your browser’s print. Navigation and buttons are hidden on print.

## Database backup
- Super Admins can download a SQL backup from Admin → Backup Database.
- If `mysqldump` is available in PATH, it’s used; otherwise a safe SQL dump is generated via PDO.

## Troubleshooting
- If http://localhost/rajbhasha_portal/ redirects to login repeatedly, ensure database is created and schema is imported.
- If styles or JS don’t load, verify BASE_URL in `.env` matches the folder under `htdocs`.
- For file uploads, ensure the folder `public/uploads` is writable (created automatically on first run).
 - If using the built-in server: set `BASE_URL=http://localhost:8088/` so asset URLs resolve correctly.

## License
Local/internal use for WCL demonstration and evaluation.