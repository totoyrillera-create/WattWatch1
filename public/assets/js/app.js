'use strict';

const API_URL=(()=>{const b=window.location.pathname.replace(/\/[^/]*$/,'');return b+'/api.php';})();

async function api(action,data=null,method='GET'){
  const opts={method,headers:{'Content-Type':'application/json'}};
  let url=`${API_URL}?action=${action}`;
  if(method==='GET'&&data) Object.entries(data).forEach(([k,v])=>url+=`&${k}=${encodeURIComponent(v)}`);
  else if(data) opts.body=JSON.stringify(data);
  const res=await fetch(url,opts);
  const json=await res.json();
  if(json.status!=='ok') throw new Error(json.message||'API error');
  return json.data??json;
}

const $=id=>document.getElementById(id);
const $$=sel=>document.querySelectorAll(sel);

function showAlert(id,msg,type='success'){
  const el=$(id);if(!el)return;
  el.className=`alert alert-${type}`;el.textContent=msg;
  el.classList.remove('hidden');
  setTimeout(()=>el.classList.add('hidden'),3500);
}

// Dark mode
let darkMode=localStorage.getItem('ww_dark')==='1';
function applyTheme(){
  document.documentElement.setAttribute('data-theme',darkMode?'dark':'light');
  localStorage.setItem('ww_dark',darkMode?'1':'0');
  const btn=$('theme-btn');
  if(btn)btn.innerHTML=darkMode?ICONS.sun:ICONS.moon;
}
function toggleTheme(){darkMode=!darkMode;applyTheme();}

const state={user:null,page:'dashboard',rooms:[],users:[],anomalies:[],esp32Online:false};

const ROLE_STYLE={
  admin: {label:'Administrator',    avatarBg:'var(--avatar-bg)',avatarColor:'var(--text)',bc:'b-admin'},
  staff: {label:'Staff / Technician',avatarBg:'var(--avatar-bg)',avatarColor:'var(--text)',bc:'b-staff'},
};
const ROLE_PERMS_MAP={
  admin: ['dashboard','rooms','monitoring','anomalies','analytics','reports','thresholds','users','logs','settings','profile'],
  staff: ['dashboard','monitoring','anomalies','analytics','reports','profile'],
};

const ROOM_ICONS={
  ac:       {svg:`<svg width="20" height="20" fill="none" stroke="#3b82f6" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="10" rx="2"/><path d="M6 17v2M10 17v2M14 17v2M18 17v2M6 7V5M18 7V5"/></svg>`,bg:'#eff6ff'},
  light:    {svg:`<svg width="20" height="20" fill="none" stroke="#f59e0b" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 21h6M12 3a6 6 0 016 6c0 2.5-1.5 4.5-3 6H9c-1.5-1.5-3-3.5-3-6a6 6 0 016-6z"/></svg>`,bg:'#fffbeb'},
  projector:{svg:`<svg width="20" height="20" fill="none" stroke="#ef4444" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="10" rx="2"/><circle cx="17" cy="12" r="2"/><path d="M5 12h5"/></svg>`,bg:'#fef2f2'},
  fan:      {svg:`<svg width="20" height="20" fill="none" stroke="#8b5cf6" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M12 2a4 4 0 014 4c0 1.5-.5 3-2 4M12 22a4 4 0 01-4-4c0-1.5.5-3 2-4M2 12a4 4 0 014-4c1.5 0 3 .5 4 2M22 12a4 4 0 01-4 4c-1.5 0-3-.5-4-2"/></svg>`,bg:'#f5f3ff'},
  hvac:     {svg:`<svg width="20" height="20" fill="none" stroke="#0891b2" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>`,bg:'#ecfeff'},
  fridge:   {svg:`<svg width="20" height="20" fill="none" stroke="#22c55e" stroke-width="1.8" viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M5 10h14M9 6v2M9 14v4"/></svg>`,bg:'#f0fdf4'},
  computer: {svg:`<svg width="20" height="20" fill="none" stroke="#3b82f6" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>`,bg:'#eff6ff'},
  other:    {svg:`<svg width="20" height="20" fill="none" stroke="#64748b" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 3"/></svg>`,bg:'#f8fafc'},
};

const ICONS={
  bolt:      `<svg width="18" height="18" fill="white" viewBox="0 0 24 24"><path d="M13 3L4 14h7v7l9-11h-7V3z"/></svg>`,
  dashboard: `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>`,
  room:      `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>`,
  monitor:   `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>`,
  alert:     `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
  analytics: `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21H4.6A1.6 1.6 0 013 19.4V3"/><path d="M7 16l4-4 4 4 4-8"/></svg>`,
  report:    `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`,
  threshold: `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>`,
  users:     `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>`,
  logs:      `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>`,
  settings:  `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>`,
  profile:   `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>`,
  bell:      `<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>`,
  logout:    `<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>`,
  plus:      `<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>`,
  check:     `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>`,
  download:  `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`,
  arrow:     `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12,5 19,12 12,19"/></svg>`,
  edit:      `<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`,
  trash:     `<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3,6 5,6 21,6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>`,
  lock:      `<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>`,
  shield:    `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
  eye:       `<svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`,
  eyeoff:    `<svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`,
  help:      `<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
  trend:     `<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22,7 13.5,15.5 8.5,10.5 2,17"/><polyline points="16,7 22,7 22,13"/></svg>`,
  calendar2: `<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`,
  pulse:     `<svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>`,
  calendar:  `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`,
  moon:      `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>`,
  sun:       `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`,
  wifi:      `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0114.08 0"/><path d="M1.42 9a16 16 0 0121.16 0"/><path d="M8.53 16.11a6 6 0 016.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>`,
  wifioff:   `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0119 12.55"/><path d="M5 12.55a11 11 0 015.17-2.39"/><path d="M10.71 5.05A16 16 0 0122.56 9"/><path d="M1.42 9a15.91 15.91 0 014.7-2.88"/><path d="M8.53 16.11a6 6 0 016.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>`,
};

// ── Chart ──────────────────────────────────────────────────────
function renderChart(labels,values,containerId,height=200,color='#22c55e'){
  const el=$(containerId);if(!el)return;
  const max=Math.max(...values,1),W=800,H=height;
  const yMax=Math.ceil(max/1000)*1000||6000;
  const ySteps=[yMax,yMax*.75,yMax*.5,yMax*.25,0].map(v=>Math.round(v));
  const pts=values.map((v,i)=>`${(i/(values.length-1||1))*W},${H-10-(v/yMax)*(H-20)}`).join(' ');
  const step=Math.ceil(labels.length/8);
  const xLabs=labels.filter((_,i)=>i%step===0).map(l=>`<span>${l}</span>`).join('');
  el.innerHTML=`
    <p class="chart-y-label">Power (W)</p>
    <div class="chart-wrap">
      <div class="chart-y">${ySteps.map(v=>`<span>${v>=1000?(v/1000).toFixed(0)+'k':v}</span>`).join('')}</div>
      <div class="chart-svg-wrap">
        <svg viewBox="0 0 ${W} ${H}" height="${H}" preserveAspectRatio="none">
          <defs><linearGradient id="cg${containerId}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="${color}" stop-opacity=".18"/>
            <stop offset="100%" stop-color="${color}" stop-opacity=".01"/>
          </linearGradient></defs>
          <polygon points="0,${H} ${pts} ${W},${H}" fill="url(#cg${containerId})"/>
          <polyline points="${pts}" fill="none" stroke="${color}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
        </svg>
        <div class="chart-x">${xLabs}</div>
      </div>
    </div>`;
}

function demoChart(){
  const vals=[800,720,680,660,640,620,720,1100,2200,3800,4600,5200,5432,5100,4800,4400,3900,3400,2800,2200,1800,1400,1100,900];
  const lbs=Array.from({length:24},(_,i)=>{const h=i%12||12,s=i<12?'AM':'PM';return`${h}:00 ${s}`;});
  return{labels:lbs,values:vals};
}
function weekChart(){
  const days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  return{labels:days,values:[38000,42000,35000,44000,48000,29000,22000]};
}
function monthChart(){
  const days=Array.from({length:30},(_,i)=>`${i+1}`);
  return{labels:days,values:[38,42,35,44,48,29,22,40,45,52,38,42,35,44,48,29,22,40,45,38,42,35,44,48,29,22,40,45,38,42].map(v=>v*1000)};
}

function badge(text,cls){return`<span class="badge ${cls}">${text}</span>`;}
function prog(pct,cls){return`<div class="prog-wrap"><div class="prog-bar ${cls}" style="width:${Math.min(pct,100)}%"></div></div>`;}

