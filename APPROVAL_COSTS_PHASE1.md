# Approval Costs — Phase 1 Audit & Design

> **Scope.** Stage 1 only — audit, reconciliation, and design proposal. **No code written or modified.** The 8 deliverables requested in the brief are below (§1-§8), followed by acceptance-criteria mapping and open questions.

---

## 0. Source counts validated

| Source | Sheet | Entries |
|---|---|---|
| A — `NOC-Approval List 16.10.2026.xlsx` | `NOCs related to Buidling Devel` | **72** (Sr. No. 1-72) |
| A — same workbook | `Land Acquisition Documents` | **21** (Sr. No. 1-21) |
| B — `Checklist - Extract.xlsx` | `Checklist 2-FICCI` | **28** (Sr. No. 2-29; item 1 absent in the extract) |
| **Total** | | **121** |

Source A's items are name-only (no authority, cost, duration). Source B has 5 columns: `Sr.No`, `Approval Step`, `Approving Authority`, `Time Required`, `Cost`. Of its 28 entries, 11 have an explicit cost figure (indicative), 27 have a time figure.

---

## §1. Understanding of the existing system

### Technology stack
- **PHP 8 MVC** (vanilla, no framework), **MySQL / MariaDB** (`pms_db`), vanilla CSS with a shared design system (`--v2-*` tokens).
- Front controller: [`index.php`](index.php) switches on `?page=<slug>` → calls `<Area>Controller::<method>()`.
- Database access via `getDB()` helper (`mysqli`).
- Token-based auth: `TokenAuth::boot()` at the top of every request; `requireLogin()` / `requirePermission('xxx')` guards.
- Full-fat helpers: `flashSet()`, `flashGet()`, `redirect()`, `e()`.
- 19 migrations already applied (`sql/migrate_v2..v19`). New schema additions go via `migrate_v20_*.sql`.

### What's already in the workflow
```
Area Analysis (v2 wizard, 5 stages) → Financial Analysis → Best Outcome
                                              ↑
                       NEW module goes here  ─┘
```

- **Area Analysis v2** (just shipped): 5-stage schema-driven wizard, writes locked statements to `area_statements` + child tables. Covers 4 regulations (33(5), 33(10), 33(11), 33(20)(B)).
- **Financial Analysis**: 1 statement = 1 row in `area_statement_financials` with revenue/cost/IRR/NP. Already has `area_statement_line_items` child table (categories: `site_cost`, `premium`, `permit_fees` …).
- **Best Outcome**: ranks statements by verdict (STRONG GO / GO / CAUTION / NO-GO / INCOMPLETE).

### Project data already available (do not duplicate)

Area Analysis has already captured (per statement):

| Already stored | Where | Reuse for |
|---|---|---|
| Project name, CTS, address, city, region, ward | `area_statements` | Prefill, display |
| Planning authority (MCGM/SRA/MHADA/MMRDA/Collector) | `area_statements.planning_authority` | **Trigger** for authority-specific approvals |
| Development category (sra/mhada/redevelopment/society/land_development/cluster) | `area_statements.development_type` | **Applicability engine** |
| Island City flag | `draft_json.v2_stage1.is_island_city` | Trigger (ancient monument, CRZ) |
| Zone (DP) | `draft_json.v2_stage1.zone_dp` | — |
| Selected DCPR regulation | `area_statements.selected_regulation_key` | **Applicability engine** |
| Gross plot area · net plot · road width | `area_statement_land` + `draft_json.step_2` | **Cost formulas** (per-sqm charges) |
| Total BUA · Rehab BUA · Sale BUA · FSI | `area_statements.{total_permissible_bua_sqm, rehab_bua_sqm, sale_bua_sqm, total_permissible_fsi}` | **Cost formulas** (BUA-based) |
| Existing building roster (tenants, carpet, BUA) | `existing_buildings` | — |
| Line items (site cost, premium, permit fees) | `area_statement_line_items` | Interface target — approval totals must flow here |

### Conventions to follow

- Views in `views/<module>/*.php` + a `_shell.php` for shared chrome.
- Schema-driven forms via `SchemaFormRenderer` (already built for Stage 3) — reusable for authority / fee-schedule forms.
- Permission guards (`requirePermission('edit_proposals')`) on save endpoints; read endpoints only `requireLogin()`.
- No non-additive schema changes. New columns only. Migration numbered `v20+`.
- Flash messages via `flashSet()` for all success/error.
- JetBrains Mono for numbers; Lexend for body.

---

## §2. Reconciliation report — all 121 source entries

Columns:
- **Src.** = source identifier (`A1` = Source A sheet 1 row N, `A2` = sheet 2, `B` = Source B)
- **Original name** — preserved verbatim
- **Proposed master name** — standardized target name (may be shared across sources)
- **Type** — see §3 record types
- **Phase** — Pre-construction (Pre) · During · Post / Occupancy (Post) · Land-acquisition (Land)
- **Authority** — if known from source or widely known; otherwise `—` (do not fabricate)
- **Status** — new · duplicate (of another src) · related (overlapping but distinct) · conflict (same name but conflicting fact)

### 2.1 Source A sheet 1 — Building Development NOCs (72 entries)

