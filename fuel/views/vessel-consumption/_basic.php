<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use app\modules\fuel\models\VesselFuelConsumption;
use app\modules\fuel\models\LocationType;

/* دریافت لیست لوکیشن‌ها به همراه مختصات جغرافیایی جهت محاسبه مسافت */
$locationData = LocationType::getLocationListWithCoords();
$locationCoordsJson = Json::encode($locationData['coords']);

$vesselSelector = Json::htmlEncode('#' . Html::getInputId($report, 'vesselIdRef'));
$statusSelector = Json::htmlEncode('#' . Html::getInputId($report, 'status'));
$endSelector = Json::htmlEncode('#' . Html::getInputId($report, 'end_time'));
$endOfDaySelector = Json::htmlEncode('#' . Html::getInputId($report, 'endOfDay'));
$equipmentUrl = Json::htmlEncode(Url::to(['/fuel/vessel-consumption/equipment']));
$isNewRecord = $report->isNewRecord ? 'true' : 'false';
$isEndOfDay = (string) $report->endOfDay === '1' || $report->end_time === '24:00';

$endDisplay = Html::textInput(null, '۲۴:۰۰ ـ پایان روز', [
    'id' => 'vc-end-day-display',
    'class' => 'form-control vc-end-day-display',
    'readonly' => true,
    'tabindex' => -1,
    'aria-label' => 'ساعت پایان: ۲۴:۰۰، پایان روز',
    'style' => $isEndOfDay ? '' : 'display:none;',
]);

$endButton = Html::button($isEndOfDay ? '✓ پایان روز' : 'پایان روز', [
    'id' => 'vc-end-day-toggle',
    'class' => 'btn ' . ($isEndOfDay ? 'btn-primary' : 'btn-default'),
    'aria-pressed' => $isEndOfDay ? 'true' : 'false',
    'aria-controls' => Html::getInputId($report, 'end_time') . ' vc-end-day-display',
    'aria-describedby' => 'vc-end-time-help',
]);

