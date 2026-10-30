# Experienced Laravel Developer Technical Assessment

## Candidate Notes

### What you would improve with more time
- IMPORTANT: Use AI less when faced with unknowns, as I still do not grasp everything but had to use it since it is not prohibited, and I just did not have the time to rummage around for answers. If that eliminates me, then so be it, I did learn a lot about how to work with Laravel.

- Integrate Redis for rate-limiting and atomic locks (Cache::lock()).
- Add idempotency key headers (X-Idempotency-Key) for public pledge submissions.
- Set up PHPStan for static analysis.


### Summary of the implementation
The fundraising API handles Cause creation and management by administrators, as well as Pledge submissions by public visitors. 
When total pledges meet or exceed a Cause's funding goal, the system automatically transitions the Cause to inactive, dispatches a `GoalReached` event, and queues an administrator notification. 
The public listing endpoint returns paginated Causes ordered with featured items first and newest items within each group, utilizing database-level aggregates to ensure optimal efficiency.

### Architecture decisions
- **Thin Controllers & Form Requests:** Controller methods are kept lightweight, delegating request validation to dedicated `FormRequest` classes (`StoreCauseRequest`, `UpdateCauseRequest`, `StorePledgeRequest`).
- **Domain Events & Queued Listeners:** Business side-effects (notifying admins on goal reach) are decoupled using Laravel's Event-Listener pattern and queued asynchronously.
- **API Resources:** All API responses pass through Laravel `JsonResource` layers (`CauseResource`, `PledgeResource`) to prevent raw model exposure and maintain a predictable API schema.

### Concurrency problem identified
Under high traffic, simultaneous Pledge submissions against the same active Cause could cause a race condition. 
Two concurrent requests reading the current sum of pledges might both determine the goal isn't reached yet, both persist their pledge, and both trigger the goal-reached transition, leading to duplicate notifications or inaccurate state updates.

### Concurrency-control strategy & Why appropriate
- **Database Transactions (`DB::transaction`):** Submissions, sum calculations, and state transitions are executed within an atomic database transaction.
- **Pessimistic Locking (`lockForUpdate()`):** When retrieving the target Cause during a pledge submission, a `SELECT FOR UPDATE` lock is acquired on the Cause row. This serializes concurrent pledge processing for that specific Cause without locking unrelated records, preventing race conditions and ensuring aggregate calculation accuracy.

### Exactly-once event and notification handling
Within the locked database transaction, the total pledged amount is evaluated against `goal_amount`. If the threshold is crossed, the Cause's `is_active` attribute is updated to `false` and saved within the same transaction. The `GoalReached` event is dispatched **only** when `is_active` transitions from `true` to `false`. Subsequent locked requests will read `is_active = false` and skip dispatching the event, guaranteeing exactly-once execution.

### Queue configuration
- **Connection:** Configured using the `database` queue driver for local development without external dependencies (Redis/RabbitMQ).
- **Asynchronous Execution:** The `SendGoalReachedNotification` listener implements `ShouldQueue`, ensuring admin notifications are processed outside the HTTP request/response lifecycle.

### Authorization approach
- **Policy Mapping:** Secured using `CausePolicy` mapped in `AuthServiceProvider` and protected via the `auth:sanctum` middleware on all `/api/admin/*` routes.
- **Role Verification:** Authenticated users are checked against an `is_admin` boolean flag on the `users` table, yielding HTTP `403 Forbidden` for unauthorized attempts.

### N+1 prevention
The public Cause listing endpoint (`GET /api/causes`) utilizes Eloquent's `withSum('pledges', 'amount')` and `withCount('pledges')`. Aggregate metrics are computed via database-level subqueries/joins in a single SQL query, completely eliminating N+1 query issues and in-memory PHP loops.

### Database indexes added
- **Composite Index `(is_featured, created_at)` on `causes`:** Directly optimizes the public listing query (`ORDER BY is_featured DESC, created_at DESC`) and pagination, avoiding full table scans.
- **Foreign Key Index `cause_id` on `pledges`:** Accelerates `SUM` and `COUNT` aggregate joins per Cause.
### Rate-limit configuration and justification
- **Public Endpoints (`/api/pledges`, `/api/causes`):** Restricted using Laravel's `throttle:api` (60 requests/minute per IP) to mitigate abusive traffic, brute-force attempts, and denial-of-service vectors.
- **Admin Endpoints (`/api/admin/*`):** Throttle limits applied per authenticated user ID.

