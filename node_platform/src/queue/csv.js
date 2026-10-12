export function generateCSV(rows) {
  if (!Array.isArray(rows) || rows.length === 0) return '';
  const headers = Object.keys(rows[0]);
  const headerRow = headers.join(',');
  const dataRows = rows.map(r => headers.map(h => {
    const v = r[h];
    if (v === null || v === undefined) return '';
    const str = String(v);
    return str.includes(',') || str.includes('"') ? `"${str.replace(/"/g, '""')}"` : str;
  }).join(','));
  return [headerRow, ...dataRows].join('\n');
}

export function csvToJSON(csv) {
  if (!csv) return [];
  const lines = csv.trim().split('\n');
  if (lines.length < 2) return [];
  const headers = lines[0].split(',').map(h => h.trim());
  return lines.slice(1).map(line => {
    const cells = line.split(',').map(c => c.trim().replace(/^"(.*)"$/, '$1'));
    return headers.reduce((acc, h, i) => {
      acc[h] = cells[i] || '';
      return acc;
    }, {});
  });
}