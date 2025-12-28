# WCL Rajbhasha Portal – Knowledge Guide

This document summarizes how the portal works so the in‑app assistant can answer accurately and provide actionable guidance.

## Overview
- Purpose: Quarterly Hindi usage reporting, analytics, and administrative management.
- Stack: PHP 8 + PDO MySQL, Bootstrap 5, vanilla JS.
- Security: Login required, CSRF protection, server-side validation. API keys remain server-side.
- Key Paths:
  - Dashboard: `public/dashboard.php`
  - Reports list: `public/report/list.php`
  - New report: `public/report/new.php`
  - Edit report: `public/report/edit.php?id=...`
  - View report: `public/report/view.php?id=...`
  - Admin users: `public/admin/users.php`

## Roles
- `officer`: Creates and submits reports for their unit.
- `reviewer`: Reviews reports; access to some admin menus.
- `super_admin`: Full administration (users, units, backups).

## Report Structure (Quarterly)
- Step 1 – Period: Quarter `1–4`, Year (default current).
- Step 2 – Section 1 (धारा 3(3)) Fields:
  - `sec1_total_issued`: Total issued.
  - `sec1_issued_in_hindi`: Issued in Hindi (total).
  - `sec1_issued_english_only`: English-only.
  - `sec1_issued_hindi_only`: Hindi-only (must be ≤ Issued in Hindi).
  - Validation: `Hindi-only ≤ Issued in Hindi`, and `Hindi (total) + English-only ≤ Total issued`.
- Step 3 – Section 2 (Rule-5):
  - `sec2_received_in_hindi`, `sec2_replied_in_hindi`, `sec2_not_replied_in_hindi`, `sec2_reason_not_replied` (required if not replied > 0).
- Step 4 – Section 3 (Dynamic Rows):
  - Table rows with `कुल (total)`, `हिंदी (hi)`, `अंग्रेज़ी (en)`, `टिप्पणी (rem)` stored as JSON in hidden `sec3_rows_json`.
  - Use “+ Add Row” to add entries.
- Step 5 – Section 4/5/6 and Attachments:
  - Section 4 categories (`क, ख, ग` each with `Hindi`, `English`, `Bilingual`).
  - Section 5: `files_hindi`, `files_pending`, `files_delayed`, `remarks`.
  - Section 6: `events`, `participants`, and upload (PDF/JPG/PNG up to 10MB each).

Notes:
- Inputs accept free text; numbers are parsed in JS (`report.js`).
- Autosave runs every 30s to `report/save_ajax.php`; manual Draft and Final submission available.
- After submit, redirects to `report/view.php?id=...`.

## Admin: Users
- Create user requires unique email; duplicate emails show a friendly flash error (handled in `public/admin/users.php`).
- Validates name, email, password, role, unit.

## Assistant Features
- Floating Assistant (💬) modal with:
  - Chat: posts to `assistant/openrouter_chat.php` (login + CSRF required). Uses OpenRouter model from `.env`.
  - Translate: `assistant/translate.php` (Hindi/English).
  - OCR: `assistant/ocr.php` for images/PDF.
- Chat memory is session-based with last few turns preserved.
- Retrieval: Assistant reads this knowledge and `README.md` to answer precisely.

## Branding & Assets
- Navbar/logo auto-detection from `assets/images/` or `public/assets/images/` with data URI embedding.
- Login background auto-detection with fallbacks.

## Configuration (.env)
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`, `BASE_URL`.
- OpenRouter:
  - `OPENROUTER_API_KEY` (required), `OPENROUTER_MODEL` (default `openai/gpt-4o`), `OPENROUTER_MAX_TOKENS` (default `512`).
  - Optional: `OPENROUTER_SITE_URL`, `OPENROUTER_SITE_TITLE`.
- Assistant knowledge & memory:
  - `ASSISTANT_KNOWLEDGE_ENABLED=1`
  - `ASSISTANT_KNOWLEDGE_DIR=knowledge`
  - `ASSISTANT_CONTEXT_CHARS=6000`
  - `ASSISTANT_MAX_HISTORY=6`

## Tips for Good Reports
- Keep Hindi-only ≤ Hindi (total); ensure Hindi (total) + English-only ≤ Total.
- Provide a reason when not replied in Hindi.
- Attach supporting documents (PDF/JPG/PNG <= 10MB).
- Use suggestions and translate tools for accuracy.

## Troubleshooting
- 404 after submit: ensure routes under `public/report/` (fixed in code).
- Images not showing: use assets folders; logo/background are auto-detected.
- OpenRouter errors: check `.env` key, model, and `OPENROUTER_MAX_TOKENS` to avoid credit errors.
