<?php

namespace SlaveMarket\Lease;

/**
 * Сервис работы с контрактами
 *
 * @package SlaveMarket\Lease
 */
class LeaseContractsService
{
    public $leaseHourService;

    public function __construct(LeaseHourService $leaseHourService)
    {
        $this->leaseHourService = $leaseHourService;
    }

    /**
     * Проверка занятости рабов на период создаваемого контракта
     *
     * @param LeaseContractsRepository $leaseContractsRepository
     * @param LeaseContract $contract
     * @throws LeaseException
     */
    public function checkAvailabilitySlave(
        LeaseContractsRepository $leaseContractsRepository,
        LeaseContract $contract
    )
    {
        // Собираем контракты на раба за даты создаваемого контракта
        $contracts = $leaseContractsRepository->getForSlave(
            $contract->slave->getId(),
            $contract->leasedHours[0]->getDate(),
            end($contract->leasedHours)->getDate()
        );

        $busyPeriods = $this->getSlaveBusyPeriods($contracts, $contract);
        if (!empty($busyPeriods)) {
            throw new LeaseException('Раб ' . $contract->slave->getName() . ' занят в периоды: ' . implode(',', $busyPeriods));
        }
    }

    /**
     * Собирает информацию о занятых периодах для нового контракта из списка контрактов
     *
     * @param LeaseContract[]|null $contracts
     * @param LeaseContract $newContract
     * @return array
     * @throws LeaseException
     */
    protected function getSlaveBusyPeriods(?array $contracts, LeaseContract $newContract): array
    {
        $periods = [];
        $contracts = $contracts ?? [];
        foreach ($contracts as $contract) {
            $isAddPeriod = (int)$contract->master->isVIP() >= (int)$newContract->master->isVIP();
            if ($isAddPeriod) {
                $crossHours = $this->leaseHourService->getCrossHours($contract->leasedHours, $newContract->leasedHours);
                if (!empty($crossHours)) {
                    $periods[] = ' с ' . $contract->leasedHours[0]->getDateString() .
                        ' по ' . end($contract->leasedHours)->getDateString();
                } elseif (count($contract->leasedHours) + count($newContract->leasedHours) > $this->leaseHourService::MAX_HOUR_JOB_IN_DAY) {
                    // Если рабочие часы не перекрываются, значит периоды меньше рабочего для - проверяем, чтобы рабу
                    // не пришлось работать более 16 часов на двух господ.
                    throw new LeaseException(
                        'У раба ' . $contract->slave->getName() . ' уже занято ' .
                        (count($contract->leasedHours)) . ' рабочих часов. Но рабочий день не может превышать ' .
                        $this->leaseHourService::MAX_HOUR_JOB_IN_DAY . ' рабочих часов.'
                    );
                }
            }
        }

        return $periods;
    }
}