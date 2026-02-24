# Copilot Instructions for EC Codebase

## Big Picture Architecture
**Type:** Vanilla PHP EMR (Electronic Medical Record) for wound care, running on XAMPP.

**Stack:**
- **Backend:** Procedural PHP (no framework), MySQL via mysqli
- **Frontend:** Vanilla JS + jQuery, Tailwind CSS (CDN), Bootstrap Bundle (CDN - limited)
- **Environment:** Local XAMPP (Apache/MySQL/PHP)

**Core Patterns:**
- **View-Logic Separation:** PHP views render HTML and fetch data; JS handles interactivity
- **API-First:** All dynamic actions use `api/` endpoints (procedural PHP returning JSON)
- **Data Injection:** PHP prepares data, injects into `window` scope at bottom of files:
  ```php
  <script>
  window.printData = <?= json_encode($print_data) ?>;
  </script>
  ```

**Services:**
- Google Cloud Vertex AI / Gemini for AI features
- Firebase Firestore for real-time chat
- Custom `.env` loader in `db_connect.php`

## Key Files & Directories

**Essential Files:**
- `db_connect.php` - Single source of truth for DB + custom `.env` loader with `loadEnv()` function
- `templates/header.php` - Universal header with CDN imports (jQuery, Tailwind, Bootstrap, Lucide icons)
- `templates/footer.php` - Closing tags
- `autosave_manager.js` - Centralized debounced autosave with status indicators

**Core Views:**
- `visit_ai_assistant.php` - Modern AI-driven visit interface (1600+ lines)
- `dashboard.php` - Main dashboard
- Patient management views follow pattern: `<feature>_<entity>.php`

**API Directory (`api/`):**
- Naming: `verb_noun.php` (e.g., `get_patients.php`, `save_visit_note.php`, `create_wound.php`)
- All require `require_once '../db_connect.php'` at top
- All start with `session_start()` and check `$_SESSION['ec_user_id']`
- Return JSON with structure: `{"success": bool, "message": string, "data": object}`

**Config Files:**
- `.env` - Environment variables (DB credentials, API keys)
- `api/vertex_auth.php` - Google Cloud auth with JWT token caching
- `api/google_cloud_config.php` - GC project settings
- `config/service_account.json` - Google service account (gitignored)

## Developer Workflows

**Running the App:**
- Start XAMPP (Apache + MySQL)
- Navigate to `http://localhost/ec/` (or your XAMPP htdocs path)
- No build step - uses CDN links exclusively

**Database Management:**
- Schema changes: Create `.sql` files in root (e.g., `add_<feature>_col.sql`)
- Apply manually via phpMyAdmin or command line
- Check schema: Use `check_*_schema.php` scripts (e.g., `check_patients_schema.php`)
- Active DB: `ec_wound` (default in `.env`)

**Testing & Debugging:**
- **API Testing:** Create `api/test_*.php` scripts (e.g., `test_vertex_connection.php`)
- **PHP Errors:** Check `error_log` (root) or `api/error_log`
- **AI Debug Logs:** `api/debug_ai_response.txt`, `api/debug_vertex_response.txt`
- **Network Debugging:** Browser DevTools → Network tab for API calls
- **Session Issues:** Check `$_SESSION` vars in `templates/header.php`

**Adding New Features:**
1. Create API endpoint in `api/` with session check + `db_connect.php`
2. Update/create view file with data fetch at top, HTML in middle, JS at bottom
3. Add schema changes in `.sql` file if needed
4. Test API directly in browser or via `test_*.php` script

## Project-Specific Conventions

**Authentication & Sessions:**
- Session-based: `$_SESSION['ec_user_id']`, `$_SESSION['ec_role']`, `$_SESSION['ec_full_name']`
- All API endpoints MUST check: `if (!isset($_SESSION['ec_user_id'])) { http_response_code(401); exit; }`
- `templates/header.php` auto-redirects to `login.php` if not authenticated
- Patient portal uses `$_SESSION['portal_patient_id']`

**API Endpoint Pattern:**
```php
<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// Your logic here
echo json_encode(["success" => true, "data" => $result]);
```

**Frontend Data Flow:**
1. PHP fetches data from DB at top of file
2. PHP builds `$data_array` with all needed info
3. Bottom of PHP file injects: `window.myData = <?= json_encode($data_array) ?>;`
4. JS accesses via `window.myData` and handles UI updates

**Modal/Embedded Mode (CRITICAL FOR MDI):**
- Query param `?layout=modal` enables embedded view mode
- **MUST add at TOP of ALL view files** (before `require_once 'templates/header.php'`):
  ```php
  // Check if we are in "Modal Mode" (embedded in MDI iframe)
  $is_modal_mode = isset($_GET['layout']) && $_GET['layout'] === 'modal';
  ```
- **MUST conditionally load sidebar**:
  ```php
  <?php 
  if (!$is_modal_mode) {
      require_once 'templates/sidebar.php';
  }
  ?>
  ```
- When true: Skip sidebar, skip header/footer navigation
- Used by MDI (Multi-Document Interface) in `mdi_shell.php`
- Example: `visit_ai_assistant.php?patient_id=123&layout=modal`
- **Common Bug**: Forgetting modal mode check causes duplicate sidebars in MDI tabs

