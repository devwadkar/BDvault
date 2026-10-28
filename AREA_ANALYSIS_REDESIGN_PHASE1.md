# Area Analysis — Redesign Phase 1 Audit

> **Scope.** This is Phase 1 only: audit the existing module and propose the new user journey. **No code was changed.** The 6 deliverables requested in the brief are below (§1–§6).

---

## 1. How the current module works

**Entry point:** `index.php?page=area_analysis` → [AreaAnalysisController::index()](controllers/AreaAnalysisController.php:44). The user clicks "+ New statement" against an **accepted** proposal, which POSTs to `area_analysis_start` → creates a fresh `area_statements` row with status `draft`, auto-named `"<Proposal Name> — Area Statement v1.0"`, and jumps the user into a **6-step wizard**:

| Step | Title | View | Writes to |
|---|---|---|---|
| 1 | Project Details | [step1.php](views/area_analysis/step1.php) | `area_statements` columns (project_name, cts, ward, planning_authority, development_type) |
| 2 | Plot & Land | [step2.php](views/area_analysis/step2.php) | `area_statement_land` rows (gross, roads, reservation, amenity, other deductions) |
| 3 | Existing Development | [step3.php](views/area_analysis/step3.php) | `existing_buildings` rows + `s3_*` keys in `draft_json` |
| 4 | Regulatory Details | [step4.php](views/area_analysis/step4.php) | `area_statements.selected_regulation_key` |
| 5 | Area Calculation | [step5.php](views/area_analysis/step5.php) | Triggers `AreaCalcEngine::run()` → populates `area_statement_calcs` + `_fsi` + `_dev` + `_valid` + summary columns |
| 6 | Review & Generate | [step6.php](views/area_analysis/step6.php) | Locks the statement (`status='locked'`) |

**Mechanics (`AreaAnalysisController` + helpers):**
- Every step POSTs to `area_analysis_save_step` which merges the posted form into a per-step blob inside `area_statements.draft_json`, then runs step-specific side effects (`mirrorStep1ToRow`, `writeLandRows`, `writeExistingBuildings`, `persistSelectedRegulation`).
- Step 4 calls [`RegulationSuggester::suggest()`](lib/RegulationSuggester.php:44) — a deterministic rule engine that scores 8 regulations (33(5), 33(7)/(A)/(B), 33(9), 33(10), 33(11), 33(20)(B)) from `development_type` + `planning_authority` + plot/road + tenant count, and returns `{regulation_key, confidence, rationale, alternates[]}`.
- Step 5 → [`AreaCalcEngine::run()`](lib/AreaCalcEngine.php:29) picks `Reg33_5|10|11|20B_Calculator`, maps inputs via `mapInputs()`, runs `.compute()`, applies manual overrides via `CalcOverrideManager::applyToRows()`, persists calc rows, then runs the `FormulaDiscrepancyDetector`.
- Only **4 of 8** suggested regulations have calculators — the other 4 (33(7), 33(7)(A), 33(7)(B), 33(9)) are "pending" and the wizard dead-ends at Step 5 if picked.
- Common constants (loading factor, carpet defaults, fungible %, FSI caps) are centralised in `regulatory_constants` and read through `RulesRegistry::get()` — regulation-specific defaults do exist, just not per-regulation **input fields**.

**Supporting pieces** (good bones to keep):
- `dcpr_rules/*.json` — the authored regulatory text per section
- `dcpr_rules/schemas/reg_33_{5,10,11,20_B}_inputs.json` — input schemas (currently used by the calculators internally, **not** by the wizard forms)
- `dcpr_rules/validations/reg_33_{10,11,20_B}_checks.json` — assertion packs
- `FormulaDiscrepancyDetector`, `CalcOverrideManager`, `AreaStatementAIAssistant` — pre-existing audit/override/chat scaffolding

---

## 2. Main usability problems and technical limitations

### UX problems (what a non-technical user hits today)

