<?php

use app\components\PageHeader;
use app\components\GridViewBox;
use app\components\jDateTime;
use app\modules\fuel\models\VesselFuelConsumption;
use kartik\grid\GridView;
use yii\data\ArrayDataProvider;
use yii\helpers\Html;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $rigConsumptionProvider */
/** @var yii\data\ActiveDataProvider $vesselConsumptionProvider */

$this->title = 'مدیریت سوخت';
$this->params['breadcrumbs'][] = $this->title;

/*
 * تبدیل تاریخ ایجاد به timestamp:
 * پشتیبانی از Unix timestamp و تاریخ متنی دیتابیس.
 */
$toTimestamp = static function ($value) {
    if ($value === null || $value === '') {
        return 0;
    }

    return is_numeric($value)
        ? (int) $value
        : (strtotime($value) ?: 0);
};

/*
 * کلید ترکیبی برای جلوگیری از تداخل شناسه گزارش دکل و شناور.
 */
$allRecords = [];

foreach ($rigConsumptionProvider->getModels() as $record) {
    $allRecords[] = [
        'key' => 'rig-' . $record->id,
        'type' => 'rig',
        'data' => $record,
        'createdTimestamp' => $toTimestamp($record->created_at),
    ];
}

foreach ($vesselConsumptionProvider->getModels() as $record) {
    $allRecords[] = [
        'key' => 'vessel-' . $record->id,
        'type' => 'vessel',
        'data' => $record,
        'createdTimestamp' => $toTimestamp($record->created_at),
    ];
}

usort($allRecords, static function ($a, $b) {
    $comparison = $b['createdTimestamp'] <=> $a['createdTimestamp'];

    if ($comparison !== 0) {
        return $comparison;
    }

    $comparison = (int) $b['data']->id <=> (int) $a['data']->id;

    return $comparison !== 0
        ? $comparison
        : strcmp($a['type'], $b['type']);
});

$dataProvider = new ArrayDataProvider([
    'allModels' => $allRecords,
    'key' => 'key',
    'sort' => false,
    'pagination' => [
        'pageSize' => 50,
    ],
]);

$numberColumn = static function ($attribute, $label) {
    return [
        'label' => $label,
        'value' => static function ($model) use ($attribute) {
            return $model['data']->$attribute;
        },
        'format' => ['decimal', 2],
        'headerOptions' => [
            'class' => 'fuel-number-heading',
        ],
        'contentOptions' => [
            'class' => 'fuel-number-cell',
        ],
    ];
};

$this->registerCss(<<<'CSS'
.fuel-list-page {
    direction: rtl;
}

/* نوار ابزار */
.fuel-list-page .fuel-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: -5px;
    margin-bottom: 15px;
}