**Autosave Pattern:**
- Import `autosave_manager.js` as module
- Call `initAutosaveManager()` with DOM refs
- Inputs need `name` or `id` attributes for auto-detection
- Debounced at 3 seconds (see `AUTOSAVE_DELAY` constant)

**Environment Variables:**
- Custom loader in `db_connect.php` - NOT using vlucas/phpdotenv
- Access via: `getenv('VAR_NAME')` or constants like `GEMINI_API_KEY`
- Loaded automatically when `db_connect.php` is included

**Styling Guidelines:**
- **Primary:** Tailwind utility classes (`flex`, `p-4`, `bg-indigo-600`, `text-white`)
- **Limited:** Bootstrap components (modals, tooltips) - only if already used
- **Avoid:** New CSS files or inline `<style>` blocks
- **Icons:** Lucide icons via `<i data-lucide="icon-name"></i>` + `lucide.createIcons()`

**Path Conventions:**
- Root-level views reference: `api/endpoint.php`, `templates/header.php`
- API files reference: `../db_connect.php`, `../config/file.ext`
- Patient portal: `patient_portal/api/` uses `../../db_connect.php`

**Role-Based Access Control (RBAC):**
- Roles: `admin`, `clinician`, `facility`, `scheduler`
- API endpoints filter data by role (see `get_patients.php` for example)
- Clinicians see only their assigned patients: `WHERE primary_user_id = $_SESSION['ec_user_id']`

## Integration Points

**Google Cloud AI (Vertex AI / Gemini):**
- Auth: `api/vertex_auth.php` (JWT + token caching in `config/access_token.json`)
- API Key: `GEMINI_API_KEY` constant from `.env`
- Example usage: `api/generate_ai_summary.php`, `api/ai_companion.php`
- Test connection: `api/test_vertex_connection.php`

**Firebase (Chat System):**
- Config: Injected in `templates/header.php` into `window.firebaseConfig`
- Logic: `chat_logic_firebase.js` (633 lines)
- Auth: `window.authReadyPromise` ensures Firebase ready before operations
- Firestore collections: `chats`, `users`, `messages`

**Third-Party APIs:**
- Google Maps: `GOOGLE_MAPS_API_KEY` constant from `.env`
- ICD-10 Code Lookup: External API (see `api/test_icd_api.php`)

## Common Tasks

**Adding a New API Endpoint:**
1. Create `api/<verb>_<noun>.php`
2. Add session check + `db_connect.php` require
3. Write procedural logic (no classes/namespaces)
4. Return JSON with `success`, `message`, `data` keys
5. Test directly: `http://localhost/ec/api/<your_file>.php`

**Adding a New View:**
1. Create `<feature>.php` in root
2. **Add modal mode detection FIRST**:
   ```php
   <?php
   $is_modal_mode = isset($_GET['layout']) && $_GET['layout'] === 'modal';
   ```
3. Include `require_once 'templates/header.php';`
4. Fetch data from DB (after header)
5. Output HTML with Tailwind classes
6. **Conditionally include sidebar**: `if (!$is_modal_mode) { require_once 'templates/sidebar.php'; }`
7. Add `<script>` at bottom with `window.pageData = <?= json_encode($data) ?>;`
8. Include `require_once 'templates/footer.php';` at end

**Modifying Visit Interface:**
- Main file: `visit_ai_assistant.php`
- Data prep: Lines 1-100
- HTML structure: Lines 100-1500
- JS logic: Lines 1500-1667
- Read FULL file top-to-bottom before editing

**Debugging AI Features:**
- Check `api/debug_ai_response.txt` for raw AI output
- Verify API key: `echo GEMINI_API_KEY;` in test file
- Test auth: Run `api/test_vertex_connection.php` in browser
- Firebase issues: Check browser console for `authReadyPromise` errors

**Schema Changes:**
1. Create descriptive `.sql` file (e.g., `add_appointment_status_column.sql`)
2. Test on local DB via phpMyAdmin
3. Document in migration notes if complex
4. Run on production manually (no ORM migrations)

## Critical Go Detection:** **MANDATORY** for all views - forgetting this causes duplicate sidebars in MDI
  - Add `$is_modal_mode` check BEFORE header include
  - Conditionally load sidebar based on this variable
  - See `visit_ai_assistant.php` or `add_appointment.php` for reference
- **Firebase Auth:** Wait for `window.authReadyPromise` before Firestore operations
- **Autosave:** Only works if form inputs have proper `name` or `id` attributes
- **API Paths:** Always use relative paths from current directory context
- **MDI Navigation:** Never manually manipulate iframe src or tab metadata - use `manager.openTab()`, `manager.closeTab()`, `manager.switchTab()` methods this globally
- **JSON Encoding:** Use `json_encode($data, JSON_PRETTY_PRINT)` for readability in logs
- **Error Display:** Production has `ini_set('display_errors', 0);` - check error logs
- **Modal Mode:** Views with `?layout=modal` should skip sidebars and limit navigation
- **Firebase Auth:** Wait for `window.authReadyPromise` before Firestore operations
- **Autosave:** Only works if form inputs have proper `name` or `id` attributes
- **API Paths:** Always use relative paths from current directory context
