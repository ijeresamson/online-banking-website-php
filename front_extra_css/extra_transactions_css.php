<?php   
$extra_css = '
.stats-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}.ss-card{background:var(--white);
border-radius:12px;padding:16px;box-shadow:var(--shadow);
border:1px solid var(--border)}.ss-val{font-size:21px;
font-weight:700;margin-bottom:4px}.ss-lbl{font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;
letter-spacing:.4px}.filter-bar{background:var(--white);border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);
border:1px solid var(--border);margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}.fg{display:flex;
flex-direction:column;gap:4px}.fg label{font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;
letter-spacing:.4px}.fg input,.fg select{height:38px;padding:0 10px;border:1.5px solid var(--border);border-radius:8px;
font-size:13px;font-family:"Inter",sans-serif;color:var(--text);
background:#fafafa;outline:none}.fg input:focus,.fg select:focus{border-color:var(--accent)}.txn-table-row{display:flex;
align-items:center;gap:14px;padding:13px 20px;border-bottom:1px solid var(--border);
transition:background .15s}.txn-table-row:last-child{border-bottom:none}.txn-table-row:hover{background:#f9fafb}.txn-ico{width:40px;
height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;
font-size:17px;flex-shrink:0}.txn-desc{font-size:13px;font-weight:600;margin-bottom:2px;white-space:nowrap;overflow:hidden;
text-overflow:ellipsis}.txn-meta{font-size:11px;color:var(--text-muted)}.status-pill{padding:2px 9px;border-radius:5px;font-size:10px;
font-weight:700}.s-completed{background:#d1fae5;color:#059669}.s-pending{background:#fef3c7;color:#d97706}.s-failed{background:#fee2e2;
color:#dc2626}.s-cancelled{background:#f3f4f6;color:#6b7280}.s-reversed{background:#ede9fe;color:#7c3aed}.s-processing{background:#dbeafe;
color:#2563eb}.recv-badge{background:#d1fae5;color:#059669;padding:2px 7px;
border-radius:4px;font-size:10px;font-weight:700;margin-left:4px}.page-btn{width:34px;height:34px;border-radius:8px;
border:1.5px solid var(--border);background:#fff;font-size:13px;font-weight:600;color:var(--text);cursor:pointer;
display:flex;align-items:center;justify-content:center;text-decoration:none}.page-btn:hover,.page-btn.active{background:var(--accent);
color:#fff;border-color:var(--accent)}.txn-error{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:10px;
padding:14px 16px;margin-bottom:20px;font-size:13px}@media(max-width:800px){.stats-strip{grid-template-columns:1fr 1fr}.filter-bar{flex-direction:column;
align-items:stretch}.filter-bar .fg{width:100%!important;min-width:0!important}.filter-bar .btn{width:100%}}@media(max-width:600px){.txn-table-row{padding:12px 14px;
gap:10px}.txn-ico{width:36px;
height:36px;font-size:15px}.txn-desc{font-size:12px}.txn-meta{font-size:10px}.txn-table-row>div:last-child{min-width:82px!important}.txn-table-row>div:last-child div:first-child{font-size:14px!important}}
';
