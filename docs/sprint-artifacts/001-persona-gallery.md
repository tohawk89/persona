---
id: STORY-001
title: "STORY-001: Persona Gallery - View persona media"
owner: Amelia / Barry
priority: high
estimate: 8h
labels: [frontend, livewire, medialibrary, ux]
status: draft
workflowType: feature
workflow: .bmad/bmm/workflows/feature/persona-gallery/workflow.yaml
artifacts_folder: '{project-root}/.bmad/artifacts/STORY-001'
stepsCompleted: [1, 2, 3, 4, 5]
user_name: Nazar
date: 2025-12-16
communication_language: English
---

## Summary
Provide a responsive, accessible Persona Gallery page/component that surfaces all media attached to a `Persona` via Spatie MediaLibrary collections (`avatar`, `reference_image`, `generated_images`, `voice_notes`). No DB schema changes.

## Problem
Team needs a central UI to review persona-related media (images, generated assets, voice notes) for debugging, QA, and demos.

## Acceptance Criteria
- A story artifact exists (this file) and is approved by `John` (PM).
- A Livewire component `PersonaGallery` can be mounted at `/personas/{id}/gallery` or from the persona dashboard.
- The gallery displays media from the `Persona` collections: `avatar`, `reference_image`, `generated_images`, `voice_notes`.
- Grid is responsive (3/2/1 cols) with lazy-loading thumbnails.
- Clicking an item opens a preview modal with large image or audio player, metadata, and a download action.
- Download and preview use signed/temporary URLs when storage is private.
- No DB migrations performed.
- Tests: basic integration test that the gallery endpoint renders and that a sample `Media` attached to a test `Persona` appears.

## Definition of Done
- Code merged to a feature branch with passing local tests.
- `docs/PERSONA_GALLERY_UI.md` updated (UX spec included).
- `Paige` has drafted user-facing documentation for README/OPERATING.
- `John` approves acceptance criteria and demo.

## Tasks
1. (1h) Finalize UI spec and wireframes — Owner: Sally (UX). Reference: `docs/PERSONA_GALLERY_UI.md`.
	- Save outputs to `{project-root}/.bmad/artifacts/STORY-001/step-01-ui-spec` after completion.
2. (4h) Scaffold Livewire `PersonaGallery` component + Blade view, implementing grid, filter dropdown, and preview modal — Owner: Amelia.
	- Save component files and a short implementation note to `{project-root}/.bmad/artifacts/STORY-001/step-02-implementation` after completion.
3. (1h) Add `registerMediaConversions()` to `App\\Models\\Persona` with `thumb` and `large` conversions and ensure conversions run (no DB changes) — Owner: Barry.
	- Save conversion config and verification screenshots/logs to `{project-root}/.bmad/artifacts/STORY-001/step-03-conversions`.
4. (1h) Add route `GET /personas/{persona}/gallery` and link from persona dashboard; ensure `auth` middleware applies — Owner: Amelia.
	- Save route diff and test URLs to `{project-root}/.bmad/artifacts/STORY-001/step-04-route`.
5. (1h) Add integration test(s) for rendering gallery and showing attached media — Owner: Murat.
	- Save test results and any CI notes to `{project-root}/.bmad/artifacts/STORY-001/step-05-tests`.
6. (0.5h) Update `docs/PERSONA_GALLERY_UI.md` and README snippet — Owner: Paige.
	- Save docs draft to `{project-root}/.bmad/artifacts/STORY-001/step-06-docs`.
7. (0.5h) Demo and sign-off with `John` — Owner: John (PM).
	- Save demo recording/notes to `{project-root}/.bmad/artifacts/STORY-001/step-07-demo`.
8. (0.5h) Validate BMAD workflow and produce validation report — Owner: Bob (SM).
	- Execute `{project-root}/.bmad/core/tasks/validate-workflow.xml` with `workflow` and `artifacts` parameters and save validation report to `{project-root}/.bmad/artifacts/STORY-001/validation-report.md`.

## Test Cases
- Given a persona with a `generated_images` media item, when I visit `/personas/{id}/gallery`, then I see the thumbnail and can open the preview.
- Given storage is private, when I request download, then the server returns a temporary signed URL.

## Risks & Notes
- MediaLibrary is already used by the project; this PR must respect existing collections and not alter collection names.
- Large galleries may require pagination or incremental loading; implement server-side pagination if needed.

## Related
- `docs/PERSONA_GALLERY_UI.md`
- `resources/views/components/persona_gallery_example.blade.php`


