<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

?>

<div class="location-update">
    <?php $form = ActiveForm::begin([
        'id' => 'location-update-form',
        'action' => ['update', 'id' => $model->id],
        'method' => 'post',
        'layout' => 'horizontal',
        'fieldConfig' => [
            'horizontalCssClasses' => [
                'label' => 'col-lg-3',
                'offset' => 'col-lg-offset-1',
                'wrapper' => 'col-lg-6',
            ],
        ],
    ]); ?>

    <?= $form->field($model, 'name')->textInput(['maxlength' => true, 'placeholder' => 'نام لوکیشن']) ?>
    
    <?= $form->field($model, 'description')->textarea(['rows' => 3, 'placeholder' => 'توضیحات لوکیشن']) ?>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($model, 'latitude')->textInput(['placeholder' => 'مثال: 35.6892', 'step' => 'any']) ?>
            <div class="help-block" style="color: #888; font-size: 12px;">(درجه) محدوده: -90 تا 90</div>
        </div>
        <div class="col-md-6">
            <?= $form->field($model, 'longitude')->textInput(['placeholder' => 'مثال: 51.3890', 'step' => 'any']) ?>
            <div class="help-block" style="color: #888; font-size: 12px;">(درجه) محدوده: -180 تا 180</div>
        </div>
    </div>

    <?php ActiveForm::end(); ?>
</div>