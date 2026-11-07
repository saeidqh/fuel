<?php

use yii\helpers\Html;

/** @var array $summary */
/** @var bool $ready */

$ready = $ready ?? false;

$cards = [
    'operating_hours' => ['جمع کارکرد تجهیزات', 'تجهیز‌ساعت'],
    'actual_consumption' => ['مصرف واقعی', 'لیتر'],
    'ideal_consumption' => ['مصرف ایده‌آل', 'لیتر'],
    'difference' => ['اختلاف مصرف', 'لیتر'],
];
?>

<div class="panel panel-primary">
    <div class="panel-body">
        <h4 class="section-title">خلاصه محاسبات مصرف</h4>

        <div class="row">
            <?php foreach ($cards as $key => $card): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="summary-card">
                        <strong><?= Html::encode($card[0]) ?></strong>

                        <span data-summary="<?= Html::encode($key) ?>">
                            <?= $ready
                                ? Html::encode(number_format($summary[$key], 2, '.', ''))
                                : '—' ?>
                        </span>

                        <small><?= Html::encode($card[1]) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

                <p class="text-muted">
                    اختلاف مصرف = مصرف واقعی کل − مصرف مبنای مقایسه.
                    برای موتور و ژنراتور از نرخ استاندارد استفاده می‌شود؛
                    برای تجهیزات جانبی بدون نرخ، مقدار مبنا برابر مصرف واقعی است.
                </p>

        <div id="rig-calculation-status" class="text-muted" aria-live="polite">
            محاسبات نهایی و کنترل موجودی هنگام ثبت دوباره انجام می‌شود.
        </div>

        <div id="rig-calculation-errors" class="alert alert-warning" hidden></div>
    </div>
</div>