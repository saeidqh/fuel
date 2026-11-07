<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\ActiveForm;
use app\modules\fuel\models\RigType;
use app\modules\fuel\models\LocationType;

/** @var yii\web\View $this */
/** @var RigType $model */
/** @var array|null $engines */
/** @var array|null $generators */
/** @var bool|null $balancesLocked */
/** @var bool|null $showActions */

$isNew = $model->isNewRecord;
$isAjax = Yii::$app->request->isAjax;

$formId = $isNew ? 'rig-create-form' : 'rig-update-form';

$formAction = $isNew
    ? ['/fuel/rig/create']
    : ['/fuel/rig/update', 'id' => $model->id];

$this->title = $isNew
    ? 'ایجاد دکل جدید'
    : 'ویرایش دکل: ' . $model->name;

if (!$isAjax) {
    $this->params['breadcrumbs'][] = [
        'label' => 'مدیریت سوخت',
        'url' => ['/fuel/fuel/list'],
    ];

    $this->params['breadcrumbs'][] = [
        'label' => 'مدیریت دکل',
        'url' => ['/fuel/rig/list'],
    ];

    $this->params['breadcrumbs'][] = $this->title;
}

$maxEquipmentCount = RigType::MAX_EQUIPMENT_COUNT;

$engineOptions = [0 => 'بدون موتور'];
$generatorOptions = [0 => 'بدون موتور اضطراری'];

for ($number = 1; $number <= $maxEquipmentCount; $number++) {
    $engineOptions[$number] = $number . ' موتور';
    $generatorOptions[$number] = $number . ' موتور اضطراری';
}

// مقادیر قدیمی خارج از محدوده، بی‌صدا به عدد دیگری تبدیل نمی‌شوند.
$appendExistingCount = static function (array $options, $value, $label) {
    if (
        is_scalar($value)
        && preg_match('/\A\d+\z/', (string) $value)
        && !array_key_exists((int) $value, $options)
    ) {
        $options[(int) $value] = $value . ' ' . $label . ' ـ نیازمند اصلاح';
        ksort($options);
    }

    return $options;
};

$engineOptions = $appendExistingCount(
    $engineOptions,
    $model->engine_count,
    'موتور'
);

$generatorOptions = $appendExistingCount(
    $generatorOptions,
    $model->generator_count,
    'موتور اضطراری'
);

$years = range(1300, 1430);
$yearOptions = array_combine($years, $years);

$locations = LocationType::find()
    ->select(['name', 'id'])
    ->orderBy(['name' => SORT_ASC])
    ->indexBy('id')
    ->column();

$engineModels = $engines ?? ($isNew ? [] : $model->enginesArray);
$generatorModels = $generators ?? ($isNew ? [] : $model->generatorsArray);

$equipmentData = [
    'engine' => [],
    'generator' => [],
];

foreach ($engineModels as $engine) {
    $equipmentData['engine'][(int) $engine->engine_number] = [
        'type' => $engine->engine_type,
        'power' => $engine->engine_power,
        'fuel_consumption' => $engine->engine_fuel_consumption,
        'tested_fuel_consumption' => $engine->engine_tested_fuel_consumption,
    ];
}

foreach ($generatorModels as $generator) {
    $equipmentData['generator'][(int) $generator->generator_number] = [
        'type' => $generator->generator_type,
        'power' => $generator->generator_power,
        'fuel_consumption' => $generator->generator_fuel_consumption,
        'tested_fuel_consumption' => $generator->generator_tested_fuel_consumption,
    ];
}

// حفظ ورودی‌های کاربر پس از خطا.
if (Yii::$app->request->isPost) {
    foreach (['engine', 'generator'] as $type) {
        $postedItems = Yii::$app->request->post($type, []);
        $equipmentData[$type] = [];

        if (!is_array($postedItems)) {
            continue;
        }

        foreach ($postedItems as $number => $item) {
            if (
                !preg_match('/\A[1-9]\d*\z/', (string) $number)
                || (int) $number > $maxEquipmentCount
                || !is_array($item)
            ) {
                continue;
            }

            $values = [];

            foreach (
                ['type', 'power', 'fuel_consumption', 'tested_fuel_consumption']
                as $field
            ) {
                $value = $item[$field] ?? '';

                $values[$field] = is_scalar($value) ? (string) $value : '';
            }

            $equipmentData[$type][(int) $number] = $values;
        }
    }
}

$balancesLocked = $balancesLocked ?? $model->hasConsumptionHistory();
$showActions = $showActions ?? !$isAjax;

$currentDocument = $isNew
    ? null
    : $model->getOldAttribute('rig_document');

