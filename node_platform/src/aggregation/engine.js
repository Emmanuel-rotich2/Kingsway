let polarsCache = null;

async function getPolars() {
  if (polarsCache !== null) return polarsCache;
  try {
    const pl = await import('polars');
    polarsCache = pl;
    return pl;
  } catch {
    polarsCache = false;
    return false;
  }
}

export class AggregationEngine {
  constructor() {
    this.polarsAvailable = null;
  }

  async init() {
    this.polarsAvailable = await getPolars();
  }

  async compute(rawData, aggregations) {
    const sections = this.resolveSections(rawData, aggregations);

    if (!this.polarsAvailable) {
      return this.computeVanilla(rawData, aggregations, sections);
    }

    const pl = this.polarsAvailable;
    const results = { summary: {}, tables: {}, charts: {}, filterOptions: {} };

    for (const [key, spec] of Object.entries(aggregations)) {
      if (key === 'summary') {
        results.summary = await this.computeSummary(sections.summary.data, sections.summary.spec, pl);
      } else if (key === 'tables') {
        results.tables = await this.computeTables(sections.tables.data, sections.tables.spec, pl);
      } else if (key === 'charts') {
        results.charts = await this.computeCharts(sections.charts.data, sections.charts.spec, pl);
      } else if (key === 'filterOptions') {
        results.filterOptions = await this.computeFilterOptions(sections.filterOptions.data, sections.filterOptions.spec, pl);
      }
    }

    return results;
  }

  /**
   * Each aggregation section may name a `dataSource` key from the raw datasets
   * (e.g. summary over the whole-cohort `status` bundle while tables stay on
   * the paginated primary array). Without one, the first array in rawData wins.
   * The directive is stripped so section specs never leak the key to scorebars.
   */
  resolveSections(rawData, aggregations) {
    const sections = {};
    const mainData = rawData[Object.keys(rawData).find(k => Array.isArray(rawData[k]))] || [];
    for (const [key, spec] of Object.entries(aggregations || {})) {
      const rest = (spec && typeof spec === 'object' && !Array.isArray(spec)) ? { ...spec } : {};
      const { dataSource, ...specOnly } = rest;
      const data = typeof dataSource === 'string' && Array.isArray(rawData[dataSource])
        ? rawData[dataSource]
        : mainData;
      sections[key] = { spec: specOnly, data };
    }
    return sections;
  }

  async computeSummary(data, spec, pl) {
    const result = {};
    const mainData = Array.isArray(data) ? data : [];

    for (const [metricName, expr] of Object.entries(spec)) {
      try {
        if (typeof expr === 'string') {
          const df = pl.DataFrame(mainData);
          let value;
          if (expr === 'count()') {
            value = df.height;
          } else if (expr.startsWith("filter(")) {
            const filterExpr = expr.match(/filter\((.+)\)\.count\(\)/)[1];
            const parts = filterExpr.match(/^([^!=]+?)\s*(==|!=)\s*(.+)$/);
            if (!parts) {
              value = 0;
            } else {
              const field = parts[1].trim();
              const op = parts[2];
              const expected = parts[3].trim().replace(/^'|'$/g, '').replace(/^"|"$/g, '');
              const col = pl.col(field);
              value = df.filter(op === '==' ? col.eq(expected) : col.neq(expected)).height;
            }
          } else if (expr.startsWith('sum(')) {
            const field = expr.slice(4, -1).trim();
            value = df.select(pl.col(field).sum()).toArray()[0];
          } else if (expr.startsWith('avg(')) {
            const field = expr.slice(4, -1).trim();
            value = df.select(pl.col(field).mean()).toArray()[0];
          } else if (expr.startsWith('min(')) {
            const field = expr.slice(4, -1).trim();
            value = df.select(pl.col(field).min()).toArray()[0];
          } else if (expr.startsWith('max(')) {
            const field = expr.slice(4, -1).trim();
            value = df.select(pl.col(field).max()).toArray()[0];
          } else {
            value = df.select(pl.col(expr)).toArray()[0];
          }
          result[metricName] = value;
        }
      } catch (e) {
        result[metricName] = 0;
      }
    }
    return result;
  }

  async computeTables(data, spec, pl) {
    // Grouped table metrics are deliberately computed in plain JS. The
    // optional Polars adapter previously selected metric names as source
    // columns (for example `totalStudents`) and either returned empty groups
    // or copied the first row instead of calculating the metric.
    return this.computeTablesVanilla(Array.isArray(data) ? data : [], spec);
  }

  async computeCharts(rawData, spec, pl) {
    return {};
  }

  async computeFilterOptions(data, spec, pl) {
    const result = {};
    const mainData = Array.isArray(data) ? data : [];
    const df = pl.DataFrame(mainData);

    for (const [optName, expr] of Object.entries(spec)) {
      try {
        if (expr.startsWith('unique(')) {
          const col = expr.match(/unique\((.+)\)/)[1];
          result[optName] = df.select(pl.col(col).unique()).toArray().flat();
        }
      } catch {
        result[optName] = [];
      }
    }
    return result;
  }

