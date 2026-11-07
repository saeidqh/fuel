<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'مدیریت سوخت', 'url' => ['fuel/list']];
$this->params['breadcrumbs'][] = ['label' => 'مدیریت فعالیت‌ها', 'url' => ['list']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="activity-view">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-12 text-right mb-3">
            <?= Html::a('ویرایش', ['update', 'id' => $model->id], [
                'class' => 'btn btn-warning',
                'data-toggle' => 'modal',
                'data-target' => '#updateModal',
                'onclick' => 'loadUpdateModalFromView(' . $model->id . ')',
            ]) ?>
            <?= Html::a('حذف', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => 'آیا مطمئن هستید که می‌خواهید این فعالیت را حذف کنید؟',
                    'method' => 'post',
                ],
            ]) ?>
            <?= Html::a('بازگشت به لیست', ['list'], ['class' => 'btn btn-secondary']) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <?= DetailView::widget([
                        'model' => $model,
                        'attributes' => [
                            'id',
                            'name',
                            'description:ntext',
                            'created_at:datetime',
                            'updated_at:datetime',
                            [
                                'attribute' => 'created_by',
                                'value' => function($model) {
                                    return $model->createdBy ? $model->createdBy->username : '-';
                                },
                            ],
                        ],
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Update (از صفحه view) -->
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
                <button type="button" class="btn btn-primary" onclick="submitUpdateFormFromView()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<script>
// بارگذاری فرم ویرایش در مودال از صفحه view
function loadUpdateModalFromView(id) {
    $.ajax({
        url: '<?= yii\helpers\Url::to(['update']) ?>?id=' + id,
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

// ارسال فرم ویرایش
function submitUpdateFormFromView() {
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