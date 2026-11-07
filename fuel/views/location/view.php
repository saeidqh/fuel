<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'مدیریت سوخت', 'url' => ['fuel/list']];
$this->params['breadcrumbs'][] = ['label' => 'مدیریت لوکیشن‌ها', 'url' => ['list']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="location-view">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-12 text-right mb-3">
            <?= Html::a('ویرایش', ['update', 'id' => $model->id], [
                'class' => 'btn btn-warning',
                'data-toggle' => 'modal',
                'data-target' => '#updateModal',
                'onclick' => 'loadModalContent("' . yii\helpers\Url::to(['update', 'id' => $model->id]) . '", "#updateModal")',
            ]) ?>
            <?= Html::a('حذف', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger',
                'data' => [
                    'confirm' => 'آیا مطمئن هستید که می‌خواهید این لوکیشن را حذف کنید؟',
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
    'label' => 'آخرین کاربر',
    'value' => function($model) {
        if ($model->updated_by) {
            return $model->updatedByUser ? $model->updatedByUser->getFullName() : '-';
        }
        return $model->createdByUser ? $model->createdByUser->getFullName() : '-';
    },
],
                        ],
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>