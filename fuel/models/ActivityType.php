<?php

namespace app\modules\fuel\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "activity_table_activity".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $created_by
 * @property string $created_at
 * @property string $updated_at
  * @property int|null $updated_by
  * @property int $is_deleted
 */
class ActivityType extends ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'activity_table_activity';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
           
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required', 'message' => 'نام فعالیت اجباری است.'],
            [['name'], 'string', 'max' => 100, 'tooLong' => 'نام فعالیت نباید بیشتر از 100 کاراکتر باشد.'],
            [['description'], 'string'],
            [['created_by'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['created_by', 'updated_by'], 'integer'],
            [['is_deleted'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'شناسه',
            'name' => 'نام فعالیت',
            'description' => 'توضیحات',
            'created_by' => 'ایجاد شده توسط',
            'created_at' => 'زمان ایجاد',
            'updated_at' => 'زمان به‌روزرسانی',
            'updated_by' => 'آخرین ویرایش توسط',
            'is_deleted' => 'حذف شده',
        ];
    }

    /**
     * Gets a list of activities as an array for dropdowns or other UI elements.
     * @return array
     */
    public static function getActivityList()
    {
        return self::find()
            ->select(['name', 'id'])
            ->indexBy('id')
            ->column();
    }
public function getCreatedByUser()
{
    return $this->hasOne(\app\modules\account\models\User::class, ['UserID' => 'created_by']);
}
public function getUpdatedByUser()
{
    return $this->hasOne(\app\modules\account\models\User::class, ['UserID' => 'updated_by']);
}
/**
 * Get created_at as Jalali date
 * @return string
 */
public function getCreatedAtJalali()
{
    if (!$this->created_at || $this->created_at == '0000-00-00 00:00:00') {
        return '-';
    }
    
    $jDate = new \app\components\jDateTime(true, true, 'Asia/Tehran');
    $timestamp = strtotime($this->created_at);
    $milaDiDate = date('Y/m/d', $timestamp);
    $explode_miladi = explode('/', $milaDiDate);
    $jalaliDate = $jDate->toJalali($explode_miladi[0], $explode_miladi[1], $explode_miladi[2]);
    
    return $jalaliDate[0] . '/' . $jalaliDate[1] . '/' . $jalaliDate[2];
}

/**
 * Get updated_at as Jalali date
 * @return string
 */
public function getUpdatedAtJalali()
{
    if (!$this->updated_at || $this->updated_at == '0000-00-00 00:00:00') {
        return '-';
    }
    
    $jDate = new \app\components\jDateTime(true, true, 'Asia/Tehran');
    $timestamp = strtotime($this->updated_at);
    $milaDiDate = date('Y/m/d', $timestamp);
    $explode_miladi = explode('/', $milaDiDate);
    $jalaliDate = $jDate->toJalali($explode_miladi[0], $explode_miladi[1], $explode_miladi[2]);
    
    return $jalaliDate[0] . '/' . $jalaliDate[1] . '/' . $jalaliDate[2];
}
public static function find()
{
    return parent::find()->andWhere(['is_deleted' => 0]);
}
}