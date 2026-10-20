<?php
use yii\helpers\Html;

/** @var app\modules\fuel\models\RigFuelConsumption $model */
/** @var array $equipmentRows */

$groups = [
    'engines' => ['type' => 'engine', 'label' => 'موتورها'],
    'generators' => ['type' => 'generator', 'label' => 'ژنراتورها'],
];
?>

<div class="panel panel-primary">
    <div class="panel-body">
        <h4 class="section-title">اطلاعات کارکرد موتور و ژنراتور</h4>

        <?php foreach ($groups as $key => $group): ?>
            <?php if (empty($equipmentRows[$key])) continue; ?>

            <h5 class="equipment-heading"><?= Html::encode($group['label']) ?></h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>تجهیز</th>
                            <th>قدرت</th>
                            <th>نرخ ایده‌آل (L/h)</th>
                            <th>نرخ تست‌شده (L/h)</th>
                            <th>ساعت کارکرد</th>
                            <th>مصرف محاسبه‌شده (لیتر)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipmentRows[$key] as $row): ?>
                            <?php
                            $type = $group['type'];
                            $number = $row['number'];
                            $hours = $model->getEquipmentValue($type, $number, 'hours');
                            $calculatedConsumption = round($hours * ($row['tested_rate'] ?? 0), 2);
                            ?>
                            <tr>
                                <td><?= Html::encode($row['title']) ?> (<?= Html::encode($row['model']) ?>)</td>
                                <td><?= Html::encode($row['power']) ?> <?= Html::encode($row['power_unit']) ?></td>
                                <td><?= Html::encode($row['rate']) ?></td>
                                <td><strong><?= Html::encode($row['tested_rate']) ?></strong></td>
                                <td>
                                    <?= Html::input('number', "equipment[{$type}][{$number}][hours]", $hours, [
                                        'class' => 'form-control equipment-hours-input',
                                        'data-tested-rate' => $row['tested_rate'],
                                        'min' => 0,
                                        'max' => 24,
                                        'step' => '0.01',
                                        'dir' => 'ltr',
                                        'required' => true,
                                    ]) ?>
                                </td>
                                <td>
                                    <?= Html::input('text', "equipment[{$type}][{$number}][consumption_user]", $calculatedConsumption, [
                                        'class' => 'form-control equipment-consumption-output',
                                        'readonly' => true,
                                        'dir' => 'ltr',
                                    ]) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
$this->registerJs(<<<JS
// شنیدن هر دو رویداد input و change تا با پر شدن مقادیر از طریق JS هم محاسبه شود
$(document).on('input change', '.equipment-hours-input', function() {
    var hours = parseFloat($(this).val()) || 0;
    var rate = parseFloat($(this).data('tested-rate')) || 0;
    var total = (hours * rate).toFixed(2);
    $(this).closest('tr').find('.equipment-consumption-output').val(total);
});
JS
);
?>