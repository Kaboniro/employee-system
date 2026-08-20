-- Employee System auth schema (PostgreSQL)

CREATE TABLE IF NOT EXISTS users (
    id                    SERIAL PRIMARY KEY,
    name                  VARCHAR(255) NOT NULL,
    department            VARCHAR(255) NOT NULL,
    phone                 VARCHAR(20)  NOT NULL,
    email                 VARCHAR(255) NOT NULL UNIQUE,
    password_hash         VARCHAR(255) NOT NULL,
    role                  VARCHAR(20)  NOT NULL DEFAULT 'employee' CHECK (role IN ('employee', 'admin')),
    failed_login_attempts SMALLINT     NOT NULL DEFAULT 0,
    locked_until          TIMESTAMPTZ,
    logged_in_at          TIMESTAMPTZ,
    last_active_at        TIMESTAMPTZ,
    created_at            TIMESTAMPTZ  NOT NULL DEFAULT now(),
    edited_at             TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS mfa_codes (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash  VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ  NOT NULL,
    attempts   SMALLINT     NOT NULL DEFAULT 0,
    used       BOOLEAN      NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_mfa_codes_user_id ON mfa_codes(user_id);

-- One-time magic login links (used for the "forgot password" email flow,
-- and for the initial invite when an admin creates an employee account).
CREATE TABLE IF NOT EXISTS login_tokens (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ  NOT NULL,
    used       BOOLEAN      NOT NULL DEFAULT false,
    created_at TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_login_tokens_user_id ON login_tokens(user_id);

-- Tasks form a shared board: every employee can see and complete every task,
-- but only the creator (user_id) can edit or delete it.
CREATE TABLE IF NOT EXISTS tasks (
    id                  SERIAL PRIMARY KEY,
    user_id             INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title               VARCHAR(255) NOT NULL,
    status              VARCHAR(20)  NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'completed')),
    due_date            DATE,
    supervisor_comment  TEXT,
    completed_by        INTEGER      REFERENCES users(id) ON DELETE SET NULL,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_tasks_user_id ON tasks(user_id);
