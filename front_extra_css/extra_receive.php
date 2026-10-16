<?php       
$extra_css = '
.receive-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.bank-slip{background:linear-gradient(160deg,#0f0c29,#1a1a2e 45%,#16213e);border-radius:18px;overflow:hidden;}
.slip-header{padding:22px 28px 18px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:14px;}
.slip-logo{width:46px;height:46px;background:rgba(255,255,255,.1);border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:21px;color:#93c5fd;flex-shrink:0;}
.slip-bank{font-size:16px;font-weight:700;color:#fff;}
.slip-sub{font-size:11px;color:rgba(255,255,255,.5);margin-top:2px;}
.slip-body{padding:18px 28px 20px;}
.slip-row{display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.07);gap:16px;}
.slip-row:last-child{border-bottom:none;}
.slip-lbl{font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.7px;flex-shrink:0;padding-right:14px;min-width:130px;margin-top:2px;}
.slip-val{font-size:13px;font-weight:600;color:#fff;text-align:right;word-break:break-word;}
.slip-val.hi{font-size:20px;font-weight:700;color:#fbbf24;letter-spacing:3px;font-family:"Courier New",monospace;}
.slip-val.mono{font-family:"Courier New",monospace;color:#93c5fd;letter-spacing:1.5px;}
.slip-val.swift{font-family:monospace;color:#86efac;letter-spacing:2px;}
.slip-footer{padding:14px 28px;border-top:1px solid rgba(255,255,255,.08);display:flex;gap:10px;}
.slip-btn{flex:1;padding:10px;border-radius:9px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);color:#fff;font-family:"Inter",sans-serif;display:flex;align-items:center;justify-content:center;gap:6px;transition:background .2s,transform .2s;}
.slip-btn:hover{background:rgba(255,255,255,.22);transform:translateY(-1px);}
.slip-btn:disabled{opacity:.55;cursor:not-allowed;transform:none;}
.qc-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:16px;}
.qc-tile{background:var(--bg);border-radius:10px;padding:12px;border:1.5px solid var(--border);cursor:pointer;transition:all .2s;}
.qc-tile:hover{border-color:var(--accent);background:var(--accent-light);}
.qc-lbl{font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px;}
.qc-val{font-size:12px;font-weight:700;font-family:monospace;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.qc-copy{font-size:10px;color:var(--accent);font-weight:600;margin-top:4px;}
.share-item{display:flex;align-items:center;gap:12px;padding:13px;background:var(--bg);border:1.5px solid var(--border);border-radius:11px;cursor:pointer;transition:all .2s;margin-bottom:10px;}
.share-item:hover{border-color:var(--accent);background:var(--accent-light);transform:translateX(3px);}
.share-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.recv-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
.recv-item:last-child{border-bottom:none;}
.copy-ok{color:#059669!important;}
@media(max-width:900px){.receive-grid{grid-template-columns:1fr;}.qc-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:560px){.qc-grid{grid-template-columns:1fr;}.slip-header,.slip-body,.slip-footer{padding-left:18px;padding-right:18px;}.slip-footer{flex-wrap:wrap;}.slip-row{display:block;}.slip-lbl{display:block;margin-bottom:5px;}.slip-val{text-align:left;}.slip-val.hi{font-size:17px;}}
';
