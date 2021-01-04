<?php

namespace SlaveMarket\Lease;

use SlaveMarket\MastersRepository;
use SlaveMarket\SlavesRepository;
use DateTime;
/**
 * Операция "Арендовать раба"
 *
 * @package SlaveMarket\Lease
 */
class LeaseOperation
{
    /**
     * @var LeaseContractsRepository
     */
    protected $contractsRepository;

    /**
     * @var MastersRepository
     */
    protected $mastersRepository;

    /**
     * @var SlavesRepository
     */
    protected $slavesRepository;

    /**
     * @var LeaseHourService
     */
    protected $leaseHourService;

    /**
     * LeaseOperation constructor.
     *
     * @param LeaseContractsRepository $contractsRepo
     * @param MastersRepository $mastersRepo
     * @param SlavesRepository $slavesRepo
     */
    public function __construct(LeaseContractsRepository $contractsRepo, MastersRepository $mastersRepo, SlavesRepository $slavesRepo)
    {
        $this->contractsRepository   = $contractsRepo;
        $this->mastersRepository     = $mastersRepo;
        $this->slavesRepository      = $slavesRepo;
        $this->leaseHourService      = new LeaseHourService();
        $this->leaseContractsService = new LeaseContractsService($this->leaseHourService);
    }

    /**
     * Выполнить операцию
     *
     * @param LeaseRequest $request
     * @return LeaseResponse
     * @throws LeaseException
     *
     */
    public function run(LeaseRequest $request): LeaseResponse
    {
        $response = new LeaseResponse();

        try {
            $startTime = DateTime::createFromFormat('Y-m-d H', $request->timeFrom);
            $endTime = DateTime::createFromFormat('Y-m-d H', $request->timeTo);
            // Создаем список рабочих часов по запросу
            $hours = $this->leaseHourService->getLeaseHours($startTime, $endTime);

            $slave = $this->slavesRepository->getById($request->slaveId);
            // Вычисляем цену контракта
            $price = count($hours) * $slave->getPricePerHour();

            $newContract = new LeaseContract(
                $this->mastersRepository->getById($request->masterId),
                $slave,
                $price,
                $hours
            );
            // Проверяем новый контракт на предмет занятости раба
            $this->leaseContractsService->checkAvailabilitySlave($this->contractsRepository, $newContract);
            // Если метод сбора часов аренды и проверки занятости не выбросили ошибку - создаем контракт
            $response->setLeaseContract($newContract);
        } catch (LeaseException $e) {
            $response->addError($e->getMessage());
        }

        return $response;
    }
}