| Src. | Original name | Proposed master name | Type | Phase | Authority (source) | Status |
|---|---|---|---|---|---|---|
| A1-1 | IOD/IOA | Intimation of Disapproval (IOD/IOA) | Statutory Approval | Pre | — | **duplicate of B-4** |
| A1-2 | LOI | Letter of Intent (LOI) | Statutory Approval | Pre | — | new |
| A1-3 | Plinth CC/Further CC/Revalidation/Full CC | Commencement Certificate (CC — all stages) | Statutory Approval | Pre→During | — | **related to B-17** (B is the umbrella CC) |
| A1-4 | Amended IOD/IOA | Amended IOD/IOA | Statutory Approval | Pre→During | — | new |
| A1-5 | Part OC | Part Occupancy Certificate | Statutory Approval | Post | — | **related to B-25** |
| A1-6 | Occupation Certificate | Occupation / Occupancy Certificate | Statutory Approval | Post | — | **duplicate of B-25** |
| A1-7 | Storm Water Drainage / Storm Water NOC | Storm Water & Drain NOC | NOC / Clearance | Pre | — | **duplicate of B-8** |
| A1-8 | Hydraulic Engineer NOC | Hydraulic Engineer NOC | NOC / Clearance | Pre | — | new |
| A1-9 | Water Connection / Water Supply NOC | Water Supply NOC | NOC / Clearance | Pre | — | **related to B-28** (B-28 is the permanent connection) |
| A1-10 | Sewerage Remarks / Sewerage NOC | Sewerage NOC | NOC / Clearance | Pre | — | **duplicate of B-9** |
| A1-11 | Plot Boundary Demarcation (Subdivision/Amalgamation/NA) | Plot Boundary Demarcation | Technical Report | Pre | — | new |
| A1-12 | Chief Fire Office (CFO) NOC | Fire Clearance (CFO) | NOC / Clearance | Pre | — | **duplicate of B-12** |
| A1-13 | MHADA NOC | MHADA NOC | NOC / Clearance | Pre | MHADA | new |
| A1-14 | Civil Aviation (AAI) NOC | Airport Authority (AAI) NOC | NOC / Clearance | Pre | — | **duplicate of B-16** |
| A1-15 | Rain Water Harvesting (RWH) | Rain Water Harvesting NOC | NOC / Clearance | Pre | — | new |
| A1-16 | Road Level Formation | Road Level Formation | Technical Report | Pre | — | new |
| A1-17 | MOEF/EC | Environment Clearance (MoEF / SEIAA) | Statutory Approval | Pre | — | **duplicate of B-13** |
| A1-18 | Metro / Monorail NOC | Metro / Monorail NOC | NOC / Clearance | Pre | — | new |
| A1-19 | Tree NOC | Tree Authority NOC | NOC / Clearance | Pre | — | **duplicate of B-7** |
| A1-20 | Pest Control Officer (PCO NOC) | Pest Control Officer NOC | NOC / Clearance | Pre | — | new |
| A1-21 | Assessment & Collector NOC | Assessment & Collector NOC | NOC / Clearance | Pre | — | new |
| A1-22 | Categary Certificate | Category Certificate | Compliance / Registration | Pre | — | new (typo preserved) |
| A1-23 | Property Tax Demand Register | Property Tax Demand Register | Land Record | Land | — | new |
| A1-24 | Inspection Extract/Annexure-A | Inspection Extract (Annexure-A) | Land Record | Land | — | new |
| A1-25 | Internal layout roads/Street Lights/Set back/D.P. Road | Internal Layout Roads / Street Lights / DP Road | Technical Report | Pre | — | new |
| A1-26 | EETC | EETC (Electrical Energy Transmission Certificate) | Compliance / Registration | During | — | new (abbreviation un-expanded in source) |
| A1-27 | Mechanical & Electrical works Ventilation | Mechanical & Electrical Ventilation | Technical Report | During | — | new |
| A1-28 | HVAC | HVAC Design Approval | Technical Report | During | — | new |
| A1-29 | Geotechnical Report | Geotechnical Report | Technical Report | Pre | — | new |
| A1-30 | Site Elevation Report | Site Elevation Report | Technical Report | Pre | — | new |
| A1-31 | MCZMA / Coastal Regulation Zone (CRZ) NOC | Coastal Regulation Zone (CRZ) NOC | NOC / Clearance | Pre | — | **duplicate of B-19** |
| A1-32 | Railway NOC | Railway NOC | NOC / Clearance | Pre | — | new |
| A1-33 | Police NOC | Police NOC | NOC / Clearance | Pre | — | new |
| A1-34 | Estate NOC | Estate NOC | NOC / Clearance | Pre | — | new |
| A1-35 | Jail NOC | Jail NOC | NOC / Clearance | Pre | — | new |
| A1-36 | Defence Department | Defence Department NOC | NOC / Clearance | Pre | — | new |
| A1-37 | Forest & Revenue Department | Forest & Revenue NOC | NOC / Clearance | Pre | — | new |
| A1-38 | National Green Tribunal (NGT) | NGT Clearance | Statutory Approval | Pre | — | new |
| A1-39 | Labour Camp NOC | Labour Camp NOC | NOC / Clearance | During | — | new |
| A1-40 | Temaporary Shed NOC | Temporary Shed NOC | NOC / Clearance | During | — | new (typo preserved) |
| A1-41 | Sale Office NOC | Sale Office NOC | NOC / Clearance | During | — | new |
| A1-42 | Layout Approval | Building Layout Approval | Statutory Approval | Pre | — | **duplicate of B-3** |
| A1-43 | AE Survey Remarks | AE Survey Remarks | Technical Report | Pre | — | new |
| A1-44 | Mechnaical & Engineering | Mechanical & Engineering Clearance | Technical Report | During | — | new (typo preserved) |
| A1-45 | Solid Waste Management (SWM) / C&D Waste NOC | Solid Waste Management NOC | NOC / Clearance | During | — | new |
| A1-46 | High Rise Committee | High Rise Committee Clearance | Statutory Approval | Pre | — | new |
| A1-47 | Electric Power / Power Supply NOC | Electric Supply NOC | NOC / Clearance | Pre | — | **related to B-24 (Substation) and B-27 (Permanent Power)** |
| A1-48 | DP Remark | Development Plan Remark | Land Record | Land | — | new |
| A1-49 | CTS Plan (True Extract) | CTS Plan (True Extract) | Land Record | Land | — | **related to A2-6** |
| A1-50 | Google Map | Google Map Extract | Supporting Document | Pre | — | new (informal) |
| A1-51 | Total Survey Station | Total Station Survey | Technical Report | Pre | — | new |
| A1-52 | Status Of Road | Road Status Certificate | Land Record | Land | — | new |
| A1-53 | TP Remarks | Town Planning Remarks | Land Record | Land | — | new |
| A1-54 | Storage Watar Tank NOC | Storage Water Tank NOC | NOC / Clearance | Pre | — | new (typo preserved) |
| A1-55 | Down Take NOC | Down Take NOC | NOC / Clearance | During | — | new |
| A1-56 | Labour Insurance Policy | Labour Insurance Policy | Compliance / Registration | During | — | new |
| A1-57 | MTDC NOC | MTDC NOC | NOC / Clearance | Pre | MTDC | new |
| A1-58 | MMRDA NOC | MMRDA NOC | NOC / Clearance | Pre | MMRDA | new |
| A1-59 | MIDC NOC | MIDC NOC | NOC / Clearance | Pre | MIDC | new |
| A1-60 | Regular Line Remarks | Regular Line Remarks | Land Record | Land | — | new |
| A1-61 | Borewell Permission | Borewell Permission | Statutory Approval | During | — | **related to B-18 (outside municipal limit)** |
| A1-62 | Soil Investigation Report | Soil Investigation Report | Technical Report | Pre | — | new |
| A1-63 | Hoarding Permission | Hoarding Permission | Statutory Approval | During | — | new |
| A1-64 | Heritage NOC | Heritage Conservation NOC | NOC / Clearance | Pre | — | **related to B-14 (ancient monument)** |
| A1-65 | Lift Approval / PWD Lift / Lift Certificates | Lift / Escalator Installation Approval | Statutory Approval | During | — | **duplicate of B-23** |
| A1-66 | RERA | RERA Registration | Compliance / Registration | Pre | — | new |
| A1-67 | Approved Building Plan | Approved Building Plan | Supporting Document | Pre | — | new |
| A1-68 | Nallah Remarks | Nallah Remarks | Land Record | Land | — | new |
| A1-69 | Highway NOC (if required) | Highway / Road Access NOC | NOC / Clearance | Pre | — | **duplicate of B-22** |
| A1-70 | Monitoring & Evaluation Certificate | Monitoring & Evaluation Certificate | Compliance / Registration | During | — | new |
| A1-71 | High-Rise Permission (Electrical Inspector) | High-Rise Electrical Inspector Permission | Statutory Approval | During | — | new |
| A1-72 | CFO Completion Certificate | CFO Completion Certificate | Completion Milestone | Post | — | new |

