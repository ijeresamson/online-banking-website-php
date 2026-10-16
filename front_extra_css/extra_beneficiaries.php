<?php 

$extra_css = '
.benef-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px,1fr)); gap:16px; }
.benef-card {
    background:#fff; border-radius:14px; padding:20px;
    border:1.5px solid var(--border);
    box-shadow:var(--shadow);
    transition:all .2s;
    position:relative; overflow:hidden;
}
.benef-card::before {
    content:"";
    position:absolute; top:0; left:0;
    width:4px; height:100%;
}
.benef-card.internal::before { background:var(--green); }
.benef-card.external::before { background:var(--accent); }
.benef-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.1); }
.benef-av { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:#fff; flex-shrink:0; }
.benef-av.internal { background:linear-gradient(135deg,var(--green),#059669); }
.benef-av.external { background:linear-gradient(135deg,var(--accent),#1a365d); }
.benef-name { font-size:16px; font-weight:700; color:var(--text); margin-bottom:3px; }
.benef-detail { font-size:12px; color:var(--text-muted); margin-bottom:2px; }
.benef-detail span { font-family:monospace; color:var(--text); font-weight:600; }
.benef-type-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; }
.bt-internal { background:#d1fae5; color:#059669; }
.bt-external { background:#dbeafe; color:#2563eb; }
.benef-actions { display:flex; gap:8px; margin-top:14px; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:500; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal-box { background:#fff; border-radius:16px; padding:28px; width:100%; max-width:520px; margin:16px; box-shadow:0 20px 60px rgba(0,0,0,.2); max-height:90vh; overflow-y:auto; }
.modal-title { font-size:18px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
.tab-strip { display:flex; gap:4px; background:var(--bg); border-radius:10px; padding:4px; margin-bottom:20px; }
.tab-strip button { flex:1; padding:9px; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; color:var(--text-muted); border:none; background:transparent; font-family:"Inter",sans-serif; transition:all .2s; }
.tab-strip button.active { background:#fff; color:var(--text); box-shadow:0 1px 4px rgba(0,0,0,.1); }
.empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
.empty-state i { font-size:60px; color:var(--border); display:block; margin-bottom:16px; }
.filter-pills { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.filter-pill { padding:6px 16px; border-radius:20px; font-size:13px; font-weight:600; cursor:pointer; border:1.5px solid var(--border); background:#fff; color:var(--text-muted); transition:all .2s; text-decoration:none; }
.filter-pill.active { background:var(--accent); color:#fff; border-color:var(--accent); }
';
