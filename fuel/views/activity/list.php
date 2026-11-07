<?php

use app\components\PageHeader;
use app\components\GridViewBox;
use kartik\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\Pjax;

/** @var yii\web\View $this */
/** @var app\modules\fuel\models\ActivityTypeSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'مدیریت فعالیت‌ها';
$this->params['breadcrumbs'][] = ['label' => 'مدیریت سوخت', 'url' => ['/fuel/fuel/list']];
$this->params['breadcrumbs'][] = $this->title;

$css = <<<'CSS'
.fuel-list-page { direction: rtl; }
.fuel-list-page .fuel-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px; margin-top: -5px; margin-bottom: 15px;
}
.fuel-list-page .fuel-toolbar-group { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.fuel-list-page .fuel-toolbar .btn {
    margin: 0; padding: 6px 11px; border-radius: 4px; font-size: 12px; line-height: 1.6;
}
.fuel-list-page .fuel-create-button {
    color: #fff; background: #245d91; border: 1px solid #245d91;
}
.fuel-list-page .fuel-create-button:hover,
.fuel-list-page .fuel-create-button:focus {
    color: #fff; background: #19476f; border-color: #19476f;
}
.fuel-list-page .fuel-base-button {
    color: #425466; background: #f4f6f8; border: 1px solid #d5dee7;
}
.fuel-list-page .fuel-base-button:hover,
.fuel-list-page .fuel-base-button:focus {
    color: #234563; background: #e7edf3; border-color: #bac9d7;
}
.fuel-list-page .fuel-grid-table {
    margin-bottom: 12px; border-color: #d8e2ec; font-size: 12px;
}

/* ===== رنگ سرمه‌ای ستون‌ها ===== */
.fuel-list-page .fuel-grid-table > thead > tr > th,
.fuel-list-page .kv-table-header th,
.fuel-list-page .kv-grid-table thead tr th {
    padding: 12px 10px !important;
    color: #0b2545 !important;                 /* ← سرمه‌ای تیره */
    background: #d9e4f0 !important;
    border-color: #c3d4e4 !important;
    border-bottom: 2px solid #a8bdd4 !important;
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
    font-weight: 700 !important;
}

