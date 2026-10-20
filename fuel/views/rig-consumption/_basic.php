<?php

use yii\helpers\Html;

/** @var yii\widgets\ActiveForm $form */
/** @var app\modules\fuel\models\RigFuelConsumption $model */
/** @var array $formData */

// تابع تبدیل تاریخ میلادی به شمسی بدون وابستگی به کامپوننت سرور
function getTodayJalali() {
    $g_y = (int)date('Y');
    $g_m = (int)date('n');
    $g_d = (int)date('j');

    $g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    if (($g_y % 4 == 0 && $g_y % 100 != 0) || ($g_y % 400 == 0)) {
        $g_days_in_month[1] = 29;
    }

    $gy = $g_y - 1600;
    $gm = $g_m - 1;
    $gd = $g_d - 1;

    $g_day_no = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);
    for ($i = 0; $i < $gm; ++$i) {
        $g_day_no += $g_days_in_month[$i];
    }
    $g_day_no += $gd;

    $j_day_no = $g_day_no - 79;
    $j_np = (int)($j_day_no / 12053);
    $j_day_no %= 12053;

    $jy = 979 + 33 * $j_np + 4 * (int)($j_day_no / 1461);
    $j_day_no %= 1461;

    if ($j_day_no >= 366) {
        $jy += (int)(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }

    for ($i = 0; $i < 11 && $j_day_no >= ($i < 6 ? 31 : 30); ++$i) {
        $j_day_no -= ($i < 6 ? 31 : 30);
    }
    $jm = $i + 1;
    $jd = $j_day_no + 1;

    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}

// مقداردهی تاریخ امروز شمسی در صورت جدید بودن فرم
$defaultJalaliDate = $model->isNewRecord && empty($model->jalaliDate)
    ? getTodayJalali()
    : $model->jalaliDate;

$isEndOfDay = (string) $model->endOfDay === '1'
    || in_array($model->end_time, ['24:00', '24:00:00'], true);

$endDisplay = Html::textInput(null, '۲۴:۰۰ ـ پایان روز', [
    'id' => 'rig-end-day-display',
    'class' => 'form-control rig-end-day-display',
    'readonly' => true,
    'tabindex' => -1,
    'aria-label' => 'ساعت پایان: ۲۴:۰۰، پایان روز',
    'style' => $isEndOfDay ? '' : 'display:none;',
]);

$endButton = Html::button(
    $isEndOfDay ? '✓ پایان روز' : 'پایان روز',
    [
        'id' => 'rig-end-day-toggle',
        'type' => 'button',
        'class' => 'btn ' . ($isEndOfDay ? 'btn-primary' : 'btn-default'),
        'aria-pressed' => $isEndOfDay ? 'true' : 'false',
        'aria-controls' => 'rig-end-time rig-end-day-display',
        'aria-describedby' => 'rig-end-time-help',
    ]
);

$this->registerCss(<<<'CSS'
.rig-consumption-form .rig-time-group {
    display: flex;
    align-items: stretch;
    direction: ltr;
}

.rig-consumption-form .rig-time-group > .form-control {
    float: none;
    flex: 1 1 0;
    min-width: 0;
    width: 1%;
    height: 36px;
    border-radius: 5px 0 0 5px;
}

.rig-consumption-form .rig-time-group > .form-control:focus {
    position: relative;
    z-index: 2;
}

.rig-consumption-form .rig-time-group > .btn {
    flex: 0 0 auto;
    height: 36px;
    margin-left: -1px;
    border-radius: 0 5px 5px 0;
    direction: rtl;
}

.rig-consumption-form .rig-time-group > .rig-end-day-display {
    background: #edf5ff;
    color: #23527c;
    text-align: center;
    direction: rtl;
    font-weight: 600;
}

.rig-consumption-form .rig-time-help {
    margin-top: 6px;
    color: #d9534f;
    font-size: 12px;
    line-height: 1.8;
}

.rig-consumption-form #rig-end-day-toggle {
    background: #1565c0;
    border-color: #1565c0;
    color: #fff;
    font-weight: 700;
}

