# BDVault — Project Handover

> **What this is:** A PHP MVC real-estate **feasibility & redevelopment planning** system built around Mumbai's **DCPR 2034** regulations (33(5), 33(10), 33(11), 33(20)(B), etc.). It takes a plot + building inputs, applies the correct regulation, produces a formal **Area Statement**, layers a **Financial Model** on top (revenue / cost / IRR / NP), runs **sensitivity + scenarios**, cross-checks the numbers against **formula discrepancies**, and finally gives a **GO / NO-GO verdict** — with everything auditable, exportable to PDF/Excel, and explained by a grounded AI assistant.

Repo lives at `C:\xampp\htdocs\bdvault\` — served via XAMPP at **http://localhost/bdvault**.

---

## 1. TL;DR — the 30-second story

- **Stack:** Vanilla PHP 8, MySQL/MariaDB (XAMPP), Bootstrap-ish CSS, no framework.
- **DB name:** `pms_db` (project's original name was "PMS – Proposal Management System"; renamed folder to `bdvault`, DB name kept for compatibility).
- **Login:** `admin` / `demo123` (see §12 — original seed was `Admin@123`).
- **Entry point:** `index.php` (single front-controller router — `?page=<route>`).
- **The user-facing workflow (final shape):**
  1. **Area Analysis** — pick proposal → wizard → produce Area Statement (BUA, FSI, rehab component, sale component)
  2. **Financial Analysis** — configure rates & costs → compute revenue, cost, IRR, NP, margin
  3. **Best Outcome** — see GO / NO-GO verdict across every calculated statement
- Every other feature (PDF report, discrepancies, overrides, AI chat, cross-scheme view, sensitivity, scenarios) hangs off those 3 steps but does **not** clutter the sidebar.

---

## 2. Local setup

### Prerequisites
- **XAMPP** with Apache + MySQL running (default ports 80 / 3306).
- Windows filesystem (case-insensitive — see §11 for the gotcha this creates).
- PHP 8.x (bundled with recent XAMPP).

### First-run
1. Clone / copy the project into `C:\xampp\htdocs\bdvault\`.
2. Create the DB `pms_db` in phpMyAdmin (or let migrations create tables into an existing empty schema).
3. Visit **http://localhost/bdvault/index.php?page=setup** once → creates initial tables + seeds default weights.
4. Visit **http://localhost/bdvault/index.php?page=migrate** → runs `migrate_v2.sql` which seeds the `admin` user.
5. Then run each migration in order (see §5).
6. Login with `admin` / `demo123`.

### DB credentials
`config/database.php` picks `root` / empty password on localhost:
```php
if (localhost / 127.0.0.1 / 192.168.*) {
    DB_HOST=localhost  DB_USER=root  DB_PASS=''  DB_NAME=pms_db
} else {
    // production creds baked in — see file
}
```
`getDB()` returns a memoized `mysqli` connection with `utf8mb4`.

### Auth model
- Token-based (not PHP sessions) — see `includes/token_auth.php`. Sessions were fighting Varnish/NFS on the intended prod host, so tokens live in the `auth_tokens` table and are set as cookies.
- Bootstrapped every request by `TokenAuth::boot()` at the top of `index.php`.
- Permission helper `requirePermission('xxx')` checks role → capability map in `includes/permissions.php`.

---

## 3. Repo layout

```
bdvault/
├── index.php                  ← front-controller router (all routes)
├── config/
│   ├── database.php           ← DB creds + getDB()
│   └── constants.php
├── includes/
│   ├── functions.php          ← e(), utility helpers
│   ├── token_auth.php         ← TokenAuth::boot / user / logout
│   └── permissions.php        ← requireLogin / requirePermission / requireAdmin
├── controllers/               ← ~11 controllers, one per feature area
│   ├── AreaAnalysisController.php   ← THE feasibility workflow controller
│   ├── DevelopmentPlanningController.php  ← legacy planning (kept for cross-scheme + financials)
│   ├── DocumentsController.php      ← DA (Development Agreement) upload + Gemini extraction
│   ├── ProposalController.php       ← proposal CRUD + list
│   ├── DCPRController.php           ← rule browser + /regulatory_rules admin
│   ├── MarketAnalysisController.php ← AI-driven market analysis on a proposal
│   ├── AIController.php             ← proposal-level AI (extract, stage1/2 scoring, chat)
│   ├── AuthController.php           ← login / logout / register (registration disabled)
│   ├── AdminController.php          ← user CRUD
│   ├── DashboardController.php
│   └── WeightsController.php        ← proposal scoring weights config
├── lib/                       ← business logic — read the calculators + engine
│   ├── AreaCalcEngine.php           ← inputs → calculator → persistence → discrepancies → overrides
│   ├── calculators/                 ← one per DCPR regulation
│   │   ├── Reg33_10_Calculator.php  ← SRA (most-used, most complete)
│   │   ├── Reg33_11_Calculator.php  ← Transit / civic amenity
│   │   ├── Reg33_5_Calculator.php   ← MHADA layouts
│   │   ├── Reg33_20B_Calculator.php ← Private affordable housing
│   │   └── Reg33_10_FinancialsCalculator.php ← revenue / cost / IRR / NP model
│   ├── validators/PlanValidator.php ← JSON-schema style assertions
│   ├── RulesRegistry.php            ← central store for DCPR constants (see §7)
│   ├── AreaStatementFinancialsBridge.php ← Area Statement → FinancialsCalculator inputs
│   ├── CalcOverrideManager.php      ← manual overrides + audit trail
│   ├── FormulaDiscrepancyDetector.php ← 8 cross-checks that flag bad numbers
│   ├── RegulationSuggester.php      ← rule engine picking best regulation for a plot
│   ├── SitePlanRenderer.php         ← SVG site-plan visualization
│   ├── AIClient.php                 ← callGemini() wrapper (also handles vision)
│   ├── AreaStatementAIAssistant.php ← grounded chat context for area statements
│   ├── PlanAIAssistant.php          ← grounded chat for legacy plans
│   ├── DAExplainerAssistant.php     ← Q&A over uploaded DAs
│   ├── DAExtractionEngine.php       ← Gemini JSON extraction from DAs
│   ├── DocumentTextExtractor.php    ← text + OCR from PDFs/DOCX/images
│   ├── DocumentStorage.php          ← file handling for /uploads
│   └── DCPRRuleLoader.php           ← loads dcpr_rules/*.json into DB
├── views/                     ← templated PHP views (no engine — plain PHP)
│   ├── layout/                ← header / footer / sidebar
│   ├── area_analysis/         ← 26 views — wizard + reports + best_outcome + AI chat + PDF
│   ├── planning/              ← legacy planning views (still reachable)
│   ├── documents/             ← DA upload / extract / verify / reconcile
│   ├── proposals/ dcpr/ dashboard/ weights/ admin/ auth/
├── dcpr_rules/                ← JSON regulation definitions + schemas + validations
├── sql/                       ← 17 migration files (setup + migrate_v2..v17)
├── uploads/                   ← DA files, extracted text (protected via .htaccess)
├── assets/                    ← css, images
├── models/                    ← thin data helpers
└── scratch/                   ← throwaway seed scripts, ignore
```

**Top-level debug / one-off scripts you can safely delete or ignore:**
`debug_cats.php`, `debug_db.php`, `deploy.php`, `fix_admin_role.php`, `migrate3.php`, `psftp.exe`, `read_cols.php`, `repair_roles.php`, `test_ftp.ps1`, `WhatsApp Image ...jpeg`, `ai_debug.log`, `/sessions/` (empty), `/scratch/`.

---

## 4. The router (`index.php`)

Every URL is `index.php?page=<slug>`. The router is a big `switch` — search inside `index.php` for the slug to find its handler. Groups you should know:

| Group | Slugs | Handler |
|---|---|---|
| Auth | `login`, `login_process`, `logout` | `AuthController` |
| Proposals | `proposals`, `proposal_detail`, `proposal_add`, `proposal_edit`, `proposal_save`, `proposal_decision`, `proposal_delete`, `reclassify`, `upload`, `upload_process`, `proposal_report` | `ProposalController` |
| AI (proposal-level) | `api_ai_extract`, `api_ai_chat`, `api_ai_stage1`, `api_ai_stage2`, `api_ai_underwrite`, `api_market_analysis_*` | `AIController` / `MarketAnalysisController` |
| Weights | `weights`, `weights_save`, `weights_activate` | `WeightsController` |
| Admin | `admin_users`, `admin_user_create`, `admin_user_edit`, `admin_user_delete`, `admin_user_save_*` | `AdminController` |
| Documents (DA) | `documents`, `documents_upload`, `documents_extract`, `documents_verify`, `documents_download`, `documents_source`, `documents_reconcile`, `api_documents_assistant` | `DocumentsController` |
| **Area Analysis (canonical)** | `area_analysis`, `area_analysis_start`, `area_analysis_step`, `area_analysis_save_step`, `area_analysis_review`, `area_analysis_generate`, `area_analysis_calculate`, `area_analysis_refresh_calc`, `area_analysis_report`, `area_analysis_pdf_report`, `area_analysis_dashboard`, `area_analysis_sensitivity`, `area_analysis_scenarios`, `area_analysis_feasibility`, `area_analysis_discrepancies`, `area_analysis_best_outcome`, `area_analysis_financials_list`, `area_analysis_financials_form`, `area_analysis_financials_save`, `area_analysis_financials_view`, `area_analysis_overrides`, `area_analysis_apply_override`, `area_analysis_deactivate_override`, `area_analysis_new_version`, `area_analysis_compare`, `area_analysis_feasibility_excel`, `area_analysis_export`, `area_analysis_delete`, `api_area_assistant`, `api_calc_explain` | `AreaAnalysisController` |
| Planning (legacy) | `planning`, `planning_form`, `planning_save`, `planning_view`, `planning_report`, `planning_clone`, `planning_compare`, `planning_delete`, `planning_cross_scheme`, `planning_financials_*`, `planning_prefill_da`, `planning_upload_autofill`, `api_planning_assistant` | `DevelopmentPlanningController` |
| DCPR | `dcpr`, `dcpr_rule`, `dcpr_reload`, `regulatory_rules`, `regulatory_rule_update`, `regulatory_rule_audit` | `DCPRController` |
| Migrations | `migrate`, `migrate_v3`..`migrate_v17`, `setup` | inline |

Anything else → 404.

---

## 5. Database (17 migrations, run in order)

Run each migration by visiting `index.php?page=migrate_vN` (localhost-only guard). Migration files are in `sql/`. Recap:

| # | Purpose | Key tables |
|---|---|---|
| v1 (setup.sql) | Core schema | `proposals`, `stage1_results`, `stage2_scores`, `weights_config`, `import_logs` |
| v2 | Auth + users | `users` (seeds `admin` user), roles / permissions |
| v3 | Proposal report fields | ALTER `proposals` |
| v4 | Small ALTER | — |
| v5 | Additional proposal fields | — |
| v6 | **DCPR rule storage** | `dcpr_rules` |
| v7 | **Legacy planning schema** | `development_plans`, `plan_computations`, `plan_validations` |
| v8 | Scenarios | `parent_plan_id`, `is_scenario` columns on plans |
| v9 | **DA docs + extractions** | `documents`, `document_extractions`, `extraction_fields` |
| v10 | **Plan financials** | `plan_financials` |
| v11 | **Area Analysis wizard** | `area_statements`, `area_statement_calcs`, `area_statement_fsi`, `area_statement_dev_units`, `area_statement_land`, `area_statement_valids`, versions/status columns |
| v12 | **Area Statement financials** | `area_statement_financials` (PK = `statement_id`) |
| v13 | Multi-road + sub-labels + in-situ + RR fields | ALTER `area_statement_land` and `area_statement_financials` |
| v14 | **Financial line items** | `area_statement_line_items` (site cost, TDR, permit categories) |
| v15 | **Regulatory constants centralized** | `regulatory_constants` + `regulatory_constants_audit` (33+ seed rows: FSI caps, loading factors, fungible %, zonal/additional FSI, share splits, amenity defaults) |
| v16 | **Formula discrepancies** | `formula_discrepancies` (severity/expected/actual/variance) |
| v17 | **Manual overrides** | `area_statement_calc_overrides` + `_audit` + ALTER for `is_overridden`, `original_value`, `override_id` columns on `area_statement_calcs` |

**Schema gotchas** (bit us more than once — write them into your head):
- `area_statement_calcs` uses **`calc_unit`** (not `unit`). No `calc_group` column — grouping is by prefix (`rehab_*`, `sale_*`, etc.).
- `area_statement_fsi` uses **`fsi_ratio`** (not `fsi_value`).
- `area_statement_financials` has **no `id`** — PK is `statement_id`. In SQL: `SELECT f.statement_id AS fin_id`, not `f.id`.
- `area_statement_financials.sale_rate_y1_inr_sqft` (year suffix at the end).
- `area_statement_calc_overrides`: unique constraint on `(statement_id, calc_key, active)` was too strict (couldn't have two inactive rows for one key); replaced with a plain INDEX and the app enforces "one active per key" via `apply()`.

---

## 6. The 3-step workflow (final, current shape)

Sidebar shows exactly three numbered steps under "Feasibility Workflow":

1. **① Area Analysis** → `index.php?page=area_analysis`
2. **② Financial Analysis** → `index.php?page=area_analysis_financials_list`
3. **③ Best Outcome** → `index.php?page=area_analysis_best_outcome`

Every page in the workflow shows a **`.wf-crumb`** breadcrumb at the top:
`1 ✓ → 2 ✓ → 3 (current)` with completed steps highlighted green.

### Step 1 — Area Analysis (`AreaAnalysisController`)
Wizard with 6 steps (views `step1.php` … `step6.php` inside a shared `_wizard_shell.php`):
1. Statement info (name, proposal, tenure)
2. Plot & land (multi-road, deductions, reservations, net plot area auto-derived)
3. Buildings (dynamic multi-building table, rehab preview)
4. Regulation pick (33(5) / 33(10) / 33(11) / 33(20)(B)) — `RegulationSuggester` scores each option
5. Calculate (runs `AreaCalcEngine` → picks correct calculator → persists rows into `area_statement_calcs`, `area_statement_fsi`, etc.)
6. Review + Lock (semantic version bump — v1.0, v1.1, ...)

Then the **Feasibility Statement view** (`feasibility_statement.php`) renders the classic **21-row** feasibility table with regulation-aware key fallbacks (Rows 9–14 fall back through `rehab_tenement_bua_sqm` → `total_rehab_bua_sqm` → `rehab_bua_sqm` → `society_bua_sqm` → `sra_ptc_share_bua_sqm` etc., so both 33(10) and 33(11)/33(5)/33(20)(B) statements render).

**Rows 16, 17, 18 always recompute fresh in the view** (`totalBuaProposed = rehabBldg + saleInSitu`, `fsiConsumedInSitu`, `balanceSaleTDR`) — this fixes stale-override-propagation bugs.

### Step 2 — Financial Analysis (`financials_list.php`)
Grouped by proposal. Each Area Statement shows a row with: version, status, NP after int, margin, IRR, revenue, and either "View" (if financials exist) or "Set up" (if not). Green hero, JetBrains Mono numbers.

Financial model uses `Reg33_10_FinancialsCalculator` (works for all regulations — it's really an income-statement engine). Inputs come via `AreaStatementFinancialsBridge`:
- Sale BUA, sale-in-situ, sale-rate Y1, RR rates, hard construction cost, soft cost %, marketing %, GST %, finance rate, project duration
- Plus dynamic **line items** (site cost, TDR premium, permit fees) from `area_statement_line_items`

Outputs: `np_after_int`, `margin_pct`, `irr_pct`, `revenue_inr`, `total_cost_inr`, per-year cash flows.

### Step 3 — Best Outcome (`best_outcome.php`)
Loads every calculated statement, runs `AreaStatementFinancialsBridge`, computes a verdict:

| Verdict | Trigger |
|---|---|
| **STRONG GO** | Margin ≥ 20% AND IRR ≥ 20% |
| **GO** | NP ≥ 0 AND margin ≥ 10% AND IRR ≥ 15% |
| **CAUTION** | Below internal thresholds — fragile |
| **NO-GO** | NP < 0 at base inputs |
| **INCOMPLETE** | Financials not configured yet |

Shows 5 verdict-count tiles + per-statement cards with pill + metrics + action buttons (Scenarios / Sensitivity / PDF, or "Set up financials" for incomplete ones).

---

## 7. Regulatory constants — `RulesRegistry`

Before RR1–RR6, DCPR constants (5.40 fungible %, 1.15 loading factor, 27.88 amenity FSI, 35% share split, 4.0 FSI cap, 38% amenity-of-rehab default, etc.) were hardcoded across `AreaCalcEngine` + 4 calculators. **Do NOT re-introduce hardcoded constants** — everything lives in the `regulatory_constants` table now.

**Usage:**
```php
use lib\RulesRegistry;

$fungiblePct  = RulesRegistry::get('fungible_pct_of_rehab_bua', '33_10');       // required — throws if missing
$maxFsi       = RulesRegistry::getOrNull('max_fsi_cap', '33_11');               // null-safe
$row          = RulesRegistry::getRow('amenity_pct_default_of_rehab_bua', '33_10');
$all33_10     = RulesRegistry::getAll('33_10');
RulesRegistry::update($key, $scope, $newValue, $userId, $reason);              // writes audit row
```

- Per-request memoization inside the class — safe to call repeatedly.
- Admin edits go through `/regulatory_rules` (view `regulatory_rules.php`) with full audit trail at `/regulatory_rule_audit`.
- Audit table is `regulatory_constants_audit` (who changed what, when, why).

**Premium map** (`AreaStatementFinancialsBridge::premiumRate()`) also uses the registry — `RulesRegistry::getOrNull('premium_rate_factor', $reg)`.

---

## 8. Formula Discrepancy Detector

`FormulaDiscrepancyDetector::run($statementId)` runs **8 cross-checks** identity-style and writes rows to `formula_discrepancies` with severity:

1. `rehab_component_identity` — rehab_tenement + amenity + fungible + BE + ancillary + LOD_saleable = total_rehab_bua
2. `sale_component_identity` — sale_permissible sum
3. `total_bua_identity` — rehab + sale = total
4. `fsi_bua_ratio` — FSI × net plot = total BUA
5. `fsi_ceiling_hit` — flags if computed FSI exceeds regulation cap
6. `sale_fungible_pct` — sale fungible / sale BUA should match rule
7. `rehab_bua_derivation` — rehab BUA from carpet × loading factor
8. `net_plot_identity` — gross plot − deductions = net plot

Called automatically at the end of `AreaCalcEngine::run()`. UI at `/area_analysis_discrepancies` shows severity chips, expected vs actual vs variance, and a rescan / resolve action.

---

## 9. Manual Overrides + Audit

Any calc row in `area_statement_calcs` can be overridden.

- **Table:** `area_statement_calc_overrides` (one active row per `(statement_id, calc_key)`)
- **Audit:** `area_statement_calc_overrides_audit` (who / when / why / from → to)
- **Applied at read time** by `CalcOverrideManager::applyToRows($rows, $statementId)`
- Overridden calc rows carry `is_overridden = 1`, `original_value`, `override_id`
- View badge: amber "OVERRIDE" pill on the Feasibility Statement

**Foundational overrides (Net Plot, FSI cap, …) do NOT re-propagate through the calc chain** — MVP scope. The UI shows an amber warning banner + "Refresh calc" button on the Feasibility view. `CalcOverrideManager::FOUNDATIONAL_KEYS` is the whitelist.

Full UI at `/area_analysis_overrides` (apply form + active list + audit trail).

---

## 10. AI (Gemini)

Wraps a single provider — Gemini — via `lib/AIClient.php`. Three main assistants:

| Assistant | Where | Grounded on |
|---|---|---|
| `AreaStatementAIAssistant` | Area Statement pages (Feasibility, Report, PDF, Best Outcome) — chat FAB | statement + calcs (with `is_overridden` flags) + discrepancies + overrides + line items + financials + `regulatory_constants` |
| `PlanAIAssistant` | Legacy Planning view — chat FAB | plan + computation + validation |
| `DAExplainerAssistant` | Documents (DA) page | extracted DA JSON + source text |

- Grounded chat renders **markdown-lite** in the answer, with **citation chips** (green for DCPR references, indigo for `calc_keys`).
- API endpoints: `api_area_assistant`, `api_planning_assistant`, `api_documents_assistant`, `api_ai_chat`.
- Rate limit / usage logging: `ai_usage_logs` table.

---

## 11. Gotchas (WILL bite you)

1. **Windows case-insensitive FS**
   `bdvault/` and `BDVault/` resolve to the same directory in the shell but PHP's `require` treats them as different paths → `Cannot declare class X, because the name is already in use`. **Always use `require_once`**, and pick one casing (folder is `bdvault` lowercase).

2. **PHP null coalescing `??` fires on null only, not on 0.**
   `$x ?? 0` returns `0` when `$x === null`, but `$x = 0; $x ?? 5` returns `0`. Bit us on Row 15 fallback logic.

3. **Rows 9–21 on Feasibility view** are regulation-aware — if you add a new regulation, add the calc-key fallbacks in `views/area_analysis/feasibility_statement.php` (`$rehabBldg`, `$saleComponent`, etc.).

4. **Row 16 / 17 / 18** always compute fresh in the view — do NOT read `total_bua_proposed_sqm` from the DB there (override propagation issue).

5. **`bind_param` type strings** must match placeholder count EXACTLY. `persistCalcs` uses `issdidissssii` (13 chars for 13 placeholders).

6. **`.php ?? $foo['bar'] ?? 0`** patterns can hit undefined-index warnings — always wrap: `is_array($x ?? null) ? $x : []`.

7. **XAMPP MySQL flakiness** — service occasionally refuses connections. Restart MySQL from XAMPP Control Panel. Apache almost never goes down.

8. **PDF report warnings around missing columns** — always check the actual schema before writing new report fields. `area_statement_calcs` has `calc_unit` not `unit`, no `calc_group`. `area_statement_fsi` has `fsi_ratio` not `fsi_value`. `area_statement_financials` uses `sale_rate_y1_inr_sqft`.

9. **`loadLineItems()`** returns pre-grouped `[category => [rows]]` — don't try to re-group it, just `array_filter` out empty categories.

10. **Admin password was reset to `demo123`** during an old verification pass without asking the user first. Original seed value was `Admin@123` — restore if you prefer. To reset: run
    ```sql
    UPDATE users SET password = '<hash>' WHERE username = 'admin';
    ```
    where `<hash>` = `password_hash('YourNewPassword', PASSWORD_DEFAULT)` from any PHP shell.

---

## 12. Credentials

| System | Login | Password | Where |
|---|---|---|---|
| App (BDVault) | `admin` | `demo123` | Original was `Admin@123` — see §11 gotcha #10 |
| MySQL (local) | `root` | *(empty)* | `config/database.php` |
| MySQL (production) | *(env)* | *(env)* | Set `BDVAULT_DB_USER` / `BDVAULT_DB_PASS` / `BDVAULT_DB_HOST` / `BDVAULT_DB_NAME` in production env. See [`.env.example`](.env.example). |
| Gemini API key | — | Check `.env` or `AIClient.php` — grep for `GEMINI_API_KEY` |

**⚠️ Rotate the production MySQL password and the Gemini key before handing off to a different org — they're in-repo.**

---

## 13. Domain glossary (Mumbai DCPR 2034)

If you're new to Indian real-estate redevelopment, learn these:

| Term | Meaning |
|---|---|
| **DCPR 2034** | Development Control & Promotion Regulations (Mumbai), 2034 sanction. |
| **FSI** | Floor Space Index. Ratio of built-up area to plot area. FSI 4.0 on 1000 sqm plot ⇒ 4000 sqm built. |
| **BUA** | Built-Up Area (sqm). Includes walls, common areas. |
| **RG** | Recreation Ground. Mandatory open space, deducted from gross plot to get net plot. |
| **Reservation** | DP-marked land (road-widening, amenities) — deducted from gross plot. |
| **Rehab component** | Free area given to existing tenants/society members in redevelopment. |
| **Sale component** | Balance BUA the developer sells commercially — funds the project. |
| **Loading factor** | Ratio to convert tenant carpet area → BUA (typically 1.15). |
| **Fungible FSI** | Additional 35% (residential) / 20% (commercial) of rehab BUA the developer can build, on premium payment. |
| **Amenity** | Common-use area (society office, gym, welfare) — DCPR often mandates ~38% of rehab BUA as amenity, buildable at no additional charge. |
| **TDR** | Transferable Development Rights — can be loaded onto a plot to increase BUA. |
| **In-situ FSI** | FSI actually consumed on the plot itself (as opposed to via TDR elsewhere). |
| **RR rate** | Ready Reckoner rate — govt-published property valuation used for premiums. |
| **33(10)** | SRA redevelopment (slum). Most-used, most-complete calculator. |
| **33(5)** | MHADA layout redevelopment. |
| **33(11)** | Transit / civic amenity buildings. |
| **33(20)(B)** | Private affordable-housing scheme. |
| **DA** | Development Agreement — the legal contract between society and developer. |
| **IRR / NP / Margin** | Internal Rate of Return / Net Profit / Margin % — the three financial verdict metrics. |

---

## 14. Where to look when...

| I want to... | Read |
|---|---|
| Understand a URL | Find the slug in `index.php` → jump to the controller method |
| Add a new DCPR regulation | Copy `Reg33_10_Calculator.php`, register in `AreaCalcEngine::pickCalculator()`, add JSON rule to `dcpr_rules/`, seed constants into `regulatory_constants`, add fallbacks in `feasibility_statement.php` |
| Change a DCPR constant (e.g. fungible %) | Edit via `/regulatory_rules` UI — don't touch code |
| Add a new financial input | Extend `area_statement_financials` schema (write a `migrate_v18_*.sql`), update `AreaStatementFinancialsBridge`, update `financials_form.php` + `financials_view.php` |
| Add a formula cross-check | Add a method to `FormulaDiscrepancyDetector`, register in `run()` |
| Trace why a Feasibility row is wrong | Open `views/area_analysis/feasibility_statement.php`, look up the row's `calcVal([...keys])` fallback chain, then check `area_statement_calcs` for those keys |
| Manually override a calc row | `/area_analysis_overrides` → apply form (or via `CalcOverrideManager::apply()` in code) |
| Debug an AI chat | `ai_debug.log` in project root (tail it) |
| Reset admin password | See §12 |

---

## 15. Known unfinished / future work

- [ ] **Restore admin password** to `Admin@123` or user-chosen value (see §11 #10).
- [ ] **Advanced-tools grouping** on Feasibility toolbar — I promised to move Overrides / Discrepancies / Refresh calc under a single "⋯ Advanced" dropdown but haven't done it yet.
- [ ] **Foundational-input override propagation** — currently overrides on Net Plot / FSI cap don't re-derive dependent rows; user must click "Refresh calc" after applying. Would require re-running `AreaCalcEngine::run()` with overridden values injected as inputs.
- [ ] **Line-item categories** — currently site cost / TDR / permit; could extend to construction / professional / marketing / GST.
- [ ] **Port DA-prefill bridge** from legacy Planning to Area Analysis — task #37 equivalent for area analysis.
- [ ] **Rotate baked-in production credentials** in `config/database.php` before real deployment.
- [ ] **Delete the debug/one-off scripts** listed at end of §3.

---

## 16. Handoff checklist (what to do first)

1. Clone repo, install XAMPP, create `pms_db` schema.
2. Run `setup` + all migrations (§2, §5).
3. Log in with `admin` / `demo123`.
4. Open `/proposals`, click any proposal, click "Start Area Analysis" — walk through Steps 1 → 6.
5. Click through to Financial Analysis (Step 2), fill in sale rate + costs, save.
6. Visit `/area_analysis_best_outcome` — see the GO/NO-GO verdict.
7. Now read **`AreaAnalysisController.php`** end-to-end (it's ~1500 lines but it's the whole app). Then **`AreaCalcEngine.php`** (how inputs become numbers). Then any one calculator (**`Reg33_10_Calculator.php`** is the reference). You'll understand the whole system.

---

_Good luck. The DCPR domain is genuinely fiddly (every regulation has edge cases and Mumbai adds its own zonal overlays) but the abstractions here — Calculator + RulesRegistry + DiscrepancyDetector + OverrideManager — hold up. The 3-step workflow gives you a clean surface even if the underlying regulation math is dense._