.fuel-list-page .filters td,
.fuel-list-page .filters th {
    background: #f0f4f8 !important;
    padding: 6px 4px !important;
    vertical-align: middle !important;
}
.fuel-list-page .filters .form-control {
    height: 30px;
    padding: 4px 6px;
    font-size: 12px;
    border-radius: 4px;
    border: 1px solid #c5d3e0;
}
.fuel-list-page .fuel-grid-table > tbody > tr > td {
    padding: 10px; color: #344454; border-color: #e2e8ef;
    vertical-align: middle; line-height: 1.8;
}
.fuel-list-page .fuel-grid-table > tbody > tr:nth-child(even) > td { background: #f8fafc; }
.fuel-list-page .fuel-grid-table > tbody > tr:hover > td { background: #edf5fc; }
.fuel-list-page .fuel-center-cell { text-align: center; white-space: nowrap; }
.fuel-list-page .fuel-number-cell {
    direction: ltr; text-align: center; white-space: nowrap; font-variant-numeric: tabular-nums;
}
.fuel-list-page .fuel-name-cell { min-width: 140px; }
.fuel-list-page .fuel-badge {
    display: inline-block; min-width: 55px; padding: 3px 9px;
    border: 1px solid transparent; border-radius: 5px;
    font-size: 11px; font-weight: 700; text-align: center; white-space: nowrap;
}
.fuel-list-page .fuel-badge-on { color: #28613c; background: #edf6ef; border-color: #cce3d2; }
.fuel-list-page .fuel-badge-off { color: #8a3b3b; background: #fdeeee; border-color: #f0c9c9; }
.fuel-list-page .fuel-badge-yes { color: #24547d; background: #eaf2fa; border-color: #cbdff0; }
.fuel-list-page .fuel-badge-no { color: #596575; background: #f0f2f5; border-color: #dce1e7; }
.fuel-list-page .btn-action { margin: 0 2px; padding: 4px 8px; border-radius: 4px; }
.fuel-list-page .pagination > .active > a,
.fuel-list-page .pagination > .active > span { background: #245d91; border-color: #245d91; }
.fuel-list-page .fuel-flash { margin-bottom: 15px; border-radius: 5px; }
#createModal .modal-dialog, #updateModal .modal-dialog {
    width: 75% !important; max-width: 1000px !important;
}
@media (max-width: 767px) {
    .fuel-list-page .fuel-toolbar { align-items: stretch; }
    .fuel-list-page .fuel-toolbar-group { width: 100%; }
    .fuel-list-page .fuel-toolbar .btn { flex: 1 1 auto; text-align: center; }
}
CSS;
$this->registerCss($css);

echo PageHeader::widget(['title' => $this->title, 'icon' => 'glyphicon glyphicon-tasks']);
?>

<div class="fuel-list-page">
    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="alert alert-success fuel-flash"><?= Html::encode(Yii::$app->session->getFlash('success')) ?></div>
    <?php endif; ?>

    <div class="row">
        <?php GridViewBox::begin(); ?>
        <div class="fuel-toolbar">
            <div class="fuel-toolbar-group">
                <?= Html::button('<i class="glyphicon glyphicon-plus"></i> ایجاد فعالیت جدید', [
                    'class' => 'btn btn-sm btn-mini fuel-create-button',
                    'data-toggle' => 'modal', 'data-target' => '#createModal',
                ]) ?>
            </div>
            <div class="fuel-toolbar-group">
                <?= Html::a('بازگشت به لیست سوخت', ['/fuel/fuel/list'], ['class' => 'btn btn-sm btn-mini fuel-base-button', 'data-pjax' => '0']) ?>
            </div>
        </div>

        <?php Pjax::begin(['id' => 'activity-list-pjax', 'timeout' => 10000]); ?>
        <?= GridView::widget([
            'dataProvider' => $dataProvider,
            'filterModel' => $searchModel,
            'pjax' => false,
            'responsive' => true,
            'bordered' => true,
            'hover' => true,
            'striped' => false,
            'tableOptions' => ['class' => 'table table-bordered fuel-grid-table'],
            'pager' => [
                'firstPageLabel' => 'ابتدا', 'lastPageLabel' => 'انتها',
                'prevPageLabel' => 'قبلی', 'nextPageLabel' => 'بعدی', 'maxButtonCount' => 5,
            ],
            'columns' => [
                [
                    'attribute' => 'id',
                    'label' => 'شناسه',
                    'contentOptions' => ['class' => 'fuel-center-cell', 'dir' => 'ltr'],
                    'filterInputOptions' => ['class' => 'form-control', 'placeholder' => 'شناسه'],
                ],
                [
                    'attribute' => 'name',
                    'label' => 'نام فعالیت',
                    'contentOptions' => ['class' => 'fuel-name-cell'],
                    'filterInputOptions' => ['class' => 'form-control', 'placeholder' => 'نام'],
                ],
                [
                    'attribute' => 'description',
                    'label' => 'توضیحات',
                    'format' => 'ntext',
                    'filterInputOptions' => ['class' => 'form-control', 'placeholder' => 'توضیحات'],
                ],
                [
                    'attribute' => 'created_at',
                    'label' => 'تاریخ ایجاد',
                    'filter' => false,
                    'value' => static function ($model) {
                        if (!$model->created_at || $model->created_at === '0000-00-00 00:00:00') {
                            return '-';
                        }
                        $jDate = new \app\components\jDateTime(true, true, 'Asia/Tehran');
                        $ts = strtotime($model->created_at);
                        $j = $jDate->toJalali(date('Y', $ts), date('m', $ts), date('d', $ts));
                        return $j[0] . '/' . $j[1] . '/' . $j[2] . ' ' . date('H:i', $ts);
                    },
                    'contentOptions' => ['class' => 'fuel-center-cell', 'dir' => 'ltr'],
                    'filterInputOptions' => ['class' => 'form-control', 'placeholder' => 'تاریخ'],
                ],
                [
                    'attribute' => 'created_by',
                    'label' => 'آخرین کاربر',
                    'filter' => Html::activeTextInput($searchModel, 'userName', [
                        'class' => 'form-control',
                        'placeholder' => 'نام کاربر',
                    ]),
                    'value' => static function ($model) {
                        // اگه آخرین ویرایش‌کننده وجود داشت، اسمش رو نشون بده
                        if ($model->updated_by && $model->updatedByUser) {
                            return $model->updatedByUser->getFullName();
                        }
                        // وگرنه اسم ایجادکننده
                        if ($model->createdByUser) {
                            return $model->createdByUser->getFullName();
                        }
                        return '-';
                    },
                    'contentOptions' => ['class' => 'fuel-center-cell'],
                ],
                [
                    'class' => 'yii\grid\ActionColumn',
                    'template' => '{update} {delete}',
                    'header' => 'عملیات',
                    // 'filter' => false,  ← حذف شد چون ActionColumn این پراپرتی رو نداره
                    'contentOptions' => ['class' => 'fuel-center-cell'],
                    'buttons' => [
                        'update' => static function ($url, $model) {
                            return Html::button('<i class="glyphicon glyphicon-pencil"></i>', [
                                'class' => 'btn btn-warning btn-sm btn-action',
                                'onclick' => 'loadUpdateModal(' . (int) $model->id . ')',
                            ]);
                        },
                        'delete' => static function ($url, $model) {
                            return Html::a('<i class="glyphicon glyphicon-trash"></i>', ['delete', 'id' => $model->id], [
                                'class' => 'btn btn-danger btn-sm btn-action',
                                'data' => ['confirm' => 'آیا مطمئن هستید؟', 'method' => 'post'],
                            ]);
                        },
                    ],
                ],
            ],
        ]) ?>
        <?php Pjax::end(); ?>
        <?php GridViewBox::end(); ?>
    </div>
</div>

<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">ایجاد فعالیت جدید</h5>
<button type="button" class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body"><div id="createModalContent">بارگذاری...</div></div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
<button type="button" class="btn btn-primary" onclick="submitCreateForm()">ذخیره</button>
</div></div></div></div>

<div class="modal fade" id="updateModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">ویرایش فعالیت</h5>
<button type="button" class="close" data-dismiss="modal">&times;</button></div>
<div class="modal-body"><div id="updateModalContent">بارگذاری...</div></div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
<button type="button" class="btn btn-primary" onclick="submitUpdateForm()">ذخیره</button>
</div></div></div></div>

<?php
$createUrl = Url::to(['create']);
$updateBase = Url::to(['update', 'id' => '']);

$js = <<<JS
$('#createModal').on('show.bs.modal', function () {
  $.get('{$createUrl}').done(function (r) { $('#createModalContent').html(r); });
});
window.loadUpdateModal = function (id) {
  $.get('{$updateBase}' + id).done(function (r) { $('#updateModalContent').html(r); $('#updateModal').modal('show'); });
};
window.submitCreateForm = function () {
  var form = $('#createModalContent').find('form');
  $.post(form.attr('action'), form.serialize(), null, 'json').done(function (r) {
    if (r.success) { location.reload(); } else { $('#createModalContent').html(r); }
  });
};
window.submitUpdateForm = function () {
  var form = $('#updateModalContent').find('form');
  $.post(form.attr('action'), form.serialize(), null, 'json').done(function (r) {
    if (r.success) { location.reload(); } else { $('#updateModalContent').html(r); }
  });
};
JS;
$this->registerJs($js);