1. **One generic form fits all regulations.** Steps 1–3 ask every question for every regulation. A vacant-plot `land_development` project still gets Step 3 "Existing Development" (buildings/tenants/carpet) that has no meaning for it. An SRA scheme and a MHADA scheme see the exact same Step 2 fields, even though they treat reservations, loading factors, and amenity handovers differently.
2. **Regulation is picked LATE (Step 4), after irrelevant data is already entered.** The user has to fill 3 steps of common+not-common fields before the system reveals which regulation applies — then if a different regulation is chosen, there's no visible pruning or prompting for the fields that regulation actually needs.
3. **Field labels and defaults assume domain knowledge.** `s3_carpet_res_sqm` defaults to `27.88` with hint "SRA floor" — users not familiar with DCPR won't know what carpet floor means or when 35.00 (MHADA) applies. Multiple fields show cryptic units (`sqm`), legal abbreviations (`PTC`, `AH`, `RR`, `LOD`), and inline formulas without plain-English explanation.
4. **Rehab-specific terminology appears for non-redevelopment projects.** Step 3 shows "Eligible tenements" as `required` even when `development_type = land_development`.
5. **No real recommendation stage.** Step 4 shows a suggestion inline with alternates — but the user has already walked through 3 steps. There is no up-front "tell us about your project, and we'll suggest where to go next" moment.
6. **"Pending" regulations are picked, then crash the user.** `RegulationSuggester` can recommend 33(7) with 65% confidence, and the user picks it, saves it, hits Step 5 → `AreaCalcEngine::run()` returns an error because `AreaCalcEngine::SUPPORTED` doesn't include it. The error is a one-line banner, no clean "this regulation isn't ready yet, pick an alternate" flow.
7. **No validation at point of entry.** Fields are validated at Step 5 only, after `.compute()` runs. Entering an obviously bad plot area at Step 2 doesn't throw a plain-English error until much later, after everything else has been filled.
8. **The wizard is linear with no map.** Users can go back one step at a time but can't jump to Step 4 to change the regulation without saving unchanged intermediate steps. There is no visible per-step completion status.
9. **Dense tables everywhere.** Step 2's "Road setbacks" and "Named deductions" use three-column data-grid tables; Step 3's building roster is an even wider grid. On tablet width these overflow.
10. **No draft recovery UX.** The `draft_json` auto-save works, but the user doesn't see "Draft saved 12 seconds ago" — so they don't know whether to trust leaving the page.
11. **"Generate Area Statement" at the end is a lock, not a generate.** Users expect "Generate" to produce an output they can review and then approve; here it immediately locks the version and the output they expected has already been computed at Step 5.

### Technical limitations

1. **Wizard structure is hardcoded** in `AreaAnalysisController::STEPS` (6 fixed slots). To make different regulations show different steps/sections, the controller + every view would need branching.
2. **Form fields are hardcoded in each PHP view**. There is no data-driven renderer that reads an input schema and produces the matching form. The `dcpr_rules/schemas/*_inputs.json` files exist but are consumed only inside the calculators.
3. **`draft_json` is a flat per-step payload** — resilient for round-trips but opaque to analytics, hard to validate server-side, and loses structure when the regulation changes (e.g. selecting MHADA after entering SRA tenement data leaves orphaned keys).
4. **No per-field "universal vs regulation-specific" classification** exists in the schema. Every field is treated the same.
5. **`mapInputs()` in `AreaCalcEngine`** does regulation-aware input assembly inline (`if ($reg === '33(5)')` branches), which couples the engine to each regulation and makes adding a new one invasive.
6. **No server-side validation** on save-step beyond the DB column type. Invalid combinations are rejected only at calc time by the calculator.
7. **Pending regulations** have no clear data model — `RegulationSuggester::REGULATIONS[*].status` flags `pending` vs `calculator`, but there's nothing stopping a user from selecting one and reaching a dead end.
8. **`existing_buildings` is a dedicated table** but the roster UI puts most of the real driver values (`s3_eligible_total`, `s3_carpet_res_sqm`, `s3_loading_factor`) into `draft_json` instead — two sources of truth for one concept.
9. **Live preview** in Step 1-3 is computed in PHP on every page reload, not with AJAX — so you can't actually watch numbers change as you type.
10. **No `effective_date` / regulation version** stamping on a saved statement — if DCPR constants change, old statements will silently recompute differently on "Refresh calc".

