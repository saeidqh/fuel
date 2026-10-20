<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $itemRows array */

// فیلتر کردن فقط تجهیزات جانبی
$extraRows = array_filter($itemRows, function($row) {
    return isset($row['group']) && $row['group'] === 'extra';
});

if (empty($extraRows)) {
    return;
}
?>
<fieldset class="vc-box">
    <legend>تجهیزات جانبی و متفرقه</legend>

    <div class="table-responsive">
        <table class="table table-bordered table-striped vc-table vessel-extra-table">
            <thead>
                <tr>
                    <th style="width: 35%;">تجهیز</th>
                    <th style="width: 20%;">کارکرد (دقیقه)</th>
                    <th style="width: 20%;">مصرف (لیتر - دستی)</th>
                    <th style="width: 25%;">توضیحات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extraRows as $item): ?>
                    <tr class="equipment-row equipment-extra-row">
                        <td class="text-right">
                            <strong><?= Html::encode($item['title']) ?></strong>
                            <?php if (!empty($item['specification'])): ?>
                                <div class="small text-muted"><?= Html::encode($item['specification']) ?></div>
                            <?php endif; ?>

                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][id]", isset($item['id']) ? $item['id'] : '') ?>
                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][engine_id]", '') ?>
                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][generator_id]", '') ?>
                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][auxiliary_code]", isset($item['auxiliary_code']) ? $item['auxiliary_code'] : '') ?>
                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][operation_mode]", 'default') ?>
                        </td>

                        <td>
                            <?= Html::textInput(
                                "VesselConsumptionItem[{$item['index']}][operating_minutes]",
                                isset($item['operating_minutes']) ? $item['operating_minutes'] : 0,
                                [
                                    'class' => 'form-control text-center equipment-minutes-input',
                                    'type' => 'number',
                                    'min' => '0',
                                    'max' => '1440',
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= Html::textInput(
                                "VesselConsumptionItem[{$item['index']}][actual_consumption]",
                                isset($item['actual_consumption']) ? $item['actual_consumption'] : '',
                                [
                                    'class' => 'form-control text-center equipment-actual-consumption-input extra-actual-consumption',
                                    'type' => 'number',
                                    'step' => '0.01',
                                    'min' => '0',
                                    'placeholder' => 'ورودی دستی',
                                    'style' => 'background-color: #fffde7; font-weight: bold;',
                                ]
                            ) ?>
                        </td>

                        <td>
                            <?= Html::textInput(
                                "VesselConsumptionItem[{$item['index']}][description]",
                                isset($item['description']) ? $item['description'] : '',
                                [
                                    'class' => 'form-control',
                                    'maxlength' => 2000,
                                    'placeholder' => 'توضیحات اختیاری...',
                                ]
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</fieldset>