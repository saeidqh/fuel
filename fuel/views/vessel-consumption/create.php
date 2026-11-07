<?php
use app\components\PageHeader;

$this->title = 'ثبت مصرف سوخت شناور';
$this->params['breadcrumbs'][] = $this->title;

echo PageHeader::widget([
    'title' => $this->title,
]);

$this->registerCss('#content > div:first-child{padding:0}');
?>
<div class="vessel-consumption-create">
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