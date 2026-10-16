<?php 

$extra_css = '
.transfer-wrap { display:grid; grid-template-columns:1.5fr 1fr; gap:20px; }
.tab-switch { display:flex; background:var(--bg); border-radius:12px; padding:4px; margin-bottom:20px; }
.ts-btn { flex:1; padding:10px; text-align:center; border-radius:9px; font-size:13px; font-weight:600; cursor:pointer; color:var(--text-muted); border:none; background:transparent; font-family:"Inter",sans-serif; transition:all .2s; }
.ts-btn.active { background:#fff; color:var(--text); box-shadow:0 2px 8px rgba(0,0,0,.08); }
.step-bar { display:flex; align-items:center; margin-bottom:24px; }
.sn { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; }
.sn.done   { background:var(--green); color:#fff; }
.sn.active { background:var(--accent); color:#fff; }
.sn.wait   { background:var(--border); color:var(--text-muted); }
.sl { font-size:12px; font-weight:600; margin-left:6px; }
.sl.active { color:var(--accent); }
.sl.wait   { color:var(--text-muted); }
.sline { flex:1; height:2px; min-width:24px; }
.sline.done { background:var(--green); }
.sline.wait { background:var(--border); }
.section-box { background:var(--bg); border-radius:12px; padding:16px; margin-bottom:16px; }
.section-box-title { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.6px; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
.benef-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border:1.5px solid var(--border); border-radius:10px; cursor:pointer; margin-bottom:7px; transition:all .2s; }
.benef-item:hover,.benef-item.sel { border-color:var(--accent); background:var(--accent-light); }
.benef-av { width:34px; height:34px; border-radius:9px; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
.preview-box { background:var(--bg); border-radius:12px; padding:18px; margin-bottom:18px; }
.prev-row { display:flex; justify-content:space-between; align-items:flex-start; padding:9px 0; border-bottom:1px solid var(--border); font-size:14px; }
.prev-row:last-child { border-bottom:none; font-size:16px; font-weight:700; }
.bal-display { background:linear-gradient(135deg,#1e40af,#1a365d); border-radius:12px; padding:16px 18px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; color:#fff; }
.success-card { text-align:center; padding:48px 24px; max-width:560px; margin:0 auto; }
.success-icon { width:80px; height:80px; border-radius:50%; background:#fef3c7; color:#d97706; font-size:36px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
@media(max-width:900px){ .transfer-wrap{grid-template-columns:1fr;} }
';