import { resolveView } from './_shared.js';

export async function genericAggregateHandler(jobData, deps) {
  const { jobId, operatorId } = jobData;
  const payload = jobData;
  const { entity, view, specKey } = resolveView(payload);

  const spec = {
    view: specKey,
    queries: payload.queries || [],
    aggregations: payload.aggregations || {},
  };

  const filters = payload.filters || {};

  await deps.progress(0.1);

  const rawData = await deps.internalClient.fetchViewData(spec, filters, operatorId);

  await deps.progress(0.5);

  const aggregated = await deps.aggregationEngine.compute(rawData, spec.aggregations);

  await deps.progress(0.9);

  return {
    entity,
    view,
    data: {
      sources: rawData,
      summary: aggregated.summary || {},
      groups: Object.fromEntries(
        Object.entries(aggregated.tables || {}).map(([key, table]) => [key, table.rows || []]),
      ),
      filterOptions: aggregated.filterOptions || {},
    },
    meta: {
      generatedAt: Date.now(),
      jobId,
      pagination: rawData._viewMeta?.pagination ?? null,
    },
  };
}
