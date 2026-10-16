<?php  

$extra_css = '
.security-layout { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.toggle-row { display:flex; align-items:center; justify-content:space-between; padding:14px 0; border-bottom:1px solid var(--border); }
.toggle-row:last-child { border-bottom:none; }
.toggle-info h4 { font-size:14px; font-weight:600; margin-bottom:3px; }
.toggle-info p  { font-size:12px; color:var(--text-muted); }
.switch { position:relative; display:inline-block; width:46px; height:26px; flex-shrink:0; }
.switch input { opacity:0; width:0; height:0; }
.slider { position:absolute; cursor:pointer; inset:0; background:#cbd5e0; border-radius:26px; transition:.3s; }
.slider::before { position:absolute; content:""; height:20px; width:20px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s; }
input:checked + .slider { background:var(--accent); }
input:checked + .slider::before { transform:translateX(20px); }
.log-row { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
.log-row:last-child { border-bottom:none; }
.log-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.log-icon.ok  { background:#d1fae5; color:var(--green); }
.log-icon.bad { background:#fee2e2; color:var(--red); }
.log-badge { padding:3px 10px; border-radius:6px; font-size:11px; font-weight:600; }
.log-badge.ok  { background:#d1fae5; color:var(--green); }
.log-badge.bad { background:#fee2e2; color:var(--red); }
.strength-bar { height:6px; background:var(--border); border-radius:3px; overflow:hidden; margin:8px 0 4px; }
.strength-fill { height:100%; border-radius:3px; transition:all .3s; width:0; }
.danger-zone { border:1px solid #fecaca; border-radius:var(--radius); padding:22px; background:#fff5f5; }
.danger-action { display:flex; align-items:center; justify-content:space-between; padding:14px 0; border-bottom:1px solid #fecaca; }
.danger-action:last-child { border-bottom:none; }
.danger-action h4 { font-size:14px; font-weight:600; margin-bottom:3px; }
.danger-action p  { font-size:12px; color:var(--text-muted); }
@media(max-width:900px){ .security-layout{grid-template-columns:1fr;} }
';