$this->registerCss(<<<'CSS'
.vessel-consumption-form .vc-time-group{display:flex;align-items:stretch;direction:ltr}
.vessel-consumption-form .vc-time-group>.form-control{float:none;flex:1 1 0;min-width:0;width:1%;height:36px;border-radius:5px 0 0 5px}
.vessel-consumption-form .vc-time-group>.form-control:focus{position:relative;z-index:2}
.vessel-consumption-form .vc-time-group>.btn{flex:0 0 auto;height:36px;margin-left:-1px;border-radius:0 5px 5px 0;direction:rtl}
.vessel-consumption-form .vc-time-group>.vc-end-day-display{background:#edf5ff;color:#23527c;text-align:center;direction:rtl;font-weight:600}
.vessel-consumption-form .vc-time-help{margin-top:6px;color:#64748b;font-size:12px;line-height:1.8}
.vessel-consumption-form #vc-end-day-toggle{background:#1565c0;border-color:#1565c0;color:#fff;font-weight:700;}
.vessel-consumption-form #vc-end-day-toggle:hover{background:#0d47a1;border-color:#0d47a1;}
.vessel-consumption-form #vc-end-day-toggle[aria-pressed="true"]{background:#08357a;border-color:#06285c;box-shadow:inset 0 2px 4px rgba(0,0,0,.2);}
.vessel-consumption-form #vc-end-day-toggle:focus{outline:3px solid #90caf9;outline-offset:2px;}
.vessel-consumption-form .vc-distance-box{background:#eef6ff;font-weight:bold;color:#0d47a1;}
CSS
);
?>

<fieldset class="vc-box">
    <legend>اطلاعات پایه مصرف و مسیر حرکت شناور</legend>

    <!-- ردیف اول: تاریخ، فعالیت، وضعیت، شناور -->
    <div class="row">
        <div class="col-md-3">
            <?= $form->field($report, 'jalaliDate')->textInput([
                'placeholder' => '1405/06/30',
                'maxlength' => 10,
                'autocomplete' => 'off',
                'dir' => 'ltr',
            ])->hint('تاریخ شمسی گزارش را وارد کنید.') ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($report, 'activityIdRef')->dropDownList(
                $activities,
                ['prompt' => 'فعالیت را انتخاب کنید']
            ) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($report, 'status')->dropDownList(
                VesselFuelConsumption::getStatusOptions(),
                ['prompt' => 'وضعیت را انتخاب کنید']
            ) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($report, 'vesselIdRef')->dropDownList($vessels, [
                'prompt' => 'شناور را انتخاب کنید',
                'disabled' => !$report->isNewRecord,
            ]) ?>
            <?php if (!$report->isNewRecord): ?>
                <?= Html::hiddenInput(
                    Html::getInputName($report, 'vesselIdRef'),
                    $report->vesselIdRef
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ردیف دوم: مبدأ، مقصد، لوکیشن، مسافت -->
    <div class="row">
        <div class="col-md-3">
            <?= $form->field($report, 'destination_from')->dropDownList(
                $locationData['list'],
                ['prompt' => 'انتخاب مبدأ حرکت', 'id' => 'vessel-from-location']
            )->label('مبدأ حرکت جغرافیایی') ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($report, 'destination_to')->dropDownList(
                $locationData['list'],
                ['prompt' => 'انتخاب مقصد حرکت', 'id' => 'vessel-to-location']
            )->label('مقصد حرکت جغرافیایی') ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($report, 'locationIdRef')->dropDownList(
                $locationData['list'],
                ['prompt' => 'لوکیشن فعلی استقرار']
            ) ?>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="control-label">مسافت محاسبه‌شده (مایل دریایی)</label>
                <?= Html::activeTextInput($report, 'distance_nautical_miles', [
                    'id' => 'vessel-distance-field',
                    'class' => 'form-control vc-distance-box',
                    'readonly' => true,
                    'dir' => 'ltr',
                    'value' => Html::encode($report->distance_nautical_miles ?? '0.00'),
                ]) ?>
            </div>
        </div>
    </div>

    <!-- ردیف سوم: زمان پایان (با پایان روز)، زمان شروع -->
    <div class="row">
        <div class="col-md-3">
            <?= Html::activeHiddenInput($report, 'endOfDay', [
                'value' => $isEndOfDay ? '1' : '0',
            ]) ?>

            <?= $form->field($report, 'end_time', [
                'template' => "{label}\n"
                    . '<div class="vc-time-group">{input}'
                    . $endDisplay . $endButton . '</div>'
                    . "\n{error}\n{hint}",
            ])->input('time', [
                'step' => 60,
                'min' => '00:00',
                'max' => '23:59',
                'dir' => 'ltr',
                'value' => $isEndOfDay ? '' : $report->end_time,
                'disabled' => $isEndOfDay,
                'style' => $isEndOfDay ? 'display:none;' : '',
                'aria-describedby' => 'vc-end-time-help',
            ])->hint(
                'برای ساعت ۲۴:۰۰، «پایان روز» را بزنید؛ با کلیک دوباره به انتخاب ساعت برگردید.',
                ['id' => 'vc-end-time-help', 'class' => 'vc-time-help', 'style' => 'color:#d9534f;']
            ) ?>
            <?= Html::error($report, 'endOfDay', ['class' => 'help-block']) ?>
        </div>
        <div class="col-md-3">
            <?= $form->field($report, 'start_time')->input('time', [
                'step' => 60,
                'min' => '00:00',
                'max' => '23:59',
                'dir' => 'ltr',
            ]) ?>
        </div>
    </div>

    <?= $form->field($report, 'description')->textarea([
        'rows' => 3,
        'maxlength' => 5000,
        'placeholder' => 'توضیحات تکمیلی فعالیت، شیفت یا شرایط انجام کار',
    ]) ?>

    <p class="vc-note" style="color:#d9534f;">
        هر گزارش مربوط به یک تاریخ است.
        فعالیت عبوری از نیمه‌شب را در دو گزارش ثبت کنید:
        گزارش اول تا «پایان روز» و گزارش دوم از ساعت ۰۰:۰۰ روز بعد.
        ثبت زمان آینده مجاز نیست.
        وضعیت Off Hire مانع ثبت مصرف واقعی نیست.
    </p>
</fieldset>

<?php
$js = "
(function ($) {
    var form = $('#vessel-consumption-form');
    var endInput = $(" . $endSelector . ");
    var endOfDay = $(" . $endOfDaySelector . ");
    var endDisplay = $('#vc-end-day-display');
    var endButton = $('#vc-end-day-toggle');
    var previousEndTime = endInput.val() || '';

    function syncEndTime() {
        var active = endOfDay.val() === '1';

        if (active) {
            if (endInput.val()) {
                previousEndTime = endInput.val();
            }
            endInput.val('');
        } else if (!endInput.val()) {
            endInput.val(previousEndTime);
        }

        endInput.prop('disabled', active).toggle(!active);
        endDisplay.toggle(active);

        endButton
            .toggleClass('btn-primary', active)
            .toggleClass('btn-default', !active)
            .attr('aria-pressed', active ? 'true' : 'false')
            .text(active ? '✓ پایان روز' : 'پایان روز');
    }

    endButton
        .off('click.vesselEndOfDay')
        .on('click.vesselEndOfDay', function () {
            endOfDay.val(endOfDay.val() === '1' ? '0' : '1');
            syncEndTime();
            endInput.trigger('change');

            if (endOfDay.val() !== '1') {
                endInput.trigger('focus');
            }
        });

    syncEndTime();

    if (!" . $isNewRecord . ") {
        return;
    }

    var selector = " . $vesselSelector . ";
    var statusSelector = " . $statusSelector . ";
    var container = $('#vessel-equipment-container');
    var marker = $('#vessel-items-present');
    var submit = $('#vessel-submit');
    var request = null;
    var sequence = 0;
    var previousId = $(selector).val() || '';
    var dirty = form.find('.alert-danger').length > 0;

    function setReady(ready) {
        marker.val(ready ? '1' : '0');
        submit.prop('disabled', !ready);
        form.trigger('vesselEquipmentUpdated');
    }

    function showMessage(type, message) {
        var box = $('<fieldset>', {'class': 'vc-box'});
        $('<legend>').text('اطلاعات تجهیزات شناور').appendTo(box);
        $('<div>', {'class': 'alert alert-' + type}).text(message).appendTo(box);
        container.empty().append(box);
    }

    $(document)
        .off('input.vesselEquipment change.vesselEquipment', '#vessel-equipment-container :input')
        .on('input.vesselEquipment change.vesselEquipment', '#vessel-equipment-container :input', function () {
            dirty = true;
        });

    $(document)
        .off('change.vesselSelector', selector)
        .on('change.vesselSelector', selector, function () {
            var vesselId = $(this).val() || '';

            if (vesselId === previousId) {
                return;
            }

            previousId = vesselId;
            dirty = false;
            var currentSequence = ++sequence;

            if (request) {
                request.abort();
                request = null;
            }

            setReady(false);
            $(statusSelector).val('');

            if (!vesselId) {
                showMessage('info', 'ابتدا شناور را انتخاب کنید تا تجهیزات آن نمایش داده شوند.');
                return;
            }

            showMessage('info', 'در حال دریافت اطلاعات تجهیزات شناور...');

            request = $.ajax({
                url: " . $equipmentUrl . ",
                type: 'GET',
                dataType: 'json',
                data: {vesselId: vesselId}
            }).done(function (data) {
                if (currentSequence !== sequence) {
                    return;
                }

                if (!data || typeof data.html !== 'string') {
                    showMessage('danger', 'پاسخ تجهیزات معتبر نیست؛ شناور را دوباره انتخاب کنید.');
                    return;
                }

                container.html(data.html);
                $(statusSelector).val(data.status == null ? '' : data.status);
                setReady(true);
            }).fail(function (xhr, status) {
                if (currentSequence !== sequence || status === 'abort') {
                    return;
                }

                showMessage('danger', 'دریافت تجهیزات ناموفق بود؛ انتخاب شناور را خالی کرده و دوباره انتخاب کنید.');
            }).always(function () {
                if (currentSequence === sequence) {
                    request = null;
                }
            });
        });

    form
        .off('beforeSubmit.vesselEquipment')
        .on('beforeSubmit.vesselEquipment', function () {
            return marker.val() === '1';
        });

    /* بخش اسکریپت محاسبه هوشمند مسافت جغرافیایی بر حسب مایل دریایی */
    var coords = " . $locationCoordsJson . ";

    function calculateHaversineDistance(lat1, lon1, lat2, lon2) {
        if (!lat1 || !lon1 || !lat2 || !lon2) return 0;
        var R = 6371; // شعاع زمین به کیلومتر
        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLon = (lon2 - lon1) * Math.PI / 180;
        var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        var dKm = R * c;
        return (dKm / 1.852).toFixed(2); // تبدیل کیلومتر به مایل دریایی
    }

    function updateDistance() {
        var fromId = $('#vessel-from-location').val();
        var toId = $('#vessel-to-location').val();

        if (fromId && toId && coords[fromId] && coords[toId]) {
            var dist = calculateHaversineDistance(
                coords[fromId].lat, coords[fromId].lng,
                coords[toId].lat, coords[toId].lng
            );
            $('#vessel-distance-field').val(dist);
        } else {
            $('#vessel-distance-field').val('0.00');
        }
    }

    $(document).on('change', '#vessel-from-location, #vessel-to-location', updateDistance);

})(jQuery);
";
$this->registerJs($js);
?>