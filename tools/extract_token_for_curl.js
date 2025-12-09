/**
 * Extract Token for cURL Testing
 * 
 * Run this in your browser console to get the token and curl command
 */

(function() {
    const token = localStorage.getItem('token');
    
    if (!token) {
        console.error('❌ No token found in localStorage');
        console.log('Please log in first, then run this script again.');
        return;
    }
    
    // Decode the token to see what's inside
    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            console.error('❌ Invalid JWT token format');
            return;
        }
        
        const payload = JSON.parse(atob(parts[1]));
        const header = JSON.parse(atob(parts[0]));
        
        console.log('%c📊 Token Analysis', 'font-size: 16px; font-weight: bold; color: #2196F3;');
        console.log('');
        
        // Token size
        const tokenSize = token.length;
        const authHeaderSize = `Bearer ${token}`.length;
        
        console.log('%cToken Size:', 'font-weight: bold;', tokenSize, 'bytes');
        console.log('%cAuthorization Header Size:', 'font-weight: bold;', authHeaderSize, 'bytes');
        console.log('');
        
        // Permissions analysis
        if (payload.permissions && Array.isArray(payload.permissions)) {
            const firstPerm = payload.permissions[0];
            const permType = typeof firstPerm === 'string' ? 'STRING (compact)' : 'OBJECT (bloated)';
            const permColor = typeof firstPerm === 'string' ? '#4CAF50' : '#F44336';
            
            console.log('%cPermissions Format:', 'font-weight: bold;', `%c${permType}`, `color: ${permColor}; font-weight: bold;`);
            console.log('%cTotal Permissions:', 'font-weight: bold;', payload.permissions.length);
            console.log('%cSample Permission:', 'font-weight: bold;', firstPerm);
        }
        
        console.log('');
        console.log('%c📋 cURL Test Commands', 'font-size: 16px; font-weight: bold; color: #FF9800;');
        console.log('');
        
        // Generate curl commands
        const curlCmd1 = `curl -v -H "Authorization: Bearer ${token}" http://localhost/Kingsway/api/academic/classes-list 2>&1 | head -30`;
        const curlCmd2 = `curl -v -H "Authorization: Bearer ${token}" http://localhost/Kingsway/api/academic/classes-list 2>&1 | grep -i "header"`;
        
        console.log('%c1. Test API call with full output:', 'font-weight: bold; color: #9C27B0;');
        console.log(curlCmd1);
        console.log('');
        
        console.log('%c2. Test API call (headers only):', 'font-weight: bold; color: #9C27B0;');
        console.log(curlCmd2);
        console.log('');
        
        console.log('%c3. Check token size:', 'font-weight: bold; color: #9C27B0;');
        console.log(`echo "${token}" | wc -c`);
        console.log('');
        
        console.log('%c💡 Copy any command above and run it in your terminal', 'color: #4CAF50; font-weight: bold;');
        console.log('');
        
        // Also show the fix command
        console.log('%c🔧 To Fix the Issue', 'font-size: 16px; font-weight: bold; color: #E91E63;');
        console.log('');
        console.log('Run this command to clear the old token and log in fresh:');
        console.log('%clocalStorage.clear(); window.location.href = "/Kingsway/index.php";', 'background: #333; color: #0F0; padding: 5px; font-family: monospace;');
        
    } catch (e) {
        console.error('❌ Error decoding token:', e);
    }
})();
