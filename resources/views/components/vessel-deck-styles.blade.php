<style>
.ny-stage{overflow-x:auto;padding:8px 4px 16px}
.ny-hull{width:max(100%,calc(46px + var(--ny-cols) * 52px));margin:8px auto;padding:18px 16px 14px;border:2px solid #0f766e;border-radius:46% 46% 16px 16px / 10% 10% 16px 16px;background:linear-gradient(90deg,#d8ebe7 0,#fff 14%,#fff 86%,#d8ebe7 100%)}
.ny-bow,.ny-stern{margin:0;text-align:center;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#0f766e}
.ny-cabin{margin-top:12px}
.ny-row{display:grid;grid-template-columns:28px repeat(var(--ny-cols),minmax(44px,1fr));gap:8px;align-items:center;margin:8px 0}
.ny-rowno{font-size:12px;font-weight:700;color:#64748b;text-align:center}
.ny-seat-form{display:grid;margin:0}
.ny-seat{display:grid;place-items:center;width:100%;min-width:44px;height:44px;border:2px solid #059669;border-radius:12px 12px 8px 8px;background:#fff;color:#065f46;font-size:12px;font-weight:800;cursor:pointer}
.ny-seat.selected{border-color:#f59e0b;background:#062c21;color:#fbbf24}
.ny-seat.blocked,.ny-seat:disabled{border-color:#cbd5e1;background:#e2e8f0;color:#64748b;cursor:not-allowed;text-decoration:none}
.ny-aisle{min-height:44px}
.ny-seat-sample{width:18px;min-width:18px;height:18px;pointer-events:none}
</style>