---

## 3. Proposed step-by-step user journey

A compressed 5-stage flow that **asks for less upfront**, **recommends a regulation after the minimum needed data**, and **dynamically shows regulation-specific fields only once the regulation is locked in**.

```
┌───────────────────────────────────────────────────────────────────────┐
│ Stage 1 — Project Basics                                              │
│   Collect ONLY universally-required info (name, address, plot, use,   │
│   development category). 5–7 fields. No regulation-specific asks.     │
└──────────────────────────┬────────────────────────────────────────────┘
                           ▼
┌───────────────────────────────────────────────────────────────────────┐
│ Stage 2 — Regulation Recommendation                                   │
│   System shows: "Based on what you entered, DCPR 33(10) looks like    │
│   the right fit (87% confidence). Here's why."                        │
│   User can: accept · explore alternatives · pick manually.            │
│   Alternatives show eligibility gaps so user understands trade-offs.  │
└──────────────────────────┬────────────────────────────────────────────┘
                           ▼
┌───────────────────────────────────────────────────────────────────────┐
│ Stage 3 — Regulation-Specific Details  ← THIS IS THE DYNAMIC STAGE   │
│   Form is BUILT from the regulation's input schema. Shows only the    │
│   fields that regulation actually needs. Grouped into sub-sections    │
│   (e.g. Plot → Existing structure → Rehab drivers → Incentives).      │
│   Live inline validation. Live auto-calculated preview.               │
│   Changing regulation here resets the dynamic form (preserving        │
│   universally-captured values) and prompts for the new asks.          │
└──────────────────────────┬────────────────────────────────────────────┘
                           ▼
┌───────────────────────────────────────────────────────────────────────┐
│ Stage 4 — Review                                                      │
│   Compact summary of what was entered, grouped as the Area Statement  │
│   will present it. Edit-in-place buttons on every group. Shows any    │
│   warnings/validations BEFORE generation.                             │
└──────────────────────────┬────────────────────────────────────────────┘
                           ▼
┌───────────────────────────────────────────────────────────────────────┐
│ Stage 5 — Area Statement (output)                                     │
│   Final structured statement, regulation-specific sections only.      │
│   Clear separation: User inputs · Derived · Needs verification.       │
│   Explicit "Calculated estimate — not a legally approved statement"   │
│   banner. Export: PDF / Excel. Edit → return to Stage 3.              │
└───────────────────────────────────────────────────────────────────────┘
```

**Key differences from today**:
- Step 3 ("Existing Development") disappears as a fixed stage — those fields move into Stage 3 only when the chosen regulation needs them.
- Regulation choice moves from Step 4 → Stage 2 (second screen the user sees).
- Current Step 5 ("Calculate" as a button to click) becomes **automatic**: as the user fills Stage 3, the preview recalculates. Stage 4 just shows what will be generated.
- Current Step 6 ("Review & Generate") splits into Stage 4 (Review) + Stage 5 (Statement). The "Generate" button becomes just a final confirm-and-stamp action.

**Persistent chrome throughout:**
- Progress indicator (5 numbered stages, current one highlighted)
- Project summary panel (always visible: name, plot area, regulation, net BUA)
- Save-indicator ("Draft saved 2s ago")
- Back / Next / Save Draft / Review buttons

---

## 4. Recommended structure for the Basic Project Information stage

Stage 1 is the **only** stage with hardcoded fields — everything beyond it is driven by the regulation schema. These are the fields that genuinely apply to every DCPR redevelopment project, grouped into three logical sections so the form never looks like a wall of inputs.

