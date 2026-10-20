<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

$this->title = 'مدیریت فعالیت‌ها';
$this->params['breadcrumbs'][] = ['label' => 'مدیریت سوخت', 'url' => ['fuel/list']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="activity-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-12 text-right mb-3">
            <?= Html::button('ایجاد فعالیت جدید', [
                'class' => 'btn btn-primary',
                'data-toggle' => 'modal',
                'data-target' => '#createModal',
            ]) ?>
            <?= Html::a('بازگشت به لیست سوخت', ['fuel/list'], ['class' => 'btn btn-secondary']) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <?= GridView::widget([
    'dataProvider' => $dataProvider,
   'columns' => [
    'id',  // ← این ستون ID دیتابیس را نشان می‌دهد
    'name',
    'description:ntext',
    // ...

[
    'attribute' => 'created_at',
    'label' => 'تاریخ ایجاد',
    'value' => function($model) {
        if ($model->created_at && $model->created_at != '0000-00-00 00:00:00') {
            $jDate = new \app\components\jDateTime(true, true, 'Asia/Tehran');
            $timestamp = strtotime($model->created_at);
            $jalaliDate = $jDate->toJalali(date('Y', $timestamp), date('m', $timestamp), date('d', $timestamp));
            return $jalaliDate[0] . '/' . $jalaliDate[1] . '/' . $jalaliDate[2] . ' ' . date('H:i', $timestamp);
        }
        return '-';
    },
],
[
    'attribute' => 'created_by',
    'label' => 'آخرین کاربر',
    'value' => function($model) {
        // اگر updated_by پر شده باشد، یعنی ویرایش شده
        if ($model->updated_by) {
            return $model->updatedByUser ? $model->updatedByUser->getFullName() : '-';
        }
        // در غیر این صورت، ایجاد کننده
        return $model->createdByUser ? $model->createdByUser->getFullName() : '-';
    },
],
                            [
                                'class' => 'yii\grid\ActionColumn',
                                'template' => ' {update} {delete}',
                                'buttons' => [
//                                    'view' => function ($url, $model) {
//                                        return Html::a('مشاهده', ['view', 'id' => $model->id], [
//                                            'class' => 'btn btn-info btn-sm',
//                                            'target' => '_blank',
//                                        ]);
//                                    },
                                    'update' => function ($url, $model) {
                                        return Html::button('ویرایش', [
                                            'class' => 'btn btn-warning btn-sm',
                                            'data-toggle' => 'modal',
                                            'data-target' => '#updateModal',
                                            'onclick' => 'loadUpdateModal(' . $model->id . ')',
                                        ]);
                                    },
                                    'delete' => function ($url, $model) {
                                        return Html::a('حذف', ['delete', 'id' => $model->id], [
                                            'class' => 'btn btn-danger btn-sm',
                                            'data' => [
                                                'confirm' => 'آیا مطمئن هستید که می‌خواهید این فعالیت را حذف کنید؟',
                                                'method' => 'post',
                                            ],
                                        ]);
                                    },
                                ],
                            ],
                        ],
                    ]); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Create -->
<div class="modal fade" id="createModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ایجاد فعالیت جدید</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="createModalContent">بارگذاری...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                <button type="button" class="btn btn-primary" onclick="submitCreateForm()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Update -->
<div class="modal fade" id="updateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">ویرایش فعالیت</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="updateModalContent">بارگذاری...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                <button type="button" class="btn btn-primary" onclick="submitUpdateForm()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<script>
// بارگذاری فرم ایجاد در مودال
$('#createModal').on('show.bs.modal', function (e) {
    $.ajax({
        url: '<?= Url::to(['create']) ?>',
        type: 'GET',
        success: function(response) {
            $('#createModalContent').html(response);
        },
        error: function() {
            $('#createModalContent').html('<div class="alert alert-danger">خطا در بارگذاری فرم</div>');
        }
    });
});

// بارگذاری فرم ویرایش در مودال
// بارگذاری فرم ویرایش در مودال
function loadUpdateModal(id) {
    $.ajax({
        url: '<?= Url::to(['update', 'id' => '']) ?>' + id,  // تغییر در این خط
        type: 'GET',
        success: function(response) {
            $('#updateModalContent').html(response);
            $('#updateModal').modal('show');
        },
        error: function() {
            $('#updateModalContent').html('<div class="alert alert-danger">خطا در بارگذاری فرم</div>');
        }
    });
}

// ارسال فرم ایجاد
function submitCreateForm() {
    var form = $('#createModalContent').find('form');
    $.ajax({
        url: form.attr('action'),
        type: 'POST',
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#createModal').modal('hide');
                location.reload(); // رفرش صفحه برای نمایش رکورد جدید
            } else {
                $('#createModalContent').html(response);
            }
        },
        error: function() {
            alert('خطا در ذخیره‌سازی');
        }
    });
}

// ارسال فرم ویرایش
function submitUpdateForm() {
    var form = $('#updateModalContent').find('form');
    $.ajax({
        url: form.attr('action'),
        type: 'POST',
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#updateModal').modal('hide');
                location.reload(); // رفرش صفحه برای نمایش تغییرات
            } else {
                $('#updateModalContent').html(response);
            }
        },
        error: function() {
            alert('خطا در ذخیره‌سازی');
        }
    });
}
</script>