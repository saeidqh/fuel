<?php

use app\components\PageHeader;

/** @var yii\web\View $this */
/** @var app\modules\fuel\models\RigFuelConsumption $model */
/** @var array $formData */
/** @var array $equipmentRows */
/** @var array $summary */
/** @var array $fuelSummary */
/** @var array $waterSummary */

$this->title = 'ثبت مصرف دکل';
$this->params['breadcrumbs'][] = $this->title;

echo PageHeader::widget([
    'title' => $this->title,
]);

$this->registerCss(<<<CSS
.rig-consumption-create {
    direction: rtl;
    padding: 10px 15px 25px;
}

.rig-consumption-create .panel {
    margin: 30px 0 0;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.rig-consumption-create .panel-body {
    position: relative;
    padding: 25px 20px 15px;
    border: 2px solid #337ab7;
    border-radius: 8px;
    background: #fff;
}

.rig-consumption-create .section-title {
    position: absolute;
    top: -18px;
    right: 20px;
    z-index: 1;
    max-width: calc(100% - 40px);
    margin: 0;
    padding: 4px 12px;
    border: 2px solid #337ab7;
    border-radius: 8px;
    background: #fff2b3;
    color: #0b58a2;
    font-size: 15px;
    line-height: 1.4;
}

.rig-consumption-create .form-group {
    margin-bottom: 15px;
}

.rig-consumption-create .control-label {
    color: #444;
    font-size: 13px;
    font-weight: 700;
}

.rig-consumption-create .form-control {
    height: 34px;
    border-radius: 4px;
    font-size: 13px;
}

.rig-consumption-create textarea.form-control {
    height: auto;
    resize: vertical;
}

.rig-consumption-create input[type="time"],
.rig-consumption-create input[type="number"] {
    direction: ltr;
    text-align: center;
    font-variant-numeric: tabular-nums;
}

.rig-consumption-create .table {
    margin-bottom: 10px;
    font-size: 13px;
}

.rig-consumption-create .table > thead > tr > th {
    border-bottom-width: 1px;
    background: #edf5fc;
    color: #23527c;
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
}

.rig-consumption-create .table > tbody > tr > td {
    text-align: center;
    vertical-align: middle;
}

.rig-consumption-create .table .form-control {
    min-width: 110px;
}

.rig-consumption-create .equipment-heading {
    margin: 0 0 15px;
    color: #23527c;
    font-size: 14px;
    font-weight: 700;
}

.rig-consumption-create .balance-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 15px;
}

.rig-consumption-create .balance-card,
.rig-consumption-create .summary-card {
    min-width: 0;
    min-height: 88px;
    padding: 14px 10px;
    border: 1px solid #d9e6f2;
    border-radius: 6px;
    background: #f8fbfe;
    text-align: center;
    overflow-wrap: anywhere;
}

.rig-consumption-create .summary-card {
    margin-bottom: 15px;
}

.rig-consumption-create .balance-label,
.rig-consumption-create .summary-card strong {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-size: 12px;
    font-weight: 700;
}

.rig-consumption-create .balance-value,
.rig-consumption-create .summary-card span {
    display: inline-block;
    direction: ltr;
    color: #0b58a2;
    font-size: 18px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.rig-consumption-create .balance-unit,
.rig-consumption-create .summary-card small {
    color: #777;
    font-size: 12px;
}

.rig-consumption-create .summary-card span.text-danger,
.rig-consumption-create .balance-value.text-danger {
    color: #b52b27;
}

.rig-consumption-create .summary-card span.text-success,
.rig-consumption-create .balance-value.text-success {
    color: #287c3e;
}

.rig-consumption-create .resource-section + .resource-section {
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #d9e6f2;
}

.rig-consumption-create .resource-master-box {
    margin: 10px 0 15px;
    padding: 12px 15px;
    border: 1px solid #bdd5eb;
    border-radius: 6px;
    background: #edf5fc;
}

.rig-consumption-create .resource-master-box label,
.rig-consumption-create .operation-box > label {
    margin-bottom: 0;
    color: #23527c;
    font-weight: 700;
    cursor: pointer;
}

.rig-consumption-create input[type="checkbox"] {
    margin-left: 7px;
    vertical-align: middle;
}

.rig-consumption-create .operation-box {
    margin-bottom: 12px;
    padding: 12px 15px;
    border: 1px solid #d9e6f2;
    border-radius: 6px;
    background: #fff;
}

.rig-consumption-create .operation-fields {
    margin-top: 15px;
}

.rig-consumption-create .end-day-box {
    padding: 9px 12px;
    border: 1px solid #337ab7;
    border-radius: 6px;
    background: #eaf3ff;
}

.rig-consumption-create .end-day-box label {
    color: #064a96;
    font-weight: 700;
    cursor: pointer;
}

.rig-consumption-create .end-day-box .form-group {
    margin-bottom: 0;
}

.rig-consumption-create .end-day-box .checkbox {
    margin: 0;
}

/* استایل ساعت در تمام اندازه‌های صفحه اعمال می‌شود. */
.rig-consumption-create .rig-end-time-group {
    direction: ltr;
    width: 100%;
}

.rig-consumption-create .rig-end-time-group .form-control {
    min-width: 75px;
    text-align: center;
}

.rig-consumption-create #rig-end-day-display {
    direction: rtl;
    height: 34px;
    background: #eaf3ff;
    color: #064a96;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.rig-consumption-create .rig-end-day-button,
.rig-consumption-create #rig-end-day-toggle {
    direction: rtl;
    height: 34px;
    padding: 6px 12px;
    border-color: #0753a0;
    background: #0864bd;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.rig-consumption-create .rig-end-day-button:hover,
.rig-consumption-create .rig-end-day-button:focus,
.rig-consumption-create #rig-end-day-toggle:hover,
.rig-consumption-create #rig-end-day-toggle:focus {
    border-color: #043c78;
    background: #0753a0;
    color: #fff;
}

.rig-consumption-create .rig-end-day-button.active,
.rig-consumption-create #rig-end-day-toggle.active {
    border-color: #032d5b;
    background: #043d7b;
    color: #fff;
    box-shadow: inset 0 2px 5px rgba(0, 0, 0, .25);
}

