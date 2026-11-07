<?php

namespace app\modules\fuel;

use Yii;

class FuelModule extends \yii\base\Module {

    public $controllerNamespace = 'app\modules\fuel\controllers';
    public $defaultRoute = 'fuel';

    public function init() {
        parent::init();
    }

}