// ── Navigation ────────────────────────────────────────────────
function navigate(page){
  state.page=page;
  $$('.nav-item').forEach(el=>el.classList.toggle('active',el.dataset.page===page));
  const role=state.user?.role_key;
  const titles={dashboard:'Dashboard',rooms:'Rooms / Equipment',monitoring:'Real-time Monitoring',anomalies:'Anomalies',analytics:'Analytics',reports:'Reports',thresholds:'Thresholds',users:'User Management',logs:'System Logs',settings:'Settings',profile:'My Profile'};
  const subs={dashboard:`Welcome back, ${state.user?.full_name}!`,rooms:'Manage monitored rooms and devices',monitoring:'Live electricity readings',anomalies:'Detected power anomalies',analytics:'Hourly patterns, weekly trends, room breakdown & consumption forecast',reports:'Consumption summaries',thresholds:'Set power limits',users:'Manage system users',logs:'Activity and audit trail',settings:'Configure WattWatch',profile:'Manage your account'};
  const tl=$('topbar-title'),ts=$('topbar-sub');
  if(tl)tl.textContent=titles[page]||'WattWatch';
  if(ts)ts.textContent=subs[page]||'';
  const pages={dashboard:renderDashboard,rooms:renderRooms,monitoring:renderMonitoring,anomalies:renderAnomalies,analytics:renderAnalytics,reports:renderReports,thresholds:renderThresholds,users:renderUsers,logs:renderLogs,settings:renderSettings,profile:renderProfile};
  (pages[page]||renderDashboard)();
}

// ── Dashboard ─────────────────────────────────────────────────
async function renderDashboard(){
  const pc=$('page-content');
  pc.innerHTML=`<div class="spinner-wrap"><div class="spinner"></div></div>`;
  let stats=null;
  try{stats=await api('dashboard_stats');}catch{}
  const rooms=stats?.rooms||[];
  const totalPower=rooms.reduce((s,r)=>s+parseFloat(r.power_watts||0),0);
  const todayE=parseFloat(stats?.today_energy||45.67).toFixed(2);
  const monthE=parseFloat(stats?.month_energy||1256.34).toFixed(2);
  const activeAnom=parseInt(stats?.active_anomalies||2);
  const now=new Date();
  const dateStr=now.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});

  pc.innerHTML=`
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--icon-bg-green)"><svg width="26" height="26" fill="#22c55e" viewBox="0 0 24 24"><path d="M13 3L4 14h7v7l9-11h-7V3z"/></svg></div>
        <div class="stat-text"><label>Total Power (Now)</label><h2 class="text-green">${totalPower.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g,',')} W</h2><small>Updated: ${now.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'})}</small></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--icon-bg-blue)">${ICONS.trend}</div>
        <div class="stat-text"><label>Total Energy (Today)</label><h2 class="text-blue">${todayE} kWh</h2><small>From 12:00 AM</small></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--icon-bg-yellow)">${ICONS.calendar2}</div>
        <div class="stat-text"><label>Total Energy (This Month)</label><h2 class="text-yellow">${monthE} kWh</h2><small>${now.toLocaleDateString('en-US',{month:'long',day:'numeric'})} – ${dateStr}</small></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--icon-bg-red)">${ICONS.pulse}</div>
        <div class="stat-text"><label>Active Anomalies</label><h2 class="text-red">${activeAnom}</h2><small>Requires attention</small></div>
      </div>
    </div>

    <div class="dash-mid">
      <div class="chart-card">
        <div class="chart-header"><h3>Power Consumption Over Time</h3><span class="chart-date-label">${dateStr}</span></div>
        <div id="main-chart"></div>
      </div>
      <div class="anom-panel">
        <div class="panel-header"><h3>Recent Anomalies</h3><a href="#" onclick="navigate('anomalies');return false">View all</a></div>
        <div id="dash-anomalies"></div>
        <button class="btn-view-all-anom" onclick="navigate('anomalies')">View All Anomalies ${ICONS.arrow}</button>
      </div>
    </div>

    <div class="dash-bot">
      <div class="room-section">
        <div class="flex-bc mb-16">
          <h3 style="font-size:14px;font-weight:700">Current Power by Room / Equipment</h3>
          <a href="#" style="font-size:12px;color:var(--green);font-weight:600" onclick="navigate('monitoring');return false">View all</a>
        </div>
        <div class="room-scroll" id="room-cards"></div>
      </div>
      <div class="activity-panel">
        <div class="panel-header">
          <h3>Recent Activity</h3>
          <a href="#" onclick="navigate('logs');return false" style="font-size:12px;color:var(--green);font-weight:600">View all</a>
        </div>
        <div id="activity-list"></div>
      </div>
    </div>
    <p class="pg-footer">WattWatch v1.0 · © ${now.getFullYear()} All rights reserved. &nbsp;|&nbsp; Role: ${ROLE_STYLE[state.user?.role_key]?.label||''}</p>`;

  renderChart(demoChart().labels,demoChart().values,'main-chart',230);

  const rcEl=$('room-cards');
  rcEl.innerHTML=rooms.length?rooms.map(r=>{
    const isA=r.status==='anomaly';
    const ri=ROOM_ICONS[r.icon_key||'other']||ROOM_ICONS.other;
    return`<div class="room-card ${isA?'anomaly':''}">
      <div class="room-card-icon" style="background:${ri.bg}">${ri.svg}</div>
      <h4>${r.room_name}</h4><p>${r.equipment_label}</p>
      <div class="room-power ${isA?'bad':'ok'}">${parseFloat(r.power_watts||0).toFixed(0)} W</div>
      <div class="room-status"><div class="status-dot ${isA?'bad':'ok'}"></div><span>${isA?'Anomaly':'Normal'}</span></div>
    </div>`;
  }).join(''):`<p class="text-muted" style="font-size:13px;padding:20px 0">No rooms yet. Add rooms and connect the ESP32.</p>`;

  loadDashAnomalies();
  loadDashActivity();
}

async function loadDashAnomalies(){
  const el=$('dash-anomalies');if(!el)return;
  try{
    const list=await api('get_anomalies',{status:'active'});
    el.innerHTML=list.slice(0,2).map(a=>`
      <div class="anom-item">
        <div class="anom-item-icon"><svg width="18" height="18" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><circle cx="12" cy="16" r="1" fill="#ef4444"/></svg></div>
        <div class="anom-item-body">
          <div class="anom-item-top"><strong>${a.room_name} – ${a.equipment_label}</strong><span class="badge b-high">${a.type_label}</span></div>
          <p>Current: ${parseFloat(a.power_at_event).toLocaleString()} W &nbsp;•&nbsp; Threshold: ${parseFloat(a.threshold_used).toLocaleString()} W</p>
          <small>${a.detected_at}</small>
        </div>
      </div>`).join('')||'<p class="text-muted" style="font-size:12px;padding:16px 0 8px">No active anomalies.</p>';
  }catch{el.innerHTML='<p class="text-muted" style="font-size:12px;padding:12px 0">Could not load.</p>';}
}

