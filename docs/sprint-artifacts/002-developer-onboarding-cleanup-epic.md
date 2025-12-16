---
id: EPIC-002
title: "Developer Onboarding & Technical Debt Cleanup"
epic_owner: John (PM)
priority: critical
labels: [documentation, technical-debt, onboarding, testing, architecture]
status: planning
workflowType: epic
sprint: Q4-2025
user_name: Nazar
date: 2025-12-16
communication_language: English
---

# EPIC-002: Developer Onboarding & Technical Debt Cleanup

## Executive Summary
Prepare the AI Virtual Companion codebase for external contributors and scaling by establishing comprehensive documentation, cataloging/fixing critical bugs, and cleaning technical debt. Triggered by organic interest from potential contributors who witnessed smooth AI-human chat interactions.

---

## Problem Statement

**Business Context:**
The AI Virtual Companion application has demonstrated product-market fit signals - multiple people expressed interest in contributing after observing smooth persona interactions. However, the codebase is not ready for external contributors.

**Current Blockers:**
1. **Documentation scattered** - 13+ markdown files at project root instead of organized `docs/` structure
2. **Unknown bug inventory** - Small bugs accumulated during "going big" phase, not cataloged or prioritized
3. **Technical debt unmapped** - No clear picture of code quality, test coverage, or architectural health
4. **No onboarding path** - New developers have no clear entry point or contribution guide

**Impact:**
- Cannot confidently onboard contributors → **blocks growth**
- Unclear what works smoothly vs. has bugs → **blocks trust/demos**
- Technical debt slows velocity → **blocks scaling**
- Poor documentation → **friction for all of above**

---

## Success Criteria

### Epic-Level Acceptance Criteria
1. ✅ Documentation organized into clear `docs/` hierarchy with architecture, guides, and reference sections
2. ✅ All critical bugs cataloged in `BUGS.md` with severity, impact, and fix priority
3. ✅ Technical debt mapped in `TECHNICAL_DEBT.md` with actionable remediation plan
4. ✅ Test coverage baseline established (% measured, gaps identified)
5. ✅ Developer onboarding documentation exists: README, CONTRIBUTING.md, development setup guide
6. ✅ First 3-5 "good first issue" tasks identified for new contributors
7. ✅ All existing documentation links updated and validated

### Business Outcomes
- **Time to first contribution:** Reduced from unknown to <30 minutes (clone to PR)
- **Confidence level:** Can demo to potential contributors without encountering known bugs
- **Velocity impact:** Clear technical debt map enables informed prioritization

---

## Current State Analysis

### What's Working (Based on User Feedback)
- ✅ **AI chat experience** - Smooth, natural conversations (attracted contributors)
- ✅ **Core services architecture** - GeminiBrain, Telegram, SmartQueue pattern is solid
- ✅ **Memory system** - Persona memory with tags appears functional
- ✅ **Multi-persona support** - Architecture supports multiple personas

### Known Issues (To Be Cataloged)
- 🐛 **Small bugs identified** during "going big" phase (not yet detailed by Nazar)
- ⚠️ **Test coverage unknown** - Likely low or zero
- ⚠️ **Documentation inconsistency** - Files scattered, some may be outdated
- ⚠️ **Setup complexity** - New developer friction points unknown

### Technical Stack (Reference)
- **Framework:** Laravel 12
- **AI:** Gemini 2.5 Flash
- **Integrations:** Telegram Bot API, ElevenLabs TTS, Cloudflare Workers AI
- **Frontend:** Livewire, Alpine.js, Tailwind CSS
- **Media:** Spatie MediaLibrary
- **Database:** MySQL (via Herd)

---

## Scope

### In Scope
✅ Documentation reorganization and structure  
✅ Bug catalog creation (discovery + prioritization)  
✅ Technical debt mapping  
✅ Test coverage assessment  
✅ Developer onboarding guides  
✅ Architecture documentation review  
✅ Contribution workflow definition  
✅ Internal link validation  

### Out of Scope (Future Epics)
❌ Fixing all identified bugs (will be prioritized separately)  
❌ Implementing test suite (separate epic)  
❌ Resolving all technical debt (will create roadmap)  
❌ Adding new features  
❌ Performance optimization  
❌ Deployment automation  

---

## Stories Breakdown

### Story 1: Documentation Reorganization
**Owner:** Paige (Tech Writer) + Master  
**Estimate:** 4 hours  
**Priority:** P0 (Foundation for everything else)

**Tasks:**
- Create `docs/` folder structure (architecture/, guides/, reference/)
- Move 13 root-level `.md` files to appropriate locations
- Update internal documentation links
- Validate all links work
- Create `docs/README.md` as navigation hub

**Acceptance Criteria:**
- All `.md` files organized per proposed structure
- Zero broken internal links
- Navigation clear from root README

---

### Story 2: Bug Discovery & Catalog
**Owner:** Mary (Analyst) + Nazar  
**Estimate:** 3 hours  
**Priority:** P0 (Blocks trust)

**Tasks:**
- Interview Nazar about known bugs
- Test key user workflows to discover issues
- Create `BUGS.md` with template (severity, impact, repro steps)
- Prioritize by: Critical → High → Medium → Low
- Tag "good first issue" candidates