### 2.2 Source A sheet 2 — Land Acquisition Documents (21 entries)

| Src. | Original name | Proposed master name | Type | Phase | Status |
|---|---|---|---|---|---|
| A2-1 | 8A | 8-A Extract | Land Record | Land | new |
| A2-2 | 7/12 Extract | 7/12 Extract | Land Record | Land | new |
| A2-3 | Property Card | Property Card | Land Record | Land | **related to B-2** |
| A2-4 | Form 9 | Form 9 | Land Record | Land | new |
| A2-5 | 6 D (Ferfar-Mutation Entry) | 6-D Mutation Entry | Land Record | Land | new |
| A2-6 | CTS Plan/CS Plan/True Extract | CTS / CS Plan (True Extract) | Land Record | Land | **related to A1-49** |
| A2-7 | Gutbook/Sudbook | Gutbook / Sudbook | Land Record | Land | new |
| A2-8 | Inquiry Register (Chowkashi nondwahi) | Inquiry Register | Land Record | Land | new |
| A2-9 | KJP | KJP (Kami Jasti Patra) | Land Record | Land | new |
| A2-10 | Layout Plan | Approved Layout Plan | Supporting Document | Land | new |
| A2-11 | Amalgamation/Subdivision Order | Amalgamation / Subdivision Order | Statutory Approval | Land | new |
| A2-12 | NA Order/Sanad/Conversion Tax | Non-Agriculture Order / Sanad | Statutory Approval | Land | **duplicate of B-6** |
| A2-13 | TILR/MR Plan | TILR / MR Plan | Land Record | Land | new |
| A2-14 | Village Map | Village Map | Land Record | Land | new |
| A2-15 | Tikka Sheet | Tikka Sheet | Land Record | Land | new |
| A2-16 | Estate Plan | Estate Plan | Land Record | Land | new |
| A2-17 | Akarfod/Akarband | Akarfod / Akarband | Land Record | Land | new |
| A2-18 | ULC Order | Urban Land Ceiling Order | Statutory Approval | Land | new |
| A2-19 | Excavation Permission | Excavation Permission | Statutory Approval | During | **duplicate of B-20** |
| A2-20 | Royalty Permission | Royalty Permission | Statutory Approval | During | new |
| A2-21 | Encumbrances Clearance | Encumbrances Clearance | Land Record | Land | new |

### 2.3 Source B — Checklist (28 entries) with source-reported cost + time

| Src. | Original name | Source-reported authority | Source-reported duration | Source-reported cost | Status |
|---|---|---|---|---|---|
| B-2 | Ownership Certificate/Extract | Revenue Department | 15 Days | Rs. 50 per CTS No. / PR Card | **related to A2-3** |
| B-3 | Building Layout Approval | Development Authority/Municipality | 1 Month | Rs 28-56 per sqm | **duplicate of A1-42** |
| B-4 | Intimation of Disapproval (IOD) | BMC Building Proposal Office | 30-45 Days | Rs 33-66 per sqm + Dev. Charges Rs 350-700 | **duplicate of A1-1** |
| B-5 | Structural Plans Submission | BMC (Copy submission) | Parallel | — | new |
| B-6 | Non-Agriculture Permission | Revenue Department | 3+ Months | Rs 2-8 per sqft | **duplicate of A2-12** |
| B-7 | Tree Authority NOC | Tree Authority Committee | 30-60 Days | INR 4,000 per tree | **duplicate of A1-19** |
| B-8 | Storm Water & Drain NOC | Municipal | 15-30 Days | Nominal fee (INR 2/sqm) | **duplicate of A1-7** |
| B-9 | Sewerage NOC | Municipal | 15-30 Days | — | **duplicate of A1-10** |
| B-10 | Electric Department NOC | BEST | 15-30 Days | — | **related to A1-47** |
| B-11 | Traffic & Coordination NOC | Municipal | 30 Days | — | new |
| B-12 | Fire Clearance (CFO) | Chief Fire Officer | 30 Days | Rs 50/sqm (Approx Rs 50,000-1,00,000) | **duplicate of A1-12** |
| B-13 | Environment Clearance | MoEF / SEIAA | 3 Months - 1 Year | Depends on project size | **duplicate of A1-17** |
| B-14 | Ancient Monument Approval | Archaeological Survey of India | 6 Months | — | **related to A1-64** |
| B-15 | Consent to Establish & Operate | Pollution Control Board | 2 Months | — | new |
| B-16 | Airport Authority NOC | Civil Aviation Dept. | 3-4 Months | — | **duplicate of A1-14** |
| B-17 | Commencement Certificate (CC) | BMC Building Proposal Office | 15-30 Days | Rs 200 (Land Component) & 500 per sqm (Building Component) | **duplicate of A1-3** |
| B-18 | Bore Well Registration (Outside Municipal Limit) | Central Ground Water Authority | 60 Days | — | **related to A1-61** |
| B-19 | Coastal Zone NOC | Coastal Zone Management Authority | 6-12 Months | — | **duplicate of A1-31** |
| B-20 | Excavation Permission | Collector / Mines Dept. | 15-30 Days | Royalty Payment | **duplicate of A2-19** |
| B-21 | Other Infra Approvals (Water/Electricity/Telecom) | Respective Providers | 30 Days | — | new (umbrella; relates to A1-9, A1-47, utilities) |
| B-22 | Road Access / Highway NOC | NHAI/PWD | 60 Days | — | **duplicate of A1-69** |
| B-23 | Lift / Escalator Installation | PWD/CPWD | 30-45 Days | — | **duplicate of A1-65** |
| B-24 | Electric Substation NOC | Electricity Distribution Authority | 15 Days | — | **related to A1-47** |
| B-25 | Occupancy Certificate | BMC | 60 Days | — | **duplicate of A1-6** (and related to A1-5 Part OC) |
| B-26 | Building Completion Certificate | BMC | 30 Days | — | new (related to A1-72) |
| B-27 | Permanent Power Connection | BEST | 30 Days | Rs 50 + Demand Charges | **related to A1-47** |
| B-28 | Permanent Water Connection | Municipal | 45 Days | Rs 1,200 | **related to A1-9** |
| B-29 | Permanent Sewerage Connection | Municipal | 30 Days | Rs 50,000 | **related to A1-10** |

