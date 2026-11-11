<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Syndromic surveillance (development)

The dedicated **Syndromic Surveillance** page monitors verified residents of Barangay Alabang, Muntinlupa City. It stores signals for internal review only. It does not diagnose disease, certify clinical rules, or confirm outbreaks. This module sends no mail, SMS, Laravel notifications, or external HTTP requests. Existing follow-up SMS functionality is separate and unchanged.

### Setup

Use the existing PHP 8.4 / Laravel 13 / Livewire 3 installation; no new dependencies are required.

```sh
php artisan migrate --no-interaction
npm run build
```

The migration is additive. Existing prototype surveillance alerts and audit events are reused and preserved; old alerts are shown read only to reviewers and excluded from new calculations. Existing intake/model/delivery tables are left intact. Legacy intake records marked synthetic are excluded; free-text-trained model artifacts are not approved structured-symptom definitions and are not imported. Existing residence values become **Unverified** and historical encounter timestamps remain untouched. In patient registration/editing, authorized staff must explicitly confirm **Barangay Alabang, Muntinlupa City** against the patient's address. Unknown and outside-scope residences are excluded. Address changes invalidate an unchanged residence verification; save and verify the updated address before including the patient again. Verification time and account are recorded. No address keyword inference is used.

Administrators create syndrome definitions under **Definitions & settings**, selecting existing structured symptoms and the minimum number of distinct matches. A doctor or authorized administrator reviews and approves each version. An explicit historical application date permits backfilling only previously unclassified encounters. Chief complaints, custom free text, and diagnosis wording are not classification inputs. The doctor's diagnosis remains unchanged; the existing application has no separate suspected/probable/confirmed diagnosis field.

Optional category names can be installed with `php artisan db:seed --class=SyndromeCategorySeeder --no-interaction`. They are inactive and have no mappings or approvals. This seeder creates no patient data and is not run automatically.

Administrators must separately review the statistical configuration for development testing. Until then results are **Insufficient Data**. The shipped thresholds, baseline length, episode gap, coverage requirements, and matching algorithm require local clinical/statistical validation before operational use; saving test settings is not that validation.

### Automated and manual execution

Configure the system scheduler to invoke the following from the project directory every minute:

```sh
php artisan schedule:run
```

On Windows, create a recurring Task Scheduler task invoking `php.exe` with `artisan schedule:run` and set this project as its working directory. For local testing, `php artisan schedule:work` keeps the scheduler running. Herd already serves the website; do not start an additional HTTP server.

The scheduled `surveillance:detect --scheduled` command checks the database-configured detection interval (60 minutes by default), with scheduler and shared cache locks preventing overlap. The cache backend must be persistent and shared across scheduler/application processes; use the project's database or Redis cache in deployment. Do not use an array cache outside tests. A detection lock expires after 60 minutes; configure execution limits below that duration. Failed runs retain an error record and report details to the application log; related results, classifications, and alerts roll back atomically. Investigate failures and retry; no operations are silently discarded.

The scheduled task performs detection synchronously, so **no queue worker is required for surveillance**. Existing follow-up queue workers remain separate. A manually initiated CLI evaluation is available with `php artisan surveillance:detect` (logged as **Console**); administrators can also use **Run manual detection** (logged as **Manual**, including the initiating account). Scheduled runs are logged as **Scheduled**. Repeated page visits do not run detection.

### Data and model assumptions

