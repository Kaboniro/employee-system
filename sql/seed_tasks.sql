-- Run after employees exist. Adds a handful of placeholder tasks to every
-- user currently in the 'employee' role, so the dashboard isn't empty.

INSERT INTO tasks (user_id, title, status, due_date, supervisor_comment)
SELECT u.id, t.title, t.status, t.due_date, t.supervisor_comment
FROM users u
CROSS JOIN (VALUES
    ('Submit Q3 expense report',       'pending',   CURRENT_DATE + INTERVAL '5 days',  'Please include receipts for travel.'),
    ('Complete compliance training',   'completed', CURRENT_DATE - INTERVAL '3 days',  'Great job finishing early.'),
    ('Update project status doc',      'pending',   CURRENT_DATE + INTERVAL '2 days',  NULL),
    ('Onboard new team member',        'completed', CURRENT_DATE - INTERVAL '10 days', 'Thanks for the thorough walkthrough.'),
    ('Prepare client presentation',    'pending',   CURRENT_DATE + INTERVAL '7 days',  'Focus on the Q4 roadmap slides.')
) AS t(title, status, due_date, supervisor_comment)
WHERE u.role = 'employee';
