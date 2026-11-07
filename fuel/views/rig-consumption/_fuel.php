<?php

use yii\helpers\Html;
use app\modules\fuel\models\RigFuelConsumption;

/** @var app\modules\fuel\models\RigFuelConsumption $model */
/** @var array $fuelSummary */
/** @var array $waterSummary */

// دریافت لیست دسته‌بندی‌شده دکل‌ها و شناورها همراه با گزینه سایر
$equipmentList = RigFuelConsumption::getTargetEquipmentList();

/**
 * تابع کمکی جهت رندر یکنواخت فیلدهای ورودی (Text, Number, Dropdown)
 */
$renderInput = function ($label, $name, $value, $type = 'text', $options = [], $items = []) use ($model) {
    $id = 'rig-' . trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-');
    $error = $model->getFirstError($name);

    $options = array_merge([
        'id' => $id,
        'class' => 'form-control',
        'dir' => $type === 'number' ? 'ltr' : 'rtl',
    ], $options);

    if ($type === 'number') {
        $options = array_merge([
            'min' => 0,
            'max' => 99999999.99,
            'step' => '0.01',
        ], $options);
    }

    // انتخاب نوع المان ورودی (Dropdown یا Input معمولی)
    if ($type === 'select') {
        $inputHtml = Html::dropDownList($name, $value ?? '', $items, $options);
    } else {
        $inputHtml = Html::input($type, $name, $value ?? '', $options);
    }

    return Html::tag(
        'div',
        Html::label($label, $id, ['class' => 'control-label']) .
        $inputHtml .
        ($error
            ? Html::tag('div', Html::encode($error), ['class' => 'help-block'])
            : ''),
        ['class' => 'form-group' . ($error ? ' has-error' : '')]
    );
};

$resources = [
    'fuel' => [
        'label' => 'سوخت',
        'input' => $model->fuelBalanceInput,
        'summary' => $fuelSummary,
    ],
    'water' => [
        'label' => 'آب',
        'input' => $model->waterBalanceInput,
        'summary' => $waterSummary,
    ],
];

$cards = [
    'opening_balance' => 'موجودی اولیه',
    'received' => 'دریافتی',
    'used' => 'مصرف',
    'transferred' => 'انتقالی',
    'closing_balance' => 'موجودی نهایی',
];
?>

<div class="panel panel-primary">
    <div class="panel-body">
        <h4 class="section-title">موجودی لحظه‌ای سوخت و آب</h4>

        <?php foreach ($resources as $resource => $data): ?>
            <?php
            $input = $data['input'];
            $label = $data['label'];
            $prefix = $resource . '_balance';
            ?>

            <section class="resource-section" data-resource="<?= $resource ?>">
                <h5 class="equipment-heading">
                    <?= Html::encode('موجودی لحظه‌ای ' . $label) ?>
                </h5>

                <div class="balance-grid">
                    <?php foreach ($cards as $key => $title): ?>
                        <div class="balance-card">
                            <span class="balance-label">
                                <?= Html::encode($title . ' ' . $label) ?>
                            </span>

                            <span
                                class="balance-value"
                                data-balance="<?= $resource . '.' . $key ?>"
                            ><?= $model->getSummaryReady()
                                    ? Html::encode(number_format($data['summary'][$key], 2, '.', ''))
                                    : '—' ?></span>

                            <span class="balance-unit">لیتر</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($resource === 'water'): ?>
                    <div class="row">
                        <div class="col-md-3 pull-right">
                            <?= $renderInput(
                                'مصرف واقعی آب ـ لیتر',
                                'water_balance[used]',
                                $input['used'],
                                'number',
                                ['required' => true]
                            ) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="resource-master-box">
                    <?= Html::checkbox(
                        $prefix . '[enabled]',
                        (string) $input['enabled'] === '1',
                        [
                            'value' => '1',
                            'uncheck' => '0',
                            'class' => 'resource-master',
                            'label' => 'آیا دریافت یا انتقال ' . $label . ' دارید؟',
                        ]
                    ) ?>

                    <?php if ($error = $model->getFirstError($prefix . '[enabled]')): ?>
                        <div class="help-block text-danger">
                            <?= Html::encode($error) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="resource-operations">
                    <?php foreach ([
                        'receive' => [
                            'title' => 'دریافت',
                            'amount' => 'received',
                            'party' => 'received_from',
                            'party_label' => 'مبدأ دریافت',
                            'desc' => 'received_from_desc',
                        ],
                        'transfer' => [
                            'title' => 'انتقال',
                            'amount' => 'transferred',
                            'party' => 'transferred_to',
                            'party_label' => 'مقصد انتقال',
                            'desc' => 'transferred_to_desc',
                        ],
                    ] as $operation => $spec): ?>
                        <div class="operation-box">
                            <?= Html::checkbox(
                                $prefix . '[' . $operation . '_enabled]',
                                (string) $input[$operation . '_enabled'] === '1',
                                [
                                    'value' => '1',
                                    'uncheck' => '0',
                                    'class' => 'operation-toggle',
                                    'label' => $spec['title'] . ' ' . $label,
                                ]
                            ) ?>

                            <div class="operation-fields">
                                <div class="row">
                                    <div class="col-md-4">
                                        <?= $renderInput(
                                            'مقدار ' . $spec['title'] . ' ـ لیتر',
                                            $prefix . '[' . $spec['amount'] . ']',
                                            $input[$spec['amount']],
                                            'number',
                                            ['min' => '0.01', 'required' => true]
                                        ) ?>
                                    </div>

                                    <div class="col-md-4">
                                        <!-- تبدیل فیلد مبدأ/مقصد به Dropdown قابل انتخاب -->
                                        <?= $renderInput(
                                            $spec['party_label'],
                                            $prefix . '[' . $spec['party'] . ']',
                                            $input[$spec['party']],
                                            'select',
                                            [
                                                'prompt' => 'انتخاب ' . $spec['party_label'] . '...',
                                                'class' => 'form-control target-equipment-select',
                                                'required' => true
                                            ],
                                            $equipmentList
                                        ) ?>
                                    </div>

                                    <div class="col-md-4">
                                        <?= $renderInput(
                                            'شرح / شماره رسید یا حواله',
                                            $prefix . '[' . $spec['desc'] . ']',
                                            $input[$spec['desc']],
                                            'text',
                                            [
                                                'maxlength' => 10000,
                                                'placeholder' => 'در صورت انتخاب «سایر»، درج نام الزامی است'
                                            ]
                                        ) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <p class="text-muted">
            موجودی نهایی = موجودی اولیه + دریافت − مصرف واقعی − انتقال
        </p>
    </div>
</div>

<?php
// اسکریپت کنترل تعاملی فرم تراز
$this->registerJs(<<<JS
$(document).ready(function() {
    // کنترل فعال/غیرفعال بودن باکس عملیات
    $('.resource-master').on('change', function() {
        var isChecked = $(this).is(':checked');
        var section = $(this).closest('.resource-section');
        section.find('.resource-operations').toggle(isChecked);
    }).trigger('change');

    $('.operation-toggle').on('change', function() {
        var isChecked = $(this).is(':checked');
        $(this).closest('.operation-box').find('.operation-fields').toggle(isChecked);
    }).trigger('change');
});
JS
);
?>