### Duplicate-submission strategy
Pledge submissions require mandatory client validation via Form Requests (`email`, `amount`, `cause_id`). Idempotency at the pledge layer can be further guaranteed by coupling transactional verification with unique client-provided tokens if required.

### API Response design
Structured standard JSON responses wrapped via API Resources with explicit ISO-8601 timestamp formatting, explicit numeric typecasting for monetary values, and standardized Laravel HTTP pagination metadata (`data`, `links`, `meta`).

### Rate limiting
A legitimate donator will only make one at a time, so 5 requests per minute should be enought in case they mess up on the form, and it will block scripts that does auto-spams or denial of services attacks.

### Chosen strategy in order to prevent having multiple pledges
I have choosen to use the header X-Idempotency-Key
How it works:
1. When a client sends a pledge with the X-Idempotency-Key, the server verifies if the response with that key already exists
2. If it exists the server answers with the saved response whitout having to verify the pledge again or whitout touching the DB
3. If it doesn't exists the server treats the request normally and generates a response that goes in the cache for 24h

Combined with the lock already put in place for the Causes, it guarantees a total security of the requests when they arrive at the same time,
which is why I have taken this approach


### Test strategy
Automated integration and unit tests covering:
- Admin authorization and policy enforcement (`403` vs `200`).
- Public listing ordering and aggregate calculations (`total_pledged`, `pledge_count`).
- Pledge validation and automatic Cause deactivation upon reaching goal_amount.
- Event dispatching and queued listener registration.

### Database-specific limitations
- **SQLite:** Used for local testing environment. SQLite locking operates at a broader level compared to row-level locks in PostgreSQL/MySQL (`InnoDB`). In production MySQL/PostgreSQL, `lockForUpdate()` provides full row-level concurrency isolation.

### Assumptions
- Administrative users are provisioned with `is_admin = true`.
- Currency amounts are stored using precise decimal fields (`decimal(10,2)`).

### Trade-offs
- Using pessimistic row locks (`lockForUpdate()`) trades a slight throughput overhead on a single Cause for strict financial accuracy and state consistency.

### Known limitations
- Under SQLite, high write concurrency may encounter database lock contention due to file-level locking behavior.

### Incomplete requirements
As far as I can see, most are fulfilled at this point.

### Setup instructions
1. Clone the repository and install dependencies:
   ```bash
   composer install
   ```

2. Configure environment file:
    ```cp .env.example .env
    php artisan key:generate
    ```

3. Run migrations:
    ```php artisan migrate
    ```

### Test instructions
Run the PHPUnit test suite:
php artisan test



## Overview

Build a production-ready JSON API for a small **Causes and Pledges** platform using Laravel 10.

The repository contains a fresh Laravel installation with no domain-specific implementation.

Your task is to design and implement the required functionality while demonstrating strong Laravel knowledge, architectural judgment, security awareness, database performance, concurrency handling, and automated testing.

## Time limit

The assessment is designed for approximately **5–7 hours of focused work**.

Do not spend more than one working day on it.

You are not expected to build a polished frontend. The focus is the backend implementation, API design, correctness, security, performance, and maintainability.

## Technical environment

* PHP 8.1+
* Laravel 10
* MySQL or SQLite
* Eloquent ORM
* PHPUnit
* Laravel events
* Laravel queues
* Laravel notifications or mailables
* Laravel authorization
* Laravel API Resources

Redis may be used for queues, caching, rate limiting, or locking, but it is not required.

If Redis is not used, document the local alternative and any limitations.

---

## Scenario

The application manages fundraising Causes.

An administrator can create and manage Causes.

Visitors can submit Pledges toward active Causes.

When the total pledged amount reaches or exceeds the Cause goal:

* The Cause becomes inactive.
* A goal-reached event is dispatched.
* An administrator notification is queued.
* This transition must happen exactly once.

