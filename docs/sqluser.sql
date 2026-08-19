-- sqluser
-- PostgreSQL queries for inspecting and managing local development users in DBeaver.
-- Direct SQL bypasses Laravel validation, RBAC, token lifecycle, and the final-admin guard.
-- Prefer the User API for normal operations and never run the entire file at once.

-- DBeaver connection:
-- Host: localhost
-- Port: 5433
-- Database: clinic_app
-- Username: clinic
-- Password: secret


-- 1. List users with their assigned roles.
SELECT
    u.id,
    u.name,
    u.email,
    u.is_active,
    r.id AS role_id,
    r.name AS role,
    u.created_at,
    u.updated_at
FROM users AS u
JOIN roles AS r ON r.id = u.role_id
ORDER BY u.id;


-- 2. Find one user by ID.
-- Replace 4 with the target user ID.
SELECT
    u.id,
    u.name,
    u.email,
    u.is_active,
    r.id AS role_id,
    r.name AS role
FROM users AS u
JOIN roles AS r ON r.id = u.role_id
WHERE u.id = 4;


-- 3. Find a user by email.
SELECT
    u.id,
    u.name,
    u.email,
    u.is_active,
    r.name AS role
FROM users AS u
JOIN roles AS r ON r.id = u.role_id
WHERE u.email = 'test.cashier@clinic.test';


-- 4. List the role catalog.
SELECT id, name, display_name
FROM roles
ORDER BY id;


-- 5. Create a local test user.
-- This copies the password hash from test.receptionist2@clinic.test.
-- The new account password is therefore Password@123.
-- Change the email before running the statement again because users.email is unique.
INSERT INTO users (
    role_id,
    name,
    email,
    password,
    is_active,
    created_at,
    updated_at
)
SELECT
    (SELECT id FROM roles WHERE name = 'RECEPTIONIST'),
    'DBeaver Test User',
    'dbeaver.user@clinic.test',
    source_user.password,
    TRUE,
    NOW(),
    NOW()
FROM users AS source_user
WHERE source_user.email = 'test.receptionist2@clinic.test'
RETURNING id, name, email, role_id, is_active;


-- 6. Update a user's name and email.
-- Replace user ID 5 and the email with the intended values.
UPDATE users
SET
    name = 'Updated DBeaver User',
    email = 'updated.dbeaver@clinic.test',
    updated_at = NOW()
WHERE id = 5
RETURNING id, name, email, is_active;


-- 7. Assign a role by its stable role name.
-- Never use this statement to demote an ADMIN; use PATCH /api/users/{id} instead.
UPDATE users
SET
    role_id = (
        SELECT id
        FROM roles
        WHERE name = 'DOCTOR'
    ),
    updated_at = NOW()
WHERE id = 5
RETURNING id, name, email, role_id;


-- 8. Verify the assigned role after an update.
SELECT
    u.id,
    u.name,
    u.email,
    r.name AS role
FROM users AS u
JOIN roles AS r ON r.id = u.role_id
WHERE u.id = 5;


-- 9. Count active administrators before any status or role mutation.
SELECT COUNT(*) AS active_admin_count
FROM users AS u
JOIN roles AS r ON r.id = u.role_id
WHERE r.name = 'ADMIN'
  AND u.is_active = TRUE;


-- 10. List active administrators.
SELECT
    u.id,
    u.name,
    u.email
FROM users AS u
JOIN roles AS r ON r.id = u.role_id
WHERE r.name = 'ADMIN'
  AND u.is_active = TRUE
ORDER BY u.id;


-- 11. Deactivate a non-admin test user and revoke all Sanctum tokens.
-- Replace user ID 5. Use the API instead when the target might be an ADMIN.
BEGIN;

UPDATE users
SET
    is_active = FALSE,
    updated_at = NOW()
WHERE id = 5;

DELETE FROM personal_access_tokens
WHERE tokenable_type = 'App\Models\User'
  AND tokenable_id = 5;

COMMIT;


-- 12. Reactivate a user.
-- Reactivation does not issue a token; the user must log in again.
UPDATE users
SET
    is_active = TRUE,
    updated_at = NOW()
WHERE id = 5
RETURNING id, email, is_active;


-- 13. Hard-delete a local test user.
-- Not recommended: the application DELETE endpoint intentionally performs deactivation.
-- Foreign keys from later business tables may reject this operation.
-- Replace user ID 5 only after confirming the target is disposable test data.
BEGIN;

DELETE FROM personal_access_tokens
WHERE tokenable_type = 'App\Models\User'
  AND tokenable_id = 5;

DELETE FROM users
WHERE id = 5;

COMMIT;


-- 14. Safely preview an update and discard it.
BEGIN;

UPDATE users
SET
    name = 'Temporary Name',
    updated_at = NOW()
WHERE id = 4;

SELECT id, name, email, is_active
FROM users
WHERE id = 4;

ROLLBACK;

