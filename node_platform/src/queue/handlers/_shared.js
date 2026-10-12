export function resolveView(payload) {
  if (typeof payload.view === 'string' && payload.view.includes('/')) {
    const [entity, view] = payload.view.split('/', 2);
    return { entity, view, specKey: `${entity}/${view}` };
  }
  const entity = String(payload.entity || '');
  const view = String(payload.view || '');
  return { entity, view, specKey: `${entity}/${view}` };
}