.fuel-list-page .fuel-toolbar-group {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.fuel-list-page .fuel-toolbar .btn {
    margin: 0;
    padding: 6px 11px;
    border-radius: 4px;
    font-size: 12px;
    line-height: 1.6;
}

.fuel-list-page .fuel-toolbar .btn i {
    margin-left: 5px;
}

.fuel-list-page .fuel-create-button {
    color: #fff;
    background: #245d91;
    border: 1px solid #245d91;
}

.fuel-list-page .fuel-create-button:hover,
.fuel-list-page .fuel-create-button:focus {
    color: #fff;
    background: #19476f;
    border-color: #19476f;
}

.fuel-list-page .fuel-base-button {
    color: #425466;
    background: #f4f6f8;
    border: 1px solid #d5dee7;
}

.fuel-list-page .fuel-base-button:hover,
.fuel-list-page .fuel-base-button:focus {
    color: #234563;
    background: #e7edf3;
    border-color: #bac9d7;
}

.fuel-list-page .fuel-toolbar .btn:focus-visible {
    outline: 3px solid #90bde6;
    outline-offset: 2px;
}

/* جدول */
.fuel-list-page .grid-view {
    padding-top: 0;
}

.fuel-list-page .fuel-grid-table {
    margin-bottom: 12px;
    border-color: #d8e2ec;
    font-size: 12px;
}

.fuel-list-page .fuel-grid-table > thead > tr > th {
    padding: 12px 10px;
    color: #234563;
    background: #edf3f8;
    border-color: #d8e2ec;
    border-bottom: 2px solid #c3d4e4;
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
    font-weight: 700;
}

.fuel-list-page .fuel-grid-table > tbody > tr > td {
    padding: 10px;
    color: #344454;
    border-color: #e2e8ef;
    vertical-align: middle;
    line-height: 1.8;
}

.fuel-list-page .fuel-grid-table > tbody > tr:nth-child(even) > td {
    background: #f8fafc;
}

.fuel-list-page .fuel-grid-table > tbody > tr:hover > td {
    background: #edf5fc;
}

.fuel-list-page .fuel-number-cell,
.fuel-list-page .fuel-date-cell {
    direction: ltr;
    text-align: center;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.fuel-list-page .fuel-center-cell {
    text-align: center;
    white-space: nowrap;
}

.fuel-list-page .fuel-name-cell {
    min-width: 160px;
}

.fuel-list-page .fuel-text-cell {
    min-width: 120px;
}

/* نشان نوع تجهیز و وضعیت */
.fuel-list-page .fuel-badge {
    display: inline-block;
    min-width: 55px;
    padding: 3px 9px;
    border: 1px solid transparent;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
    text-align: center;
    white-space: nowrap;
}

.fuel-list-page .fuel-badge-rig {
    color: #24547d;
    background: #eaf2fa;
    border-color: #cbdff0;
}

.fuel-list-page .fuel-badge-vessel {
    color: #246465;
    background: #eaf5f3;
    border-color: #c9e4df;
}

.fuel-list-page .fuel-badge-on {
    color: #28613c;
    background: #edf6ef;
    border-color: #cce3d2;
}

.fuel-list-page .fuel-badge-off {
    color: #596575;
    background: #f0f2f5;
    border-color: #dce1e7;
}

.fuel-list-page .fuel-grid-summary {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 12px;
}

.fuel-list-page .pagination {
    margin: 8px 0 0;
}

.fuel-list-page .pagination > .active > a,
.fuel-list-page .pagination > .active > span {
    background: #245d91;
    border-color: #245d91;
}

.fuel-list-page .fuel-flash {
    margin-bottom: 15px;
    border-radius: 5px;
}

@media (max-width: 767px) {
    .fuel-list-page .fuel-toolbar {
        align-items: stretch;
    }

    .fuel-list-page .fuel-toolbar-group {
        width: 100%;
    }

    .fuel-list-page .fuel-toolbar .btn {
        flex: 1 1 auto;
        text-align: center;
    }
}
CSS
);

echo PageHeader::widget([
    'title' => $this->title,
    'icon' => 'glyphicon glyphicon-tint',
]);
?>

<div class="fuel-list-page">
    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="alert alert-success fuel-flash" role="status">
            <?= Html::encode(Yii::$app->session->getFlash('success')) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <?php GridViewBox::begin(); ?>

        <div class="fuel-toolbar">
            <div class="fuel-toolbar-group">
                <?= Html::a(
                    '<i class="fa-plus" aria-hidden="true"></i> ثبت مصرف دکل',
                    ['/fuel/rig-consumption/create'],
                    [
                        'class' => 'btn btn-sm btn-mini fuel-create-button',
                        'data-pjax' => '0',
                    ]
                ) ?>

                <?= Html::a(
                    '<i class="fa-plus" aria-hidden="true"></i> ثبت مصرف شناور',
                    ['/fuel/vessel-consumption/create'],
                    [
                        'class' => 'btn btn-sm btn-mini fuel-create-button',
                        'data-pjax' => '0',
                    ]
                ) ?>
            </div>

            <div class="fuel-toolbar-group">
                <?= Html::a(
                    'مدیریت دکل‌ها',
                    ['/fuel/rig/list'],
                    [
                        'class' => 'btn btn-sm btn-mini btn-gray fuel-base-button',
                        'data-pjax' => '0',
                    ]
                ) ?>

                <?= Html::a(
                    'مدیریت شناورها',
                    ['/fuel/vessel/list'],
                    [
                        'class' => 'btn btn-sm btn-mini btn-gray fuel-base-button',
                        'data-pjax' => '0',
                    ]
                ) ?>

                <?= Html::a(
                    'لوکیشن‌ها',
                    ['/fuel/location/list'],
                    [
                        'class' => 'btn btn-sm btn-mini btn-gray fuel-base-button',
                        'data-pjax' => '0',
                    ]
                ) ?>

                <?= Html::a(
                    'فعالیت‌ها',
                    ['/fuel/activity/list'],
                    [
                        'class' => 'btn btn-sm btn-mini btn-gray fuel-base-button',
                        'data-pjax' => '0',
                    ]
                ) ?>
            </div>
        </div>

        <?php Pjax::begin([
            'id' => 'fuel-list-pjax',
            'timeout' => 10000,
            'enablePushState' => true,
        ]); ?>

        <?= GridView::widget([
            'id' => 'grid-fuel-list',
            'dataProvider' => $dataProvider,

            // PJAX توسط ویجت بیرونی مدیریت می‌شود.
            'pjax' => false,

            'floatHeader' => true,
            'responsive' => true,
            'bordered' => true,
            'striped' => false,
            'hover' => true,
            'condensed' => false,

            'tableOptions' => [
                'class' => 'table table-bordered fuel-grid-table',
            ],

            'rowOptions' => static function ($model, $key, $index, $grid) {
                return [
                    'id' => 'fuel-row-' . $model['key'],
                    'class' => $index % 2 ? 'odd' : 'even',
                ];
            },

            'pager' => [
                'firstPageLabel' => 'ابتدا',
                'lastPageLabel' => 'انتها',
                'prevPageLabel' => 'قبلی',
                'nextPageLabel' => 'بعدی',
                'maxButtonCount' => 5,
            ],

            'columns' => [
                [
                    'label' => 'شناسه گزارش',
                    'value' => static function ($model) {
                        $prefix = $model['type'] === 'rig' ? 'R' : 'V';

                        return $prefix . '-' . $model['data']->id;
                    },
                    'contentOptions' => [
                        'class' => 'fuel-center-cell',
                        'dir' => 'ltr',
                    ],
                ],
                [
                    'label' => 'تاریخ گزارش',
                    'value' => static function ($model) {
                        $date = $model['data']->date ?? null;

                        if (!$date) {
                            return '-';
                        }

                        $timestamp = strtotime($date);

                        return $timestamp !== false
                            ? jDateTime::date('Y/m/d', $timestamp)
                            : '-';
                    },
                    'contentOptions' => [
                        'class' => 'fuel-date-cell',
                    ],
                ],
                [
                    'label' => 'نوع تجهیز',
                    'format' => 'raw',
                    'value' => static function ($model) {
                        $isRig = $model['type'] === 'rig';

                        return Html::tag(
                            'span',
                            $isRig ? 'دکل' : 'شناور',
                            [
                                'class' => 'fuel-badge ' . (
                                    $isRig
                                        ? 'fuel-badge-rig'
                                        : 'fuel-badge-vessel'
                                ),
                            ]
                        );
                    },
                    'contentOptions' => [
                        'class' => 'fuel-center-cell',
                    ],
                ],
                [
                    'label' => 'نام تجهیز',
                    'value' => static function ($model) {
                        $record = $model['data'];

                        if ($model['type'] === 'rig') {
                            return isset($record->rig)
                                ? ($record->rig->name ?? '-')
                                : '-';
                        }

                        return isset($record->vessel)
                            ? ($record->vessel->name ?? '-')
                            : '-';
                    },
                    'contentOptions' => [
                        'class' => 'fuel-name-cell',
                    ],
                ],

                $numberColumn(
                    'fuel_consumption_user_input',
                    'مصرف واقعی سوخت (لیتر)'
                ),

                $numberColumn(
                    'water_consumption_user_input',
                    'مصرف واقعی آب (لیتر)'
                ),

                $numberColumn(
                    'fuel_closing_balance',
                    'موجودی نهایی سوخت (لیتر)'
                ),

                $numberColumn(
                    'water_closing_balance',
                    'موجودی نهایی آب (لیتر)'
                ),

                $numberColumn(
                    'operating_hours',
                    'کارکرد (ساعت)'
                ),

                [
                    'label' => 'وضعیت قراردادی',
                    'format' => 'raw',
                    'value' => static function ($model) {
                        if ($model['type'] !== 'vessel') {
                            return '-';
                        }

                        $status = $model['data']->status;

                        if ($status === VesselFuelConsumption::STATUS_ON_HIRE) {
                            return Html::tag('span', 'On Hire', [
                                'class' => 'fuel-badge fuel-badge-on',
                                'dir' => 'ltr',
                            ]);
                        }

                        if ($status === VesselFuelConsumption::STATUS_OFF_HIRE) {
                            return Html::tag('span', 'Off Hire', [
                                'class' => 'fuel-badge fuel-badge-off',
                                'dir' => 'ltr',
                            ]);
                        }

                        return '-';
                    },
                    'contentOptions' => [
                        'class' => 'fuel-center-cell',
                    ],
                ],
                [
                    'label' => 'لوکیشن',
                    'value' => static function ($model) {
                        $record = $model['data'];

                        return isset($record->location)
                            ? ($record->location->name ?? '-')
                            : '-';
                    },
                    'contentOptions' => [
                        'class' => 'fuel-text-cell',
                    ],
                ],
                [
                    'label' => 'فعالیت',
                    'value' => static function ($model) {
                        $record = $model['data'];

                        return isset($record->activity)
                            ? ($record->activity->name ?? '-')
                            : '-';
                    },
                    'contentOptions' => [
                        'class' => 'fuel-text-cell',
                    ],
                ],
                [
                    'label' => 'کاربر ایجادکننده',
                    'value' => static function ($model) {
                        $record = $model['data'];

                        return isset($record->createdByUser)
                            ? $record->createdByUser->getFullName()
                            : '-';
                    },
                    'contentOptions' => [
                        'class' => 'fuel-text-cell',
                    ],
                ],
                [
                    'label' => 'تاریخ ایجاد',
                    'value' => static function ($model) {
                        return $model['createdTimestamp']
                            ? jDateTime::date(
                                'Y/m/d H:i',
                                $model['createdTimestamp']
                            )
                            : '-';
                    },
                    'contentOptions' => [
                        'class' => 'fuel-date-cell',
                    ],
                ],
            ],
        ]) ?>

        <?php Pjax::end(); ?>

        <?php GridViewBox::end(); ?>
    </div>
</div>