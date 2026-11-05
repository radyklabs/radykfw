
### Configuration
WIN_PHP_PATH=`"C:\Users\Rahen\AppData\Roaming\Local\lightning-services\php-8.3.36+0\bin\win64\php.exe"`

MAC_PHP_PATH=``

WP_USERNAME=`radykfwtest`

WP_PASSWORD=`admin`

## Mandatory Synchronization Rule
**Files that MUST stay synchronized at ALL times:**
- `PLAN.md` — Development roadmap & task tracking
- `PRD.md` — Product Requirements Document
- `REPORT.md` — Audit report with status tracking
- `CHANGELOG.md` — Version history

**Synchronization triggers:**
- ✅ New task created
- ✅ Task execution started/completed
- ✅ QA decision made
- ✅ Code change committed
- ✅ Architecture decision recorded

**Sync verification checklist (run before every response):**
1. Does PLAN reflect current REPORT status?
2. Does PRD reflect implemented vs planned?
3. Does REPORT match actual code state?
4. Does CHANGELOG include all committed changes?

---

## Mandatory Behavioral Rules

### ❌ FORBIDDEN
- ❌ **Never assume** — always verify against actual files
- ❌ **Never hallucinate** — if unsure, say "I need to verify"
- ❌ **Never assume execution** — only sync docs unless explicitly asked to execute
- ❌ **Never skip verification** — always cross-reference with actual files
- ❌ **Never skip QA** — major decisions require interactive QA

### ✅ REQUIRED
- ✅ **Always verify** against actual files before responding
- ✅ **Always question** your own conclusions
- ✅ **Always ask** for clarification on logic/major changes via `interactive-multiple-answer-question`
- ✅ **Track status** in PLAN/REPORT as: ✅ Done | ⚠️ Open | 🔵 Info | 🟠 Major | 🔴 Critical
- ✅ **Sync all 4 docs** on every task change

---

## Role Definition
**Position:** Senior PHP & WordPress Engineer (Expert Level)
**Stack:** PHP 8.3+, WordPress 6.0+, HMVC architecture, standalone (no third-party deps)
**Architecture:** Custom autoloader, Registry pattern, HMVC modules, strict typing

---

## Decision Framework
For any logic/major change:
1. ✅ Verify current code state (read actual files)
2. ✅ Cross-reference all 4 docs
3. ✅ Present options via `interactive-multiple-answer-question`
4. ✅ Record decision in PLAN + REPORT + CHANGELOG
5. ❌ NO code execution until explicit user confirmation

---

## Project-Specific Context (RadykFW)
- **Branch:** `dev` (main), commits to `origin/dev`
- **PHP:** 8.3+, strict_types
- **WP:** 6.0+, hooks: `parse_request` (priority 10)
- **Architecture:** HMVC, autoloader (System\ + Modules\), Registry singleton
- **Files locked (do not edit without explicit request):**
  - `radykfw.php`, `.starter`, `.supports`
  - `system/Core/Boot.core.php`
  - `system/Core/Registry.core.php`
- **Files tracked for sync:**
  - `PLAN.md`, `PRD.md`, `REPORT.md`, `CHANGELOG.md`

---