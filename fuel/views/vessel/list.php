<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

$this->title = 'مدیریت شناورها';
$this->params['breadcrumbs'][] = ['label' => 'مدیریت سوخت', 'url' => ['fuel/list']];
$this->params['breadcrumbs'][] = $this->title;
?>

<style>
#createModal .modal-dialog,
#updateModal .modal-dialog {
    width: 75% !important;
    max-width: 1000px !important;
}
    /* استایل برای هماهنگی با فرم */
    .vessel-index h1 {
        color: #4facfe;
        font-weight: bold;
        margin-bottom: 30px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(0,0,0,0.08);
    }
    .card-header-custom {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 15px 15px 0 0;
        font-weight: bold;
        font-size: 18px;
    }
    /* استایل Badgeها برای وضعیت */
    .badge-on-hire { background-color: #2ecc71; color: white; padding: 5px 10px; border-radius: 5px; }
    .badge-off-hire { background-color: #e74c3c; color: white; padding: 5px 10px; border-radius: 5px; }
    
    /* استایل دکمه‌های جدول */
    .btn-action { margin: 2px; border-radius: 5px; }
</style>

<div class="vessel-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row mb-4">
        <div class="col-md-12 text-right">
            <?= Html::a('بازگشت به لیست سوخت', ['fuel/list'], ['class' => 'btn btn-default btn-sm']) ?>
            <?= Html::button('➕ ایجاد شناور جدید', [
                'class' => 'btn btn-primary',
                'data-toggle' => 'modal',
                'data-target' => '#createModal',
                'style' => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;'
            ]) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header-custom">
                    <i class="glyphicon glyphicon-list-alt"></i> لیست شناورهای فعال
                </div>
                <div class="card-body">
                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'tableOptions' => ['class' => 'table table-hover'],
                        'columns' => [
                            [
                                'attribute' => 'id',
                                'label' => 'شناسه',
                                'headerOptions' => ['style' => 'width: 50px;'],
                            ],
                            [
                                'attribute' => 'name',
                                'label' => 'نام شناور',
                                'headerOptions' => ['style' => 'width: 150px;'],
                            ],
                            'code',
                            [
                                'attribute' => 'status',
                                'label' => 'وضعیت',
                                'format' => 'raw',
                                'value' => function ($model) {
                                    if ($model->status === \app\modules\fuel\models\VesselType::STATUS_ON_HIRE) {
                                        return '<span class="badge-on-hire">On Hire</span>';
                                    }
                                    return '<span class="badge-off-hire">Off Hire</span>';
                                },
                            ],
                            'engine_count',
                            [
                                'attribute' => 'vessel_speed',
                                'label' => 'سرعت (نات)',
                                'value' => function($model) {
                                    return $model->vessel_speed ? number_format($model->vessel_speed, 1) : '-';
                                },
                            ],
                            [
                                'attribute' => 'created_at',
                                'label' => 'تاریخ ایجاد',
                                'value' => function($model) {
                                    return $model->getCreatedAtJalali();
                                },
                            ],
                            [
                                'class' => 'yii\grid\ActionColumn',
                                'template' => '{update} {delete}',
                                'header' => 'عملیات',
                                'buttons' => [
                                    'update' => function ($url, $model) {
                                        return Html::button('<i class="glyphicon glyphicon-pencil"></i>', [
                                            'class' => 'btn btn-warning btn-sm btn-action',
                                            'title' => 'ویرایش',
                                            'data-toggle' => 'modal',
                                            'data-target' => '#updateModal',
                                            'onclick' => 'loadUpdateModal(' . $model->id . ')',
                                        ]);
                                    },
                                    'delete' => function ($url, $model) {
                                        return Html::a('<i class="glyphicon glyphicon-trash"></i>', $url, [
                                            'class' => 'btn btn-danger btn-sm btn-action',
                                            'title' => 'حذف',
                                            'data' => [
                                                'confirm' => 'آیا مطمئن هستید که می‌خواهید این شناور را حذف کنید؟',
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

<!-- Modal for Create - استفاده از modal-xl برای فرم‌های سنگین -->
<div class="modal fade" id="createModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl"> <!-- تغییر به xl -->
        <div class="modal-content">
            <div class="modal-header" style="background: #f8f9fa;">
                <h5 class="modal-title"><i class="glyphicon glyphicon-plus"></i> ایجاد شناور جدید</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="createModalContent">
                    <div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i> در حال بارگذاری...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                <button type="button" class="btn btn-primary" onclick="submitCreateForm()">ذخیره اطلاعات</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Update -->
<div class="modal fade" id="updateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl"> <!-- تغییر به xl -->
        <div class="modal-content">
            <div class="modal-header" style="background: #f8f9fa;">
                <h5 class="modal-title"><i class="glyphicon glyphicon-edit"></i> ویرایش مشخصات شناور</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="updateModalContent">
                    <div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i> در حال بارگذاری...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">بستن</button>
                <button type="button" class="btn btn-primary" onclick="submitUpdateForm()">بروزرسانی</button>
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
function loadUpdateModal(id) {
    $.ajax({
        url: '<?= Url::to(['update', 'id' => '']) ?>' + id,
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
function submitVesselForm(modalSelector) {
    var modal = $(modalSelector);
    var form = modal.find('form').first();

    if (!form.length || form.data('submitting')) {
        return;
    }

    var errors = form.find('.vessel-save-errors');

    if (!errors.length) {
        errors = $('<div>', {
            class: 'alert alert-danger vessel-save-errors',
            role: 'alert'
        }).prependTo(form);
    }

    errors.empty().hide();
    form.data('submitting', true);

    var buttons = modal.find('.modal-footer .btn-primary');
    buttons.prop('disabled', true);

    $.ajax({
        url: form.attr('action'),
        type: 'POST',
        data: new FormData(form[0]),
        processData: false,
        contentType: false,
        dataType: 'json'
    }).done(function (response) {
        if (response.success) {
            window.location.reload();
            return;
        }

        $('<div>').text(response.message || 'ذخیره انجام نشد.').appendTo(errors);

        $.each(response.errors || {}, function (attribute, messages) {
            $.each(messages, function (index, message) {
                $('<div>').text(message).appendTo(errors);
            });
        });

        errors.show();
    }).fail(function () {
        errors.text('ارتباط یا پردازش درخواست با خطا مواجه شد.').show();
    }).always(function () {
        form.data('submitting', false);
        buttons.prop('disabled', false);
    });
}

function submitCreateForm() {
    $('#createModalContent').find('form').first().trigger('submit');
}

function submitUpdateForm() {
    $('#updateModalContent').find('form').first().trigger('submit');
}
</script>