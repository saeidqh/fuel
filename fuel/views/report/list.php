<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\modules\fuel\models\FuelReportSearch;
use app\modules\fuel\models\RigType;
use app\modules\fuel\models\VesselType;
use kartik\select2\Select2;

/* @var $this yii\web\View */
/* @var $searchModel app\modules\fuel\models\FuelReportSearch */
/* @var $reportData array */

$this->title = 'داشبورد مصرف سوخت';
$this->params['breadcrumbs'][] = $this->title;

$hasData = !empty($reportData) && !empty($reportData['summary']);

$summary = $hasData ? $reportData['summary'] : [
    'reports_count' => 0,
    'report_days' => 0,
    'total_operating_hours' => 0,
    'actual_consumption' => 0,
    'ideal_consumption' => 0,
    'consumption_difference' => 0,
    'deviation_percent' => 0,
    'fuel_received' => 0,
    'fuel_transferred' => 0,
    'fuel_used' => 0,
];

$dailyConsumption = $hasData ? ($reportData['daily_consumption'] ?? []) : [];
$dailyDifference = $hasData ? ($reportData['daily_difference'] ?? []) : [];
$dailyOperatingHours = $hasData ? ($reportData['daily_operating_hours'] ?? []) : [];

// آماده‌سازی داده نمودارها
$chartLabels = [];
$actualData = [];
$idealData = [];
$differenceData = [];
$hoursData = [];

foreach ($dailyConsumption as $row) {
    $chartLabels[] = $row['report_date'];
    $actualData[] = (float)$row['actual_consumption'];
    $idealData[] = (float)$row['ideal_consumption'];
    $differenceData[] = (float)$row['consumption_difference'];
}

foreach ($dailyOperatingHours as $row) {
    $hoursData[] = (float)$row['operating_hours'];
}

$chartLabelsJson = json_encode($chartLabels, JSON_UNESCAPED_UNICODE);
$actualDataJson = json_encode($actualData, JSON_UNESCAPED_UNICODE);
$idealDataJson = json_encode($idealData, JSON_UNESCAPED_UNICODE);
$differenceDataJson = json_encode($differenceData, JSON_UNESCAPED_UNICODE);
$hoursDataJson = json_encode($hoursData, JSON_UNESCAPED_UNICODE);
$rigOptionsJson = json_encode(RigType::getDropdownList(), JSON_UNESCAPED_UNICODE);
$vesselOptionsJson = json_encode(VesselType::getDropdownList(), JSON_UNESCAPED_UNICODE);

// رنگ وضعیت انحراف
$deviation = (float)$summary['deviation_percent'];
if ($deviation > 10) {
    $deviationClass = 'danger';
    $deviationText = 'انحراف بالا';
} elseif ($deviation > 5) {
    $deviationClass = 'warning';
    $deviationText = 'انحراف متوسط';
} elseif ($deviation < -5) {
    $deviationClass = 'info';
    $deviationText = 'مصرف کمتر از ایده‌آل';
} else {
    $deviationClass = 'success';
    $deviationText = 'وضعیت مطلوب';
}

$this->registerJsFile('@web/js/chart.min.js', [
    'position' => \yii\web\View::POS_END,
]);
?>

<style>
.dashboard-wrapper {
    padding: 15px 0 40px;
    direction: rtl;
    text-align: right;
    font-family: Tahoma, "Segoe UI", sans-serif;
}

