# Data model

The training release uses SQLite. This is the schema the migrations build up to, one table at a time as the issues need it.

SQLite ignores foreign keys unless each connection turns them on, so `Database::connection()` must run `PRAGMA foreign_keys = ON`.

Dates are stored as UTC text, such as `2026-11-06T09:30:00Z`. The database fills in the time a row was created, so inserts can leave those columns out.

```sql
CREATE TABLE users (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'learner' CHECK (role IN ('learner', 'admin')),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

CREATE TABLE auth_tokens (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,
  expires_at TEXT NOT NULL,
  revoked_at TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);
CREATE INDEX auth_tokens_user_id ON auth_tokens (user_id);

CREATE TABLE courses (
  id INTEGER PRIMARY KEY,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  summary TEXT NOT NULL,
  pass_mark INTEGER NOT NULL CHECK (pass_mark BETWEEN 1 AND 100),
  published_at TEXT,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

CREATE TABLE lessons (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses (id),
  position INTEGER NOT NULL,
  title TEXT NOT NULL,
  blocks TEXT NOT NULL,
  UNIQUE (course_id, position)
);

CREATE TABLE lesson_completions (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  lesson_id INTEGER NOT NULL REFERENCES lessons (id),
  completed_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
  UNIQUE (user_id, lesson_id)
);
CREATE INDEX lesson_completions_lesson_id ON lesson_completions (lesson_id);

CREATE TABLE questions (
  id INTEGER PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses (id),
  position INTEGER NOT NULL,
  prompt TEXT NOT NULL,
  UNIQUE (course_id, position)
);

CREATE TABLE options (
  id INTEGER PRIMARY KEY,
  question_id INTEGER NOT NULL REFERENCES questions (id) ON DELETE CASCADE,
  position INTEGER NOT NULL,
  text TEXT NOT NULL,
  is_correct INTEGER NOT NULL DEFAULT 0 CHECK (is_correct IN (0, 1)),
  UNIQUE (question_id, position)
);

CREATE TABLE attempts (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  course_id INTEGER NOT NULL REFERENCES courses (id),
  score INTEGER NOT NULL CHECK (score BETWEEN 0 AND 100),
  passed INTEGER NOT NULL CHECK (passed IN (0, 1)),
  submitted_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);
CREATE INDEX attempts_user_course ON attempts (user_id, course_id);
CREATE INDEX attempts_course_id ON attempts (course_id);

CREATE TABLE attempt_answers (
  id INTEGER PRIMARY KEY,
  attempt_id INTEGER NOT NULL REFERENCES attempts (id) ON DELETE CASCADE,
  question_id INTEGER NOT NULL REFERENCES questions (id),
  option_id INTEGER NOT NULL REFERENCES options (id),
  UNIQUE (attempt_id, question_id)
);
CREATE INDEX attempt_answers_question_id ON attempt_answers (question_id);
CREATE INDEX attempt_answers_option_id ON attempt_answers (option_id);

CREATE TABLE certificates (
  id INTEGER PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
  course_id INTEGER NOT NULL REFERENCES courses (id),
  code TEXT NOT NULL UNIQUE,
  issued_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
  UNIQUE (user_id, course_id)
);
CREATE INDEX certificates_course_id ON certificates (course_id);
```

The migration runner also keeps its own `schema_migrations` table, recording which migrations have run.

## Rules the schema protects

| Rule | How |
| --- | --- |
| No two accounts share an email | `users.email` is `UNIQUE` |
| The database never holds a usable token | Only `auth_tokens.token_hash` is stored, the SHA-256 hash of the token |
| Completing a lesson twice has no extra effect | `UNIQUE (user_id, lesson_id)` |
| A learner has one certificate per course | `UNIQUE (user_id, course_id)` |
| An attempt answers each question once | `UNIQUE (attempt_id, question_id)` |
| A lesson or question cannot be deleted while answers or completions use it | The foreign keys have no `ON DELETE CASCADE` on those references |

The schema cannot check that a question has exactly one correct option, because that rule spans several rows. The API checks it.