The application must also provide a public API endpoint that lists Causes with:

* Featured Causes first
* Newest Causes first within each group
* Total pledged amount
* Pledge count
* Pagination

The implementation must be designed for real production traffic, including simultaneous requests, repeated client requests, abusive traffic, and efficient database access.

---

## Functional requirements

## 1. Causes

Create a `causes` table containing at least:

* `id`
* `title`
* `description`
* `goal_amount`
* `is_active`
* `is_featured`
* timestamps

Recommended types:

* `goal_amount`: decimal
* `is_active`: boolean, default `true`
* `is_featured`: boolean, default `false`

Implement API endpoints for administrators to:

* Create a Cause
* View a Cause
* Update a Cause
* Delete a Cause
* Mark or unmark a Cause as featured

Use Laravel RESTful conventions where appropriate.

---

## 2. Pledges

Create a `pledges` table containing at least:

* `id`
* `cause_id`
* `pledger_name`
* `pledger_email`
* `amount`
* timestamps

Requirements:

* A Cause has many Pledges.
* A Pledge belongs to a Cause.
* Deleting a Cause should delete its Pledges.
* Visitors can submit Pledges toward an active Cause.
* Pledges must not be accepted for an inactive Cause.

Use appropriate foreign keys and database constraints.

---

## 3. Pledge validation

Use a Laravel Form Request class.

Validation must include:

* `pledger_name` is required.
* `pledger_name` has a reasonable minimum length.
* `pledger_email` is required.
* `pledger_email` is a valid email address.
* `amount` is required.
* `amount` is numeric.
* `amount` is greater than zero.

Do not place validation logic directly inside the controller.

Return appropriate JSON validation responses.

---

## 4. Goal completion

After a valid Pledge is created, calculate the total amount pledged toward the Cause.

When the total pledged amount reaches or exceeds `goal_amount`:

* Set `is_active` to `false`.
* Dispatch a domain event such as `CauseGoalReached`.
* Queue an administrator notification or mailable.

The goal-reached transition must happen exactly once.

A Cause that has already reached its goal must not:

* Be deactivated repeatedly.
* Dispatch the event repeatedly.
* Send duplicate goal-reached notifications.

---

## 5. Concurrency handling

The pledge creation flow must be safe when multiple Pledges are submitted against the same Cause at nearly the same time.

For example, two requests may arrive within milliseconds of each other and both attempt to complete the Cause.

Use an appropriate strategy, such as:

* A database transaction with `lockForUpdate()`
* An atomic conditional update
* An application-level lock
* Another clearly justified approach

Your implementation must ensure that the goal-reached transition and notification are triggered exactly once.

Your README must explain:

* The race condition being prevented
* The strategy you selected
* Why it is suitable for this use case
* Any limitations or database-specific behaviour
* How your test demonstrates the fix

A normal single-request test is not sufficient to demonstrate concurrency safety.

---

## 6. Events and queued notifications

Do not send goal-reached email directly from the controller.

Requirements:

* Create a domain event, such as `CauseGoalReached`.
* Handle it with a listener.
* The listener must implement `ShouldQueue`.
* Send a dedicated Laravel Notification or Mailable.
* Do not use inline `Mail::raw()`.
* The Pledge API response must not wait for the email provider.

Tests must use:

```php
Event::fake();
Queue::fake();
Notification::fake();
```

or the appropriate Laravel fake for the selected design.

Automated tests must not send real emails.

---

## 7. Featured Causes

Authorized administrators must be able to mark or unmark a Cause as featured.

Requirements:

* Use the `is_featured` column.
* Only authorized users can change it.
* Unauthorized users must receive HTTP `403`.
* Do not authorize using direct role-string comparisons.

Do not use logic such as:

```php
$user->role === 'admin'
```

Use one of:

* A Laravel Policy
* Laravel Gates
* Spatie Laravel Permission

A Laravel Policy is preferred unless you choose Spatie and explain why.

Add tests for both authorized and unauthorized users.

---

## 8. Public Cause listing

Create a public API endpoint that returns Causes.

The ordering must be:

```text
is_featured DESC, created_at DESC
```

Each Cause should include at least:

* `id`
* `title`
* `description`
* `goal_amount`
* `is_active`
* `is_featured`
* `total_pledged`
* `pledge_count`
* `created_at`

Requirements:

* Use pagination.
* Use Eloquent.
* Use `withSum()`, `withCount()`, or another efficient aggregate query.
* Do not run separate aggregate queries for every Cause.
* Do not calculate totals with PHP loops.
* Query count must not grow linearly with the number of Causes returned.

Add an appropriate database index for the listing query.

Your README must explain:

* Which index you added
* Which query it supports
* Why it is useful

---

## 9. API Resources

Use Laravel API Resource classes.

Requirements:

* Do not return raw Eloquent models.
* Define a stable JSON response structure.
* Avoid exposing internal or sensitive columns.
* Use a Cause Resource for Cause responses.
* Use a Pledge Resource for Pledge responses.
* Include pagination metadata for list responses.

The API structure should remain consistent across endpoints.

---

## 10. Rate limiting

Protect the Pledge creation endpoint against abusive or accidental repeated traffic.

You may use:

* Laravel `throttle` middleware
* A named Laravel RateLimiter
* Per-IP limiting
* Per-email limiting
* A combination of IP and email

Requirements:

* Requests below the limit are accepted.
* Requests above the limit return HTTP `429`.
* The selected rate limit must be documented and justified.
* Add an automated test covering the rate limit.

Do not select an arbitrary number without explaining the reasoning.

---

## 11. Duplicate submission protection

Clients may retry a request after a timeout.

The API must prevent an identical retry from creating multiple Pledges unintentionally.

Implement one reasonable strategy, such as:

* An `Idempotency-Key` request header
* A unique request identifier
* A database-backed idempotency record
* A short-lived deduplication mechanism
* A unique database constraint based on supplied request data

Requirements:

* Repeating the same request identifier must not create another Pledge.
* The response to a repeated request should be predictable.
* The implementation must be safe under simultaneous duplicate requests.
* Add an automated test for duplicate submission handling.

Document:

* The selected approach
* The lifetime of the idempotency record
* The response returned for repeated requests
* Any cleanup strategy
* Any trade-offs

---

## 12. Database performance

Use appropriate database design and query techniques.

Requirements:

* Foreign-key index for `pledges.cause_id`
* Index supporting featured and date ordering
* Efficient aggregation
* Pagination
* No N+1 query pattern
* No manual PHP aggregation
* No string-concatenated raw SQL

You may add additional indexes where justified.

Avoid creating unnecessary indexes without explaining their purpose.

---

## 13. Automated tests

Add PHPUnit feature or integration tests covering at least the following scenarios.

### Cause management

* An authorized user can create a Cause.
* An unauthorized user cannot create or modify a Cause.
* An authorized user can update featured status.
* An unauthorized user receives HTTP `403`.

### Pledge validation

* A valid Pledge is accepted.
* A zero amount is rejected.
* A negative amount is rejected.
* An invalid email address is rejected.
* A Pledge cannot be created for an inactive Cause.

### Goal completion

* A valid Pledge is stored.
* A Pledge that reaches the goal deactivates the Cause.
* The goal-reached event is dispatched.
* The notification is queued.
* No real email is sent during tests.

### Concurrency

Create a test that demonstrates competing Pledge processing against the same Cause.

The test must prove that:

* The Cause is deactivated correctly.
* The goal-reached event is dispatched exactly once.
* The notification is queued exactly once.
* The test would fail if the concurrency protection were removed.

Explain any limitations of concurrency testing with SQLite.

If your concurrency test requires MySQL or another database engine, document how to run it.

### Cause listing

* Featured Causes appear before non-featured Causes.
* Causes within each group are ordered newest first.
* `total_pledged` is correct.
* `pledge_count` is correct.
* Pagination is returned.
* The endpoint does not produce an N+1 query pattern.

You may check query count using:

```php
DB::enableQueryLog();
```

or another reasonable approach.

### Rate limiting

* Requests below the configured limit succeed.
* Requests above the configured limit return HTTP `429`.