- New completed consultations receive a UTC encounter timestamp in the existing queue transaction. A precise timestamp is assigned only when the Manila consultation date matches the visit. Backdated clinical entries remain supported; records with no reliable encounter time are excluded from surveillance. Legacy records qualify only when `created_at` reliably agrees with their Manila consultation date. Missing/contradictory timestamps, missing or mismatched vital-sign relationships, canceled/incomplete queues, invalid diagnoses, unverified residence, and records at or beyond the window end are excluded. Records without structured symptoms do not become syndrome cases and reduce coverage.
- Each consultation/category is classified once, including nonmatches. The snapshot preserves symptom names/IDs, approved rule version, approval identity, and encounter time. Category deactivation stops new classification and detection. Rule changes create immutable new versions; past classifications are not rewritten. Detection uses only the currently approved version, so changed definitions must accumulate sufficient comparable history again. Reliable unclassified historical records may be classified when the approved application date covers them.
- Counts represent illness episodes per patient and syndrome. Consecutive matching encounters separated by less than the configurable gap (72 hours by default) remain one episode; the first encounter timestamps that episode. Exactly the configured gap begins another episode. The same patient may contribute to different syndromes. This is a configurable operational approximation; explicit illness-onset/episode identifiers are not present in the source system. Healthcare personnel must validate this approximation locally.
- The default monitoring interval is `[end - 48 hours, end)`, evaluated at minute precision. Historical windows have identical duration and weekday/time, shifted by whole weeks, and never overlap the current window. The lookback is 12 weeks, requiring at least eight reliable historical periods by default. Missing or low-attendance periods are not inserted as zero observations. Every accepted period must meet minimum encounter attendance and structured classification coverage (defaults five encounters and 80%). Current coverage must also pass. All-zero historical syndrome counts return **Insufficient Data**, avoiding an unjustifiably certain zero-rate model.
- Expected count is the historical syndrome episode rate per eligible encounter multiplied by current eligible encounter attendance. Attendance counts encounters while the numerator counts illness episodes; changes in repeat-visit patterns can affect this approximation. The model assumes a reasonably stable rate across comparable periods. For lookbacks below 60 weeks, only recent weekday effects are considered; annual seasonality cannot be estimated. At 60–104 weeks, comparable weekdays are additionally restricted to within 45 calendar days of the current annual season. This simple seasonal approach and attendance adjustment require local validation and do not model long-term trends or population changes.
- Poisson upper-tail probability is `P(X >= observed)`. A Pearson residual dispersion estimate checks variance beyond Poisson's mean/variance relationship. Above the configured tolerance (1.5 by default), a negative-binomial count model is moment-matched with size `expected / (dispersion - 1)`. Otherwise Poisson is used. Numerically stable regularized gamma/beta functions evaluate tails, with explicit errors for invalid/nonconvergent calculations. Model selection, fitting uncertainty, sparse counts, independence of episodes, and reporting consistency require statistical review. Reference definitions: [NIST Poisson distribution](https://www.itl.nist.gov/div898/handbook/eda/section3/eda366j.htm), [FAO negative-binomial distribution](https://www.fao.org/4/x6602e/x6602e0d.htm).
- Bonferroni adjusts the probability by the number of active, approved syndrome categories in a run. An increase with adjusted probability at or below `alpha` (default 0.01) is **Alert**; at or below `watch_alpha` (default 0.05) is **Watch**; otherwise **Normal**. Reliability failures are **Insufficient Data**. This correction controls comparison multiplicity within a run, not across an unlimited sequence of correlated rolling runs. Neither outcome proves an outbreak. See [CDC's overview of syndromic surveillance](https://www.cdc.gov/mmwr/preview/mmwrhtml/su5301a3.htm) for the role of investigation.

Results store the selected model, raw/adjusted probability, expected/observed counts, approved version, dispersion/size, exact periods, coverage, attendance record IDs, episode classification IDs, residence-verification evidence, and settings snapshot. Trend charts use these saved results; they do not recompute counts. Total eligible patients is a distinct-patient metric for the window, separate from potentially overlapping syndrome episode totals.

Alerts use a rule-and-case-set fingerprint. Re-evaluating the same case set cannot create another alert. Shrinking subsets are suppressed; overlapping signals remain one active investigation while an alert is New, Under Review, or Escalated. Original alert evidence remains unchanged; subsequent detections are retained in the results/trend history. A new disjoint case cluster can create a new alert, and closed investigations can receive a new alert when additional cases appear. Reopening and review actions preserve an append-only review history. Optimistic versions reject stale concurrent review submissions, and foreign keys restrict deletion of referenced source records.

Administrators manage configuration and manual runs. Doctors and administrators approve definitions, inspect individual alert cases, assign reviewers, add investigation notes, and record Under Review, Reviewed, Escalated, Dismissed, or Resolved outcomes. Health staff have aggregate dashboard/alert access only. Every server action rechecks authorization; detailed cases and investigation notes are omitted from staff Livewire responses. Administrative actions store before/after settings or definition evidence in the audit trail. Existing User & Roles account management controls these roles.

### Validation with simulated data

```sh
php artisan test --compact tests/Feature/SyndromicSurveillanceTest.php tests/Unit/SurveillanceStatisticsTest.php
```

Tests use the existing in-memory SQLite configuration and factories with explicit **SIMULATED** labels. They never seed simulated patients into the application database. Coverage includes historical backfill, immutable classification versions, scope and encounter validity, exact window boundaries, repeat illness visits, coverage and zero baselines, normal/watch/alert outcomes, multiplicity, stable Poisson and overdispersed negative-binomial tails, duplicate/overlap prevention, review permissions/history, scheduling, transactional failures, and absence of mail/notifications/HTTP calls. Database migrations and locking should also be exercised against the deployment's MySQL and shared cache before operational rollout.