$documentUrl = null;

if (
    is_string($currentDocument)
    && preg_match(
        '~\A/uploads/rigs/[a-zA-Z0-9_.-]+\z~',
        $currentDocument
    )
) {
    $documentUrl = rtrim(Yii::getAlias('@web'), '/') . $currentDocument;
}

$config = Json::htmlEncode([
    'formId' => $formId,
    'maxCount' => $maxEquipmentCount,
    'engines' => $equipmentData['engine'],
    'generators' => $equipmentData['generator'],
]);

$this->registerCss(<<<'CSS'
.rig-base-form {
    padding: 5px 0;
}
.rig-base-form .panel.panel-primary {
    margin: 27px 0 20px !important;
    border: 0;
    background: transparent;
    box-shadow: none;
}
.rig-base-form .panel-body {
    position: relative;
    padding: 25px 15px 0;
    border: 2px solid #337ab7;
    border-radius: 8px;
    background: #fff;
}
.rig-base-form .text-on-panel {
    position: absolute;
    top: -16px;
    right: 15px;
    margin: 0;
    padding: 3px 8px;
    border: 2px solid #337ab7;
    border-radius: 8px;
    background: #fff2b3;
    color: #0b58a2;
    line-height: 20px;
}
.rig-base-form .text-on-panel strong {
    font-size: 13px !important;
}
.rig-base-form .form-group {
    margin-bottom: 18px;
}
.rig-base-form .control-label {
    margin-bottom: 7px;
    color: #444;
    font-size: 12px;
}
.rig-base-form .form-control {
    border-radius: 4px;
}
.rig-base-form select.form-control {
    padding: 0 6px !important;
    font-size: 12px !important;
}
.rig-base-form .equipment-item {
    margin-bottom: 18px;
    padding: 12px 12px 0;
    border: 1px solid #d9e5ef;
    border-radius: 6px;
    background: #fafcfe;
}
.rig-base-form .equipment-title {
    margin: 0 0 15px;
    padding-bottom: 9px;
    border-bottom: 1px solid #d9e5ef;
    color: #0b58a2;
    font-size: 13px;
    font-weight: 700;
}
.rig-base-form .section-note,
.rig-base-form .empty-message {
    margin: 0 0 18px;
    color: #666;
    font-size: 12px;
    line-height: 1.9;
}
.rig-base-form .help-block {
    font-size: 12px;
}
.rig-base-form .rig-save-errors {
    margin: 0 0 15px;
}
.rig-base-form .number-input {
    direction: ltr;
    text-align: right;
}
.rig-base-form .current-file {
    margin-bottom: 18px;
}
.rig-base-form .current-file a {
    color: #0b58a2;
    font-size: 12px;
}
.rig-base-form .equipment-error {
    margin: 0 0 18px;
    color: #a94442;
    font-size: 12px;
}
.rig-base-form .form-actions {
    margin-top: 20px;
    text-align: left;
}
.rig-base-form input[type="file"] {
    max-width: 100%;
}
.rig-base-form input[readonly] {
    background: #f1f3f5;
}
@media (max-width: 767px) {
    .rig-base-form .panel-body {
        padding-right: 10px;
        padding-left: 10px;
    }
    .rig-base-form .text-on-panel {
        right: 10px;
    }
}
CSS
);
?>

