<?php
use yii\helpers\Html;

$this->title = 'ویرایش گزارش مصرف شماره ' . $report->id;
?>
<div class="vessel-consumption-update">
    <h3><?= Html::encode($this->title) ?></h3>
    <?= $this->render('_form', [
        'report' => $report,
        'vessels' => $vessels,
        'locations' => $locations,
        'activities' => $activities,
        'itemRows' => $itemRows,
        'transactionRows' => $transactionRows,
        'modeOptions' => $modeOptions,
    ]) ?>
</div>