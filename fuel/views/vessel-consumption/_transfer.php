<?php

use yii\helpers\Html;
use app\modules\fuel\models\VesselFuelConsumption; // یا مدل مربوط به شناور شما
use app\modules\fuel\models\RigFuelConsumption;

/** @var array $transactionRows */
/** @var object $report */

// دریافت لیست دسته‌بندی‌شده شناورها و دکل‌ها همراه با گزینه «سایر»
// در صورت عدم وجود متد متغیر، لیست را از مدل یا هلپر مربوطه فراخوانی کنید
$equipmentList = RigFuelConsumption::getTargetEquipmentList();

$groups = [
    'fuel' => [
        'title' => 'سوخت',
        'summaryGroup' => 'fuelSummary',
        'rows' => [],
    ],
    'water' => [
        'title' => 'آب',
        'summaryGroup' => 'waterSummary',
        'rows' => [],
    ],
];

foreach ($transactionRows as $row) {
    if (isset($groups[$row['resource_type']])) {
        $groups[$row['resource_type']]['rows'][] = $row;
    }
}

$this->registerCss(<<<'CSS'
.vessel-consumption-form .checkbox label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0;
    cursor: pointer;
}
.vessel-consumption-form .checkbox input[type="checkbox"] {
    position: static;
    float: none;
    margin: 0;
    flex-shrink: 0;
}
#vessel-transfers .vc-resource {
    margin-bottom: 15px;
    padding: 15px;
    border: 1px solid #d9e6f2;
    border-radius: 7px;
    background: #fff;
}
#vessel-transfers .vc-balance-title {
    margin: 0 0 15px;
    color: #23527c;
    font-size: 15px;
    font-weight: 700;
}
#vessel-transfers .vc-resource-title {
    margin: 0;
    color: #23527c;
    font-size: 14px;
    font-weight: 700;
}
#vessel-transfers .vc-resource-toggle {
    display: none;
    margin: 0;
    color: #23527c;
    font-weight: 700;
}
#vessel-transfers .vc-resource-body { margin-top: 15px; }
#vessel-transfers .vc-transfer:last-child { margin-bottom: 0; }
#vessel-transfers .vc-transfer > .checkbox { margin-bottom: 0; }
#vessel-transfers .vc-transaction-fields { margin-top: 15px; }
#vessel-transfers .vc-resource-operations {
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}
#vessel-transfers input[type=checkbox] { cursor: pointer; }
CSS
);
?>

