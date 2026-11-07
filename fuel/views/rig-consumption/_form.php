<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\modules\fuel\models\RigFuelConsumption $model */
/** @var array $formData */
/** @var array $equipmentRows */
/** @var array $summary */
/** @var array $fuelSummary */
/** @var array $waterSummary */

$equipmentUrl = Json::htmlEncode(
    Url::to(['/fuel/rig-consumption/equipment'])
);

$calculateUrl = Json::htmlEncode(
    Url::to(['/fuel/rig-consumption/calculate'])
);
?>

<div class="rig-consumption-form">
    <?php $form = ActiveForm::begin([
        'id' => 'rig-consumption-form',
        'action' => ['/fuel/rig-consumption/create'],
        'method' => 'post',
        'enableClientValidation' => false,
        'enableAjaxValidation' => false,
    ]); ?>

    <?= $form->errorSummary($model, [
        'class' => 'alert alert-danger',
        'encode' => true,
        'role' => 'alert',
    ]) ?>

    <?= $this->render('_basic', [
        'form' => $form,
        'model' => $model,
        'formData' => $formData,
    ]) ?>

    <div id="rig-equipment-container" aria-busy="false">
        <?= $this->render('_equipment', [
            'model' => $model,
            'equipmentRows' => $equipmentRows,
        ]) ?>
    </div>

    <?= $this->render('_extra', [
        'model' => $model,
    ]) ?>

    <?= $this->render('_fuel', [
        'model' => $model,
        'fuelSummary' => $fuelSummary,
        'waterSummary' => $waterSummary,
    ]) ?>

    <?= $this->render('_summary', [
        'summary' => $summary,
        'ready' => $model->getSummaryReady(),
    ]) ?>

    <div class="form-actions">
        <?= Html::submitButton('ثبت مصرف دکل', [
            'id' => 'rig-submit',
            'class' => 'btn btn-success btn-submit',
        ]) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(<<<JS
(function ($) {
    'use strict';

    var form = $('#rig-consumption-form');

    if (!form.length) {
        return;
    }

    var rigSelector = form.find('#rig-selector');
    var equipmentContainer = form.find('#rig-equipment-container');
    var submitButton = form.find('#rig-submit');
    var statusElement = form.find('#rig-calculation-status');
    var errorsElement = form.find('#rig-calculation-errors');

    var equipmentRequest = null;
    var calculationRequest = null;
    var equipmentVersion = 0;
    var calculationVersion = 0;
    var timer = null;

    var equipmentReady = !!rigSelector.val();
    var submitting = false;

    var initialEndTime = form.find('#rig-end-time').val();
    var oldEndTime = initialEndTime && initialEndTime !== '24:00'
        ? initialEndTime
        : '';

    statusElement.attr({
        role: 'status',
        'aria-live': 'polite',
        'aria-atomic': 'true'
    });

    errorsElement.attr({
        role: 'alert',
        'aria-live': 'polite'
    });

    form.off('.rigConsumption');
    $(window).off('pageshow.rigConsumption');

    // محاسبه خودکار ساعت کارکرد تجهیزات از روی زمان شروع و پایان
    function calculateHoursFromTime() {
        var startTimeInput = form.find('#rig-start-time');
        if (!startTimeInput.length) {
            startTimeInput = form.find('input[name*="[start_time]"]');
        }

        var endTimeInput = form.find('#rig-end-time');
        if (!endTimeInput.length) {
            endTimeInput = form.find('input[name*="[end_time]"]');
        }

        var startTime = startTimeInput.val();
        var isEndDay = String(form.find('#rig-end-day').val()) === '1';
        var endTime = isEndDay ? '24:00' : endTimeInput.val();

        if (startTime && endTime) {
            var start = startTime.split(':');
            var end = endTime.split(':');

            if (start.length >= 2 && end.length >= 2) {
                var startH = parseInt(start[0], 10);
                var startM = parseInt(start[1], 10);
                var endH = parseInt(end[0], 10);
                var endM = parseInt(end[1], 10);

                var startDate = new Date(0, 0, 0, startH, startM, 0);
                var endDate = new Date(0, 0, 0, endH, endM, 0);

                var diffMs = endDate.getTime() - startDate.getTime();
                if (diffMs < 0) {
                    diffMs += 24 * 60 * 60 * 1000;
                }

                var totalHours = (diffMs / (1000 * 60 * 60)).toFixed(2);

                var hourInputs = form.find('.equipment-hours-input');
                if (hourInputs.length) {
                    hourInputs.each(function () {
                        $(this).val(totalHours).trigger('change');
                    });
                }
            }
        }
    }

    function setVisible(element, visible) {
        element
            .prop('hidden', !visible)
            .toggle(visible);
    }

    function clearSummary(message) {
        form.find('[data-summary], [data-balance]')
            .text('—')
            .removeClass('text-danger text-success');

        statusElement.text(message || '');
        errorsElement.empty().prop('hidden', true);
    }

    function cancelCalculation() {
        calculationVersion++;

        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        if (calculationRequest) {
            var request = calculationRequest;
            calculationRequest = null;
            request.abort();
        }
    }

    function syncResource(section) {
        if (!section.length) {
            return;
        }

        var enabled = section.find('.resource-master').prop('checked') === true;

        setVisible(section.find('.resource-operations'), enabled);

        section.find('.operation-toggle')
            .prop('disabled', !enabled);

        section.find('.operation-box').each(function () {
            var box = $(this);
            var active = enabled
                && box.find('.operation-toggle').prop('checked') === true;

            var fields = box.find('.operation-fields');

            setVisible(fields, active);
            fields.find(':input').prop('disabled', !active);
        });
    }

    function syncEndDay() {
        var input = form.find('#rig-end-time');
        var hidden = form.find('#rig-end-day');
        var display = form.find('#rig-end-day-display');
        var button = form.find('#rig-end-day-toggle');

        if (!input.length || !hidden.length) {
            return;
        }

        var active = String(hidden.val()) === '1';
        var currentValue = input.val();

        if (active) {
            if (currentValue && currentValue !== '24:00') {
                oldEndTime = currentValue;
            }
            input.val('');
        } else if (!currentValue || currentValue === '24:00') {
            input.val(oldEndTime);
        }

        input
            .prop('readonly', false)
            .prop('disabled', active);

        setVisible(input, !active);
        setVisible(display, active);

        button
            .attr('type', 'button')
            .attr('aria-pressed', active ? 'true' : 'false')
            .toggleClass('btn-primary', active)
            .toggleClass('btn-default', !active)
            .toggleClass('active', active)
            .text(active ? '✓ پایان روز' : 'پایان روز');

        calculateHoursFromTime();
    }

    function updateSubmitButton() {
        submitButton
            .prop('disabled', submitting || !equipmentReady)
            .text(submitting ? 'در حال ثبت...' : 'ثبت مصرف دکل');

        form.attr('aria-busy', submitting ? 'true' : 'false');
    }

    function showEquipmentMessage(type, message) {
        var panel = $('<div>', {
            'class': 'panel panel-primary'
        });

        var body = $('<div>', {
            'class': 'panel-body'
        }).appendTo(panel);

        $('<h4>', {
            'class': 'section-title',
            text: 'اطلاعات موتور و ژنراتور'
        }).appendTo(body);

        $('<div>', {
            'class': 'alert alert-' + type,
            role: type === 'danger' ? 'alert' : 'status',
            text: message
        }).appendTo(body);

        equipmentContainer.empty().append(panel);

        return body;
    }

    function showCalculationErrors(errors) {
        var list = $('<ul>');
        var count = 0;

        errorsElement.empty();

        $.each(errors || {}, function (attribute, messages) {
            if (!Array.isArray(messages)) {
                messages = [messages];
            }

            $.each(messages, function (index, message) {
                if (message === null || typeof message === 'undefined') {
                    return;
                }

                var text = String(message);

                if (!text.trim()) {
                    return;
                }

                $('<li>').text(text).appendTo(list);
                count++;
            });
        });

        if (count > 0) {
            errorsElement.append(list).prop('hidden', false);
        } else {
            errorsElement.prop('hidden', true);
        }
    }

    function toNumber(value) {
        if (typeof value !== 'number' && typeof value !== 'string') {
            return null;
        }

        if (typeof value === 'string' && value.trim() === '') {
            return null;
        }

        var number = Number(value);

        return isFinite(number) ? number : null;
    }

    function formatNumber(value) {
        var number = toNumber(value);

        return number === null ? '—' : number.toFixed(2);
    }

    function isObject(value) {
        return value !== null
            && typeof value === 'object'
            && !Array.isArray(value);
    }

    function renderCalculation(data) {
        form.find('[data-summary]').each(function () {
            var element = $(this);
            var key = element.attr('data-summary');

            element.text(formatNumber(data.summary[key]));
        });

        form.find('[data-balance]').each(function () {
            var element = $(this);
            var name = element.attr('data-balance') || '';
            var parts = name.split('.');

            if (parts.length !== 2
                || (parts[0] !== 'fuel' && parts[0] !== 'water')) {
                element.text('—');
                return;
            }

            element.text(formatNumber(data[parts[0]][parts[1]]));
        });

        var difference = toNumber(data.summary.difference);

        form.find('[data-summary="difference"]')
            .removeClass('text-danger text-success')
            .toggleClass(
                'text-danger',
                difference !== null && difference > 0
            )
            .toggleClass(
                'text-success',
                difference !== null && difference < 0
            );

        errorsElement.empty().prop('hidden', true);

        statusElement.text(
            'محاسبات انجام شد؛ هنگام ثبت، اطلاعات و موجودی دوباره بررسی می‌شوند.'
        );
    }

    function requestErrorMessage(xhr, fallback) {
        if (xhr.status === 401 || xhr.status === 403) {
            return 'نشست کاربری یا مجوز دسترسی را بررسی کنید؛ ممکن است ورود مجدد لازم باشد.';
        }

        if (xhr.status === 400) {
            return 'درخواست معتبر نیست؛ اطلاعات فرم و اعتبار نشست کاربری را بررسی کنید.';
        }

        return fallback;
    }

    function calculate(version) {
        if (version !== calculationVersion
            || submitting
            || !equipmentReady
            || !rigSelector.val()) {
            return;
        }

        statusElement.text('در حال محاسبه...');

        calculationRequest = $.ajax({
            url: {$calculateUrl},
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            data: form.serialize()
        }).done(function (data) {
            if (version !== calculationVersion || submitting) {
                return;
            }

            if (!isObject(data)) {
                clearSummary('پاسخ محاسبات معتبر نیست.');
                return;
            }

            if (data.valid !== true) {
                clearSummary('برای محاسبه، موارد زیر را اصلاح کنید.');

                showCalculationErrors(
                    isObject(data.errors) && Object.keys(data.errors).length
                        ? data.errors
                        : {form: ['اطلاعات فرم برای محاسبه معتبر نیست.']}
                );

                return;
            }

            if (!isObject(data.summary)
                || !isObject(data.fuel)
                || !isObject(data.water)) {
                clearSummary('پاسخ محاسبات کامل نیست؛ دوباره تلاش کنید.');
                return;
            }

            renderCalculation(data);
        }).fail(function (xhr, status) {
            if (status === 'abort'
                || version !== calculationVersion
                || submitting) {
                return;
            }

            clearSummary(requestErrorMessage(
                xhr,
                'دریافت محاسبات ناموفق بود؛ با تغییر یکی از فیلدها دوباره تلاش کنید.'
            ));
        }).always(function () {
            if (version === calculationVersion) {
                calculationRequest = null;
            }
        });
    }

    function scheduleCalculation() {
        cancelCalculation();

        if (submitting) {
            return;
        }

        if (!equipmentReady || !rigSelector.val()) {
            clearSummary('ابتدا دکل و اطلاعات تجهیزات را مشخص کنید.');
            return;
        }

        clearSummary('اطلاعات تغییر کرده؛ در انتظار محاسبه...');

        var version = calculationVersion;

        timer = setTimeout(function () {
            timer = null;
            calculate(version);
        }, 650);
    }

    function loadEquipment() {
        if (submitting) {
            return;
        }

        var rigId = rigSelector.val();
        var version = ++equipmentVersion;

        cancelCalculation();

        if (equipmentRequest) {
            var request = equipmentRequest;
            equipmentRequest = null;
            request.abort();
        }

        equipmentReady = false;
        equipmentContainer.attr('aria-busy', 'false');
        updateSubmitButton();

        clearSummary('ابتدا اطلاعات تجهیزات تکمیل شود.');

        if (!rigId) {
            showEquipmentMessage(
                'info',
                'ابتدا دکل را انتخاب کنید.'
            );

            return;
        }

        equipmentContainer.attr('aria-busy', 'true');

        showEquipmentMessage(
            'info',
            'در حال دریافت اطلاعات تجهیزات دکل...'
        );

        equipmentRequest = $.ajax({
            url: {$equipmentUrl},
            type: 'GET',
            dataType: 'html',
            cache: false,
            timeout: 30000,
            data: {
                rigId: rigId
            }
        }).done(function (html) {
            if (version !== equipmentVersion
                || submitting
                || String(rigSelector.val()) !== String(rigId)) {
                return;
            }

            equipmentContainer.html(html);

            equipmentReady = true;
            updateSubmitButton();
            calculateHoursFromTime();
            scheduleCalculation();
        }).fail(function (xhr, status) {
            if (status === 'abort'
                || version !== equipmentVersion
                || submitting) {
                return;
            }

            equipmentReady = false;

            var body = showEquipmentMessage(
                'danger',
                requestErrorMessage(
                    xhr,
                    'دریافت اطلاعات تجهیزات ناموفق بود.'
                )
            );

            $('<button>', {
                type: 'button',
                'class': 'btn btn-default',
                text: 'تلاش مجدد'
            }).on('click', function () {
                loadEquipment();
            }).appendTo(body);

            updateSubmitButton();
        }).always(function () {
            if (version === equipmentVersion) {
                equipmentRequest = null;
                equipmentContainer.attr('aria-busy', 'false');
            }
        });
    }

    form.on(
        'click.rigConsumption',
        '#rig-end-day-toggle',
        function (event) {
            event.preventDefault();

            if (submitting) {
                return;
            }

            var hidden = form.find('#rig-end-day');

            if (!hidden.length) {
                return;
            }

            var active = String(hidden.val()) === '1';

            hidden.val(active ? '0' : '1');

            syncEndDay();
            scheduleCalculation();

            if (String(hidden.val()) === '0') {
                form.find('#rig-end-time').trigger('focus');
            }
        }
    );

    form.on(
        'change.rigConsumption',
        '#rig-selector',
        function () {
            loadEquipment();
        }
    );

    form.on(
        'input.rigConsumption change.rigConsumption',
        ':input',
        function (event) {
            if (submitting
                || this.id === 'rig-selector'
                || this.type === 'hidden'
                || this.type === 'submit'
                || this.type === 'button'
                || this.type === 'reset') {
                return;
            }

            if ((this.type === 'checkbox' || this.type === 'radio')
                && event.type !== 'change') {
                return;
            }

            if (this.id === 'rig-end-time') {
                oldEndTime = $(this).val() || '';
            }

            // چک کردن تغییرات فیلد‌های زمان جهت محاسبه ساعات تجهیزات
            if (this.id === 'rig-start-time' || this.id === 'rig-end-time' || (this.name && (this.name.indexOf('start_time') !== -1 || this.name.indexOf('end_time') !== -1))) {
                calculateHoursFromTime();
            }

            if ($(this).is('.resource-master, .operation-toggle')) {
                syncResource($(this).closest('.resource-section'));
            }

            scheduleCalculation();
        }
    );

    form.on('beforeSubmit.rigConsumption', function () {
        if (submitting) {
            return false;
        }

        if (!equipmentReady || !rigSelector.val()) {
            statusElement.text(
                'ابتدا دکل را انتخاب کنید و منتظر بارگذاری تجهیزات بمانید.'
            );

            return false;
        }

        syncEndDay();

        form.find('.resource-section').each(function () {
            syncResource($(this));
        });

        if (typeof form[0].reportValidity === 'function'
            && !form[0].reportValidity()) {
            return false;
        }

        cancelCalculation();
        submitting = true;

        updateSubmitButton();

        return true;
    });

    form.find('.resource-section').each(function () {
        syncResource($(this));
    });

    syncEndDay();
    updateSubmitButton();

    $(window).on('pageshow.rigConsumption', function (event) {
        if (!event.originalEvent || !event.originalEvent.persisted) {
            return;
        }

        submitting = false;

        syncEndDay();

        form.find('.resource-section').each(function () {
            syncResource($(this));
        });

        updateSubmitButton();

        if (!equipmentReady && rigSelector.val()) {
            loadEquipment();
        } else {
            scheduleCalculation();
        }
    });
})(jQuery);
JS
);
?>