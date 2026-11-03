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
.ny-aisle{min-height:36px;display:grid;place-items:center;color:#0f766e;font-size:11px;font-weight:800;letter-spacing:.04em}
#liveHull .ny-seat{pointer-events:none;cursor:default}
.ny-seat-sample{width:36px;min-width:36px;height:28px;border-radius:10px 10px 6px 6px;pointer-events:none}
#seatPreview{background:#ecfdf5;color:#334155}
#seatPreview.is-over{background:#fff1f2;color:#be123c}
</style>
