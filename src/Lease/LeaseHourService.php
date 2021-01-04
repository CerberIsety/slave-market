<?php

namespace SlaveMarket\Lease;

use DateTime, DateInterval;

/**
 * Сервис для расчета рабочих часов аренды
 *
 * @package SlaveMarket\Lease
 */
class LeaseHourService
{
    const MAX_HOUR_JOB_IN_DAY = 16; // Максимальная продолжительность рабочего дня в часах

    /**
     * Возвращает массив арендованных часов
     *
     * @param DateTime $startLease
     * @param DateTime $endLease
     * @return array
     * @throws LeaseException
     */
    public function getLeaseHours(DateTime $startLease, DateTime $endLease): array
    {
        $diff = $startLease->diff($endLease);
        $this->checkDiff($diff);

        // Вычисляем количество рабочих часов
        $hours = $diff->days * self::MAX_HOUR_JOB_IN_DAY + $diff->h + ($diff->i > 0 ? 1 : 0);
        if ($hours == 0) {
            $hours = 1;
        }

        $result = [];
        for ($i = 0; $i < $hours; $i++) {
            $result[] = new LeaseHour($startLease->modify('+1 hour'));
        }

        return $result;
    }

    /**
     * Возвращает список занятых часов для нового списка рабочих часов
     *
     * @param LeaseHour[] $existingHours
     * @param LeaseHour[] $newHours
     * @return LeaseHour[]
     */
    public function getCrossHours(array $existingHours, array $newHours)
    {
        $result = [];
        foreach ($existingHours as $existsHour) {
            foreach ($newHours as $newHour) {
                if ($existsHour->getDateTime() == $newHour->getDateTime()) {
                    $result = $existsHour;
                }
            }
        }

        return $result;
    }

    /**
     * Проверка интервала аренды на основные ошибки
     *
     * @param DateInterval $diff
     * @throws LeaseException
     */
    protected function checkDiff(DateInterval $diff)
    {
        if ($diff->invert > 0) {
            throw new LeaseException('Начало аренды старше окончания');
        }
        if ($diff->h > self::MAX_HOUR_JOB_IN_DAY) {
            throw new LeaseException('Рабочий день раба не должен превышать ' . self::MAX_HOUR_JOB_IN_DAY . ' часов.');
        }
    }
}