### Section A — Identification *(from source proposal, user confirms)*
| Field | Type | Source | Required | Notes |
|---|---|---|---|---|
| Project name | text | Pre-filled from `proposals.proposal_name` | ✓ | Editable |
| Source proposal | read-only link | FK | ✓ | "Click to view original proposal" |
| CTS / Survey number | text | user | ✓ | Example: "CTS 123/A pt" |
| Property-card plot area | number (sqm) | user | ✓ | Separate from "survey area" — see Section B |

### Section B — Location & jurisdiction
| Field | Type | Source | Required | Notes |
|---|---|---|---|---|
| Plot address | textarea | Pre-filled from `proposals.site_address` | ✓ | |
| City / region | text | Pre-filled from `proposals.city` + `proposals.region` | ✓ | |
| Ward | searchable dropdown | user | ✓ | List of MCGM ward codes (A/B/C/D/…), "N/A" option |
| Zone (as per DP) | dropdown | user | – | Residential · Commercial · Industrial · No-development · Mixed |
| Planning authority | dropdown | user | ✓ | MCGM · SRA · MHADA · MMRDA · Collector · Other |
| Is this an Island City plot? | yes/no | user | ✓ | Determines some 33(7) eligibility |

### Section C — Project category
| Field | Type | Source | Required | Notes |
|---|---|---|---|---|
| Development category | dropdown | user | ✓ | Redevelopment · SRA · MHADA layout · Society redev · Cluster · Vacant / land dev · Other |
| Is there existing construction on the plot? | yes/no | user | ✓ | If **no**, Section D stays collapsed |
| Approx. survey plot area | number (sqm) | user | – | Shown only when the user flags a discrepancy with the property-card area |
| Primary access road width | number (m) | user | ✓ | Needed by every regulation's FSI eligibility |

### Section D — Existing occupancy *(shown only when "Is there existing construction?" = Yes)*
*Collapsed by default for vacant plots. Deliberately **lightweight** — detailed per-building roster moves to Stage 3 (regulation-specific).*
| Field | Type | Required | Notes |
|---|---|---|---|
| Approx. number of residential units | number | – | For recommendation only |
| Approx. number of commercial units | number | – | For recommendation only |
| Approx. tenant / member count | number | – | Required if dev category = SRA or MHADA |

**UX rules for Stage 1:**
- Each section is a card with its own heading, not one long form.
- Tooltips (`?` icon) explain CTS, DP, FSI, "Island City", "property-card area vs survey area".
- All numbers show `sqm` with a toggle to display `sq ft` (never ask the user to convert).
- Inline validation fires on blur (not just on submit).
- "Save & continue to recommendation" button, not "Save & Next" — tells the user what happens next.

**Fields consciously removed from Stage 1 vs today:**
- Multiple road setback rows (goes to Stage 3 — needed only once regulation picked)
- Reservations / amenity handover (Stage 3)
- Multi-building roster with carpets + loadings + tenant counts (Stage 3)
- Carpet area defaults (Stage 3 — regulation-dependent)

---

## 5. Field classification — universal vs conditional vs regulation-specific

### A — Universally required *(every DCPR redevelopment project)*
These stay in Stage 1 (Section A, B, C above) regardless of regulation.
- Project name
- CTS / survey number
- Plot address, city, region, ward
- Property-card plot area
- Planning authority
- Development category
- Primary access road width
- "Is there existing construction?" (yes/no)

### B — Conditionally required *(depends on answers to A, not regulation)*
Revealed by progressive disclosure inside Stage 1 or Stage 3, based on user answers to A.
- **"Existing construction = Yes"** → unlocks residential-unit / commercial-unit / tenant approx counts
- **"Development category = SRA or MHADA"** → requires tenant count (eligibility threshold)
- **"Development category = Cluster"** → requires plot ≥ 4000 sqm reminder
- **"Is Island City plot = Yes"** → unlocks "building categorized as cessed?" question for recommendation
- **Property-card area ≠ survey area** → unlocks "approx. survey area" field + reconciliation note

