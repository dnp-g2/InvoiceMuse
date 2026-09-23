<style>
.invoice-workspace {
    max-width:1200px;
    margin:0 auto;
    padding:24px;
    color:#263544;
    font-size:15px;
    line-height:1.5
}
.invoice-workspace [hidden] {
    display:none!important
}
.iw-heading>div {
    min-width:0;
    overflow-wrap:anywhere
}
.iw-summary-row>div {
    min-width:0;
    overflow-wrap:anywhere
}
.iw-back {
    display:inline-block;
    margin-bottom:20px
}
.iw-heading {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:24px;
    margin-bottom:24px
}
.iw-title,.iw-actions {
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap
}
.iw-title h2 {
    margin:0;
    font-size:28px;
    font-weight:600;
    letter-spacing:-.4px
}
.iw-status {
    background:#edf3f8;
    color:#325575;
    border-radius:20px;
    padding:4px 12px;
    font-size:13px
}
.iw-customer {
    font-size:19px;
    margin:8px 0 3px
}
.iw-muted {
    color:#596775
}
.iw-heading p:last-child {
    margin-bottom:0
}
.invoice-workspace .btn {
    min-height:40px;
    padding:9px 14px;
    font-size:14px;
    border-radius:5px
}
.invoice-workspace .btn-primary {
    background:#267bb5;
    border-color:#267bb5
}
.invoice-workspace .btn-link {
    padding-left:0
}
.iw-layout {
    display:grid;
    grid-template-columns:minmax(0,1fr) 290px;
    gap:24px;
    align-items:start
}
.iw-main,.iw-sidebar {
    min-width:0
}
.iw-card {
    padding:24px;
    border:1px solid #dce3e8;
    border-radius:8px;
    background:#fff;
    margin-bottom:20px
}
.invoice-workspace h3 {
    font-size:18px;
    margin:0 0 14px;
    font-weight:600
}
.iw-section-heading {
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:baseline;
    margin:28px 0 14px
}
.iw-section-heading h3 {
    margin:0
}
.iw-section-heading a {
    font-size:14px
}
.iw-billing {
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    gap:24px
}
.iw-billing address {
    min-height:0;
    height:auto;
    margin:5px 0 0;
    line-height:1.6;
    font-style:normal
}
.iw-contact {
    margin-top:12px
}
.iw-contact p {
    margin:8px 0 0;
    overflow-wrap:anywhere
}
.invoice-workspace summary {
    cursor:pointer;
    color:#354e63
}
.invoice-workspace summary:focus-visible,.invoice-workspace a:focus-visible,.invoice-workspace button:focus-visible {
    outline:3px solid #287daf;
    outline-offset:3px
}
.iw-eyebrow {
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.07em;
    font-weight:600;
    color:#596775
}
.iw-property-heading h4 {
    font-size:16px;
    line-height:1.5;
    font-weight:600;
    margin:6px 0 18px;
    overflow-wrap:anywhere
}
.iw-property-heading {
    border-bottom:1px solid #e5eaee
}
.iw-summary-head,.iw-summary-row {
    display:grid;
    grid-template-columns:minmax(0,1fr) 70px 92px 96px;
    gap:14px
}
.iw-summary-head {
    font-size:12px;
    color:#596775;
    padding:16px 0 4px
}
.iw-summary-head>span:not(:first-child),.iw-summary-row>div:not(:first-child) {
    text-align:right;
    font-variant-numeric:tabular-nums
}
.iw-summary-row {
    padding:16px 0;
    border-bottom:1px solid #edf0f3
}
.iw-summary-row small {
    display:block;
    color:#596775
}
.iw-description {
    white-space:pre-wrap;
    overflow-wrap:anywhere;
    margin:5px 0 0;
    color:#4a5967
}
.iw-adjustments {
    font-size:13px;
    margin:8px 0 0
}
.iw-mobile-label {
    display:none
}
.iw-property-total {
    display:flex;
    justify-content:space-between;
    gap:16px;
    padding-top:16px;
    font-size:14px
}
.iw-property-total strong {
    font-variant-numeric:tabular-nums
}
.iw-balance {
    display:block;
    font-size:36px;
    font-weight:600;
    line-height:1.2;
    margin:10px 0;
    color:#214c69;
    font-variant-numeric:tabular-nums
}
.iw-totals dl {
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:12px;
    margin:24px 0 0
}
.iw-totals dt {
    font-weight:400
}
.iw-totals dd {
    text-align:right;
    margin:0;
    font-variant-numeric:tabular-nums
}
.iw-totals small {
    display:block;
    color:#596775;
    font-size:12px
}
.iw-totals .iw-total-line {
    border-top:1px solid #dce3e8;
    padding-top:16px;
    font-weight:600
}
.iw-totals>p:last-child {
    margin:20px 0 0
}
.iw-extra>summary {
    font-size:16px
}
.iw-extra[open]>summary {
    margin-bottom:18px
}
.iw-extra .panel {
    border:0;
    box-shadow:none
}
.iw-extra .panel-heading {
    display:none
}
.iw-extra .panel-body {
    padding:0
}
.iw-lock {
    font-size:13px;
    color:#596775;
    margin:0 0 16px
}
.iw-notice {
    padding:12px 14px;
    background:#fff6e5;
    color:#745420;
    border-radius:5px;
    margin-bottom:18px
}
.invoice-workspace label {
    display:block;
    font-size:13px;
    font-weight:500;
    min-width:0;
    margin-bottom:12px
}
.invoice-workspace input.form-control,.invoice-workspace select.form-control,.invoice-workspace textarea.form-control {
    width:100%;
    height:auto;
    min-height:40px;
    padding:9px 10px;
    border-color:#c8d2dc;
    border-radius:5px;
    box-shadow:none;
    font-size:15px;
    color:#263544;
    background:#fff;
    margin-top:5px
}
.invoice-workspace .form-control:focus {
    outline:2px solid #287daf;
    outline-offset:1px
}
.invoice-workspace .form-control:disabled {
    background:#f3f5f6;
    color:#56616c
}
.iw-charge {
    padding:20px 0;
    border-bottom:1px solid #e4e9ee
}
.iw-charge-fields {
    display:grid;
    grid-template-columns:minmax(0,1fr) 85px 120px;
    gap:14px
}
.iw-detail-fields {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:0 16px;
    margin-top:14px
}
.iw-charge-details {
    margin:12px 0
}
.iw-row-actions {
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:baseline;
    flex-wrap:wrap
}
.iw-saved-line {
    font-size:12px;
    color:#596775;
    margin-left:auto;
    font-variant-numeric:tabular-nums
}
.iw-row-more {
    flex:1;
    min-width:0
}
.iw-row-more>.iw-actions {
    margin:12px 0
}
.iw-row-more summary,.iw-charge-details summary {
    font-size:13px
}
.iw-remove {
    color:#a23838
}
.iw-group-actions {
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    padding-top:18px
}
.iw-add-property {
    border:1px dashed #b8c9d5;
    border-radius:8px;
    padding:18px;
    margin-bottom:24px
}
.iw-add-property select {
    flex:1;
    min-width:0;
    max-width:100%;
    margin-top:0!important
}
.invoice-workspace .dropdown-menu {
    max-width:calc(100vw - 48px)
}
.invoice-workspace .dropdown-menu>li>button {
    display:block;
    width:100%;
    border:0;
    background:transparent;
    text-align:left;
    padding:8px 20px;
    color:#334552;
    font-size:14px
}
.invoice-workspace .dropdown-menu>li>a {
    padding:8px 20px;
    white-space:normal
}
.invoice-workspace .dropdown-menu>li>button:hover {
    background:#f1f5f8
}
.iw-check {
    margin-top:18px
}
.iw-check input {
    margin-right:6px
}
.iw-settings-data dt {
    font-weight:500;
    margin-top:12px
}
.iw-settings-data dd {
    margin:3px 0
}
.iw-tax-row {
    display:flex;
    gap:12px;
    align-items:center
}
.iw-tax-row form {
    margin:0
}
.iw-has-error {
    border-color:#b23b36!important
}
.iw-sidebar {
    position:sticky;
    top:20px
}
#invoice-feedback:not(:empty) {
    margin-bottom:18px;
    padding:12px;
    background:#edf5ee;
    color:#2b613c;
    border-radius:5px
}
.invoice-workspace .iw-charge textarea.form-control {
    min-height:72px;
    height:72px;
    resize:vertical
}
@media(max-width:1000px) {
    .iw-layout {
        grid-template-columns:minmax(0,1fr)
    }
    .iw-sidebar {
        position:static;
        grid-row:1
    }
    .iw-totals {
        display:grid;
        grid-template-columns:minmax(0,1fr) minmax(0,1fr);
        gap:0 24px
    }
    .iw-totals dl {
        grid-column:2;
        grid-row:1 / span 4;
        margin:0
    }
    .iw-totals>p:last-child {
        margin:10px 0 0
    }
    .iw-heading {
        flex-direction:column
    }
    .iw-sidebar>p {
        margin-top:-10px
    }
    .iw-totals .iw-notice {
        grid-column:1 / -1
    }
}
@media(max-width:650px) {
    .invoice-workspace {
        padding:18px 14px;
        font-size:15px
    }
    .iw-heading {
        gap:16px
    }
    .iw-title h2 {
        font-size:25px
    }
    .iw-card {
        padding:18px
    }
    .iw-layout {
        gap:8px
    }
    .iw-billing {
        grid-template-columns:1fr;
        gap:16px
    }
    .iw-dates {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:12px
    }
    .iw-summary-head {
        display:none
    }
    .iw-summary-row {
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:12px
    }
    .iw-summary-row>div:first-child {
        grid-column:1 / -1
    }
    .iw-summary-row>div:not(:first-child) {
        text-align:left
    }
    .iw-mobile-label {
        display:block;
        color:#596775;
        font-size:12px;
        margin-bottom:4px
    }
    .iw-section-heading {
        align-items:start
    }
    .iw-section-heading a {
        max-width:120px;
        text-align:right
    }
    .iw-charge-fields {
        grid-template-columns:1fr 1fr
    }
    .iw-charge-fields>label:first-child {
        grid-column:1 / -1
    }
    .iw-detail-fields {
        grid-template-columns:1fr
    }
    .invoice-workspace .btn {
        min-height:44px
    }
    .invoice-workspace input.form-control,.invoice-workspace select.form-control,.invoice-workspace textarea.form-control {
        font-size:16px
    }
    .iw-totals {
        display:block
    }
    .iw-totals dl {
        margin-top:20px
    }
    .iw-balance {
        font-size:32px
    }
    .iw-add-property .iw-actions {
        display:block
    }
    .iw-add-property button {
        margin-top:10px
    }
    .iw-extra summary {
        min-height:28px
    }
    .iw-property-heading h4 {
        font-size:16px
    }
    .iw-back {
        margin-bottom:16px
    }
}
</style>