### 2.4 Reconciliation summary

| Status | Count | Meaning |
|---|---|---|
| Duplicates (same approval named in both sources) | **15 pairs** | Merge into one master record, keep both source aliases + original rows |
| Related (overlapping but distinct) | **~12 pairs** | Keep as separate master records with cross-references; e.g. Part OC vs OC vs Building Completion Certificate are three distinct milestones |
| Unique to A sheet 1 | ~48 | New master records |
| Unique to A sheet 2 | ~17 | New master records (mostly Land Records) |
| Unique to B | ~4 | New master records (Structural Plans Submission, Traffic & Coord NOC, Consent to Establish, Electric Substation NOC) |
| **Proposed deduplicated master count** | **≈ 97** | **All 121 originals mapped — none dropped**; 15 duplicates folded, 24 "related" pairs kept distinct |

---

## §3. Proposed categories and record types

### Record types (8, per spec §4)

| Code | Record type | Example |
|---|---|---|
| STATUTORY | Statutory Approval / Permission | IOD, CC, OC, NA Order, ULC Order |
| NOC | NOC / Clearance | CFO, CRZ, Tree, AAI, PCB |
| LAND_RECORD | Land Record / Supporting Document | 7/12, Property Card, CTS Plan, Form 9 |
| TECH_REPORT | Technical Report / Certificate | Geotechnical, Soil Investigation, Total Station Survey |
| MILESTONE | Construction Milestone / Completion | Plinth CC, Full CC, Part OC, Building Completion Certificate |
| REGISTRATION | Compliance / Registration | RERA, Labour Insurance, Category Certificate |
| UTILITY | Utility Connection / Service | Permanent Power/Water/Sewerage connections |
| OTHER | Other / Requires Classification | catch-all; nothing is force-fit |

### Categories (configurable, phased)

| Category | Phase | Owner / Lead |
|---|---|---|
| Land & Title | Land | Legal / Acquisition |
| Planning & DP | Pre-construction | Architect / Liaison |
| Municipal Permissions | Pre-construction | Liaison |
| Environmental | Pre-construction | Env consultant |
| Fire Safety | Pre-construction | Architect |
| Location-specific NOCs (AAI, CRZ, Railway, Metro, Heritage, Defence, Jail, Police, Forest) | Pre-construction | Liaison |
| Demolition & Site Prep | Pre-construction | Site team |
| Construction-stage Inspections | During | Site team |
| Completion & Occupancy | Post | Architect / Liaison |
| Registrations (RERA, Insurance, Labour) | Pre→Post | Legal |
| Technical Reports | Pre → During | Technical team |
| Utilities | Pre → Post | MEP consultant |

**Rule:** a record's `record_type` is independent of its `financial_treatment` (fixed fee / per-sqm / per-sqft / per-tree / professional-fee / deposit / unknown). The brief's §4 point is honored — a single approval may bundle multiple fee components (e.g. IOD = per-sqm charge + Dev Charges + liaison fees). Modelled as 1-to-many `cost_components`.

---

## §4. Duplicates, conflicts, and unresolved information

### 4.1 Exact duplicates to merge (15 pairs → preserve both source aliases)

| Master name | Sources | Resolution |
|---|---|---|
| Intimation of Disapproval (IOD/IOA) | A1-1, B-4 | Merge; B supplies cost (Rs 33-66/sqm + Dev Rs 350-700) + duration (30-45d) as source-reported |
| Building Layout Approval | A1-42, B-3 | Merge; B supplies Rs 28-56/sqm + 1 month |
| Non-Agriculture Permission | A2-12, B-6 | Merge; B supplies Rs 2-8/sqft + 3+ months |
| Tree Authority NOC | A1-19, B-7 | Merge; B supplies INR 4,000/tree + 30-60d |
| Storm Water & Drain NOC | A1-7, B-8 | Merge; B supplies INR 2/sqm nominal + 15-30d |
| Sewerage NOC | A1-10, B-9 | Merge; B supplies 15-30d, no cost |
| Fire Clearance (CFO) | A1-12, B-12 | Merge; B supplies Rs 50/sqm + 30d |
| Environment Clearance | A1-17, B-13 | Merge; B supplies "3 months – 1 year", cost "depends on project size" |
| Airport Authority NOC | A1-14, B-16 | Merge; B supplies 3-4 months |
| Commencement Certificate (CC — umbrella) | A1-3, B-17 | Merge; keep A1-3's granular milestones (Plinth / Further / Full) as sub-stages |
| Coastal Regulation Zone NOC | A1-31, B-19 | Merge; B supplies 6-12 months |
| Excavation Permission | A2-19, B-20 | Merge; B supplies "royalty payment" + 15-30d |
| Highway NOC | A1-69, B-22 | Merge; B supplies 60d |
| Lift / Escalator Installation | A1-65, B-23 | Merge; B supplies 30-45d |
| Occupation / Occupancy Certificate | A1-6, B-25 | Merge; B supplies 60d; distinct from A1-5 (Part OC) |