.dashboard-hero {
    background: linear-gradient(135deg, #0f2c4c 0%, #1a5276 45%, #2980b9 100%);
    color: #fff;
    border-radius: 18px;
    padding: 28px 32px;
    margin-bottom: 24px;
    box-shadow: 0 12px 30px rgba(15, 44, 76, 0.22);
    position: relative;
    overflow: hidden;
}

.dashboard-hero:before {
    content: "";
    position: absolute;
    width: 200px;
    height: 200px;
    left: -50px;
    top: -70px;
    background: rgba(255,255,255,0.07);
    border-radius: 50%;
}

.dashboard-hero-title {
    margin: 0 0 8px;
    font-size: 28px;
    font-weight: bold;
}

.dashboard-hero-subtitle {
    margin: 0;
    font-size: 14px;
    line-height: 1.9;
    opacity: 0.95;
}

.hero-badge {
    display: inline-block;
    margin-top: 14px;
    padding: 7px 14px;
    border-radius: 30px;
    background: rgba(255,255,255,0.15);
    font-size: 13px;
    font-weight: bold;
}

.filter-panel,
.dashboard-panel {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.07);
    border: 1px solid #e8eef5;
    margin-bottom: 22px;
    overflow: hidden;
}

.filter-panel-header,
.panel-clean-header {
    padding: 16px 22px;
    border-bottom: 1px solid #eef2f7;
    background: #fff;
}

.filter-panel-title,
.panel-clean-title {
    margin: 0;
    font-size: 17px;
    font-weight: bold;
    color: #17324d;
}

.filter-panel-body,
.panel-clean-body {
    padding: 20px 22px;
}

.kpi-box {
    border-radius: 14px;
    padding: 16px 18px;
    color: #fff;
    margin-bottom: 18px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
    min-height: 110px;
    position: relative;
    overflow: hidden;
}

.kpi-box:before {
    content: "";
    position: absolute;
    left: -15px;
    top: -15px;
    width: 70px;
    height: 70px;
    background: rgba(255,255,255,0.12);
    border-radius: 50%;
}

.kpi-title {
    position: relative;
    z-index: 2;
    font-size: 12px;
    margin-bottom: 8px;
    opacity: 0.95;
}

.kpi-value {
    position: relative;
    z-index: 2;
    font-size: 26px;
    font-weight: bold;
    line-height: 1.2;
}

.kpi-subtitle {
    position: relative;
    z-index: 2;
    font-size: 11px;
    margin-top: 6px;
    opacity: 0.9;
}

