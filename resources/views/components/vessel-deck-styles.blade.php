<style>
.ny-stage{overflow-x:auto;padding:4px 2px 12px}
.ny-hull{width:max(100%,calc(36px + var(--ny-cols) * 46px));margin:4px auto;padding:26px 14px 12px;border:2px solid #0f766e;border-radius:140px 140px 18px 18px / 78px 78px 18px 18px;background:radial-gradient(120px 36px at 50% 0,#cfe8e3 0,transparent 72%),linear-gradient(90deg,#d8ebe7 0,#fff 12%,#fff 88%,#d8ebe7 100%)}
.ny-bow,.ny-stern{margin:0;text-align:center;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#0f766e}
.ny-cabin{margin-top:8px}
.ny-row{display:grid;grid-template-columns:22px repeat(var(--ny-cols),minmax(36px,1fr));gap:6px;align-items:center;margin:6px 0}
.ny-rowno{font-size:12px;font-weight:700;color:#64748b;text-align:center}
.ny-seat-form{display:grid;margin:0}
.ny-seat{display:grid;place-items:center;width:100%;min-width:36px;height:36px;border:2px solid #059669;border-radius:10px 10px 6px 6px;background:#fff;color:#065f46;font-size:12px;font-weight:800;cursor:pointer}
.ny-seat.selected{border-color:#f59e0b;background:#062c21;color:#fbbf24}
.ny-seat.blocked,.ny-seat:disabled{border-color:#cbd5e1;background:#e2e8f0;color:#64748b;cursor:not-allowed}
.ny-aisle{min-height:36px;display:grid;place-items:center;color:#0f766e;font-size:12px;font-weight:800;letter-spacing:.04em}
#liveHull .ny-seat{pointer-events:none;cursor:default}
.ny-seat-sample{width:36px;min-width:36px;height:28px;border-radius:10px 10px 6px 6px;pointer-events:none}
#seatPreview{background:#ecfdf5;color:#334155}
#seatPreview.is-over{background:#fff1f2;color:#be123c}
.ny-deco{display:grid;min-height:36px;place-items:center;border-radius:8px;background:color-mix(in srgb,var(--ny-tone-river) 14%,white);color:var(--ny-tone-river-deep)}
.ny-deco svg{width:18px;height:18px}
.ny-deco-legend{display:flex;flex-wrap:wrap;gap:8px 14px;margin-top:10px;color:#475569;font-size:12px;font-weight:700}
.ny-deco-legend span{display:inline-flex;align-items:center;gap:6px}
.ny-deco-legend .ny-deco{width:24px;min-height:24px}
.ny-deco-legend .ny-deco svg{width:14px;height:14px}
.ny-designer{display:grid;gap:12px}
.ny-designer-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:flex-start}
.ny-designer-head h3{margin:0;color:#0f172a;font-size:18px;font-weight:900}
.ny-designer-head p{margin:4px 0 0;color:#64748b;font-size:14px}
.ny-designer-head p.is-warn{color:#92400e}
.ny-designer-count{padding:8px 12px;border-radius:12px;background:#ecfdf5;color:#065f46;font-size:14px;text-align:right}
.ny-designer-count small{display:block;font-size:12px;color:#047857}
.ny-designer-count.is-over{background:#fff1f2;color:#be123c}
.ny-designer-count.is-over small{color:#be123c}
.ny-designer-tools{display:flex;flex-wrap:wrap;gap:6px}
.ny-tool{display:inline-flex;min-height:44px;align-items:center;gap:8px;border:1px solid #d8e5e0;border-radius:12px;background:#fff;padding:6px 10px 6px 6px;color:#153b32;font-size:13px;font-weight:800;white-space:nowrap;transition:transform .15s ease,border-color .15s ease}
.ny-tool:hover{transform:translateY(-1px);border-color:#176d60}
.ny-tool.is-on{border-color:#062c21;background:#062c21;color:#fff}
.ny-tool .ny-mark{width:30px;height:30px;border-radius:8px}
.ny-tool .ny-mark svg{width:16px;height:16px}
.ny-designer-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.ny-stepper{display:inline-flex;align-items:center;gap:6px;border:1px solid #d8e5e0;border-radius:12px;padding:4px 6px 4px 10px;font-size:13px;font-weight:700;color:#334155}
.ny-stepper button{display:grid;width:34px;height:34px;place-items:center;border-radius:9px;background:#f1f7f4;color:#062c21;font-size:18px;font-weight:900}
.ny-stepper b{min-width:22px;text-align:center;color:#0f172a}
.ny-designer-menu{position:relative}
.ny-designer-menu summary{display:inline-flex;min-height:44px;align-items:center;border:1px solid #d8e5e0;border-radius:12px;padding:0 14px;font-size:13px;font-weight:800;color:#153b32;cursor:pointer;list-style:none}
.ny-designer-menu div{position:absolute;z-index:20;top:calc(100% + 6px);left:0;display:grid;min-width:260px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:6px;box-shadow:0 14px 32px rgba(10,48,38,.14)}
.ny-designer-menu div button{border-radius:8px;padding:10px 12px;text-align:left;font-size:14px;font-weight:700;color:#153b32}
.ny-designer-menu div button:hover{background:#ecfdf5}
.ny-designer-preview{min-height:44px;border-radius:12px;background:#f2b624;padding:0 14px;color:#062c21;font-size:13px;font-weight:900}
.ny-designer-msg{margin:0;border-radius:10px;background:#fff7df;padding:10px 12px;color:#8a6000;font-size:14px;font-weight:700}
.ny-designer-msg.is-error{background:#fff1f2;color:#be123c}
.ny-designer-grid{touch-action:none;user-select:none}
.ny-cell{display:grid;width:100%;min-width:36px;height:36px;place-items:center;border:1px dashed #cbd5e1;border-radius:8px;background:transparent;color:#065f46;font-size:12px;font-weight:800;cursor:crosshair}
.ny-cell.is-seat{border:2px solid #059669;border-radius:10px 10px 6px 6px;background:#fff}
.ny-cell.is-blocked{border-color:#cbd5e1;background:#e2e8f0;color:#64748b;text-decoration:line-through}
.ny-cell.is-sold{box-shadow:inset 0 -4px 0 #f2b624}
.ny-cell.is-element{border:0;background:color-mix(in srgb,var(--ny-tone-river) 14%,white);color:var(--ny-tone-river-deep)}
.ny-cell-element svg{width:18px;height:18px}
.ny-designer-grid.is-preview .ny-cell{cursor:default}
.ny-designer-grid.is-preview .ny-cell.is-aisle{border-color:transparent}
.ny-designer-legend{display:flex;flex-wrap:wrap;gap:8px 16px;color:#475569;font-size:12px;font-weight:700}
.ny-designer-legend span{display:inline-flex;align-items:center;gap:6px}
.ny-designer-legend .ny-cell{width:24px;min-width:24px;height:22px}
.ny-designer-save button{width:100%;min-height:48px;border-radius:12px;background:#062c21;color:#fff;font-size:15px;font-weight:900}
.ny-designer-save button:disabled{background:#e2e8f0;color:#64748b;cursor:not-allowed}
</style>