async function loadDashActivity(){
  const el=$('activity-list');if(!el)return;
  const actIcon={
    auth:    {svg:`<svg width="14" height="14" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,bg:'#eff6ff'},
    anomaly: {svg:`<svg width="14" height="14" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>`,bg:'#fffbeb'},
    room:    {svg:`<svg width="14" height="14" fill="none" stroke="#22c55e" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>`,bg:'#f0fdf4'},
    report:  {svg:`<svg width="14" height="14" fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>`,bg:'#eff6ff'},
    settings:{svg:`<svg width="14" height="14" fill="none" stroke="#8b5cf6" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/></svg>`,bg:'#f5f3ff'},
    system:  {svg:`<svg width="14" height="14" fill="none" stroke="#64748b" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/></svg>`,bg:'#f8fafc'},
  };
  try{
    const logs=await api('get_logs');
    el.innerHTML=logs.slice(0,5).map(l=>{
      const ic=actIcon[l.log_type]||actIcon.system;
      const t=l.logged_at?l.logged_at.split(' ')[1]?.substr(0,5):'';
      return`<div class="activity-item">
        <div class="activity-icon" style="background:${ic.bg}">${ic.svg}</div>
        <div class="activity-body"><p>${l.action}</p></div>
        <span class="activity-time">${t}</span>
      </div>`;
    }).join('')||'<p class="text-muted" style="font-size:12px;padding:12px 0">No recent activity.</p>';
  }catch{el.innerHTML='<p class="text-muted" style="font-size:12px;padding:12px 0">Could not load.</p>';}
}

// ── Rooms ────────────────────────────────────────────────────
async function renderRooms(){
  $('page-content').innerHTML=`
    <div class="page-action-bar">
      <button class="btn btn-primary" onclick="showRoomForm()">${ICONS.plus} Add Room</button>
    </div>
    <div id="room-alert" class="alert hidden"></div>
    <div id="room-form-wrap"></div>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Room</th><th>Equipment</th><th>Location</th><th>Threshold (W)</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="rooms-tbody"><tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner" style="margin:0 auto"></div></td></tr></tbody>
    </table></div>`;
  loadRoomsTable();
}

async function loadRoomsTable(){
  try{
    const rooms=await api('get_rooms');state.rooms=rooms;
    $('rooms-tbody').innerHTML=rooms.map(r=>`
      <tr>
        <td class="fw7">${r.room_name}</td><td>${r.equipment_label}</td>
        <td class="text-muted">${r.building_name}</td>
        <td>${parseFloat(r.threshold_watts).toLocaleString()}</td>
        <td>${badge(r.status==='anomaly'?'Anomaly':'Normal',r.status==='anomaly'?'b-anomaly':'b-normal')}</td>
        <td><div class="flex gap-6">
          <button class="btn btn-blue btn-sm" onclick="showRoomForm(${r.room_id})">${ICONS.edit} Edit</button>
          <button class="btn btn-danger btn-sm" onclick="deleteRoom(${r.room_id})">${ICONS.trash} Delete</button>
        </div></td>
      </tr>`).join('')||`<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">No rooms found.</td></tr>`;
  }catch{$('rooms-tbody').innerHTML=`<tr><td colspan="6" style="text-align:center;color:var(--red);padding:20px">Failed to load rooms.</td></tr>`;}
}

function showRoomForm(id=null){
  const r=id?state.rooms.find(x=>x.room_id==id):null;
  $('room-form-wrap').innerHTML=`
    <div class="form-panel"><h3>${r?'Edit Room':'Add New Room'}</h3>
      <div class="form-grid">
        <div class="form-group"><label>Room Name</label><input id="rf-name" class="form-ctrl" value="${r?.room_name||''}" placeholder="e.g. Room 204"></div>
        <div class="form-group"><label>Equipment Label</label><input id="rf-equip" class="form-ctrl" value="${r?.equipment_label||''}" placeholder="e.g. Air Conditioner"></div>
        <div class="form-group"><label>Building / Location</label><input id="rf-bldg" class="form-ctrl" value="${r?.building_name||''}" placeholder="e.g. Building A"></div>
        <div class="form-group"><label>Power Threshold (W)</label><input id="rf-thresh" type="number" class="form-ctrl" value="${r?.threshold_watts||''}" placeholder="e.g. 3000"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" onclick="saveRoom(${id||'null'})">${ICONS.check} Save</button>
        <button class="btn btn-secondary" onclick="$('room-form-wrap').innerHTML=''">Cancel</button>
      </div>
    </div>`;
}

async function saveRoom(id){
  const d={room_name:$('rf-name').value.trim(),equipment_label:$('rf-equip').value.trim(),building_name:$('rf-bldg').value.trim()||'Building A',threshold_watts:parseFloat($('rf-thresh').value)||1000,type_id:8};
  if(!d.room_name||!d.equipment_label)return showAlert('room-alert','Please fill in all required fields.','error');
  try{
    if(id){d.room_id=id;await api('update_room',d,'POST');}else{await api('add_room',d,'POST');}
    $('room-form-wrap').innerHTML='';
    showAlert('room-alert',id?'Room updated!':'Room added!');
    loadRoomsTable();
  }catch(e){showAlert('room-alert',e.message,'error');}
}

async function deleteRoom(id){
  if(!confirm('Remove this room?'))return;
  try{await api('delete_room',{room_id:id},'POST');loadRoomsTable();}catch(e){alert(e.message);}
}

// ── Real-time Monitoring (with ESP32 status + Daily/Weekly/Monthly chart tabs) ──
async function renderMonitoring(){
  const pc=$('page-content');
  pc.innerHTML=`
    <div class="page-action-bar">
      <div id="esp32-status-badge" class="esp32-badge esp32-offline">${ICONS.wifioff} ESP32 Offline</div>
    </div>
    <div class="grid-monitor">
      <div class="card" style="height:fit-content">
        <div style="padding:12px 14px;border-bottom:1px solid var(--border)">
          <p style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Select Location</p>
        </div>
        <div id="monitor-list"></div>
      </div>
      <div id="monitor-detail"><div class="spinner-wrap"><div class="spinner"></div></div></div>
    </div>`;

  // Check ESP32 online (last reading within 2 mins)
  checkEsp32Status();

  try{
    const s=await api('dashboard_stats');state.rooms=s.rooms||[];
    $('monitor-list').innerHTML=state.rooms.map(r=>`
      <button class="monitor-list-item" data-rid="${r.room_id}" onclick="selectMonitor(${r.room_id})">
        <div class="monitor-dot" style="background:${r.status==='anomaly'?'var(--red)':'var(--green)'}"></div>
        <div><h5>${r.room_name}</h5><span>${r.equipment_label}</span></div>
        <span class="monitor-list-power" style="color:${r.status==='anomaly'?'var(--red)':'var(--text)'}">${parseFloat(r.power_watts||0).toFixed(0)} W</span>
      </button>`).join('');
    if(state.rooms.length)selectMonitor(state.rooms[0].room_id);
    else $('monitor-detail').innerHTML=`<div class="card card-body"><p class="text-muted">No rooms configured yet.</p></div>`;
  }catch{$('monitor-detail').innerHTML=`<div class="card card-body"><p class="text-muted">Could not load rooms.</p></div>`;}
}

async function checkEsp32Status(){
  const badge=$('esp32-status-badge');if(!badge)return;
  try{
    // Check if latest reading is within 2 minutes
    const readings=await api('get_readings',{room_id:1,limit:1});
    if(readings&&readings.length){
      const lastRead=new Date(readings[0].read_at);
      const diffMin=(Date.now()-lastRead.getTime())/60000;
      if(diffMin<2){
        badge.className='esp32-badge esp32-online';
        badge.innerHTML=`${ICONS.wifi} ESP32 Online`;
        state.esp32Online=true;return;
      }
    }
  }catch{}
  badge.className='esp32-badge esp32-offline';
  badge.innerHTML=`${ICONS.wifioff} ESP32 Offline`;
  state.esp32Online=false;
}

async function selectMonitor(id){
  $$('.monitor-list-item').forEach(el=>el.classList.toggle('active',el.dataset.rid==id));
  const r=state.rooms.find(x=>x.room_id==id);if(!r)return;
  const isA=r.status==='anomaly';
  $('monitor-detail').innerHTML=`
    <div class="card card-body mb-16">
      <div class="flex-bc mb-16">
        <div>
          <h2 style="font-size:17px;font-weight:800">${r.room_name} — ${r.equipment_label}</h2>
          <p style="font-size:12px;color:var(--text-muted);margin-top:3px">${r.building_name} · Updated live</p>
        </div>
        ${badge(isA?'Anomaly':'Normal',isA?'b-anomaly':'b-normal')}
      </div>
      <div class="metric-grid">
        ${[
          ['Voltage',`${parseFloat(r.voltage||220).toFixed(1)} V`,'#3b82f6','#eff6ff'],
          ['Current',`${parseFloat(r.current_amp||0).toFixed(3)} A`,'#f59e0b','#fffbeb'],
          ['Power',`${parseFloat(r.power_watts||0).toFixed(1)} W`,isA?'#ef4444':'#22c55e',isA?'#fef2f2':'#f0fdf4'],
          ['Energy',`${parseFloat(r.energy_kwh||0).toFixed(4)} kWh`,'#8b5cf6','#f5f3ff'],
        ].map(([l,v,c,bg])=>`
          <div class="metric-box" style="background:${bg}">
            <label style="color:${c};font-size:11.5px;font-weight:600;display:block;margin-bottom:4px">${l}</label>
            <div style="font-size:20px;font-weight:800;color:${c}">${v}</div>
          </div>`).join('')}
      </div>
    </div>
    <div class="chart-card">
      <div class="chart-header">
        <h3>Power Trend</h3>
        <div class="filter-tabs" style="margin:0">
          <button class="ftab active" id="mt-daily"  onclick="switchMonitorChart('daily',${id})">Daily</button>
          <button class="ftab"        id="mt-weekly" onclick="switchMonitorChart('weekly',${id})">Weekly</button>
          <button class="ftab"        id="mt-monthly" onclick="switchMonitorChart('monthly',${id})">Monthly</button>
        </div>
      </div>
      <div id="detail-chart"></div>
      <p style="font-size:11px;color:var(--text-muted);margin-top:8px">Threshold: ${parseFloat(r.threshold_watts||0).toLocaleString()} W</p>
    </div>`;
  switchMonitorChart('daily',id,r);
}

function switchMonitorChart(period,id,room){
  $$('#mt-daily,#mt-weekly,#mt-monthly').forEach(b=>{if(b)b.classList.remove('active');});
  const btn=$(`mt-${period}`);if(btn)btn.classList.add('active');
  const r=room||state.rooms.find(x=>x.room_id==id);
  const scale=parseFloat(r?.power_watts||1000)/5432;
  const charts={daily:demoChart(),weekly:weekChart(),monthly:monthChart()};
  const cd=charts[period]||demoChart();
  renderChart(cd.labels,cd.values.map(v=>v*scale),'detail-chart',180);
}

// ── Anomalies (with auto-delete resolved after 30 days note) ──
async function renderAnomalies(){
  $('page-content').innerHTML=`
    <div class="page-action-bar">
      <span style="font-size:11px;color:var(--text-muted);display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Resolved anomalies are removed after 30 days
      </span>
    </div>
    <div class="filter-tabs">
      <button class="ftab active" onclick="filterAnom('all',this)">All</button>
      <button class="ftab" onclick="filterAnom('active',this)">Active</button>
      <button class="ftab" onclick="filterAnom('resolved',this)">Resolved</button>
    </div>
    <div id="anom-list"><div class="spinner-wrap"><div class="spinner"></div></div></div>`;
  loadAnomList('all');
}

async function loadAnomList(filter){
  const el=$('anom-list');if(!el)return;
  const canAct=['admin','staff'].includes(state.user?.role_key);
  try{
    const list=await api('get_anomalies',{status:filter});
    el.innerHTML=list.length?list.map(a=>{
      const isA=a.status==='active';
      const over=parseFloat(a.power_at_event)-parseFloat(a.threshold_used);
      const pct=(over/parseFloat(a.threshold_used)*100).toFixed(1);
      return`<div class="anom-card ${isA?'active-anom':''}">
        <div class="anom-card-icon" style="background:${isA?'var(--red-bg)':'var(--green-bg)'}">
          <svg width="18" height="18" fill="none" stroke="${isA?'#ef4444':'#22c55e'}" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div style="flex:1">
          <div class="flex gap-8" style="align-items:center;flex-wrap:wrap;margin-bottom:4px">
            <strong style="font-size:13.5px">${a.room_name} — ${a.equipment_label}</strong>
            ${badge(a.type_label,'b-high')} ${badge(a.status,isA?'b-active':'b-resolved')}
          </div>
          <p style="font-size:12.5px;color:var(--text-secondary)">
            Current: <strong>${parseFloat(a.power_at_event).toLocaleString()} W</strong> · Threshold: ${parseFloat(a.threshold_used).toLocaleString()} W · Exceeded by ${pct}%
          </p>
          <p style="font-size:11px;color:var(--text-muted);margin-top:3px">Detected: ${a.detected_at}${a.resolved_by_name?' · Resolved by: '+a.resolved_by_name:''}</p>
          ${!isA?`<p style="font-size:10.5px;color:var(--text-muted);margin-top:2px">Auto-removed 30 days after resolution</p>`:''}
        </div>
        ${isA&&canAct?`<button class="btn btn-secondary btn-sm" onclick="resolveAnom(${a.anomaly_id})">${ICONS.check} Resolve</button>`:''}
      </div>`;
    }).join(''):`<div class="empty-state">${ICONS.check}<p>No anomalies found.</p></div>`;
  }catch{el.innerHTML=`<div class="empty-state"><p style="color:var(--red)">Failed to load.</p></div>`;}
}

function filterAnom(f,btn){$$('.ftab').forEach(e=>e.classList.remove('active'));btn.classList.add('active');loadAnomList(f);}
async function resolveAnom(id){try{await api('resolve_anomaly',{anomaly_id:id},'POST');loadAnomList('all');}catch(e){alert(e.message);}}

// ── Analytics (Data Science) ─────────────────────────────────
async function renderAnalytics(){
  const pc=$('page-content');
  pc.innerHTML=`<div class="spinner-wrap"><div class="spinner"></div></div>`;

  // Demo fallbacks (used when DB has no readings yet)
  const DEMO_HOURLY=[620,540,490,470,460,480,780,1820,3200,4100,4800,5100,5300,5000,4700,4300,3800,3200,2600,2100,1700,1300,990,740];
  const DEMO_WEEKLY={days:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],kwh:[38.2,42.1,35.0,44.8,48.3,29.1,22.4]};
  const DEMO_ROOMS=[
    {room_name:'Server Room',  equipment_label:'HVAC',         avg_power:4800,peak_power:5200,total_kwh:72.0, anomaly_count:0},
    {room_name:'Room 204',     equipment_label:'Air Cond.',    avg_power:2850,peak_power:5012,total_kwh:42.1, anomaly_count:2},
    {room_name:'Computer Lab', equipment_label:'Room 103',     avg_power:1240,peak_power:2200,total_kwh:18.6, anomaly_count:1},
    {room_name:'Cafeteria',    equipment_label:'Refrigerators',avg_power:950, peak_power:1100,total_kwh:14.3, anomaly_count:0},
    {room_name:'Room 101',     equipment_label:'Projector',    avg_power:420, peak_power:812, total_kwh:6.3,  anomaly_count:1},
    {room_name:'Room 201',     equipment_label:'Electric Fan', avg_power:110, peak_power:145, total_kwh:1.7,  anomaly_count:0},
  ];

  let hourlyData=DEMO_HOURLY, weekDays=DEMO_WEEKLY.days, weekKwh=DEMO_WEEKLY.kwh;
  let rooms=DEMO_ROOMS, weekTotal=260.0, dailyAvg=37.1, monthForecast=1113.0, rate=6.00;
  let anomalyStats={total:4,total_excess:5693,first_hour:10,last_hour:14};
  let topRoom={room_name:'Room 204',equipment_label:'Air Conditioner',cnt:2};
  let hasLiveData=false;

  try{
    const d=await api('get_analytics');
    if(d.hourly_pattern&&d.hourly_pattern.some(v=>v>0)){
      hourlyData=d.hourly_pattern; hasLiveData=true;
    }
    if(d.weekly&&d.weekly.length){
      weekDays=d.weekly.map(w=>w.day_name.substr(0,3));
      weekKwh=d.weekly.map(w=>parseFloat(w.total_kwh));
    }
    if(d.rooms&&d.rooms.length) rooms=d.rooms;
    if(d.week_total_kwh>0) weekTotal=d.week_total_kwh;
    if(d.daily_avg_kwh>0)  dailyAvg=d.daily_avg_kwh;
    if(d.month_forecast>0) monthForecast=d.month_forecast;
    if(d.kwh_rate)         rate=parseFloat(d.kwh_rate);
    if(d.anomaly_stats)    anomalyStats=d.anomaly_stats;
    if(d.top_room)         topRoom=d.top_room;
  }catch{}

  // Trigger auto-cleanup of old resolved anomalies
  api('auto_resolve_anomalies',null,'POST').catch(()=>{});

  const peakDayIdx=weekKwh.indexOf(Math.max(...weekKwh));
  const peakDay=weekDays[peakDayIdx]||'—';
  const estMonthlyCost=(monthForecast*rate).toFixed(2);
  const avgExcess=anomalyStats.total>0?Math.round(parseFloat(anomalyStats.total_excess||0)/anomalyStats.total):0;
  const totalKwhRooms=rooms.reduce((s,r)=>s+parseFloat(r.total_kwh||0),0)||1;

  pc.innerHTML=`
    ${!hasLiveData?`<div style="margin-bottom:14px;padding:10px 14px;background:var(--icon-bg-yellow);border:1px solid var(--yellow);border-radius:var(--radius);font-size:12px;color:var(--text-secondary)">
      <strong>Demo data shown</strong> — connect the ESP32 and send readings to see live analytics.
    </div>`:''}

    <!-- KPI Summary -->
    <div class="stats-row mb-20">
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--icon-bg-blue)">${ICONS.analytics}</div>
        <div class="stat-text"><label>Total This Week</label><h2>${weekTotal.toFixed(1)} kWh</h2><small>7-day consumption</small></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--icon-bg-green)"><svg width="24" height="24" fill="none" stroke="#22c55e" stroke-width="2" viewBox="0 0 24 24"><polyline points="22,7 13.5,15.5 8.5,10.5 2,17"/></svg></div>
        <div class="stat-text"><label>Daily Average</label><h2>${dailyAvg.toFixed(1)} kWh</h2><small>Last 30 days</small></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--icon-bg-yellow)"><svg width="24" height="24" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26 12,2"/></svg></div>
        <div class="stat-text"><label>Peak Day</label><h2>${peakDay}</h2><small>${Math.max(...weekKwh).toFixed(1)} kWh peak</small></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--icon-bg-red)"><svg width="24" height="24" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <div class="stat-text"><label>Est. Monthly Cost</label><h2>₱${parseFloat(estMonthlyCost).toLocaleString()}</h2><small>@ ₱${rate}/kWh rate</small></div>
      </div>
    </div>

    <!-- Hourly pattern + Weekly bar -->
    <div class="grid-2 mb-16">
      <div class="chart-card">
        <div class="chart-header"><h3>Average Hourly Consumption Pattern</h3><span class="chart-date-label">Typical Day</span></div>
        <div id="hourly-chart"></div>
      </div>
      <div class="chart-card">
        <div class="chart-header"><h3>Weekly Energy (kWh)</h3><span class="chart-date-label">This Week</span></div>
        <div id="weekly-bar"></div>
      </div>
    </div>

    <!-- Room-by-room breakdown -->
    <div class="card card-body mb-16">
      <div class="flex-bc mb-14">
        <div class="card-title" style="margin:0">Room-by-Room Breakdown</div>
        <span style="font-size:11px;color:var(--text-muted)">Last 30 days · Avg / Peak / Energy / % Share / Anomalies</span>
      </div>
      <div class="tbl-wrap" style="box-shadow:none;border:1px solid var(--border)">
        <table>
          <thead><tr><th>Room</th><th>Equipment</th><th>Avg Power</th><th>Peak Power</th><th>Energy (kWh)</th><th>% of Total</th><th>Anomalies</th></tr></thead>
          <tbody>
            ${rooms.map(r=>{
              const share=(parseFloat(r.total_kwh||0)/totalKwhRooms*100).toFixed(1);
              const barW=Math.min(100,Math.round(parseFloat(r.avg_power||0)/6000*100));
              const barCls=barW>80?'prog-over':barW>60?'prog-warn':'prog-ok';
              return`<tr>
                <td class="fw7">${r.room_name}</td>
                <td style="color:var(--text-secondary)">${r.equipment_label}</td>
                <td><div style="display:flex;align-items:center;gap:8px">
                  <span class="fw7">${parseFloat(r.avg_power||0).toLocaleString()} W</span>
                  <div style="width:56px">${prog(barW,barCls)}</div>
                </div></td>
                <td>${parseFloat(r.peak_power||0).toLocaleString()} W</td>
                <td>${parseFloat(r.total_kwh||0).toFixed(1)} kWh</td>
                <td>${share}%</td>
                <td>${parseInt(r.anomaly_count||0)>0?badge(r.anomaly_count+' found','b-high'):badge('None','b-normal')}</td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>
      </div>
    </div>

    <!-- Forecast + Anomaly stats -->
    <div class="grid-2">
      <div class="card card-body">
        <div class="card-title">Consumption Forecast</div>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Based on 30-day rolling average (${dailyAvg.toFixed(1)} kWh/day)</p>
        ${[
          ['Projected Monthly Usage', monthForecast.toFixed(1)+' kWh', '30 × daily avg'],
          ['Estimated Monthly Cost',  '₱'+parseFloat(estMonthlyCost).toLocaleString(), '@ ₱'+rate+'/kWh'],
          ['Weekly Average',          (weekTotal/7).toFixed(1)+' kWh/day', 'Last 7 days'],
        ].map(([l,v,s])=>`
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:13px;color:var(--text-secondary)">${l}</span>
            <div style="text-align:right">
              <strong style="font-size:14px">${v}</strong><br>
              <span style="font-size:11px;color:var(--text-muted)">${s}</span>
            </div>
          </div>`).join('')}
      </div>
      <div class="card card-body">
        <div class="card-title">Anomaly Analysis</div>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px">Last 7 days anomaly detection summary</p>
        ${[
          ['Total Anomalies Detected', anomalyStats.total+' events'],
          ['Most Affected Room',        topRoom?(topRoom.room_name+' – '+topRoom.equipment_label):'None'],
          ['Peak Anomaly Window',       anomalyStats.total>0?(anomalyStats.first_hour+':00 – '+anomalyStats.last_hour+':00'):'—'],
          ['Avg Excess Power',          anomalyStats.total>0?(avgExcess.toLocaleString()+' W over limit'):'—'],
        ].map(([l,v])=>`
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:13px;color:var(--text-secondary)">${l}</span>
            <strong style="font-size:13px">${v}</strong>
          </div>`).join('')}
      </div>
    </div>`;

  renderChart(Array.from({length:24},(_,i)=>`${i}:00`),hourlyData,'hourly-chart',160);
  renderWeeklyBar(weekDays,weekKwh,'weekly-bar');
}

function renderWeeklyBar(labels,values,containerId){
  const el=$(containerId);if(!el)return;
  const max=Math.max(...values);
  el.innerHTML=`<div style="display:flex;align-items:flex-end;gap:8px;height:160px;padding:10px 0 4px">
    ${values.map((v,i)=>{
      const pct=Math.round(v/max*100);
      return`<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
        <span style="font-size:10px;color:var(--text-muted)">${v.toFixed(0)}</span>
        <div style="width:100%;height:${Math.max(pct,4)}%;background:var(--green);border-radius:4px 4px 0 0;opacity:.85;min-height:6px"></div>
        <span style="font-size:10px;color:var(--text-muted)">${labels[i]}</span>
      </div>`;
    }).join('')}
  </div>`;
}

// ── Reports (only summary report, tabs work) ──────────────────
async function renderReports(){
  $('page-content').innerHTML=`
    <div class="page-action-bar">
      <button class="btn btn-primary" onclick="window.print()">${ICONS.download} Export</button>
    </div>
    <div class="filter-tabs">
      <button class="ftab active" id="rep-daily"   onclick="loadReport('daily',this)">Daily</button>
      <button class="ftab"        id="rep-weekly"  onclick="loadReport('weekly',this)">Weekly</button>
      <button class="ftab"        id="rep-monthly" onclick="loadReport('monthly',this)">Monthly</button>
    </div>
    <div id="report-body"></div>`;
  loadReport('daily',$('rep-daily'));
}

async function loadReport(period,btn){
  $$('.ftab').forEach(e=>e.classList.remove('active'));
  if(btn)btn.classList.add('active');
  const el=$('report-body');if(!el)return;
  el.innerHTML=`<div class="spinner-wrap"><div class="spinner"></div></div>`;

  const defaults={daily:{energy:45.67,peak:5432,anomalies:2,rooms:7},weekly:{energy:312.5,peak:5800,anomalies:5,rooms:7},monthly:{energy:1256.34,peak:6200,anomalies:12,rooms:7}};
  const def=defaults[period];
  const cd=period==='weekly'?weekChart():period==='monthly'?monthChart():demoChart();
  let totalEnergy=def.energy,peakPower=def.peak,anomalyCount=def.anomalies,roomsMonitored=def.rooms,byRoom=[];

  try{
    const d=await api('get_report',{period});
    const s=d.summary||{};
    totalEnergy=parseFloat(s.total_energy)||totalEnergy;
    peakPower=parseFloat(s.peak_power)||peakPower;
    anomalyCount=parseInt(d.anomaly_count)??anomalyCount;
    roomsMonitored=parseInt(s.rooms_monitored)||roomsMonitored;
    byRoom=d.by_room||[];
  }catch{}

  const periodLabel={daily:'Today',weekly:'This Week',monthly:'This Month'}[period];

  el.innerHTML=`
    <!-- Summary boxes -->
    <div class="stats-row mb-16">
      <div class="stat-card"><div class="stat-text"><label>Total Energy</label><h2>${totalEnergy.toFixed(2)} kWh</h2><small>${periodLabel}</small></div></div>
      <div class="stat-card"><div class="stat-text"><label>Peak Power</label><h2>${peakPower.toLocaleString()} W</h2><small>Highest reading</small></div></div>
      <div class="stat-card"><div class="stat-text"><label>Anomalies</label><h2>${anomalyCount}</h2><small>${periodLabel}</small></div></div>
      <div class="stat-card"><div class="stat-text"><label>Rooms Monitored</label><h2>${roomsMonitored}</h2><small>Active locations</small></div></div>
    </div>

    <!-- Chart only -->
    <div class="chart-card mb-16">
      <div class="chart-header"><h3>Power Consumption — ${periodLabel}</h3></div>
      <div id="rep-chart"></div>
    </div>

    <!-- Room table -->
    <div class="card card-body">
      <div class="card-title">Consumption by Room</div>
      ${byRoom.length?byRoom.map(r=>{
        const pct=Math.min(100,Math.round((parseFloat(r.avg_power||0)/6000)*100));
        const cls=pct>80?'prog-over':pct>60?'prog-warn':'prog-ok';
        return`<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
          <div style="width:130px;flex-shrink:0"><strong style="font-size:12px">${r.room_name}</strong><br><span style="font-size:11px;color:var(--text-muted)">${r.equipment_label}</span></div>
          <div style="flex:1">${prog(pct,cls)}</div>
          <span style="font-size:12px;font-weight:700;width:70px;text-align:right">${parseFloat(r.avg_power||0).toFixed(0)} W</span>
        </div>`;
      }).join(''):
      // fallback demo room data
      [['Server Room','HVAC',4800],['Room 204','Air Cond.',2850],['Computer Lab','Room 103',1240],['Cafeteria','Fridges',950],['Room 101','Projector',420],['Room 201','Fan',110]].map(([n,e,w])=>{
        const pct=Math.min(100,Math.round(w/6000*100));
        const cls=pct>80?'prog-over':pct>60?'prog-warn':'prog-ok';
        return`<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
          <div style="width:130px;flex-shrink:0"><strong style="font-size:12px">${n}</strong><br><span style="font-size:11px;color:var(--text-muted)">${e}</span></div>
          <div style="flex:1">${prog(pct,cls)}</div>
          <span style="font-size:12px;font-weight:700;width:70px;text-align:right">${w.toLocaleString()} W</span>
        </div>`;
      }).join('')}
    </div>`;

  renderChart(cd.labels,cd.values,'rep-chart',180);
}

// ── Thresholds (with working Edit + Delete actions) ────────────
async function renderThresholds(){
  $('page-content').innerHTML=`
    <div id="thresh-alert" class="alert hidden"></div>
    <div class="tbl-wrap"><table>
      <thead><tr><th>Room</th><th>Equipment</th><th>Current Power</th><th>Threshold</th><th>Usage</th><th>Actions</th></tr></thead>
      <tbody id="thresh-tbody"><tr><td colspan="6" style="text-align:center;padding:30px"><div class="spinner" style="margin:0 auto"></div></td></tr></tbody>
    </table></div>`;
  loadThreshTable();
}

async function loadThreshTable(){
  try{
    const rooms=await api('get_rooms');state.rooms=rooms;
    $('thresh-tbody').innerHTML=rooms.length?rooms.map(r=>{
      const pw=parseFloat(r.power_watts||0),th=parseFloat(r.threshold_watts);
      const pct=Math.min(100,Math.round((pw/th)*100));
      const cls=pw>th?'prog-over':pct>80?'prog-warn':'prog-ok';
      return`<tr>
        <td class="fw7">${r.room_name}</td>
        <td style="color:var(--text-secondary)">${r.equipment_label}</td>
        <td><strong style="color:${pw>th?'var(--red)':'var(--text)'}">${pw.toFixed(0)} W</strong></td>
        <td id="td-th-${r.room_id}">
          <strong>${th.toLocaleString()} W</strong>
        </td>
        <td style="min-width:160px">
          ${prog(pct,cls)}
          <span style="font-size:11px;color:${pw>th?'var(--red)':'var(--text-muted)'};margin-top:3px;display:block">${pct}% of limit</span>
        </td>
        <td>
          <div class="flex gap-6">
            <button class="btn btn-blue btn-sm" onclick="editThresh(${r.room_id},${th})">${ICONS.edit} Edit</button>
            <button class="btn btn-danger btn-sm" onclick="confirmDeleteRoom(${r.room_id},'${r.room_name}')">${ICONS.trash} Remove</button>
          </div>
        </td>
      </tr>`;
    }).join(''):`<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted)">No rooms found.</td></tr>`;
  }catch{$('thresh-tbody').innerHTML=`<tr><td colspan="6" style="text-align:center;color:var(--red);padding:20px">Failed to load.</td></tr>`;}
}