### C — Regulation-specific *(entered in Stage 3, driven by the regulation schema)*
Each regulation's `dcpr_rules/schemas/<reg>_inputs.json` defines its own:

| Regulation | Example regulation-specific fields |
|---|---|
| **33(5) MHADA** | MHADA scheme type (housing/colony/apartment); existing MHADA FSI consumed; share split (society/MHADA); contribution per sqm; TDR loading desired; Yes/No on 4.0 FSI road criteria |
| **33(10) SRA** | Eligible slum-tenant count; tenement carpet floor (27.88); fungible FSI opt-in %; sale-component in-situ vs TDR; PTC obligation; LOD saleable area; amenity common %; social amenity % |
| **33(11) PTC** | Transit camp type; rental housing tenure; permissible BUA caps per 33(11); authority sanction letter number |
| **33(20)(B) Private AH** | AH share %; owner-share %; AH unit carpet mix; price control category; tenure of AH lease; qualifies for additional FSI; income-group definition |
| **33(7) / 33(9) (pending)** | Will be driven by their own schemas when calculators ship |

Each schema node declares: `key`, `label`, `type`, `unit`, `required`, `default`, `help` (plain-English), `shown_if` (conditional reveal expression), `validate` (min/max/regex/custom), `maps_to_calc_input` (name used by the calculator).

**Classification by field (summary):**

| Category | ~Count today | Count after redesign | Lives in |
|---|---|---|---|
| Universal | ~10 | ~12 | Stage 1 (hardcoded view) |
| Conditional-on-universal | ~5 | ~8 | Stage 1 progressive disclosure |
| Regulation-specific | ~50 (spread across Steps 2+3 for all regulations) | 15–30 **per regulation** | Stage 3 (schema-driven) |

---

## 6. Plan for introducing regulation-specific logic without breaking the existing app

The strategy is **additive, not destructive**. Three layered steps, each individually shippable + reversible, each verified against the existing live data before moving on.

### Phase A — Introduce the schema runtime, keep the old wizard alive

1. **Extend input schemas** `dcpr_rules/schemas/reg_<key>_inputs.json` to carry UI metadata (`label`, `help`, `shown_if`, `group`, `display_order`, `validate`) alongside the existing `maps_to_calc_input` keys. Current calculators continue reading only the `maps_to_calc_input` side — no behavior change.
2. **Build a `SchemaFormRenderer` library** (new file, no existing code touched) that takes a schema + current values and produces HTML + a server-side validator. Pure function; testable in isolation.
3. **Add new routes** `?page=area_analysis_v2_*` that use the new flow, side-by-side with the existing `?page=area_analysis_*`. Users opt in via a "Try the new flow" toggle on the Area Analysis landing. **Zero disruption** to anyone using the current wizard.

### Phase B — Rebuild Stage 1 (universal basics)

4. New controller `AreaAnalysisV2Controller::basics()` + new view `views/area_analysis_v2/stage1_basics.php`.
5. Writes to the **same** `area_statements` table (reusing columns + `draft_json`). No schema migration — just a `wizard_version` ENUM column added via a small `migrate_v18_*.sql` to tag v2 statements.
6. Verify Stage 1 round-trips cleanly: open a v1 statement in v2 → shows same values. Open a v2 statement in v1 → still legible (worst case: missing per-regulation fields).

### Phase C — Rebuild Stage 2 (recommendation)

7. Reuses existing `RegulationSuggester::suggest()` **unchanged**. Only the view changes.
8. New view shows the recommendation card with explicit eligibility checklist (✓ met · ✗ gap · ? missing) per alternative, so the user can see WHY a regulation is or isn't recommended.
9. Pending regulations (no calculator) are **not selectable** — shown as "Review-only (calculator pending)" so the user never dead-ends.