.rig-consumption-form #rig-end-day-toggle:hover {
    background: #0d47a1;
    border-color: #0d47a1;
}

.rig-consumption-form #rig-end-day-toggle[aria-pressed="true"] {
    background: #08357a;
    border-color: #06285c;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, .2);
}

.rig-consumption-form #rig-end-day-toggle:focus {
    outline: 3px solid #90caf9;
    outline-offset: 2px;
}

.rig-consumption-form #rig-start-time {
    height: 36px;
}

.rig-consumption-form .rig-time-note {
    color: #d9534f;
    font-size: 12px;
    line-height: 1.9;
}
CSS
);
?>

<div class="panel panel-primary">
    <div class="panel-body">
        <h4 class="section-title">اطلاعات پایه مصرف دکل</h4>

        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'jalaliDate')->textInput([
                    'value' => $defaultJalaliDate,
                    'placeholder' => '1403/07/14',
                    'maxlength' => 10,
                    'autocomplete' => 'off',
                    'dir' => 'ltr',
                ])->hint('تاریخ شمسی گزارش را وارد کنید.') ?>
            </div>

            <div class="col-md-4">
                <?= $form->field($model, 'activityIdRef')->dropDownList(
                    $formData['activities'],
                    ['prompt' => 'فعالیت را انتخاب کنید']
                ) ?>
            </div>

            <div class="col-md-4">
                <?= $form->field($model, 'rigIdRef')->dropDownList(
                    $formData['rigs'],
                    [
                        'prompt' => 'دکل را انتخاب کنید',
                        'id' => 'rig-selector',
                    ]
                ) ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <?= $form->field($model, 'locationIdRef')->dropDownList(
                    $formData['locations'],
                    ['prompt' => 'لوکیشن را انتخاب کنید']
                ) ?>
            </div>

            <div class="col-md-4">
                <?= Html::activeHiddenInput($model, 'endOfDay', [
                    'id' => 'rig-end-day',
                    'value' => $isEndOfDay ? '1' : '0',
                ]) ?>

                <?= $form->field($model, 'end_time', [
                    'template' => "{label}\n"
                        . '<div class="rig-time-group">{input}'
                        . $endDisplay
                        . $endButton
                        . '</div>'
                        . "\n{error}\n{hint}",
                ])->input('time', [
                    'id' => 'rig-end-time',
                    'step' => 60,
                    'min' => '00:00',
                    'max' => '23:59',
                    'dir' => 'ltr',
                    'value' => $isEndOfDay ? '' : $model->end_time,
                    'disabled' => $isEndOfDay,
                    'style' => $isEndOfDay ? 'display:none;' : '',
                    'aria-describedby' => 'rig-end-time-help',
                ])->hint(
                    'برای ساعت ۲۴:۰۰، «پایان روز» را بزنید؛ با کلیک دوباره به انتخاب ساعت برگردید.',
                    [
                        'id' => 'rig-end-time-help',
                        'class' => 'rig-time-help',
                    ]
                ) ?>

                <?= Html::error($model, 'endOfDay', [
                    'class' => 'help-block text-danger',
                ]) ?>
            </div>

            <div class="col-md-4">
                <?= $form->field($model, 'start_time')->input('time', [
                    'id' => 'rig-start-time',
                    'step' => 60,
                    'min' => '00:00',
                    'max' => '23:59',
                    'dir' => 'ltr',
                ]) ?>
            </div>
        </div>

        <?= $form->field($model, 'description')->textarea([
            'rows' => 3,
            'maxlength' => 10000,
            'placeholder' => 'توضیحات تکمیلی فعالیت، شیفت یا شرایط انجام کار',
        ]) ?>

        <p class="rig-time-note">
            هر گزارش مربوط به یک تاریخ است.
            فعالیت عبوری از نیمه‌شب را در دو گزارش ثبت کنید:
            گزارش اول تا «پایان روز» و گزارش دوم از ساعت ۰۰:۰۰ روز بعد.
            ثبت زمان آینده مجاز نیست.
        </p>
    </div>
</div>