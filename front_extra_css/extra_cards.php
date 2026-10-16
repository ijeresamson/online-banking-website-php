<?php 
$extra_css = '
.cards-layout{display:grid;grid-template-columns:1fr 1.2fr;gap:20px;}

/* Card Visual */
.card-vis{border-radius:20px;padding:28px;color:#fff;min-height:200px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s;cursor:pointer;}
.card-vis:hover{transform:translateY(-2px);box-shadow:0 16px 40px rgba(0,0,0,.3);}
.card-vis::before{content:"";position:absolute;width:260px;height:260px;background:rgba(255,255,255,.05);border-radius:50%;top:-80px;right:-80px;}
.card-vis::after{content:"";position:absolute;width:160px;height:160px;background:rgba(255,255,255,.03);border-radius:50%;bottom:-40px;left:-40px;}
.cv-active {background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%);}
.cv-gold   {background:linear-gradient(135deg,#78350f 0%,#92400e 50%,#78350f 100%);}
.cv-frozen {background:linear-gradient(135deg,#0f172a,#1e3a5f);filter:saturate(.5);}
.cv-blocked{background:linear-gradient(135deg,#1f2937,#111827);filter:grayscale(.8);}
.cv-expired{background:linear-gradient(135deg,#374151,#1f2937);filter:grayscale(.6);}
.cv-chip{width:40px;height:30px;background:linear-gradient(135deg,#d4af37,#f5d269);border-radius:5px;position:relative;z-index:1;margin-bottom:12px;}
.cv-chip::before{content:"";position:absolute;left:13px;top:0;bottom:0;width:2px;background:rgba(0,0,0,.15);}
.cv-chip::after{content:"";position:absolute;top:10px;left:0;right:0;height:2px;background:rgba(0,0,0,.15);}
.cv-num{font-size:17px;font-weight:600;letter-spacing:3px;font-family:"Courier New",monospace;position:relative;z-index:1;margin-bottom:16px;word-spacing:8px;}
.cv-bot{display:flex;justify-content:space-between;z-index:1;position:relative;}
.cv-lbl{font-size:9px;opacity:.55;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:3px;}
.cv-val{font-size:13px;font-weight:700;font-family:"Courier New",monospace;letter-spacing:1px;}
.cv-network{position:absolute;top:24px;right:24px;font-size:22px;font-weight:800;font-style:italic;opacity:.8;z-index:1;}

/* Status Pill */
.status-pip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
.sp-active {background:#d1fae5;color:#059669;}
.sp-frozen {background:#dbeafe;color:#2563eb;}
.sp-blocked{background:#fee2e2;color:#dc2626;}
.sp-expired{background:#f3f4f6;color:#6b7280;}

/* Card Details Panel */
.card-details-panel{background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:14px;padding:20px;color:#fff;margin-bottom:16px;position:relative;overflow:hidden;}
.cdp-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.5);margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;}
.cdp-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.08);}
.cdp-row:last-child{border-bottom:none;}
.cdp-lbl{font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.6px;}
.cdp-val{font-size:15px;font-weight:700;font-family:"Courier New",monospace;letter-spacing:2px;color:#fff;}
.cdp-val.masked{letter-spacing:4px;font-size:18px;}
.cdp-actions{display:flex;gap:6px;align-items:center;}
.icon-btn{width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;transition:all .2s;font-family:"Inter",sans-serif;}
.icon-btn:hover{background:rgba(255,255,255,.2);color:#fff;}
.icon-btn.copied{background:#d1fae5;color:#059669;border-color:#059669;}
.security-bar{background:rgba(255,255,255,.06);border-radius:8px;padding:10px 14px;margin-top:14px;display:flex;align-items:center;gap:8px;font-size:11px;color:rgba(255,255,255,.5);}

/* Card Actions Grid */
.ca-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;}
.ca-btn{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;border-radius:12px;background:var(--bg);border:1.5px solid var(--border);cursor:pointer;font-size:11px;font-weight:600;color:var(--text);transition:all .2s;text-align:center;font-family:"Inter",sans-serif;text-decoration:none;}
.ca-btn:hover{background:var(--accent-light);border-color:var(--accent);color:var(--accent);}
.ca-btn i{font-size:22px;}

/* Card List */
.card-list-item{display:flex;align-items:center;gap:12px;padding:12px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:all .2s;margin-bottom:8px;text-decoration:none;color:inherit;}
.card-list-item:hover,.card-list-item.sel{border-color:var(--accent);background:var(--accent-light);}
.card-mini{width:52px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0;}

/* Freeze Notice */
.freeze-notice{background:#dbeafe;border:1px solid #bfdbfe;border-radius:10px;padding:14px;margin-bottom:14px;display:flex;gap:12px;align-items:flex-start;}

/* Modal */
.modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center;}
.modal-ov.active{display:flex;}
.modal-bx{background:#fff;border-radius:16px;padding:28px;width:100%;max-width:440px;margin:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);}

/* Info Rows */
.info-det{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px;}
.info-det:last-child{border-bottom:none;}

@media(max-width:1000px){.cards-layout{grid-template-columns:1fr;}}
@media(max-width:600px){.ca-grid{grid-template-columns:repeat(3,1fr);}}
';

