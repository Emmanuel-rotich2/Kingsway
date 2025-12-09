/**
 * Token Debug Helper
 * 
 * Run this in the browser console to check your current JWT token size
 * and contents. This helps diagnose "400 Request Header Too Large" errors.
 * 
 * Usage:
 * 1. Open browser console (F12)
 * 2. Copy and paste this entire script
 * 3. Press Enter
 */

(function() {
    console.log('='.repeat(80));
    console.log('JWT TOKEN DEBUG INFORMATION');
    console.log('='.repeat(80));
    
    // Get token from localStorage
    const token = localStorage.getItem('token');
    
    if (!token) {
        console.error('❌ No token found in localStorage');
        console.log('You need to log in first.');
        return;
    }
    
    // Calculate token size
    const tokenSize = new Blob([token]).size;
    const tokenSizeKB = (tokenSize / 1024).toFixed(2);
    
    console.log(`\n📊 TOKEN SIZE:`);
    console.log(`   ${tokenSize} bytes (${tokenSizeKB} KB)`);
    
    if (tokenSize > 4096) {
        console.error(`   ⚠️  WARNING: Token is TOO LARGE (> 4KB)`);
        console.error(`   This will cause "400 Request Header Too Large" errors`);
    } else if (tokenSize > 2048) {
        console.warn(`   ⚠️  Token is large (> 2KB), may cause issues`);
    } else {
        console.log(`   ✅ Token size is acceptable`);
    }
    
    // Decode JWT (without verification)
    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            console.error('❌ Invalid JWT format');
            return;
        }
        
        const payload = JSON.parse(atob(parts[1]));
        
        console.log(`\n📦 TOKEN PAYLOAD:`);
        console.log(`   User ID: ${payload.user_id}`);
        console.log(`   Username: ${payload.username}`);
        console.log(`   Email: ${payload.email}`);
        console.log(`   Display Name: ${payload.display_name}`);
        
        if (payload.exp) {
            const expiry = new Date(payload.exp * 1000);
            const now = new Date();
            const minutesUntilExpiry = Math.floor((expiry - now) / 1000 / 60);
            
            console.log(`\n⏰ TOKEN EXPIRY:`);
            console.log(`   Expires: ${expiry.toLocaleString()}`);
            console.log(`   Time until expiry: ${minutesUntilExpiry} minutes`);
            
            if (minutesUntilExpiry < 0) {
                console.error(`   ❌ Token has EXPIRED`);
            } else if (minutesUntilExpiry < 5) {
                console.warn(`   ⚠️  Token expires soon`);
            } else {
                console.log(`   ✅ Token is still valid`);
            }
        }
        
        // Check permissions structure
        if (payload.permissions) {
            const permCount = Array.isArray(payload.permissions) ? payload.permissions.length : 0;
            console.log(`\n🔐 PERMISSIONS:`);
            console.log(`   Count: ${permCount}`);
            
            if (permCount > 0) {
                const firstPerm = payload.permissions[0];
                const isCompact = typeof firstPerm === 'string';
                
                console.log(`   Format: ${isCompact ? 'COMPACT (strings only)' : 'FULL OBJECTS'}`);
                
                if (!isCompact) {
                    console.error(`   ❌ PROBLEM FOUND: Permissions are stored as FULL OBJECTS`);
                    console.error(`   This is causing the token to be too large!`);
                    console.log(`\n   Sample permission object:`);
                    console.log(`   `, firstPerm);
                    
                    // Calculate approximate size of permissions
                    const permSize = new Blob([JSON.stringify(payload.permissions)]).size;
                    const permSizeKB = (permSize / 1024).toFixed(2);
                    console.log(`\n   Permissions size: ${permSize} bytes (${permSizeKB} KB)`);
                    console.log(`   This is ${((permSize / tokenSize) * 100).toFixed(1)}% of total token size`);
                } else {
                    console.log(`   ✅ Permissions are compact (strings only)`);
                    console.log(`   Sample: "${firstPerm}"`);
                }
            }
        }
        
        // Check roles
        if (payload.roles) {
            const roleCount = Array.isArray(payload.roles) ? payload.roles.length : 0;
            console.log(`\n👥 ROLES:`);
            console.log(`   Count: ${roleCount}`);
            if (roleCount > 0) {
                console.log(`   Roles:`, payload.roles);
            }
        }
        
        // Recommendation
        console.log(`\n${'='.repeat(80)}`);
        console.log(`💡 RECOMMENDATION:`);
        
        if (tokenSize > 4096 || (payload.permissions && typeof payload.permissions[0] === 'object')) {
            console.log(`\n   1. LOG OUT of the application`);
            console.log(`   2. LOG BACK IN to get a new compact token`);
            console.log(`   3. The new token should be < 1KB with permission codes only`);
            console.log(`\n   Run this script again after re-logging to verify.`);
        } else {
            console.log(`\n   ✅ Your token looks good! No action needed.`);
        }
        
        console.log(`${'='.repeat(80)}\n`);
        
    } catch (error) {
        console.error('❌ Error decoding token:', error);
        console.log('Token might be corrupted. Try logging out and back in.');
    }
})();
