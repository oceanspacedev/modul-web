# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A Laravel 13 (PHP 8.3+) training/learning portal: trainers run trainings (pretest → session → attendance → evaluation → posttest), manage videos, documents and learning modules with quizzes, and participants take part through a WhatsApp-delivered portal link. Server-rendered Blade only (no JS framework; Vite just builds `resources/css/app.css`). UI text, comments, route names and many identifiers are in Indonesian (`divisi`, `panduan`, `modul-saya`, `rekap-nilai`); keep new user-facing text in Indonesian.

Note: `C:\Users\AHTAR\AGENTS.md` (pulled in by the parent `CLAUDE.md`) contains Next.js agent rules. They do not apply to this repo.

## Commands

```bash
composer setup                 # install, copy .env, key:generate, migrate, npm install + build
composer dev                   # local dev (php artisan dev)
composer test                  # config:clear + php artisan test (full suite)
php artisan test --filter=TrainingEvaluationTest                # one test class
php artisan test --filter='TrainingEvaluationTest::test_name'   # one test method
vendor/bin/pint                # format (CI runs `vendor/bin/pint --test`)
npm run build                  # build assets
```

CI (`.github/workflows/ci.yml`) runs on PHP 8.3/8.4/8.5: `composer validate --strict`, `composer audit`, `pint --test`, `composer test`, then `config:cache`, `route:cache`, `view:cache` must succeed (so no closures in routes/config that break caching). The asset job runs `npm audit` and `npm run build`.

## Branches and commits

- Work branches come from `staging` (`feat/<scope>`, `fix/<scope>`, `docs/<scope>`) and PR into `staging`. `main` is production and only accepts PRs **from `staging`** (enforced by `.github/workflows/pr-to-main.yml`). Never target `main` directly.
- Commit subjects are conventional-style (`feat:`, `fix:`, `docs:`), written in plain English describing the user-visible effect, with a body explaining what changed and why.

## Tests

- `tests/TestCase.php` uses `RefreshDatabase` and seeds `Tests\Support\TestDatabaseSeeder` (area/divisi id 1, roles and permissions, an `admin` user). It **refuses to run** unless the DB is in-memory SQLite (set in `phpunit.xml`), so don't point tests at MySQL.
- `Http::preventStrayRequests()` is on and the WhatsApp gateway is faked to `whatsapp.test`; any other outgoing HTTP (e.g. Gemini) must be faked in the test.
- Queue is `sync` in tests, so background jobs run inline.
- Nearly all coverage is feature tests in `tests/Feature/` (training lifecycle, quiz modes, portal journey, imports/exports, AI, security).

## Architecture

### Access control (three layers)
1. **Spatie permissions**: permission names are seeded in `database/seeders/RoleAndPermissionSeeder.php` and labelled in `RoleController`. Routes use `can:<permission>` middleware (`manage-master-data`, `manage-roles`, `view-dashboard`, `view-learning-modules`, ...). `User::TRAINER_PERMISSIONS` groups the trainer abilities.
2. **Policies** (`app/Policies`) authorize Training, Video, Document, LearningModule, User and VideoComment resources.
3. **Data scope**: `DataAccessService` limits which employees a user can see by business unit (`areas` / `users.area_id`). A super admin is ADMIN with `data_access = all`. ADMIN with `selected` sees only areas in `user_area_access` plus their team. Non-admins see only their team via `ApprovalScopeService` (approval line, direct reports + one level down). This mirrors the sibling DnD app.

`Gate::before` in `AppServiceProvider` grants every ability to `isAdministrator()` users. Browser 403s are turned into a redirect back with an Indonesian flash message (`bootstrap/app.php`).