### 4.2 Related — kept as distinct records with cross-references

| Master records | Reason |
|---|---|
| Property Card (A2-3) ↔ Ownership Certificate/Extract (B-2) | B is a broader description; Property Card is the specific document. Keep both; B's Rs 50/CTS fee attaches to the PR Card retrieval. |
| CTS Plan — A1-49 ↔ CTS/CS Plan (A2-6) | Same document, cross-sheet. **Merge into one master**, both aliases retained. |
| Part OC (A1-5) · Full OC (A1-6/B-25) · Building Completion Certificate (B-26) · CFO Completion Certificate (A1-72) | Four distinct milestones with distinct timing. **Keep all four.** |
| Water Supply NOC (A1-9) ↔ Permanent Water Connection (B-28) | NOC is upfront, connection is utility post-OC. Keep both. |
| Sewerage NOC (A1-10) ↔ Permanent Sewerage Connection (B-29) | Same split. Keep both. |
| Electric Supply NOC (A1-47) ↔ Electric Substation NOC (B-24) ↔ Permanent Power Connection (B-27) ↔ Electric Dept NOC (B-10) | Four related items. Keep distinct — different authorities / phases. |
| Borewell Permission (A1-61) ↔ Bore Well Registration (B-18, outside municipal limit) | Keep both — different jurisdictions. |
| Heritage NOC (A1-64) ↔ Ancient Monument Approval (B-14, ASI) | Keep both — different authorities (local heritage conservation vs ASI). |
| Layout Plan (A2-10) ↔ Building Layout Approval (B-3 / A1-42) | Layout Plan is the artefact, Approval is the decision. Keep both. |

### 4.3 Cost / timeline conflicts to preserve (never auto-reconcile)

The spec §9 is explicit: do not treat Source B's indicative figures as official. Both figures stay with their source references. When a cost component is created on a master record, it declares its **confidence**:
- `verified_official` — gazetted / authority-published fee schedule (require URL + effective date)
- `source_reported` — pasted from Source B's "indicative" column (default for all B costs)
- `consultant_estimate` / `historical_estimate` / `manual_estimate` / `pending_verification`

**No single-value reconciliation is forced** — an IOD cost shows up in the project register as "Rs 33-66 per sqm (Source B, indicative)" until a user supplies a verified rate. Range values stay as ranges.

### 4.4 Unresolved / needs classification

| Entry | Issue |
|---|---|
| A1-26 EETC | Abbreviation un-expanded in source. Needs user clarification before classification confirmed. |
| A1-50 Google Map | Informal; keep as Supporting Document. |
| A1-70 Monitoring & Evaluation Certificate | Context unclear (which authority, which phase). |
| A1-30 Site Elevation Report vs A1-29 Geotechnical vs A1-62 Soil Investigation | Possible subset overlap; keep distinct until confirmed. |
| B-21 Other Infra Approvals (Water/Electricity/Telecom) | Umbrella / informal — split into 3 or 4 master records tied to actual utility NOCs? Flag for user. |
| A1-56 Labour Insurance Policy | Compliance, not an approval — tagged `REGISTRATION`. |

---

## §5. Proposed database structure

### 5.1 Overview of new tables

Prefix new tables with `approval_` to avoid any clash with existing schema.

```
┌─ Master library (shared across all projects) ───────────────────────────────┐
│  approval_master                     — standardized approval definitions    │
│  approval_master_alias               — original source names (preserved)    │
│  approval_master_document_req        — required document list              │
│  approval_master_dependency          — prerequisite master → master         │
│  approval_master_applicability_rule  — "when X then applies" rules          │
│  approval_master_cost_component      — fee components (fixed, per-sqm, …)   │
│  approval_master_duration            — source-reported durations            │
│  approval_authority                  — authorities registry                 │
│  approval_regulation_ref             — regulations / notifications          │
│  approval_source_import              — audit of source-workbook imports     │
└─────────────────────────────────────────────────────────────────────────────┘

┌─ Per-project register (per area_statement) ─────────────────────────────────┐
│  project_approval                    — one row per approval on a project    │
│  project_approval_cost               — per-component estimated/actual costs │
│  project_approval_deposit            — refundable deposits (separate)       │
│  project_approval_timeline           — planned/actual dates per step        │
│  project_approval_dependency         — in-project overrides                 │
│  project_approval_document           — uploaded supporting documents        │
│  project_approval_followup           — follow-up history + queries          │
│  project_approval_history            — chronological audit log              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Key tables (abbreviated — full DDL in migration v20)

**`approval_master`** — the deduplicated set of ~97 standardized records
```
id, code (slug), standardized_name, record_type, category_key,
description, authority_id (nullable), typical_phase,
is_active, created_by, created_at, updated_at
```

**`approval_master_alias`** — every original source name preserved
```
id, master_id, alias, source_workbook ('A' | 'B'),
source_sheet, source_row, is_primary (first-seen flag)
```

**`approval_master_applicability_rule`** — the applicability engine's input (configurable, not code)
```
id, master_id, rule_type ('mandatory' | 'conditional' | 'not_applicable'),
condition_json (expression like {"planning_authority":"MHADA"} or
                                {"is_island_city":true, "zone_dp":"Coastal"}),
rationale_template, verified_by, verified_at
```

**`approval_master_cost_component`** — fee breakdown per master record
```
id, master_id, label (e.g. "Development Charge"),
method ('fixed' | 'per_sqm_plot' | 'per_sqm_bua' | 'per_sqft' | 'per_tree' |
        'percent_of_x' | 'range' | 'deposit' | 'unknown'),
rate_numeric, rate_range_low, rate_range_high, unit, formula_text,
confidence ('verified_official' | 'source_reported' | 'consultant_estimate' |
            'historical_estimate' | 'manual_estimate' | 'pending_verification'),
is_refundable (0/1), source_ref, effective_from, last_verified_at
```

**`project_approval`** — per-project register
```
id, statement_id (FK area_statements), master_id (FK approval_master),
applicability ('mandatory' | 'conditional' | 'not_applicable' | 'requires_verification'),
applicability_rationale, user_override (0/1), override_reason,
project_phase, criticality ('critical' | 'high' | 'medium' | 'low'),
status ('not_started' | 'docs_pending' | 'ready_to_apply' | 'applied' |
        'under_review' | 'query_raised' | 'approved' | 'rejected' |
        'on_hold' | 'not_applicable' | 'expired'),