function editThresh(id,cur){
  $(`td-th-${id}`).innerHTML=`
    <div class="flex gap-6">
      <input id="thi-${id}" type="number" class="form-ctrl" value="${cur}" style="width:90px;padding:6px 8px">
      <button class="btn btn-primary btn-sm" onclick="saveThresh(${id})">${ICONS.check}</button>
      <button class="btn btn-secondary btn-sm" onclick="loadThreshTable()">✕</button>
    </div>`;
  setTimeout(()=>{const i=$(`thi-${id}`);if(i){i.focus();i.select();}},50);
}

async function saveThresh(id){
  const v=parseFloat($(`thi-${id}`)?.value);
  if(!v||v<=0)return showAlert('thresh-alert','Enter a valid threshold value.','error');
  try{
    await api('set_threshold',{room_id:id,threshold_watts:v},'POST');
    showAlert('thresh-alert','Threshold updated successfully!');
    loadThreshTable();
  }catch(e){showAlert('thresh-alert',e.message,'error');}
}

function confirmDeleteRoom(id,name){
  if(!confirm(`Remove "${name}" from monitoring?\nThis will also delete all its readings and anomaly records.`))return;
  api('delete_room',{room_id:id},'POST').then(()=>{showAlert('thresh-alert','Room removed.');loadThreshTable();}).catch(e=>showAlert('thresh-alert',e.message,'error'));
}

