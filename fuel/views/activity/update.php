<?php

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

?>

<div class="activity-update">
    <?php $form = ActiveForm::begin([
        'id' => 'activity-update-form',
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

    <?= $form->field($model, 'name')->textInput(['maxlength' => true, 'placeholder' => 'نام فعالیت']) ?>
    
    <?= $form->field($model, 'description')->textarea(['rows' => 5, 'placeholder' => 'توضیحات فعالیت (اختیاری)']) ?>

    <?php ActiveForm::end(); ?>
</div>