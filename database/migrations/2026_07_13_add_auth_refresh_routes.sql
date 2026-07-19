-- Add refresh token routes to the database
-- This migration adds the refresh-token and logout-refresh endpoints to the routes table
-- and grants all roles access to these routes

-- Add refresh-token route
INSERT INTO routes (name, url, domain, module, description, controller, action, is_active, created_at, updated_at) 
VALUES ('auth_refresh_token', '/api/auth/refresh-token', 'SYSTEM', 'auth', 'Refresh JWT access token', 'AuthController', 'postRefreshToken', 1, NOW(), NOW());

-- Add logout-refresh route  
INSERT INTO routes (name, url, domain, module, description, controller, action, is_active, created_at, updated_at) 
VALUES ('auth_logout_refresh', '/api/auth/logout-refresh', 'SYSTEM', 'auth', 'Revoke refresh token on logout', 'AuthController', 'postLogoutRefresh', 1, NOW(), NOW());

-- Grant all roles access to refresh-token route
INSERT INTO role_routes (role_id, route_id, is_allowed, created_at) 
SELECT id, (SELECT id FROM routes WHERE name = 'auth_refresh_token'), 1, NOW() FROM roles;

-- Grant all roles access to logout-refresh route
INSERT INTO role_routes (role_id, route_id, is_allowed, created_at) 
SELECT id, (SELECT id FROM routes WHERE name = 'auth_logout_refresh'), 1, NOW() FROM roles;