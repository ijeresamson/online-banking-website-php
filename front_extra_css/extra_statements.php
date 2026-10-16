<?php 
$extra_css = '
.statement-wrap { max-width:820px; margin:0 auto; }
.stmt-card { background:#fff; border-radius:14px; box-shadow:var(--shadow); border:1px solid var(--border); overflow:hidden; }
.stmt-header { background:linear-gradient(135deg,#1a1a2e,#16213e); color:#fff; padding:28px 32px; }
.stmt-title { font-size:22px; font-weight:700; margin-bottom:4px; }
.stmt-subtitle { font-size:13px; opacity:.6; }
.stmt-bank-row { display:flex; justify-content:space-between; align-items:flex-start; margin-top:20px; }
.stmt-bank-name { font-size:16px; font-weight:700; }
.stmt-bank-addr { font-size:11px; opacity:.5; margin-top:3px; line-height:1.5; }
.stmt-logo { width:50px; height:50px; background:rgba(255,255,255,.12); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; }
.stmt-acct { display:grid; grid-template-columns:repeat(3,1fr); gap:0; background:#f8fafc; border-bottom:1px solid var(--border); }
.stmt-acct-item { padding:16px 20px; border-right:1px solid var(--border); }
.stmt-acct-item:last-child { border-right:none; }
.stmt-acct-lbl { font-size:10px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
.stmt-acct-val { font-size:14px; font-weight:700; color:var(--text); }
.stmt-summary { display:grid; grid-template-columns:repeat(4,1fr); background:#fff; border-bottom:1px solid var(--border); }
.stmt-sum-item { padding:16px 20px; border-right:1px solid var(--border); text-align:center; }
.stmt-sum-item:last-child { border-right:none; }
.stmt-sum-val { font-size:18px; font-weight:700; margin-bottom:4px; }
.stmt-sum-lbl { font-size:11px; color:var(--text-muted); font-weight:600; }
table.stmt-table { width:100%; border-collapse:collapse; }
table.stmt-table th { padding:10px 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); background:#f8fafc; border-bottom:2px solid var(--border); text-align:left; }
table.stmt-table td { padding:11px 16px; font-size:13px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
table.stmt-table tr:hover td { background:#f9fafb; }
.credit-amt { color:#059669; font-weight:700; }
.debit-amt  { color:#dc2626; font-weight:700; }
.stmt-footer-bar { padding:18px 24px; background:#f8fafc; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; font-size:12px; color:var(--text-muted); }
.filter-form { background:#fff; border-radius:12px; padding:20px; box-shadow:var(--shadow); border:1px solid var(--border); margin-bottom:20px; display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap; }
.ff { display:flex; flex-direction:column; gap:4px; }
.ff label { font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; }
.ff input { height:38px; padding:0 12px; border:1.5px solid var(--border); border-radius:8px; font-size:13px; font-family:"Inter",sans-serif; outline:none; }
.ff input:focus { border-color:var(--accent); }
@media print {
    .filter-form, .print-btn-row, nav, .sidebar, .top-header, .upgrade-card { display:none !important; }
    body, .main-wrapper { margin:0 !important; padding:0 !important; }
    .stmt-card { box-shadow:none !important; border:1px solid #ccc !important; }
    .page-container { padding:0 !important; }
    table.stmt-table tr:hover td { background:transparent !important; }
}
';