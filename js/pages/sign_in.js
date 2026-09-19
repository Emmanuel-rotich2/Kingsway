(function () {
  'use strict';
  const state = { userId: null, method: null, challenge: null, remember: false, identifier: '', password: '', recovery: false, verifying: false, redirecting: false };
  const byId = (id) => document.getElementById(id);
  const showError = (id, message) => { const el=byId(id); if(!el)return; el.textContent=message; el.classList.remove('d-none'); };
  const clearMessages = () => ['authError','verifyError','verifySuccess'].forEach((id)=>byId(id)?.classList.add('d-none'));
  const api = (path, method='POST', body={}) => window.API.apiCall(path, method, body, {}, { checkPermission:false });
  const toB64 = (buffer) => { let value=''; new Uint8Array(buffer).forEach((byte)=>{value+=String.fromCharCode(byte);}); return btoa(value); };
  const decodeOptions = (value, key='') => { if(Array.isArray(value))return value.map((item)=>decodeOptions(item,key)); if(value&&typeof value==='object'){Object.keys(value).forEach((child)=>{value[child]=decodeOptions(value[child],child);});return value;} if(typeof value==='string'&&['challenge','id'].includes(key)){const normalized=value.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-value.length%4)%4);return Uint8Array.from(atob(normalized),(character)=>character.charCodeAt(0));} return value; };
  const otpCells = () => Array.from(document.querySelectorAll('.auth-otp-cell'));
  const otpValue = () => otpCells().map((cell)=>cell.value).join('');
  const clearOtp = () => otpCells().forEach((cell)=>{cell.value='';cell.classList.remove('is-complete');});

  function dashboard(response) {
    if (response?.password_setup_required && response?.password_setup_url) return response.password_setup_url;
    const next = new URLSearchParams(window.location.search).get('next') || undefined;
    return window.AuthContext?.getAfterLoginUrl?.(next) || `${window.APP_BASE||''}/home.php`;
  }
  async function finish(userId, challenge) {
    const result=await window.API.auth.complete2FALogin(userId,state.remember,challenge);
    if(!result?.token) throw new Error(result?.message||'The authenticated session could not be created.');
    window.location.replace(dashboard(result));
  }
  async function beginVerification(result) {
    state.userId=Number(result.user_id); state.method=result.method||'email'; state.challenge=result.challenge_token;
    byId('signInStep').classList.add('d-none'); byId('verificationStep').classList.remove('d-none');
    const isPasskey=state.method==='passkey';
    byId('verificationTitle').textContent=isPasskey?'Passkey verification':state.method==='totp'?'Authenticator verification':'Check your email';
    byId('verificationDescription').textContent=isPasskey?'Use your fingerprint, face or device screen lock to continue.':state.method==='totp'?'Enter the current code from your authenticator app.':'Enter the six-digit code sent to your registered email address.';
    byId('verificationForm').classList.toggle('d-none',isPasskey); byId('authVerifyPasskey').classList.toggle('d-none',!isPasskey);
    byId('authResend').classList.toggle('d-none',state.method==='totp'||isPasskey); byId('authUseRecovery').classList.toggle('d-none',isPasskey);
    if(state.method==='email') await api('/twofactor/challenge','POST',{challenge_token:state.challenge});
    if(!isPasskey) otpCells()[0]?.focus();
  }
  async function login(event) {
    event.preventDefault(); clearMessages();
    state.identifier=byId('authIdentifier').value.trim(); state.password=byId('authPassword').value; state.remember=byId('authRemember').checked;
    const button=byId('authSubmit'); button.disabled=true; button.querySelector('.spinner-border').classList.remove('d-none');
    try { const result=await window.API.auth.login(state.identifier,state.password,state.remember); if(result?.parent_portal_only){state.redirecting=true;showError('authError',(result.message||'This sign-in is for school staff only. Parents use the Parent Portal.')+' Redirecting to the Parent Portal…');setTimeout(()=>{window.location.replace(result.parent_portal_url||`${window.APP_BASE||''}/parent_portal.php`);},1800);return;} if(result?.requires_2fa){await beginVerification(result);return;} if(result?.token){window.location.replace(dashboard(result));return;} throw new Error(result?.message||'Sign in failed.'); }
    catch(error){if(!state.redirecting)showError('authError',error.message||'Sign in failed.');}
    finally{if(!state.redirecting){button.disabled=false;button.querySelector('.spinner-border').classList.add('d-none');}}
  }
  async function verify(event) {
    event?.preventDefault?.();
    if(state.verifying)return;
    clearMessages(); const code=state.recovery?byId('verificationCode').value.trim():otpValue(); if(!code||(!state.recovery&&code.length!==6))return;
    const method=state.recovery?'backup':state.method; const button=byId('verifySubmit'); button.disabled=true;
    const status=byId('authAutoStatus'); state.verifying=true; otpCells().forEach((cell)=>cell.disabled=true); if(status)status.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Verifying securely…';
    try { const result=await api('/twofactor/verify','POST',{challenge_token:state.challenge,method,code}); if(!result?.verified)throw new Error('Verification failed.'); await finish(state.userId,state.challenge); }
    catch(error){showError('verifyError',error.message||'The code is invalid or expired.');if(!state.recovery){clearOtp();otpCells()[0]?.focus();}}
    finally{state.verifying=false;button.disabled=false;otpCells().forEach((cell)=>cell.disabled=false);if(status)status.innerHTML='<i class="bi bi-lightning-charge-fill"></i> Verification starts automatically when all six digits are entered.';}
  }
  async function resend() {
    clearMessages(); try { await api('/twofactor/challenge','POST',{challenge_token:state.challenge}); const el=byId('verifySuccess');el.textContent='A new code was sent. Previous codes are no longer valid.';el.classList.remove('d-none'); } catch(error){showError('verifyError',error.message||'A new code could not be sent.');}
  }
  async function passwordless() {
    clearMessages(); if(!window.PublicKeyCredential||!navigator.credentials?.get){showError('authError','Passkeys are not supported on this browser or device.');return;}
    try { const options=decodeOptions(await api('/twofactor/passwordless-options')); const credential=await navigator.credentials.get({publicKey:options}); const encoded={id:credential.id,clientDataJSON:toB64(credential.response.clientDataJSON),authenticatorData:toB64(credential.response.authenticatorData),signature:toB64(credential.response.signature),userHandle:credential.response.userHandle?toB64(credential.response.userHandle):null}; const verified=await api('/twofactor/passwordless-verify','POST',{credential:encoded}); await finish(verified.user_id,verified.challenge_token); } catch(error){if(error?.name!=='NotAllowedError')showError('authError',error.message||'Passkey sign-in failed.');}
  }
  async function verifyPasskey() {
    clearMessages();
    try { const start=await api('/twofactor/challenge','POST',{challenge_token:state.challenge,method:'passkey'}); const options=decodeOptions(start.public_key); const credential=await navigator.credentials.get({publicKey:options}); const encoded={id:credential.id,clientDataJSON:toB64(credential.response.clientDataJSON),authenticatorData:toB64(credential.response.authenticatorData),signature:toB64(credential.response.signature),userHandle:credential.response.userHandle?toB64(credential.response.userHandle):null}; const verified=await api('/twofactor/verify','POST',{challenge_token:state.challenge,method:'passkey',credential:encoded,code:'passkey'}); if(!verified?.verified)throw new Error('Passkey verification failed.'); await finish(state.userId,state.challenge); } catch(error){if(error?.name!=='NotAllowedError')showError('verifyError',error.message||'Passkey verification failed.');}
  }
  function setupOtpInputs(){
    const cells=otpCells();
    cells.forEach((cell,index)=>{
      cell.addEventListener('input',()=>{cell.value=cell.value.replace(/\D/g,'').slice(-1);cell.classList.toggle('is-complete',cell.value!=='');if(cell.value&&index<cells.length-1)cells[index+1].focus();if(otpValue().length===6)void verify();});
      cell.addEventListener('keydown',(event)=>{if(event.key==='Backspace'&&!cell.value&&index>0){cells[index-1].value='';cells[index-1].classList.remove('is-complete');cells[index-1].focus();}if(event.key==='ArrowLeft'&&index>0)cells[index-1].focus();if(event.key==='ArrowRight'&&index<cells.length-1)cells[index+1].focus();});
      cell.addEventListener('paste',(event)=>{const digits=(event.clipboardData?.getData('text')||'').replace(/\D/g,'').slice(0,6);if(!digits)return;event.preventDefault();clearOtp();digits.split('').forEach((digit,i)=>{if(cells[i]){cells[i].value=digit;cells[i].classList.add('is-complete');}});cells[Math.min(digits.length,6)-1]?.focus();if(digits.length===6)void verify();});
    });
  }
  function reset() { state.userId=null;state.challenge=null;state.recovery=false;state.verifying=false;clearMessages();clearOtp();byId('verificationStep').classList.add('d-none');byId('signInStep').classList.remove('d-none');byId('verificationCode').value='';byId('verificationCode').classList.add('d-none');byId('authOtpGrid').classList.remove('d-none');byId('verifySubmit').classList.add('d-none');byId('authPassword').focus(); }
  document.addEventListener('DOMContentLoaded',async()=>{ await window.AuthContext?.ready?.(); if(window.AuthContext?.isAuthenticated?.()){const next = new URLSearchParams(window.location.search).get('next')||undefined; window.location.replace(window.AuthContext.getAfterLoginUrl?.(next)||`${window.APP_BASE||''}/home.php`);return;} setupOtpInputs();byId('authLoginForm').addEventListener('submit',login);byId('verificationForm').addEventListener('submit',verify);byId('authPasswordless').addEventListener('click',passwordless);byId('authVerifyPasskey').addEventListener('click',verifyPasskey);byId('authResend').addEventListener('click',resend);byId('authBack').addEventListener('click',reset);byId('authUseRecovery').addEventListener('click',()=>{state.recovery=true;byId('verificationTitle').textContent='Use a recovery code';byId('verificationDescription').textContent='Enter one unused recovery code saved during MFA setup.';byId('authOtpGrid').classList.add('d-none');byId('authAutoStatus').classList.add('d-none');byId('verificationCode').classList.remove('d-none');byId('verifySubmit').classList.remove('d-none');byId('verificationCode').value='';byId('verificationCode').removeAttribute('inputmode');byId('verificationCode').focus();});byId('authTogglePassword').addEventListener('click',()=>{const input=byId('authPassword');input.type=input.type==='password'?'text':'password';}); });
})();
