<?php

use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\ActiveForm;
use app\modules\fuel\models\VesselType;

/** @var yii\web\View $this */
/** @var app\modules\fuel\models\VesselType $model */
/** @var array|null $existingEngines */
/** @var array|null $existingGenerators */
/** @var array|null $existingModes */
/** @var array|null $existingBowThruster */

$formId = $model->isNewRecord
    ? 'vessel-create-form'
    : 'vessel-update-form';

$engineOptions = [];
$generatorOptions = [];

for ($i = 0; $i <= VesselType::MAX_EQUIPMENT; $i++) {
    $engineOptions[$i] = $i
        ? $i . ' موتور'
        : 'بدون موتور';

    $generatorOptions[$i] = $i
        ? $i . ' ژنراتور'
        : 'بدون ژنراتور';
}

$configJson = Json::encode([
    'formId' => $formId,
    'engines' => $existingEngines ?? [],
    'generators' => $existingGenerators ?? [],
    'modes' => $existingModes ?? [],
    'bowThruster' => $existingBowThruster ?? [],
]);

$this->registerCss(<<<'CSS'
.vessel-form {
    padding: 5px 0;
}

.vessel-form .panel.panel-primary {
    margin: 27px 0 20px !important;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.vessel-form .panel-body {
    position: relative;
    padding: 25px 15px 0;
    border: 2px solid #337ab7;
    border-radius: 8px;
    background: #fff;
}

.vessel-form .text-on-panel {
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

.vessel-form .text-on-panel strong {
    font-size: 13px !important;
}

.vessel-form .form-group {
    margin-bottom: 18px;
}

.vessel-form .control-label {
    margin-bottom: 7px;
    color: #444;
    font-size: 13px;
}

.vessel-form .form-control {
    border-radius: 4px;
}

.vessel-form select.form-control {
    padding: 0 6px !important;
    font-size: 12px !important;
}

.vessel-form .equipment-item,
.vessel-form .mode-item {
    margin-bottom: 18px;
    padding: 12px 12px 0;
    border: 1px solid #d9e5ef;
    border-radius: 6px;
    background: #fafcfe;
}

.vessel-form .equipment-title,
.vessel-form .mode-title {
    margin: 0 0 15px;
    padding-bottom: 9px;
    border-bottom: 1px solid #d9e5ef;
    color: #0b58a2;
    font-size: 13px;
    font-weight: 700;
}

.vessel-form .mode-item {
    background: #fffdf5;
}

.vessel-form .bow-thruster-item {
    border-color: #b9d4e9;
    background: #f5faff;
}

.vessel-form .section-note {
    margin-bottom: 15px;
    color: #777;
    font-size: 12px;
    line-height: 1.9;
}

.vessel-form .empty-message {
    margin: 0 0 18px;
    color: #777;
}

.vessel-form .current-image,
.vessel-form .preview-image {
    display: block;
    max-width: 150px;
    max-height: 150px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.vessel-form .help-block {
    font-size: 12px;
}

.vessel-form .vessel-save-errors {
    margin: 0 0 15px;
}

.vessel-form .number-input {
    direction: ltr;
    text-align: right;
}

.vessel-form .required-mark {
    margin-right: 4px;
    color: #b3261e;
}

@media (max-width: 767px) {
    .vessel-form .panel-body {
        padding-right: 10px;
        padding-left: 10px;
    }

    .vessel-form .text-on-panel {
        right: 10px;
    }
}
CSS
);
?>

<div class="vessel-form">
    <?php $form = ActiveForm::begin([
        'id' => $formId,
        'action' => [
            'save',
            'id' => $model->isNewRecord ? null : $model->id,
        ],
        'method' => 'post',
        'options' => [
            'enctype' => 'multipart/form-data',
        ],
        'fieldConfig' => [
            'template' => "{label}\n{input}\n{hint}\n{error}",
            'labelOptions' => [
                'class' => 'control-label',
            ],
        ],
    ]); ?>

    <div
        class="alert alert-danger vessel-save-errors"
        role="alert"
        style="display:none"
    ></div>

    <?= $form->errorSummary($model) ?>

    <!-- اطلاعات پایه -->
    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel text-primary">
                <strong>اطلاعات پایه شناور</strong>
            </h4>

            <div class="row">
                    <div class="col-md-4">
                    <?= $form->field($model, 'has_bow_thruster')
                        ->dropDownList(
                            VesselType::getBowThrusterOptions(),
                            [
                                'id' => $formId . '-has-bow-thruster',
                                'data-role' => 'has-bow-thruster',
                                'aria-controls' => $formId . '-bow-thruster-details',
                            ]
                        )
                        ->label('آیا شناور بوتراستر دارد؟') ?>
                </div>
                  <div class="col-md-4">
                    <?= $form->field($model, 'code')->textInput([
                        'id' => $formId . '-code',
                        'maxlength' => true,
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <?= $form->field($model, 'name')->textInput([
                        'id' => $formId . '-name',
                        'maxlength' => true,
                    ]) ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <?= $form->field($model, 'status')->dropDownList(
                        VesselType::getStatusOptions(),
                        [
                            'id' => $formId . '-status',
                            'prompt' => 'انتخاب وضعیت',
                        ]
                    ) ?>
                </div>

                <div class="col-md-3">
                    <?= $form->field($model, 'engine_count')->dropDownList(
                        $engineOptions,
                        [
                            'id' => $formId . '-engine-count',
                            'data-role' => 'engine-count',
                        ]
                    ) ?>
                </div>

                <div class="col-md-3">
                    <?= $form->field($model, 'generator_count')->dropDownList(
                        $generatorOptions,
                        [
                            'id' => $formId . '-generator-count',
                            'data-role' => 'generator-count',
                        ]
                    ) ?>
                </div>

                <div class="col-md-3">
                    <?= $form->field($model, 'vessel_speed')->input(
                        'number',
                        [
                            'id' => $formId . '-speed',
                            'min' => 0,
                            'max' => '99999999.99',
                            'step' => '0.01',
                            'class' => 'form-control number-input',
                        ]
                    )->label('سرعت شناور (نات)') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- مشخصات موتورها -->
    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel text-primary">
                <strong>مشخصات موتورها</strong>
            </h4>

            <div data-role="engines"></div>
        </div>
    </div>

    <!-- مشخصات ژنراتورها -->
    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel text-primary">
                <strong>مشخصات ژنراتورها</strong>
            </h4>

            <div data-role="generators"></div>
        </div>
    </div>

    <!-- مشخصات بوتراستر -->
    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel text-primary">
                <strong>مشخصات بوتراستر</strong>
            </h4>

            <div class="row">
            <p
                class="empty-message"
                data-role="bow-thruster-empty"
            >
                برای این شناور بوتراستر انتخاب نشده است.
            </p>

            <div
                id="<?= Html::encode($formId . '-bow-thruster-details') ?>"
                data-role="bow-thruster-details"
                style="display:none"
            ></div>
        </div>
    </div>

    <!-- موجودی اولیه -->
    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel text-primary">
                <strong>موجودی اولیه</strong>
            </h4>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'initial_fuel_balance')->input(
                        'number',
                        [
                            'id' => $formId . '-fuel-balance',
                            'min' => 0,
                            'max' => '99999999.99',
                            'step' => '0.01',
                            'class' => 'form-control number-input',
                        ]
                    ) ?>
                </div>

                <div class="col-md-6">
                    <?= $form->field($model, 'initial_water_balance')->input(
                        'number',
                        [
                            'id' => $formId . '-water-balance',
                            'min' => 0,
                            'max' => '99999999.99',
                            'step' => '0.01',
                            'class' => 'form-control number-input',
                        ]
                    ) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- مصرف در حالت‌های عملیاتی -->
    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel text-primary">
                <strong>مصرف در حالت‌های عملیاتی</strong>
            </h4>

            <p class="section-note">
                نرخ مصرف ایده‌آل و تست‌شده تجهیزات را در حالت‌های عملیاتی مختلف وارد کنید (بر حسب لیتر بر ساعت).
            </p>

            <div data-role="modes"></div>
        </div>
    </div>

    <!-- توضیحات و تصویر -->
    <div class="panel panel-primary">
        <div class="panel-body">
            <h4 class="text-on-panel text-primary">
                <strong>توضیحات و تصویر شناور</strong>
            </h4>

            <div class="row">
                <div class="col-md-12">
                    <?= $form->field($model, 'description')->textarea([
                        'id' => $formId . '-description',
                        'rows' => 3,
                    ])->label('توضیحات') ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <?= $form->field($model, 'image_file')->fileInput([
                        'id' => $formId . '-image',
                        'accept' => 'image/jpeg,image/png,image/gif',
                        'data-role' => 'vessel-image-input',
                    ])
                        ->label('تصویر شناور')
                        ->hint('فرمت JPG، PNG یا GIF؛ حداکثر ۵ مگابایت') ?>
                </div>

                <div class="col-md-6" data-role="image-preview-container">
                    <?php if ($model->vessel_image): ?>
                        <p class="control-label">تصویر فعلی</p>
                        <?= Html::img($model->vessel_image, [
                            'class' => 'current-image',
                            'alt' => 'تصویر فعلی شناور',
                        ]) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(<<<JS
(function ($, config) {
    'use strict';

    if (!config) {
        return;
    }

    var form = $('#' + config.formId);

    if (!form.length) {
        return;
    }

    var cache = Object.create(null);

    var engineSelect = form.find('[data-role="engine-count"]');
    var generatorSelect = form.find('[data-role="generator-count"]');
    var bowThrusterSelect = form.find('[data-role="has-bow-thruster"]');

    var equipment = {
        engine: {
            model: 'VesselEngine',
            prefix: 'engine',
            number: 'engine_number',
            title: 'موتور',
            unit: 'اسب بخار',
            container: 'engines',
            records: config.engines || {}
        },
        generator: {
            model: 'VesselGenerator',
            prefix: 'gen',
            number: 'generator_number',
            title: 'ژنراتور',
            unit: 'کیلووات',
            container: 'generators',
            records: config.generators || {}
        }
    };

    var modes = {
        tie_up: 'Tie Up ـ بسته‌شده در اسکله',
        anchorage: 'Anchorage ـ لنگرگاه',
        standby: 'Standby ـ آماده‌باش',
        operation: 'Operation ـ عملیات'
    };

    function getValue(name, fallback) {
        if (Object.prototype.hasOwnProperty.call(cache, name)) {
            return cache[name];
        }

        return fallback === null || typeof fallback === 'undefined'
            ? ''
            : fallback;
    }

    function rememberValues() {
        form.find('[data-dynamic]').each(function () {
            cache[this.name] = $(this).val();
        });
    }

    function appendHidden(parent, name, value) {
        $('<input>', {
            type: 'hidden',
            name: name,
            'data-dynamic': '1'
        })
            .val(getValue(name, value))
            .appendTo(parent);
    }

    function appendField(
        parent,
        name,
        label,
        value,
        options,
        columnClass
    ) {
        var id = config.formId + '-'
            + name.replace(/[^a-zA-Z0-9_-]/g, '-');

        var column = $('<div>', {
            class: columnClass || 'col-md-3'
        });

        var group = $('<div>', {
            class: 'form-group'
        });

        var settings = $.extend({
            type: 'number',
            min: 0,
            step: '0.01',
            max: '99999999.99'
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

        var labelElement = $('<label>', {
            for: id,
            class: 'control-label'
        }).text(label);

        if (settings.required) {
            input.attr('aria-required', 'true').attr('required', 'required');

            $('<span>', {
                class: 'required-mark',
                'aria-hidden': 'true'
            }).text('*').appendTo(labelElement);
        }

        labelElement.appendTo(group);
        group.append(input);

        column.append(group).appendTo(parent);
    }

    function renderEquipment(type, count) {
        var item = equipment[type];

        var container = form
            .find('[data-role="' + item.container + '"]')
            .empty();

        if (!count) {
            $('<p>', {
                class: 'empty-message'
            })
                .text(
                    'برای این شناور '
                    + item.title
                    + ' انتخاب نشده است.'
                )
                .appendTo(container);

            return;
        }

        for (var number = 1; number <= count; number++) {
            var data = item.records[number] || {};
            var base = item.model + '[' + number + ']';

            var section = $('<div>', {
                class: 'equipment-item'
            });

            var row = $('<div>', {
                class: 'row'
            });

            $('<h5>', {
                class: 'equipment-title'
            })
                .text(item.title + ' شماره ' + number)
                .appendTo(section);

            appendHidden(
                section,
                base + '[id]',
                data.id
            );

            appendHidden(
                section,
                base + '[' + item.number + ']',
                number
            );

            appendField(
                row,
                base + '[' + item.prefix + '_power]',
                'قدرت (' + item.unit + ')',
                data[item.prefix + '_power']
            );

            appendField(
                row,
                base + '[' + item.prefix + '_type]',
                'نوع ' + item.title,
                data[item.prefix + '_type'],
                {
                    type: 'text',
                    maxlength: 50
                }
            );

            appendField(
                row,
                base + '[' + item.prefix + '_year]',
                'سال ساخت',
                data[item.prefix + '_year'],
                {
                    min: 1,
                    max: 9999,
                    step: 1
                }
            );

            section.append(row).appendTo(container);
        }
    }

    function renderBowThruster() {
        var container = form
            .find('[data-role="bow-thruster-details"]')
            .empty();

        var data = config.bowThruster || {};
        var base = 'VesselBowThruster';

        var section = $('<div>', {
            class: 'equipment-item bow-thruster-item'
        });

        var row = $('<div>', {
            class: 'row'
        });

        $('<h5>', {
            class: 'equipment-title'
        })
            .text('مشخصات فنی بوتراستر')
            .appendTo(section);

        appendHidden(
            section,
            base + '[id]',
            data.id
        );

        appendField(
            row,
            base + '[power_kw]',
            'قدرت (کیلووات)',
            data.power_kw
        );

        appendField(
            row,
            base + '[engine_type]',
            'نوع موتور',
            data.engine_type,
            {
                type: 'text',
                maxlength: 50
            }
        );

        appendField(
            row,
            base + '[manufacture_year]',
            'سال ساخت',
            data.manufacture_year,
            {
                min: 1,
                max: 9999,
                step: 1
            }
        );

        section.append(row).appendTo(container);

        toggleBowThruster();
    }

    function toggleBowThruster() {
        var enabled = bowThrusterSelect.val() === '1';

        var container = form.find(
            '[data-role="bow-thruster-details"]'
        );

        container.find(':input').prop('disabled', !enabled);

        container
            .toggle(enabled)
            .attr('aria-hidden', enabled ? 'false' : 'true');

        form.find('[data-role="bow-thruster-empty"]')
            .toggle(!enabled);
    }

    function renderModes(engineCount, generatorCount) {
        var container = form
            .find('[data-role="modes"]')
            .empty();

        if (!engineCount && !generatorCount) {
            $('<p>', {
                class: 'empty-message'
            })
                .text('ابتدا تعداد موتور یا ژنراتور را انتخاب کنید.')
                .appendTo(container);

            return;
        }

        $.each(modes, function (mode, title) {
            var section = $('<div>', {
                class: 'mode-item'
            });

            var row = $('<div>', {
                class: 'row'
            });

            var data = (config.modes && config.modes[mode]) ? config.modes[mode] : {};

            $('<h5>', {
                class: 'mode-title'
            })
                .text(title)
                .appendTo(section);

            $.each({
                engine: engineCount,
                generator: generatorCount
            }, function (type, count) {
                for (var number = 1; number <= count; number++) {
                    var key = type + '_' + number;
                    var itemData = data[key] || {};

                    var nameIdeal = 'VesselFuelConsumptionMode['
                        + mode
                        + ']['
                        + key
                        + '][ideal]';

                    var nameTested = 'VesselFuelConsumptionMode['
                        + mode
                        + ']['
                        + key
                        + '][tested]';

                    // فیلد ایده آل
                    appendField(
                        row,
                        nameIdeal,
                        equipment[type].title
                            + ' '
                            + number
                            + ' ایده‌آل (لیتر/ساعت)',
                        itemData.ideal,
                        {},
                        'col-md-3'
                    );

                    // فیلد تست شده
                    appendField(
                        row,
                        nameTested,
                        equipment[type].title
                            + ' '
                            + number
                            + ' تست‌شده (لیتر/ساعت)',
                        itemData.tested,
                        {},
                        'col-md-3'
                    );
                }
            });

            section.append(row).appendTo(container);
        });
    }

    function renderEquipmentSections() {
        var engineCount = parseInt(engineSelect.val(), 10) || 0;
        var generatorCount = parseInt(generatorSelect.val(), 10) || 0;

        rememberValues();

        renderEquipment('engine', engineCount);
        renderEquipment('generator', generatorCount);
        renderModes(engineCount, generatorCount);
    }

    form.on('change', '[data-role="vessel-image-input"]', function () {
        var input = this;
        var container = form.find('[data-role="image-preview-container"]');

        if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function (e) {
                container.html(
                    '<p class="control-label">پیش‌نمایش تصویر جدید</p>' +
                    '<img src="' + e.target.result + '" class="preview-image" alt="پیش‌نمایش تصویر">'
                );
            };

            reader.readAsDataURL(input.files[0]);
        }
    });

    form.off('.vesselForm');

    form.on(
        'change.vesselForm',
        '[data-role="engine-count"], [data-role="generator-count"]',
        renderEquipmentSections
    );

    form.on(
        'change.vesselForm',
        '[data-role="has-bow-thruster"]',
        toggleBowThruster
    );

    form.on('beforeSubmit.vesselForm', function () {
        toggleBowThruster();

        if (!form[0].checkValidity()) {
            if (form[0].reportValidity) {
                form[0].reportValidity();
            }

            return false;
        }

        var modal = form.closest('.modal');

        if (
            !modal.length
            || typeof window.submitVesselForm !== 'function'
        ) {
            form.find('.vessel-save-errors')
                .text(
                    'ارسال فرم مقداردهی نشده است. '
                    + 'صفحه را دوباره بارگذاری کنید.'
                )
                .show();

            return false;
        }

        window.submitVesselForm('#' + modal.attr('id'));

        return false;
    });

    renderEquipmentSections();
    renderBowThruster();

})(jQuery, {$configJson});
JS
);
?>