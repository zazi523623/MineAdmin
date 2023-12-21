<?php

declare(strict_types=1);
namespace App\Setting\Service;


use App\Setting\Mapper\SettingConfigMapper;
use Hyperf\Cache\Annotation\Cacheable;
use Hyperf\Cache\Annotation\CacheAhead;
use Hyperf\Cache\Annotation\CacheEvict;
use Hyperf\Cache\Listener\DeleteListenerEvent;
use Hyperf\Config\Annotation\Value;
use Hyperf\Redis\Redis;
use Mine\Abstracts\AbstractService;
use Mine\Annotation\DependProxy;
use Mine\Annotation\Transaction;
use Mine\Interfaces\ServiceInterface\ConfigServiceInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

#[DependProxy(values: [ ConfigServiceInterface::class ])]
class SettingConfigService extends AbstractService implements ConfigServiceInterface
{
    /**
     * @var SettingConfigMapper
     */
    public $mapper;

    private EventDispatcherInterface $dispatcher;


    /**
     * @param SettingConfigMapper $mapper
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        SettingConfigMapper $mapper,
        EventDispatcherInterface $eventDispatcher,

    )
    {
        $this->mapper = $mapper;
        $this->dispatcher = $eventDispatcher;
    }

    /**
     * 按key获取配置，并缓存
     */
    #[Cacheable(
        prefix: 'system:config:value',
        value: '_#{key}',
        ttl: 600,
        listener: 'system-config-update'
    )]
    public function getConfigByKey(string $key): ?array
    {
        return $this->mapper->getConfigByKey($key);
    }

    /**
     * 按组的key获取一组配置信息
     * @param string $groupKey
     * @return array|null
     */
    public function getConfigByGroupKey(string $groupKey): ?array
    {
        return $this->mapper->getConfigByGroupKey($groupKey);
    }

    /**
     * 清除缓存
     * @return bool
     */
    #[CacheEvict(
        prefix: 'system:config:value',
        value: '_#{key}',
        all: true
        )
    ]
    public function clearCache(): bool
    {
        return true;
    }

    /**
     * 更新配置
     * @param string $key
     * @param array $data
     * @return bool
     */
    #[CacheEvict(
        prefix: 'system:config:value',
        value: '_#{key}'
    )]
    public function updated(string $key, array $data): bool
    {
        return $this->mapper->updateConfig($key, $data);
    }

    /**
     * 按 keys 更新配置
     * @param array $data
     * @return bool
     */
    #[Transaction]
    public function updatedByKeys(array $data): bool
    {
        foreach ($data as $name => $value) {
            $this->dispatcher->dispatch(new DeleteListenerEvent(
                'system:config:value',
                [
                    'key'   =>  (string)$name
                ]
            ));
            $this->mapper->updateByKey((string) $name, $value);
        }
        return true;
    }

}