<div class="rig-base-form" dir="rtl">
    <?php $form = ActiveForm::begin([
        'id' => $formId,
        'action' => $formAction,
        'method' => 'post',
        'options' => [
            'enctype' => 'multipart/form-data',
        ],
        'fieldConfig' => [
            'template' => "{label}\n{input}\n{hint}\n{error}",
            'labelOptions' => ['class' => 'control-label'],
        ],
    ]); ?>

    <div
        class="alert alert-danger rig-save-errors"
        role="alert"
        style="display:none"
    ></div>

    <?= $form->errorSummary($model) ?>

    <noscript>
        <div class="alert alert-warning">
            برای ورود اطلاعات تجهیزات، جاوااسکریپت مرورگر را فعال کنید.
        </div>
    </noscript>

    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel">
                <strong>اطلاعات پایه دکل</strong>
            </h4>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'name')->textInput([
                        'id' => $formId . '-name',
                        'maxlength' => true,
                        'placeholder' => 'نام دکل',
                    ]) ?>
                </div>

                <div class="col-md-3">
                    <?= $form->field($model, 'code')->textInput([
                        'id' => $formId . '-code',
                        'maxlength' => true,
                        'placeholder' => 'اختیاری',
                    ]) ?>
                </div>

                <div class="col-md-3">
                    <?= $form->field($model, 'build_year')->dropDownList(
                        $yearOptions,
                        [
                            'id' => $formId . '-build-year',
                            'prompt' => 'انتخاب سال ساخت',
                        ]
                    ) ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'location_id')->dropDownList(
                        $locations,
                        [
                            'id' => $formId . '-location',
                            'prompt' => 'انتخاب لوکیشن',
                        ]
                    ) ?>
                </div>

                <div class="col-md-3">
                    <?= $form->field($model, 'engine_count')->dropDownList(
                        $engineOptions,
                        [
                            'id' => $formId . '-engine-count',
                            'data-role' => 'engine-count',
                            'required' => true,
                        ]
                    ) ?>
                </div>

                <div class="col-md-3">
                    <?= $form->field($model, 'generator_count')->dropDownList(
                        $generatorOptions,
                        [
                            'id' => $formId . '-generator-count',
                            'data-role' => 'generator-count',
                            'required' => true,
                        ]
                    ) ?>
                </div>
            </div>

            <p class="section-note">
                ساختار فعلی گزارش مصرف، حداکثر ۵ موتور و ۵ موتور اضطراری را پشتیبانی می‌کند.
            </p>
        </div>
    </div>

    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel">
                <strong>مشخصات موتورها</strong>
            </h4>

            <p class="section-note">
                نرخ ایده‌آل و نرخ تست‌شده هر موتور الزامی است.
                قدرت و نوع موتور اختیاری هستند.
            </p>

            <div data-role="engines"></div>
        </div>
    </div>

    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel">
                <strong>مشخصات موتور اضطراری</strong>
            </h4>

            <p class="section-note">
                نرخ ایده‌آل و نرخ تست‌شده هر موتور اضطراری الزامی است.
                قدرت و نوع موتور اضطراری اختیاری هستند.
            </p>

            <div data-role="generators"></div>
        </div>
    </div>

    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel">
                <strong>موجودی اولیه سوخت و آب</strong>
            </h4>

            <?php if ($balancesLocked): ?>
                <div class="alert alert-info">
                    به دلیل وجود سابقه گزارش مصرف، موجودی اولیه قابل تغییر نیست.
                    سایر مشخصات دکل قابل ویرایش هستند.
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'initial_fuel_balance')
                        ->input('number', [
                            'id' => $formId . '-fuel-balance',
                            'min' => 0,
                            'max' => '99999999.99',
                            'step' => '0.01',
                            'readonly' => $balancesLocked,
                            'class' => 'form-control number-input',
                        ]) ?>
                </div>

                <div class="col-md-6">
                    <?= $form->field($model, 'initial_water_balance')
                        ->input('number', [
                            'id' => $formId . '-water-balance',
                            'min' => 0,
                            'max' => '99999999.99',
                            'step' => '0.01',
                            'readonly' => $balancesLocked,
                            'class' => 'form-control number-input',
                        ]) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel">
                <strong>توضیحات و مستندات دکل</strong>
            </h4>

            <div class="row">
                <div class="col-md-12">
                    <?= $form->field($model, 'description')->textarea([
                        'id' => $formId . '-description',
                        'rows' => 3,
                        'placeholder' => 'توضیحات تکمیلی ـ اختیاری',
                    ]) ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'documentFile')->fileInput([
                        'id' => $formId . '-document',
                        'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png',
                    ])->hint(
                        'اختیاری؛ حداکثر ۱۰ مگابایت. پس از خطای ثبت، فایل را دوباره انتخاب کنید.'
                    ) ?>
                </div>

                <?php if ($documentUrl !== null): ?>
                    <div class="col-md-6">
                        <div class="current-file">
                            <p class="control-label">فایل فعلی</p>

                            <?= Html::a(
                                'مشاهده فایل مشخصات دکل',
                                $documentUrl,
                                [
                                    'target' => '_blank',
                                    'rel' => 'noopener noreferrer',
                                    'data-pjax' => 0,
                                ]
                            ) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($showActions): ?>
        <div class="form-actions">
            <?= Html::submitButton(
                $isNew ? 'ثبت دکل' : 'ذخیره تغییرات',
                [
                    'class' => 'btn btn-success',
                    'data-role' => 'rig-save',
                ]
            ) ?>

            <?php if (!$isAjax): ?>
                <?= Html::a(
                    'بازگشت',
                    ['/fuel/rig/list'],
                    ['class' => 'btn btn-default']
                ) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(<<<JS
(function ($, config) {
    'use strict';

    var form = $('#' + config.formId);

    if (!form.length) {
        return;
    }

    var cache = Object.create(null);

    var equipment = {
        engine: {
            title: 'موتور',
            unit: 'اسب بخار',
            container: 'engines',
            records: config.engines || {}
        },
        generator: {
            title: 'موتور اضطراری',
            unit: 'کیلووات',
            container: 'generators',
            records: config.generators || {}
        }
    };

    function getValue(name, fallback) {
        if (Object.prototype.hasOwnProperty.call(cache, name)) {
            return cache[name];
        }

        if (typeof fallback === 'string' || typeof fallback === 'number') {
            return fallback;
        }

        return '';
    }

    function rememberValues() {
        form.find('[data-dynamic]').each(function () {
            cache[this.name] = $(this).val();
        });
    }

    function getCount(type) {
        var value = form.find(
            '[data-role="' + type + '-count"]'
        ).val();

        if (value === null || value === '') {
            return null;
        }

        var count = Number(value);

        if (
            !isFinite(count)
            || Math.floor(count) !== count
            || count < 0
            || count > config.maxCount
        ) {
            return null;
        }

        return count;
    }

    function appendField(parent, name, label, value, options) {
        var id = config.formId + '-'
            + name.replace(/[^a-zA-Z0-9_-]/g, '-');

        var column = $('<div>', {
            class: 'col-md-3 col-sm-6'
        });

        var group = $('<div>', {
            class: 'form-group'
        });

        var settings = $.extend({
            type: 'number',
            min: 0,
            max: '99999999.99',
            step: '0.01'
        }, options || {});

        var input = $('<input>').attr(settings);

        if (settings.type === 'text') {
            input.removeAttr('min max step');
        }

        input.attr({
            id: id,
            name: name,
            class: 'form-control',
            'data-dynamic': '1'
        }).val(getValue(name, value));

        if (settings.type === 'number') {
            input.addClass('number-input');
        }

        $('<label>', {
            for: id,
            class: 'control-label'
        }).text(label).appendTo(group);

        group.append(input);
        column.append(group).appendTo(parent);
    }

    function renderEquipment(type) {
        var item = equipment[type];

        var select = form.find(
            '[data-role="' + type + '-count"]'
        );

        var container = form.find(
            '[data-role="' + item.container + '"]'
        ).empty();

        var count = getCount(type);

        if (count === null) {
            var message = 'تعداد ' + item.title
                + ' باید بین صفر و ' + config.maxCount + ' باشد.';

            select[0].setCustomValidity(message);

            $('<p>', {
                class: 'equipment-error'
            }).text(message).appendTo(container);

            return;
        }

        select[0].setCustomValidity('');

        if (count === 0) {
            $('<p>', {
                class: 'empty-message'
            }).text(
                'برای این دکل ' + item.title + ' تعریف نشده است.'
            ).appendTo(container);

            return;
        }

        for (var number = 1; number <= count; number++) {
            var data = item.records[number] || {};
            var base = type + '[' + number + ']';

            var section = $('<div>', {
                class: 'equipment-item'
            });

            var row = $('<div>', {
                class: 'row'
            });

            $('<h5>', {
                class: 'equipment-title'
            }).text(
                item.title + ' شماره ' + number
            ).appendTo(section);

            appendField(
                row,
                base + '[power]',
                'قدرت (' + item.unit + ')',
                data.power
            );

            appendField(
                row,
                base + '[fuel_consumption]',
                'نرخ ایده‌آل (لیتر/ساعت) *',
                data.fuel_consumption,
                {required: true}
            );

            appendField(
                row,
                base + '[tested_fuel_consumption]',
                'نرخ تست‌شده (لیتر/ساعت) *',
                data.tested_fuel_consumption,
                {required: true}
            );

            appendField(
                row,
                base + '[type]',
                'نوع ' + item.title,
                data.type,
                {
                    type: 'text',
                    maxlength: 100
                }
            );

            section.append(row).appendTo(container);
        }
    }

    function render() {
        rememberValues();
        renderEquipment('engine');
        renderEquipment('generator');
    }

    form.off('.rigBaseForm');

    form.on(
        'change.rigBaseForm',
        '[data-role="engine-count"], [data-role="generator-count"]',
        render
    );

    form.on('beforeSubmit.rigBaseForm', function () {
        if (!form[0].checkValidity()) {
            if (typeof form[0].reportValidity === 'function') {
                form[0].reportValidity();
            }

            return false;
        }
    });

    // ارسال AJAX همچنان بر عهده اسکریپت مودال موجود است.
    render();
})(jQuery, {$config});
JS
);
?>