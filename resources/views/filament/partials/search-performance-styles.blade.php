{{-- Eigen stijlblok: klantprojecten scannen vendor/dashed niet in hun Tailwind-build. --}}
<style>
    .dsp-card { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: .75rem; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
    .dark .dsp-card { background: rgb(24 24 27); border-color: rgba(255,255,255,.1); }
    .dsp-card h2 { font-size: 1rem; font-weight: 600; margin: 0 0 .25rem; }
    .dsp-muted { color: rgb(113 113 122); font-size: .85rem; }
    .dsp-table-wrap { overflow-x: auto; }
    .dsp-table { width: 100%; border-collapse: collapse; font-size: .875rem; margin-top: .75rem; }
    .dsp-table th { text-align: left; font-weight: 600; padding: .4rem .5rem; border-bottom: 1px solid rgba(0,0,0,.1); white-space: nowrap; }
    .dsp-table td { padding: .4rem .5rem; border-bottom: 1px solid rgba(0,0,0,.05); vertical-align: top; }
    .dsp-table td.dsp-num, .dsp-table th.dsp-num { text-align: right; white-space: nowrap; }
    .dsp-worse { color: rgb(220 38 38); font-weight: 600; }
    .dsp-better { color: rgb(22 163 74); font-weight: 600; }
    .dsp-break { word-break: break-all; }
    .dsp-select { border: 1px solid rgba(0,0,0,.15); border-radius: .5rem; padding: .35rem .6rem; background: transparent; margin-bottom: 1rem; }
    .dsp-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); margin-top: .75rem; }
    .dsp-score { font-size: 1.75rem; font-weight: 700; line-height: 1; }
    .dsp-good { color: rgb(22 163 74); } .dsp-avg { color: rgb(217 119 6); } .dsp-poor { color: rgb(220 38 38); }
    .dsp-chart { width: 100%; height: 120px; margin-top: .75rem; }
    .dsp-legend { display: flex; gap: 1rem; font-size: .8rem; margin-top: .25rem; }
    .dsp-dot { display: inline-block; width: .6rem; height: .6rem; border-radius: 999px; margin-right: .3rem; }
</style>
