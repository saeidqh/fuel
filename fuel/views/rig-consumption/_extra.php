<?php

use yii\helpers\Html;

/** @var app\modules\fuel\models\RigFuelConsumption $model */

$items = [
    'cement_unit' => 'واحد سیمان',
    'crane' => 'جرثقیل',
    'other' => 'سایر تجهیزات',
];
?>

<div class="panel panel-primary">
    <div class="panel-body">
        <h4 class="section-title">تجهیزات جانبی</h4>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>تجهیز</th>
                        <th>کارکرد ـ ساعت</th>
                        <th>مصرف واقعی سوخت ـ لیتر</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $type => $label): ?>
                        <tr>
                            <td><?= Html::encode($label) ?></td>

                            <?php foreach (['hours', 'consumption_user'] as $field): ?>
                                <?php
                                $name = "auxiliary_equipment[{$type}][{$field}]";
                                $error = $model->getFirstError($name);
                                ?>
                                <td class="<?= $error ? 'has-error' : '' ?>">
                                    <?= Html::input(
                                        'number',
                                        $name,
                                        $model->auxiliaryEquipment[$type][$field],
                                        [
                                            // اضافه کردن کلاس equipment-hours-input برای فیلد hours
                                            'class' => 'form-control' . ($field === 'hours' ? ' equipment-hours-input' : ''),
                                            'min' => 0,
                                            'max' => $field === 'hours' ? 24 : 99999999.99,
                                            'step' => '0.01',
                                            'dir' => 'ltr',
                                            'required' => true,
                                        ]
                                    ) ?>

                                    <?php if ($error): ?>
                                        <div class="help-block">
                                            <?= Html::encode($error) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>