**Acceptance Criteria:**
- `BUGS.md` exists with all known bugs cataloged
- Each bug has severity (P0-P3) and impact assessment
- Top 3 critical bugs identified for immediate fixing

---

### Story 3: Architecture & Technical Debt Assessment
**Owner:** Winston (Architect) + Murat (Test Architect)  
**Estimate:** 6 hours  
**Priority:** P1 (Informs roadmap)

**Tasks:**
- Review service layer architecture vs. Laravel best practices
- Analyze test coverage (run coverage report if possible)
- Identify code quality issues (duplicate code, long methods, etc.)
- Review external service abstractions
- Assess database migration health
- Create `TECHNICAL_DEBT.md` with findings + remediation plan

**Acceptance Criteria:**
- `TECHNICAL_DEBT.md` exists with categorized debt items
- Test coverage % measured (even if 0%)
- Top 5 technical debt items prioritized
- Architecture diagram created or validated

---

### Story 4: Developer Onboarding Documentation
**Owner:** Paige (Tech Writer) + Bob (Scrum Master)  
**Estimate:** 5 hours  
**Priority:** P0 (Enables contributors)

**Tasks:**
- Write `CONTRIBUTING.md` (how to contribute)
- Enhance root `README.md` (quick start, 5-minute setup)
- Create `docs/guides/development-setup.md`
- Document testing workflow (even if tests don't exist yet)
- Create issue templates for bugs/features
- Write first 3-5 "good first issue" descriptions

**Acceptance Criteria:**
- New developer can clone → setup → run app in <30 min following docs
- `CONTRIBUTING.md` explains workflow, standards, PR process
- 3-5 "good first issue" tasks ready with clear acceptance criteria
- Issue templates exist in `.github/`

---

### Story 5: Quick Win Bug Fixes
**Owner:** Barry (Quick Flow Dev) or Amelia (Dev)  
**Estimate:** 4-8 hours (depends on bugs)  
**Priority:** P1 (Builds confidence)

**Tasks:**
- Fix top 3 critical bugs from Story 2 catalog
- Add regression tests for each fix (if feasible)
- Update BUGS.md status
- Document fixes in CHANGELOG or commit messages

**Acceptance Criteria:**
- Top 3 P0 bugs resolved
- No new bugs introduced (manual verification)
- BUGS.md reflects "fixed" status

---

## Timeline Estimate

**Total Epic Effort:** ~22-26 hours

**Suggested Sprint Plan:**
- **Day 1-2:** Stories 1, 2 (Documentation + Bug Catalog) - 7 hours
- **Day 3:** Story 3 (Architecture Assessment) - 6 hours  
- **Day 4:** Story 4 (Onboarding Docs) - 5 hours
- **Day 5:** Story 5 (Quick Win Fixes) - 4-8 hours

**Target Completion:** 1 week (with focused effort)

---

## Dependencies

### External Dependencies
- **Nazar's availability** for bug interview (Story 2)
- **Access to production/staging** environment for testing (Story 2)
- **Test framework setup** (if doesn't exist) for coverage report (Story 3)

### Internal Dependencies
- Story 1 → Story 4 (onboarding docs need organized structure)
- Story 2 → Story 5 (must identify bugs before fixing)
- Story 3 → Story 5 (architecture review informs fixes)

---

## Risks & Mitigation

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| More bugs discovered than expected | Timeline extends | High | Prioritize ruthlessly, defer P2/P3 bugs |
| Zero test coverage makes fixes risky | Quality concerns | Medium | Add basic smoke tests first |
| Documentation scattered more than known | Cleanup takes longer | Medium | Time-box to 4h, mark incomplete items as TODO |
| New contributors arrive before ready | Poor first impression | Low | Set expectations in README |

---

## Definition of Done (Epic Level)

✅ All 5 stories completed and acceptance criteria met  
✅ Documentation passes "30-minute onboarding" test with fresh developer  
✅ Top 3 critical bugs fixed  
✅ Technical debt roadmap approved by Winston and Nazar  
✅ At least 3 "good first issue" tasks published  
✅ All documentation links validated  
✅ Epic reviewed and approved by John (PM)  

---

## Next Steps After Epic

1. **Story Sprint Execution** - Break down each story into daily tasks
2. **Technical Debt Epic** - Address top 5 debt items from Story 3
3. **Test Coverage Epic** - Implement testing strategy from Story 3
4. **Bug Fix Sprints** - Address remaining P1/P2 bugs from catalog
5. **Contributor Onboarding** - Actually onboard first external developer

---

## Stakeholders

- **Epic Owner:** John (PM)
- **Technical Lead:** Winston (Architect)
- **Execution Leads:** Master, Paige, Mary, Barry/Amelia, Murat
- **Approver:** Nazar (Product Owner)

---

## Notes

- This epic is **foundational** - blocks growth and scaling
- Focus on **enablement** not perfection
- Documentation must be **living** - update as system evolves
- **Quick wins matter** - fixing visible bugs builds contributor confidence

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2025-12-16 | Epic created | BMad Master + Team |