// ── Users ───────────────────────────────────────────────────────
async function renderUsers(){
  $('page-content').innerHTML=`
    <div class="page-action-bar">
      <button class="btn btn-primary" onclick="showUserForm()">${ICONS.plus} Add User</button>
    </div>
    <div id="user-alert" class="alert hidden"></div>
    <div id="user-form-wrap"></div>
    <div id="users-list"><div class="spinner-wrap"><div class="spinner"></div></div></div>`;
  loadUsersList();
}

async function loadUsersList(){
  try{
    const users=await api('get_users');state.users=users;
    $('users-list').innerHTML=users.map(u=>{
      const rs=ROLE_STYLE[u.role_key]||ROLE_STYLE.staff;
      const isMe=u.user_id==state.user?.user_id;
      return`<div class="user-card">
        <div class="u-avatar" style="background:var(--avatar-bg);color:var(--text)">${u.avatar}</div>
        <div class="u-info">
          <h4>${u.full_name}${isMe?` <span style="font-size:10px;background:var(--green-bg);color:var(--green-dark);padding:2px 7px;border-radius:20px;font-weight:600">You</span>`:''}</h4>
          <p style="color:var(--text-secondary)">${u.email}${u.department?' · '+u.department:''}</p>
          <small style="color:var(--text-muted)">Last login: ${u.last_login||'Never'}</small>
        </div>
        <div class="u-right">
          <div class="u-badges">${badge(rs.label,rs.bc)} ${badge(u.status,u.status==='active'?'b-normal':'b-inactive')}</div>
          <div class="u-btns">
            <button class="btn btn-blue btn-sm" onclick="showUserForm(${u.user_id})">${ICONS.edit} Edit</button>
            ${!isMe?`<button class="btn btn-secondary btn-sm" onclick="toggleUser(${u.user_id})">${u.status==='active'?'Deactivate':'Activate'}</button>
            <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.user_id})">${ICONS.trash}</button>`:''}
          </div>
        </div>
      </div>`;
    }).join('');
  }catch{$('users-list').innerHTML=`<p style="color:var(--red);padding:20px">Failed to load users.</p>`;}
}

function showUserForm(id=null){
  const u=id?state.users.find(x=>x.user_id==id):null;
  $('user-form-wrap').innerHTML=`
    <div class="form-panel"><h3>${u?'Edit User':'Add New User'}</h3>
      <div class="form-grid">
        <div class="form-group"><label>Full Name</label><input id="uf-name" class="form-ctrl" value="${u?.full_name||''}"></div>
        <div class="form-group"><label>Email</label><input id="uf-email" type="email" class="form-ctrl" value="${u?.email||''}"></div>
        ${!u?`<div class="form-group"><label>Password</label><input id="uf-pw" type="password" class="form-ctrl" placeholder="Min. 6 characters"></div>`:''}
        <div class="form-group"><label>Department</label><input id="uf-dept" class="form-ctrl" value="${u?.department||''}"></div>
        <div class="form-group"><label>Role</label>
          <select id="uf-role" class="form-ctrl" onchange="updatePermPrev()">
            ${Object.entries(ROLE_STYLE).map(([k,v])=>`<option value="${k}" ${u?.role_key===k?'selected':''}>${v.label}</option>`).join('')}
          </select>
        </div>
      </div>
      <div style="margin-top:4px;padding:12px 14px;background:var(--bg-secondary);border-radius:var(--radius)">
        <p style="font-size:11px;font-weight:600;color:var(--text-muted);margin-bottom:8px">Permissions for selected role:</p>
        <div id="perm-prev" class="perm-tags">${(ROLE_PERMS_MAP[u?.role_key||'staff']||[]).map(p=>`<span class="perm-tag">${p}</span>`).join('')}</div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" onclick="saveUser(${id||'null'})">${ICONS.check} Save</button>
        <button class="btn btn-secondary" onclick="$('user-form-wrap').innerHTML=''">Cancel</button>
      </div>
    </div>`;
}

function updatePermPrev(){
  const r=$('uf-role')?.value||'staff';
  $('perm-prev').innerHTML=(ROLE_PERMS_MAP[r]||[]).map(p=>`<span class="perm-tag">${p}</span>`).join('');
}

async function saveUser(id){
  const d={full_name:$('uf-name').value.trim(),email:$('uf-email').value.trim(),role_key:$('uf-role').value,department:$('uf-dept').value.trim()};
  if(!d.full_name||!d.email)return showAlert('user-alert','Name and email are required.','error');
  if(!id)d.password=$('uf-pw')?.value||'password';
  try{
    if(id){d.user_id=id;await api('update_user',d,'POST');}else{await api('add_user',d,'POST');}
    $('user-form-wrap').innerHTML='';
    showAlert('user-alert',id?'User updated!':'User added!');
    loadUsersList();
  }catch(e){showAlert('user-alert',e.message,'error');}
}
async function toggleUser(id){try{await api('toggle_user',{user_id:id},'POST');loadUsersList();}catch(e){alert(e.message);}}
async function deleteUser(id){if(!confirm('Delete this user permanently?'))return;try{await api('delete_user',{user_id:id},'POST');loadUsersList();}catch(e){alert(e.message);}}

// ── Logs ────────────────────────────────────────────────────────
async function renderLogs(){
  $('page-content').innerHTML=`
    <div class="tbl-wrap"><div id="logs-wrap"><div class="spinner-wrap"><div class="spinner"></div></div></div></div>`;
  try{
    const logs=await api('get_logs');
    const CHIP={auth:'#eff6ff;#3b82f6',anomaly:'#fef2f2;#ef4444',settings:'#fffbeb;#d97706',room:'#f0fdf4;#16a34a',report:'#f5f3ff;#7c3aed',system:'#f8fafc;#64748b'};
    $('logs-wrap').innerHTML=logs.map((l,i)=>{
      const [bg,col]=(CHIP[l.log_type]||CHIP.system).split(';');
      return`<div class="log-row" style="background:${i%2?'var(--bg-secondary)':'var(--bg)'}">
        <span class="log-type-chip" style="background:${bg};color:${col}">${l.log_type}</span>
        <div style="flex:1"><strong>${l.full_name||'System'}</strong> — ${l.action}</div>
        <span style="font-size:11px;color:var(--text-muted);white-space:nowrap">${l.logged_at}</span>
      </div>`;
    }).join('')||`<div class="empty-state"><p>No logs found.</p></div>`;
  }catch{$('logs-wrap').innerHTML=`<div class="empty-state"><p style="color:var(--red)">Failed to load logs.</p></div>`;}
}

// ── Settings ────────────────────────────────────────────────────
async function renderSettings(){
  $('page-content').innerHTML=`
    <div id="set-alert" class="alert hidden"></div>
    <div class="grid-2">
      <div class="card card-body">
        <div class="card-title">General</div>
        <div class="form-group"><label>System Name</label><input id="s-name" class="form-ctrl" value="WattWatch"></div>
        <div class="form-group"><label>Timezone</label><input id="s-tz" class="form-ctrl" value="Asia/Manila"></div>
        <div class="form-group"><label>Refresh Rate (seconds)</label><input id="s-ref" type="number" class="form-ctrl" value="5"></div>
        <div class="form-group"><label>Data Retention (days)</label><input id="s-ret" type="number" class="form-ctrl" value="90"></div>
        <div class="form-group"><label>Electricity Rate (₱/kWh)</label><input id="s-rate" type="number" step="0.01" class="form-ctrl" value="6.00"></div>
        <div class="form-group"><label>Anomaly Auto-Delete (days after resolved)</label><input id="s-anomdel" type="number" class="form-ctrl" value="30"></div>
      </div>
      <div class="card card-body">
        <div class="card-title">Alert Notifications</div>
        ${[['alert_email','Email Alerts','Send alerts via email on anomaly'],
           ['alert_dashboard','Dashboard Alerts','Show popup on dashboard'],
           ['alert_buzzer','Buzzer / LED Alert','Trigger hardware buzzer on ESP32'],
          ].map(([k,l,d])=>`<div class="settings-row">
            <div><h5>${l}</h5><p style="color:var(--text-muted)">${d}</p></div>
            <div class="toggle-wrap"><input type="checkbox" id="tog-${k}" class="toggle-input" checked><label for="tog-${k}" class="toggle-label"></label></div>
          </div>`).join('')}
      </div>
    </div>
    <div style="margin-top:16px"><button class="btn btn-primary" onclick="saveSettings()">${ICONS.check} Save Settings</button></div>`;
  try{
    const s=await api('get_settings');
    if($('s-name'))$('s-name').value=s.system_name||'WattWatch';
    if($('s-tz'))$('s-tz').value=s.timezone||'Asia/Manila';
    if($('s-ref'))$('s-ref').value=s.refresh_rate||'5';
    if($('s-ret'))$('s-ret').value=s.data_retention||'90';
    ['alert_email','alert_dashboard','alert_buzzer'].forEach(k=>{const e=$('tog-'+k);if(e)e.checked=s[k]!=='0';});
    if($('s-rate'))$('s-rate').value=s.kwh_rate||'6.00';
  }catch{}
}

async function saveSettings(){
  const d={system_name:$('s-name')?.value||'WattWatch',timezone:$('s-tz')?.value||'Asia/Manila',refresh_rate:$('s-ref')?.value||'5',data_retention:$('s-ret')?.value||'90',alert_email:$('tog-alert_email')?.checked?'1':'0',alert_dashboard:$('tog-alert_dashboard')?.checked?'1':'0',alert_buzzer:$('tog-alert_buzzer')?.checked?'1':'0',kwh_rate:$('s-rate')?.value||'6.00'};
  try{await api('save_settings',d,'POST');showAlert('set-alert','Settings saved!');}
  catch(e){showAlert('set-alert',e.message,'error');}
}

// ── Profile ──────────────────────────────────────────────────────
async function renderProfile(){
  const u=state.user;const rs=ROLE_STYLE[u.role_key]||ROLE_STYLE.staff;
  const perms=ROLE_PERMS_MAP[u.role_key]||[];
  $('page-content').innerHTML=`
    <div class="grid-2">
      <div class="card card-body">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--border)">
          <div class="u-avatar" style="width:52px;height:52px;border-radius:14px;font-size:18px;background:var(--avatar-bg);color:var(--text)">${u.avatar}</div>
          <div><h3 style="font-size:17px;font-weight:800;margin:0 0 4px">${u.full_name}</h3>${badge(rs.label,rs.bc)}</div>
        </div>
        <div id="prof-alert" class="alert hidden"></div>
        <div class="form-group"><label>Full Name</label><input id="p-name" class="form-ctrl" value="${u.full_name}"></div>
        <div class="form-group"><label>Email Address</label><input id="p-email" type="email" class="form-ctrl" value="${u.email}"></div>
        <div class="form-group"><label>Department</label><input id="p-dept" class="form-ctrl" value="${u.department||''}"></div>
        <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="saveProfile()">${ICONS.check} Save Changes</button>
      </div>
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card card-body">
          <div class="card-title">${ICONS.lock} Change Password</div>
          <div id="pw-alert" class="alert hidden"></div>
          <div class="form-group"><label>Current Password</label><input id="p-cur" type="password" class="form-ctrl" placeholder="••••••••"></div>
          <div class="form-group"><label>New Password</label><input id="p-new" type="password" class="form-ctrl" placeholder="••••••••"></div>
          <div class="form-group"><label>Confirm Password</label><input id="p-conf" type="password" class="form-ctrl" placeholder="••••••••"></div>
          <button class="btn btn-secondary" style="width:100%;justify-content:center" onclick="changePassword()">${ICONS.lock} Update Password</button>
        </div>
        <div class="card card-body">
          <div class="card-title">${ICONS.shield} Your Permissions</div>
          <div class="perm-tags">${perms.map(p=>`<span class="perm-tag">${p.replace('_',' ')}</span>`).join('')}</div>
        </div>
      </div>
    </div>`;
}

async function saveProfile(){
  const d={full_name:$('p-name').value.trim(),email:$('p-email').value.trim(),department:$('p-dept').value.trim()};
  try{await api('update_profile',d,'POST');state.user.full_name=d.full_name;state.user.email=d.email;$('topbar-uname').textContent=d.full_name;showAlert('prof-alert','Profile updated!');}
  catch(e){showAlert('prof-alert',e.message,'error');}
}
async function changePassword(){
  const d={current_password:$('p-cur').value,new_password:$('p-new').value,confirm_password:$('p-conf').value};
  if(!d.current_password||!d.new_password)return showAlert('pw-alert','Fill all password fields.','error');
  try{await api('change_password',d,'POST');showAlert('pw-alert','Password updated!');$('p-cur').value=$('p-new').value=$('p-conf').value='';}
  catch(e){showAlert('pw-alert',e.message,'error');}
}

// ── Shell ─────────────────────────────────────────────────────
let notifOpen=false;

const NAV_DEF=[
  {id:'dashboard', label:'Dashboard',           icon:'dashboard', roles:['admin','staff']},
  {id:'rooms',     label:'Rooms / Equipment',   icon:'room',      roles:['admin']},
  {id:'monitoring',label:'Real-time Monitoring',icon:'monitor',   roles:['admin','staff']},
  {id:'anomalies', label:'Anomalies',           icon:'alert',     roles:['admin','staff'],badge:true},
  {id:'analytics', label:'Analytics',           icon:'analytics', roles:['admin','staff']},
  {id:'reports',   label:'Reports',             icon:'report',    roles:['admin','staff']},
  {id:'thresholds',label:'Thresholds',          icon:'threshold', roles:['admin']},
  {id:'users',     label:'Users',               icon:'users',     roles:['admin']},
  {id:'logs',      label:'Logs',                icon:'logs',      roles:['admin']},
  {id:'settings',  label:'Settings',            icon:'settings',  roles:['admin']},
  {id:'profile',   label:'My Profile',          icon:'profile',   roles:['admin','staff']},
];

function buildShell(user){
  const rs=ROLE_STYLE[user.role_key]||ROLE_STYLE.staff;
  const navHTML=NAV_DEF.filter(n=>(n.roles||[]).includes(user.role_key)).map(n=>{
    const label=n.label;
    return`<button class="nav-item ${n.id==='dashboard'?'active':''}" data-page="${n.id}" onclick="navigate('${n.id}')">
      <span class="nav-icon">${ICONS[n.icon]||''}</span>${label}
      ${n.badge?`<span class="nav-badge hidden" id="nav-anom-badge">0</span>`:''}
    </button>`;
  }).join('');

  document.body.innerHTML=`
  <div class="app-shell">
    <nav class="sidebar">
      <div class="sidebar-logo" style="cursor:pointer" onclick="navigate('dashboard')" title="Go to Dashboard">
        <div class="logo-bolt">${ICONS.bolt}</div>
        <div class="logo-text"><h1>WattWatch</h1><p>Smart Energy Monitoring</p></div>
      </div>
      <div class="sidebar-nav">${navHTML}</div>
      <div class="sidebar-bottom">
        <div>
          <span class="sys-status-dot"></span><span class="sys-status-label">System Status</span>
          <div class="sys-status-val">Online</div>
          <div class="sys-status-sub">All systems operational.</div>
        </div>
        ${user.role_key==='staff'?`<div class="help-box" style="margin-top:12px">
          <div class="help-title">${ICONS.help} Need Help?</div>
          <p>Contact your administrator<br>for assistance.</p>
        </div>`:''}
      </div>
      <div class="sidebar-footer">WattWatch v1.0<br>© ${new Date().getFullYear()} All rights reserved.</div>
    </nav>

    <div class="main-area">
      <div class="topbar">
        <div class="topbar-left">
          <h1 id="topbar-title">Dashboard</h1>
          <p id="topbar-sub">Welcome back, ${user.full_name}!</p>
        </div>
        <div class="topbar-right">
          <span class="topbar-meta" id="topbar-clock"></span>
          <button id="theme-btn" class="btn-icon" onclick="toggleTheme()" title="Toggle dark/light mode">${darkMode?ICONS.sun:ICONS.moon}</button>
          <div style="position:relative">
            <button class="btn-bell" onclick="toggleNotif()">${ICONS.bell}<span class="bell-badge hidden" id="bell-badge">0</span></button>
            <div id="notif-panel" class="user-dropdown hidden" style="min-width:300px;right:0">
              <div class="dropdown-head" style="display:flex;align-items:center;justify-content:space-between">
                <strong>Notifications</strong>
                <a href="#" style="font-size:12px;color:var(--green);font-weight:600" onclick="navigate('anomalies');closeNotif();return false">View all</a>
              </div>
              <div id="notif-list"><p style="padding:14px;font-size:12px;color:var(--text-muted)">Loading…</p></div>
            </div>
          </div>
          <div style="position:relative">
            <button class="user-chip" onclick="toggleDD()">
              <div class="user-avatar" style="background:var(--avatar-bg);color:var(--text)">${user.avatar}</div>
              <div class="user-info-text">
                <strong id="topbar-uname">${user.full_name}</strong>
                <span>${rs.label}</span>
              </div>
              <span class="user-caret">▼</span>
            </button>
            <div class="user-dropdown hidden" id="user-dd">
              <div class="dropdown-head">
                <strong>${user.full_name}</strong><br>
                <span style="font-size:12px;color:var(--text-muted)">${user.email}</span><br>
                <span style="font-size:11px;color:var(--text-muted);margin-top:4px;display:inline-block">${rs.label}</span>
              </div>
              <button class="dd-item" onclick="navigate('profile');toggleDD()">${ICONS.profile} My Profile</button>
              <div class="dd-divider"></div>
              <button class="dd-item danger" onclick="confirmLogout()">${ICONS.logout} Sign Out</button>
            </div>
          </div>
        </div>
      </div>
      <div class="page-content" id="page-content"><div class="spinner-wrap"><div class="spinner"></div></div></div>
    </div>
  </div>

  <!-- Logout modal -->
  <div id="logout-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg);border-radius:16px;padding:28px;max-width:360px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3)">
      <h3 style="font-size:17px;font-weight:800;margin:0 0 8px">Sign out?</h3>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 22px">You will be returned to the login page.</p>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn btn-secondary" onclick="closeLogoutModal()">Cancel</button>
        <button class="btn btn-danger" onclick="logout()">Sign Out</button>
      </div>
    </div>
  </div>`;

  applyTheme();

  const tick=()=>{const el=$('topbar-clock');if(el)el.textContent=new Date().toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'})+' • '+new Date().toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});};
  tick();setInterval(tick,30000);

  const pollBadge=async()=>{
    try{
      const list=await api('get_anomalies',{status:'active'});const n=list.length;
      const bb=$('bell-badge'),nb=$('nav-anom-badge');
      if(bb){bb.textContent=n;bb.classList.toggle('hidden',n===0);}
      if(nb){nb.textContent=n;nb.classList.toggle('hidden',n===0);}
    }catch{}
  };
  pollBadge();setInterval(pollBadge,30000);

  document.addEventListener('click',e=>{
    if(!e.target.closest('#user-dd')&&!e.target.closest('.user-chip'))$('user-dd')?.classList.add('hidden');
    if(!e.target.closest('#notif-panel')&&!e.target.closest('.btn-bell')){$('notif-panel')?.classList.add('hidden');notifOpen=false;}
  });

  navigate('dashboard');
}

function toggleDD(){$('user-dd')?.classList.toggle('hidden');}
function toggleNotif(){const p=$('notif-panel');if(!p)return;notifOpen=!notifOpen;p.classList.toggle('hidden',!notifOpen);if(notifOpen)loadNotifPanel();}
function closeNotif(){$('notif-panel')?.classList.add('hidden');notifOpen=false;}

async function loadNotifPanel(){
  const el=$('notif-list');if(!el)return;
  try{
    const list=await api('get_anomalies',{status:'active'});
    el.innerHTML=list.length?list.slice(0,5).map(a=>`
      <div style="display:flex;gap:10px;padding:10px 12px;border-bottom:1px solid var(--border)">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--red);flex-shrink:0;margin-top:5px"></div>
        <div>
          <p style="font-size:13px;font-weight:600;margin:0">${a.room_name} – ${a.equipment_label}</p>
          <p style="font-size:11px;color:var(--text-muted);margin:2px 0 0">${a.type_label} · ${parseFloat(a.power_at_event).toLocaleString()} W</p>
          <p style="font-size:11px;color:var(--text-muted);margin:1px 0 0">${a.detected_at}</p>
        </div>
      </div>`).join(''):`<p style="padding:16px 12px;font-size:13px;color:var(--text-muted)">No active anomalies.</p>`;
  }catch{el.innerHTML=`<p style="padding:14px;font-size:12px;color:var(--red)">Could not load.</p>`;}
}

function confirmLogout(){toggleDD();const m=$('logout-modal');if(m)m.style.display='flex';}
function closeLogoutModal(){const m=$('logout-modal');if(m)m.style.display='none';}

// ── Login ───────────────────────────────────────────────────────
function buildLogin(){
  document.body.innerHTML=`
  <div class="login-bg">
    <div class="login-wrap">
      <div class="login-logo-row">
        <div class="logo-bolt" style="width:42px;height:42px"><svg width="22" height="22" fill="white" viewBox="0 0 24 24"><path d="M13 3L4 14h7v7l9-11h-7V3z"/></svg></div>
        <h1 style="font-size:28px;font-weight:800">WattWatch</h1>
      </div>
      <p class="login-subtitle">IoT-Based Electricity Monitoring System</p>
      <div class="login-card">
        <div id="login-alert" class="alert hidden"></div>
        <div class="form-group"><label>Email Address</label>
          <input id="l-email" type="email" class="form-ctrl" placeholder="you@wattwatch.com" onkeydown="if(event.key==='Enter')doLogin()">
        </div>
        <div class="form-group"><label>Password</label>
          <div class="pw-wrap">
            <input id="l-pw" type="password" class="form-ctrl" placeholder="••••••••" onkeydown="if(event.key==='Enter')doLogin()">
            <button class="pw-toggle" type="button" id="pw-tog" onclick="togglePw()">${ICONS.eye}</button>
          </div>
        </div>
        <button id="login-btn" class="btn btn-primary" style="width:100%;justify-content:center;padding:11px 0;font-size:14px" onclick="doLogin()">Sign In</button>
        <div class="login-demo">
          <div class="login-demo-title">Demo Accounts</div>
          ${[['Administrator','admin@wattwatch.com','admin123'],
             ['Staff / Technician','staff@wattwatch.com','juan123'],
            ].map(([l,e,p])=>`<button class="demo-btn" onclick="fillDemo('${e}','${p}')"><strong>${l}</strong> — ${e}</button>`).join('')}
        </div>
      </div>
      <p style="text-align:center;font-size:11px;color:var(--slate-400);margin-top:16px">WattWatch v1.0 · Isabela State University</p>
    </div>
  </div>`;
  applyTheme();
}

let _pwVis=false;
function togglePw(){_pwVis=!_pwVis;const i=$('l-pw'),b=$('pw-tog');if(i)i.type=_pwVis?'text':'password';if(b)b.innerHTML=_pwVis?ICONS.eyeoff:ICONS.eye;}
function fillDemo(e,p){$('l-email').value=e;$('l-pw').value=p;}

async function doLogin(){
  const email=$('l-email')?.value.trim(),pw=$('l-pw')?.value;
  if(!email||!pw)return showAlert('login-alert','Enter your email and password.','error');
  const btn=$('login-btn');
  if(btn){btn.disabled=true;btn.textContent='Signing in…';}
  try{const user=await api('login',{email,password:pw},'POST');state.user=user;buildShell(user);}
  catch(e){showAlert('login-alert',e.message,'error');if(btn){btn.disabled=false;btn.textContent='Sign In';}}
}

async function logout(){
  closeLogoutModal();
  try{await api('logout',null,'POST');}catch{}
  state.user=null;buildLogin();
}

(async function boot(){
  applyTheme();
  if(window.__WW_USER__){state.user=window.__WW_USER__;buildShell(window.__WW_USER__);return;}
  try{const me=await api('me');if(me?.user_id){state.user=me;buildShell(me);return;}}catch{}
  buildLogin();
})();
