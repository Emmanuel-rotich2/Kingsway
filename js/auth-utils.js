/** Auth UI helpers. AuthContext is the single authority. */
function getAuthContext() { return window.AuthContext || null; }
function canUser(code) { return Boolean(getAuthContext()?.hasPermission?.(code)); }
function canUserAny(codes=[]) { return Boolean(getAuthContext()?.hasAnyPermission?.(codes)); }
function canUserAll(codes=[]) { return Boolean(getAuthContext()?.hasAllPermissions?.(codes)); }
function isUserRole(role) { return Boolean(getAuthContext()?.hasRole?.(role)); }
function isUserAuthenticated() { return Boolean(getAuthContext()?.isAuthenticated?.()); }
function getCurrentUser() { return getAuthContext()?.getUser?.() || null; }
function getUserDisplayName() { const u=getCurrentUser(); return u?.full_name||u?.name||u?.username||'Guest'; }
function getUserEmail() { return getCurrentUser()?.email||null; }
function getUserAvatar() { const u=getCurrentUser(); if(!u)return'?'; if(u.avatar_url)return u.avatar_url; return (u.full_name||u.username||'User').split(/\s+/).map(p=>p[0]).join('').slice(0,3).toUpperCase(); }
function getUserRoles() { return getAuthContext()?.getRoles?.()||[]; }
function getUserPrimaryRole() { return getUserRoles()[0]||null; }
function getUserPermissions() { return getAuthContext()?.getPermissions?.()||[]; }
function showPermissionDenied(action='access this resource') { window.showNotification?.(`Permission denied: you are not authorized to ${action}.`,'error'); }
function checkPermissionAndNotify(code,action='perform this action'){const ok=canUser(code);if(!ok)showPermissionDenied(action);return ok;}
function toggleElementByPermission(id,code){toggleElement(id,canUser(code));}
function toggleElementByAnyPermission(id,codes){toggleElement(id,canUserAny(codes));}
function toggleElementByAllPermissions(id,codes){toggleElement(id,canUserAll(codes));}
function toggleElement(id,allowed){const el=document.getElementById(id);if(!el)return;el.hidden=!allowed;if('disabled'in el)el.disabled=!allowed;}
function requirePermissionOnClick(id,code,callback,action){const b=document.getElementById(id);if(!b)return;b.addEventListener('click',e=>{if(!checkPermissionAndNotify(code,action)){e.preventDefault();e.stopImmediatePropagation();return;}callback?.(e);});}
async function initializePermissionUI(){const a=getAuthContext();if(!a)return;if(typeof a.ready==='function')await a.ready();document.querySelectorAll('[data-permission]').forEach(el=>{const codes=(el.dataset.permission||'').split(',').map(v=>v.trim()).filter(Boolean);const allowed=el.dataset.permissionAll==='true'?a.hasAllPermissions(codes):(codes.length===1?a.hasPermission(codes[0]):a.hasAnyPermission(codes));el.hidden=!allowed;if('disabled'in el)el.disabled=!allowed;});}
function debugAuthState(){const a=getAuthContext();console.table({authenticated:a?.isAuthenticated?.()||false,user:getUserDisplayName(),roles:getUserRoles().join(', '),permissions:getUserPermissions().length});}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>initializePermissionUI());else initializePermissionUI();
