/** Reusable reporting visual primitives. Pages compose these; they do not choose reports. */
window.ReportComponents = (() => {
  const charts = new WeakMap();
  const supportedChartTypes = ['bar','column','bullet','radar','line','area','pie','doughnut','stacked_bar','waterfall','treemap','histogram','box','violin','scatter','bubble'];
  const palette = ["#176b4d", "#2d8f67", "#62b38f", "#e0a52b", "#d66b3d", "#8b5fbf", "#3677b5", "#b94356"];
  // Canvas colours must stay literal for Chart.js; DOM/SVG painting can use the
  // semantic CSS variables and therefore follows every theme switch.
  const domPalette = palette.map((_, index) => `var(--kw-chart-${index + 1})`);
  const domColor = (color) => {
    const index = palette.indexOf(String(color || "").toLowerCase());
    return index >= 0 ? domPalette[index] : color || domPalette[0];
  };
  const number = (value) => Number.isFinite(Number(value)) ? Number(value) : 0;
  const text = (value) => String(value ?? "");
  const escape = (value) => text(value).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
  const format = (value, kind = "number") => kind === "currency"
    ? `KES ${number(value).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`
    : kind === "percent" ? `${number(value).toFixed(1)}%` : kind === "integer" ? number(value).toLocaleString() : text(value);
  const target = (value) => typeof value === "string" ? document.querySelector(value) : value;

  function kpis(container, items) {
    const el = target(container); if (!el) return;
    el.classList.add("kw-kpi-grid");
    el.innerHTML = items.map((item) => `<article class="kw-kpi" style="--kpi-color:${escape(domColor(item.color || palette[0]))}"><div class="kw-kpi__label">${escape(item.label)}</div><div class="kw-kpi__value">${escape(format(item.value,item.format))}</div>${item.delta == null ? "" : `<div class="kw-kpi__delta ${number(item.delta)>0?'kw-positive':number(item.delta)<0?'kw-negative':'kw-neutral'}">${number(item.delta)>0?'▲':number(item.delta)<0?'▼':'•'} ${escape(format(Math.abs(number(item.delta)),item.deltaFormat || 'percent'))} ${escape(item.deltaLabel || '')}</div>`}</article>`).join("");
  }

  function table(container, columns, rows, options = {}) {
    const el = target(container); if (!el) return;
    if (!rows.length) { el.innerHTML = `<div class="kw-table-empty">${escape(options.empty || "No records matched the selected filters.")}</div>`; return; }
    const value = (row,col) => typeof col.value === "function" ? col.value(row) : row[col.key];
    el.innerHTML = `<div class="table-responsive"><table class="table table-hover table-striped kw-data-table"><thead><tr>${columns.map(c=>`<th>${escape(c.label)}</th>`).join("")}</tr></thead><tbody>${rows.map(row=>`<tr>${columns.map(c=>`<td class="${escape(c.className||'')}">${c.html ? c.html(value(row,c),row) : escape(format(value(row,c),c.format))}</td>`).join("")}</tr>`).join("")}</tbody></table></div>`;
  }

  function pivot(container, rows, config) {
    const rowValues=[...new Set(rows.map(r=>text(r[config.row])))]; const colValues=[...new Set(rows.map(r=>text(r[config.column])))];
    const aggregate=(subset)=>{const vals=subset.map(r=>number(r[config.value])); if(config.aggregate==='count')return subset.length;if(config.aggregate==='average')return vals.length?vals.reduce((a,b)=>a+b,0)/vals.length:0;return vals.reduce((a,b)=>a+b,0)};
    const matrix=rowValues.map(rv=>({label:rv,values:colValues.map(cv=>aggregate(rows.filter(r=>text(r[config.row])===rv&&text(r[config.column])===cv)))}));
    const el=target(container); if(!el)return;
    el.innerHTML=`<div class="table-responsive"><table class="table table-bordered kw-data-table kw-pivot"><thead><tr><th>${escape(config.rowLabel||config.row)}</th>${colValues.map(v=>`<th>${escape(v)}</th>`).join('')}<th>Total</th></tr></thead><tbody>${matrix.map(r=>`<tr><th scope="row">${escape(r.label)}</th>${r.values.map(v=>`<td>${escape(format(v,config.format))}</td>`).join('')}<td><strong>${escape(format(r.values.reduce((a,b)=>a+b,0),config.format))}</strong></td></tr>`).join('')}</tbody></table></div>`;
    return { rows:rowValues, columns:colValues, matrix };
  }

  function heatmapTable(container, columns, rows, valueKeys) {
    const values=rows.flatMap(row=>valueKeys.map(key=>number(row[key]))); const min=Math.min(...values),max=Math.max(...values); const range=max-min||1;
    table(container,columns,rows,{empty:"No heatmap data available."});
    target(container)?.querySelectorAll('tbody tr').forEach((tr,rowIndex)=>columns.forEach((col,colIndex)=>{if(!valueKeys.includes(col.key))return;const ratio=(number(rows[rowIndex][col.key])-min)/range;const cell=tr.children[colIndex];cell.classList.add('kw-heat-cell');const strength=Math.round(10+ratio*70);cell.style.backgroundColor=`color-mix(in srgb, var(--kw-theme-good) ${strength}%, var(--kw-theme-surface))`;cell.style.color=ratio>.58?'#fff':'var(--kw-theme-text)';}));
  }

  function chart(canvas, type, spec, options = {}) {
    const el=target(canvas); if(!el || typeof Chart === 'undefined') return null;
    charts.get(el)?.destroy();
    let chartType=type, data={labels:spec.labels||[],datasets:spec.datasets||[]};
    if(type==='column') chartType='bar';
    if(type==='area'){chartType='line';data.datasets=data.datasets.map(d=>({...d,fill:true,tension:.28}));}
    if(type==='stacked_bar'){chartType='bar';options={...options,scales:{x:{stacked:true},y:{stacked:true},...(options.scales||{})}};}
    if(type==='bullet'){chartType='bar';data={labels:spec.labels,datasets:[{label:'Current',data:spec.current,backgroundColor:palette[0],barPercentage:.45},{label:'Target',data:spec.target,backgroundColor:'#d9e2dd',barPercentage:.8}]};options={...options,indexAxis:'y'};}
    if(type==='waterfall'){chartType='bar';let running=number(spec.start||0);const floating=(spec.changes||[]).map(change=>{const next=running+number(change);const pair=[running,next];running=next;return pair;});data={labels:spec.labels,datasets:[{label:spec.label||'Change',data:floating,backgroundColor:(spec.changes||[]).map(v=>number(v)>=0?'#26845d':'#c64b4b')}]};}
    if(type==='histogram'){chartType='bar';const vals=(spec.values||[]).map(number),bins=Math.max(3,spec.bins||8),min=Math.min(...vals),max=Math.max(...vals),width=(max-min||1)/bins,counts=Array(bins).fill(0);vals.forEach(v=>counts[Math.min(bins-1,Math.floor((v-min)/width))]++);data={labels:counts.map((_,i)=>`${(min+i*width).toFixed(1)}–${(min+(i+1)*width).toFixed(1)}`),datasets:[{label:spec.label||'Frequency',data:counts,backgroundColor:palette[0]}]};options={...options,plugins:{legend:{display:false}}};}
    const colored=data.datasets.map((dataset,index)=>({...dataset,borderColor:dataset.borderColor||palette[index%palette.length],backgroundColor:dataset.backgroundColor||`${palette[index%palette.length]}bb`,borderWidth:dataset.borderWidth??2}));
    const instance=new Chart(el,{type:chartType,data:{...data,datasets:colored},options:{responsive:true,maintainAspectRatio:false,...options}});charts.set(el,instance);return instance;
  }

  function sparkline(container, values, color=palette[0]) { const el=target(container);if(!el)return;const nums=values.map(number),min=Math.min(...nums),max=Math.max(...nums),range=max-min||1,points=nums.map((v,i)=>`${i*(88/Math.max(1,nums.length-1))+1},${26-((v-min)/range)*22}`).join(' ');el.innerHTML=`<svg class="kw-sparkline" viewBox="0 0 90 28" aria-label="Trend"><polyline fill="none" stroke="${escape(domColor(color))}" stroke-width="2" points="${points}"/></svg>`; }
  function treemap(container, items) {const el=target(container);if(!el)return;const total=items.reduce((s,i)=>s+number(i.value),0)||1;el.className='kw-treemap';el.innerHTML=items.map((item,i)=>`<div class="kw-treemap__cell" style="background:${domPalette[i%domPalette.length]};flex-grow:${Math.max(1,number(item.value)/total*100)}"><span>${escape(item.label)}</span><span class="kw-treemap__value">${escape(format(item.value,item.format))}</span></div>`).join('');}
  function statistical(container,type,groups){const el=target(container);if(!el)return;const width=900,height=320,pad=50,all=groups.flatMap(g=>g.values.map(number)),min=Math.min(...all),max=Math.max(...all),y=v=>height-pad-((v-min)/(max-min||1))*(height-pad*2),step=(width-pad*2)/groups.length;let shapes='';groups.forEach((g,i)=>{const vals=g.values.map(number).sort((a,b)=>a-b),x=pad+step*(i+.5),q=p=>vals[Math.min(vals.length-1,Math.floor((vals.length-1)*p))]||0;if(type==='box'){shapes+=`<line x1="${x}" y1="${y(vals[0])}" x2="${x}" y2="${y(vals.at(-1))}" stroke="var(--kw-theme-link)"/><rect x="${x-22}" y="${y(q(.75))}" width="44" height="${Math.max(1,y(q(.25))-y(q(.75)))}" fill="var(--kw-theme-good-soft)" stroke="var(--kw-theme-link)"/><line x1="${x-22}" y1="${y(q(.5))}" x2="${x+22}" y2="${y(q(.5))}" stroke="var(--kw-theme-text)" stroke-width="2"/>`;}else{const buckets=12,span=(max-min||1)/buckets,counts=Array(buckets).fill(0);vals.forEach(v=>counts[Math.min(buckets-1,Math.floor((v-min)/span))]++);const peak=Math.max(...counts)||1;const left=counts.map((c,b)=>`${x-(c/peak)*28},${y(min+(b+.5)*span)}`).join(' '),right=[...counts].reverse().map((c,ri)=>`${x+(c/peak)*28},${y(min+(buckets-ri-.5)*span)}`).join(' ');shapes+=`<polygon points="${left} ${right}" fill="var(--kw-theme-good-soft)" stroke="var(--kw-theme-link)"/>`;}shapes+=`<text x="${x}" y="${height-12}" text-anchor="middle" font-size="12" fill="var(--kw-theme-text)">${escape(g.label)}</text>`;});el.innerHTML=`<svg class="kw-stat-svg" viewBox="0 0 ${width} ${height}" role="img">${shapes}</svg>`;}
  function state(container,kind,message){const el=target(container);if(el)el.innerHTML=`<div class="kw-report-${escape(kind)}">${escape(message)}</div>`;}
  return { palette,supportedChartTypes,format,kpis,table,pivot,heatmapTable,chart,sparkline,treemap,boxPlot:(c,g)=>statistical(c,'box',g),violinPlot:(c,g)=>statistical(c,'violin',g),state };
})();
