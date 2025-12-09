/**
 * ONE-TIME TOKEN FIX
 * 
 * This script checks if you have an old bloated token and guides you to fix it.
 * Run this in browser console (F12) ONCE to resolve the 400 error.
 */

(function() {
    console.clear();
    console.log('%c='.repeat(80), 'color: blue');
    console.log('%cTOKEN FIX UTILITY', 'color: blue; font-size: 20px; font-weight: bold');
    console.log('%c='.repeat(80), 'color: blue');
    
    const token = localStorage.getItem('token');
    
    if (!token) {
        console.log('%c✅ NO TOKEN FOUND', 'color: green; font-size: 16px');
        console.log('\nYou are not logged in. Just log in normally and you\'ll get a compact token.');
        console.log('\n%cACTION: Go to login page and log in', 'background: green; color: white; padding: 5px; font-weight: bold');
        return;
    }
    
    // Check token size
    const tokenSize = new Blob([token]).size;
    const tokenSizeKB = (tokenSize / 1024).toFixed(2);
    
    console.log(`\n📊 Current token size: ${tokenSize} bytes (${tokenSizeKB} KB)`);
    
    // Decode token
    try {
        const parts = token.split('.');
        const payload = JSON.parse(atob(parts[1]));
        
        console.log(`👤 User: ${payload.username}`);
        console.log(`🔐 Permissions: ${payload.permissions?.length || 0}`);
        
        // Check format
        const firstPerm = payload.permissions?.[0];
        const isOldFormat = typeof firstPerm === 'object';
        
        if (isOldFormat) {
            console.log('%c\n❌ PROBLEM FOUND: OLD TOKEN FORMAT', 'color: red; font-size: 16px; font-weight: bold');
            console.log('\nYour token contains FULL permission objects instead of just codes.');
            console.log('This makes the token too large and causes 400 errors.\n');
            
            console.log('Sample permission (OLD FORMAT):');
            console.log(firstPerm);
            
            console.log('\n%c━'.repeat(80), 'color: yellow');
            console.log('%cSOLUTION (SIMPLE - 30 seconds):', 'color: yellow; font-size: 16px; font-weight: bold');
            console.log('%c━'.repeat(80), 'color: yellow');
            console.log('\n1. Run this command to logout:');
            console.log('%c   localStorage.clear(); window.location.href = "/Kingsway/index.php";', 'background: black; color: lime; padding: 5px; font-family: monospace');
            console.log('\n2. Log in again with your username and password');
            console.log('\n3. You\'ll get a NEW compact token (< 1KB)');
            console.log('\n4. Everything will work perfectly!\n');
            
            console.log('%cWould you like me to logout for you now? (Y/N)', 'color: cyan; font-size: 14px');
            console.log('Type: %cfixToken()', 'background: black; color: lime; padding: 2px; font-family: monospace');
            
            // Add helper function to global scope
            window.fixToken = function() {
                console.log('%c\n✅ Clearing old token and redirecting to login...', 'color: green; font-weight: bold');
                localStorage.clear();
                setTimeout(() => {
                    window.location.href = '/Kingsway/index.php';
                }, 1000);
            };
            
        } else if (tokenSize > 2048) {
            console.log('%c\n⚠️  Token is large but in correct format', 'color: orange; font-size: 16px');
            console.log('\nThis might cause issues. Recommended to get a fresh token.');
            console.log('\nRun: %cfixToken()', 'background: black; color: lime; padding: 2px');
            
            window.fixToken = function() {
                localStorage.clear();
                window.location.href = '/Kingsway/index.php';
            };
            
        } else {
            console.log('%c\n✅ TOKEN IS GOOD!', 'color: green; font-size: 16px; font-weight: bold');
            console.log('\nYour token is compact and should work fine.');
            console.log('Permission format: COMPACT (strings only)');
            console.log('Sample permission:', firstPerm);
            console.log('\nIf you\'re still getting errors, check:');
            console.log('1. Backend API is running');
            console.log('2. NGINX configuration');
            console.log('3. Network tab in browser DevTools');
        }
        
    } catch (error) {
        console.error('%c\n❌ ERROR: Token is corrupted', 'color: red; font-size: 16px');
        console.error(error);
        console.log('\nRun: %cfixToken()', 'background: black; color: lime; padding: 2px');
        
        window.fixToken = function() {
            localStorage.clear();
            window.location.href = '/Kingsway/index.php';
        };
    }
    
    console.log('\n%c='.repeat(80), 'color: blue');
})();
