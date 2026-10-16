<?php        

$extra_css = '
.profile-layout{display:grid;grid-template-columns:1fr 1.4fr;gap:20px;}
.profile-hero{background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:16px;padding:30px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:20px;position:relative;overflow:hidden;}
.profile-hero::before{content:"";position:absolute;width:300px;height:300px;background:rgba(255,255,255,.03);border-radius:50%;top:-80px;right:-80px;}
.hero-av{width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,#2b6cb0,#1a365d);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;color:#fff;flex-shrink:0;border:3px solid rgba(255,255,255,.2);}
.hero-name{font-size:22px;font-weight:700;margin-bottom:4px;}
.hero-email{font-size:13px;opacity:.65;margin-bottom:10px;}
.hero-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;margin-right:6px;}
.hb-active  {background:rgba(16,185,129,.2);color:#34d399;border:1px solid rgba(16,185,129,.3);}
.hb-pending {background:rgba(245,158,11,.2);color:#fbbf24;border:1px solid rgba(245,158,11,.3);}
.hb-acc{background:rgba(43,108,176,.3);color:#93c5fd;border:1px solid rgba(43,108,176,.4);}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);}
.info-row:last-child{border-bottom:none;}
.info-lbl{font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;}
.info-val{font-size:14px;font-weight:600;text-align:right;}
.kyc-doc{display:flex;align-items:center;gap:14px;padding:14px;background:var(--bg);border-radius:12px;margin-bottom:10px;}
.kyc-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
.acct-banner{background:linear-gradient(135deg,#2b6cb0,#1a365d);border-radius:14px;padding:18px;color:#fff;margin-bottom:16px;}
.ab-lbl{font-size:10px;opacity:.65;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;}
.ab-val{font-size:20px;font-weight:700;font-family:"Courier New",monospace;letter-spacing:2px;}
@media(max-width:900px){.profile-layout{grid-template-columns:1fr;}}
';