authority_id, responsible_user_id, next_action,
application_reference, submitted_at, decision_target_at, decided_at,
validity_until, remarks
```

**`project_approval_cost`** — per-component numbers (both estimated and actual)
```
id, project_approval_id, master_cost_component_id (nullable for custom),
label, method, inputs_json, rate_used, estimated_amount, actual_amount,
confidence, is_refundable, locked_at (locks estimate once approved)
```

**`project_approval_deposit`** — refundable amounts
```
id, project_approval_id, label, deposit_paid, refund_received,
adjusted_against_other_charges, outstanding_amount, status
```

**`project_approval_timeline`** — timeline fields separated
```
id, project_approval_id,
planned_start_at, expected_apply_at, actual_submitted_at,
target_decision_at, actual_decided_at,
delay_days, delay_reason,
followup_next_at, expiry_at, renewal_due_at
```

**`approval_source_import`** — provenance + reimport-safe
```
id, source_workbook, source_sheet, source_row, original_name,
imported_at, imported_by, mapped_master_id, mapping_decision
```

### 5.3 Non-destructive extensions to existing tables

Only two columns needed on existing tables:

```
ALTER TABLE area_statements
  ADD COLUMN approval_phase_started_at TIMESTAMP NULL,
  ADD COLUMN approval_summary_json LONGTEXT NULL;  -- cached rollup for Fin Analysis
```

No existing columns dropped. No existing data mutated.

---

## §6. Proposed user workflow

### 6.1 Where it sits in the sidebar

The current "Feasibility Workflow" section becomes 4 numbered steps:

```
Feasibility Workflow
 ① Area Analysis         (unchanged)
 ② Approval Costs        ← NEW
 ③ Financial Analysis    (receives totals from ②)
 ④ Best Outcome          (unchanged)
```

The v2 Area Analysis's "Stage 5 → Next" tiles get a new "② Approval Costs" tile.

### 6.2 Approval Costs internal flow (5 steps, non-wizard — matches brief's dynamic-table emphasis)

```
┌───────────────────────────────────────────────────────────────────────┐
│ Step 1 — Register landing (per statement_id)                          │
│   Pick a locked Area Statement → "Build approval register"            │
│   Table shows: "Suggested from master library" count · "Added" · etc.│
└──────────────────────┬────────────────────────────────────────────────┘
                       ▼
┌───────────────────────────────────────────────────────────────────────┐
│ Step 2 — Applicability review                                         │
│   Rules engine runs against area_statements facts                     │
│   Each master record → 'mandatory' | 'conditional' | 'not_applicable' │
│                        | 'requires_verification'                      │
│   User reviews, confirms or overrides with rationale                  │
└──────────────────────┬────────────────────────────────────────────────┘
                       ▼
┌───────────────────────────────────────────────────────────────────────┐
│ Step 3 — Main Checklist Table (the primary workspace)                 │
│   Columns per §8 of the brief. Grouping, filtering, sort, inline edit │
│   Click a row → side panel with full detail (docs, timeline, costs)   │
│   Add custom (non-source) requirements here too                       │
└──────────────────────┬────────────────────────────────────────────────┘
                       ▼
┌───────────────────────────────────────────────────────────────────────┐
│ Step 4 — Cost estimation panel                                        │
│   Per approval: configurable components, formulas, confidence         │
│   Automatic calc where inputs available (plot area, BUA from Area)    │
│   Deposits tracked separately                                         │
│   Rollup totals: non-refundable / refundable / cash requirement       │
└──────────────────────┬────────────────────────────────────────────────┘
                       ▼
