import { resolveView } from './_shared.js';
import { generateCSV } from '../csv.js';

export async function exportBulkHandler(jobData, deps) {
  const { jobId, operatorId } = jobData;
  const payload = jobData;
  const { entity, view, specKey } = resolveView(payload);
  const format = String(payload.format || 'csv');

  if (!['csv', 'json'].includes(format)) throw new Error(`Unsupported export format: ${format}`);

  const spec = deps.viewSpecs.get(specKey);
  if (!spec) throw new Error(`No view spec for ${specKey}`);

  const filters = payload.filters || {};

  await deps.progress(0.2);

  const rawData = await deps.internalClient.fetchViewData(spec, filters, operatorId);

  await deps.progress(0.6);

  const aggregated = await deps.aggregationEngine.compute(rawData, spec.aggregations);
  const rows = aggregated.tables?.detail || [];

  await deps.progress(0.9);

  const content = format === 'csv' ? generateCSV(rows) : JSON.stringify(aggregated);

  return {
    kind: 'export',
    format,
    content,
    filename: `${entity}_${view}_${Date.now()}.${format}`,
    meta: { generatedAt: Date.now(), jobId },
  };
}