.kpi-blue { background: linear-gradient(135deg, #1565c0, #42a5f5); }
.kpi-green { background: linear-gradient(135deg, #2e7d32, #66bb6a); }
.kpi-cyan { background: linear-gradient(135deg, #00838f, #26c6da); }
.kpi-orange { background: linear-gradient(135deg, #ef6c00, #ffa726); }
.kpi-red { background: linear-gradient(135deg, #c62828, #ef5350); }
.kpi-purple { background: linear-gradient(135deg, #6a1b9a, #ab47bc); }
.kpi-indigo { background: linear-gradient(135deg, #283593, #5c6bc0); }
.kpi-teal { background: linear-gradient(135deg, #00695c, #26a69a); }

.chart-container {
    position: relative;
    height: 340px;
}

.table thead tr th {
    background: #f5f8fc !important;
    color: #17324d;
    font-weight: bold;
    text-align: center;
    vertical-align: middle !important;
}

.table tbody tr td {
    vertical-align: middle !important;
    text-align: center;
}

.table tbody tr:hover {
    background: #f8fbff;
}

.diff-positive {
    color: #c62828;
    font-weight: bold;
}

.diff-negative {
    color: #2e7d32;
    font-weight: bold;
}

.diff-zero {
    color: #546e7a;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-badge.success { background: #e8f5e9; color: #2e7d32; }
.status-badge.warning { background: #fff8e1; color: #f57f17; }
.status-badge.danger { background: #ffebee; color: #c62828; }
.status-badge.info { background: #e3f2fd; color: #1565c0; }

.empty-state {
    text-align: center;
    color: #78909c;
    padding: 50px 15px;
    font-size: 14px;
}

.empty-state i {
    font-size: 42px;
    display: block;
    margin-bottom: 12px;
    opacity: 0.5;
}

.form-control {
    border-radius: 8px;
}

.btn {
    border-radius: 8px;
}

.control-label {
    font-weight: bold;
    color: #17324d;
}
</style>

<div class="dashboard-wrapper">

    <!-- Hero -->
    <div class="dashboard-hero">
        <div class="row">
            <div class="col-md-9">
                <h1 class="dashboard-hero-title"><?= Html::encode($this->title) ?></h1>
                <p class="dashboard-hero-subtitle">
                    پایش مصرف سوخت واقعی در برابر مصرف ایده‌آل دکل‌ها و شناورها.
                    با انتخاب نوع تجهیز، شناسه و بازه تاریخی، خلاصه عملکرد و روند روزانه را مشاهده کنید.
                </p>
                <?php if ($hasData): ?>
                    <div class="hero-badge">
                        وضعیت انحراف: <?= Html::encode($deviationText) ?>
                        (<?= number_format($deviation, 2) ?>%)
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-3 text-left" style="padding-top: 20px;">
                <?= Html::a('<i class="fa fa-refresh"></i> بازنشانی', ['list'], [
                    'class' => 'btn btn-warning btn-lg',
                ]) ?>
            </div>
        </div>
    </div>

    <!-- فیلتر -->
    <div class="filter-panel">
        <div class="filter-panel-header">
            <h3 class="filter-panel-title">فیلتر گزارش</h3>
        </div>
        <div class="filter-panel-body">
            <?php $form = ActiveForm::begin([
                'method' => 'get',
                'action' => ['list'],
            ]); ?>

            <div class="row">
                   <div class="col-md-3">
                    <?= $form->field($searchModel, 'date_to')->textInput([
                        'placeholder' => '1405/01/31',
                        'dir' => 'ltr',
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($searchModel, 'date_from')->textInput([
                        'placeholder' => '1405/01/01',
                        'dir' => 'ltr',
                    ]) ?>
                </div>


                <div class="col-md-3">
                    <?php

                    $equipmentOptions = [];
                    if ($searchModel->equipment_type === FuelReportSearch::TYPE_RIG) {
                        $equipmentOptions = RigType::getDropdownList();
                    } elseif ($searchModel->equipment_type === FuelReportSearch::TYPE_VESSEL) {
                        $equipmentOptions = VesselType::getDropdownList();
                    }
                    ?>

                    <label class="control-label"><?= $searchModel->getAttributeLabel('equipment_id') ?></label>
                    <?= Select2::widget([
                        'model' => $searchModel,
                        'attribute' => 'equipment_id',
                        'data' => $equipmentOptions,
                        'options' => [
                            'placeholder' => 'ابتدا نوع تجهیز را انتخاب کنید...',
                            'id' => 'equipment-id-select',
                        ],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'dir' => 'rtl',
                        ],
                    ]) ?>
                </div>
                 <div class="col-md-3">
                    <?= $form->field($searchModel, 'equipment_type')->dropDownList(
                        FuelReportSearch::equipmentTypeOptions(),
                        [
                            'prompt' => 'انتخاب نوع تجهیز',
                            'id' => 'equipment-type-select',   // مهم: این id را حتماً بگذار
                        ]
                    ) ?>
                </div>


            </div>

            <div class="form-group" style="margin-top: 8px;">
                <?= Html::submitButton('<i class="fa fa-filter"></i> نمایش گزارش', [
                    'class' => 'btn btn-primary',
                ]) ?>
                <?= Html::a('پاک کردن فیلترها', ['list'], [
                    'class' => 'btn btn-default',
                ]) ?>
            </div>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <?php if (!$hasData): ?>
        <div class="dashboard-panel">
            <div class="panel-clean-body">
                <div class="empty-state">
                    <i class="fa fa-bar-chart"></i>
                    برای مشاهده داشبورد، نوع تجهیز، شناسه و بازه تاریخی را انتخاب کنید و دکمه «نمایش گزارش» را بزنید.
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- KPI اصلی -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="kpi-box kpi-blue">
                    <div class="kpi-title">مصرف واقعی</div>
                    <div class="kpi-value"><?= number_format($summary['actual_consumption'], 2) ?></div>
                    <div class="kpi-subtitle">لیتر / واحد</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-box kpi-green">
                    <div class="kpi-title">مصرف ایده‌آل</div>
                    <div class="kpi-value"><?= number_format($summary['ideal_consumption'], 2) ?></div>
                    <div class="kpi-subtitle">بر اساس استاندارد</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-box kpi-orange">
                    <div class="kpi-title">اختلاف مصرف</div>
                    <div class="kpi-value"><?= number_format($summary['consumption_difference'], 2) ?></div>
                    <div class="kpi-subtitle">واقعی − ایده‌آل</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-box kpi-red">
                    <div class="kpi-title">درصد انحراف</div>
                    <div class="kpi-value"><?= number_format($summary['deviation_percent'], 2) ?>%</div>
                    <div class="kpi-subtitle">
                        <span class="status-badge <?= $deviationClass ?>" style="background:rgba(255,255,255,0.25);color:#fff;">
                            <?= Html::encode($deviationText) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI فرعی -->
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="kpi-box kpi-cyan">
                    <div class="kpi-title">ساعات کارکرد</div>
                    <div class="kpi-value"><?= number_format($summary['total_operating_hours'], 2) ?></div>
                    <div class="kpi-subtitle">ساعت</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-box kpi-indigo">
                    <div class="kpi-title">تعداد روز گزارش</div>
                    <div class="kpi-value"><?= (int)$summary['report_days'] ?></div>
                    <div class="kpi-subtitle">روز دارای داده</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-box kpi-teal">
                    <div class="kpi-title">سوخت دریافتی</div>
                    <div class="kpi-value"><?= number_format($summary['fuel_received'], 2) ?></div>
                    <div class="kpi-subtitle">در بازه انتخابی</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="kpi-box kpi-purple">
                    <div class="kpi-title">سوخت انتقالی</div>
                    <div class="kpi-value"><?= number_format($summary['fuel_transferred'], 2) ?></div>
                    <div class="kpi-subtitle">در بازه انتخابی</div>
                </div>
            </div>
        </div>

        <!-- نمودار مصرف واقعی در برابر ایده‌آل -->
        <div class="dashboard-panel">
            <div class="panel-clean-header">
                <h3 class="panel-clean-title">روند مصرف واقعی در برابر مصرف ایده‌آل</h3>
            </div>
            <div class="panel-clean-body">
                <?php if (!empty($dailyConsumption)): ?>
                    <div class="chart-container">
                        <canvas id="consumptionChart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">داده‌ای برای نمودار مصرف وجود ندارد.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- نمودار اختلاف -->
            <div class="col-md-6">
                <div class="dashboard-panel">
                    <div class="panel-clean-header">
                        <h3 class="panel-clean-title">اختلاف مصرف روزانه</h3>
                    </div>
                    <div class="panel-clean-body">
                        <?php if (!empty($differenceData)): ?>
                            <div class="chart-container">
                                <canvas id="differenceChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">داده‌ای برای نمودار اختلاف وجود ندارد.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- نمودار ساعات کارکرد -->
            <div class="col-md-6">
                <div class="dashboard-panel">
                    <div class="panel-clean-header">
                        <h3 class="panel-clean-title">ساعات کارکرد روزانه</h3>
                    </div>
                    <div class="panel-clean-body">
                        <?php if (!empty($hoursData)): ?>
                            <div class="chart-container">
                                <canvas id="hoursChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">داده‌ای برای نمودار ساعات وجود ندارد.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول روزانه -->
        <div class="dashboard-panel">
            <div class="panel-clean-header">
                <h3 class="panel-clean-title">جزئیات روزانه مصرف</h3>
            </div>
            <div class="panel-clean-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped">
                        <thead>
                            <tr>
                                <th style="width:60px;">ردیف</th>
                                <th>تاریخ</th>
                                <th>مصرف واقعی</th>
                                <th>مصرف ایده‌آل</th>
                                <th>اختلاف</th>
                                <th>ساعات کارکرد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($dailyConsumption)): ?>
                                <?php foreach ($dailyConsumption as $index => $row): ?>
                                    <?php
                                    $diff = (float)$row['consumption_difference'];
                                    $diffClass = $diff > 0 ? 'diff-positive' : ($diff < 0 ? 'diff-negative' : 'diff-zero');
                                    $hours = isset($dailyOperatingHours[$index])
                                        ? $dailyOperatingHours[$index]['operating_hours']
                                        : 0;
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= Html::encode($row['report_date']) ?></td>
                                        <td><?= number_format((float)$row['actual_consumption'], 2) ?></td>
                                        <td><?= number_format((float)$row['ideal_consumption'], 2) ?></td>
                                        <td class="<?= $diffClass ?>">
                                            <?= number_format($diff, 2) ?>
                                        </td>
                                        <td><?= number_format((float)$hours, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">داده‌ای یافت نشد.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
 </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
window.onload = function () {
    if (typeof Chart === 'undefined') {
        console.log('Chart.js بارگذاری نشده است.');
        return;
    }

    var labels = <?= $chartLabelsJson ?: '[]' ?>;
    var actualData = <?= $actualDataJson ?: '[]' ?>;
    var idealData = <?= $idealDataJson ?: '[]' ?>;
    var differenceData = <?= $differenceDataJson ?: '[]' ?>;
    var hoursData = <?= $hoursDataJson ?: '[]' ?>;

    // نمودار مصرف واقعی vs ایده‌آل
    var consumptionCanvas = document.getElementById('consumptionChart');
    if (consumptionCanvas && labels.length) {
        new Chart(consumptionCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'مصرف واقعی',
                        data: actualData,
                        borderColor: '#1565c0',
                        backgroundColor: 'rgba(21, 101, 192, 0.12)',
                        borderWidth: 2,
                        pointRadius: 3,
                        fill: true
                    },
                    {
                        label: 'مصرف ایده‌آل',
                        data: idealData,
                        borderColor: '#2e7d32',
                        backgroundColor: 'rgba(46, 125, 50, 0.08)',
                        borderWidth: 2,
                        pointRadius: 3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: true }
                    }]
                }
            }
        });
    }

    // نمودار اختلاف
    var differenceCanvas = document.getElementById('differenceChart');
    if (differenceCanvas && labels.length) {
        new Chart(differenceCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'اختلاف مصرف',
                    data: differenceData,
                    backgroundColor: differenceData.map(function (v) {
                        return v > 0 ? 'rgba(198, 40, 40, 0.75)' : 'rgba(46, 125, 50, 0.75)';
                    }),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: true }
                    }]
                }
            }
        });
    }

    // نمودار ساعات کارکرد
    var hoursCanvas = document.getElementById('hoursChart');
    if (hoursCanvas && labels.length) {
        new Chart(hoursCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ساعات کارکرد',
                    data: hoursData,
                    borderColor: '#00838f',
                    backgroundColor: 'rgba(0, 131, 143, 0.12)',
                    borderWidth: 2,
                    pointRadius: 3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: true }
                    }]
                }
            }
        });
    }
};
$(document).ready(function () {
    var rigOptions = <?= $rigOptionsJson ?>;
    var vesselOptions = <?= $vesselOptionsJson ?>;

    function fillEquipmentSelect(type) {
        var $select = $('#equipment-id-select');
        var data = {};

        if (type === 'rig') {
            data = rigOptions;
        } else if (type === 'vessel') {
            data = vesselOptions;
        }

        // پاک کردن گزینه‌های قبلی
        $select.empty();

        // گزینه خالی
        $select.append(new Option('', '', false, false));

        // پر کردن لیست جدید
        $.each(data, function (id, label) {
            $select.append(new Option(label, id, false, false));
        });

        // ریست کردن انتخاب
        $select.val(null).trigger('change');
    }

    // وقتی نوع تجهیز عوض شد
    $('#equipment-type-select').on('change', function () {
        fillEquipmentSelect($(this).val());
    });
});
</script>