┌───────────────────────────────────────────────────────────────────────┐
│ Step 5 — Critical Approval Tracker + Dashboard                        │
│   Critical items · blockers · upcoming deadlines · overdue            │
│   Phase-wise cost summary                                             │
│   "Push to Financial Analysis" action → materializes line items       │
└───────────────────────────────────────────────────────────────────────┘
```

The dashboard sits alongside the main table (not after it), always visible as a right-rail panel on the main page.

---

## §7. Applicability, Cost, and Timeline design

### 7.1 Applicability engine

**Deterministic, rule-based** — same philosophy as `RegulationSuggester`. No AI, no LLM. Rules live in `approval_master_applicability_rule` and are configurable without a code change.

**Project context** passed to the engine (reused from Area Analysis, zero duplication):
```json
{
  "statement_id": 41,
  "planning_authority": "SRA",
  "development_type": "sra",
  "selected_regulation_key": "33(10)",
  "is_island_city": false,
  "zone_dp": "Residential",
  "ward": "N",
  "gross_plot_area_sqm": 64219.67,
  "net_plot_area_sqm": 53224.50,
  "primary_road_width_m": 27.45,
  "total_permissible_bua_sqm": 299882.38,
  "sale_bua_sqm": 183914.12,
  "has_existing_construction": true,
  "approx_tenant_count": 3617
}
```

**Rule shape** (JSON condition evaluated server-side; no `eval`):
```json
{
  "all": [
    { "field": "is_island_city", "op": "eq", "value": true },
    { "field": "zone_dp", "op": "in", "value": ["Coastal", "No-development"] }
  ],
  "rationale": "Coastal zone in Island City → CRZ NOC applies."
}
```

Operators: `eq`, `neq`, `in`, `not_in`, `gte`, `lte`, `exists`, `and`, `or`, `not`.

**Example seed rules:**
- MHADA NOC → `mandatory` when `planning_authority = 'MHADA'` OR `development_type = 'mhada'`.
- Airport Authority NOC → `requires_verification` when `ward ∈ ('K/E','K/W','H/E','H/W')` (near Mumbai airport).
- CRZ NOC → `conditional` when `zone_dp ∈ ('Coastal')` OR address contains coastal keywords.
- Heritage NOC → `requires_verification` when `is_island_city = true` (Grade I/II buildings often in Island City).
- Tree Authority NOC → `conditional` when `gross_plot_area_sqm > 1000` (likely trees on site).
- Env Clearance (MoEF/SEIAA) → `mandatory` when `total_permissible_bua_sqm > 20000`; `conditional` 5000-20000.
- RERA → `mandatory` always (every real-estate project in Maharashtra).
- High-rise Permissions → `conditional` when `total_permissible_bua_sqm / gross_plot_area_sqm > 2.5` or building height proxy.

**Change tracking.** When upstream Area Analysis values change (e.g. BUA changes after a Refresh Calc), engine re-evaluates: user sees a diff panel ("Env Clearance now mandatory because BUA ≥ 20,000 sqm — review and confirm"). Already-confirmed decisions never silently overwritten; previous confirmation + reason retained.

### 7.2 Cost estimation design

**Three-layer model:**

1. **Master cost component** — the template (`approval_master_cost_component`). E.g. IOD = `[Dev Charge: Rs 350-700 range, source_reported]` + `[Per-sqm Fee: Rs 33-66/sqm, source_reported]`.
2. **Project cost component** — instance (`project_approval_cost`) with concrete inputs (BUA from Area, chosen rate from range).
3. **Rollup** — `approval_summary_json` on `area_statements` with totals by category/confidence/refundability.

**Supported calculation methods** (fully modelled, no inventing of rates):

| Method | Needs | Example |
|---|---|---|
| `fixed` | rate_numeric | Rs 50 per CTS extract |
| `range` | rate_range_low, rate_range_high | Rs 28-56/sqm |
| `per_sqm_plot` | rate × gross_plot_area_sqm | — |
| `per_sqm_bua` | rate × total_permissible_bua_sqm | — |
| `per_sqft_plot` / `per_sqft_bua` | sqft conversion applied automatically | — |
| `per_tree` | rate × tree_count (user input per project) | Rs 4,000/tree |
| `percent_of` | % × reference amount | — |
| `deposit` | separate flag `is_refundable=1` | — |
| `unknown` | shows "needs verification" chip, estimate blank | — |

**Confidence chips** render on every cost row (same visual language as the In-Situ auto-max / user-set chips just shipped):
- 🟢 `VERIFIED OFFICIAL` (green)
- 🟡 `SOURCE REPORTED` (amber) — all Source B figures default here
- 🔵 `CONSULTANT ESTIMATE`
- ⚪ `HISTORICAL`
- ⚫ `MANUAL`
- 🔴 `PENDING VERIFICATION`

**No automatic rate selection from a range.** If the master says "Rs 33-66/sqm", the project record shows both ends until a user picks one with a reason.

### 7.3 Timeline design

Each `project_approval_timeline` row carries 4 separate duration figures:

| Field | Source |
|---|---|
| `source_reported_duration` | from Source B (range preserved as a text + numeric_low/numeric_high) |
| `internal_prep_duration_days` | user-set (consultant estimate) |
| `authority_processing_duration_days` | user-set (based on experience) |
| `expected_elapsed_days` | computed via dependency graph (critical path, parallel-aware) |
| `actual_elapsed_days` | from actual_submitted_at → actual_decided_at |

**Dependency aware.** `approval_master_dependency` + project override lets us compute critical path. If A depends on B, A's `expected_start_at ≥ B's expected_decided_at`. Parallel approvals (e.g. multiple NOCs applied in parallel) do not stack sequentially.

**Nothing is a deadline until the user commits to one.** The brief says: "Do not convert a source-reported duration into a guaranteed deadline." Our fields separate `source_reported_duration` (display-only, shown as a range) from `target_decision_at` (user-set date). Never auto-derive the second from the first.

---

## §8. Integration plan

### 8.1 Area Analysis → Approval Costs (upstream read)

- Read-only consumption from `area_statements` + `area_statement_land` + `area_statement_calcs` + `existing_buildings`.
- Prefills: project name, CTS, authority, dev category, Island City, ward, zone (DP), gross/net plot, road width, Total BUA, Rehab BUA, Sale BUA, tenant count.
- Reactive: if Area Analysis Refresh Calc runs, Approval Costs shows a diff banner on its landing — doesn't silently recompute.
- No columns added to Area Analysis tables except the two in §5.3.

### 8.2 Approval Costs → Financial Analysis (downstream push)

Two integration surfaces:

**(a) Direct read interface** — `area_statements.approval_summary_json` carries a canonical rollup:
```json
{
  "non_refundable_total_inr": 12750000,
  "refundable_deposits_total_inr": 450000,
  "cash_requirement_total_inr": 13200000,
  "by_phase": {
    "pre": 8900000, "during": 2850000, "post": 1000000
  },
  "by_category": {
    "statutory": 7200000, "noc": 2100000, "utilities": 850000, …
  },
  "by_confidence": {
    "verified_official": 3200000, "source_reported": 6700000, …
  },
  "unverified_count": 11,
  "last_pushed_at": "2026-10-24 11:30"
}
```

**(b) Line-item materialization** — "Push to Financial Analysis" action creates rows in the existing `area_statement_line_items` table under the `permit_fees` category (and optionally new `approval_statutory` / `approval_consultant` categories). Reversible — a `source: 'approval_costs'` tag marks each row so it can be refreshed without manual cleanup.

**Doubles-protection.** Financial Analysis's existing manual line items stay untouched; only rows tagged `source='approval_costs'` are managed by this module.

### 8.3 Backward compatibility

- Zero changes to existing calculators, `RulesRegistry`, `AreaCalcEngine`, `AreaStatementFinancialsBridge`, `CalcOverrideManager`, `FormulaDiscrepancyDetector`.
- Zero changes to existing views except (a) sidebar entry, (b) one "Approval Costs" tile added to Area Analysis Stage 5's "Next" grid, (c) one summary card added to Financial Analysis view ("Approvals: ₹X estimated · N items pending").

---

## §9. Staged implementation plan

### Stage 1 — Audit & design (this document)
Deliverable: this file + reconciliation table + proposed schema + workflow proposal.
**Status: DONE — awaiting approval.**

### Stage 2 — Main interface + master library import
1. `migrate_v20_approval_costs.sql` — all tables in §5.1.
2. `AdminController::approvalMasterLibrary()` + `scripts/import_sources.php` to load the reconciled list into `approval_master` + `approval_master_alias` with full provenance into `approval_source_import`.
3. `ApprovalCostsController` with 2 routes: `approval_costs` (landing) and `approval_costs_detail` (per-approval drawer).
4. Views: `views/approval_costs/{index.php, _shell.php, _checklist_table.php, _detail_drawer.php, master_library.php}`.
5. Reuse `SchemaFormRenderer` for authority / fee-schedule edit forms.
6. Sidebar entry.
**Verify:** master library loads clean, 15 duplicates folded, all 121 originals preserved as aliases.

