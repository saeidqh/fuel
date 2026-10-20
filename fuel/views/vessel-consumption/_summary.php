<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var app\modules\fuel\models\VesselFuelConsumption $report */

$route = ['/fuel/vessel-consumption/calculate'];

if (!$report->isNewRecord) {
    $route['id'] = $report->id;
}

$calculateUrl = Json::htmlEncode(Url::to($route));

$cards = [
    'equipment_hours' => [
        'label' => 'جمع کارکرد تجهیزات',
        'unit' => 'تجهیز‌ساعت',
    ],
    'actual_consumption' => [
        'label' => 'مصرف واقعی',
        'unit' => 'لیتر',
    ],
    'ideal_consumption' => [
        'label' => 'مصرف ایده‌آل',
        'unit' => 'لیتر',
    ],
    'difference' => [
        'label' => 'اختلاف مصرف',
        'unit' => 'لیتر',
    ],
];

// استایل مشترک کارت‌های خلاصه و موجودی لحظه‌ای در هر دو بخش فرم.
$this->registerCss(<<<'CSS'
.vessel-consumption-form .vc-summary-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    margin-bottom:18px;
}
.vessel-consumption-form .vc-summary-grid-balance{
    grid-template-columns:repeat(5,minmax(0,1fr));
}
.vessel-consumption-form .vc-summary-card{
    min-height:125px;
    padding:16px 10px;
    border:1px solid #d9e6f2;
    border-radius:7px;
    background:#f8fbfe;
    text-align:center;
}
.vessel-consumption-form .vc-summary-card-final{
    background:#edf5ff;
    border-color:#9fc3ec;
}
.vessel-consumption-form .vc-summary-card strong{
    display:block;
    min-height:36px;
    color:#475569;
    font-size:12px;
    line-height:1.7;
}
.vessel-consumption-form .vc-summary-value{
    display:block;
    margin:8px 0;
    color:#23527c;
    font-size:23px;
    font-weight:700;
    direction:ltr;
    overflow-wrap:anywhere;
}
.vessel-consumption-form .vc-summary-card small{
    color:#64748b;
    font-size:12px;
}
.vessel-consumption-form .vc-summary-value.positive{color:#b91c1c}
.vessel-consumption-form .vc-summary-value.negative{color:#15803d}
.vessel-consumption-form .vc-summary-errors{
    margin:8px 0 0;
    padding-right:20px;
    line-height:1.9;
}
.vessel-consumption-form .vc-summary-footer{margin-bottom:10px}
@media(max-width:991px){
    .vessel-consumption-form .vc-summary-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media(max-width:479px){
    .vessel-consumption-form .vc-summary-grid{
        grid-template-columns:1fr;
    }
}
CSS
);
?>

<fieldset class="vc-box" id="vessel-summary" aria-busy="false">
    <legend>خلاصه محاسبات مصرف</legend>

    <div id="vessel-summary-message"
         class="alert alert-info"
         role="status"
         aria-live="polite">
        اطلاعات گزارش و کارکرد تجهیزات را تکمیل کنید.
    </div>

    <div class="vc-summary-grid">
        <?php foreach ($cards as $key => $card): ?>
            <div class="vc-summary-card">
                <strong><?= Html::encode($card['label']) ?></strong>

                <span class="vc-summary-value"
                      data-summary-group="summary"
                      data-summary-key="<?= Html::encode($key) ?>">—</span>

                <small><?= Html::encode($card['unit']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="vc-summary-footer">
        <?= Html::button('محاسبه مجدد', [
            'id' => 'vessel-summary-refresh',
            'class' => 'btn btn-default btn-sm',
        ]) ?>
    </div>

    <p class="vc-note">
        اختلاف مصرف برابر با مصرف واقعی منهای مصرف ایده‌آل است.
        مصرف ایده‌آل بر اساس نرخ‌های تعریف‌شده تجهیزات محاسبه می‌شود.
    </p>

    <p class="vc-note">
        موجودی لحظه‌ای سوخت و آب در بخش دریافت و انتقال نمایش داده می‌شود.
        هنگام ثبت، محاسبات و کنترل موجودی دوباره در سرور انجام می‌شوند.
    </p>
</fieldset>

<?php

$js = <<<JS
(function ($) {
    var form = $('#vessel-consumption-form');
    var boxes = form.find('#vessel-summary, #vessel-transfers');
    var messages = form.find('#vessel-summary-message, #vessel-balance-message');
    var refresh = $('#vessel-summary-refresh');
    var marker = $('#vessel-items-present');
    var timer = null;
    var request = null;
    var sequence = 0;

    // کارت‌ها در دو بخش جدا قرار دارند؛ جست‌وجو در کل فرم انجام می‌شود.
    function getValues() {
        return form.find('[data-summary-group][data-summary-key]');
    }

    function clearValues() {
        getValues().text('—').removeClass('positive negative');
    }

    function setBusy(busy) {
        boxes.attr('aria-busy', busy ? 'true' : 'false');
        refresh.prop('disabled', busy);
    }

    function showMessage(type, text, errors) {
        messages.each(function () {
            var message = $(this);

            message
                .removeClass('alert-info alert-success alert-warning alert-danger')
                .addClass('alert-' + type)
                .empty();

            $('<div>').text(text).appendTo(message);

            if ($.isArray(errors) && errors.length) {
                var list = $('<ul>', {'class': 'vc-summary-errors'});

                $.each(errors, function (_, error) {
                    $('<li>').text(error).appendTo(list);
                });

                message.append(list);
            }
        });
    }

    function cancelPending() {
        sequence++;
        window.clearTimeout(timer);
        timer = null;

        if (request) {
            request.abort();
            request = null;
        }

        setBusy(false);
    }

    function isReady() {
        return marker.val() === '1';
    }

    function isNumericValue(value) {
        return (typeof value === 'number'
                || (typeof value === 'string' && value.trim() !== ''))
            && isFinite(Number(value));
    }

    function renderSummary(data) {
        var values = getValues();
        var valid = true;

        values.each(function () {
            var element = $(this);
            var group = element.attr('data-summary-group');
            var key = element.attr('data-summary-key');

            if (!data[group] || !isNumericValue(data[group][key])) {
                valid = false;
                return false;
            }
        });

        if (!valid) {
            clearValues();
            showMessage(
                'danger',
                'پاسخ محاسبات کامل یا معتبر نیست؛ دوباره تلاش کنید.'
            );
            return;
        }

        values.each(function () {
            var element = $(this);
            var group = element.attr('data-summary-group');
            var key = element.attr('data-summary-key');
            var value = data[group][key];

            element.text(value);

            if (group === 'summary' && key === 'difference') {
                element
                    .toggleClass('positive', Number(value) > 0)
                    .toggleClass('negative', Number(value) < 0);
            }
        });

        showMessage(
            'success',
            'مصرف و موجودی‌ها بر اساس اطلاعات فعلی فرم در سرور محاسبه شدند.'
        );
    }

    function calculate() {
        cancelPending();
        clearValues();

        if (!isReady()) {
            showMessage(
                'info',
                'ابتدا شناور را انتخاب کنید و منتظر بارگذاری تجهیزات بمانید.'
            );
            return;
        }

        var currentSequence = sequence;

        setBusy(true);
        showMessage('info', 'در حال محاسبه مصرف و موجودی در سرور...');

        request = $.ajax({
            url: {$calculateUrl},
            type: 'POST',
            dataType: 'json',
            data: form.serialize(),
            timeout: 30000
        }).done(function (data) {
            if (currentSequence !== sequence) {
                return;
            }

            if (!data || data.success !== true) {
                showMessage(
                    'warning',
                    'برای محاسبه معتبر، موارد زیر را اصلاح کنید:',
                    data && $.isArray(data.errors) && data.errors.length
                        ? data.errors
                        : ['پاسخ سرور معتبر نیست.']
                );
                return;
            }

            renderSummary(data);
        }).fail(function (xhr, status) {
            if (currentSequence !== sequence || status === 'abort') {
                return;
            }

            showMessage(
                'danger',
                'محاسبه انجام نشد. اتصال و اعتبار نشست را بررسی کرده و دوباره تلاش کنید.'
            );
        }).always(function () {
            if (currentSequence === sequence) {
                request = null;
                setBusy(false);
            }
        });
    }

    function schedule() {
        cancelPending();
        clearValues();

        if (!isReady()) {
            showMessage(
                'info',
                'ابتدا شناور را انتخاب کنید و منتظر بارگذاری تجهیزات بمانید.'
            );
            return;
        }

        showMessage(
            'info',
            'اطلاعات تغییر کرده است؛ محاسبات در حال به‌روزرسانی هستند.'
        );

        timer = window.setTimeout(calculate, 500);
    }

    form
        .off('.vesselSummary')
        .on(
            'input.vesselSummary change.vesselSummary',
            'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea',
            schedule
        )
        .on('vesselEquipmentUpdated.vesselSummary', schedule);

    refresh
        .off('click.vesselSummary')
        .on('click.vesselSummary', calculate);

    schedule();
})(jQuery);
JS;

$this->registerJs($js);
?>