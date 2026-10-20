<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $itemRows array */
/* @var $modeOptions array */

// فیلتر کردن فقط برای موتورها و ژنراتورها
$equipmentRows = array_filter($itemRows, function($row) {
    return isset($row['group']) && $row['group'] === 'equipment';
});
?>
<fieldset class="vc-box">
    <legend>اطلاعات تجهیزات شناور و کارکرد</legend> 
    
    <div class="table-responsive">
        <table class="table table-bordered table-striped vc-table vessel-equipment-table">
            <thead>
                <tr>
                    <th style="width: 30%;">تجهیز</th>
                    <th style="width: 25%;">مبنای نرخ / حالت کاری</th>
                    <th style="width: 20%;">کارکرد (دقیقه)</th>
                    <th style="width: 25%;">مصرف محاسبه‌شده (لیتر)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($equipmentRows as $item): ?>
                    <?php 
                        // استخراج نرخ‌های حالت‌های کاری
                        $ratesMap = isset($item['mode_rates']) && is_array($item['mode_rates']) ? $item['mode_rates'] : [];
                        $currentRate = isset($item['consumption_rate']) ? (float)$item['consumption_rate'] : 0;
                        
                        // تنظیم دقیق data-rate برای هر گزینه
                        $optionOptions = [];
                        if (!empty($modeOptions)) {
                            foreach ($modeOptions as $modeKey => $modeLabel) {
                                $rateValue = isset($ratesMap[$modeKey]) ? $ratesMap[$modeKey] : $currentRate;
                                $optionOptions[$modeKey] = [
                                    'data-rate' => $rateValue,
                                ];
                            }
                        }
                    ?>
                    <tr class="equipment-row" data-rate="<?= Html::encode($currentRate) ?>">
                        <td>
                            <strong><?= Html::encode($item['title']) ?></strong>
                            <?php if (!empty($item['specification'])): ?>
                                <div class="small text-muted"><?= Html::encode($item['specification']) ?></div>
                            <?php endif; ?>
                            
                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][id]", isset($item['id']) ? $item['id'] : '') ?>
                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][engine_id]", isset($item['engine_id']) ? $item['engine_id'] : '') ?>
                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][generator_id]", isset($item['generator_id']) ? $item['generator_id'] : '') ?>
                            <?= Html::hiddenInput("VesselConsumptionItem[{$item['index']}][auxiliary_code]", isset($item['auxiliary_code']) ? $item['auxiliary_code'] : '') ?>
                        </td>

                        <td>
                            <?php if (!empty($modeOptions)): ?>
                                <?= Html::dropDownList(
                                    "VesselConsumptionItem[{$item['index']}][operation_mode]",
                                    isset($item['operation_mode']) ? $item['operation_mode'] : null,
                                    $modeOptions,
                                    [
                                        'class' => 'form-control equipment-mode-select',
                                        'prompt' => 'انتخاب حالت کاری...',
                                        'options' => $optionOptions,
                                    ]
                                ) ?>
                            <?php else: ?>
                                <span class="badge badge-info">نرخ: <?= Html::encode($currentRate) ?></span>
                            <?php endif; ?>
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
                                    'class' => 'form-control text-center equipment-actual-consumption-input',
                                    'readonly' => true,
                                    'style' => 'background-color: #eef9f2; font-weight: bold;',
                                    'placeholder' => 'محاسبه خودکار',
                                ]
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</fieldset>