### Stage 3 — Applicability engine
1. `ApprovalApplicabilityEngine` library — pure PHP, deterministic.
2. Seed ~30 initial rules into `approval_master_applicability_rule`.
3. UI: Step-2 "Review applicability" page per statement.
4. User-override flow with mandatory reason.
**Verify:** on Ghatkopar (SRA, 33(10)), engine suggests MHADA = No, RERA = mandatory, Env Clearance = mandatory (BUA > 20,000), CRZ = NOT applicable, Heritage = requires_verification, Tree NOC = conditional, etc. Hand-check against a known reference project.

### Stage 4 — Cost estimation
1. Calculation methods (§7.2 table) in `ApprovalCostCalculator`.
2. Preserve Source B's indicative figures with `source_reported` confidence.
3. Deposit tracking (separate table).
4. Rollup → `approval_summary_json` on `area_statements`.
**Verify:** no auto-reconciliation of a range value; all unverified rates flagged with amber chip; rollup matches sum of components.

### Stage 5 — Timeline + critical tracker
1. `project_approval_timeline` fields + the dependency graph.
2. Critical Approval Tracker view.
3. Dashboard right-rail panel.
4. Alerts / follow-up queue.
**Verify:** critical-path calc for a sample statement; "Env Clearance missing" blocker surfaces.

### Stage 6 — Integration
1. Area Analysis Stage 5 → tile → Approval Costs landing.
2. "Push to Financial Analysis" materializer.
3. Financial Analysis view: Approval summary card.
4. Change-notification banners when upstream Area Analysis changes.
**Verify:** push + edit a line item on Fin side + re-push doesn't duplicate; manual Fin lines preserved; unpush cleans only `source='approval_costs'` rows.

### Stage 7 — Testing + refinement
1. Hand-test with each of 4 regulations' sample statements.
2. Verify duplicate-import guard (`approval_source_import` prevents re-insertion).
3. Verify user permissions.
4. Verify existing modules intact.

**Each stage returns for sign-off before the next starts.**

---

## §10. Acceptance criteria coverage (brief §20)

| # | Criterion | Coverage in this plan |
|---|---|---|
| 1 | All 93 + 28 entries accounted for | §2 reconciliation table — every entry listed |
| 2 | Reconciled intelligently without data loss | 15 duplicates folded; 24 "related" pairs kept distinct; **all 121 originals aliased** |
| 3 | Conflicting cost/timeline figures preserved + flagged | §4.3 + §7.2 confidence model keeps both source values |
| 4 | Supporting docs / tech reports / approvals distinguished | §3 record types (8 types, not a flat list) |
| 5 | Each project has independent register | §5 `project_approval` keyed by `statement_id` |
| 6 | Transparent suggestion rules | §7.1 deterministic engine; rules in DB, not code |
| 7 | User override allowed | `user_override` + `override_reason` columns + confirmed-decision diff when upstream changes |
| 8 | Confidence classification on every estimate | §7.2 — 6-tier chip system |
| 9 | Refundable deposits separated | §5 `project_approval_deposit` table |
| 10 | Actual costs / timelines / dependencies / criticality trackable | §5 schema + §7.3 timeline design |
| 11 | Next action per outstanding item | `project_approval.next_action` column |
| 12 | Dashboard totals reliable, no double-count | §8.2 line-item tagging + rollup JSON single source |
| 13 | Clean data interface to Fin Analysis | §8.2 two surfaces documented |
| 14 | Unverified never presented as confirmed | §7.2 confidence chips always visible; Source B figures default `source_reported` |
| 15 | Existing modules intact | §5.3 only 2 additive columns on existing tables; §8.3 explicit non-destructive guarantee |

---

## §11. Open questions for your sign-off

1. **Scope of initial rule-engine seed** — 30-50 rules in Stage 3, or deeper? More rules = more accurate suggestions but more review time.
2. **Authority master** — do you want a hard-coded `approval_authority` seed (MCGM, SRA, MHADA, MMRDA, BEST, MoEF, SEIAA, CFO, Tree Auth, Revenue Dept, Collector, ASI, NHAI, PWD, CGWA, CRZ Auth, Railway, AAI, …), or should we build the UI first and let users add on demand?
3. **"Push to Financial Analysis"** — automatic on every change, or an explicit user-triggered sync button? Brief §17 says structured interface but doesn't specify.
4. **Permission model** — currently uses `edit_proposals` for most writes. Need a new `manage_approval_master` permission for the master-library edit UI (library edits affect every project).
5. **Historical cost preservation** — when a master rate changes (e.g. CGST amendment), should project records that already applied with the old rate stay frozen, or offer "recompute with new rate"? Default proposed: freeze; offer explicit refresh button.
6. **A1-26 EETC + A1-70 M&E Certificate + B-21 Umbrella** — tagged `requires_classification` until you confirm their proper type/phase. Flagged, not fabricated.
7. **Sidebar numbering vs existing numbering** — current sidebar is "① Area Analysis, ② Financial, ③ Best Outcome". Adding Approval Costs between 1 and 2 bumps the others. OK to renumber? Alternative: insert as "① ✓ · ② Approval Costs · ③ Financial · ④ Best Outcome".
8. **Source B item 1 (RERA?)** — the extract starts at Sr. No. 2. Is item 1 of the FICCI checklist present elsewhere, or should we treat RERA (A1-66) as the natural `B-1` equivalent? Default: pair them, mark B-1 as "not in extract, assumed RERA Registration".
9. **Timeline range handling** — if source says "30-45 Days", show both bounds in all views, or let the user pick a central value for the dashboard? Brief leans toward "preserve range". Default proposed: preserve range; critical-path uses the high bound (conservative).
10. **Non-Mumbai geography** — brief says Mumbai first but extensible. OK to use `jurisdiction = 'MH/Mumbai'` on master records and expand later via a migration?

---

## §12. What I will NOT do until approved

- No code written.
- No database schema altered.
- No existing module touched.
- No source entry classified as "verified official" without an explicit cost schedule reference I can cite.
- No autofill of indicative values into project cost estimates.

**Awaiting your sign-off (or targeted answers to §11) before starting Stage 2.**