  computeVanilla(rawData, aggregations, sections) {
    const results = { summary: {}, tables: {}, charts: {}, filterOptions: {} };

    for (const [key, spec] of Object.entries(aggregations || {})) {
      if (key === 'summary' && sections.summary) {
        results.summary = this.computeSummaryVanilla(sections.summary.data, sections.summary.spec);
      } else if (key === 'tables' && sections.tables) {
        results.tables = this.computeTablesVanilla(sections.tables.data, sections.tables.spec);
      } else if (key === 'filterOptions' && sections.filterOptions) {
        results.filterOptions = this.computeFilterOptionsVanilla(sections.filterOptions.data, sections.filterOptions.spec);
      }
    }
    return results;
  }

  computeSummaryVanilla(data, spec) {
    const result = {};
    for (const [metricName, expr] of Object.entries(spec)) {
      if (expr === 'count()') {
        result[metricName] = data.length;
      } else if (typeof expr === 'string' && expr.startsWith('filter(')) {
        const match = expr.match(/filter\((.+)\)\.count\(\)/);
        if (match) {
          const filterStr = match[1];
          const parts = filterStr.match(/^([^!=]+?)\s*(==|!=)\s*(.+)$/);
          if (!parts) {
            result[metricName] = 0;
          } else {
            const field = parts[1].trim();
            const op = parts[2];
            const value = parts[3].trim().replace(/^'|'$/g, '').replace(/^"|"$/g, '');
            result[metricName] = data.filter(r => {
              const actual = String(r[field] ?? '');
              return op === '==' ? actual === String(value) : actual !== String(value);
            }).length;
          }
        }
      } else if (typeof expr === 'string' && expr.startsWith('sum(')) {
        const field = expr.slice(4, -1).trim();
        result[metricName] = data.reduce((acc, r) => acc + (Number(r[field]) || 0), 0);
      } else if (typeof expr === 'string' && expr.startsWith('avg(')) {
        const field = expr.slice(4, -1).trim();
        const nums = data.map(r => Number(r[field])).filter(v => Number.isFinite(v));
        result[metricName] = nums.length ? nums.reduce((a, b) => a + b, 0) / nums.length : 0;
      } else if (typeof expr === 'string' && expr.startsWith('min(')) {
        const field = expr.slice(4, -1).trim();
        const nums = data.map(r => Number(r[field])).filter(v => Number.isFinite(v));
        result[metricName] = nums.length ? Math.min(...nums) : 0;
      } else if (typeof expr === 'string' && expr.startsWith('max(')) {
        const field = expr.slice(4, -1).trim();
        const nums = data.map(r => Number(r[field])).filter(v => Number.isFinite(v));
        result[metricName] = nums.length ? Math.max(...nums) : 0;
      }
    }
    return result;
  }

  computeTablesVanilla(data, spec) {
    const result = {};
    for (const [tableName, tableSpec] of Object.entries(spec)) {
      let rows = [...data];
      if (tableSpec.groupBy) {
        const groups = new Map();
        for (const row of data) {
          const keyParts = tableSpec.groupBy.map(g => row[g] ?? null);
          const key = JSON.stringify(keyParts);
          if (!groups.has(key)) {
            groups.set(key, {
              keys: Object.fromEntries(tableSpec.groupBy.map((field, index) => [field, keyParts[index]])),
              rows: [],
            });
          }
          groups.get(key).rows.push(row);
        }
        rows = Array.from(groups.values(), ({ keys, rows: groupRows }) => {
          const output = { ...keys };
          for (const [field, expression] of Object.entries(tableSpec.metrics || {})) {
            output[field] = this.computeMetric(groupRows, expression);
          }
          return output;
        });
      }
      result[tableName] = {
        columns: tableSpec.cols || Object.keys(rows[0] || {}),
        rows: rows.map(r => {
          const obj = {};
          for (const col of (tableSpec.cols || Object.keys(r))) {
            obj[col] = r[col];
          }
          return obj;
        }),
      };
    }
    return result;
  }

  computeMetric(rows, expression) {
    if (expression === 'count()') return rows.length;
    if (typeof expression !== 'string') return null;

    const filteredCount = expression.match(/^filter\(([^!=]+?)\s*(==|!=)\s*(['\"]?)(.*?)\3\)\.count\(\)$/);
    if (filteredCount) {
      const [, rawField, operator, , expected] = filteredCount;
      const field = rawField.trim();
      return rows.filter(row => {
        const matches = String(row[field] ?? '') === expected;
        return operator === '==' ? matches : !matches;
      }).length;
    }

    const numeric = expression.match(/^(sum|avg|min|max)\(([^)]+)\)$/);
    if (numeric) {
      const [, operation, rawField] = numeric;
      const values = rows.map(row => Number(row[rawField.trim()])).filter(Number.isFinite);
      if (operation === 'sum') return values.reduce((total, value) => total + value, 0);
      if (!values.length) return null;
      if (operation === 'avg') return values.reduce((total, value) => total + value, 0) / values.length;
      return operation === 'min' ? Math.min(...values) : Math.max(...values);
    }

    return null;
  }

  computeFilterOptionsVanilla(data, spec) {
    const result = {};
    for (const [optName, expr] of Object.entries(spec)) {
      if (expr.startsWith('unique(')) {
        const col = expr.match(/unique\((.+)\)/)[1];
        result[optName] = [...new Set(data.map(r => r[col]).filter(Boolean))];
      }
    }
    return result;
  }
}

let engineCache = null;

export function getAggregationEngine() {
  if (!engineCache) {
    engineCache = new AggregationEngine();
  }
  return engineCache;
}

export function resetAggregationEngine() {
  engineCache = null;
}
