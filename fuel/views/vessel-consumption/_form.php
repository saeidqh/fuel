<?php
use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$hasVessel = is_scalar($report->vesselIdRef)
    && preg_match('/\A[1-9]\d*\z/', (string) $report->vesselIdRef);

$css = <<<'CSS'
.vessel-consumption-form{direction:rtl;padding:10px 15px 25px}
.vessel-consumption-form .vc-box{position:relative;min-width:0;margin:30px 0 0;padding:25px 20px 10px;border:2px solid #337ab7;border-radius:8px;background:#fff}
.vessel-consumption-form .vc-box legend{position:absolute;top:-18px;right:20px;z-index:1;width:auto;margin:0;padding:4px 12px;border:2px solid #337ab7;border-radius:8px;background:#fff2b3;color:#0b58a2;font-size:15px;font-weight:700;line-height:1.4}
.vessel-consumption-form .form-group{margin-bottom:15px}
.vessel-consumption-form .control-label{font-size:13px;font-weight:700;color:#444}
.vessel-consumption-form .form-control{height:34px;border-radius:4px;font-size:13px}
.vessel-consumption-form select.form-control{padding:6px 10px}
.vessel-consumption-form textarea.form-control{height:auto;resize:vertical}
.vessel-consumption-form .table{margin-bottom:10px;font-size:13px}
.vessel-consumption-form .vc-table>thead>tr>th{border-bottom-width:1px;background:#edf5fc;color:#23527c;text-align:center;vertical-align:middle;white-space:nowrap}
.vessel-consumption-form .vc-table>tbody>tr>td{vertical-align:middle;text-align:center}
.vessel-consumption-form .vc-table .form-control{min-width:110px}
.vessel-consumption-form .vc-note{margin-top:6px;color:#64748b;font-size:12px;line-height:1.8}
.vessel-consumption-form .vc-transfer{margin-bottom:15px;padding:15px;border:1px solid #d9e6f2;border-radius:6px;background:#f8fbfe}
.vessel-consumption-form .vc-transfer .checkbox{margin-top:0;margin-bottom:15px;color:#23527c}
.vessel-consumption-form .alert{margin-bottom:15px;border-radius:5px;font-size:13px}
.vessel-consumption-form .help-block{margin:5px 0 0;font-size:12px;text-align:right}
.vessel-consumption-form .vc-actions{margin-top:25px;text-align:left}
.vessel-consumption-form .btn-submit{min-width:150px;padding:8px 22px;font-size:14px;font-weight:700}
.vessel-consumption-form .loading-equipment{padding:18px;color:#31708f;text-align:center}
@media(max-width:767px){
.vessel-consumption-form{padding:10px}
.vessel-consumption-form .vc-box{padding-right:12px;padding-left:12px}
.vessel-consumption-form .vc-box legend{right:12px}
.vessel-consumption-form .table-responsive{border:0}
}
CSS;
$this->registerCss($css);

$form = ActiveForm::begin([
    'id' => 'vessel-consumption-form',
    'action' => $report->isNewRecord ? ['create'] : ['update', 'id' => $report->id],
    'method' => 'post',
    'enableClientValidation' => false,
    'enableAjaxValidation' => false,
    'options' => [
        'class' => 'vessel-consumption-form',
        'autocomplete' => 'off',
    ],
]);
?>
<?= $form->errorSummary($report, [
    'class' => 'alert alert-danger',
    'header' => '<strong>لطفاً موارد زیر را اصلاح کنید:</strong>',
    'encode' => true,
]) ?>
<?= Html::hiddenInput('_items_present', $hasVessel ? '1' : '0', [
    'id' => 'vessel-items-present',
]) ?>
<?= Html::hiddenInput('_transactions_present', '1') ?>

<?= $this->render('_basic', [
    'form' => $form,
    'report' => $report,
    'vessels' => $vessels,
    'locations' => $locations,
    'activities' => $activities,
]) ?>

<div id="vessel-equipment-container" aria-live="polite">
    <?php if ($hasVessel): ?>
        <?= $this->render('_equipment', [
            'itemRows' => $itemRows,
            'modeOptions' => $modeOptions,
        ]) ?>
        <?= $this->render('_extra', ['itemRows' => $itemRows]) ?>
    <?php else: ?>
        <fieldset class="vc-box">
            <legend>اطلاعات تجهیزات شناور</legend>
            <div class="alert alert-info">
                ابتدا شناور را انتخاب کنید تا موتور، ژنراتور و تجهیزات جانبی آن نمایش داده شوند.
            </div>
        </fieldset>
    <?php endif; ?>
</div>

<?= $this->render('_transfer', [
    'form' => $form,
    'report' => $report,
    'transactionRows' => $transactionRows,
]) ?>
<?= $this->render('_summary', ['report' => $report]) ?>

<div class="vc-actions">
    <?= Html::submitButton(
        $report->isNewRecord ? 'ثبت گزارش مصرف' : 'ذخیره تغییرات',
        [
            'id' => 'vessel-submit',
            'class' => 'btn btn-success btn-submit',
            'disabled' => !$hasVessel,
        ]
    ) ?>
    <?php if (!$report->isNewRecord): ?>
        <?= Html::a('انصراف', ['view', 'id' => $report->id], [
            'class' => 'btn btn-default',
        ]) ?>
    <?php endif; ?>
</div>

<?php ActiveForm::end(); ?>

<?php
$calcJs = <<<JS
(function ($) {
    var rateUrl = '/cando/web/index.php?r=fuel/vessel-consumption/rate';

    // محاسبه خودکار زمان کارکرد از روی ساعت شروع و پایان
    function calculateHoursFromTime() {
        var startTime = $('input[name*="[start_time]"]').val();
        var endTime = $('input[name*="[end_time]"]').val();

        if (startTime && endTime) {
            var start = startTime.split(':');
            var end = endTime.split(':');

            if (start.length >= 2 && end.length >= 2) {
                var startDate = new Date(0, 0, 0, parseInt(start[0], 10), parseInt(start[1], 10), 0);
                var endDate = new Date(0, 0, 0, parseInt(end[0], 10), parseInt(end[1], 10), 0);

                var diffMs = endDate.getTime() - startDate.getTime();

                // در صورتی که ساعت پایان کوچکتر باشد (عبور از نیمه شب)
                if (diffMs < 0) {
                    diffMs += 24 * 60 * 60 * 1000;
                }

                var totalMinutes = Math.round(diffMs / (1000 * 60));
                var totalHours = (diffMs / (1000 * 60 * 60)).toFixed(2);

                // مقداردهی به فیلد مجموع کارکرد
                var opHoursInput = $('input[name*="[operating_hours]"]');
                if (opHoursInput.length) {
                    opHoursInput.val(totalHours);
                }

                // مقداردهی به فیلدهای دقیقه یا کارکرد تجهیزات (جدول تجهیزات)
                $('.equipment-minutes-input').each(function () {
                    $(this).val(totalMinutes).trigger('change');
                });
            }
        }
    }

    function calculateRow(row) {
        // تجهیز جانبی نیازی به دریافت نرخ از سرور ندارد
        if (row.hasClass('equipment-extra-row')) {
            triggerFullCalculation();
            return;
        }

        var modeSelect = row.find('.equipment-mode-select');
        var minutes = parseFloat(row.find('.equipment-minutes-input').val()) || 0;
        var actualInput = row.find('.equipment-actual-consumption-input');

        if (modeSelect.length === 0) {
            return;
        }

        var mode = modeSelect.val();

        if (!mode) {
            actualInput.val('0.00');
            triggerFullCalculation();
            return;
        }

        var vesselId = $('#vessel-consumption-form').find('[name*="[vesselIdRef]"]').val();
        var engineId = row.find('input[name*="[engine_id]"]').val();
        var generatorId = row.find('input[name*="[generator_id]"]').val();

        $.ajax({
            url: rateUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                vessel_id: vesselId,
                engine_id: engineId,
                generator_id: generatorId,
                operation_mode: mode
            }
        }).done(function (response) {
            var rate = response.success ? parseFloat(response.rate) || 0 : 0;
            var consumption = (minutes / 60) * rate;
            actualInput.val(consumption.toFixed(2));
            triggerFullCalculation();
        }).fail(function () {
            actualInput.val('0.00');
            triggerFullCalculation();
        });
    }

    function triggerFullCalculation() {
        var form = $('#vessel-consumption-form');
        var calcUrl = '/cando/web/index.php?r=fuel/vessel-consumption/calculate';
        
        $.ajax({
            url: calcUrl,
            type: 'POST',
            dataType: 'json',
            data: form.serialize()
        }).done(function(res) {
            if (res.success && res.fuelSummary) {
                if ($('#fuel-total-consumption').length) {
                    $('#fuel-total-consumption').text(res.fuelSummary.total_consumption);
                }
                if ($('#fuel-closing-balance').length) {
                    $('#fuel-closing-balance').text(res.fuelSummary.closing_balance);
                }
            }
        });
    }

    // شنود تغییرات ساعت شروع و پایان
    $(document).on('change input', 'input[name*="[start_time]"], input[name*="[end_time]"]', function () {
        calculateHoursFromTime();
    });

    // شنود تغییرات در موتورها و ژنراتورها
    $(document).on('change', '.equipment-mode-select', function () {
        var row = $(this).closest('tr');
        calculateRow(row);
    });

    $(document).on('input change', '.equipment-minutes-input', function () {
        var row = $(this).closest('tr');
        if (!row.hasClass('equipment-extra-row')) {
            clearTimeout(row.data('calcTimer'));
            row.data('calcTimer', setTimeout(function () {
                calculateRow(row);
            }, 300));
        } else {
            triggerFullCalculation();
        }
    });

    // شنود تغییر مصرف دستی در تجهیزات جانبی
    $(document).on('input change', '.extra-actual-consumption', function () {
        triggerFullCalculation();
    });

    // محاسبه اولیه در هنگام بارگذاری
    $('.vessel-equipment-table tbody tr.equipment-row').each(function () {
        if (!$(this).hasClass('equipment-extra-row')) {
            calculateRow($(this));
        }
    });

})(jQuery);
JS;
$this->registerJs($calcJs);
?>