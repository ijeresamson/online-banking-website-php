<?php  
$extra_css = '
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px;}
.stat-card{background:var(--white);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border:1px solid var(--border);position:relative;overflow:hidden;}
.stat-card::before{content:"";position:absolute;top:0;left:0;width:4px;height:100%;}
.sc-green::before{background:var(--green);}
.sc-blue::before{background:var(--accent);}
.sc-red::before{background:var(--red);}
.sc-purple::before{background:var(--purple);}
.sc-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:19px;margin-bottom:13px;}
.sc-green .sc-icon{background:#d1fae5;color:var(--green);}
.sc-blue  .sc-icon{background:var(--accent-light);color:var(--accent);}
.sc-red   .sc-icon{background:#fee2e2;color:var(--red);}
.sc-purple .sc-icon{background:#ede9fe;color:var(--purple);}
.sc-lbl{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:5px;}
.sc-val{font-size:22px;font-weight:700;color:var(--text);margin-bottom:4px;line-height:1;}
.sc-sub{font-size:12px;color:var(--text-muted);}
.dash-grid{display:grid;grid-template-columns:320px 1fr;gap:20px;margin-bottom:24px;}
.left-col{display:flex;flex-direction:column;gap:16px;}
.right-col{display:flex;flex-direction:column;gap:16px;}
.bank-card-vis{background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);border-radius:18px;padding:26px;color:#fff;min-height:185px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;cursor:pointer;transition:transform .2s;}
.bank-card-vis:hover{transform:scale(1.01);}
.bank-card-vis::before{content:"";position:absolute;width:220px;height:220px;background:rgba(255,255,255,.05);border-radius:50%;top:-60px;right:-60px;}
.bcv-num{font-size:16px;font-weight:600;letter-spacing:3px;font-family:"Courier New",monospace;margin:10px 0 0;position:relative;z-index:1;}
.bcv-bot{display:flex;justify-content:space-between;z-index:1;position:relative;}
.bcv-lbl{font-size:9px;opacity:.55;text-transform:uppercase;letter-spacing:1px;}
.bcv-val{font-size:13px;font-weight:700;margin-top:2px;}
.acct-box{background:linear-gradient(135deg,#2b6cb0,#1a365d);border-radius:var(--radius);padding:20px;color:#fff;}
.ai-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.1);}
.ai-row:last-child{border-bottom:none;}
.ai-lbl{font-size:11px;opacity:.65;}
.ai-val{font-size:12px;font-weight:700;font-family:monospace;text-align:right;max-width:180px;word-break:break-all;}
.qa-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:0;}
.qa-btn{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 6px;border-radius:12px;background:var(--bg);border:1.5px solid var(--border);cursor:pointer;font-size:11px;font-weight:600;color:var(--text);text-decoration:none;transition:all .2s;text-align:center;}
.qa-btn:hover{background:var(--accent-light);border-color:var(--accent);color:var(--accent);transform:translateY(-2px);}
.qa-btn i{font-size:21px;}
.chart-wrap{height:130px;display:flex;align-items:flex-end;gap:10px;padding-bottom:24px;position:relative;}
.chart-wrap::after{content:"";position:absolute;bottom:24px;left:0;right:0;height:1px;background:var(--border);}
.bar-grp{flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;}
.bar-pair{display:flex;gap:3px;align-items:flex-end;width:100%;justify-content:center;flex:1;}
.bar-in {width:13px;background:var(--green);border-radius:3px 3px 0 0;min-height:4px;}
.bar-out{width:13px;background:var(--red);border-radius:3px 3px 0 0;min-height:4px;}
.bar-lbl{font-size:10px;color:var(--text-muted);margin-top:4px;}
.txn-row{display:flex;align-items:center;gap:12px;padding:13px 20px;border-bottom:1px solid var(--border);transition:background .15s;}
.txn-row:last-child{border-bottom:none;}
.txn-row:hover{background:#f9fafb;}
.txn-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.txn-desc{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;}
.txn-date{font-size:11px;color:var(--text-muted);}
.txn-amt{font-size:15px;font-weight:700;white-space:nowrap;}
.badge-recv{font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px;background:#d1fae5;color:#059669;}
@media(max-width:1100px){.stat-grid{grid-template-columns:repeat(2,1fr);}.dash-grid{grid-template-columns:1fr;}}
@media(max-width:600px){.stat-grid{grid-template-columns:1fr 1fr;}.qa-grid{grid-template-columns:repeat(4,1fr);}.sc-val{font-size:18px;}}
';  