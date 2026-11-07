# API contract

This is the agreement between the API, the website and the mobile app. If the code and this file disagree, one of them is wrong, so change them together.

## The basics

- Every path starts with `/api`.
- Requests and responses are JSON. Send `Content-Type: application/json` with a body.
- Dates are UTC in ISO 8601, such as `2026-11-06T09:30:00Z`.
- To act as a signed-in user, send `Authorization: Bearer <token>`.

A successful response wraps its result in `data`:

```json
{ "data": { "id": 1, "name": "Test Learner" } }
```

A failed response wraps its details in `error`. The `fields` key appears only when `code` is `validation_failed`:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Check the highlighted fields.",
    "fields": { "email": ["Enter a valid email address."] }
  }
}
```

Clients should act on `code` and show `message`.

`OPTIONS` on any path returns `204` with no body, for local CORS preflights. A wrong method on a real path returns `404 not_found`. `Content-Type` is not checked.

| Status | `code` | When |
| --- | --- | --- |
| 400 | `invalid_json` | The body is not a JSON object |
| 401 | `unauthenticated` | The token is missing, wrong, expired or revoked |
| 401 | `invalid_credentials` | Login failed |
| 403 | `forbidden` | Signed in, but not allowed to do this |
| 404 | `not_found` | No such endpoint or record, or the course is not published |
| 409 | `email_taken` | That email is already registered |
| 409 | `course_published` | This change is not allowed while the course is published |
| 409 | `in_use` | Learner records depend on this lesson or question |
| 422 | `validation_failed` | A field is missing or invalid |
| 500 | `server_error` | Something went wrong on the server. Details go to the log, never the response |

## Who can call what

| Area | Endpoints | Access |
| --- | --- | --- |
| Health | `GET /api/health` | Public |
| Accounts | `POST /api/auth/register`, `POST /api/auth/login` | Public |
| Accounts | `POST /api/auth/logout`, `GET /api/me` | Signed in |
| Courses | `GET /api/courses`, `GET /api/courses/{slug}` | Public |
| Progress | `POST /api/lessons/{id}/complete`, `GET /api/me/progress` | Signed in |
| Quizzes | `GET /api/courses/{slug}/quiz`, `POST /api/courses/{slug}/attempts` | Signed in |
| Certificates | `GET /api/me/certificates` | Signed in |
| Certificates | `GET /api/certificates/verify/{code}` | Public |
| Admin | Everything under `/api/admin` | Admin only |

A learner calling an admin endpoint gets a 403. Without a valid token, any signed-in or admin endpoint returns a 401.

## Health

### `GET /api/health`

`200`:

```json
{ "data": { "status": "ok" } }
```

## Accounts

### `POST /api/auth/register`

```json
{ "name": "Test Learner", "email": "learner@example.com", "password": "correct-horse-battery" }
```

- `name`: 1-100 characters after trimming.
- `email`: a valid address. It is stored in lowercase, so `A@example.com` and `a@example.com` are the same account.
- `password`: 15-72 bytes. It is never stored or logged, only its hash.
- Any other field, including `role`, is ignored.

`201`:

```json
{ "data": { "id": 1, "name": "Test Learner", "email": "learner@example.com", "role": "learner" } }
```

Errors: `400 invalid_json`, `409 email_taken`, `422 validation_failed`.

### `POST /api/auth/login`

```json
{ "email": "learner@example.com", "password": "correct-horse-battery" }
```

`200`. The token is shown only here, and it expires after 7 days:

```json
{
  "data": {
    "token": "64-character-hex-string",
    "expires_at": "2026-11-13T09:30:00Z",
    "user": { "id": 1, "name": "Test Learner", "email": "learner@example.com", "role": "learner" }
  }
}
```

An unknown email and a wrong password both return `401 invalid_credentials` with the same message, so nobody can use login to find out who has an account.

Errors: `400 invalid_json`, `401 invalid_credentials`, `422 validation_failed`.

### `POST /api/auth/logout`

Revokes the token used for this request. `204` with no body.

### `GET /api/me`

`200`:

```json
{ "data": { "id": 1, "name": "Test Learner", "email": "learner@example.com", "role": "learner" } }
```

## Courses

Learners and the public see published courses only. An unpublished course returns `404 not_found`.

### `GET /api/courses`

`200`, sorted by title, ignoring capitals:

```json
{
  "data": [
    {
      "slug": "passwords-and-2fa",
      "title": "Passwords and 2FA",
      "summary": "Make your accounts much harder to break into.",
      "lesson_count": 3
    }
  ]
}
```

### `GET /api/courses/{slug}`

`200`, with lessons sorted by `position`:

```json
{
  "data": {
    "slug": "passwords-and-2fa",
    "title": "Passwords and 2FA",
    "summary": "Make your accounts much harder to break into.",
    "pass_mark": 70,
    "lessons": [
      {
        "id": 1,
        "position": 1,
        "title": "Why passwords get stolen",
        "blocks": [
          { "type": "h2", "text": "It is rarely a lucky guess" },
          { "type": "p", "text": "Many stolen passwords come from another site's data breach." },
          { "type": "tip", "text": "Use a different password on every site." }
        ]
      }
    ]
  }
}
```

### Lesson blocks

A lesson is a list of blocks, never HTML. Both clients draw each block as plain text, so nothing in a lesson can run as code.

| `type` | Shown as |
| --- | --- |
| `h2` | A heading |
| `p` | A paragraph |
| `tip` | A highlighted tip |

Each block has a `text` of 1-5000 characters.

## Progress

### `POST /api/lessons/{id}/complete`

No body. Calling it twice is safe, and the original `completed_at` is kept.

`200`:

```json
{ "data": { "lesson_id": 1, "completed_at": "2026-11-06T09:30:00Z" } }
```

Errors: `401 unauthenticated`, and `404 not_found` if the lesson does not exist or its course is not published.

### `GET /api/me/progress`

`200`, listing each published course where the user has completed a lesson or submitted an attempt. Courses they have not started are left out:

```json
{
  "data": [
    {
      "course_slug": "passwords-and-2fa",
      "course_title": "Passwords and 2FA",
      "lesson_count": 3,
      "completed_lesson_ids": [1, 2],
      "best_score": 80,
      "passed": true
    }
  ]
}
```

`best_score` is `null` and `passed` is `false` until the user submits an attempt.

## Quizzes

A learner can take a quiz at any time, as often as they like. They do not have to finish the lessons first.

### `GET /api/courses/{slug}/quiz`

`200`, with questions and options sorted by `position`. This response must never say which option is correct:

```json
{
  "data": {
    "course_slug": "passwords-and-2fa",
    "pass_mark": 70,
    "questions": [
      {
        "id": 1,
        "position": 1,
        "prompt": "What should you do if a site you use has a data breach?",
        "options": [
          { "id": 1, "text": "Change that password, and anywhere else you used it" },
          { "id": 2, "text": "Nothing, unless money goes missing" }
        ]
      }
    ]
  }
}
```

### `POST /api/courses/{slug}/attempts`

Include every question exactly once:

```json
{ "answers": [ { "question_id": 1, "option_id": 1 } ] }
```

The server scores the attempt and saves it in one transaction. It never accepts a score from the client. `score` is a percentage from 0 to 100, rounded down, and the learner passes when `score` is at least `pass_mark`.

`201`:

```json
{
  "data": {
    "id": 1,
    "score": 100,
    "passed": true,
    "results": [ { "question_id": 1, "correct": true } ],
    "certificate": { "code": "7F3A9C21B04E6D18" }
  }
}
```

`certificate` is `null` when the learner did not pass. Passing the same course again returns the certificate they already have.

Errors: `400 invalid_json`, `401 unauthenticated`, `404 not_found`, and `422 validation_failed` when a question is missing or repeated, or an option does not belong to its question.

## Certificates

### `GET /api/me/certificates`

`200`, newest first:

```json
{
  "data": [
    {
      "code": "7F3A9C21B04E6D18",
      "course_slug": "passwords-and-2fa",
      "course_title": "Passwords and 2FA",
      "issued_at": "2026-11-06T09:30:00Z"
    }
  ]
}
```

### `GET /api/certificates/verify/{code}`

Anyone with the code can check a certificate. It shows the learner's name, but never their email.

`200`:

```json
{
  "data": {
    "code": "7F3A9C21B04E6D18",
    "learner_name": "Test Learner",
    "course_title": "Passwords and 2FA",
    "issued_at": "2026-11-06T09:30:00Z"
  }
}
```

An unknown code returns `404 not_found`. A certificate stays valid and listed if its course is later unpublished, because the learner earned it. Codes are 16 uppercase hex characters from `random_bytes(8)`, so they cannot be guessed.

## Admin

No endpoint can create an admin. The first one comes from running `php bin/make-admin.php <email>` with access to the server.

Admin endpoints use numeric IDs, and they can see unpublished courses. Lessons and questions in these responses include the same fields as the learner endpoints, and options also include `is_correct`.

### Courses

| Endpoint | Body | Success |
| --- | --- | --- |
| `GET /api/admin/courses` | | `200`: all courses, each with `id`, `slug`, `title`, `summary`, `pass_mark`, `published_at` |
| `POST /api/admin/courses` | `slug`, `title`, `summary`, `pass_mark` | `201`: the course |
| `GET /api/admin/courses/{id}` | | `200`: the course with its `lessons` and `questions` |
| `PATCH /api/admin/courses/{id}` | Any of `title`, `summary`, `pass_mark` | `200`: the course |
| `POST /api/admin/courses/{id}/publish` | | `200`: the course |
| `POST /api/admin/courses/{id}/unpublish` | | `200`: the course |

- `slug`: unique lowercase letters, digits and hyphens, up to 100 characters. A repeated slug returns `422 validation_failed`. It cannot change once created, so a `slug` sent to `PATCH` is ignored.
- `title`: 1-150 characters.
- `summary`: 1-500 characters.
- `pass_mark`: a whole number from 1 to 100.
- Publishing a course with no lessons or no questions returns `422 validation_failed`. Publishing twice returns `200` and keeps the first `published_at`.

### Lessons

| Endpoint | Body | Success |
| --- | --- | --- |
| `POST /api/admin/courses/{id}/lessons` | `position`, `title`, `blocks` | `201`: the lesson |
| `PATCH /api/admin/lessons/{id}` | Any of `position`, `title`, `blocks` | `200`: the lesson |
| `DELETE /api/admin/lessons/{id}` | | `204` |

- `title`: 1-150 characters.
- `position`: a whole number from 1, unique within the course.
- `blocks`: at least one block, in the format under [Lesson blocks](#lesson-blocks).

### Questions

| Endpoint | Body | Success |
| --- | --- | --- |
| `POST /api/admin/courses/{id}/questions` | `position`, `prompt`, `options` | `201`: the question |
| `PATCH /api/admin/questions/{id}` | Any of `position`, `prompt`, `options` | `200`: the question |
| `DELETE /api/admin/questions/{id}` | | `204` |

- `prompt`: 1-500 characters.
- `position`: a whole number from 1, unique within the course.
- `options`: 2-6 items, each with `text` (1-300 characters) and `is_correct`. Exactly one must be correct. Their order sets each option's position.

All text fields are trimmed before their lengths are checked.

### Changing a published course

To keep past attempts meaningful, these return `409 course_published` while the course is published. Unpublish it first:

- Deleting a lesson or question
- Adding a question
- Changing a question's `options`
- Changing the course's `pass_mark`, because saved attempts and certificates were judged against it

The usual checks come first: signing in, then finding the record, then a `422` for a bad body, then this `409`.

Learner records must keep their meaning, so these return `409 in_use`, even while the course is unpublished:

- Deleting a lesson that a learner has completed
- Deleting a question that has saved answers
- Changing the `options` of a question that has saved answers
