-- Dummy accounts for local UI/flow testing. Passwords are bcrypt-hashed
-- below; the plaintext values are NOT stored anywhere in the repo.

INSERT INTO users (name, department, phone, email, password_hash, role) VALUES
    ('Ada Admin',    'IT',        '555-0100', 'admin@example.com',     '$2b$10$FrojZEGHhbnZ6w1Z58T7M.bKvhfspzbyv3l9Vm5X8vk..khw42JAC', 'admin'),
    ('Eli Employee', 'Sales',     '555-0200', 'employee@example.com',  '$2b$10$cJ/.TAejbFFKXNMtf5lrsebhaKvdaMshbYqpy19.qlj4gUW7U/neS', 'employee'),
    ('Sam Employee', 'Marketing', '555-0300', 'employee2@example.com', '$2b$10$PocZldDhkVy3emhIhgkZAu/zT/UYxpfhZEgUHSRC/FmORghOOOzPC', 'employee')
ON CONFLICT (email) DO NOTHING;
