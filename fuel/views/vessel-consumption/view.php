<?php
use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\grid\GridView;
use yii\data\ArrayDataProvider;

$this->title = 'گزارش مصرف شناور شماره ' . $report->id;
?>
<div class="vessel-consumption-view">
    <h3><?= Html::encode($this->title) ?></h3>

    <p>
        <?= Html::a('ویرایش گزارش', ['update', 'id' => $report->id], [
            'class' => 'btn btn-primary',
        ]) ?>
        <?= Html::a('ثبت گزارش جدید', ['create', 'vesselId' => $report->vesselIdRef], [
            'class' => 'btn btn-success',
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $report,
        'attributes' => [
            [
                'label' => 'شناور',
                'value' => $report->vessel ? $report->vessel->name : '-',
            ],
            'started_at',
            'ended_at',
            [
                'label' => 'مدت گزارش ـ دقیقه',
                'value' => $report->getDurationMinutes(),
            ],
            [
                'attribute' => 'status',
                'value' => $report->status === 'on_hire' ? 'On Hire' : 'Off Hire',
            ],
            [
                'label' => 'لوکیشن',
                'value' => $report->location ? $report->location->name : '-',
            ],
            [
                'label' => 'فعالیت',
                'value' => $report->activity ? $report->activity->name : '-',
            ],
            'operating_hours',
            'fuel_used',
            'total_ideal_consumption',
            'consumption_difference',
            'description:ntext',
        ],
    ]) ?>

    <h4>موجودی سوخت و آب</h4>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>منبع</th>
                    <th>موجودی اولیه</th>
                    <th>دریافت</th>
                    <th>مصرف</th>
                    <th>انتقال</th>
                    <th>موجودی نهایی</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ([
                    'سوخت' => $report->getFuelBalanceSummary(),
                    'آب' => $report->getWaterBalanceSummary(),
                ] as $label => $balance): ?>
                    <tr>
                        <th><?= Html::encode($label) ?></th>
                        <?php foreach ($balance as $value): ?>
                            <td><?= Html::encode($value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h4>جزئیات مصرف تجهیزات</h4>
    <?= GridView::widget([
        'dataProvider' => new ArrayDataProvider([
            'allModels' => $report->getItems()->with(['engine', 'generator'])->all(),
            'pagination' => false,
            'sort' => false,
        ]),
        'layout' => '{items}',
        'emptyText' => 'تجهیزی در این گزارش ثبت نشده است.',
        'columns' => [
            [
                'label' => 'تجهیز',
                'value' => function ($item) {
                    return $item->getEquipmentTitle();
                },
            ],
            [
                'attribute' => 'operation_mode',
                'value' => function ($item) {
                    if ($item->auxiliary_code !== null) {
                        return '-';
                    }

                    $options = $item::getModeOptions();
                    $key = $item->operation_mode === null ? '' : $item->operation_mode;

                    return isset($options[$key]) ? $options[$key] : $key;
                },
            ],
            'operating_minutes',
            'consumption_rate',
            'actual_consumption',
            'ideal_consumption',
            [
                'label' => 'اختلاف',
                'value' => function ($item) {
                    return $item->getConsumptionDifference();
                },
            ],
            'description',
        ],
    ]) ?>

    <h4>دریافت و انتقال</h4>
    <?= GridView::widget([
        'dataProvider' => new ArrayDataProvider([
            'allModels' => $report->getTransactions()->all(),
            'pagination' => false,
            'sort' => false,
        ]),
        'layout' => '{items}',
        'emptyText' => 'دریافت یا انتقالی ثبت نشده است.',
        'columns' => [
            [
                'attribute' => 'resource_type',
                'value' => function ($transaction) {
                    return $transaction->resource_type === 'water' ? 'آب' : 'سوخت';
                },
            ],
            [
                'attribute' => 'transaction_type',
                'value' => function ($transaction) {
                    return $transaction->transaction_type === 'receive' ? 'دریافت' : 'انتقال';
                },
            ],
            'amount',
            'from_source',
            'to_destination',
            [
                'label' => 'زمان عملیات',
                'attribute' => 'occurred_at',
            ],
            'description',
        ],
    ]) ?>
</div>