### Phase D — Rebuild Stage 3 (dynamic, regulation-specific) **starting with ONE regulation**

10. Pick **33(10) SRA** as the pilot (highest-coverage schema, most-complete calculator, most test data).
11. Update `dcpr_rules/schemas/reg_33_10_inputs.json` with full UI metadata.
12. Render Stage 3 for 33(10) with `SchemaFormRenderer`. Live inline validation. Live preview via AJAX against `AreaCalcEngine::run()` (new return mode: dry-run, don't persist).
13. **Verify end-to-end against an existing seeded statement** (Ghatkopar Parksite): same inputs → same calculated numbers. If byte-identical, we know v2 doesn't break the math.
14. Repeat with 33(11), 33(5), 33(20)(B). One per shippable PR.

### Phase E — Rebuild Stages 4 (review) & 5 (statement)

15. Reuse the existing `feasibility_statement.php` + PDF/Excel exporters — they already read from persisted calc rows, which don't change.
16. Add the "Calculated estimate — not a legally approved statement" banner.

### Phase F — Flip default + sunset old wizard

17. After every regulation is on v2 and verified, flip the Area Analysis landing default to v2.
18. Keep `?page=area_analysis_*` routes alive and readable for 1 release cycle (locked statements render in v1 view mode).
19. After one cycle with no v1 usage, remove v1 controller + views.

### Guard-rails observed throughout

- **No destructive schema migrations.** New columns only, never drops.
- **No calculator changes.** `Reg33_*_Calculator`, `RulesRegistry`, `CalcOverrideManager`, `FormulaDiscrepancyDetector`, `AreaStatementFinancialsBridge` are all unchanged — the redesign is a UI layer on top of them.
- **Shared persistence.** v2 writes to the same tables so Financial Analysis + Best Outcome continue to work for v2 statements without any changes.
- **Audit trail intact.** Overrides + discrepancies + calc rows still populate because `AreaCalcEngine::run()` is called the same way.
- **Rollback = flip the default + delete the opt-in toggle.** No DB rollback needed.
- **Each phase ships independently.** Any phase can pause for weeks without blocking the others — the opt-in gate means v1 continues working regardless.

### Risks + how we handle them

| Risk | Mitigation |
|---|---|
| Schema-driven form can't express a weird edge case (e.g. 33(5)'s MHADA 4.0 FSI toggle) | `SchemaFormRenderer` supports a per-field escape hatch: `"render": "custom:phpViewPath"` for the 1–2 fields that need bespoke HTML |
| Live preview AJAX is slow for large statements | Dry-run `AreaCalcEngine::run()` already runs in ~50ms on Ghatkopar; debounce input to 300ms, show spinner on recompute |
| User changes regulation mid-way and loses entered values | Universal fields stay; regulation-specific fields are preserved per-regulation in `draft_json` under `regulation_{key}` namespaces, so switching back restores them |
| New schema metadata breaks the existing calculators | Calculators only read `maps_to_calc_input`; new UI metadata keys are additive and ignored by them |

---

## Deliverable summary (for cross-reference against the brief)

| Brief ask | Section |
|---|---|
| 1. How current module works | §1 |
| 2. Main usability problems + tech limitations | §2 |
| 3. Proposed step-by-step user journey | §3 |
| 4. Recommended structure for Basic Project Information | §4 |
| 5. Universal vs conditional vs regulation-specific fields | §5 |
| 6. Plan for introducing regulation-specific logic non-destructively | §6 |

---

## What I recommend as the next step

**Review this document with the stakeholder(s)**, confirm:
1. The 5-stage flow matches what they want.
2. The Stage 1 field list (§4) is complete + correct for their process.
3. The additive opt-in deployment (§6, Phase A) is acceptable.

Once confirmed, I'll implement **Stage 1 only** (new controller + view + schema layer + v1/v2 opt-in toggle) and verify it against the Ghatkopar Parksite statement before touching Stages 2–5. No v1 code is removed in that pass.
