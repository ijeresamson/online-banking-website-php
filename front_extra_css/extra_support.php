<?php 
$extra_css = '
/* ============================================
   SUPPORT PAGE STYLES
============================================ */
.sp-layout { display:grid; grid-template-columns:1.4fr 1fr; gap:24px; }
.sp-new-ticket-card { background:#fff; border-radius:16px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,.07); border:1px solid var(--border); margin-bottom:20px; }
.sp-ticket-list { background:#fff; border-radius:16px; box-shadow:0 1px 3px rgba(0,0,0,.07); border:1px solid var(--border); overflow:hidden; }
.sp-ticket-list-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
/* Ticket items */
.sp-ticket { padding:16px 20px; border-bottom:1px solid var(--border); cursor:pointer; transition:all .18s; position:relative; }
.sp-ticket:last-child { border-bottom:none; }
.sp-ticket:hover { background:#f8faff; }
.sp-ticket.has-unread { background:linear-gradient(135deg,#eff6ff,#f0f9ff); border-left:3px solid #2563eb; }
.sp-ticket.awaiting-reply { border-left:3px solid #f59e0b; }
/* Status pills */
.pill { display:inline-flex; align-items:center; gap:3px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
.pill-open        { background:#dbeafe; color:#2563eb; }
.pill-in_progress { background:#fef3c7; color:#d97706; }
.pill-resolved    { background:#d1fae5; color:#059669; }
.pill-closed      { background:#f3f4f6; color:#6b7280; }
.pill-high   { background:#fee2e2; color:#dc2626; }
.pill-medium { background:#fef3c7; color:#d97706; }
.pill-low    { background:#d1fae5; color:#059669; }
/* Unread badge & animation */
.unread-badge { display:inline-flex; align-items:center; gap:5px; background:#ef4444; color:#fff; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; }
.pulse-dot { width:8px; height:8px; background:#fff; border-radius:50%; animation:pulse-anim 1.5s infinite; }
@keyframes pulse-anim { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.6;transform:scale(1.4);} }
/* Conversation thread */
.conv-thread { display:flex; flex-direction:column; gap:14px; }
.conv-msg { border-radius:12px; padding:16px 18px; }
.conv-msg.from-customer { background:#f1f5f9; border-left:3px solid #94a3b8; }
.conv-msg.from-admin    { background:#eff6ff; border-left:3px solid #2563eb; }
.conv-msg.from-customer-reply { background:#f0fdf4; border-left:3px solid #22c55e; }
.conv-avatar { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; }
/* Modal */
.modal-ov { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:600; align-items:center; justify-content:center; }
.modal-ov.active { display:flex; }
.modal-bx { background:#fff; border-radius:16px; width:100%; max-width:660px; margin:16px; display:flex; flex-direction:column; max-height:92vh; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,.18); }
.modal-head { padding:20px 24px; border-bottom:1px solid var(--border); flex-shrink:0; }
.modal-scroll { flex:1; overflow-y:auto; padding:22px 24px; }
.modal-foot { padding:16px 24px; border-top:1px solid var(--border); flex-shrink:0; background:#fafafa; }
/* Right column */
.contact-box { background:#fff; border-radius:16px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.07); border:1px solid var(--border); margin-bottom:20px; }
.contact-row { display:flex; align-items:center; gap:14px; padding:12px; background:var(--bg); border-radius:10px; margin-bottom:10px; border:1px solid var(--border); }
.contact-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:19px; flex-shrink:0; }
.faq-card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.07); border:1px solid var(--border); }
.faq-item { border:1px solid var(--border); border-radius:10px; margin-bottom:8px; overflow:hidden; }
.faq-q { display:flex; justify-content:space-between; align-items:center; padding:13px 16px; cursor:pointer; font-size:13px; font-weight:600; transition:background .15s; }
.faq-q:hover { background:var(--bg); }
.faq-a { display:none; padding:12px 16px; font-size:13px; color:var(--text-muted); line-height:1.7; border-top:1px solid var(--border); }
.faq-a.open { display:block; }
/* Form */
.sp-form-group { margin-bottom:14px; }
.sp-label { font-size:12px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; display:block; }
.awaiting-msg { background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:14px 16px; display:flex; gap:12px; align-items:flex-start; }
.reply-form-area { background:#f8faff; border-radius:10px; padding:16px; border:1.5px solid var(--accent); }
@media(max-width:900px){ .sp-layout{ grid-template-columns:1fr; } }
';

