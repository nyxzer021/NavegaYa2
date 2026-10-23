<style>
/* Compatibility layer for legacy ny-* admin screens. Kept here so every admin route receives the same visual system. */
.ny-main{--ny-control-height:40px;--ny-control-radius:10px;--ny-panel-radius:16px;--ny-panel-padding:18px}
.ny-main>main{font-size:13px;line-height:1.45}
.ny-main>main h1,.ny-main>main .text-3xl{font-size:24px!important;line-height:1.18!important;letter-spacing:-.025em}
.ny-main>main h2,.ny-main>main .text-2xl{font-size:19px!important;line-height:1.25!important;letter-spacing:-.015em}
.ny-main>main h3,.ny-main>main .text-xl{font-size:17px!important;line-height:1.3!important}
.ny-main>main h4,.ny-main>main .text-lg{font-size:15px!important;line-height:1.35!important}
.ny-main>main p{line-height:1.5}
.ny-main>main .rounded-3xl{border-radius:var(--ny-panel-radius)!important}
.ny-main>main .rounded-2xl{border-radius:14px!important}
.ny-main>main .rounded-xl{border-radius:var(--ny-control-radius)!important}
.ny-main>main .p-7{padding:20px!important}.ny-main>main .p-6{padding:18px!important}.ny-main>main .p-5{padding:16px!important}
.ny-main>main input:not([type=checkbox]):not([type=radio]):not([type=color]),.ny-main>main select{min-height:var(--ny-control-height)!important;padding:.5rem .75rem!important;border-color:#cbd5e1!important;border-radius:var(--ny-control-radius)!important;background-color:#fff;font-size:12.5px!important;line-height:1.25!important}
.ny-main>main textarea{min-height:82px;padding:.65rem .75rem!important;border-color:#cbd5e1!important;border-radius:var(--ny-control-radius)!important;background-color:#fff;font-size:12.5px!important;line-height:1.45!important;resize:vertical}
.ny-main>main input:focus,.ny-main>main select:focus,.ny-main>main textarea:focus{border-color:#0f766e!important;box-shadow:0 0 0 3px rgba(15,118,110,.12)!important;outline:0!important}
.ny-main>main input[type=checkbox],.ny-main>main input[type=radio]{box-sizing:border-box!important;width:17px!important;min-width:17px!important;max-width:17px!important;height:17px!important;min-height:17px!important;max-height:17px!important;padding:0!important;flex:0 0 17px!important;border:1.5px solid #94a3b8!important;border-radius:4px!important;accent-color:#047857}.ny-main>main input[type=radio]{border-radius:50%!important}.ny-main>main .permission-check:checked{border-color:#047857!important;background-color:#047857!important}
.ny-main>main button,.ny-main>main a[class*="px-"]{min-height:36px;line-height:1.2}
.ny-main>main label{line-height:1.35}
.ny-main>main table{font-size:11.5px}
.ny-main>main th{font-size:9.5px;letter-spacing:.045em}
.ny-main>main th,.ny-main>main td{padding-top:.65rem!important;padding-bottom:.65rem!important}
.ny-main>main dialog{max-height:calc(100vh - 40px);overflow:auto}
.ny-main>main .max-h-\[520px\],.ny-main>main .max-h-\[540px\]{max-height:430px!important;scrollbar-width:thin}
.ny-admin-header{min-height:56px!important;padding:8px 20px!important}
.ny-page-title{font-size:18px!important;line-height:1.25}
.ny-side{width:256px!important;flex-basis:256px!important}
.ny-side .ny-nav{padding-top:10px}
.ny-side .space-y-4>:not([hidden])~:not([hidden]){margin-top:.65rem}
.ny-side nav a{font-size:12px;line-height:1.25}
.ny-side nav section>p{font-size:9px}
.ny-main .ny-route-wrap,.ny-main .ny-vessel-wrap,.ny-main .ny-form-wrap,.ny-main .ny-departure-form,.ny-main .ny-seat-wrap,.ny-main .ny-aircraft-wrap,.ny-main .ny-air-route-wrap,.ny-main .ny-cargo-wrap{max-width:1120px!important;margin-inline:auto!important}
.ny-main .ny-route-hero,.ny-main .ny-vessel-hero,.ny-main .ny-seat-heading,.ny-main .ny-air-hero,.ny-main .ny-cargo-hero{border-radius:var(--ny-radius-lg)!important;background:linear-gradient(120deg,var(--ny-primary),var(--ny-primary-hover))!important;box-shadow:var(--ny-shadow-sm)!important}
.ny-main .ny-card,.ny-main .ny-form-card,.ny-main .ny-seat-panel,.ny-main .ny-seat-map,.ny-main .ny-table,.ny-main .ny-vessel-table,.ny-main .ny-route-table,.ny-main .ny-air-table{border-color:var(--ny-border)!important;border-radius:var(--ny-radius-lg)!important;box-shadow:var(--ny-shadow-sm)!important}
.ny-main .ny-card input,.ny-main .ny-card select,.ny-main .ny-card textarea,.ny-main .ny-form-card input,.ny-main .ny-form-card select,.ny-main .ny-form-card textarea,.ny-main .ny-grid input,.ny-main .ny-grid select,.ny-main .ny-grid textarea,.ny-main .ny-fields input,.ny-main .ny-fields select,.ny-main .ny-fields textarea{border-color:var(--ny-border)!important;border-radius:var(--ny-control-radius)!important;min-height:var(--ny-control-height)!important}
.ny-main .ny-save,.ny-main .ny-button,.ny-main .ny-vessel-button,.ny-main .ny-air-button,.ny-main .ny-route-actions .ny-btn{border-radius:var(--ny-radius-sm)!important}
.ny-main .ny-table table,.ny-main .ny-vessel-table table,.ny-main .ny-route-table table,.ny-main .ny-air-table table{width:100%!important;border-collapse:collapse!important}.ny-main .ny-table th,.ny-main .ny-vessel-table th,.ny-main .ny-route-table th,.ny-main .ny-air-table th{background:#f4f8f6!important;color:var(--ny-text-muted)!important}.ny-main .ny-table td,.ny-main .ny-vessel-table td,.ny-main .ny-route-table td,.ny-main .ny-air-table td{border-top:1px solid #eaf0ed!important}
.ny-main .ny-alert,.ny-main .ny-note,.ny-main .ny-warning{border-radius:var(--ny-radius)!important}.ny-main [style*="background:#e8fbf2"]{border-radius:var(--ny-radius)!important}.ny-main [style*="background:#fff4d5"],.ny-main [style*="background:#fff8df"]{border-radius:var(--ny-radius)!important}
@media(max-width:900px){.ny-main>main{padding:16px!important}.ny-main>main h1,.ny-main>main .text-3xl{font-size:21px!important}.ny-main>main h2,.ny-main>main .text-2xl{font-size:18px!important}}
@media(max-width:700px){.ny-main>header{padding:10px 14px!important}.ny-main>header .ny-account{font-size:0}.ny-main>header .ny-account span{font-size:11px}.ny-main>main{padding:14px!important}.ny-main>main .p-7,.ny-main>main .p-6,.ny-main>main .p-5{padding:15px!important}.ny-main .ny-table,.ny-main .ny-vessel-table,.ny-main .ny-route-table,.ny-main .ny-air-table{overflow-x:auto!important}.ny-main .ny-route-hero,.ny-main .ny-vessel-hero,.ny-main .ny-seat-heading,.ny-main .ny-air-hero,.ny-main .ny-cargo-hero{padding:18px!important}}
</style>