<fieldset class="vc-box" id="vessel-transfers" aria-busy="false">
    <legend>موجودی لحظه‌ای سوخت و آب، دریافت و انتقال</legend>

    <div id="vessel-balance-message" class="alert alert-info" role="status" aria-live="polite">
        برای نمایش موجودی‌ها، اطلاعات گزارش را تکمیل کنید.
    </div>

    <div class="alert alert-info">
        در صورت دریافت یا انتقال، بخش مربوطه را فعال و نوع عملیات را انتخاب کنید.
        زمان عملیات، پایان گزارش در نظر گرفته می‌شود.
        انتقال خودکار بین دو شناور در این فرم فعال نیست.
    </div>

    <?php if (!$report->isNewRecord): ?>
        <div class="alert alert-warning">
            برداشتن تیک عملیات و ذخیره موفق، تراکنش مربوطه را حذف می‌کند.
            خاموش‌کردن بخش سوخت یا آب، تمام تراکنش‌های همان بخش را برای حذف انتخاب می‌کند.
        </div>
    <?php endif; ?>

    <?php foreach ($groups as $resource => $group): ?>
        <?php
        $groupEnabled = false;

        foreach ($group['rows'] as $row) {
            if ((string) $row['enabled'] === '1') {
                $groupEnabled = true;
                break;
            }
        }

        $bodyId = 'vc-resource-body-' . $resource;

        $balanceCards = [
            'opening_balance' => 'موجودی اولیه ' . $group['title'],
            'received' => $group['title'] . ' دریافتی',
            'used' => $resource === 'fuel' ? 'مصرف واقعی سوخت' : 'مصرف آب',
            'transferred' => $group['title'] . ' انتقالی',
            'closing_balance' => 'موجودی نهایی ' . $group['title'],
        ];
        ?>

        <div class="vc-resource" data-resource="<?= Html::encode($resource) ?>">
            <h4 class="vc-balance-title">
                <?= Html::encode('موجودی لحظه‌ای ' . $group['title']) ?>
            </h4>

            <div class="vc-summary-grid vc-summary-grid-balance">
                <?php foreach ($balanceCards as $key => $label): ?>
                    <div class="vc-summary-card<?= $key === 'closing_balance' ? ' vc-summary-card-final' : '' ?>">
                        <strong><?= Html::encode($label) ?></strong>
                        <span class="vc-summary-value"
                              data-summary-group="<?= Html::encode($group['summaryGroup']) ?>"
                              data-summary-key="<?= Html::encode($key) ?>">—</span>
                        <small>لیتر</small>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($resource === 'water'): ?>
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <?= $form->field($report, 'waterUsed')->textInput([
                            'inputmode' => 'decimal',
                            'dir' => 'ltr',
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="vc-resource-operations">
                <h4 class="vc-resource-title">
                    <?= Html::encode('دریافت و انتقال ' . $group['title']) ?>
                </h4>

                <div class="checkbox vc-resource-toggle">
                    <label style="color:#1b5e20; font-weight:900;">
                        <?= Html::checkbox(null, $groupEnabled, [
                            'id' => 'vc-resource-enabled-' . $resource,
                            'class' => 'js-resource-enabled',
                            'aria-controls' => $bodyId,
                        ]) ?>
                        <?= Html::encode('دریافت یا انتقال ' . $group['title'] . ' دارید؟') ?>
                    </label>
                </div>

                <div class="vc-resource-body" id="<?= Html::encode($bodyId) ?>">
                    <?php foreach ($group['rows'] as $row): ?>
                        <?php
                        $name = 'VesselTransaction[' . $row['index'] . ']';
                        $fieldsId = 'vc-transaction-fields-' . $row['index'];
                        ?>

                        <div class="vc-transfer">
                            <?php foreach (['id', 'resource_type', 'transaction_type'] as $field): ?>
                                <?= Html::hiddenInput(
                                    $name . '[' . $field . ']',
                                    $row[$field]
                                ) ?>
                            <?php endforeach; ?>

                            <div class="checkbox">
                                <label>
                                    <?= Html::checkbox(
                                        $name . '[enabled]',
                                        (string) $row['enabled'] === '1',
                                        [
                                            'id' => 'tx-enabled-' . $row['index'],
                                            'class' => 'js-transaction-enabled',
                                            'value' => '1',
                                            'uncheck' => '0',
                                            'aria-controls' => $fieldsId,
                                        ]
                                    ) ?>
                                    <strong>
                                        <?= Html::encode($row['title']) ?>
                                    </strong>
                                </label>
                            </div>

                            <div class="row vc-transaction-fields" id="<?= Html::encode($fieldsId) ?>">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <?= Html::label('مقدار ـ لیتر', 'tx-amount-' . $row['index']) ?>
                                        <?= Html::textInput(
                                            $name . '[amount]',
                                            $row['amount'],
                                            [
                                                'id' => 'tx-amount-' . $row['index'],
                                                'class' => 'form-control js-tx-input',
                                                'inputmode' => 'decimal',
                                                'dir' => 'ltr',
                                            ]
                                        ) ?>
                                    </div>
                                </div>

                                <!-- تبدیل فیلد طرف حساب (مبدأ/مقصد) به کشویی شامل شناورها، دکل‌ها و سایر -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <?= Html::label($row['partyLabel'], 'tx-party-' . $row['index']) ?>
                                        <?= Html::dropDownList(
                                            $name . '[' . $row['partyField'] . ']',
                                            $row[$row['partyField']],
                                            $equipmentList,
                                            [
                                                'id' => 'tx-party-' . $row['index'],
                                                'class' => 'form-control js-tx-input target-equipment-select',
                                                'prompt' => 'انتخاب ' . $row['partyLabel'] . '...',
                                            ]
                                        ) ?>
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <div class="form-group">
                                        <?= Html::label('جزئیات عملیات / شماره رسید', 'tx-description-' . $row['index']) ?>
                                        <?= Html::textarea(
                                            $name . '[description]',
                                            $row['description'],
                                            [
                                                'id' => 'tx-description-' . $row['index'],
                                                'class' => 'form-control js-tx-input',
                                                'rows' => 2,
                                                'placeholder' => 'در صورت انتخاب «سایر»، درج نام یا جزئیات الزامی است',
                                            ]
                                        ) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <p class="vc-note">
        موجودی نهایی = موجودی اولیه + دریافتی − مصرف واقعی − انتقالی.
        موجودی اولیه به‌صورت خودکار از موجودی پایه یا زنجیره گزارش‌های قبلی
        به دست می‌آید و در این فرم قابل ویرایش نیست.
    </p>

    <p class="vc-note">
        خاموش‌کردن دریافت و انتقال، مصرف تجهیزات یا مصرف آب را غیرفعال نمی‌کند.
        مقادیر نمایش‌داده‌شده پیش‌‌نمایش هستند و هنگام ثبت دوباره کنترل می‌شوند.
    </p>
</fieldset>

<?php
$js = <<<'JS'
(function ($) {
    function syncGroup(group) {
        var master = group.find('.js-resource-enabled');
        var isMasterChecked = master.prop('checked');

        master.attr('aria-expanded', isMasterChecked ? 'true' : 'false');
        group.find('.vc-resource-body').toggle(isMasterChecked);

        group.find('.vc-transfer').each(function () {
            var row = $(this);
            var checkbox = row.find('.js-transaction-enabled');
            
            checkbox.prop('disabled', !isMasterChecked);

            var active = isMasterChecked && checkbox.prop('checked');
            var fields = row.find('.vc-transaction-fields');

            checkbox.attr('aria-expanded', active ? 'true' : 'false');
            fields.toggle(active);

            fields.find('.js-tx-input').prop('disabled', !active);
        });
    }

    function initTransfers() {
        var root = $('#vessel-transfers');
        if (!root.length) return;

        root.find('.vc-resource').each(function () {
            var group = $(this);
            group.find('.vc-resource-title').hide();
            group.find('.vc-resource-toggle').show();
            syncGroup(group);
        });
    }

    initTransfers();

    $(document).off('.vesselTransfers').on(
        'change.vesselTransfers',
        '#vessel-transfers .js-resource-enabled, #vessel-transfers .js-transaction-enabled',
        function () {
            syncGroup($(this).closest('.vc-resource'));
        }
    );

    $(document).on('pjax:complete pjax:success', function () {
        initTransfers();
    });
})(jQuery);
JS;

$this->registerJs($js);
?>