.rig-consumption-create #rig-end-day-toggle:focus-visible {
    outline: 2px solid #23527c;
    outline-offset: 2px;
}

.rig-consumption-create .alert {
    margin-bottom: 15px;
    border-radius: 5px;
    font-size: 13px;
    overflow-wrap: anywhere;
}

.rig-consumption-create .help-block {
    margin: 5px 0 0;
    font-size: 12px;
    text-align: right;
}

.rig-consumption-create .has-error .help-block {
    color: #a94442;
}

.rig-consumption-create .text-muted {
    font-size: 12px;
    line-height: 1.9;
}

.rig-consumption-create .form-actions {
    margin-top: 25px;
    text-align: left;
}

.rig-consumption-create .btn-submit {
    min-width: 150px;
    padding: 8px 22px;
    font-size: 14px;
    font-weight: 700;
}

.rig-consumption-create .btn-submit:disabled {
    cursor: not-allowed;
}

.rig-consumption-create #rig-calculation-status {
    line-height: 1.9;
}

.rig-consumption-create #rig-calculation-errors {
    margin-top: 12px;
}

.rig-consumption-create #rig-calculation-errors ul {
    margin-bottom: 0;
    padding-right: 20px;
    padding-left: 0;
}

.rig-consumption-create [hidden] {
    display: none !important;
}

@media (max-width: 991px) {
    .rig-consumption-create .balance-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767px) {
    .rig-consumption-create {
        padding: 10px;
    }

    .rig-consumption-create .panel-body {
        padding-right: 12px;
        padding-left: 12px;
    }

    .rig-consumption-create .section-title {
        right: 12px;
        max-width: calc(100% - 24px);
        font-size: 14px;
    }

    .rig-consumption-create .table-responsive {
        border: 0;
    }

    .rig-consumption-create .balance-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .rig-consumption-create .rig-end-day-button,
    .rig-consumption-create #rig-end-day-toggle {
        padding-right: 8px;
        padding-left: 8px;
    }
}
CSS
);
?>

<div class="rig-consumption-create">
    <?= $this->render('_form', [
        'model' => $model,
        'formData' => $formData,
        'equipmentRows' => $equipmentRows,
        'summary' => $summary,
        'fuelSummary' => $fuelSummary,
        'waterSummary' => $waterSummary,
    ]) ?>
</div>