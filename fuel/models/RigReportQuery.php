<?php

namespace app\modules\fuel\models;

use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * Query class for rig fuel-consumption reports.
 *
 * مسئولیت این کلاس فقط خواندن، تجمیع و آماده‌سازی
 * داده‌های گزارش مصرف سوخت دکل است.
 */
class RigReportQuery
{
    /**
     * خروجی کامل مورد نیاز داشبورد گزارش دکل.
     *
     * @param array $filters
     * @return array
     */
    public function getDashboardData(array $filters)
    {
        return [
            'summary' => $this->getSummary($filters),
            'daily_consumption' => $this->getDailyConsumption($filters),
            'daily_difference' => $this->getDailyDifference($filters),
            'daily_operating_hours' => $this->getDailyOperatingHours($filters),
        ];
    }

    /**
     * خلاصه گزارش در بازه انتخاب‌شده.
     *
     * @param array $filters
     * @return array
     */
    public function getSummary(array $filters)
    {
        $row = $this->createBaseQuery($filters)
            ->select([
                'reports_count' => new Expression('COUNT(*)'),
                'report_days' => new Expression('COUNT(DISTINCT date)'),

                'total_operating_hours' => new Expression(
                    'COALESCE(SUM(operating_hours), 0)'
                ),

                'actual_consumption' => new Expression(
                    'COALESCE(SUM(fuel_consumption_user_input), 0)'
                ),

                'ideal_consumption' => new Expression(
                    'COALESCE(SUM(total_ideal_consumption), 0)'
                ),

                'consumption_difference' => new Expression(
                    'COALESCE(SUM(consumption_difference), 0)'
                ),

                'fuel_received' => new Expression(
                    'COALESCE(SUM(fuel_received), 0)'
                ),

                'fuel_transferred' => new Expression(
                    'COALESCE(SUM(fuel_transferred), 0)'
                ),

                'fuel_used' => new Expression(
                    'COALESCE(SUM(fuel_used), 0)'
                ),
            ])
            ->asArray()
            ->one();

        $row = $row ?: [];

        $actualConsumption = (float)($row['actual_consumption'] ?? 0);
        $idealConsumption = (float)($row['ideal_consumption'] ?? 0);
        $difference = (float)($row['consumption_difference'] ?? 0);

        /*
         * درصد انحراف:
         * (مصرف واقعی - مصرف ایده‌آل) / مصرف ایده‌آل * 100
         *
         * اگر مصرف ایده‌آل صفر باشد، درصد قابل محاسبه نیست؛
         * برای جلوگیری از تقسیم بر صفر، صفر برمی‌گردانیم.
         */
        $deviationPercent = $idealConsumption > 0
            ? ($difference / $idealConsumption) * 100
            : 0;

        return [
            'reports_count' => (int)($row['reports_count'] ?? 0),
            'report_days' => (int)($row['report_days'] ?? 0),

            'total_operating_hours' => round(
                (float)($row['total_operating_hours'] ?? 0),
                2
            ),

            'actual_consumption' => round($actualConsumption, 2),

            'ideal_consumption' => round($idealConsumption, 2),

            'consumption_difference' => round($difference, 2),

            'deviation_percent' => round($deviationPercent, 2),

            /*
             * این سه مقدار فعلاً برای کارت‌های احتمالی آینده نگه داشته شده‌اند.
             * fuel_used با مصرف واردشده توسط کاربر یکی نیست؛
             * fuel_used مربوط به گردش/موجودی سوخت است.
             */
            'fuel_received' => round(
                (float)($row['fuel_received'] ?? 0),
                2
            ),

            'fuel_transferred' => round(
                (float)($row['fuel_transferred'] ?? 0),
                2
            ),

            'fuel_used' => round(
                (float)($row['fuel_used'] ?? 0),
                2
            ),
        ];
    }

    /**
     * مصرف واقعی، ایده‌آل و اختلاف مصرف به تفکیک روز.
     *
     * این خروجی برای نمودار خطی:
     * «مصرف واقعی در برابر مصرف ایده‌آل»
     * استفاده می‌شود.
     *
     * @param array $filters
     * @return array
     */
    public function getDailyConsumption(array $filters)
    {
        $rows = $this->createBaseQuery($filters)
            ->select([
                'report_date' => 'date',

                'actual_consumption' => new Expression(
                    'COALESCE(SUM(fuel_consumption_user_input), 0)'
                ),

                'ideal_consumption' => new Expression(
                    'COALESCE(SUM(total_ideal_consumption), 0)'
                ),

                'consumption_difference' => new Expression(
                    'COALESCE(SUM(consumption_difference), 0)'
                ),
            ])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        return $this->normalizeDailyConsumptionRows($rows);
    }

    /**
     * اختلاف مصرف واقعی و ایده‌آل به تفکیک روز.
     *
     * مقدار مثبت: مصرف واقعی بیشتر از ایده‌آل بوده است.
     * مقدار منفی: مصرف واقعی کمتر از ایده‌آل بوده است.
     *
     * @param array $filters
     * @return array
     */
    public function getDailyDifference(array $filters)
    {
        $rows = $this->createBaseQuery($filters)
            ->select([
                'report_date' => 'date',

                'consumption_difference' => new Expression(
                    'COALESCE(SUM(consumption_difference), 0)'
                ),
            ])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'report_date' => $row['report_date'],
                'consumption_difference' => round(
                    (float)$row['consumption_difference'],
                    2
                ),
            ];
        }

        return $result;
    }

    /**
     * ساعات فعالیت تجهیزات دکل به تفکیک روز.
     *
     * @param array $filters
     * @return array
     */
    public function getDailyOperatingHours(array $filters)
    {
        $rows = $this->createBaseQuery($filters)
            ->select([
                'report_date' => 'date',

                'operating_hours' => new Expression(
                    'COALESCE(SUM(operating_hours), 0)'
                ),
            ])
            ->groupBy(['date'])
            ->orderBy(['date' => SORT_ASC])
            ->asArray()
            ->all();

        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'report_date' => $row['report_date'],
                'operating_hours' => round(
                    (float)$row['operating_hours'],
                    2
                ),
            ];
        }

        return $result;
    }

    /**
     * Query پایه برای تمام گزارش‌های دکل.
     *
     * فیلترهای قابل قبول:
     *
     * [
     *     'rig_id' => 12,
     *     'date_from' => '2026-03-21',
     *     'date_to' => '2026-04-20',
     * ]
     *
     * @param array $filters
     * @return ActiveQuery
     */
    private function createBaseQuery(array $filters)
    {
        $query = RigFuelConsumption::find();

        if (!empty($filters['rig_id'])) {
            $query->andWhere([
                'rigIdRef' => (int)$filters['rig_id'],
            ]);
        }

        if (!empty($filters['date_from'])) {
            $query->andWhere([
                '>=',
                'date',
                $filters['date_from'],
            ]);
        }

        if (!empty($filters['date_to'])) {
            $query->andWhere([
                '<=',
                'date',
                $filters['date_to'],
            ]);
        }

        return $query;
    }

    /**
     * یکسان‌سازی نوع داده‌های خروجی مصرف روزانه.
     *
     * @param array $rows
     * @return array
     */
    private function normalizeDailyConsumptionRows(array $rows)
    {
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'report_date' => $row['report_date'],

                'actual_consumption' => round(
                    (float)$row['actual_consumption'],
                    2
                ),

                'ideal_consumption' => round(
                    (float)$row['ideal_consumption'],
                    2
                ),

                'consumption_difference' => round(
                    (float)$row['consumption_difference'],
                    2
                ),
            ];
        }

        return $result;
    }
}