### Idempotency

* Reusing the same idempotency key does not create a duplicate Pledge.
* Two simultaneous requests with the same idempotency key do not create two Pledges.

Run all tests with:

```bash
php artisan test
```

---

## Development expectations

Your implementation should demonstrate professional Laravel engineering practices.

We expect:

* Laravel naming conventions
* RESTful API design
* Form Request validation
* Route model binding
* Eloquent relationships
* Thin controllers
* Clear business logic
* Transactions where appropriate
* Explicit concurrency handling
* Events and queued listeners
* Policies or permissions
* API Resource classes
* Pagination
* Rate limiting
* Database indexing
* Automated tests
* Incremental Git commits
* Clear documentation

Apply:

* SOLID
* DRY
* KISS

Use these principles with judgment.

Additional classes and abstractions are not automatically better architecture.

Each abstraction should solve a clear problem.

---

## Constraints

Do not:

* Use string-concatenated raw SQL.
* Put validation directly inside controllers.
* Compare raw role strings for authorization.
* Send email synchronously inside controllers.
* Return raw Eloquent models from API endpoints.
* Calculate Pledge totals using PHP loops.
* Execute aggregate queries separately for each Cause.
* Ignore concurrent requests.
* Ignore duplicate submissions.
* Put all work into one final Git commit.
* Rewrite Laravel functionality unnecessarily.
* Introduce a Repository, Interface, Service, DTO, and event layer for every simple operation.

Caching is optional.

If caching is added, explain:

* What is cached
* Cache duration
* Cache key structure
* Invalidation strategy
* How stale totals are prevented

Incorrect caching is worse than no caching.

---

## Optional improvement

Complete no more than one optional improvement after all required work is finished.

Possible examples:

* Cache the public Cause listing with reliable invalidation.
* Add soft deletes for Causes.
* Add a CSV export of Pledges.
* Add structured API error responses.
* Add OpenAPI documentation.
* Add a health-check endpoint.
* Add Docker configuration.
* Add a queue-failure recovery strategy.

Optional work will only be considered after the required implementation and tests are complete.

---

## Setup

Clone the repository and enter the project directory.

Create your environment file:

```bash
cp .env.example .env
```

Install dependencies:

```bash
composer install
```

Generate the application key:

```bash
php artisan key:generate
```

Configure your database in `.env`.

Run migrations:

```bash
php artisan migrate
```

Run database seeders if you add them:

```bash
php artisan db:seed
```

Start the development server:

```bash
php artisan serve
```

Run the tests:

```bash
php artisan test
```

If using a non-synchronous queue driver, start a worker:

```bash
php artisan queue:work
```

---

## Submission

Submit either:

* A link to your private GitHub repository, or
* A ZIP file containing the `.git` directory and commit history

Do not include:

* `.env`
* `vendor`
* Production credentials
* API keys
* Secrets
* Generated cache files

Your Git history should show the progression of your work.

Suggested logical commit areas include:

* Database schema and models
* Cause API
* Pledge API
* Concurrency handling
* Events and queued notification
* Authorization
* Listing and query optimization
* API Resources
* Rate limiting
* Idempotency
* Tests
* Documentation

You do not have to use these exact commit boundaries.

Use meaningful commits that match your implementation.

---

## README update required before submission

Add a section titled:

```text
Candidate Notes
```

Include:

* Summary of your implementation
* Architecture decisions
* Concurrency problem identified
* Concurrency-control strategy
* Why the selected strategy is appropriate
* Exactly-once event and notification handling
* Queue configuration
* Authorization approach
* N+1 prevention
* Database indexes added
* Rate-limit configuration and justification
* Duplicate-submission strategy
* API response design
* Test strategy
* Database-specific limitations
* Assumptions
* Trade-offs
* Known limitations
* Incomplete requirements
* Setup instructions
* Test instructions
* What you would improve with more time

---

## Important note

There is no single required architecture for this assessment.

Your solution should be:

* Correct
* Secure
* Concurrency-safe
* Maintainable
* Testable
* Efficient
* Easy to understand
* Proportionate to the problem

Be prepared to explain your decisions.