### Training flow
`Training` (`app/Models/Training.php`) is the core model. Participant steps are `PORTAL_STEPS` (pretest, session, attendance, evaluation, posttest), and their order is configurable per training. `TIMED_STEPS` (pretest, posttest, attendance, evaluation) all share one pattern: the trainer opens a step now or schedules it, with a closing time, a duration, or no limit, and can extend, retime or lock it. When you add or change a timed step, keep the trainer session controls, the trainer checklist and the participant portal consistent (see `TrainingJourneyTest`).

- Trainer side: `TrainingController` plus the nested `training/{training}/...` routes (questions, evaluation questions, toggles, resets, exports, Zoom off-cam scan).
- Participant side: `TrainingPortalController` under `training/portal/{token}`. It is **public**, authenticated by the token in the WhatsApp link, rate-limited (`portal` limiter), and the page polls `status` every 5s. Pretest reuses the quiz actions with `phase=pretest`.
- `app/Support/QuizRules.php` defines the quiz rules shared by video, document, learning-module and training quizzes: attempt limits, duration choices and what participants see after they finish. Evaluation form logic lives in `app/Support/TrainingEvaluationForm.php`.
- `php artisan training:simulate-dummy {training_id?}` fills a training with dummy participants for manual testing.

### Learning modules (one controller, three contexts)
`LearningModuleController` / `LearningModulePortalController` / `LearningModuleQuestionController` serve standalone modules (`learning-module`, `modul-saya`), video quizzes (`video/kuis/{module}`) and document quizzes (`document/kuis/{module}`). `EnsureLearningModuleContext` middleware keeps each module on its own context's URLs. Module items are polymorphic via a morph map (`video` / `document`) registered in `AppServiceProvider`. Modules have `formal` and `game` modes.

### Background work and memory limits
- Web requests are capped at **128MB** (`public/index.php`). Anything heavy (Excel import/export, AI, DnD sync) must go to a queued job in `app/Jobs`, not run in the request.
- `BackgroundTask` is the base for heavy imports/exports. It runs on the `heavy` queue on the AI connection, is unique per user and kind, and reports back through the `BackgroundTaskFinished` notification (in-app inbox, WhatsApp, email).
- `AiTask` is the base for Gemini jobs whose result the page polls. It creates a cache token (`newToken()`), the job writes its result to the cache, and the page polls an `ai-status/{token}` route. Payloads are encrypted.
- `config('queue.ai_connection')` resolves to `redis-long` in production (redis) and to the default connection locally or in tests. Horizon (`config/horizon.php`) runs `supervisor-1` (default, 128MB), `supervisor-ai` (ai, 256MB) and `supervisor-heavy` (heavy, 512MB). The `WorkerStarting` listener applies the supervisor memory as PHP `memory_limit`.

### External integrations
- **WhatsApp** (WAGHub): `WhatsAppService`, `Notifications/Channels/WhatsAppChannel`, `Support/WhatsAppNumber`. Used for OTP login, training invitations, reminders and task notifications.
- **Gemini**: `GeminiQuestionService` (generate and revise quiz questions from material), `ZoomAttendanceAiService` (detect Zoom off-cam participants), `MaterialQuizService`.
- **DnD** (sibling HR app): `DndUserSyncService` / `SyncDndUsersCommand` sync employees nightly (`DND_SYNC_TIME`).
- Uploads go to `FILESYSTEM_DISK` (`public` or `s3`, never `local`). Build URLs through `Support/StorageUrl`.

### Scheduled commands (`routes/console.php`)
`training:send-reminders` (every 15 min), `dnd:sync-users` (daily), `horizon:snapshot`, `notifications:prune`. Horizon and `/log-viewer` are restricted to administrators.

### Conventions worth knowing
- `Model::preventLazyLoading()` is on outside production, and violations are logged rather than thrown. Eager-load relations.
- Excel import/export uses `maatwebsite/excel` with classes in `app/Exports` and `app/Imports`. Recap exports follow each training's step order and questions (`TrainingStepColumns`).
- Locale `id`, timezone `Asia/Jakarta`. Pint uses the `laravel` preset and excludes `lang/`.
