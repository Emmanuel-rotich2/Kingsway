(() => {
  const state = document.getElementById('spState');
  const form = document.getElementById('spForm');
  const unwrap = (r) => r?.data?.data ?? r?.data ?? r;
  async function load() {
    try {
      const profile = unwrap(await API.staffMigration.onboarding());
      Object.entries(profile || {}).forEach(([k,v]) => { const el=form.elements.namedItem(k); if(el && v != null) el.value=v; });
      state.className='alert alert-warning'; state.textContent='Complete all required fields.'; form.classList.remove('d-none');
    } catch(e) { state.className='alert alert-danger'; state.textContent=e.message || 'Unable to load profile.'; }
  }
  form?.addEventListener('submit', async (event) => {
    event.preventDefault(); state.className='alert alert-info'; state.textContent='Saving…';
    try { await API.staffMigration.completeProfile(Object.fromEntries(new FormData(form).entries())); state.className='alert alert-success'; state.textContent='Profile completed. Redirecting…'; setTimeout(()=>location.href='home.php',800); }
    catch(e){state.className='alert alert-danger';state.textContent=e.message || 'Unable to save profile.';}
  });
  load();
})();
