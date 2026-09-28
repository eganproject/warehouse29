@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<style>
    .dash-wrap {
        --dash-blue: #2563eb;
        --dash-green: #059669;
        --dash-amber: #d97706;
        --dash-red: #dc2626;
        --dash-slate: #475569;
    }

    /* ---------- Section heading ---------- */
    .dash-section-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }
    .dash-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 17px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.01em;
    }
    .dash-section-title i {
        color: var(--dash-blue);
        font-size: 16px;
    }
    .dash-section-sub {
        font-size: 12.5px;
        color: #64748b;
        margin-top: 2px;
    }
    .dash-legend {
        font-size: 11.5px;
        color: #94a3b8;
        margin-top: 6px;
        line-height: 1.6;
    }

    /* ---------- Date filter ---------- */
    .dash-filter {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 6px 8px 6px 12px;
    }
    .dash-filter-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }
    .dash-filter input {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        height: 34px;
        width: 140px;
        font-size: 13px;
    }

    /* ---------- Stat cards ---------- */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }
    .stat-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 16px;
        border: 1px solid #e9edf3;
        border-radius: 16px;
        padding: 18px;
        background: #fff;
        overflow: hidden;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .stat-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--accent, var(--dash-blue));
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.09);
    }
    .stat-icon {
        flex-shrink: 0;
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        color: var(--accent, var(--dash-blue));
        background: var(--accent-soft, rgba(37, 99, 235, 0.1));
    }
    .stat-body { min-width: 0; }
    .stat-label {
        display: flex;
        align-items: center;
        font-size: 11.5px;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .stat-value {
        font-size: 30px;
        font-weight: 800;
        line-height: 1.1;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .stat-meta {
        font-size: 11.5px;
        color: #94a3b8;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .stat-card--blue  { --accent: var(--dash-blue);  --accent-soft: rgba(37, 99, 235, 0.1); }
    .stat-card--green { --accent: var(--dash-green); --accent-soft: rgba(5, 150, 105, 0.1); }
    .stat-card--amber { --accent: var(--dash-amber); --accent-soft: rgba(217, 119, 6, 0.12); }
    .stat-card--red   { --accent: var(--dash-red);   --accent-soft: rgba(220, 38, 38, 0.1); }

    /* ---------- Dashboard tabs and lists ---------- */
    .dash-tabs {
        border-bottom: 1px solid #e2e8f0;
        gap: 8px;
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 1px;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }
    .dash-tabs .nav-link {
        border: 0;
        border-radius: 12px 12px 0 0;
        color: #64748b;
        font-weight: 800;
        padding: 12px 16px;
        white-space: nowrap;
    }
    .dash-tabs .nav-link.active {
        color: var(--dash-blue);
        background: #eff6ff;
    }
    .dash-table-card {
        border: 1px solid #e9edf3;
        border-radius: 16px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.035);
    }
    .dash-table-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #eef2f7;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
    }
    .dash-table-title {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
    }
    .dash-table-sub {
        font-size: 11.5px;
        color: #94a3b8;
        margin-top: 2px;
    }
    .dash-mini-table {
        margin: 0;
    }
    .dash-mini-table th {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #94a3b8;
        background: #f8fafc;
    }
    .dash-mini-table td {
        vertical-align: middle;
        font-size: 12.5px;
    }
    .dash-mini-table th,
    .dash-mini-table td {
        white-space: nowrap;
    }
    .dash-mini-table th:nth-child(2),
    .dash-mini-table td:nth-child(2) {
        white-space: normal;
        min-width: 180px;
    }
    .dash-item-name {
        font-weight: 800;
        color: #0f172a;
    }
    .dash-item-meta {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 2px;
    }
    .dash-empty {
        padding: 28px 18px;
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
    }
    .dash-panel {
        border: 1px solid #e9edf3;
        border-radius: 16px;
        background: #fff;
        padding: 18px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.035);
    }
    .dash-panel .dash-section-head {
        margin-bottom: 16px;
    }
    .dash-panel .stats-grid {
        gap: 14px;
    }
    .dash-panel .stat-card {
        min-height: 136px;
        box-shadow: none;
    }
    .dash-panel .stat-card:hover {
        transform: none;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.055);
    }
    .dash-kpi-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        padding: 16px;
    }
    .dash-kpi {
        border: 1px solid #eef2f7;
        border-radius: 14px;
        padding: 14px;
        background: #f8fafc;
    }
    .dash-kpi-label {
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .dash-kpi-value {
        margin-top: 6px;
        font-size: 25px;
        line-height: 1;
        font-weight: 850;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .dash-kpi-meta {
        margin-top: 5px;
        font-size: 11px;
        color: #94a3b8;
    }
    .dash-kpi--green {
        background: rgba(5, 150, 105, 0.07);
        border-color: rgba(5, 150, 105, 0.14);
    }
    .dash-kpi--red {
        background: rgba(220, 38, 38, 0.06);
        border-color: rgba(220, 38, 38, 0.13);
    }
    .dash-kpi--amber {
        background: rgba(217, 119, 6, 0.07);
        border-color: rgba(217, 119, 6, 0.14);
    }
    .dash-kpi--blue {
        background: rgba(37, 99, 235, 0.07);
        border-color: rgba(37, 99, 235, 0.14);
    }
    .dash-rank {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 9px;
        background: #eff6ff;
        color: var(--dash-blue);
        font-size: 12px;
        font-weight: 850;
    }

    .stat-value-row {
        display: flex;
        align-items: baseline;
        gap: 9px;
        margin-top: 4px;
    }
    .stat-percent {
        font-size: 12px;
        font-weight: 800;
        padding: 2px 9px;
        border-radius: 999px;
        color: var(--accent, var(--dash-blue));
        background: var(--accent-soft, rgba(37, 99, 235, 0.1));
        white-space: nowrap;
    }
    .stat-progress {
        height: 6px;
        border-radius: 999px;
        background: #eef2f7;
        overflow: hidden;
        margin-top: 11px;
    }
    .stat-progress-bar {
        height: 100%;
        border-radius: 999px;
        background: var(--accent, var(--dash-blue));
        transition: width 0.4s ease;
    }
    .stat-progress-text {
        font-size: 11.5px;
        color: #64748b;
        font-weight: 600;
        margin-top: 6px;
    }

    /* ---------- Operasional Resi: header ---------- */
    .ops-date-nav {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .ops-date-nav .btn-icon {
        width: 34px;
        height: 34px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
    }
    .ops-date-nav .btn-icon:hover { color: var(--dash-blue); border-color: #bfdbfe; }
    .ops-updated {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: #64748b;
        margin-top: 4px;
    }

    /* ---------- Operasional Resi: KPI strip ---------- */
    .ops-kpis {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        border: 1px solid #e9edf3;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
    }
    .ops-kpi {
        padding: 16px 18px;
        border-left: 1px solid #eef2f7;
        min-width: 0;
    }
    .ops-kpi:first-child { border-left: 0; }
    .ops-kpi-label {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 11.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .ops-kpi-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--kpi-color, #94a3b8);
        flex-shrink: 0;
    }
    .ops-kpi-value {
        margin-top: 6px;
        font-size: 28px;
        font-weight: 800;
        line-height: 1.1;
        color: #0f172a;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }
    .ops-kpi-meta {
        margin-top: 4px;
        font-size: 12px;
        color: #64748b;
    }
    .ops-kpi--attention { background: #fffbeb; }
    .ops-kpi--attention .ops-kpi-value { color: #b45309; }
    .ops-kpi--done { background: #ecfdf5; }
    .ops-kpi--done .ops-kpi-value { color: #047857; }

    /* ---------- Operasional Resi: pipeline bar ---------- */
    .ops-pipeline { margin-top: 18px; }
    .ops-pipeline-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 6px 16px;
        margin-bottom: 8px;
    }
    .ops-pipeline-title {
        font-size: 13px;
        font-weight: 800;
        color: #0f172a;
    }
    .ops-pipeline-percent {
        font-size: 13px;
        font-weight: 800;
        color: var(--dash-green);
    }
    .ops-stack {
        display: flex;
        height: 12px;
        border-radius: 999px;
        overflow: hidden;
        background: #eef2f7;
    }
    .ops-stack span { height: 100%; }
    .ops-stack .seg-scan    { background: #059669; }
    .ops-stack .seg-waiting { background: #60a5fa; }
    .ops-stack .seg-new     { background: #fbbf24; }
    .ops-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 6px 18px;
        margin-top: 10px;
        font-size: 12px;
        color: #475569;
    }
    .ops-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .ops-legend-item b { color: #0f172a; font-variant-numeric: tabular-nums; }
    .ops-legend-swatch {
        width: 10px;
        height: 10px;
        border-radius: 3px;
    }

    /* ---------- Operasional Resi: kurir list ---------- */
    .kl {
        border: 1px solid #e9edf3;
        border-radius: 14px;
        overflow: hidden;
    }
    .kl-row {
        display: grid;
        grid-template-columns: minmax(170px, 1.7fr) repeat(5, minmax(72px, 0.75fr)) minmax(150px, 1.4fr) 104px;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-top: 1px solid #eef2f7;
    }
    .kl-body .kl-row:first-child { border-top: 0; }
    .kl-body .kl-row:hover { background: #f8fafc; }
    .kl-head {
        background: #f8fafc;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        padding-top: 10px;
        padding-bottom: 10px;
        border-top: 0;
        border-bottom: 1px solid #eef2f7;
    }
    .kl-foot {
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }
    .kl-num {
        text-align: right;
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
    }
    .kl-num.is-zero { color: #cbd5e1; font-weight: 600; }
    .kl-num.is-cancel { color: #dc2626; }
    .kl-name {
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kl-sub {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        color: #94a3b8;
        margin-top: 2px;
    }
    .kl-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    .kl-badge--pending { background: rgba(217, 119, 6, 0.12); color: #b45309; }
    .kl-badge--done    { background: rgba(5, 150, 105, 0.12); color: #047857; font-weight: 700; }
    .kl-progress {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .kl-bar {
        flex: 1;
        height: 8px;
        border-radius: 999px;
        background: #eef2f7;
        overflow: hidden;
    }
    .kl-bar span {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: #059669;
    }
    .kl-bar.is-low span { background: #f59e0b; }
    .kl-percent {
        width: 40px;
        text-align: right;
        font-size: 12.5px;
        font-weight: 800;
        color: #334155;
        font-variant-numeric: tabular-nums;
    }
    .kl-action { text-align: right; }
    .kl-detail-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: var(--dash-blue);
        font-size: 12.5px;
        font-weight: 700;
        white-space: nowrap;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .kl-detail-btn:hover { background: var(--dash-blue); border-color: var(--dash-blue); color: #fff; }
    .kl-label { display: none; }

    @media (max-width: 1199px) {
        .ops-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .ops-kpi:nth-child(4) { border-left: 0; }
        .ops-kpi:nth-child(n+4) { border-top: 1px solid #eef2f7; }
    }

    @media (max-width: 991px) {
        .kl-head { display: none; }
        .kl-row {
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px 8px;
            padding: 14px;
        }
        .kl-cell-name { grid-column: 1 / 4; min-width: 0; }
        .kl-action { grid-column: 4 / 6; }
        .kl-progress { grid-column: 1 / -1; order: 2; }
        .kl-num {
            order: 3;
            text-align: left;
            background: #f8fafc;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 13px;
        }
        .kl-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 2px;
        }
        .kl-foot .kl-num { background: #fff; }
        .kl-foot .kl-action { display: none; }
        .kl-foot .kl-cell-name { grid-column: 1 / -1; }
    }

    /* ---------- Modal filter cards ---------- */
    .filter-card-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    @media (max-width: 575px) { .filter-card-grid { grid-template-columns: repeat(2, 1fr); } }
    .filter-card {
        display: flex;
        align-items: center;
        gap: 11px;
        text-align: left;
        padding: 12px;
        border-radius: 13px;
        border: 1.5px solid #e9edf3;
        background: #fff;
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease, transform 0.12s ease;
    }
    .filter-card:hover { transform: translateY(-2px); border-color: #cbd5e1; }
    .filter-card-icon {
        flex-shrink: 0;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        color: var(--fc-color, var(--dash-slate));
        background: var(--fc-soft, rgba(71, 85, 105, 0.1));
    }
    .filter-card-body { min-width: 0; }
    .filter-card-label {
        font-size: 10.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .filter-card-value {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }
    .filter-card.active {
        border-color: var(--fc-color, var(--dash-slate));
        background: var(--fc-soft, rgba(71, 85, 105, 0.08));
    }
    .filter-card[data-status="all"]      { --fc-color: var(--dash-blue);  --fc-soft: rgba(37, 99, 235, 0.1); }
    .filter-card[data-status="scanned"]  { --fc-color: var(--dash-green); --fc-soft: rgba(5, 150, 105, 0.1); }
    .filter-card[data-status="pending"]  { --fc-color: var(--dash-amber); --fc-soft: rgba(217, 119, 6, 0.12); }
    .filter-card[data-status="canceled"] { --fc-color: var(--dash-red);   --fc-soft: rgba(220, 38, 38, 0.1); }

    /* ---------- Modal search ---------- */
    .modal-search {
        position: relative;
        max-width: 340px;
    }
    .modal-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 13px;
    }
    .modal-search input {
        width: 100%;
        height: 38px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0 12px 0 34px;
        font-size: 13px;
    }
    .modal-search input:focus {
        outline: none;
        border-color: var(--dash-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    @media (max-width: 767px) {
        .dash-section-head {
            gap: 12px;
        }
        .dash-section-head > div,
        .dash-filter {
            width: 100%;
        }
        .dash-filter {
            align-items: stretch;
            padding: 10px;
        }
        .dash-filter-label {
            width: 100%;
        }
        .dash-filter input {
            flex: 1 1 160px;
            width: auto;
            max-width: none;
        }
        .dash-filter .btn {
            flex: 1 1 auto;
        }
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .stat-card {
            align-items: flex-start;
            padding: 15px;
            border-radius: 12px;
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            font-size: 18px;
        }
        .stat-value {
            font-size: 26px;
        }
        .stat-value-row {
            flex-wrap: wrap;
            gap: 6px;
        }
        .dash-tabs {
            margin-left: -4px;
            margin-right: -4px;
        }
        .dash-tabs .nav-link {
            padding: 10px 12px;
            font-size: 12.5px;
        }
        .dash-table-head {
            padding: 14px;
        }
        .dash-table-head .btn {
            width: 100%;
        }
        .dash-panel {
            padding: 14px;
            border-radius: 14px;
        }
        .dash-panel .stat-card {
            min-height: auto;
        }
        .dash-kpi-grid {
            grid-template-columns: 1fr;
            padding: 14px;
        }
        .filter-card-grid {
            grid-template-columns: 1fr;
        }
        .modal-search {
            max-width: none;
            width: 100%;
        }
        .ops-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .ops-kpi { border-left: 0; border-top: 1px solid #eef2f7; padding: 14px; }
        .ops-kpi:nth-child(-n+2) { border-top: 0; }
        .ops-kpi:nth-child(even) { border-left: 1px solid #eef2f7; }
        .ops-kpi-value { font-size: 24px; }
        .ops-date-nav { flex: 1 1 100%; }
        .ops-date-nav input { flex: 1 1 auto; }
    }

    @media (min-width: 768px) and (max-width: 1199px) {
        .stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 420px) {
        .stat-card {
            gap: 12px;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
        }
        .stat-value {
            font-size: 23px;
        }
        .stat-percent {
            font-size: 11px;
            padding: 2px 7px;
        }
        .dash-section-title {
            font-size: 15px;
        }
        .dash-section-sub,
        .dash-legend {
            font-size: 11.5px;
        }
    }

    /* ---------- Modal table ---------- */
    #kurir_detail_table thead th {
        background: #f8fafc;
        font-size: 10.5px;
    }
    #kurir_detail_table tbody td { vertical-align: middle; }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .status-pill--scanned  { background: rgba(5, 150, 105, 0.12); color: #047857; }
    .status-pill--pending  { background: rgba(217, 119, 6, 0.12); color: #b45309; }
    .status-pill--canceled { background: rgba(220, 38, 38, 0.1);  color: #b91c1c; }
    .sku-list {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    .sku-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        border-radius: 7px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        font-size: 11.5px;
        font-weight: 600;
        color: #334155;
    }
    .sku-chip i { color: #94a3b8; font-size: 10px; }
    .sku-chip .sku-qty {
        font-weight: 800;
        color: var(--dash-blue);
    }
    .sku-total {
        font-size: 10.5px;
        color: #94a3b8;
        font-weight: 600;
        margin-top: 4px;
    }
    .mono { font-variant-numeric: tabular-nums; }

    .info-tip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 15px;
        height: 15px;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        color: #94a3b8;
        cursor: help;
        margin-left: 6px;
    }
    .info-tip i { font-size: 8px; }
</style>

<div class="dash-wrap">
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    <ul class="nav nav-tabs dash-tabs mb-6" id="dashboard_tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ request('tab') !== 'report' ? 'active' : '' }}" id="tab-resi" data-bs-toggle="tab" data-bs-target="#pane-resi" type="button" role="tab" aria-controls="pane-resi" aria-selected="{{ request('tab') !== 'report' ? 'true' : 'false' }}">
                <i class="fa-solid fa-truck-fast me-2"></i>Operasional Resi
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-stock" data-bs-toggle="tab" data-bs-target="#pane-stock" type="button" role="tab" aria-controls="pane-stock" aria-selected="false">
                <i class="fa-solid fa-boxes-stacked me-2"></i>Stok
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-inventory" data-bs-toggle="tab" data-bs-target="#pane-inventory" type="button" role="tab" aria-controls="pane-inventory" aria-selected="false">
                <i class="fa-solid fa-chart-line me-2"></i>Aktivitas Inventory
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ request('tab') === 'report' ? 'active' : '' }}" id="tab-report" data-bs-toggle="tab" data-bs-target="#pane-report" type="button" role="tab" aria-controls="pane-report" aria-selected="{{ request('tab') === 'report' ? 'true' : 'false' }}">
                <i class="fa-solid fa-chart-column me-2"></i>Laporan Resi
            </button>
        </li>
    </ul>

    <div class="tab-content" id="dashboard_tab_content">
        <div class="tab-pane fade {{ request('tab') === 'report' ? 'show active' : '' }}" id="pane-report" role="tabpanel" aria-labelledby="tab-report">
            @include('admin.dashboards.resi-report')
        </div>
        <div class="tab-pane fade {{ request('tab') !== 'report' ? 'show active' : '' }}" id="pane-resi" role="tabpanel" aria-labelledby="tab-resi">
    @php
        $rs = $resiSummary;
        $selectedCarbon = \Illuminate\Support\Carbon::parse($today);
        $isToday = $selectedCarbon->isToday();
        $grandTotal = $rs->active + $rs->canceled;
        $pct = fn ($part, $whole) => $whole > 0 ? (int) floor($part / $whole * 100) : 0;
        $scanPercent = $pct($rs->scan, $rs->active);
        $qcPercent = $pct($rs->qc, $rs->active);
        $segScan = $rs->active > 0 ? $rs->scan / $rs->active * 100 : 0;
        $segWaiting = $rs->active > 0 ? $rs->waiting_scan / $rs->active * 100 : 0;
        $segNew = $rs->active > 0 ? $rs->not_started / $rs->active * 100 : 0;
        $dateUrl = fn ($date) => url()->current().'?'.http_build_query(['date' => $date]);
    @endphp

    {{-- ============ Ringkasan Resi ============ --}}
    <div class="card mb-6">
        <div class="card-body">
            <div class="dash-section-head mb-5">
                <div>
                    <div class="dash-section-title"><i class="fa-solid fa-chart-pie"></i> Ringkasan Resi</div>
                    <div class="dash-section-sub">
                        Resi dengan tanggal upload <b class="text-gray-800">{{ $selectedCarbon->locale('id')->translatedFormat('l, d F Y') }}</b>
                        @if($isToday) <span class="badge badge-light-primary ms-1">Hari ini</span> @endif
                    </div>
                    <div class="ops-updated"><i class="fa-regular fa-clock"></i> Aktivitas terakhir {{ $rs->last_update }}</div>
                </div>
                <form class="dash-filter" method="GET" action="{{ url()->current() }}">
                    <span class="dash-filter-label"><i class="fa-regular fa-calendar"></i> Tanggal</span>
                    <div class="ops-date-nav">
                        <a href="{{ $dateUrl($selectedCarbon->copy()->subDay()->toDateString()) }}" class="btn btn-icon btn-sm" title="Hari sebelumnya"><i class="fa-solid fa-chevron-left"></i></a>
                        <input type="text" name="date" id="filter_date" value="{{ $today }}" autocomplete="off" />
                        <a href="{{ $dateUrl($selectedCarbon->copy()->addDay()->toDateString()) }}" class="btn btn-icon btn-sm" title="Hari berikutnya"><i class="fa-solid fa-chevron-right"></i></a>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Terapkan</button>
                    @unless($isToday)
                        <a href="{{ url()->current() }}" class="btn btn-sm btn-light">Hari ini</a>
                    @endunless
                </form>
            </div>

            <div class="ops-kpis">
                <div class="ops-kpi" style="--kpi-color: var(--dash-blue)">
                    <div class="ops-kpi-label"><span class="ops-kpi-dot"></span> Resi Aktif</div>
                    <div class="ops-kpi-value">{{ number_format($rs->active) }}</div>
                    <div class="ops-kpi-meta">dari {{ number_format($grandTotal) }} resi diupload</div>
                </div>
                <div class="ops-kpi" style="--kpi-color: #60a5fa">
                    <div class="ops-kpi-label"><span class="ops-kpi-dot"></span> Sudah QC</div>
                    <div class="ops-kpi-value">{{ number_format($rs->qc) }}</div>
                    <div class="ops-kpi-meta">{{ $qcPercent }}% resi aktif &middot; {{ number_format($rs->qc_completed) }} QC selesai</div>
                </div>
                <div class="ops-kpi" style="--kpi-color: var(--dash-green)">
                    <div class="ops-kpi-label"><span class="ops-kpi-dot"></span> Sudah Scan Out</div>
                    <div class="ops-kpi-value">{{ number_format($rs->scan) }}</div>
                    <div class="ops-kpi-meta">{{ $scanPercent }}% resi aktif</div>
                </div>
                @if($rs->active > 0 && $rs->remaining === 0)
                    <div class="ops-kpi ops-kpi--done" style="--kpi-color: var(--dash-green)">
                        <div class="ops-kpi-label"><span class="ops-kpi-dot"></span> Belum Scan Out</div>
                        <div class="ops-kpi-value">0</div>
                        <div class="ops-kpi-meta"><i class="fa-solid fa-circle-check text-success"></i> Semua resi sudah scan out</div>
                    </div>
                @else
                    <div class="ops-kpi {{ $rs->remaining > 0 ? 'ops-kpi--attention' : '' }}" style="--kpi-color: var(--dash-amber)">
                        <div class="ops-kpi-label"><span class="ops-kpi-dot"></span> Belum Scan Out</div>
                        <div class="ops-kpi-value">{{ number_format($rs->remaining) }}</div>
                        <div class="ops-kpi-meta">{{ number_format($rs->waiting_scan) }} sudah QC &middot; {{ number_format($rs->not_started) }} belum QC</div>
                    </div>
                @endif
                <div class="ops-kpi" style="--kpi-color: var(--dash-red)">
                    <div class="ops-kpi-label"><span class="ops-kpi-dot"></span> Cancel</div>
                    <div class="ops-kpi-value {{ $rs->canceled > 0 ? 'text-danger' : '' }}">{{ number_format($rs->canceled) }}</div>
                    <div class="ops-kpi-meta">{{ $pct($rs->canceled, $grandTotal) }}% dari resi diupload</div>
                </div>
            </div>

            <div class="ops-pipeline">
                <div class="ops-pipeline-head">
                    <div class="ops-pipeline-title">Progres Resi Aktif</div>
                    <div class="ops-pipeline-percent">{{ $scanPercent }}% selesai scan out</div>
                </div>
                <div class="ops-stack" role="img" aria-label="{{ $scanPercent }}% resi aktif sudah scan out">
                    <span class="seg-scan" style="width: {{ $segScan }}%"></span>
                    <span class="seg-waiting" style="width: {{ $segWaiting }}%"></span>
                    <span class="seg-new" style="width: {{ $segNew }}%"></span>
                </div>
                <div class="ops-legend">
                    <span class="ops-legend-item"><span class="ops-legend-swatch" style="background:#059669"></span> Sudah scan out <b>{{ number_format($rs->scan) }}</b></span>
                    <span class="ops-legend-item"><span class="ops-legend-swatch" style="background:#60a5fa"></span> Sudah QC, menunggu scan out <b>{{ number_format($rs->waiting_scan) }}</b></span>
                    <span class="ops-legend-item"><span class="ops-legend-swatch" style="background:#fbbf24"></span> Belum diproses <b>{{ number_format($rs->not_started) }}</b></span>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Per Kurir ============ --}}
    <div class="card">
        <div class="card-body">
            <div class="dash-section-head mb-5">
                <div>
                    <div class="dash-section-title"><i class="fa-solid fa-truck-fast"></i> Per Kurir</div>
                    <div class="dash-section-sub">
                        @if($kurirs->count())
                            {{ $kurirs->count() }} kurir memiliki resi pada tanggal ini &middot; {{ $rs->kurir_done }} selesai &middot; diurutkan dari sisa terbanyak
                        @else
                            Kurir yang memiliki resi pada tanggal ini
                        @endif
                    </div>
                </div>
            </div>

            @if($kurirs->count())
                <div class="kl">
                    <div class="kl-row kl-head">
                        <div>Kurir</div>
                        <div class="text-end">Resi Aktif</div>
                        <div class="text-end">Sudah QC</div>
                        <div class="text-end">Scan Out</div>
                        <div class="text-end">Belum Scan</div>
                        <div class="text-end">Cancel</div>
                        <div>Progres Scan Out</div>
                        <div></div>
                    </div>
                    <div class="kl-body">
                        @foreach($kurirs as $kurir)
                            <div class="kl-row">
                                <div class="kl-cell-name">
                                    <div class="kl-name" title="{{ $kurir['name'] }}">{{ $kurir['name'] }}</div>
                                    <div class="kl-sub"><i class="fa-regular fa-clock"></i> Update {{ $kurir['last_update'] }}</div>
                                </div>
                                <div class="kl-num {{ $kurir['resi_total'] ? '' : 'is-zero' }}"><span class="kl-label">Resi Aktif</span>{{ number_format($kurir['resi_total']) }}</div>
                                <div class="kl-num {{ $kurir['qc_total'] ? '' : 'is-zero' }}"><span class="kl-label">Sudah QC</span>{{ number_format($kurir['qc_total']) }}</div>
                                <div class="kl-num {{ $kurir['scan_total'] ? '' : 'is-zero' }}"><span class="kl-label">Scan Out</span>{{ number_format($kurir['scan_total']) }}</div>
                                <div class="kl-num">
                                    <span class="kl-label">Belum Scan</span>
                                    @if($kurir['remaining'] > 0)
                                        <span class="kl-badge kl-badge--pending">{{ number_format($kurir['remaining']) }}</span>
                                    @elseif($kurir['resi_total'] > 0)
                                        <span class="kl-badge kl-badge--done"><i class="fa-solid fa-check"></i> Selesai</span>
                                    @else
                                        <span class="text-gray-300">0</span>
                                    @endif
                                </div>
                                <div class="kl-num {{ $kurir['canceled_total'] ? 'is-cancel' : 'is-zero' }}"><span class="kl-label">Cancel</span>{{ number_format($kurir['canceled_total']) }}</div>
                                <div class="kl-progress">
                                    <div class="kl-bar {{ $kurir['progress'] < 50 ? 'is-low' : '' }}"><span style="width: {{ $kurir['progress'] }}%"></span></div>
                                    <div class="kl-percent">{{ $kurir['progress'] }}%</div>
                                </div>
                                <div class="kl-action">
                                    @if($kurir['id'])
                                        <button
                                            type="button"
                                            class="kl-detail-btn btn-kurir-detail"
                                            data-kurir-id="{{ $kurir['id'] }}"
                                            data-kurir-name="{{ $kurir['name'] }}"
                                            data-date="{{ $today }}"
                                        >
                                            <i class="fa-solid fa-list-ul"></i> Detail
                                        </button>
                                    @else
                                        <span class="text-muted fs-8">Kurir belum diisi</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($kurirs->count() > 1)
                        <div class="kl-row kl-foot">
                            <div class="kl-cell-name"><div class="kl-name">Total</div></div>
                            <div class="kl-num"><span class="kl-label">Resi Aktif</span>{{ number_format($rs->active) }}</div>
                            <div class="kl-num"><span class="kl-label">Sudah QC</span>{{ number_format($rs->qc) }}</div>
                            <div class="kl-num"><span class="kl-label">Scan Out</span>{{ number_format($rs->scan) }}</div>
                            <div class="kl-num"><span class="kl-label">Belum Scan</span>{{ number_format($rs->remaining) }}</div>
                            <div class="kl-num {{ $rs->canceled ? 'is-cancel' : '' }}"><span class="kl-label">Cancel</span>{{ number_format($rs->canceled) }}</div>
                            <div class="kl-progress">
                                <div class="kl-bar {{ $scanPercent < 50 ? 'is-low' : '' }}"><span style="width: {{ $scanPercent }}%"></span></div>
                                <div class="kl-percent">{{ $scanPercent }}%</div>
                            </div>
                            <div class="kl-action"></div>
                        </div>
                    @endif
                </div>
            @else
                <div class="text-center text-muted py-10">
                    <i class="fa-solid fa-truck-fast fs-2x mb-3 d-block text-gray-300"></i>
                    Tidak ada resi yang diupload pada tanggal ini.
                </div>
            @endif
        </div>
    </div>
        </div>

        <div class="tab-pane fade" id="pane-stock" role="tabpanel" aria-labelledby="tab-stock">
            @php
                $summary = $inventorySummary ?? null;
                $totalSku = (int) ($summary->total_sku ?? 0);
                $totalStock = (int) ($summary->total_stock ?? 0);
                $outStock = (int) ($summary->out_of_stock ?? 0);
                $lowStock = (int) ($summary->low_stock ?? 0);
                $noSafetyStock = (int) ($summary->no_safety_stock ?? 0);
                $healthyStock = max(0, $totalSku - $outStock - $lowStock);
                $healthyPercent = $totalSku > 0 ? round($healthyStock / $totalSku * 100) : 0;
            @endphp

            <div class="dash-panel mb-6">
                    <div class="dash-section-head mb-5">
                        <div>
                            <div class="dash-section-title"><i class="fa-solid fa-warehouse"></i> Kesehatan Stok</div>
                            <div class="dash-section-sub">Ringkasan stok aktif saat ini dan daftar SKU yang perlu perhatian</div>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card stat-card--blue">
                            <div class="stat-icon"><i class="fa-solid fa-cubes"></i></div>
                            <div class="stat-body">
                                <div class="stat-label">Total SKU Aktif</div>
                                <div class="stat-value">{{ number_format($totalSku) }}</div>
                                <div class="stat-meta">{{ number_format($totalStock) }} total stok tersedia</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card--red">
                            <div class="stat-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                            <div class="stat-body">
                                <div class="stat-label">Stok Habis</div>
                                <div class="stat-value text-danger">{{ number_format($outStock) }}</div>
                                <div class="stat-meta">SKU dengan stok 0 atau minus</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card--amber">
                            <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="stat-body">
                                <div class="stat-label">Stok Menipis</div>
                                <div class="stat-value">{{ number_format($lowStock) }}</div>
                                <div class="stat-meta">Di bawah safety stock</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card--green">
                            <div class="stat-icon"><i class="fa-solid fa-shield-heart"></i></div>
                            <div class="stat-body">
                                <div class="stat-label">Stok Aman</div>
                                <div class="stat-value">{{ number_format($healthyStock) }}</div>
                                <div class="stat-progress">
                                    <div class="stat-progress-bar" style="width: {{ $healthyPercent }}%"></div>
                                </div>
                                <div class="stat-progress-text">{{ $healthyPercent }}% dari SKU aktif &middot; {{ number_format($noSafetyStock) }} tanpa safety stock</div>
                            </div>
                        </div>
                    </div>
            </div>

            <div class="row g-6">
                <div class="col-xl-6">
                    <div class="dash-table-card h-100">
                        <div class="dash-table-head">
                            <div>
                                <div class="dash-table-title">Stok Habis</div>
                                <div class="dash-table-sub">Maksimal 8 SKU pertama yang perlu restock</div>
                            </div>
                            <a href="{{ route('admin.reports.low-stock.index', ['status' => 'out']) }}" class="btn btn-sm btn-light-primary">Lihat laporan</a>
                        </div>
                        @if(isset($outOfStockItems) && $outOfStockItems->count())
                            <div class="table-responsive">
                                <table class="table dash-mini-table table-row-dashed align-middle">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Nama</th>
                                            <th class="text-end">Safety</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($outOfStockItems as $item)
                                            <tr>
                                                <td class="fw-bold mono">{{ $item->sku ?? '-' }}</td>
                                                <td>
                                                    <div class="dash-item-name">{{ $item->name ?? '-' }}</div>
                                                    <div class="dash-item-meta">{{ $item->address ?? '-' }}</div>
                                                </td>
                                                <td class="text-end fw-bold">{{ number_format((int) ($item->safety_stock ?? 0)) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="dash-empty">Tidak ada SKU yang stoknya habis.</div>
                        @endif
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="dash-table-card h-100">
                        <div class="dash-table-head">
                            <div>
                                <div class="dash-table-title">Stok Menipis</div>
                                <div class="dash-table-sub">Diurutkan dari gap safety stock terbesar</div>
                            </div>
                            <a href="{{ route('admin.reports.low-stock.index', ['status' => 'low']) }}" class="btn btn-sm btn-light-warning">Lihat laporan</a>
                        </div>
                        @if(isset($lowStockItems) && $lowStockItems->count())
                            <div class="table-responsive">
                                <table class="table dash-mini-table table-row-dashed align-middle">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Nama</th>
                                            <th class="text-end">Stok / Safety</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($lowStockItems as $item)
                                            <tr>
                                                <td class="fw-bold mono">{{ $item->sku ?? '-' }}</td>
                                                <td>
                                                    <div class="dash-item-name">{{ $item->name ?? '-' }}</div>
                                                    <div class="dash-item-meta">Kurang {{ number_format((int) ($item->gap ?? 0)) }} pcs &middot; {{ $item->address ?? '-' }}</div>
                                                </td>
                                                <td class="text-end fw-bold">{{ number_format((int) ($item->stock ?? 0)) }} / {{ number_format((int) ($item->safety_stock ?? 0)) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="dash-empty">Tidak ada SKU yang berada di bawah safety stock.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-inventory" role="tabpanel" aria-labelledby="tab-inventory">
            @php
                $movement = $todayMovement ?? null;
                $stockIn = (int) ($movement->stock_in ?? 0);
                $stockOut = (int) ($movement->stock_out ?? 0);
                $skuIn = (int) ($movement->sku_in ?? 0);
                $skuOut = (int) ($movement->sku_out ?? 0);
                $pendingTotal = array_sum($pendingApprovals ?? []);
            @endphp

            <div class="dash-panel mb-6">
                    <div class="dash-section-head mb-5">
                        <div>
                            <div class="dash-section-title"><i class="fa-solid fa-right-left"></i> Aktivitas Inventory</div>
                            <div class="dash-section-sub">Pergerakan stok pada {{ $today ?? '-' }} dan approval yang masih pending</div>
                        </div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card stat-card--green">
                            <div class="stat-icon"><i class="fa-solid fa-arrow-down"></i></div>
                            <div class="stat-body">
                                <div class="stat-label">Stok Masuk Hari Ini</div>
                                <div class="stat-value">{{ number_format($stockIn) }}</div>
                                <div class="stat-meta">{{ number_format($skuIn) }} SKU bergerak masuk</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card--red">
                            <div class="stat-icon"><i class="fa-solid fa-arrow-up"></i></div>
                            <div class="stat-body">
                                <div class="stat-label">Stok Keluar Hari Ini</div>
                                <div class="stat-value text-danger">{{ number_format($stockOut) }}</div>
                                <div class="stat-meta">{{ number_format($skuOut) }} SKU bergerak keluar</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card--blue">
                            <div class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></div>
                            <div class="stat-body">
                                <div class="stat-label">Net Movement</div>
                                <div class="stat-value">{{ number_format($stockIn - $stockOut) }}</div>
                                <div class="stat-meta">Masuk dikurangi keluar</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card--amber">
                            <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                            <div class="stat-body">
                                <div class="stat-label">Approval Pending</div>
                                <div class="stat-value">{{ number_format($pendingTotal) }}</div>
                                <div class="stat-meta">Inbound, outbound, adjustment, dan damaged goods</div>
                            </div>
                        </div>
                    </div>
            </div>

            <div class="row g-6">
                <div class="col-xl-7">
                    <div class="dash-table-card h-100">
                        <div class="dash-table-head">
                            <div>
                                <div class="dash-table-title">Stok Paling Sering Keluar</div>
                                <div class="dash-table-sub">Akumulasi 30 hari sampai tanggal filter</div>
                            </div>
                            <a href="{{ route('admin.reports.stock-mutations.index') }}" class="btn btn-sm btn-light-primary">Lihat mutasi</a>
                        </div>
                        @if(isset($topOutgoingItems) && $topOutgoingItems->count())
                            <div class="table-responsive">
                                <table class="table dash-mini-table table-row-dashed align-middle">
                                    <thead>
                                        <tr>
                                            <th width="44">#</th>
                                            <th>SKU</th>
                                            <th>Nama</th>
                                            <th class="text-end">Qty Keluar</th>
                                            <th class="text-end">Mutasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topOutgoingItems as $item)
                                            <tr>
                                                <td><span class="dash-rank">{{ $loop->iteration }}</span></td>
                                                <td class="fw-bold mono">{{ $item->sku ?? '-' }}</td>
                                                <td><div class="dash-item-name">{{ $item->name ?? '-' }}</div></td>
                                                <td class="text-end fw-bold text-danger">{{ number_format((int) ($item->total_qty ?? 0)) }}</td>
                                                <td class="text-end">{{ number_format((int) ($item->mutation_count ?? 0)) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="dash-empty">Belum ada stok keluar dalam 30 hari terakhir.</div>
                        @endif
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="dash-table-card h-100">
                        <div class="dash-table-head">
                            <div>
                                <div class="dash-table-title">Approval Pending</div>
                                <div class="dash-table-sub">Dokumen yang belum disetujui</div>
                            </div>
                        </div>
                        <div class="dash-kpi-grid">
                            <div class="dash-kpi dash-kpi--green">
                                <div class="dash-kpi-label">Inbound</div>
                                <div class="dash-kpi-value">{{ number_format((int) ($pendingApprovals['inbound'] ?? 0)) }}</div>
                                <div class="dash-kpi-meta">menunggu approve</div>
                            </div>
                            <div class="dash-kpi dash-kpi--red">
                                <div class="dash-kpi-label">Outbound</div>
                                <div class="dash-kpi-value">{{ number_format((int) ($pendingApprovals['outbound'] ?? 0)) }}</div>
                                <div class="dash-kpi-meta">menunggu approve</div>
                            </div>
                            <div class="dash-kpi dash-kpi--blue">
                                <div class="dash-kpi-label">Adjustment</div>
                                <div class="dash-kpi-value">{{ number_format((int) ($pendingApprovals['adjustment'] ?? 0)) }}</div>
                                <div class="dash-kpi-meta">penyesuaian stok</div>
                            </div>
                            <div class="dash-kpi dash-kpi--amber">
                                <div class="dash-kpi-label">Damaged Goods</div>
                                <div class="dash-kpi-value">{{ number_format((int) ($pendingApprovals['damaged_goods'] ?? 0)) }}</div>
                                <div class="dash-kpi-meta">barang rusak</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============ Modal Detail Kurir ============ --}}
<div class="modal fade" id="modal_kurir_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">
                        <i class="fa-solid fa-truck-fast text-primary me-2"></i>Detail Resi Kurir
                    </h5>
                    <div class="text-muted fs-7" id="kurir_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Filter cards: clickable per status --}}
                <div class="filter-card-grid mb-4">
                    <div class="filter-card active" role="button" tabindex="0" data-status="all">
                        <div class="filter-card-icon"><i class="fa-solid fa-layer-group"></i></div>
                        <div class="filter-card-body">
                            <div class="filter-card-label">Total Resi Aktif</div>
                            <div class="filter-card-value" id="kurir_detail_total">0</div>
                        </div>
                    </div>
                    <div class="filter-card" role="button" tabindex="0" data-status="scanned">
                        <div class="filter-card-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="filter-card-body">
                            <div class="filter-card-label">Sudah Scan Out</div>
                            <div class="filter-card-value" id="kurir_detail_scanned">0</div>
                        </div>
                    </div>
                    <div class="filter-card" role="button" tabindex="0" data-status="pending">
                        <div class="filter-card-icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="filter-card-body">
                            <div class="filter-card-label">Belum Scan Out</div>
                            <div class="filter-card-value" id="kurir_detail_remaining">0</div>
                        </div>
                    </div>
                    <div class="filter-card" role="button" tabindex="0" data-status="canceled">
                        <div class="filter-card-icon"><i class="fa-solid fa-ban"></i></div>
                        <div class="filter-card-body">
                            <div class="filter-card-label">Dibatalkan</div>
                            <div class="filter-card-value" id="kurir_detail_canceled">0</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="text-muted fs-7">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Menampilkan: <span class="fw-bold text-gray-800" id="kurir_detail_filter_label">Total Resi Aktif</span>
                    </div>
                    <div class="modal-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="kurir_detail_search" placeholder="Cari ID pesanan, no resi, atau SKU..." autocomplete="off" />
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle" id="kurir_detail_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="18%">ID Pesanan</th>
                                <th width="18%">No Resi</th>
                                <th width="15%">Status</th>
                                <th width="34%">SKU &amp; Jumlah</th>
                                <th width="15%">Tanggal Upload</th>
                            </tr>
                        </thead>
                        <tbody id="kurir_detail_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const kurirDetailUrl = '{{ route('admin.dashboard.kurir-detail') }}';

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
        }

        const dateInput = document.getElementById('filter_date');
        if (typeof flatpickr !== 'undefined' && dateInput) {
            flatpickr(dateInput, { dateFormat: 'Y-m-d', allowInput: true });
        }

        const detailModalEl = document.getElementById('modal_kurir_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const detailSubtitle = document.getElementById('kurir_detail_subtitle');
        const detailTotal = document.getElementById('kurir_detail_total');
        const detailScanned = document.getElementById('kurir_detail_scanned');
        const detailRemaining = document.getElementById('kurir_detail_remaining');
        const detailCanceled = document.getElementById('kurir_detail_canceled');
        const detailBody = document.getElementById('kurir_detail_body');
        const detailSearch = document.getElementById('kurir_detail_search');
        const detailFilterLabel = document.getElementById('kurir_detail_filter_label');
        const filterCards = Array.from(document.querySelectorAll('.filter-card'));

        const STATUS_LABEL = {
            all: 'Total Resi Aktif',
            scanned: 'Sudah Scan Out',
            pending: 'Belum Scan Out',
            canceled: 'Dibatalkan',
        };

        let currentRows = [];
        let activeStatus = 'all';

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        const formatNumber = (value) => Number(value || 0).toLocaleString('id-ID');

        const statusPill = (row) => {
            const map = {
                scanned: 'status-pill--scanned',
                pending: 'status-pill--pending',
                canceled: 'status-pill--canceled',
            };
            const icon = {
                scanned: 'fa-circle-check',
                pending: 'fa-clock',
                canceled: 'fa-ban',
            };
            const cls = map[row.status_key] || 'status-pill--pending';
            const ic = icon[row.status_key] || 'fa-clock';
            return `<span class="status-pill ${cls}"><i class="fa-solid ${ic}"></i> ${escapeHtml(row.status_label || '-')}</span>`;
        };

        const skuCell = (row) => {
            const items = Array.isArray(row.items) ? row.items : [];
            if (!items.length) {
                return '<span class="text-muted">-</span>';
            }
            const chips = items.map((item) => `
                <span class="sku-chip">
                    <i class="fa-solid fa-cube"></i>
                    ${escapeHtml(item.sku || '-')}
                    <span class="sku-qty">&times;${formatNumber(item.qty)}</span>
                </span>
            `).join('');
            const totalLine = items.length > 1
                ? `<div class="sku-total">${items.length} SKU &middot; total ${formatNumber(row.total_qty)} pcs</div>`
                : '';
            return `<div class="sku-list">${chips}</div>${totalLine}`;
        };

        const setLoadingState = (kurirName, date) => {
            if (detailSubtitle) detailSubtitle.textContent = `${kurirName || '-'} | Tanggal ${date || '-'}`;
            [detailTotal, detailScanned, detailRemaining, detailCanceled].forEach((el) => {
                if (el) el.textContent = '0';
            });
            if (detailSearch) detailSearch.value = '';
            if (detailBody) {
                detailBody.innerHTML = `
                    <tr><td colspan="5" class="text-center text-muted py-6">
                        <span class="spinner-border spinner-border-sm me-2"></span>Memuat data...
                    </td></tr>`;
            }
        };

        const renderTable = () => {
            if (!detailBody) return;

            const keyword = (detailSearch?.value || '').trim().toLowerCase();
            const rows = currentRows.filter((row) => {
                const matchStatus = activeStatus === 'all'
                    ? (row.status_key === 'scanned' || row.status_key === 'pending')
                    : row.status_key === activeStatus;
                if (!matchStatus) return false;
                if (!keyword) return true;
                const haystack = [
                    row.id_pesanan,
                    row.no_resi,
                    ...(Array.isArray(row.items) ? row.items.map((i) => i.sku) : []),
                ].join(' ').toLowerCase();
                return haystack.includes(keyword);
            });

            if (!rows.length) {
                const msg = keyword
                    ? `Tidak ada resi yang cocok dengan pencarian "${escapeHtml(keyword)}".`
                    : `Tidak ada resi untuk status ${escapeHtml(STATUS_LABEL[activeStatus] || '-')}.`;
                detailBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-6">${msg}</td></tr>`;
                return;
            }

            detailBody.innerHTML = rows.map((row) => `
                <tr>
                    <td class="fw-semibold text-gray-800">${escapeHtml(row.id_pesanan || '-')}</td>
                    <td class="mono">${escapeHtml(row.no_resi || '-')}</td>
                    <td>${statusPill(row)}</td>
                    <td>${skuCell(row)}</td>
                    <td class="text-muted mono">${escapeHtml(row.tanggal_upload || '-')}</td>
                </tr>
            `).join('');
        };

        const setActiveStatus = (status) => {
            activeStatus = status;
            filterCards.forEach((card) => {
                card.classList.toggle('active', card.getAttribute('data-status') === status);
            });
            if (detailFilterLabel) detailFilterLabel.textContent = STATUS_LABEL[status] || '-';
            renderTable();
        };

        filterCards.forEach((card) => {
            const status = card.getAttribute('data-status');
            card.addEventListener('click', () => setActiveStatus(status));
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    setActiveStatus(status);
                }
            });
        });

        if (detailSearch) {
            detailSearch.addEventListener('input', renderTable);
        }

        document.querySelectorAll('.btn-kurir-detail').forEach((button) => {
            button.addEventListener('click', async () => {
                const kurirId = button.getAttribute('data-kurir-id');
                const kurirName = button.getAttribute('data-kurir-name') || '-';
                const date = button.getAttribute('data-date') || '';

                if (!kurirId || !detailModal) return;

                currentRows = [];
                setActiveStatus('all');
                setLoadingState(kurirName, date);
                detailModal.show();

                try {
                    const params = new URLSearchParams({ kurir_id: kurirId, date });
                    const response = await fetch(`${kurirDetailUrl}?${params.toString()}`);
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload?.message || 'Gagal memuat detail kurir.');
                    }

                    const meta = payload?.meta || {};
                    if (detailSubtitle) {
                        detailSubtitle.textContent = `${meta.kurir_name || kurirName} | Tanggal ${meta.date || date || '-'}`;
                    }
                    if (detailTotal) detailTotal.textContent = formatNumber(meta.total_resi);
                    if (detailScanned) detailScanned.textContent = formatNumber(meta.scanned_total);
                    if (detailRemaining) detailRemaining.textContent = formatNumber(meta.remaining_total);
                    if (detailCanceled) detailCanceled.textContent = formatNumber(meta.canceled_total);

                    currentRows = Array.isArray(payload?.data) ? payload.data : [];
                    renderTable();
                } catch (error) {
                    if (detailBody) {
                        detailBody.innerHTML = `
                            <tr><td colspan="5" class="text-center text-danger py-6">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                ${escapeHtml(error.message || 'Gagal memuat detail kurir.')}
                            </td></tr>`;
                    }
                }
            });
        });
    });
</script>
@endpush
